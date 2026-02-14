<?php

declare(strict_types=1);

namespace DuelDesk\Repositories;

use DuelDesk\Database\Db;
use PDO;

final class PlayerRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Db::pdo();
    }

    /** @return array<string, mixed>|null */
    public function findByUserId(int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM players WHERE user_id = :uid LIMIT 1');
        $stmt->execute(['uid' => $userId]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function createForUser(int $userId, string $handle): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO players (user_id, handle) VALUES (:uid, :handle)');
        $stmt->execute([
            'uid' => $userId,
            'handle' => $handle,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function ensureForUser(int $userId, string $handle): int
    {
        $existing = $this->findByUserId($userId);
        if ($existing !== null) {
            return (int)$existing['id'];
        }

        return $this->createForUser($userId, $handle);
    }

    public function findUserIdByPlayerId(int $playerId): ?int
    {
        if ($playerId <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT user_id FROM players WHERE id = :pid LIMIT 1');
        $stmt->execute(['pid' => $playerId]);
        $v = $stmt->fetchColumn();
        if ($v === false || $v === null) {
            return null;
        }
        $id = (int)$v;
        return $id > 0 ? $id : null;
    }
}
