<?php

declare(strict_types=1);

namespace DuelDesk\Repositories;

use DuelDesk\Database\Db;
use PDO;

final class MatchTeamLineupRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Db::pdo();
    }

    /**
     * @return list<array{pos:int,user_id:int,username:string}>
     */
    public function listLineup(int $matchId, int $teamSlot): array
    {
        if ($matchId <= 0 || ($teamSlot !== 1 && $teamSlot !== 2)) {
            return [];
        }

        $stmt = $this->pdo->prepare(
            'SELECT mtl.pos, mtl.user_id, u.username'
            . ' FROM match_team_lineups mtl'
            . ' JOIN users u ON u.id = mtl.user_id'
            . ' WHERE mtl.match_id = :mid AND mtl.team_slot = :slot'
            . ' ORDER BY mtl.pos ASC'
        );
        $stmt->execute(['mid' => $matchId, 'slot' => $teamSlot]);

        /** @var list<array<string,mixed>> $rows */
        $rows = $stmt->fetchAll();

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'pos' => (int)($r['pos'] ?? 0),
                'user_id' => (int)($r['user_id'] ?? 0),
                'username' => (string)($r['username'] ?? ''),
            ];
        }
        return $out;
    }

    /**
     * Replace the lineup for this match+slot.
     *
     * @param list<int> $orderedUserIds
     */
    public function replaceLineup(int $matchId, int $teamSlot, array $orderedUserIds): void
    {
        if ($matchId <= 0 || ($teamSlot !== 1 && $teamSlot !== 2)) {
            throw new \InvalidArgumentException('Invalid match/slot');
        }
        if ($orderedUserIds === []) {
            throw new \InvalidArgumentException('Empty lineup');
        }

        $this->pdo->beginTransaction();
        try {
            $del = $this->pdo->prepare('DELETE FROM match_team_lineups WHERE match_id = :mid AND team_slot = :slot');
            $del->execute(['mid' => $matchId, 'slot' => $teamSlot]);

            $ins = $this->pdo->prepare(
                'INSERT INTO match_team_lineups (match_id, team_slot, pos, user_id)'
                . ' VALUES (:mid, :slot, :pos, :uid)'
            );

            $pos = 1;
            foreach ($orderedUserIds as $uid) {
                $uid = (int)$uid;
                if ($uid <= 0) {
                    continue;
                }
                $ins->execute([
                    'mid' => $matchId,
                    'slot' => $teamSlot,
                    'pos' => $pos,
                    'uid' => $uid,
                ]);
                $pos++;
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}

