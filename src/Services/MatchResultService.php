<?php

declare(strict_types=1);

namespace DuelDesk\Services;

use DuelDesk\Database\Db;
use DuelDesk\Repositories\MatchRepository;
use DuelDesk\Repositories\PickBanRepository;
use DuelDesk\Services\PickBanEngine;

final class MatchResultService
{
    public function __construct(
        private readonly MatchRepository $mRepo = new MatchRepository(),
        private readonly BracketAdvancer $advancer = new BracketAdvancer(),
        private readonly PickBanRepository $pbRepo = new PickBanRepository(),
    ) {
    }

    /**
     * @param array<string,mixed> $tournament
     * @param array<string,mixed> $match
     * @return array{bracket:string,round:int,round_pos:int,winner_id:int,loser_id:int}
     */
    public function confirmAndAdvance(array $tournament, array $match, int $score1, int $score2, int $winnerSlot, bool $requirePickbanLocked = false): array
    {
        if (($match['status'] ?? 'pending') === 'confirmed') {
            throw new \RuntimeException('Match deja confirme.');
        }

        $participantType = (string)($tournament['participant_type'] ?? 'solo');
        if (!in_array($participantType, ['solo', 'team'], true)) {
            $participantType = 'solo';
        }

        $format = (string)($tournament['format'] ?? 'single_elim');
        if (!in_array($format, ['single_elim', 'double_elim', 'round_robin'], true)) {
            $format = 'single_elim';
        }

        if ($score1 < 0 || $score2 < 0 || $score1 > 99 || $score2 > 99) {
            throw new \RuntimeException('Scores invalides (0-99).');
        }
        if (!in_array($winnerSlot, [1, 2], true)) {
            throw new \RuntimeException('Winner invalide.');
        }

        // Best-of validation (soft): if scores look like a series (<= BO), enforce "first to X wins".
        $bestOf = (int)($match['best_of'] ?? 0);
        if (!in_array($bestOf, [1, 3, 5, 7, 9], true)) {
            $bestOf = (int)($tournament['best_of_default'] ?? 3);
        }
        if (!in_array($bestOf, [1, 3, 5, 7, 9], true)) {
            $bestOf = 3;
        }

        $winsToTake = intdiv($bestOf, 2) + 1;
        $looksLikeSeries = ($score1 <= $bestOf) && ($score2 <= $bestOf);

        // Always require winner to have a strictly higher score.
        if ($winnerSlot === 1 && $score1 <= $score2) {
            throw new \RuntimeException('Score incoherent: winner=A mais scoreA <= scoreB.');
        }
        if ($winnerSlot === 2 && $score2 <= $score1) {
            throw new \RuntimeException('Score incoherent: winner=B mais scoreB <= scoreA.');
        }

        // If it looks like a BO series, require majority wins.
        if ($looksLikeSeries) {
            if ($winnerSlot === 1 && $score1 !== $winsToTake) {
                throw new \RuntimeException("Score BO{$bestOf} incoherent: il faut {$winsToTake} victoire(s) pour gagner.");
            }
            if ($winnerSlot === 2 && $score2 !== $winsToTake) {
                throw new \RuntimeException("Score BO{$bestOf} incoherent: il faut {$winsToTake} victoire(s) pour gagner.");
            }
            if ($score1 >= $winsToTake && $score2 >= $winsToTake) {
                throw new \RuntimeException("Score BO{$bestOf} incoherent: les deux cotes ne peuvent pas atteindre {$winsToTake}.");
            }
        }

        $bracket = (string)($match['bracket'] ?? 'winners');
        $round = (int)($match['round'] ?? 0);
        $roundPos = (int)($match['round_pos'] ?? 0);
        if ($round <= 0 || $roundPos <= 0) {
            throw new \RuntimeException('Match invalide.');
        }

        if ($requirePickbanLocked) {
            $this->assertPickbanLockedIfRequired($tournament, $match, $bestOf);
        }

        $a = null;
        $b = null;
        if ($participantType === 'team') {
            $a = $match['team1_id'] !== null ? (int)$match['team1_id'] : null;
            $b = $match['team2_id'] !== null ? (int)$match['team2_id'] : null;
        } else {
            $a = $match['player1_id'] !== null ? (int)$match['player1_id'] : null;
            $b = $match['player2_id'] !== null ? (int)$match['player2_id'] : null;
        }
        if ($a === null || $b === null) {
            throw new \RuntimeException('Match incomplet (TBD).');
        }

        $winnerId = $winnerSlot === 1 ? $a : $b;
        $loserId = $winnerSlot === 1 ? $b : $a;

        $tournamentId = (int)($tournament['id'] ?? 0);
        $matchId = (int)($match['id'] ?? 0);
        if ($tournamentId <= 0 || $matchId <= 0) {
            throw new \RuntimeException('Match invalide.');
        }

        $pdo = Db::pdo();
        $pdo->beginTransaction();

        try {
            if ($participantType === 'team') {
                $this->mRepo->confirmTeamResult($matchId, $score1, $score2, $winnerId);
                if ($format === 'double_elim') {
                    $this->advancer->advanceTeamDoubleElim($tournamentId, $bracket, $round, $roundPos, $winnerId, $loserId);
                } elseif ($format === 'single_elim') {
                    $this->advancer->advanceTeamWinner($tournamentId, $bracket, $round, $roundPos, $winnerId);
                }
            } else {
                $this->mRepo->confirmSoloResult($matchId, $score1, $score2, $winnerId);
                if ($format === 'double_elim') {
                    $this->advancer->advanceSoloDoubleElim($tournamentId, $bracket, $round, $roundPos, $winnerId, $loserId);
                } elseif ($format === 'single_elim') {
                    $this->advancer->advanceSoloWinner($tournamentId, $bracket, $round, $roundPos, $winnerId);
                }
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return [
            'bracket' => $bracket,
            'round' => $round,
            'round_pos' => $roundPos,
            'winner_id' => $winnerId,
            'loser_id' => $loserId,
        ];
    }

    /** @param array<string,mixed> $tournament @param array<string,mixed> $match */
    private function assertPickbanLockedIfRequired(array $tournament, array $match, int $bestOf): void
    {
        $rulesetJson = is_string($tournament['ruleset_json'] ?? null) ? trim((string)$tournament['ruleset_json']) : '';
        if ($rulesetJson === '') {
            return;
        }

        $matchId = (int)($match['id'] ?? 0);
        if ($matchId <= 0) {
            return;
        }

        $status = (string)($match['status'] ?? 'pending');
        if (in_array($status, ['confirmed', 'void'], true)) {
            return;
        }

        $participantType = (string)($tournament['participant_type'] ?? 'solo');
        $complete = $participantType === 'team'
            ? (($match['team1_id'] ?? null) !== null && ($match['team2_id'] ?? null) !== null)
            : (($match['player1_id'] ?? null) !== null && ($match['player2_id'] ?? null) !== null);
        if (!$complete) {
            return;
        }

        $parsed = PickBanEngine::parseTournamentRuleset($rulesetJson);
        $ruleset = $parsed['ruleset'] ?? null;
        if (!is_array($ruleset)) {
            return;
        }

        $cfg = PickBanEngine::buildMatchConfigSnapshot($ruleset, $bestOf);
        if ($cfg === null) {
            return; // not required for this BO
        }

        $state = $this->pbRepo->findState($matchId);
        $locked = is_array($state) && ((string)($state['status'] ?? 'running')) === 'locked';
        if (!$locked) {
            throw new \RuntimeException('Pick/Ban requis: il doit etre verrouille avant le report.');
        }
    }
}
