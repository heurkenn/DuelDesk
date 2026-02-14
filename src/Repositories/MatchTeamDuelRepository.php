<?php

declare(strict_types=1);

namespace DuelDesk\Repositories;

use DuelDesk\Database\Db;
use PDO;

final class MatchTeamDuelRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Db::pdo();
    }

    /** @return list<array<string,mixed>> */
    public function listDuels(int $matchId): array
    {
        if ($matchId <= 0) {
            return [];
        }

        $stmt = $this->pdo->prepare(
            'SELECT d.*,'
            . ' u1.username AS team1_username,'
            . ' u2.username AS team2_username,'
            . ' u3.username AS reported_by_username'
            . ' FROM match_team_duels d'
            . ' JOIN users u1 ON u1.id = d.team1_user_id'
            . ' JOIN users u2 ON u2.id = d.team2_user_id'
            . ' LEFT JOIN users u3 ON u3.id = d.reported_by_user_id'
            . ' WHERE d.match_id = :mid'
            . ' ORDER BY (d.kind = \'captain_tiebreak\') ASC, d.duel_index ASC, d.id ASC'
        );
        $stmt->execute(['mid' => $matchId]);

        /** @var list<array<string,mixed>> */
        return $stmt->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function findById(int $duelId): ?array
    {
        if ($duelId <= 0) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT * FROM match_team_duels WHERE id = :id');
        $stmt->execute(['id' => $duelId]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function countDuels(int $matchId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM match_team_duels WHERE match_id = :mid');
        $stmt->execute(['mid' => $matchId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * @param list<array{kind:string,duel_index:int,team1_user_id:int,team2_user_id:int}> $duels
     */
    public function insertDuels(int $matchId, array $duels): void
    {
        if ($matchId <= 0) {
            throw new \InvalidArgumentException('Invalid match');
        }
        if ($duels === []) {
            return;
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO match_team_duels (match_id, kind, duel_index, team1_user_id, team2_user_id)'
                . ' VALUES (:mid, :kind, :idx, :u1, :u2)'
            );
            foreach ($duels as $d) {
                $stmt->execute([
                    'mid' => $matchId,
                    'kind' => (string)$d['kind'],
                    'idx' => (int)$d['duel_index'],
                    'u1' => (int)$d['team1_user_id'],
                    'u2' => (int)$d['team2_user_id'],
                ]);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function confirmDuel(int $duelId, int $winnerSlot, int $reportedByUserId): void
    {
        if ($duelId <= 0) {
            throw new \InvalidArgumentException('Invalid duel');
        }
        if ($winnerSlot !== 1 && $winnerSlot !== 2) {
            throw new \InvalidArgumentException('Invalid winner');
        }
        if ($reportedByUserId <= 0) {
            throw new \InvalidArgumentException('Invalid actor');
        }

        $stmt = $this->pdo->prepare(
            'UPDATE match_team_duels'
            . ' SET winner_slot = :w, status = \'confirmed\', reported_by_user_id = :uid, reported_at = UTC_TIMESTAMP()'
            . ' WHERE id = :id AND status <> \'confirmed\''
        );
        $stmt->execute([
            'w' => $winnerSlot,
            'uid' => $reportedByUserId,
            'id' => $duelId,
        ]);

        if ($stmt->rowCount() === 0) {
            throw new \RuntimeException('Duel deja confirme ou introuvable.');
        }
    }
}

