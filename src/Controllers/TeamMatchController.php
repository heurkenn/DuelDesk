<?php

declare(strict_types=1);

namespace DuelDesk\Controllers;

use DuelDesk\Http\Response;
use DuelDesk\Repositories\MatchRepository;
use DuelDesk\Repositories\MatchTeamDuelRepository;
use DuelDesk\Repositories\MatchTeamLineupRepository;
use DuelDesk\Repositories\TeamMemberRepository;
use DuelDesk\Repositories\TournamentRepository;
use DuelDesk\Services\TeamMatchService;
use DuelDesk\Support\Auth;
use DuelDesk\Support\Csrf;
use DuelDesk\Support\Flash;

final class TeamMatchController
{
    /** @param array<string,string> $params */
    public function setLineup(array $params = []): void
    {
        Auth::requireLogin();

        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            Response::badRequest('Invalid CSRF token');
        }
        Csrf::rotate();

        $tournamentId = (int)($params['id'] ?? 0);
        $matchId = (int)($params['matchId'] ?? 0);
        if ($tournamentId <= 0 || $matchId <= 0) {
            Response::notFound();
        }

        $tRepo = new TournamentRepository();
        $t = $tRepo->findById($tournamentId);
        if ($t === null) {
            Response::notFound();
        }

        if ((string)($t['participant_type'] ?? 'solo') !== 'team' || (string)($t['team_match_mode'] ?? 'standard') !== 'lineup_duels') {
            Response::badRequest('Team match mode not enabled');
        }

        $mRepo = new MatchRepository();
        $match = $mRepo->findTeamDetailed($matchId);
        if (!is_array($match) || (int)($match['tournament_id'] ?? 0) !== $tournamentId) {
            Response::notFound();
        }

        $st = (string)($match['status'] ?? 'pending');
        if (in_array($st, ['confirmed', 'void'], true)) {
            Flash::set('error', 'Match termine.');
            Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
        }

        $teamSlotRaw = trim((string)($_POST['team_slot'] ?? ''));
        $teamSlot = ctype_digit($teamSlotRaw) ? (int)$teamSlotRaw : 0;
        if ($teamSlot !== 1 && $teamSlot !== 2) {
            Flash::set('error', 'Team slot invalide.');
            Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
        }

        $teamId = $teamSlot === 1
            ? ($match['team1_id'] !== null ? (int)$match['team1_id'] : 0)
            : ($match['team2_id'] !== null ? (int)$match['team2_id'] : 0);
        if ($teamId <= 0) {
            Flash::set('error', 'Match incomplet (TBD).');
            Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
        }

        $meId = Auth::id();
        if ($meId === null) {
            Response::badRequest('Not authenticated');
        }

        $tmRepo = new TeamMemberRepository();
        $can = Auth::isAdmin() || $tmRepo->isCaptain($teamId, $meId);
        if (!$can) {
            Response::forbidden("Tu ne peux pas definir l'ordre.");
        }

        $teamSize = (int)($t['team_size'] ?? 0);
        if ($teamSize <= 0) {
            $teamSize = 2;
        }

        // Lock lineups once duels exist (simple safety rule).
        $duelRepo = new MatchTeamDuelRepository();
        if ($duelRepo->countDuels($matchId) > 0) {
            Flash::set('error', 'Ordre verrouille (duels deja generes).');
            Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
        }

        $members = $tmRepo->listMembers($teamId);
        $roster = [];
        foreach ($members as $m) {
            if (!is_array($m)) continue;
            $uid = (int)($m['user_id'] ?? 0);
            if ($uid > 0) {
                $roster[$uid] = true;
            }
        }
        if (count($roster) !== $teamSize) {
            Flash::set('error', "Roster invalide: il faut {$teamSize} membre(s).");
            Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
        }

        $raw = $_POST['lineup'] ?? null;
        if (!is_array($raw)) {
            Flash::set('error', 'Lineup invalide.');
            Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
        }

        // Normalize to ordered list [pos1, pos2, ...].
        $ordered = [];
        for ($i = 1; $i <= $teamSize; $i++) {
            $v = $raw[(string)$i] ?? $raw[$i] ?? null;
            $v = is_string($v) || is_int($v) ? (string)$v : '';
            $v = trim($v);
            if ($v === '' || !ctype_digit($v)) {
                Flash::set('error', 'Lineup invalide (positions manquantes).');
                Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
            }
            $uid = (int)$v;
            $ordered[] = $uid;
        }

