<?php

declare(strict_types=1);

namespace DuelDesk\Repositories;

use DuelDesk\Database\Db;
use PDO;

final class TeamMemberRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Db::pdo();
    }

    /** @return list<array<string, mixed>> */
    public function listMembers(int $teamId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT tm.team_id, tm.user_id, tm.role, tm.joined_at, u.username'
            . ' FROM team_members tm'
            . ' JOIN users u ON u.id = tm.user_id'
            . ' WHERE tm.team_id = :tid'
            . " ORDER BY (tm.role = 'captain') DESC, tm.joined_at ASC"
        );
        $stmt->execute(['tid' => $teamId]);

        /** @var list<array<string, mixed>> */
        return $stmt->fetchAll();
    }

    /**
     * @param list<int> $teamIds
     * @return array<int, list<array{user_id:int,username:string,role:string}>>
     */
    public function listMembersForTeams(array $teamIds): array
    {
        $teamIds = array_values(array_filter($teamIds, static fn ($v) => is_int($v) && $v > 0));
        if ($teamIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($teamIds), '?'));
        $stmt = $this->pdo->prepare(
            'SELECT tm.team_id, tm.user_id, tm.role, u.username'
            . ' FROM team_members tm'
            . ' JOIN users u ON u.id = tm.user_id'
            . ' WHERE tm.team_id IN (' . $placeholders . ')'
            . " ORDER BY tm.team_id ASC, (tm.role = 'captain') DESC, tm.joined_at ASC"
        );
        $stmt->execute($teamIds);

        /** @var list<array<string, mixed>> */
        $rows = $stmt->fetchAll();

        $out = [];
        foreach ($rows as $r) {
            $tid = (int)($r['team_id'] ?? 0);
            if ($tid <= 0) {
                continue;
            }

            $out[$tid] ??= [];
            $out[$tid][] = [
                'user_id' => (int)($r['user_id'] ?? 0),
                'username' => (string)($r['username'] ?? ''),
                'role' => (string)($r['role'] ?? 'member'),
            ];
        }

        return $out;
    }

    public function countMembers(int $teamId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM team_members WHERE team_id = :tid');
        $stmt->execute(['tid' => $teamId]);
        return (int)$stmt->fetchColumn();
    }

    public function maxMembersForTournament(int $tournamentId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(MAX(x.c), 0)'
            . ' FROM ('
            . '   SELECT COUNT(*) AS c'
            . '   FROM team_members tm'
            . '   JOIN teams t ON t.id = tm.team_id'
            . '   WHERE t.tournament_id = :tid'
            . '   GROUP BY tm.team_id'
            . ' ) x'
        );
        $stmt->execute(['tid' => $tournamentId]);
        return (int)$stmt->fetchColumn();
    }

    public function isUserInTeam(int $teamId, int $userId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM team_members WHERE team_id = :tid AND user_id = :uid LIMIT 1'
        );
        $stmt->execute(['tid' => $teamId, 'uid' => $userId]);
        return (bool)$stmt->fetchColumn();
    }

    public function isCaptain(int $teamId, int $userId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM team_members WHERE team_id = :tid AND user_id = :uid AND role = 'captain' LIMIT 1"
        );
        $stmt->execute(['tid' => $teamId, 'uid' => $userId]);
        return (bool)$stmt->fetchColumn();
    }

    /** @return array<string, mixed>|null */
    public function findTeamForUserInTournament(int $tournamentId, int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT t.*'
            . ' FROM teams t'
            . ' JOIN team_members tm ON tm.team_id = t.id'
            . ' WHERE t.tournament_id = :tid AND tm.user_id = :uid'
            . ' LIMIT 1'
        );
        $stmt->execute(['tid' => $tournamentId, 'uid' => $userId]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function addMember(int $teamId, int $userId, string $role = 'member'): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO team_members (team_id, user_id, role) VALUES (:tid, :uid, :role)'
        );
        $stmt->execute([
            'tid' => $teamId,
            'uid' => $userId,
            'role' => $role,
        ]);
    }

    public function removeMember(int $teamId, int $userId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM team_members WHERE team_id = :tid AND user_id = :uid');
        $stmt->execute(['tid' => $teamId, 'uid' => $userId]);
    }

    public function captainUserId(int $teamId): ?int
    {
        $stmt = $this->pdo->prepare(
            "SELECT user_id FROM team_members WHERE team_id = :tid AND role = 'captain' LIMIT 1"
        );
        $stmt->execute(['tid' => $teamId]);
        $id = $stmt->fetchColumn();
        $id = is_int($id) || is_string($id) ? (int)$id : 0;
        return $id > 0 ? $id : null;
    }

    public function setRole(int $teamId, int $userId, string $role): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE team_members SET role = :role WHERE team_id = :tid AND user_id = :uid'
        );
        $stmt->execute(['role' => $role, 'tid' => $teamId, 'uid' => $userId]);
    }

    public function promoteOldestMemberToCaptain(int $teamId): ?int
    {
        $stmt = $this->pdo->prepare(
            "SELECT user_id FROM team_members WHERE team_id = :tid ORDER BY joined_at ASC LIMIT 1"
        );
        $stmt->execute(['tid' => $teamId]);
        $uid = $stmt->fetchColumn();
        $uid = is_int($uid) || is_string($uid) ? (int)$uid : 0;
        if ($uid <= 0) {
            return null;
        }

        // Reset roles, then set captain.
        $this->pdo->prepare("UPDATE team_members SET role = 'member' WHERE team_id = :tid")->execute(['tid' => $teamId]);
        $this->setRole($teamId, $uid, 'captain');

        return $uid;
    }

    public function setCaptain(int $teamId, int $userId): void
    {
        $this->pdo->beginTransaction();

        try {
            $this->pdo->prepare("UPDATE team_members SET role = 'member' WHERE team_id = :tid")->execute(['tid' => $teamId]);

            $stmt = $this->pdo->prepare("UPDATE team_members SET role = 'captain' WHERE team_id = :tid AND user_id = :uid");
            $stmt->execute(['tid' => $teamId, 'uid' => $userId]);
            if ($stmt->rowCount() <= 0) {
                throw new \RuntimeException('Target user is not in the team');
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
