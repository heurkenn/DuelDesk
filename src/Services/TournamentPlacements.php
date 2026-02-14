<?php

declare(strict_types=1);

namespace DuelDesk\Services;

use DuelDesk\Repositories\MatchRepository;
use DuelDesk\Repositories\TournamentPlayerRepository;
use DuelDesk\Repositories\TournamentTeamRepository;

/**
 * Compute tournament placements as rank ranges (to support ties like 3-4, 5-6, ...).
 *
 * Notes:
 * - Only uses CONFIRMED matches.
 * - For elim brackets, placements are inferred from the round a participant loses in.
 */
final class TournamentPlacements
{
    /**
     * @param 'single_elim'|'double_elim'|'round_robin' $format
     * @param 'solo'|'team' $participantType
     * @return array{
     *   n: int,
     *   entrants: array<int, string>,
     *   ranges: array<int, array{start:int,end:int}>
     * }
     */
    public static function compute(int $tournamentId, string $format, string $participantType): array
    {
        $participantType = in_array($participantType, ['solo', 'team'], true) ? $participantType : 'solo';
        $format = in_array($format, ['single_elim', 'double_elim', 'round_robin'], true) ? $format : 'single_elim';

        $entrants = self::loadEntrants($tournamentId, $participantType);
        $n = count($entrants);
        if ($n <= 0) {
            return ['n' => 0, 'entrants' => [], 'ranges' => []];
        }

        $mRepo = new MatchRepository();
        $matches = $participantType === 'team'
            ? $mRepo->listTeamForTournament($tournamentId)
            : $mRepo->listSoloForTournament($tournamentId);

        if ($format === 'round_robin') {
            return [
                'n' => $n,
                'entrants' => $entrants,
                'ranges' => self::roundRobinPlacements($entrants, $matches, $participantType),
            ];
        }

        if ($format === 'double_elim') {
            return [
                'n' => $n,
                'entrants' => $entrants,
                'ranges' => self::doubleElimPlacements($n, $entrants, $matches, $participantType),
            ];
        }

        return [
            'n' => $n,
            'entrants' => $entrants,
            'ranges' => self::singleElimPlacements($n, $entrants, $matches, $participantType),
        ];
    }

