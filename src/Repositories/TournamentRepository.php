<?php

declare(strict_types=1);

namespace DuelDesk\Repositories;

use DuelDesk\Database\Db;
use PDO;

final class TournamentRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Db::pdo();
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        $stmt = $this->pdo->query(
            'SELECT t.*, g.image_path AS game_image_path'
            . ' FROM tournaments t'
            . ' LEFT JOIN games g ON g.id = t.game_id'
            . ' ORDER BY t.created_at DESC'
        );
        /** @var list<array<string, mixed>> */
        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT t.*, g.image_path AS game_image_path'
            . ' FROM tournaments t'
            . ' LEFT JOIN games g ON g.id = t.game_id'
            . ' WHERE t.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function countAll(): int
    {
        return (int)$this->pdo->query('SELECT COUNT(*) FROM tournaments')->fetchColumn();
    }

    public function countByOwner(int $ownerUserId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM tournaments WHERE owner_user_id = :uid');
        $stmt->execute(['uid' => $ownerUserId]);
        return (int)$stmt->fetchColumn();
    }

    /** @return list<array<string, mixed>> */
    public function allByOwner(int $ownerUserId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT t.*, g.image_path AS game_image_path'
            . ' FROM tournaments t'
            . ' LEFT JOIN games g ON g.id = t.game_id'
            . ' WHERE t.owner_user_id = :uid'
            . ' ORDER BY t.created_at DESC'
        );
        $stmt->execute(['uid' => $ownerUserId]);
        /** @var list<array<string, mixed>> */
        return $stmt->fetchAll();
    }

    public function create(
        ?int $ownerUserId,
        int $gameId,
        string $gameName,
        string $name,
        string $format,
        string $participantType,
        ?int $teamSize,
        string $status,
        ?string $startsAt
    ): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO tournaments (owner_user_id, game_id, name, slug, game, format, participant_type, team_size, status, starts_at)'
            . ' VALUES (:owner_user_id, :game_id, :name, :slug, :game, :format, :participant_type, :team_size, :status, :starts_at)'
        );

        $slug = $this->uniqueSlug($name);

        $stmt->execute([
            'owner_user_id' => $ownerUserId,
            'game_id' => $gameId,
            'name' => $name,
            'slug' => $slug,
            'game' => $gameName,
            'format' => $format,
            'participant_type' => $participantType,
            'team_size' => $teamSize,
            'status' => $status,
            'starts_at' => $startsAt,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function updateSettings(int $tournamentId, string $status, ?string $startsAt): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE tournaments SET status = :status, starts_at = :starts_at WHERE id = :id'
        );
        $stmt->execute([
            'status' => $status,
            'starts_at' => $startsAt,
            'id' => $tournamentId,
        ]);
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
                // Extremely unlikely unless there is slug abuse.
                $slug = $base . '-' . bin2hex(random_bytes(4));
                break;
            }
        }

        return $slug;
    }

    private function slugExists(string $slug): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM tournaments WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        return (bool)$stmt->fetchColumn();
    }
}
