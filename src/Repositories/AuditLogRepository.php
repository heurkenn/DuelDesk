<?php

declare(strict_types=1);

namespace DuelDesk\Repositories;

use DuelDesk\Database\Db;
use PDO;

final class AuditLogRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Db::pdo();
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function create(?int $tournamentId, ?int $userId, string $action, ?string $entityType = null, ?int $entityId = null, array $meta = []): void
    {
        $metaJson = null;
        if ($meta !== []) {
            $json = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (is_string($json)) {
                $metaJson = $json;
            }
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO audit_logs (tournament_id, user_id, action, entity_type, entity_id, meta_json)'
            . ' VALUES (:tournament_id, :user_id, :action, :entity_type, :entity_id, :meta_json)'
        );
        $stmt->execute([
            'tournament_id' => $tournamentId,
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'meta_json' => $metaJson,
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function listForTournament(int $tournamentId, int $limit = 60): array
    {
        if ($limit <= 0) {
            $limit = 60;
        }
        if ($limit > 200) {
            $limit = 200;
        }

        $stmt = $this->pdo->prepare(
            'SELECT a.*, u.username'
            . ' FROM audit_logs a'
            . ' LEFT JOIN users u ON u.id = a.user_id'
            . ' WHERE a.tournament_id = :tid'
            . ' ORDER BY a.id DESC'
            . ' LIMIT :limit'
        );
        $stmt->bindValue(':tid', $tournamentId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        /** @var list<array<string, mixed>> */
        return $stmt->fetchAll();
    }
}

