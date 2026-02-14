<?php

declare(strict_types=1);

namespace DuelDesk\Repositories;

use DuelDesk\Database\Db;
use PDO;

final class LanEventRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Db::pdo();
    }

    public function countAll(): int
    {
        return (int)$this->pdo->query('SELECT COUNT(*) FROM lan_events')->fetchColumn();
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM lan_events ORDER BY created_at DESC');
        /** @var list<array<string, mixed>> */
        return $stmt->fetchAll();
    }

    /** @return list<array{id:int,name:string,slug:string,participant_type:string,status:string,starts_at:mixed,ends_at:mixed,location:mixed,created_at:mixed}> */
    public function listForSelect(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, name, slug, participant_type, status, starts_at, ends_at, location, created_at'
            . ' FROM lan_events ORDER BY created_at DESC, name ASC'
        );
        /** @var list<array{id:int,name:string,slug:string,participant_type:string,status:string,starts_at:mixed,ends_at:mixed,location:mixed,created_at:mixed}> */
        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM lan_events WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM lan_events WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function create(
        ?int $ownerUserId,
        string $name,
        string $participantType = 'solo',
        string $status = 'draft',
        ?string $startsAt = null,
        ?string $endsAt = null,
        ?string $location = null,
        ?string $description = null
    ): int
    {
        $slug = $this->uniqueSlug($name);

        $stmt = $this->pdo->prepare(
            'INSERT INTO lan_events (owner_user_id, name, slug, participant_type, status, starts_at, ends_at, location, description)'
            . ' VALUES (:owner_user_id, :name, :slug, :participant_type, :status, :starts_at, :ends_at, :location, :description)'
        );
        $stmt->execute([
            'owner_user_id' => $ownerUserId,
            'name' => $name,
            'slug' => $slug,
            'participant_type' => $participantType,
            'status' => $status,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'location' => $location,
            'description' => $description,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function update(
        int $id,
        string $name,
        string $participantType,
        string $status,
        ?string $startsAt = null,
        ?string $endsAt = null,
        ?string $location = null,
        ?string $description = null
    ): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE lan_events'
            . ' SET name = :name, participant_type = :participant_type, status = :status, starts_at = :starts_at, ends_at = :ends_at, location = :location, description = :description'
            . ' WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'name' => $name,
            'participant_type' => $participantType,
            'status' => $status,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'location' => $location,
            'description' => $description,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM lan_events WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function uniqueSlug(string $name): string
    {
        $base = \DuelDesk\Support\Str::slug($name);
        if ($base === 'tournament') {
            $base = 'lan';
        }

        $slug = $base;
        $i = 2;

        while ($this->slugExists($slug)) {
            $slug = $base . '-' . $i;
            $i++;
            if ($i > 200) {
                $slug = $base . '-' . bin2hex(random_bytes(4));
                break;
            }
        }

        return $slug;
    }

    private function slugExists(string $slug): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM lan_events WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        return (bool)$stmt->fetchColumn();
    }
}