        // Validate: permutation of roster.
        $seen = [];
        foreach ($ordered as $uid) {
            if ($uid <= 0 || !isset($roster[$uid])) {
                Flash::set('error', 'Lineup invalide (membre hors equipe).');
                Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
            }
            if (isset($seen[$uid])) {
                Flash::set('error', 'Lineup invalide (doublon).');
                Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
            }
            $seen[$uid] = true;
        }

        $lineupRepo = new MatchTeamLineupRepository();
        try {
            $lineupRepo->replaceLineup($matchId, $teamSlot, $ordered);
        } catch (\Throwable $e) {
            Flash::set('error', $e->getMessage());
            Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
        }

        // If both lineups exist, create regular duels.
        try {
            $svc = new TeamMatchService();
            $svc->ensureRegularDuels($t, $match);
        } catch (\Throwable $e) {
            Flash::set('error', 'Ordre enregistre, mais creation des duels echouee: ' . $e->getMessage());
            Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
        }

        Flash::set('success', 'Ordre enregistre.');
        Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
    }

    /** @param array<string,string> $params */
    public function confirmDuel(array $params = []): void
    {
        Auth::requireLogin();

        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            Response::badRequest('Invalid CSRF token');
        }
        Csrf::rotate();

        $tournamentId = (int)($params['id'] ?? 0);
        $matchId = (int)($params['matchId'] ?? 0);
        $duelId = (int)($params['duelId'] ?? 0);
        if ($tournamentId <= 0 || $matchId <= 0 || $duelId <= 0) {
            Response::notFound();
        }

        $meId = Auth::id();
        if ($meId === null) {
            Response::badRequest('Not authenticated');
        }

        $tRepo = new TournamentRepository();
        $t = $tRepo->findById($tournamentId);
        if ($t === null) {
            Response::notFound();
        }
        if ((string)($t['participant_type'] ?? 'solo') !== 'team' || (string)($t['team_match_mode'] ?? 'standard') !== 'lineup_duels') {
            Response::badRequest('Team match mode not enabled');
        }

        $mRepo = new MatchRepository();
        $match = $mRepo->findTeamDetailed($matchId);
        if (!is_array($match) || (int)($match['tournament_id'] ?? 0) !== $tournamentId) {
            Response::notFound();
        }

        $st = (string)($match['status'] ?? 'pending');
        if (in_array($st, ['confirmed', 'void'], true)) {
            Flash::set('error', 'Match termine.');
            Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
        }

        $winnerRaw = trim((string)($_POST['winner_slot'] ?? ''));
        $winnerSlot = ctype_digit($winnerRaw) ? (int)$winnerRaw : 0;
        if ($winnerSlot !== 1 && $winnerSlot !== 2) {
            Flash::set('error', 'Winner invalide.');
            Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
        }

        $duelRepo = new MatchTeamDuelRepository();
        $duel = $duelRepo->findById($duelId);
        if (!is_array($duel) || (int)($duel['match_id'] ?? 0) !== $matchId) {
            Response::notFound();
        }

        // Permission: admin OR captain of either team OR involved player.
        $t1 = $match['team1_id'] !== null ? (int)$match['team1_id'] : 0;
        $t2 = $match['team2_id'] !== null ? (int)$match['team2_id'] : 0;
        $tmRepo = new TeamMemberRepository();

        $isCaptain = ($t1 > 0 && $tmRepo->isCaptain($t1, $meId)) || ($t2 > 0 && $tmRepo->isCaptain($t2, $meId));
        $isPlayer = ((int)($duel['team1_user_id'] ?? 0) === $meId) || ((int)($duel['team2_user_id'] ?? 0) === $meId);

        if (!Auth::isAdmin() && !$isCaptain && !$isPlayer) {
            Response::forbidden("Tu ne peux pas confirmer ce duel.");
        }

        try {
            $duelRepo->confirmDuel($duelId, $winnerSlot, $meId);
        } catch (\Throwable $e) {
            Flash::set('error', $e->getMessage());
            Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
        }

        try {
            $svc = new TeamMatchService();
            $svc->maybeFinalizeMatch($t, $match);
        } catch (\Throwable $e) {
            Flash::set('error', 'Duel confirme, mais finalisation match echouee: ' . $e->getMessage());
            Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
        }

        Flash::set('success', 'Duel confirme.');
        Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
    }
}
