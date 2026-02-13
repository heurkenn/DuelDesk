<?php

declare(strict_types=1);

namespace DuelDesk\Repositories;

use DuelDesk\Database\Db;
use PDO;

final class PickBanRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Db::pdo();
    }

    /** @return array<string, mixed>|null */
    public function findState(int $matchId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM match_pickbans WHERE match_id = :mid LIMIT 1');
        $stmt->execute(['mid' => $matchId]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function findStateForUpdate(int $matchId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM match_pickbans WHERE match_id = :mid LIMIT 1 FOR UPDATE');
        $stmt->execute(['mid' => $matchId]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /**
     * @param list<int> $matchIds
     * @return array<int, array<string, mixed>> keyed by match_id
     */
    public function listStatesByMatchIds(array $matchIds): array
    {
        $ids = [];
        foreach ($matchIds as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $ids[$id] = true;
            }
        }
        $ids = array_keys($ids);
        if ($ids === []) {
            return [];
        }

        $ph = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("SELECT * FROM match_pickbans WHERE match_id IN ({$ph})");
        $stmt->execute($ids);

        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            if (!is_array($row)) {
                continue;
            }
            $mid = (int)($row['match_id'] ?? 0);
            if ($mid > 0) {
                $out[$mid] = $row;
            }
        }

        return $out;
    }

    public function createState(
        int $matchId,
        string $configJson,
        int $coinCallSlot,
        string $coinCall,
        string $coinResult,
        int $firstTurnSlot
    ): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO match_pickbans (match_id, status, config_json, coin_call_slot, coin_call, coin_result, first_turn_slot, tossed_at)'
            . ' VALUES (:match_id, :status, :config_json, :coin_call_slot, :coin_call, :coin_result, :first_turn_slot, NOW())'
        );
        $stmt->execute([
            'match_id' => $matchId,
            'status' => 'running',
            'config_json' => $configJson,
            'coin_call_slot' => $coinCallSlot,
            'coin_call' => $coinCall,
            'coin_result' => $coinResult,
            'first_turn_slot' => $firstTurnSlot,
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function listActions(int $matchId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM match_pickban_actions WHERE match_id = :mid ORDER BY step_index ASC, id ASC'
        );
        $stmt->execute(['mid' => $matchId]);
        /** @var list<array<string, mixed>> */
        return $stmt->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function listActionsForUpdate(int $matchId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM match_pickban_actions WHERE match_id = :mid ORDER BY step_index ASC, id ASC FOR UPDATE'
        );
        $stmt->execute(['mid' => $matchId]);
        /** @var list<array<string, mixed>> */
        return $stmt->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function listSides(int $matchId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM match_pickban_sides WHERE match_id = :mid ORDER BY created_at ASC, id ASC'
        );
        $stmt->execute(['mid' => $matchId]);
        /** @var list<array<string, mixed>> */
        return $stmt->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function listSidesForUpdate(int $matchId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM match_pickban_sides WHERE match_id = :mid ORDER BY created_at ASC, id ASC FOR UPDATE'
        );
        $stmt->execute(['mid' => $matchId]);
        /** @var list<array<string, mixed>> */
        return $stmt->fetchAll();
    }

    public function addAction(
        int $matchId,
        int $stepIndex,
        ?int $slot,
        string $action,
        string $mapKey,
        string $mapName,
        ?int $createdByUserId
    ): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO match_pickban_actions (match_id, step_index, slot, action, map_key, map_name, created_by_user_id)'
            . ' VALUES (:match_id, :step_index, :slot, :action, :map_key, :map_name, :created_by_user_id)'
        );
        $stmt->execute([
            'match_id' => $matchId,
            'step_index' => $stepIndex,
            'slot' => $slot,
            'action' => $action,
            'map_key' => $mapKey,
            'map_name' => $mapName,
            'created_by_user_id' => $createdByUserId,
        ]);
    }

    public function addSide(
        int $matchId,
        string $mapKey,
        string $sideForSlot1,
        ?int $chosenBySlot,
        ?int $chosenByUserId,
        string $source = 'choice'
    ): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO match_pickban_sides (match_id, map_key, side_for_slot1, chosen_by_slot, chosen_by_user_id, source)'
            . ' VALUES (:match_id, :map_key, :side_for_slot1, :chosen_by_slot, :chosen_by_user_id, :source)'
        );
        $stmt->execute([
            'match_id' => $matchId,
            'map_key' => $mapKey,
            'side_for_slot1' => $sideForSlot1,
            'chosen_by_slot' => $chosenBySlot,
            'chosen_by_user_id' => $chosenByUserId,
            'source' => $source,
        ]);
    }

    public function lock(int $matchId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE match_pickbans SET status = 'locked', locked_at = NOW() WHERE match_id = :mid"
        );
        $stmt->execute(['mid' => $matchId]);
    }

    public function reset(int $matchId): void
    {
        $this->pdo->prepare('DELETE FROM match_pickban_sides WHERE match_id = :mid')->execute(['mid' => $matchId]);
        $this->pdo->prepare('DELETE FROM match_pickban_actions WHERE match_id = :mid')->execute(['mid' => $matchId]);
        $this->pdo->prepare('DELETE FROM match_pickbans WHERE match_id = :mid')->execute(['mid' => $matchId]);
    }

    public function isLocked(int $matchId): bool
    {
        $state = $this->findState($matchId);
        return is_array($state) && ((string)($state['status'] ?? '')) === 'locked';
    }
}