    /**
     * @param array<int,string> $entrants
     * @param list<array<string,mixed>> $matches
     * @param 'solo'|'team' $participantType
     * @return array<int, array{start:int,end:int}>
     */
    private static function roundRobinPlacements(array $entrants, array $matches, string $participantType): array
    {
        $total = 0;
        $confirmed = 0;
        foreach ($matches as $m) {
            if ((string)($m['bracket'] ?? '') !== 'round_robin') {
                continue;
            }
            $total++;
            if ((string)($m['status'] ?? 'pending') === 'confirmed') {
                $confirmed++;
            }
        }
        // Only score a RR tournament once all matches are confirmed (i.e. standings are final).
        if ($total <= 0 || $confirmed !== $total) {
            return [];
        }

        /** @var array<int, array{id:int,name:string,played:int,wins:int,losses:int,pf:int,pa:int}> $stats */
        $stats = [];
        foreach ($entrants as $id => $name) {
            $stats[(int)$id] = [
                'id' => (int)$id,
                'name' => (string)$name,
                'played' => 0,
                'wins' => 0,
                'losses' => 0,
                'pf' => 0,
                'pa' => 0,
            ];
        }

        foreach ($matches as $m) {
            if ((string)($m['bracket'] ?? '') !== 'round_robin') {
                continue;
            }
            if ((string)($m['status'] ?? 'pending') !== 'confirmed') {
                continue;
            }

            if ($participantType === 'team') {
                $a = $m['team1_id'] !== null ? (int)$m['team1_id'] : null;
                $b = $m['team2_id'] !== null ? (int)$m['team2_id'] : null;
                $win = $m['winner_team_id'] !== null ? (int)$m['winner_team_id'] : null;
            } else {
                $a = $m['player1_id'] !== null ? (int)$m['player1_id'] : null;
                $b = $m['player2_id'] !== null ? (int)$m['player2_id'] : null;
                $win = $m['winner_id'] !== null ? (int)$m['winner_id'] : null;
            }

            if ($a === null || $b === null || $win === null) {
                continue;
            }

            if (!isset($stats[$a]) || !isset($stats[$b])) {
                continue;
            }

            $s1 = (int)($m['score1'] ?? 0);
            $s2 = (int)($m['score2'] ?? 0);

            $stats[$a]['played']++;
            $stats[$b]['played']++;
            $stats[$a]['pf'] += $s1;
            $stats[$a]['pa'] += $s2;
            $stats[$b]['pf'] += $s2;
            $stats[$b]['pa'] += $s1;

            if ($win === $a) {
                $stats[$a]['wins']++;
                $stats[$b]['losses']++;
            } elseif ($win === $b) {
                $stats[$b]['wins']++;
                $stats[$a]['losses']++;
            }
        }

        $standings = array_values($stats);
        usort($standings, static function (array $x, array $y): int {
            if (($x['wins'] ?? 0) !== ($y['wins'] ?? 0)) {
                return (int)($y['wins'] ?? 0) <=> (int)($x['wins'] ?? 0);
            }
            if (($x['losses'] ?? 0) !== ($y['losses'] ?? 0)) {
                return (int)($x['losses'] ?? 0) <=> (int)($y['losses'] ?? 0);
            }
            $dx = (int)($x['pf'] ?? 0) - (int)($x['pa'] ?? 0);
            $dy = (int)($y['pf'] ?? 0) - (int)($y['pa'] ?? 0);
            if ($dx !== $dy) {
                return $dy <=> $dx;
            }
            return strcasecmp((string)($x['name'] ?? ''), (string)($y['name'] ?? ''));
        });

        $ranges = [];
        $i = 1;
        $total = count($standings);
        while ($i <= $total) {
            $row = $standings[$i - 1] ?? null;
            if (!is_array($row)) {
                break;
            }

            $wins = (int)($row['wins'] ?? 0);
            $losses = (int)($row['losses'] ?? 0);
            $diff = (int)($row['pf'] ?? 0) - (int)($row['pa'] ?? 0);

            $j = $i;
            while ($j <= $total) {
                $r2 = $standings[$j - 1] ?? null;
                if (!is_array($r2)) {
                    break;
                }
                $wins2 = (int)($r2['wins'] ?? 0);
                $losses2 = (int)($r2['losses'] ?? 0);
                $diff2 = (int)($r2['pf'] ?? 0) - (int)($r2['pa'] ?? 0);
                if ($wins2 !== $wins || $losses2 !== $losses || $diff2 !== $diff) {
                    break;
                }
                $j++;
            }

            $start = $i;
            $end = $j - 1;
            for ($k = $i; $k <= $end; $k++) {
                $rk = $standings[$k - 1] ?? null;
                $id = is_array($rk) ? (int)($rk['id'] ?? 0) : 0;
                if ($id > 0) {
                    $ranges[$id] = ['start' => $start, 'end' => $end];
                }
            }

            $i = $j;
        }

        return $ranges;
    }

