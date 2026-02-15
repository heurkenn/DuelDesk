<?php

declare(strict_types=1);

namespace DuelDesk\Repositories;

use DuelDesk\Database\Db;
use PDO;

final class LanTeamMemberRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Db::pdo();
    }

    /** @return list<array<string,mixed>> */
    public function listMembers(int $lanTeamId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ltm.lan_team_id, ltm.user_id, ltm.role, ltm.joined_at, u.username'
            . ' FROM lan_team_members ltm'
            . ' JOIN users u ON u.id = ltm.user_id'
            . ' WHERE ltm.lan_team_id = :tid'
            . " ORDER BY (ltm.role = 'captain') DESC, ltm.joined_at ASC"
        );
        $stmt->execute(['tid' => $lanTeamId]);
        /** @var list<array<string,mixed>> */
        return $stmt->fetchAll();
    }

    public function countMembers(int $lanTeamId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM lan_team_members WHERE lan_team_id = :tid');
        $stmt->execute(['tid' => $lanTeamId]);
        return (int)$stmt->fetchColumn();
    }

    public function isMember(int $lanTeamId, int $userId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM lan_team_members WHERE lan_team_id = :tid AND user_id = :uid LIMIT 1');
        $stmt->execute(['tid' => $lanTeamId, 'uid' => $userId]);
        return (bool)$stmt->fetchColumn();
    }

    public function isCaptain(int $lanTeamId, int $userId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM lan_team_members WHERE lan_team_id = :tid AND user_id = :uid AND role = 'captain' LIMIT 1"
        );
        $stmt->execute(['tid' => $lanTeamId, 'uid' => $userId]);
        return (bool)$stmt->fetchColumn();
    }

    public function addMember(int $lanTeamId, int $userId, string $role = 'member'): void
    {
        if (!in_array($role, ['captain', 'member'], true)) {
            $role = 'member';
        }
        $stmt = $this->pdo->prepare(
            'INSERT IGNORE INTO lan_team_members (lan_team_id, user_id, role) VALUES (:tid, :uid, :role)'
        );
        $stmt->execute(['tid' => $lanTeamId, 'uid' => $userId, 'role' => $role]);
    }

    public function removeMember(int $lanTeamId, int $userId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM lan_team_members WHERE lan_team_id = :tid AND user_id = :uid');
        $stmt->execute(['tid' => $lanTeamId, 'uid' => $userId]);
    }

    public function setRole(int $lanTeamId, int $userId, string $role): void
    {
        if (!in_array($role, ['captain', 'member'], true)) {
            $role = 'member';
        }
        $stmt = $this->pdo->prepare('UPDATE lan_team_members SET role = :role WHERE lan_team_id = :tid AND user_id = :uid');
        $stmt->execute(['role' => $role, 'tid' => $lanTeamId, 'uid' => $userId]);
    }

    public function findOldestMemberUserId(int $lanTeamId): ?int
    {
        $stmt = $this->pdo->prepare('SELECT user_id FROM lan_team_members WHERE lan_team_id = :tid ORDER BY joined_at ASC LIMIT 1');
        $stmt->execute(['tid' => $lanTeamId]);
        $v = $stmt->fetchColumn();
        if ($v === false || $v === null) {
            return null;
        }
        $id = (int)$v;
        return $id > 0 ? $id : null;
    }
}

