<?php

declare(strict_types=1);

namespace DuelDesk\Repositories;

use DuelDesk\Database\Db;
use PDO;

final class RulesetRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Db::pdo();
    }

    /** @return list<array<string, mixed>> */
    public function listAll(?int $gameId = null): array
    {
        if ($gameId !== null && $gameId > 0) {
            $stmt = $this->pdo->prepare(
                'SELECT r.*, g.name AS game_name'
                . ' FROM rulesets r'
                . ' LEFT JOIN games g ON g.id = r.game_id'
                . ' WHERE r.game_id = :gid'
                . ' ORDER BY r.updated_at DESC, r.id DESC'
            );
            $stmt->execute(['gid' => $gameId]);
            /** @var list<array<string, mixed>> */
            return $stmt->fetchAll();
        }

        $stmt = $this->pdo->query(
            'SELECT r.*, g.name AS game_name'
            . ' FROM rulesets r'
            . ' LEFT JOIN games g ON g.id = r.game_id'
            . ' ORDER BY r.updated_at DESC, r.id DESC'
        );

        /** @var list<array<string, mixed>> */
        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.*, g.name AS game_name'
            . ' FROM rulesets r'
            . ' LEFT JOIN games g ON g.id = r.game_id'
            . ' WHERE r.id = :id'
            . ' LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function create(?int $gameId, string $name, string $kind, string $rulesetJson): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO rulesets (game_id, name, kind, ruleset_json)'
            . ' VALUES (:game_id, :name, :kind, :ruleset_json)'
        );
        $stmt->execute([
            'game_id' => $gameId,
            'name' => $name,
            'kind' => $kind,
            'ruleset_json' => $rulesetJson,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, ?int $gameId, string $name, string $kind, string $rulesetJson): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE rulesets SET game_id = :game_id, name = :name, kind = :kind, ruleset_json = :ruleset_json WHERE id = :id'
        );
        $stmt->execute([
            'game_id' => $gameId,
            'name' => $name,
            'kind' => $kind,
            'ruleset_json' => $rulesetJson,
            'id' => $id,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM rulesets WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}

