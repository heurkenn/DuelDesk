<?php

declare(strict_types=1);

namespace DuelDesk\Repositories;

use DuelDesk\Database\Db;
use PDO;

final class LanPlayerRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Db::pdo();
    }

    public function isRegistered(int $lanEventId, int $userId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM lan_players WHERE lan_event_id = :eid AND user_id = :uid LIMIT 1');
        $stmt->execute(['eid' => $lanEventId, 'uid' => $userId]);
        return (bool)$stmt->fetchColumn();
    }

    public function add(int $lanEventId, int $userId): void
    {
        $stmt = $this->pdo->prepare('INSERT IGNORE INTO lan_players (lan_event_id, user_id) VALUES (:eid, :uid)');
        $stmt->execute(['eid' => $lanEventId, 'uid' => $userId]);
    }

    public function remove(int $lanEventId, int $userId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM lan_players WHERE lan_event_id = :eid AND user_id = :uid');
        $stmt->execute(['eid' => $lanEventId, 'uid' => $userId]);
    }

    /** @return list<int> */
    public function listUserIds(int $lanEventId): array
    {
        $stmt = $this->pdo->prepare('SELECT user_id FROM lan_players WHERE lan_event_id = :eid ORDER BY joined_at ASC');
        $stmt->execute(['eid' => $lanEventId]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $out = [];
        if (is_array($rows)) {
            foreach ($rows as $v) {
                $id = (int)$v;
                if ($id > 0) {
                    $out[] = $id;
                }
            }
        }
        return $out;
    }
}

