<?php

declare(strict_types=1);

namespace DuelDesk\Repositories;

use DuelDesk\Database\Db;
use DuelDesk\Support\Str;
use PDO;

final class TeamRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Db::pdo();
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM teams WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function findByJoinCode(int $tournamentId, string $joinCode): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM teams WHERE tournament_id = :tid AND join_code = :code LIMIT 1');
        $stmt->execute([
            'tid' => $tournamentId,
            'code' => $joinCode,
        ]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function create(int $tournamentId, string $name, string $slug, string $joinCode, ?int $createdByUserId): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO teams (tournament_id, name, slug, join_code, created_by_user_id)'
            . ' VALUES (:tournament_id, :name, :slug, :join_code, :created_by_user_id)'
        );
        $stmt->execute([
            'tournament_id' => $tournamentId,
            'name' => $name,
            'slug' => $slug,
            'join_code' => $joinCode,
            'created_by_user_id' => $createdByUserId,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function delete(int $teamId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM teams WHERE id = :id');
        $stmt->execute(['id' => $teamId]);
    }

    public function uniqueSlug(int $tournamentId, string $name): string
    {
        $base = Str::slug($name);
        if ($base === 'tournament') {
            $base = 'team';
        }
        $slug = $base;
        $i = 2;

        while ($this->slugExists($tournamentId, $slug)) {
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

    private function slugExists(int $tournamentId, string $slug): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM teams WHERE tournament_id = :tid AND slug = :slug LIMIT 1');
        $stmt->execute(['tid' => $tournamentId, 'slug' => $slug]);
        return (bool)$stmt->fetchColumn();
    }

    private function joinCodeExists(string $code): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM teams WHERE join_code = :code LIMIT 1');
        $stmt->execute(['code' => $code]);
        return (bool)$stmt->fetchColumn();
    }

    private function randomCode(int $len): string
    {
        // Avoid ambiguous chars: 0/O and 1/I.
        $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $out = '';

        $bytes = random_bytes($len);
        for ($i = 0; $i < $len; $i++) {
            $out .= $alphabet[ord($bytes[$i]) % strlen($alphabet)];
        }

        return $out;
    }
}

