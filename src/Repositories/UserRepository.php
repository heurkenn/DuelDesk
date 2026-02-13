<?php

declare(strict_types=1);

namespace DuelDesk\Repositories;

use DuelDesk\Database\Db;
use PDO;

final class UserRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Db::pdo();
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, username, role, discord_user_id, discord_username, discord_global_name, discord_avatar, created_at, updated_at'
            . ' FROM users WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function findByUsername(string $username): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE username = :username');
        $stmt->execute(['username' => $username]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function findByDiscordUserId(string $discordUserId): ?array
    {
        $discordUserId = trim($discordUserId);
        if ($discordUserId === '') {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT id, username, role, discord_user_id FROM users WHERE discord_user_id = :d');
        $stmt->execute(['d' => $discordUserId]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function countAll(): int
    {
        return (int)$this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }

    public function countAdmins(): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin'");
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        $stmt = $this->pdo->query('SELECT id, username, role, created_at, updated_at FROM users ORDER BY created_at DESC');
        /** @var list<array<string, mixed>> */
        return $stmt->fetchAll();
    }

    public function countSearch(string $query): int
    {
        $query = trim($query);
        if ($query === '') {
            return $this->countAll();
        }

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM users WHERE username LIKE :q OR CAST(id AS CHAR) = :exact');
        $stmt->execute([
            'q' => '%' . $query . '%',
            'exact' => $query,
        ]);
        return (int)$stmt->fetchColumn();
    }

    /** @return list<array<string, mixed>> */
    public function searchPaged(string $query, int $page, int $perPage): array
    {
        $query = trim($query);
        if ($page <= 0) {
            $page = 1;
        }
        if ($perPage <= 0) {
            $perPage = 40;
        }
        if ($perPage > 200) {
            $perPage = 200;
        }

        $offset = ($page - 1) * $perPage;

        $sql = 'SELECT id, username, role, created_at, updated_at FROM users';
        $params = [];

        if ($query !== '') {
            $sql .= ' WHERE username LIKE :q OR CAST(id AS CHAR) = :exact';
            $params = [
                'q' => '%' . $query . '%',
                'exact' => $query,
            ];
        }

        $sql .= ' ORDER BY created_at DESC LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        /** @var list<array<string, mixed>> */
        return $stmt->fetchAll();
    }

    public function create(string $username, string $passwordHash, string $role): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (username, password_hash, role) VALUES (:username, :password_hash, :role)'
        );

        $stmt->execute([
            'username' => $username,
            'password_hash' => $passwordHash,
            'role' => $role,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function setRole(int $id, string $role): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET role = :role WHERE id = :id');
        $stmt->execute(['role' => $role, 'id' => $id]);
    }

    public function linkDiscord(int $id, string $discordUserId, string $discordUsername, string $discordGlobalName, string $discordAvatar): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users'
            . ' SET discord_user_id = :did, discord_username = :du, discord_global_name = :dgn, discord_avatar = :dav'
            . ' WHERE id = :id'
        );
        $stmt->execute([
            'did' => $discordUserId !== '' ? $discordUserId : null,
            'du' => $discordUsername !== '' ? $discordUsername : null,
            'dgn' => $discordGlobalName !== '' ? $discordGlobalName : null,
            'dav' => $discordAvatar !== '' ? $discordAvatar : null,
            'id' => $id,
        ]);
    }

    public function unlinkDiscord(int $id): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users'
            . ' SET discord_user_id = NULL, discord_username = NULL, discord_global_name = NULL, discord_avatar = NULL'
            . ' WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
    }
}
