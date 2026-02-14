<?php

declare(strict_types=1);

namespace DuelDesk\Services;

use DuelDesk\Repositories\MatchRepository;
use DuelDesk\Repositories\MatchRoundRepository;

final class MultiRoundMatchService
{
    /**
     * Compute totals from rounds and confirm the parent match if possible.
     *
     * @param array<string,mixed> $tournament
     * @param array<string,mixed> $match Base match row (from MatchRepository::findById)
     * @return array{ok:bool, total1:int, total2:int, winner_slot:int, needs_tiebreak:bool, rounds:int}
     */
    public function finalize(array $tournament, array $match): array
    {
        $ptype = (string)($tournament['participant_type'] ?? 'solo');
        $mode = (string)($tournament['team_match_mode'] ?? 'standard');
        if ($ptype !== 'team' || $mode !== 'multi_round') {
            throw new \RuntimeException('Multi-round mode not enabled.');
        }

        $matchId = (int)($match['id'] ?? 0);
        if ($matchId <= 0) {
            throw new \RuntimeException('Invalid match.');
        }

        $st = (string)($match['status'] ?? 'pending');
        if (in_array($st, ['confirmed', 'void'], true)) {
            throw new \RuntimeException('Match already closed.');
        }

        if (($match['team1_id'] ?? null) === null || ($match['team2_id'] ?? null) === null) {
            throw new \RuntimeException('Match incomplete (TBD).');
        }

        $rRepo = new MatchRoundRepository();
        $rounds = $rRepo->listForMatch($matchId);
        if ($rounds === []) {
            throw new \RuntimeException('No rounds yet.');
        }

        $total1 = 0;
        $total2 = 0;
        foreach ($rounds as $r) {
            $p1 = (int)($r['points1'] ?? 0);
            $p2 = (int)($r['points2'] ?? 0);
            $total1 += $p1;
            $total2 += $p2;
        }

        if ($total1 === $total2) {
            return [
                'ok' => false,
                'total1' => $total1,
                'total2' => $total2,
                'winner_slot' => 0,
                'needs_tiebreak' => true,
                'rounds' => count($rounds),
            ];
        }

        $winnerSlot = $total1 > $total2 ? 1 : 2;

        $svc = new MatchResultService();
        $res = $svc->confirmAndAdvance($tournament, $match, $total1, $total2, $winnerSlot, requirePickbanLocked: true);

        return [
            'ok' => (bool)($res['ok'] ?? true),
            'total1' => $total1,
            'total2' => $total2,
            'winner_slot' => $winnerSlot,
            'needs_tiebreak' => false,
            'rounds' => count($rounds),
        ];
    }
}

