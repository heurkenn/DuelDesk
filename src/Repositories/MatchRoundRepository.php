<?php

declare(strict_types=1);

namespace DuelDesk\Repositories;

use DuelDesk\Database\Db;
use PDO;

final class MatchRoundRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Db::pdo();
    }

    /** @return list<array<string,mixed>> */
    public function listForMatch(int $matchId): array
    {
        if ($matchId <= 0) {
            return [];
        }

        $stmt = $this->pdo->prepare(
            'SELECT r.*, u.username AS created_by_username'
            . ' FROM match_rounds r'
            . ' LEFT JOIN users u ON u.id = r.created_by_user_id'
            . ' WHERE r.match_id = :mid'
            . ' ORDER BY r.round_index ASC, r.id ASC'
        );
        $stmt->execute(['mid' => $matchId]);

        /** @var list<array<string,mixed>> */
        return $stmt->fetchAll();
    }

    public function nextRoundIndex(int $matchId): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(round_index), 0) FROM match_rounds WHERE match_id = :mid');
        $stmt->execute(['mid' => $matchId]);
        return (int)$stmt->fetchColumn() + 1;
    }

    public function addRound(int $matchId, string $kind, int $points1, int $points2, ?string $note, ?int $createdByUserId): int
    {
        if ($matchId <= 0) {
            throw new \InvalidArgumentException('Invalid match');
        }
        if (!in_array($kind, ['regular', 'tiebreak'], true)) {
            throw new \InvalidArgumentException('Invalid kind');
        }

        $this->pdo->beginTransaction();
        try {
            $idx = $this->nextRoundIndex($matchId);
            $stmt = $this->pdo->prepare(
                'INSERT INTO match_rounds (match_id, round_index, kind, points1, points2, note, created_by_user_id)'
                . ' VALUES (:mid, :idx, :kind, :p1, :p2, :note, :uid)'
            );
            $stmt->execute([
                'mid' => $matchId,
                'idx' => $idx,
                'kind' => $kind,
                'p1' => $points1,
                'p2' => $points2,
                'note' => $note !== '' ? $note : null,
                'uid' => $createdByUserId,
            ]);
            $id = (int)$this->pdo->lastInsertId();
            $this->pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function deleteRound(int $roundId): void
    {
        if ($roundId <= 0) {
            return;
        }
        $stmt = $this->pdo->prepare('DELETE FROM match_rounds WHERE id = :id');
        $stmt->execute(['id' => $roundId]);
    }
}

