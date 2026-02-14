<?php

declare(strict_types=1);

namespace DuelDesk\Services;

use DuelDesk\Repositories\TeamMemberRepository;

final class LanScoring
{
    /**
     * @param array<string,mixed> $event
     * @param list<array<string,mixed>> $tournaments
     * @return array{
     *   computedTournaments:int,
     *   totalTournaments:int,
     *   leaderboard:list<array{
     *     key:string,
     *     name:string,
     *     points:int,
     *     members:list<string>
     *   }>
     * }
     */
    public static function compute(array $event, array $tournaments): array
    {
        $eventType = (string)($event['participant_type'] ?? 'solo');
        if (!in_array($eventType, ['solo', 'team'], true)) {
            $eventType = 'solo';
        }

        $computedTournaments = 0;
        $totalTournaments = count($tournaments);

        /** @var array<string, array{key:string,name:string,points:int,members:list<string>}> $acc */
        $acc = [];

        foreach ($tournaments as $t) {
            $tid = (int)($t['id'] ?? 0);
            if ($tid <= 0) {
                continue;
            }

            $format = (string)($t['format'] ?? 'single_elim');
            $participantType = (string)($t['participant_type'] ?? 'solo');
            if (!in_array($participantType, ['solo', 'team'], true)) {
                $participantType = 'solo';
            }

            // Skip mismatched tournaments (LAN is either solo or team).
            if ($participantType !== $eventType) {
                continue;
            }

            $placements = TournamentPlacements::compute($tid, $format, $participantType);
            $n = (int)($placements['n'] ?? 0);
            $ranges = $placements['ranges'] ?? [];
            if ($n < 2 || !is_array($ranges) || $ranges === []) {
                continue;
            }

            $computedTournaments++;

            $maxPoints = self::maxPointsForEntrants($n);

            // Team LAN: need roster-derived stable keys.
            $membersByTeam = [];
            if ($eventType === 'team') {
                $teamIds = array_values(array_map('intval', array_keys($ranges)));
                $tmRepo = new TeamMemberRepository();
                $membersByTeam = $tmRepo->listMembersForTeams($teamIds);
            }

            foreach ($ranges as $entrantIdRaw => $range) {
                $entrantId = is_int($entrantIdRaw) ? $entrantIdRaw : (int)$entrantIdRaw;
                if ($entrantId <= 0) {
                    continue;
                }

                $start = (int)($range['start'] ?? 0);
                $end = (int)($range['end'] ?? 0);
                if ($start <= 0 || $end <= 0 || $start > $end) {
                    continue;
                }
                if ($start > $n) {
                    continue;
                }
                if ($end > $n) {
                    $end = $n;
                }

                $avg = self::avgPointsForRange($start, $end, $n, $maxPoints);
                if ($avg <= 0) {
                    continue;
                }

                if ($eventType === 'team') {
                    $members = $membersByTeam[$entrantId] ?? [];
                    $uids = [];
                    $names = [];
                    foreach ($members as $m) {
                        $uid = (int)($m['user_id'] ?? 0);
                        if ($uid > 0) {
                            $uids[] = $uid;
                        }
                        $un = trim((string)($m['username'] ?? ''));
                        if ($un !== '') {
                            $names[] = $un;
                        }
                    }
                    sort($uids);
                    $key = 'team:' . sha1(implode(',', $uids));
                    $name = trim((string)($placements['entrants'][$entrantId] ?? ('Equipe #' . $entrantId)));
                    if ($name === '') {
                        $name = 'Equipe #' . $entrantId;
                    }

                    if (!isset($acc[$key])) {
                        $acc[$key] = ['key' => $key, 'name' => $name, 'points' => 0, 'members' => $names];
                    }
                    $acc[$key]['points'] += $avg;
                } else {
                    $key = 'solo:' . $entrantId;
                    $name = trim((string)($placements['entrants'][$entrantId] ?? ('#' . $entrantId)));
                    if ($name === '') {
                        $name = '#' . $entrantId;
                    }
                    if (!isset($acc[$key])) {
                        $acc[$key] = ['key' => $key, 'name' => $name, 'points' => 0, 'members' => []];
                    }
                    $acc[$key]['points'] += $avg;
                }
            }
        }

        $leaderboard = array_values($acc);
        usort($leaderboard, static function (array $a, array $b): int {
            if (($a['points'] ?? 0) !== ($b['points'] ?? 0)) {
                return (int)($b['points'] ?? 0) <=> (int)($a['points'] ?? 0);
            }
            return strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
        });

        return [
            'computedTournaments' => $computedTournaments,
            'totalTournaments' => $totalTournaments,
            'leaderboard' => $leaderboard,
        ];
    }

    private static function maxPointsForEntrants(int $n): int
    {
        if ($n < 2) {
            return 0;
        }
        $rounds = (int)ceil(log((float)$n, 2.0));
        $rounds = max(1, $rounds);
        return 100 * $rounds;
    }

    private static function avgPointsForRange(int $start, int $end, int $n, int $maxPoints): int
    {
        $count = ($end - $start) + 1;
        if ($count <= 0) {
            return 0;
        }
        $sum = 0;
        for ($p = $start; $p <= $end; $p++) {
            $sum += self::pointsForPlace($p, $n, $maxPoints);
        }
        return (int)round($sum / $count);
    }

    private static function pointsForPlace(int $place, int $n, int $maxPoints): int
    {
        if ($n < 2 || $place <= 0 || $place > $n || $maxPoints <= 0) {
            return 0;
        }

        if ($place === $n) {
            return 0;
        }

        $x = ($n - $place) / ($n - 1); // 1st=1.0, last=0.0
        $gamma = 1.3; // emphasize top placements a bit
        $v = (int)round($maxPoints * ($x ** $gamma));
        return max(0, min($maxPoints, $v));
    }
}