    /**
     * @param array<int,string> $entrants
     * @param list<array<string,mixed>> $matches
     * @param 'solo'|'team' $participantType
     * @return array<int, array{start:int,end:int}>
     */
    private static function singleElimPlacements(int $n, array $entrants, array $matches, string $participantType): array
    {
        $maxRound = 0;
        foreach ($matches as $m) {
            if ((string)($m['bracket'] ?? '') !== 'winners') {
                continue;
            }
            $r = (int)($m['round'] ?? 0);
            if ($r > $maxRound) {
                $maxRound = $r;
            }
        }
        if ($maxRound <= 0) {
            return [];
        }

        $final = null;
        foreach ($matches as $m) {
            if ((string)($m['bracket'] ?? '') !== 'winners') {
                continue;
            }
            if ((int)($m['round'] ?? 0) !== $maxRound) {
                continue;
            }
            if ((int)($m['round_pos'] ?? 0) !== 1) {
                continue;
            }
            $final = $m;
            break;
        }

        $champion = null;
        $runnerUp = null;
        if (is_array($final) && (string)($final['status'] ?? 'pending') === 'confirmed') {
            if ($participantType === 'team') {
                $a = $final['team1_id'] !== null ? (int)$final['team1_id'] : null;
                $b = $final['team2_id'] !== null ? (int)$final['team2_id'] : null;
                $win = $final['winner_team_id'] !== null ? (int)$final['winner_team_id'] : null;
            } else {
                $a = $final['player1_id'] !== null ? (int)$final['player1_id'] : null;
                $b = $final['player2_id'] !== null ? (int)$final['player2_id'] : null;
                $win = $final['winner_id'] !== null ? (int)$final['winner_id'] : null;
            }

            if ($a !== null && $b !== null && $win !== null && isset($entrants[$a]) && isset($entrants[$b])) {
                $champion = $win;
                $runnerUp = ($win === $a) ? $b : $a;
            }
        }

        // No champ -> not finished enough for LAN scoring.
        if ($champion === null || $runnerUp === null) {
            return [];
        }

        /** @var array<int, list<int>> $losersByRound */
        $losersByRound = [];
        foreach ($matches as $m) {
            if ((string)($m['bracket'] ?? '') !== 'winners') {
                continue;
            }
            if ((string)($m['status'] ?? 'pending') !== 'confirmed') {
                continue;
            }
            $r = (int)($m['round'] ?? 0);
            if ($r <= 0 || $r >= $maxRound) {
                continue;
            }

            $loser = self::loserId($m, $participantType);
            if ($loser === null) {
                continue;
            }
            if (!isset($entrants[$loser])) {
                continue;
            }
            if ($loser === $champion || $loser === $runnerUp) {
                continue;
            }

            $losersByRound[$r] ??= [];
            $losersByRound[$r][] = $loser;
        }

        $ranges = [
            (int)$champion => ['start' => 1, 'end' => 1],
            (int)$runnerUp => ['start' => 2, 'end' => 2],
        ];

        // Bottom-up: earlier rounds correspond to worse placements.
        $next = $n;
        for ($r = 1; $r <= $maxRound - 1; $r++) {
            $losers = $losersByRound[$r] ?? [];
            $losers = array_values(array_unique(array_filter($losers, static fn ($v) => is_int($v) && $v > 0)));
            if ($losers === []) {
                continue;
            }

            $cnt = count($losers);
            $start = max(3, $next - $cnt + 1); // 1-2 reserved for champ/runner-up
            $end = $next;
            foreach ($losers as $id) {
                $ranges[$id] = ['start' => $start, 'end' => $end];
            }
            $next = $start - 1;
        }

        return $ranges;
    }

