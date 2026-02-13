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

    public function countSearch(string $query): int
    {
        $query = trim($query);
        if ($query === '') {
            return $this->countAll();
        }

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM tournaments WHERE name LIKE ? OR game LIKE ? OR slug LIKE ?'
        );
        $v = '%' . $query . '%';
        $stmt->execute([$v, $v, $v]);
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
            $perPage = 20;
        }
        if ($perPage > 100) {
            $perPage = 100;
        }

        $offset = ($page - 1) * $perPage;

        $sql = 'SELECT t.*, g.image_path AS game_image_path'
            . ' FROM tournaments t'
            . ' LEFT JOIN games g ON g.id = t.game_id';

        $params = [];
        if ($query !== '') {
            $sql .= ' WHERE t.name LIKE :q1 OR t.game LIKE :q2 OR t.slug LIKE :q3';
            $v = '%' . $query . '%';
            $params = ['q1' => $v, 'q2' => $v, 'q3' => $v];
        }

        $sql .= ' ORDER BY t.created_at DESC LIMIT :limit OFFSET :offset';

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

    /** @return array<string, mixed>|null */
    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT t.*, g.image_path AS game_image_path'
            . ' FROM tournaments t'
            . ' LEFT JOIN games g ON g.id = t.game_id'
            . ' WHERE t.slug = :slug'
            . ' LIMIT 1'
        );
        $stmt->execute(['slug' => $slug]);
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
        ?string $startsAt,
        ?int $maxEntrants = null,
        ?string $signupClosesAt = null,
        int $bestOfDefault = 3,
        ?int $bestOfFinal = null
    ): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO tournaments (owner_user_id, game_id, name, slug, game, format, participant_type, team_size, status, starts_at, max_entrants, signup_closes_at, best_of_default, best_of_final)'
            . ' VALUES (:owner_user_id, :game_id, :name, :slug, :game, :format, :participant_type, :team_size, :status, :starts_at, :max_entrants, :signup_closes_at, :best_of_default, :best_of_final)'
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
            'max_entrants' => $maxEntrants,
            'signup_closes_at' => $signupClosesAt,
            'best_of_default' => $bestOfDefault,
            'best_of_final' => $bestOfFinal,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function updateSettings(int $tournamentId, string $status, ?string $startsAt, ?int $maxEntrants = null, ?string $signupClosesAt = null, int $bestOfDefault = 3, ?int $bestOfFinal = null): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE tournaments'
            . ' SET status = :status, starts_at = :starts_at, max_entrants = :max_entrants, signup_closes_at = :signup_closes_at, best_of_default = :best_of_default, best_of_final = :best_of_final'
            . ' WHERE id = :id'
        );
        $stmt->execute([
            'status' => $status,
            'starts_at' => $startsAt,
            'max_entrants' => $maxEntrants,
            'signup_closes_at' => $signupClosesAt,
            'best_of_default' => $bestOfDefault,
            'best_of_final' => $bestOfFinal,
            'id' => $tournamentId,
        ]);
    }

    public function updateConfig(
        int $tournamentId,
        string $name,
        int $gameId,
        string $gameName,
        string $format,
        string $participantType,
        ?int $teamSize
    ): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE tournaments'
            . ' SET name = :name, game_id = :game_id, game = :game, format = :format, participant_type = :participant_type, team_size = :team_size'
            . ' WHERE id = :id'
        );
        $stmt->execute([
            'name' => $name,
            'game_id' => $gameId,
            'game' => $gameName,
            'format' => $format,
            'participant_type' => $participantType,
            'team_size' => $teamSize,
            'id' => $tournamentId,
        ]);
    }

    public function delete(int $tournamentId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM tournaments WHERE id = :id');
        $stmt->execute(['id' => $tournamentId]);
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
