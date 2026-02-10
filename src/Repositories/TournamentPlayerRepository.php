<?php

declare(strict_types=1);

namespace DuelDesk\Repositories;

use DuelDesk\Database\Db;
use PDO;

final class TournamentPlayerRepository
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
            'SELECT p.id AS player_id, p.handle, p.user_id, tp.seed, tp.checked_in, tp.joined_at'
            . ' FROM tournament_players tp'
            . ' JOIN players p ON p.id = tp.player_id'
            . ' WHERE tp.tournament_id = :tid'
            . ' ORDER BY (tp.seed IS NULL) ASC, tp.seed ASC, tp.joined_at ASC'
        );
        $stmt->execute(['tid' => $tournamentId]);

        /** @var list<array<string, mixed>> */
        return $stmt->fetchAll();
    }

    public function isPlayerInTournament(int $tournamentId, int $playerId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM tournament_players WHERE tournament_id = :tid AND player_id = :pid LIMIT 1'
        );
        $stmt->execute(['tid' => $tournamentId, 'pid' => $playerId]);
        return (bool)$stmt->fetchColumn();
    }

    public function add(int $tournamentId, int $playerId): void
    {
        // Idempotent.
        $stmt = $this->pdo->prepare(
            'INSERT IGNORE INTO tournament_players (tournament_id, player_id) VALUES (:tid, :pid)'
        );
        $stmt->execute(['tid' => $tournamentId, 'pid' => $playerId]);
    }

    public function setSeed(int $tournamentId, int $playerId, ?int $seed): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE tournament_players SET seed = :seed WHERE tournament_id = :tid AND player_id = :pid'
        );
        $stmt->execute([
            'seed' => $seed,
            'tid' => $tournamentId,
            'pid' => $playerId,
        ]);
    }

    public function seedTaken(int $tournamentId, int $seed, int $excludePlayerId = 0): bool
    {
        $sql = 'SELECT 1 FROM tournament_players WHERE tournament_id = :tid AND seed = :seed';
        $params = ['tid' => $tournamentId, 'seed' => $seed];

        if ($excludePlayerId > 0) {
            $sql .= ' AND player_id <> :pid';
            $params['pid'] = $excludePlayerId;
        }

        $sql .= ' LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (bool)$stmt->fetchColumn();
    }

    public function setCheckedIn(int $tournamentId, int $playerId, bool $checkedIn): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE tournament_players SET checked_in = :v WHERE tournament_id = :tid AND player_id = :pid'
        );
        $stmt->execute([
            'v' => $checkedIn ? 1 : 0,
            'tid' => $tournamentId,
            'pid' => $playerId,
        ]);
    }

    public function remove(int $tournamentId, int $playerId): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM tournament_players WHERE tournament_id = :tid AND player_id = :pid'
        );
        $stmt->execute(['tid' => $tournamentId, 'pid' => $playerId]);
    }
}
