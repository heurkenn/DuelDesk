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
        $stmt = $this->pdo->prepare('SELECT id, username, role, created_at, updated_at FROM users WHERE id = :id');
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
}
