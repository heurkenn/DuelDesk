<?php

declare(strict_types=1);

namespace DuelDesk\Services;

use DuelDesk\Database\Db;
use DuelDesk\Repositories\LanPlayerRepository;
use DuelDesk\Repositories\LanTeamMemberRepository;
use DuelDesk\Repositories\LanTeamRepository;
use DuelDesk\Repositories\LanTeamTournamentTeamRepository;
use DuelDesk\Repositories\MatchRepository;
use DuelDesk\Repositories\PlayerRepository;
use DuelDesk\Repositories\TeamMemberRepository;
use DuelDesk\Repositories\TeamRepository;
use DuelDesk\Repositories\TournamentPlayerRepository;
use DuelDesk\Repositories\TournamentRepository;
use DuelDesk\Repositories\TournamentTeamRepository;
use DuelDesk\Repositories\UserRepository;

final class LanEnrollmentService
{
    /** @param array<string,mixed> $event */
    public function registerSolo(array $event, int $userId, string $handle, bool $isAdmin): void
    {
        $lanId = (int)($event['id'] ?? 0);
        if ($lanId <= 0) {
            throw new \RuntimeException('LAN invalide.');
        }

        $tRepo = new TournamentRepository();
        $tournaments = $tRepo->listByLanEventId($lanId);

        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            $lpRepo = new LanPlayerRepository();
            $lpRepo->add($lanId, $userId);

            $pRepo = new PlayerRepository();
            $playerId = $pRepo->ensureForUser($userId, $handle);

            $tpRepo = new TournamentPlayerRepository();
            foreach ($tournaments as $t) {
                $this->assertTournamentAllowsJoin($t, 'solo', $isAdmin);
                $tid = (int)($t['id'] ?? 0);
                if ($tid > 0) {
                    $tpRepo->add($tid, $playerId);
                }
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** @param array<string,mixed> $event */
    public function withdrawSolo(array $event, int $userId, bool $isAdmin): void
    {
        $lanId = (int)($event['id'] ?? 0);
        if ($lanId <= 0) {
            throw new \RuntimeException('LAN invalide.');
        }

        $tRepo = new TournamentRepository();
        $tournaments = $tRepo->listByLanEventId($lanId);

        $pRepo = new PlayerRepository();
        $p = $pRepo->findByUserId($userId);
        $playerId = is_array($p) ? (int)($p['id'] ?? 0) : 0;

        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            foreach ($tournaments as $t) {
                $this->assertTournamentAllowsWithdraw($t, $isAdmin);
                $tid = (int)($t['id'] ?? 0);
                if ($tid > 0 && $playerId > 0) {
                    (new TournamentPlayerRepository())->remove($tid, $playerId);
                }
            }

            (new LanPlayerRepository())->remove($lanId, $userId);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** @return int Effective max roster size (minimum team_size across tournaments, fallback 16) */
    public function effectiveTeamSizeLimit(int $lanEventId): int
    {
        $tRepo = new TournamentRepository();
        $tournaments = $tRepo->listByLanEventId($lanEventId);
        $limit = 0;
        foreach ($tournaments as $t) {
            $ts = (int)($t['team_size'] ?? 0);
            if ($ts <= 0) {
                continue;
            }
            $limit = $limit === 0 ? $ts : min($limit, $ts);
        }
        if ($limit <= 0) {
            $limit = 16;
        }
        return $limit;
    }

    /** @param array<string,mixed> $event */
    public function createTeam(array $event, int $captainUserId, string $name, bool $isAdmin): int
    {
        $lanId = (int)($event['id'] ?? 0);
        if ($lanId <= 0) {
            throw new \RuntimeException('LAN invalide.');
        }

        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 80) {
            throw new \RuntimeException('Nom d\'equipe invalide.');
        }

        $tRepo = new TournamentRepository();
        $tournaments = $tRepo->listByLanEventId($lanId);

        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            $ltRepo = new LanTeamRepository();
            $slug = $ltRepo->uniqueSlug($lanId, $name);
            $joinCode = $ltRepo->generateUniqueJoinCode(10);
            $lanTeamId = $ltRepo->create($lanId, $name, $slug, $joinCode, $captainUserId);

            $ltmRepo = new LanTeamMemberRepository();
            $ltmRepo->addMember($lanTeamId, $captainUserId, 'captain');

            // Auto-enroll into every tournament in the LAN.
            $this->syncLanTeamToTournaments($event, $lanTeamId, $isAdmin, $tournaments);

            $pdo->commit();
            return $lanTeamId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** @param array<string,mixed> $event */
    public function joinTeam(array $event, int $userId, int $lanTeamId, bool $isAdmin): void
    {
        $lanId = (int)($event['id'] ?? 0);
        if ($lanId <= 0) {
            throw new \RuntimeException('LAN invalide.');
        }

        $tRepo = new TournamentRepository();
        $tournaments = $tRepo->listByLanEventId($lanId);

        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            $limit = $this->effectiveTeamSizeLimit($lanId);
            $ltmRepo = new LanTeamMemberRepository();
            $count = $ltmRepo->countMembers($lanTeamId);
            if ($count >= $limit && !$isAdmin) {
                throw new \RuntimeException("Equipe complete (max {$limit}).");
            }

            $ltmRepo->addMember($lanTeamId, $userId, 'member');

            $this->syncLanTeamToTournaments($event, $lanTeamId, $isAdmin, $tournaments);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** @param array<string,mixed> $event */
    public function leaveTeam(array $event, int $userId, int $lanTeamId, bool $isAdmin): void
    {
        $lanId = (int)($event['id'] ?? 0);
        if ($lanId <= 0) {
            throw new \RuntimeException('LAN invalide.');
        }

        $tRepo = new TournamentRepository();
        $tournaments = $tRepo->listByLanEventId($lanId);

        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            // Block roster changes when any tournament is locked, unless admin.
            foreach ($tournaments as $t) {
                $this->assertTournamentAllowsJoin($t, 'team', $isAdmin);
            }

            $ltmRepo = new LanTeamMemberRepository();
            $wasCaptain = $ltmRepo->isCaptain($lanTeamId, $userId);
            $ltmRepo->removeMember($lanTeamId, $userId);

            $remaining = $ltmRepo->countMembers($lanTeamId);
            if ($remaining <= 0) {
                // Delete all linked tournament teams (cascade will remove roster/entrants).
                $linkRepo = new LanTeamTournamentTeamRepository();
                $links = $linkRepo->listLinks($lanTeamId);
                $linkRepo->deleteByLanTeamId($lanTeamId);
                $teamRepo = new TeamRepository();
                foreach ($links as $lnk) {
                    $teamRepo->delete((int)$lnk['team_id']);
                }
                (new LanTeamRepository())->delete($lanTeamId);
                $pdo->commit();
                return;
            }

            if ($wasCaptain) {
                $newCaptainId = $ltmRepo->findOldestMemberUserId($lanTeamId);
                if ($newCaptainId !== null) {
                    // Set all to member, then promote.
                    foreach ($ltmRepo->listMembers($lanTeamId) as $m) {
                        $uid = (int)($m['user_id'] ?? 0);
                        if ($uid > 0) {
                            $ltmRepo->setRole($lanTeamId, $uid, 'member');
                        }
                    }
                    $ltmRepo->setRole($lanTeamId, $newCaptainId, 'captain');
                }
            }

            // Sync tournament teams roster.
            $this->syncLanTeamToTournaments($event, $lanTeamId, $isAdmin, $tournaments);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** @param array<string,mixed> $event */
    public function backfillTournament(array $event, array $tournament, bool $isAdmin): void
    {
        $lanId = (int)($event['id'] ?? 0);
        $tid = (int)($tournament['id'] ?? 0);
        if ($lanId <= 0 || $tid <= 0) {
            return;
        }

        $ptype = (string)($event['participant_type'] ?? 'solo');
        if (!in_array($ptype, ['solo', 'team'], true)) {
            $ptype = 'solo';
        }

        if ($ptype === 'solo') {
            $lpRepo = new LanPlayerRepository();
            $userIds = $lpRepo->listUserIds($lanId);
            if ($userIds === []) {
                return;
            }

            $uRepo = new UserRepository();
            foreach ($userIds as $uid) {
                $u = $uRepo->findById($uid);
                $handle = is_array($u) ? trim((string)($u['username'] ?? '')) : '';
                if ($handle === '') {
                    $handle = 'player';
                }
                // Best-effort: keep handle stable when possible.
                // (If missing, PlayerRepository will use the provided handle.)
                $pRepo = new PlayerRepository();
                $playerId = $pRepo->ensureForUser($uid, $handle);
                $this->assertTournamentAllowsJoin($tournament, 'solo', $isAdmin);
                (new TournamentPlayerRepository())->add($tid, $playerId);
            }
            return;
        }

        $ltRepo = new LanTeamRepository();
        $teams = $ltRepo->listByLanEventId($lanId);
        foreach ($teams as $lt) {
            $lanTeamId = (int)($lt['id'] ?? 0);
            if ($lanTeamId <= 0) {
                continue;
            }
            $this->syncLanTeamToTournaments($event, $lanTeamId, $isAdmin, [$tournament]);
        }
    }

    /** @param array<string,mixed> $event */
    private function syncLanTeamToTournaments(array $event, int $lanTeamId, bool $isAdmin, array $tournaments): void
    {
        $lanId = (int)($event['id'] ?? 0);
        if ($lanId <= 0) {
            throw new \RuntimeException('LAN invalide.');
        }

        $ltRepo = new LanTeamRepository();
        $lt = $ltRepo->findById($lanTeamId);
        if (!is_array($lt) || (int)($lt['lan_event_id'] ?? 0) !== $lanId) {
            throw new \RuntimeException('Equipe LAN introuvable.');
        }

        $members = (new LanTeamMemberRepository())->listMembers($lanTeamId);
        if ($members === []) {
            throw new \RuntimeException('Equipe vide.');
        }

        $captainId = null;
        $memberUserIds = [];
        foreach ($members as $m) {
            $uid = (int)($m['user_id'] ?? 0);
            if ($uid <= 0) {
                continue;
            }
            $memberUserIds[] = $uid;
            if (($m['role'] ?? '') === 'captain') {
                $captainId = $uid;
            }
        }
        if ($captainId === null) {
            $captainId = $memberUserIds[0] ?? null;
        }

        foreach ($tournaments as $t) {
            $this->assertTournamentAllowsJoin($t, 'team', $isAdmin);

            $tid = (int)($t['id'] ?? 0);
            if ($tid <= 0) {
                continue;
            }

            $teamSize = (int)($t['team_size'] ?? 0);
            if ($teamSize > 0 && count($memberUserIds) > $teamSize && !$isAdmin) {
                throw new \RuntimeException("Roster trop grand pour le tournoi #{$tid} (max {$teamSize}).");
            }

            $linkRepo = new LanTeamTournamentTeamRepository();
            $teamId = $linkRepo->findTeamId($lanTeamId, $tid);

            if ($teamId === null) {
                // Create tournament team.
                $teamRepo = new TeamRepository();
                $slug = $teamRepo->uniqueSlug($tid, (string)($lt['name'] ?? 'team'));
                $joinCode = $teamRepo->generateUniqueJoinCode(10);
                $teamId = $teamRepo->create($tid, (string)($lt['name'] ?? 'Team'), $slug, $joinCode, (int)($lt['created_by_user_id'] ?? 0) ?: null);

                // Add roster.
                $tmRepo = new TeamMemberRepository();
                foreach ($memberUserIds as $uid) {
                    $role = ($captainId !== null && $uid === $captainId) ? 'captain' : 'member';
                    $tmRepo->addMember($teamId, $uid, $role);
                }

                // Register team in tournament.
                (new TournamentTeamRepository())->add($tid, $teamId);

                // Map to LAN team.
                $linkRepo->add($lanTeamId, $tid, $teamId);
            } else {
                // Sync roster to tournament team.
                $tmRepo = new TeamMemberRepository();
                $existing = $tmRepo->listMembers($teamId);
                $existingIds = [];
                foreach ($existing as $m) {
                    $uid = (int)($m['user_id'] ?? 0);
                    if ($uid > 0) {
                        $existingIds[$uid] = true;
                    }
                }

                $want = [];
                foreach ($memberUserIds as $uid) {
                    $want[$uid] = true;
                    if (!isset($existingIds[$uid])) {
                        $role = ($captainId !== null && $uid === $captainId) ? 'captain' : 'member';
                        $tmRepo->addMember($teamId, $uid, $role);
                    }
                }

                // Remove extra members.
                foreach (array_keys($existingIds) as $uid) {
                    if (!isset($want[$uid])) {
                        $tmRepo->removeMember($teamId, (int)$uid);
                    }
                }

                // Ensure captain role matches.
                if ($captainId !== null) {
                    foreach ($memberUserIds as $uid) {
                        $role = ($uid === $captainId) ? 'captain' : 'member';
                        $tmRepo->setRole($teamId, $uid, $role);
                    }
                }

                (new TournamentTeamRepository())->add($tid, $teamId);
            }
        }
    }

    /** @param array<string,mixed> $t */
    private function assertTournamentAllowsJoin(array $t, string $ptype, bool $isAdmin): void
    {
        if ($isAdmin) {
            return;
        }

        $status = (string)($t['status'] ?? 'draft');
        if (!in_array($status, ['published', 'running'], true)) {
            throw new \RuntimeException('Inscriptions fermees (tournoi non ouvert).');
        }

        $tid = (int)($t['id'] ?? 0);
        if ($tid <= 0) {
            throw new \RuntimeException('Tournoi invalide.');
        }

        $mRepo = new MatchRepository();
        if ($mRepo->countForTournament($tid) > 0) {
            throw new \RuntimeException('Inscriptions verrouillees (bracket deja genere).');
        }

        $signupClosesAt = $t['signup_closes_at'] ?? null;
        if (is_string($signupClosesAt) && $signupClosesAt !== '') {
            $ts = strtotime($signupClosesAt);
            if ($ts !== false && $ts <= time()) {
                throw new \RuntimeException('Inscriptions fermees (date limite depassee).');
            }
        }

        $maxEntrants = $t['max_entrants'] ?? null;
        if ($maxEntrants !== null) {
            $max = (int)$maxEntrants;
            if ($max > 0) {
                if ($ptype === 'team') {
                    $cnt = (new TournamentTeamRepository())->countForTournament($tid);
                    if ($cnt >= $max) {
                        throw new \RuntimeException('Tournoi complet.');
                    }
                } else {
                    $cnt = (new TournamentPlayerRepository())->countForTournament($tid);
                    if ($cnt >= $max) {
                        throw new \RuntimeException('Tournoi complet.');
                    }
                }
            }
        }
    }

    /** @param array<string,mixed> $t */
    private function assertTournamentAllowsWithdraw(array $t, bool $isAdmin): void
    {
        if ($isAdmin) {
            return;
        }

        $status = (string)($t['status'] ?? 'draft');
        if ($status === 'completed') {
            throw new \RuntimeException('Tournoi termine.');
        }

        $tid = (int)($t['id'] ?? 0);
        if ($tid <= 0) {
            throw new \RuntimeException('Tournoi invalide.');
        }

        $mRepo = new MatchRepository();
        if ($mRepo->countForTournament($tid) > 0) {
            throw new \RuntimeException('Retrait verrouille (bracket deja genere).');
        }

        $signupClosesAt = $t['signup_closes_at'] ?? null;
        if (is_string($signupClosesAt) && $signupClosesAt !== '') {
            $ts = strtotime($signupClosesAt);
            if ($ts !== false && $ts <= time()) {
                throw new \RuntimeException('Retrait bloque (inscriptions fermees).');
            }
        }
    }
}