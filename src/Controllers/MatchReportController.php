<?php

declare(strict_types=1);

namespace DuelDesk\Controllers;

use DuelDesk\Http\Response;
use DuelDesk\Repositories\AuditLogRepository;
use DuelDesk\Repositories\MatchRepository;
use DuelDesk\Repositories\PlayerRepository;
use DuelDesk\Repositories\TeamMemberRepository;
use DuelDesk\Repositories\TournamentRepository;
use DuelDesk\Support\Auth;
use DuelDesk\Support\Csrf;
use DuelDesk\Support\Flash;

final class MatchReportController
{
    /** @param array<string, string> $params */
    public function report(array $params = []): void
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

        $participantType = (string)($t['participant_type'] ?? 'solo');

        $mRepo = new MatchRepository();
        $match = $mRepo->findById($matchId);
        if (!is_array($match) || (int)($match['tournament_id'] ?? 0) !== $tournamentId) {
            Response::notFound();
        }

        $st = (string)($match['status'] ?? 'pending');
        if (in_array($st, ['confirmed', 'void'], true)) {
            Flash::set('error', 'Ce match ne peut pas etre reporte.');
            Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
        }

        if (!$this->canReport($participantType, $match, $meId)) {
            Response::forbidden('Tu ne peux pas reporter ce match.');
        }

        $winnerSlot = (string)($_POST['winner_slot'] ?? '');
        $score1Raw = trim((string)($_POST['score1'] ?? ''));
        $score2Raw = trim((string)($_POST['score2'] ?? ''));

        if ($score1Raw === '' || $score2Raw === '' || !ctype_digit($score1Raw) || !ctype_digit($score2Raw)) {
            Flash::set('error', 'Scores invalides.');
            Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
        }

        $score1 = (int)$score1Raw;
        $score2 = (int)$score2Raw;
        if ($score1 < 0 || $score2 < 0 || $score1 > 99 || $score2 > 99) {
            Flash::set('error', 'Scores invalides (0-99).');
            Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
        }

        if (!in_array($winnerSlot, ['1', '2'], true)) {
            Flash::set('error', 'Winner invalide.');
            Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
        }

        // Must be a fully defined match (no TBD).
        if (!$this->isMatchComplete($participantType, $match)) {
            Flash::set('error', 'Match incomplet (TBD).');
            Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
        }

        // Best-of validation (same rules as admin).
        $bestOf = (int)($match['best_of'] ?? 0);
        if (!in_array($bestOf, [1, 3, 5, 7, 9], true)) {
            $bestOf = (int)($t['best_of_default'] ?? 3);
        }
        if (!in_array($bestOf, [1, 3, 5, 7, 9], true)) {
            $bestOf = 3;
        }

        $winsToTake = intdiv($bestOf, 2) + 1;
        $looksLikeSeries = ($score1 <= $bestOf) && ($score2 <= $bestOf);

        if ($winnerSlot === '1' && $score1 <= $score2) {
            Flash::set('error', 'Score incoherent: winner=A mais scoreA <= scoreB.');
            Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
        }
        if ($winnerSlot === '2' && $score2 <= $score1) {
            Flash::set('error', 'Score incoherent: winner=B mais scoreB <= scoreA.');
            Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
        }

        if ($looksLikeSeries) {
            if ($winnerSlot === '1' && $score1 !== $winsToTake) {
                Flash::set('error', "Score BO{$bestOf} incoherent: il faut {$winsToTake} victoire(s) pour gagner.");
                Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
            }
            if ($winnerSlot === '2' && $score2 !== $winsToTake) {
                Flash::set('error', "Score BO{$bestOf} incoherent: il faut {$winsToTake} victoire(s) pour gagner.");
                Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
            }
            if ($score1 >= $winsToTake && $score2 >= $winsToTake) {
                Flash::set('error', "Score BO{$bestOf} incoherent: les deux cotes ne peuvent pas atteindre {$winsToTake}.");
                Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
            }
        }

        try {
            $mRepo->reportResult($matchId, $score1, $score2, (int)$winnerSlot, $meId);
        } catch (\Throwable $e) {
            Flash::set('error', $e->getMessage());
            Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
        }

        $updated = $mRepo->findById($matchId);
        $newStatus = is_array($updated) ? (string)($updated['status'] ?? '') : '';

        try {
            $aRepo = new AuditLogRepository();
            $aRepo->create($tournamentId, $meId, 'match.report', 'match', $matchId, [
                'status' => $newStatus,
                'score1' => $score1,
                'score2' => $score2,
                'winner_slot' => (int)$winnerSlot,
            ]);
        } catch (\Throwable) {
            // Ignore audit failures.
        }

        if ($newStatus === 'disputed') {
            Flash::set('success', 'Score contre-reporte. Match en litige (validation admin requise).');
        } else {
            Flash::set('success', 'Score reporte. En attente de validation admin.');
        }
        Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
    }

    /** @param array<string, mixed> $match */
    private function canReport(string $participantType, array $match, int $userId): bool
    {
        if (Auth::isAdmin()) {
            return true;
        }

        if ($participantType === 'team') {
            $t1 = $match['team1_id'] !== null ? (int)$match['team1_id'] : 0;
            $t2 = $match['team2_id'] !== null ? (int)$match['team2_id'] : 0;
            if ($t1 <= 0 || $t2 <= 0) {
                return false;
            }

            $tmRepo = new TeamMemberRepository();
            return $tmRepo->isCaptain($t1, $userId) || $tmRepo->isCaptain($t2, $userId);
        }

        $pRepo = new PlayerRepository();
        $p = $pRepo->findByUserId($userId);
        if ($p === null) {
            return false;
        }

        $pid = (int)($p['id'] ?? 0);
        if ($pid <= 0) {
            return false;
        }

        $a = $match['player1_id'] !== null ? (int)$match['player1_id'] : 0;
        $b = $match['player2_id'] !== null ? (int)$match['player2_id'] : 0;

        return ($a === $pid) || ($b === $pid);
    }

    /** @param array<string, mixed> $match */
    private function isMatchComplete(string $participantType, array $match): bool
    {
        if ($participantType === 'team') {
            return $match['team1_id'] !== null && $match['team2_id'] !== null;
        }

        return $match['player1_id'] !== null && $match['player2_id'] !== null;
    }
}
