<?php

declare(strict_types=1);

require __DIR__ . '/../src/Bootstrap.php';

use DuelDesk\Database\Db;
use DuelDesk\Repositories\GameRepository;
use DuelDesk\Repositories\MatchRepository;
use DuelDesk\Repositories\PlayerRepository;
use DuelDesk\Repositories\TeamMemberRepository;
use DuelDesk\Repositories\TeamRepository;
use DuelDesk\Repositories\TournamentPlayerRepository;
use DuelDesk\Repositories\TournamentRepository;
use DuelDesk\Repositories\TournamentTeamRepository;
use DuelDesk\Repositories\UserRepository;
use DuelDesk\Services\BracketGenerator;

final class DemoSeeder
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly UserRepository $uRepo = new UserRepository(),
        private readonly GameRepository $gRepo = new GameRepository(),
        private readonly TournamentRepository $tRepo = new TournamentRepository(),
        private readonly PlayerRepository $pRepo = new PlayerRepository(),
        private readonly TournamentPlayerRepository $tpRepo = new TournamentPlayerRepository(),
        private readonly TeamRepository $teamRepo = new TeamRepository(),
        private readonly TeamMemberRepository $tmRepo = new TeamMemberRepository(),
        private readonly TournamentTeamRepository $ttRepo = new TournamentTeamRepository(),
        private readonly MatchRepository $mRepo = new MatchRepository(),
        private readonly BracketGenerator $gen = new BracketGenerator(),
    ) {
    }

    public function run(): void
    {
        $pwHash = password_hash('password123', PASSWORD_DEFAULT);
        if (!is_string($pwHash) || $pwHash === '') {
            throw new RuntimeException('password_hash failed');
        }

        $adminId = $this->firstAdminId();
        if ($adminId <= 0) {
            $adminId = $this->ensureUser('admin', $pwHash, 'admin');
            fwrite(STDOUT, "Created admin user: admin / password123\n");
        }

        $imgW = $this->envInt('GAME_IMAGE_WIDTH', 512);
        $imgH = $this->envInt('GAME_IMAGE_HEIGHT', 512);

        $sf6 = $this->ensureGame('Street Fighter 6', $imgW, $imgH, [96, 165, 250, 255]);
        $cs2 = $this->ensureGame('Counter-Strike 2', $imgW, $imgH, [251, 191, 36, 255]);

        // 1) Solo double elim (8 players)
        $tSolo = $this->ensureTournament(
            'Demo DE Solo (8 joueurs)',
            $adminId,
            (int)$sf6['id'],
            (string)$sf6['name'],
            'double_elim',
            'solo',
            null,
            'published'
        );
        $this->seedSoloPlayers($tSolo, 'dd_solo_', 8, $pwHash);
        $this->ensureGenerated($tSolo, 'double_elim', 'solo');

        // 2) Team double elim (8 teams, 2 per team)
        $tTeam = $this->ensureTournament(
            'Demo DE Team (2v2, 8 equipes)',
            $adminId,
            (int)$sf6['id'],
            (string)$sf6['name'],
            'double_elim',
            'team',
            2,
            'published'
        );
        $this->seedTeams($tTeam, 8, 2, 'dd_team2_', $pwHash, 'Team');
        $this->ensureGenerated($tTeam, 'double_elim', 'team');

        // 3) CS2 double elim (4 teams, 5 per team)
        $tCs2 = $this->ensureTournament(
            'Demo CS2 DE (5v5, 4 equipes)',
            $adminId,
            (int)$cs2['id'],
            (string)$cs2['name'],
            'double_elim',
            'team',
            5,
            'published'
        );
        $this->seedTeams($tCs2, 4, 5, 'dd_cs2_', $pwHash, 'CS2');
        $this->ensureGenerated($tCs2, 'double_elim', 'team');

        fwrite(STDOUT, "\nSeed done.\n");
        fwrite(STDOUT, "Tournois:\n");
        fwrite(STDOUT, " - /tournaments/{$tSolo} (solo DE, users dd_solo_01..08)\n");
        fwrite(STDOUT, " - /tournaments/{$tTeam} (team DE 2v2, users dd_team2_01..16)\n");
        fwrite(STDOUT, " - /tournaments/{$tCs2} (CS2 team DE 5v5, users dd_cs2_01..20)\n");
        fwrite(STDOUT, "Password for all demo users: password123\n");
    }

    private function firstAdminId(): int
    {
        $id = $this->pdo->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1")->fetchColumn();
        $id = is_int($id) || is_string($id) ? (int)$id : 0;
        return $id > 0 ? $id : 0;
    }

    private function ensureUser(string $username, string $pwHash, string $role = 'user'): int
    {
        $existing = $this->uRepo->findByUsername($username);
        if (is_array($existing)) {
            $id = (int)($existing['id'] ?? 0);
            if ($id > 0 && $role !== '' && ($existing['role'] ?? '') !== $role) {
                $this->uRepo->setRole($id, $role);
            }
            return $id;
        }

        return $this->uRepo->create($username, $pwHash, $role);
    }

    /** @return array<string, mixed> */
    private function ensureGame(string $name, int $w, int $h, array $rgba): array
    {
        $existing = $this->gRepo->findByName($name);
        if (is_array($existing)) {
            return $existing;
        }

        $slug = $this->gRepo->uniqueSlug($name);
        $relDir = '/uploads/games';
        $absDir = DUELDESK_ROOT . '/public' . $relDir;
        if (!is_dir($absDir) && !@mkdir($absDir, 0775, true)) {
            throw new RuntimeException("Cannot create: {$absDir}");
        }

        $filename = $slug . '.png';
        $absPath = $absDir . '/' . $filename;
        if (!is_file($absPath)) {
            $this->writeSolidPng($absPath, $w, $h, $rgba);
            @chmod($absPath, 0644);
        }

        $imagePath = $relDir . '/' . $filename;
        $id = $this->gRepo->create($name, $slug, $imagePath, $w, $h, 'image/png');
        $row = $this->gRepo->findById($id);
        if (!is_array($row)) {
            throw new RuntimeException("Game create failed: {$name}");
        }

        return $row;
    }

    private function ensureTournament(
        string $name,
        int $ownerUserId,
        int $gameId,
        string $gameName,
        string $format,
        string $participantType,
        ?int $teamSize,
        string $status
    ): int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM tournaments WHERE name = :name LIMIT 1');
        $stmt->execute(['name' => $name]);
        $id = $stmt->fetchColumn();
        $id = is_int($id) || is_string($id) ? (int)$id : 0;
        if ($id > 0) {
            return $id;
        }

        return $this->tRepo->create($ownerUserId, $gameId, $gameName, $name, $format, $participantType, $teamSize, $status, null);
    }

    private function seedSoloPlayers(int $tournamentId, string $prefix, int $count, string $pwHash): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $username = sprintf('%s%02d', $prefix, $i);
            $uid = $this->ensureUser($username, $pwHash, 'user');
            $pid = $this->pRepo->ensureForUser($uid, $username);

            $this->tpRepo->add($tournamentId, $pid);
            $this->tpRepo->setSeed($tournamentId, $pid, $i);
        }
    }

    private function seedTeams(int $tournamentId, int $teamCount, int $teamSize, string $userPrefix, string $pwHash, string $teamPrefix): void
    {
        $userIndex = 1;

        for ($i = 1; $i <= $teamCount; $i++) {
            $teamName = sprintf('%s %02d', $teamPrefix, $i);
            $memberIds = [];

            for ($m = 1; $m <= $teamSize; $m++) {
                $username = sprintf('%s%02d', $userPrefix, $userIndex);
                $userIndex++;
                $memberIds[] = $this->ensureUser($username, $pwHash, 'user');
            }

            $captainId = $memberIds[0] ?? 0;
            if ($captainId <= 0) {
                throw new RuntimeException('Invalid captain id');
            }

            $teamId = $this->ensureTeam($tournamentId, $teamName, $captainId);

            foreach ($memberIds as $idx => $uid) {
                if ($uid <= 0) {
                    continue;
                }
                if ($this->tmRepo->isUserInTeam($teamId, $uid)) {
                    continue;
                }
                $this->tmRepo->addMember($teamId, $uid, $idx === 0 ? 'captain' : 'member');
            }

            $this->ttRepo->add($tournamentId, $teamId);
            $this->ttRepo->setSeed($tournamentId, $teamId, $i);
        }
    }

    private function ensureTeam(int $tournamentId, string $teamName, int $createdByUserId): int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM teams WHERE tournament_id = :tid AND name = :name LIMIT 1');
        $stmt->execute(['tid' => $tournamentId, 'name' => $teamName]);
        $id = $stmt->fetchColumn();
        $id = is_int($id) || is_string($id) ? (int)$id : 0;
        if ($id > 0) {
            return $id;
        }

        $slug = $this->teamRepo->uniqueSlug($tournamentId, $teamName);
        $code = $this->teamRepo->generateUniqueJoinCode(10);
        return $this->teamRepo->create($tournamentId, $teamName, $slug, $code, $createdByUserId);
    }

    private function ensureGenerated(int $tournamentId, string $format, string $participantType): void
    {
        if ($this->mRepo->countForTournament($tournamentId) > 0) {
            return;
        }

        if ($format === 'double_elim') {
            $this->gen->generateDoubleElim($tournamentId, $participantType);
        } elseif ($format === 'single_elim') {
            $this->gen->generateSingleElim($tournamentId, $participantType);
        }
    }

    private function envInt(string $key, int $default): int
    {
        $raw = getenv($key);
        if (!is_string($raw) || trim($raw) === '') {
            return $default;
        }
        $raw = trim($raw);
        if (!ctype_digit($raw)) {
            return $default;
        }
        $v = (int)$raw;
        return $v > 0 ? $v : $default;
    }

    /**
     * Create a minimal 8-bit RGBA PNG with filter type 0. No GD required.
     *
     * @param array{0:int,1:int,2:int,3:int} $rgba
     */
    private function writeSolidPng(string $absPath, int $w, int $h, array $rgba): void
    {
        if ($w <= 0 || $h <= 0) {
            throw new RuntimeException('Invalid image size');
        }

        $r = (int)($rgba[0] ?? 0);
        $g = (int)($rgba[1] ?? 0);
        $b = (int)($rgba[2] ?? 0);
        $a = (int)($rgba[3] ?? 255);

        $r = max(0, min(255, $r));
        $g = max(0, min(255, $g));
        $b = max(0, min(255, $b));
        $a = max(0, min(255, $a));

        $pixel = pack('C4', $r, $g, $b, $a);
        $row = "\x00" . str_repeat($pixel, $w);
        $raw = str_repeat($row, $h);

        $compressed = gzcompress($raw, 9);
        if (!is_string($compressed) || $compressed === '') {
            throw new RuntimeException('gzcompress failed');
        }

        $signature = "\x89PNG\r\n\x1a\n";

        $ihdr = pack('N', $w) . pack('N', $h) . "\x08" . "\x06" . "\x00" . "\x00" . "\x00";
        $png = $signature;
        $png .= $this->pngChunk('IHDR', $ihdr);
        $png .= $this->pngChunk('IDAT', $compressed);
        $png .= $this->pngChunk('IEND', '');

        if (@file_put_contents($absPath, $png) === false) {
            throw new RuntimeException("Failed to write: {$absPath}");
        }
    }

    private function pngChunk(string $type, string $data): string
    {
        $len = strlen($data);
        $out = pack('N', $len) . $type . $data;
        $crc = crc32($type . $data);
        if ($crc < 0) {
            $crc += 4294967296;
        }
        $out .= pack('N', $crc);
        return $out;
    }
}

$pdo = Db::pdo();

$seeder = new DemoSeeder($pdo);
$seeder->run();

