<?php

declare(strict_types=1);

namespace DuelDesk\Repositories;

use DuelDesk\Database\Db;
use DuelDesk\Support\Str;
use PDO;

final class LanTeamRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Db::pdo();
    }

    /** @return array<string,mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM lan_teams WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /** @return array<string,mixed>|null */
    public function findByJoinCode(int $lanEventId, string $joinCode): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM lan_teams WHERE lan_event_id = :eid AND join_code = :code LIMIT 1');
        $stmt->execute(['eid' => $lanEventId, 'code' => $joinCode]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /** @return array<string,mixed>|null */
    public function findForUser(int $lanEventId, int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT lt.* FROM lan_team_members ltm'
            . ' JOIN lan_teams lt ON lt.id = ltm.lan_team_id'
            . ' WHERE lt.lan_event_id = :eid AND ltm.user_id = :uid'
            . ' LIMIT 1'
        );
        $stmt->execute(['eid' => $lanEventId, 'uid' => $userId]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /** @return list<array<string,mixed>> */
    public function listByLanEventId(int $lanEventId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM lan_teams WHERE lan_event_id = :eid ORDER BY created_at ASC, id ASC');
        $stmt->execute(['eid' => $lanEventId]);
        /** @var list<array<string,mixed>> */
        return $stmt->fetchAll();
    }

    public function create(int $lanEventId, string $name, string $slug, string $joinCode, ?int $createdByUserId): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO lan_teams (lan_event_id, name, slug, join_code, created_by_user_id)'
            . ' VALUES (:eid, :name, :slug, :join_code, :created_by_user_id)'
        );
        $stmt->execute([
            'eid' => $lanEventId,
            'name' => $name,
            'slug' => $slug,
            'join_code' => $joinCode,
            'created_by_user_id' => $createdByUserId,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function delete(int $lanTeamId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM lan_teams WHERE id = :id');
        $stmt->execute(['id' => $lanTeamId]);
    }

    public function uniqueSlug(int $lanEventId, string $name): string
    {
        $base = Str::slug($name);
        if ($base === 'tournament') {
            $base = 'team';
        }
        $slug = $base;
        $i = 2;

        while ($this->slugExists($lanEventId, $slug)) {
            $slug = $base . '-' . $i;
            $i++;
            if ($i > 200) {
                $slug = $base . '-' . bin2hex(random_bytes(4));
                break;
            }
        }

        return $slug;
    }

    public function generateUniqueJoinCode(int $len = 10): string
    {
        if ($len < 6) {
            $len = 6;
        }
        if ($len > 16) {
            $len = 16;
        }

        for ($i = 0; $i < 100; $i++) {
            $code = $this->randomCode($len);
            if (!$this->joinCodeExists($code)) {
                return $code;
            }
        }

        return bin2hex(random_bytes(8));
    }

    private function slugExists(int $lanEventId, string $slug): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM lan_teams WHERE lan_event_id = :eid AND slug = :slug LIMIT 1');
        $stmt->execute(['eid' => $lanEventId, 'slug' => $slug]);
        return (bool)$stmt->fetchColumn();
    }

    private function joinCodeExists(string $code): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM lan_teams WHERE join_code = :code LIMIT 1');
        $stmt->execute(['code' => $code]);
        return (bool)$stmt->fetchColumn();
    }

    private function randomCode(int $len): string
    {
        $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $out = '';
        $bytes = random_bytes($len);
        $n = strlen($alphabet);
        for ($i = 0; $i < $len; $i++) {
            $out .= $alphabet[ord($bytes[$i]) % $n];
        }
        return $out;
    }
}

