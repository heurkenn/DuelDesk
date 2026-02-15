<?php

declare(strict_types=1);

namespace DuelDesk\Repositories;

use DuelDesk\Database\Db;
use PDO;

final class LanTeamTournamentTeamRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Db::pdo();
    }

    public function findTeamId(int $lanTeamId, int $tournamentId): ?int
    {
        $stmt = $this->pdo->prepare(
            'SELECT team_id FROM lan_team_tournament_teams WHERE lan_team_id = :ltid AND tournament_id = :tid LIMIT 1'
        );
        $stmt->execute(['ltid' => $lanTeamId, 'tid' => $tournamentId]);
        $v = $stmt->fetchColumn();
        if ($v === false || $v === null) {
            return null;
        }
        $id = (int)$v;
        return $id > 0 ? $id : null;
    }

    /** @return list<array{tournament_id:int,team_id:int}> */
    public function listLinks(int $lanTeamId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT tournament_id, team_id FROM lan_team_tournament_teams WHERE lan_team_id = :ltid ORDER BY tournament_id ASC'
        );
        $stmt->execute(['ltid' => $lanTeamId]);
        $rows = $stmt->fetchAll();
        $out = [];
        if (is_array($rows)) {
            foreach ($rows as $r) {
                if (!is_array($r)) {
                    continue;
                }
                $tid = (int)($r['tournament_id'] ?? 0);
                $teamId = (int)($r['team_id'] ?? 0);
                if ($tid > 0 && $teamId > 0) {
                    $out[] = ['tournament_id' => $tid, 'team_id' => $teamId];
                }
            }
        }
        return $out;
    }

    public function add(int $lanTeamId, int $tournamentId, int $teamId): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO lan_team_tournament_teams (lan_team_id, tournament_id, team_id)'
            . ' VALUES (:ltid, :tid, :team_id)'
        );
        $stmt->execute(['ltid' => $lanTeamId, 'tid' => $tournamentId, 'team_id' => $teamId]);
    }

    public function deleteByLanTeamId(int $lanTeamId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM lan_team_tournament_teams WHERE lan_team_id = :ltid');
        $stmt->execute(['ltid' => $lanTeamId]);
    }
}

