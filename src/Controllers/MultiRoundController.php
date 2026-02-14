<?php

declare(strict_types=1);

namespace DuelDesk\Controllers;

use DuelDesk\Http\Response;
use DuelDesk\Repositories\MatchRepository;
use DuelDesk\Repositories\MatchRoundRepository;
use DuelDesk\Repositories\TeamMemberRepository;
use DuelDesk\Repositories\TournamentRepository;
use DuelDesk\Services\MultiRoundMatchService;
use DuelDesk\Support\Auth;
use DuelDesk\Support\Csrf;
use DuelDesk\Support\Flash;

final class MultiRoundController
{
    /** @param array<string,string> $params */
    public function addRound(array $params = []): void
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

        $meId = Auth::id();
        if ($meId === null) {
            Response::badRequest('Not authenticated');
        }

        $tRepo = new TournamentRepository();
        $t = $tRepo->findById($tournamentId);
        if ($t === null) {
            Response::notFound();
        }
        if ((string)($t['participant_type'] ?? 'solo') !== 'team' || (string)($t['team_match_mode'] ?? 'standard') !== 'multi_round') {
            Response::badRequest('Multi-round mode not enabled');
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
        if (($match['team1_id'] ?? null) === null || ($match['team2_id'] ?? null) === null) {
            Flash::set('error', 'Match incomplet (TBD).');
            Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
        }

        $t1 = (int)$match['team1_id'];
        $t2 = (int)$match['team2_id'];
        $tmRepo = new TeamMemberRepository();
        $can = Auth::isAdmin() || $tmRepo->isCaptain($t1, $meId) || $tmRepo->isCaptain($t2, $meId);
        if (!$can) {
            Response::forbidden('Tu ne peux pas ajouter un round.');
        }

        $kind = trim((string)($_POST['kind'] ?? 'regular'));
        if (!in_array($kind, ['regular', 'tiebreak'], true)) {
            $kind = 'regular';
        }

        $p1Raw = trim((string)($_POST['points1'] ?? ''));
        $p2Raw = trim((string)($_POST['points2'] ?? ''));
        $note = trim((string)($_POST['note'] ?? ''));

        if ($p1Raw === '' || $p2Raw === '' || !preg_match('/^-?\\d+$/', $p1Raw) || !preg_match('/^-?\\d+$/', $p2Raw)) {
            Flash::set('error', 'Points invalides.');
            Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
        }
        $p1 = (int)$p1Raw;
        $p2 = (int)$p2Raw;
        if ($p1 < -9999 || $p1 > 9999 || $p2 < -9999 || $p2 > 9999) {
            Flash::set('error', 'Points invalides (-9999..9999).');
            Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
        }
        if ($note !== '' && mb_strlen($note) > 255) {
            Flash::set('error', 'Note trop longue (255).');
            Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
        }

        $rRepo = new MatchRoundRepository();
        try {
            $rRepo->addRound($matchId, $kind, $p1, $p2, $note, $meId);
        } catch (\Throwable $e) {
            Flash::set('error', $e->getMessage());
            Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
        }

        Flash::set('success', 'Round ajoute.');
        Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
    }

    /** @param array<string,string> $params */
    public function deleteRound(array $params = []): void
    {
        Auth::requireAdmin();

        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            Response::badRequest('Invalid CSRF token');
        }
        Csrf::rotate();

        $tournamentId = (int)($params['id'] ?? 0);
        $matchId = (int)($params['matchId'] ?? 0);
        $roundId = (int)($params['roundId'] ?? 0);
        if ($tournamentId <= 0 || $matchId <= 0 || $roundId <= 0) {
            Response::notFound();
        }

        $rRepo = new MatchRoundRepository();
        $rRepo->deleteRound($roundId);
        Flash::set('success', 'Round supprime.');
        Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
    }

    /** @param array<string,string> $params */
    public function finalize(array $params = []): void
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

        $meId = Auth::id();
        if ($meId === null) {
            Response::badRequest('Not authenticated');
        }

        $tRepo = new TournamentRepository();
        $t = $tRepo->findById($tournamentId);
        if ($t === null) {
            Response::notFound();
        }
        if ((string)($t['participant_type'] ?? 'solo') !== 'team' || (string)($t['team_match_mode'] ?? 'standard') !== 'multi_round') {
            Response::badRequest('Multi-round mode not enabled');
        }

        $mRepo = new MatchRepository();
        $detailed = $mRepo->findTeamDetailed($matchId);
        $base = $mRepo->findById($matchId);
        if (!is_array($detailed) || !is_array($base) || (int)($base['tournament_id'] ?? 0) !== $tournamentId) {
            Response::notFound();
        }

        if (($detailed['team1_id'] ?? null) === null || ($detailed['team2_id'] ?? null) === null) {
            Flash::set('error', 'Match incomplet (TBD).');
            Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
        }

        $t1 = (int)$detailed['team1_id'];
        $t2 = (int)$detailed['team2_id'];
        $tmRepo = new TeamMemberRepository();
        $can = Auth::isAdmin() || $tmRepo->isCaptain($t1, $meId) || $tmRepo->isCaptain($t2, $meId);
        if (!$can) {
            Response::forbidden('Tu ne peux pas finaliser ce match.');
        }

        try {
            $svc = new MultiRoundMatchService();
            $res = $svc->finalize($t, $base);
            if (!$res['ok'] && $res['needs_tiebreak']) {
                Flash::set('error', 'Egalite sur les points: ajoute un tiebreak round.');
                Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
            }
        } catch (\Throwable $e) {
            Flash::set('error', $e->getMessage());
            Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
        }

        Flash::set('success', 'Match confirme (multi-round).');
        Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
    }
}

