<?php

declare(strict_types=1);

namespace DuelDesk\Repositories;

use DuelDesk\Database\Db;
use PDO;

final class GameRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Db::pdo();
    }

    public function countAll(): int
    {
        return (int)$this->pdo->query('SELECT COUNT(*) FROM games')->fetchColumn();
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM games ORDER BY name ASC');
        /** @var list<array<string, mixed>> */
        return $stmt->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function search(string $query, string $sort = 'name'): array
    {
        $query = trim($query);
        $sort = trim($sort);

        $orderBy = 'name ASC';
        if ($sort === 'newest') {
            $orderBy = 'created_at DESC, name ASC';
        }

        // Simple "contains" search (case-insensitive with utf8mb4_unicode_ci).
        $sql = 'SELECT * FROM games';
        $params = [];

        if ($query !== '') {
            $sql .= ' WHERE name LIKE :q';
            $params['q'] = '%' . $query . '%';
        }

        $sql .= ' ORDER BY ' . $orderBy;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        /** @var list<array<string, mixed>> */
        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM games WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM games WHERE slug = :slug');
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function findByName(string $name): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM games WHERE name = :name');
        $stmt->execute(['name' => $name]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function create(string $name, string $slug, string $imagePath, int $imageWidth, int $imageHeight, string $imageMime): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO games (name, slug, image_path, image_width, image_height, image_mime) VALUES (:name, :slug, :image_path, :image_width, :image_height, :image_mime)'
        );

        $stmt->execute([
            'name' => $name,
            'slug' => $slug,
            'image_path' => $imagePath,
            'image_width' => $imageWidth,
            'image_height' => $imageHeight,
            'image_mime' => $imageMime,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function updateName(int $id, string $name): void
    {
        $stmt = $this->pdo->prepare('UPDATE games SET name = :name WHERE id = :id');
        $stmt->execute(['name' => $name, 'id' => $id]);
    }

    public function updateImageMeta(int $id, int $imageWidth, int $imageHeight, string $imageMime): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE games SET image_width = :image_width, image_height = :image_height, image_mime = :image_mime WHERE id = :id'
        );
        $stmt->execute([
            'image_width' => $imageWidth,
            'image_height' => $imageHeight,
            'image_mime' => $imageMime,
            'id' => $id,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM games WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function uniqueSlug(string $name): string
    {
        $base = \DuelDesk\Support\Str::slug($name);
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
        $stmt = $this->pdo->prepare('SELECT 1 FROM games WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        return (bool)$stmt->fetchColumn();
    }
}