    /**
     * @param array<int,string> $entrants
     * @param list<array<string,mixed>> $matches
     * @param 'solo'|'team' $participantType
     * @return array<int, array{start:int,end:int}>
     */
    private static function doubleElimPlacements(int $n, array $entrants, array $matches, string $participantType): array
    {
        $gf1 = null;
        $gf2 = null;
        foreach ($matches as $m) {
            if ((string)($m['bracket'] ?? '') !== 'grand') {
                continue;
            }
            $r = (int)($m['round'] ?? 0);
            if ($r === 1) {
                $gf1 = $m;
            } elseif ($r === 2) {
                $gf2 = $m;
            }
        }

        $champion = null;
        $runnerUp = null;
        if (is_array($gf2) && (string)($gf2['status'] ?? 'pending') === 'confirmed') {
            $win = $participantType === 'team'
                ? ($gf2['winner_team_id'] !== null ? (int)$gf2['winner_team_id'] : null)
                : ($gf2['winner_id'] !== null ? (int)$gf2['winner_id'] : null);
            $loser = self::loserId($gf2, $participantType);
            if ($win !== null && $loser !== null && isset($entrants[$win]) && isset($entrants[$loser])) {
                $champion = $win;
                $runnerUp = $loser;
            }
        }

        if ($champion === null && is_array($gf1) && (string)($gf1['status'] ?? 'pending') === 'confirmed') {
            $win = $participantType === 'team'
                ? ($gf1['winner_team_id'] !== null ? (int)$gf1['winner_team_id'] : null)
                : ($gf1['winner_id'] !== null ? (int)$gf1['winner_id'] : null);
            $loser = self::loserId($gf1, $participantType);

            $gf2IsVoid = is_array($gf2) && (string)($gf2['status'] ?? '') === 'void';
            if ($win !== null && $loser !== null && isset($entrants[$win]) && isset($entrants[$loser]) && ($gf2 === null || $gf2IsVoid)) {
                $champion = $win;
                $runnerUp = $loser;
            }
        }

        if ($champion === null || $runnerUp === null) {
            return [];
        }

        /** @var array<int, list<int>> $losersByRound */
        $losersByRound = [];
        $maxLosersRound = 0;
        foreach ($matches as $m) {
            if ((string)($m['bracket'] ?? '') !== 'losers') {
                continue;
            }
            if ((string)($m['status'] ?? 'pending') !== 'confirmed') {
                continue;
            }
            $r = (int)($m['round'] ?? 0);
            if ($r <= 0) {
                continue;
            }
            $maxLosersRound = max($maxLosersRound, $r);

            $loser = self::loserId($m, $participantType);
            if ($loser === null) {
                continue;
            }
            if (!isset($entrants[$loser])) {
                continue;
            }
            if ($loser === $champion || $loser === $runnerUp) {
                continue;
            }

            $losersByRound[$r] ??= [];
            $losersByRound[$r][] = $loser;
        }

        $ranges = [
            (int)$champion => ['start' => 1, 'end' => 1],
            (int)$runnerUp => ['start' => 2, 'end' => 2],
        ];

        $next = $n;
        for ($r = 1; $r <= $maxLosersRound; $r++) {
            $losers = $losersByRound[$r] ?? [];
            $losers = array_values(array_unique(array_filter($losers, static fn ($v) => is_int($v) && $v > 0)));
            if ($losers === []) {
                continue;
            }

            $cnt = count($losers);
            $start = max(3, $next - $cnt + 1);
            $end = $next;
            foreach ($losers as $id) {
                $ranges[$id] = ['start' => $start, 'end' => $end];
            }
            $next = $start - 1;
        }

        return $ranges;
    }

    /**
     * @param list<array<string,mixed>> $matches
     * @param 'solo'|'team' $participantType
     */
    private static function loserId(array $match, string $participantType): ?int
    {
        if ($participantType === 'team') {
            $a = $match['team1_id'] !== null ? (int)$match['team1_id'] : null;
            $b = $match['team2_id'] !== null ? (int)$match['team2_id'] : null;
            $win = $match['winner_team_id'] !== null ? (int)$match['winner_team_id'] : null;
        } else {
            $a = $match['player1_id'] !== null ? (int)$match['player1_id'] : null;
            $b = $match['player2_id'] !== null ? (int)$match['player2_id'] : null;
            $win = $match['winner_id'] !== null ? (int)$match['winner_id'] : null;
        }

        if ($a === null || $b === null || $win === null) {
            return null;
        }
        if ($win === $a) {
            return $b;
        }
        if ($win === $b) {
            return $a;
        }
        return null;
    }

    /**
     * @param 'solo'|'team' $participantType
     * @return array<int,string> entrantId => name
     */
    private static function loadEntrants(int $tournamentId, string $participantType): array
    {
        if ($participantType === 'team') {
            $ttRepo = new TournamentTeamRepository();
            $rows = $ttRepo->listForTournament($tournamentId);
            $out = [];
            foreach ($rows as $r) {
                $id = (int)($r['team_id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }
                $out[$id] = (string)($r['name'] ?? ('#' . $id));
            }
            return $out;
        }

        $tpRepo = new TournamentPlayerRepository();
        $rows = $tpRepo->listForTournament($tournamentId);
        $out = [];
        foreach ($rows as $r) {
            $id = (int)($r['player_id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $out[$id] = (string)($r['handle'] ?? ('#' . $id));
        }
        return $out;
    }
}
