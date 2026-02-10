<?php

declare(strict_types=1);

namespace DuelDesk\Repositories;

use DuelDesk\Database\Db;
use PDO;

final class MatchRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Db::pdo();
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM matches WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function findByTournamentKey(int $tournamentId, string $bracket, int $round, int $roundPos): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM matches'
            . ' WHERE tournament_id = :tid AND bracket = :bracket AND round = :round AND round_pos = :round_pos'
            . ' LIMIT 1'
        );
        $stmt->execute([
            'tid' => $tournamentId,
            'bracket' => $bracket,
            'round' => $round,
            'round_pos' => $roundPos,
        ]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function findSoloDetailed(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT m.*, p1.handle AS p1_name, p2.handle AS p2_name, ru.username AS reported_by_username'
            . ' FROM matches m'
            . ' LEFT JOIN players p1 ON p1.id = m.player1_id'
            . ' LEFT JOIN players p2 ON p2.id = m.player2_id'
            . ' LEFT JOIN users ru ON ru.id = m.reported_by_user_id'
            . ' WHERE m.id = :id'
            . ' LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function findTeamDetailed(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT m.*, t1.name AS t1_name, t2.name AS t2_name, ru.username AS reported_by_username'
            . ' FROM matches m'
            . ' LEFT JOIN teams t1 ON t1.id = m.team1_id'
            . ' LEFT JOIN teams t2 ON t2.id = m.team2_id'
            . ' LEFT JOIN users ru ON ru.id = m.reported_by_user_id'
            . ' WHERE m.id = :id'
            . ' LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function countForTournament(int $tournamentId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM matches WHERE tournament_id = :tid');
        $stmt->execute(['tid' => $tournamentId]);
        return (int)$stmt->fetchColumn();
    }

    public function maxRoundForBracket(int $tournamentId, string $bracket): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(MAX(round), 0) FROM matches WHERE tournament_id = :tid AND bracket = :bracket'
        );
        $stmt->execute(['tid' => $tournamentId, 'bracket' => $bracket]);
        return (int)$stmt->fetchColumn();
    }

    public function deleteForTournament(int $tournamentId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM matches WHERE tournament_id = :tid');
        $stmt->execute(['tid' => $tournamentId]);
    }

    public function createSolo(int $tournamentId, string $bracket, int $round, int $roundPos, int $bestOf, ?int $p1, ?int $p2): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO matches (tournament_id, round, round_pos, bracket, best_of, player1_id, player2_id)'
            . ' VALUES (:tournament_id, :round, :round_pos, :bracket, :best_of, :p1, :p2)'
        );
        $stmt->execute([
            'tournament_id' => $tournamentId,
            'round' => $round,
            'round_pos' => $roundPos,
            'bracket' => $bracket,
            'best_of' => $bestOf,
            'p1' => $p1,
            'p2' => $p2,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function createTeam(int $tournamentId, string $bracket, int $round, int $roundPos, int $bestOf, ?int $t1, ?int $t2): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO matches (tournament_id, round, round_pos, bracket, best_of, team1_id, team2_id)'
            . ' VALUES (:tournament_id, :round, :round_pos, :bracket, :best_of, :t1, :t2)'
        );
        $stmt->execute([
            'tournament_id' => $tournamentId,
            'round' => $round,
            'round_pos' => $roundPos,
            'bracket' => $bracket,
            'best_of' => $bestOf,
            't1' => $t1,
            't2' => $t2,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function setSoloSlot(int $matchId, int $slot, ?int $playerId): void
    {
        $col = $slot === 1 ? 'player1_id' : 'player2_id';
        $stmt = $this->pdo->prepare("UPDATE matches SET {$col} = :pid WHERE id = :id");
        $stmt->execute(['pid' => $playerId, 'id' => $matchId]);
    }

    public function setTeamSlot(int $matchId, int $slot, ?int $teamId): void
    {
        $col = $slot === 1 ? 'team1_id' : 'team2_id';
        $stmt = $this->pdo->prepare("UPDATE matches SET {$col} = :tid WHERE id = :id");
        $stmt->execute(['tid' => $teamId, 'id' => $matchId]);
    }

    public function setSchedule(int $matchId, ?string $scheduledAt, ?string $status = null): void
    {
        if ($status === null) {
            $stmt = $this->pdo->prepare('UPDATE matches SET scheduled_at = :v WHERE id = :id');
            $stmt->execute(['v' => $scheduledAt, 'id' => $matchId]);
            return;
        }

        $stmt = $this->pdo->prepare('UPDATE matches SET scheduled_at = :v, status = :st WHERE id = :id');
        $stmt->execute([
            'v' => $scheduledAt,
            'st' => $status,
            'id' => $matchId,
        ]);
    }

    public function setBestOf(int $matchId, int $bestOf): void
    {
        $stmt = $this->pdo->prepare('UPDATE matches SET best_of = :bo WHERE id = :id');
        $stmt->execute([
            'bo' => $bestOf,
            'id' => $matchId,
        ]);
    }

    public function reportResult(int $matchId, int $score1, int $score2, int $winnerSlot, int $reportedByUserId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE matches SET"
            . " reported_score1 = :s1,"
            . " reported_score2 = :s2,"
            . " reported_winner_slot = :ws,"
            . " reported_by_user_id = :uid,"
            . " reported_at = NOW(),"
            . " status = 'reported'"
            . " WHERE id = :id"
        );
        $stmt->execute([
            's1' => $score1,
            's2' => $score2,
            'ws' => $winnerSlot,
            'uid' => $reportedByUserId,
            'id' => $matchId,
        ]);
    }

    public function clearReport(int $matchId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE matches SET"
            . " reported_score1 = NULL,"
            . " reported_score2 = NULL,"
            . " reported_winner_slot = NULL,"
            . " reported_by_user_id = NULL,"
            . " reported_at = NULL,"
            . " status = CASE WHEN scheduled_at IS NULL THEN 'pending' ELSE 'scheduled' END"
            . " WHERE id = :id"
        );
        $stmt->execute(['id' => $matchId]);
    }

    public function voidMatch(int $matchId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE matches SET"
            . " player1_id = NULL,"
            . " player2_id = NULL,"
            . " team1_id = NULL,"
            . " team2_id = NULL,"
            . " score1 = 0,"
            . " score2 = 0,"
            . " reported_score1 = NULL,"
            . " reported_score2 = NULL,"
            . " reported_winner_slot = NULL,"
            . " reported_by_user_id = NULL,"
            . " reported_at = NULL,"
            . " winner_id = NULL,"
            . " winner_team_id = NULL,"
            . " scheduled_at = NULL,"
            . " status = 'void'"
            . " WHERE id = :id"
        );
        $stmt->execute(['id' => $matchId]);
    }

    public function resetSoloForReplay(int $matchId, int $player1Id, int $player2Id): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE matches SET"
            . " player1_id = :p1,"
            . " player2_id = :p2,"
            . " team1_id = NULL,"
            . " team2_id = NULL,"
            . " score1 = 0,"
            . " score2 = 0,"
            . " reported_score1 = NULL,"
            . " reported_score2 = NULL,"
            . " reported_winner_slot = NULL,"
            . " reported_by_user_id = NULL,"
            . " reported_at = NULL,"
            . " winner_id = NULL,"
            . " winner_team_id = NULL,"
            . " status = CASE WHEN scheduled_at IS NULL THEN 'pending' ELSE 'scheduled' END"
            . " WHERE id = :id"
        );
        $stmt->execute([
            'p1' => $player1Id,
            'p2' => $player2Id,
            'id' => $matchId,
        ]);
    }

    public function resetTeamForReplay(int $matchId, int $team1Id, int $team2Id): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE matches SET"
            . " team1_id = :t1,"
            . " team2_id = :t2,"
            . " player1_id = NULL,"
            . " player2_id = NULL,"
            . " score1 = 0,"
            . " score2 = 0,"
            . " reported_score1 = NULL,"
            . " reported_score2 = NULL,"
            . " reported_winner_slot = NULL,"
            . " reported_by_user_id = NULL,"
            . " reported_at = NULL,"
            . " winner_team_id = NULL,"
            . " winner_id = NULL,"
            . " status = CASE WHEN scheduled_at IS NULL THEN 'pending' ELSE 'scheduled' END"
            . " WHERE id = :id"
        );
        $stmt->execute([
            't1' => $team1Id,
            't2' => $team2Id,
            'id' => $matchId,
        ]);
    }

    public function confirmSoloResult(int $matchId, int $score1, int $score2, int $winnerPlayerId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE matches SET"
            . " score1 = :s1,"
            . " score2 = :s2,"
            . " winner_id = :wid,"
            . " reported_score1 = NULL,"
            . " reported_score2 = NULL,"
            . " reported_winner_slot = NULL,"
            . " reported_by_user_id = NULL,"
            . " reported_at = NULL,"
            . " status = 'confirmed'"
            . " WHERE id = :id"
        );
        $stmt->execute([
            's1' => $score1,
            's2' => $score2,
            'wid' => $winnerPlayerId,
            'id' => $matchId,
        ]);
    }

    public function confirmSoloWinner(int $matchId, int $winnerPlayerId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE matches SET"
            . " winner_id = :wid,"
            . " reported_score1 = NULL,"
            . " reported_score2 = NULL,"
            . " reported_winner_slot = NULL,"
            . " reported_by_user_id = NULL,"
            . " reported_at = NULL,"
            . " status = 'confirmed'"
            . " WHERE id = :id"
        );
        $stmt->execute(['wid' => $winnerPlayerId, 'id' => $matchId]);
    }

    public function confirmTeamResult(int $matchId, int $score1, int $score2, int $winnerTeamId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE matches SET"
            . " score1 = :s1,"
            . " score2 = :s2,"
            . " winner_team_id = :wid,"
            . " reported_score1 = NULL,"
            . " reported_score2 = NULL,"
            . " reported_winner_slot = NULL,"
            . " reported_by_user_id = NULL,"
            . " reported_at = NULL,"
            . " status = 'confirmed'"
            . " WHERE id = :id"
        );
        $stmt->execute([
            's1' => $score1,
            's2' => $score2,
            'wid' => $winnerTeamId,
            'id' => $matchId,
        ]);
    }

    public function confirmTeamWinner(int $matchId, int $winnerTeamId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE matches SET"
            . " winner_team_id = :wid,"
            . " reported_score1 = NULL,"
            . " reported_score2 = NULL,"
            . " reported_winner_slot = NULL,"
            . " reported_by_user_id = NULL,"
            . " reported_at = NULL,"
            . " status = 'confirmed'"
            . " WHERE id = :id"
        );
        $stmt->execute(['wid' => $winnerTeamId, 'id' => $matchId]);
    }

    /** @return list<array<string, mixed>> */
    public function listSoloForTournament(int $tournamentId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT m.*, p1.handle AS p1_name, p2.handle AS p2_name, ru.username AS reported_by_username'
            . ' FROM matches m'
            . ' LEFT JOIN players p1 ON p1.id = m.player1_id'
            . ' LEFT JOIN players p2 ON p2.id = m.player2_id'
            . ' LEFT JOIN users ru ON ru.id = m.reported_by_user_id'
            . ' WHERE m.tournament_id = :tid'
            . ' ORDER BY m.bracket ASC, m.round ASC, m.round_pos ASC, m.id ASC'
        );
        $stmt->execute(['tid' => $tournamentId]);

        /** @var list<array<string, mixed>> */
        return $stmt->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function listTeamForTournament(int $tournamentId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT m.*, t1.name AS t1_name, t2.name AS t2_name, ru.username AS reported_by_username'
            . ' FROM matches m'
            . ' LEFT JOIN teams t1 ON t1.id = m.team1_id'
            . ' LEFT JOIN teams t2 ON t2.id = m.team2_id'
            . ' LEFT JOIN users ru ON ru.id = m.reported_by_user_id'
            . ' WHERE m.tournament_id = :tid'
            . ' ORDER BY m.bracket ASC, m.round ASC, m.round_pos ASC, m.id ASC'
        );
        $stmt->execute(['tid' => $tournamentId]);

        /** @var list<array<string, mixed>> */
        return $stmt->fetchAll();
    }
}
