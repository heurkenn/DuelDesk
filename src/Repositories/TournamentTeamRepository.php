<?php

declare(strict_types=1);

namespace DuelDesk\Repositories;

use DuelDesk\Database\Db;
use PDO;

final class TournamentTeamRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Db::pdo();
    }

    /** @return list<array<string, mixed>> */
    public function listForTournament(int $tournamentId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT t.id AS team_id, t.name, t.slug, tt.seed, tt.checked_in, tt.joined_at'
            . ' FROM tournament_teams tt'
            . ' JOIN teams t ON t.id = tt.team_id'
            . ' WHERE tt.tournament_id = :tid'
            . ' ORDER BY (tt.seed IS NULL) ASC, tt.seed ASC, tt.joined_at ASC'
        );
        $stmt->execute(['tid' => $tournamentId]);

        /** @var list<array<string, mixed>> */
        return $stmt->fetchAll();
    }

    public function countForTournament(int $tournamentId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM tournament_teams WHERE tournament_id = :tid');
        $stmt->execute(['tid' => $tournamentId]);
        return (int)$stmt->fetchColumn();
    }

    public function findSeed(int $tournamentId, int $teamId): ?int
    {
        $stmt = $this->pdo->prepare(
            'SELECT seed FROM tournament_teams WHERE tournament_id = :tid AND team_id = :team_id LIMIT 1'
        );
        $stmt->execute(['tid' => $tournamentId, 'team_id' => $teamId]);
        $v = $stmt->fetchColumn();
        if ($v === false || $v === null) {
            return null;
        }
        $n = (int)$v;
        return $n > 0 ? $n : null;
    }

    public function isTeamInTournament(int $tournamentId, int $teamId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM tournament_teams WHERE tournament_id = :tid AND team_id = :team_id LIMIT 1'
        );
        $stmt->execute(['tid' => $tournamentId, 'team_id' => $teamId]);
        return (bool)$stmt->fetchColumn();
    }

    public function add(int $tournamentId, int $teamId): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT IGNORE INTO tournament_teams (tournament_id, team_id) VALUES (:tid, :team_id)'
        );
        $stmt->execute(['tid' => $tournamentId, 'team_id' => $teamId]);
    }

    public function remove(int $tournamentId, int $teamId): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM tournament_teams WHERE tournament_id = :tid AND team_id = :team_id'
        );
        $stmt->execute(['tid' => $tournamentId, 'team_id' => $teamId]);
    }

    public function setSeed(int $tournamentId, int $teamId, ?int $seed): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE tournament_teams SET seed = :seed WHERE tournament_id = :tid AND team_id = :team_id'
        );
        $stmt->execute([
            'seed' => $seed,
            'tid' => $tournamentId,
            'team_id' => $teamId,
        ]);
    }

    public function seedTaken(int $tournamentId, int $seed, int $excludeTeamId = 0): bool
    {
        $sql = 'SELECT 1 FROM tournament_teams WHERE tournament_id = :tid AND seed = :seed';
        $params = ['tid' => $tournamentId, 'seed' => $seed];

        if ($excludeTeamId > 0) {
            $sql .= ' AND team_id <> :team_id';
            $params['team_id'] = $excludeTeamId;
        }

        $sql .= ' LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (bool)$stmt->fetchColumn();
    }

    public function setCheckedIn(int $tournamentId, int $teamId, bool $checkedIn): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE tournament_teams SET checked_in = :v WHERE tournament_id = :tid AND team_id = :team_id'
        );
        $stmt->execute([
            'v' => $checkedIn ? 1 : 0,
            'tid' => $tournamentId,
            'team_id' => $teamId,
        ]);
    }
}
