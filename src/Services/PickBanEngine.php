<?php

declare(strict_types=1);

namespace DuelDesk\Services;

final class PickBanEngine
{
    /**
     * Tournament ruleset JSON format (stored on tournaments):
     * {
     *   "kind": "map_veto",
     *   "pool": [{"key":"dust2","name":"Dust II"}, ...],
     *   "steps_by_best_of": {
     *     "1": [{"action":"ban","actor":"starter"}, ... , {"action":"decider","actor":"any"}],
     *     "3": [{"action":"ban","actor":"starter"}, ... , {"action":"decider","actor":"any"}]
     *   }
     * }
     *
     * Match config snapshot format (stored on match_pickbans):
     * {
     *   "kind": "map_veto",
     *   "version": 1,
     *   "best_of": 3,
     *   "pool": [...],
     *   "steps": [{"action":"ban","actor":"starter"}, ... , {"action":"decider","actor":"any"}]
     * }
     */

    /** @return array<string, mixed>|null */
    public static function decodeJson(?string $json): ?array
    {
        $json = is_string($json) ? trim($json) : '';
        if ($json === '') {
            return null;
        }

        $data = json_decode($json, true);
        return is_array($data) ? $data : null;
    }

    /**
     * @return array{ruleset:array<string, mixed>|null,error:?string}
     */
    public static function parseTournamentRuleset(string $json): array
    {
        $decoded = self::decodeJson($json);
        if ($decoded === null) {
            return ['ruleset' => null, 'error' => 'JSON invalide.'];
        }

        $norm = self::normalizeTournamentRuleset($decoded);
        if ($norm['error'] !== null) {
            return ['ruleset' => null, 'error' => $norm['error']];
        }

        return ['ruleset' => $norm['ruleset'], 'error' => null];
    }

    /**
     * @param array<string, mixed> $ruleset
     * @return array{ruleset:array<string, mixed>|null,error:?string}
     */
    public static function normalizeTournamentRuleset(array $ruleset): array
    {
        $poolRaw = $ruleset['pool'] ?? null;
        if (!is_array($poolRaw) || $poolRaw === []) {
            return ['ruleset' => null, 'error' => 'Ruleset: pool manquant.'];
        }

        $pool = [];
        $seen = [];
        foreach ($poolRaw as $m) {
            if (!is_array($m)) {
                continue;
            }
            $key = is_string($m['key'] ?? null) ? trim((string)$m['key']) : '';
            $name = is_string($m['name'] ?? null) ? trim((string)$m['name']) : '';
            if ($key === '' || $name === '') {
                continue;
            }

            $key = strtolower($key);
            if (!preg_match('/^[a-z0-9][a-z0-9-]{0,63}$/', $key)) {
                return ['ruleset' => null, 'error' => "Ruleset: map key invalide: {$key}"];
            }
            if (isset($seen[$key])) {
                return ['ruleset' => null, 'error' => "Ruleset: map dupliquee: {$key}"];
            }
            $seen[$key] = true;

            $pool[] = ['key' => $key, 'name' => $name];
        }

        if (count($pool) < 3) {
            return ['ruleset' => null, 'error' => 'Ruleset: pool trop petit (min 3).'];
        }

        $stepsByBo = null;
        if (isset($ruleset['steps_by_best_of']) && is_array($ruleset['steps_by_best_of'])) {
            $stepsByBo = $ruleset['steps_by_best_of'];
        } elseif (isset($ruleset['sequences']) && is_array($ruleset['sequences'])) {
            $stepsByBo = $ruleset['sequences'];
        } elseif (isset($ruleset['sequence']) && is_array($ruleset['sequence'])) {
            // Allow a single sequence as a default (treated as BO3).
            $stepsByBo = ['3' => $ruleset['sequence']];
        }

        if (!is_array($stepsByBo) || $stepsByBo === []) {
            return ['ruleset' => null, 'error' => 'Ruleset: steps_by_best_of manquant.'];
        }

        $allowedSteps = ['ban' => true, 'pick' => true, 'decider' => true];
        $allowedActors = ['starter' => true, 'other' => true, 'alternate' => true, 'any' => true];
        $normalizedStepsByBo = [];
        foreach ($stepsByBo as $k => $stepsRaw) {
            $bo = is_int($k) || is_string($k) ? (int)$k : 0;
            if ($bo <= 0) {
                continue;
            }
            if (!is_array($stepsRaw) || $stepsRaw === []) {
                continue;
            }

            $steps = [];
            foreach ($stepsRaw as $s) {
                $action = '';
                $actor = '';

                if (is_string($s)) {
                    $action = strtolower(trim($s));
                    $actor = $action === 'decider' ? 'any' : 'alternate';
                } elseif (is_array($s)) {
                    $rawAction = $s['action'] ?? ($s['step'] ?? null);
                    $rawActor = $s['actor'] ?? ($s['by'] ?? null);
                    $action = is_string($rawAction) ? strtolower(trim((string)$rawAction)) : '';
                    $actor = is_string($rawActor) ? strtolower(trim((string)$rawActor)) : '';
                    if ($action === 'decider') {
                        $actor = 'any';
                    } elseif ($actor === '') {
                        $actor = 'alternate';
                    }
                } else {
                    continue;
                }

                if ($action === '' || !isset($allowedSteps[$action])) {
                    return ['ruleset' => null, 'error' => "Ruleset: step invalide pour BO{$bo}: {$action}"];
                }
                if ($actor === '' || !isset($allowedActors[$actor])) {
                    return ['ruleset' => null, 'error' => "Ruleset: actor invalide pour BO{$bo}: {$actor}"];
                }

                // Decider is automatic: only "any" actor makes sense.
                if ($action === 'decider') {
                    $actor = 'any';
                } elseif (!in_array($actor, ['starter', 'other', 'alternate'], true)) {
                    return ['ruleset' => null, 'error' => "Ruleset: actor invalide pour BO{$bo}: {$actor}"];
                }

                $steps[] = ['action' => $action, 'actor' => $actor];
            }

            if ($steps === []) {
                continue;
            }

            $normalizedStepsByBo[(string)$bo] = $steps;
        }

        if ($normalizedStepsByBo === []) {
            return ['ruleset' => null, 'error' => 'Ruleset: aucune sequence valide.'];
        }

        // Validate each BO sequence against the pool size so the decider is deterministic.
        $poolSize = count($pool);
        foreach ($normalizedStepsByBo as $boStr => $steps) {
            $bo = (int)$boStr;
            $deciderCount = 0;
            $banCount = 0;
            $pickCount = 0;
            foreach ($steps as $i => $s) {
                if (!is_array($s)) {
                    continue;
                }
                $act = (string)($s['action'] ?? '');
                if ($act === 'decider') {
                    $deciderCount++;
                    if ($i !== (count($steps) - 1)) {
                        return ['ruleset' => null, 'error' => "Ruleset: BO{$bo} decider doit etre en dernier."];
                    }
                } elseif ($act === 'ban') {
                    $banCount++;
                } elseif ($act === 'pick') {
                    $pickCount++;
                }
            }

            if ($deciderCount !== 1) {
                return ['ruleset' => null, 'error' => "Ruleset: BO{$bo} doit contenir exactement 1 decider."];
            }

            $mapsToPlay = $pickCount + 1;
            if ($mapsToPlay !== $bo) {
                return ['ruleset' => null, 'error' => "Ruleset: BO{$bo} incoherent (maps jouees={$mapsToPlay})."];
            }

            // Ensure bans+picks leaves exactly 1 map for the decider.
            if (($banCount + $pickCount) !== ($poolSize - 1)) {
                return ['ruleset' => null, 'error' => "Ruleset: BO{$bo} incoherent (ban+pick doit valoir " . ($poolSize - 1) . ")."];
            }
        }

        $kind = is_string($ruleset['kind'] ?? null) ? trim((string)$ruleset['kind']) : 'map_veto';
        if ($kind === '') {
            $kind = 'map_veto';
        }

        return [
            'ruleset' => [
                'kind' => $kind,
                'pool' => $pool,
                'steps_by_best_of' => $normalizedStepsByBo,
            ],
            'error' => null,
        ];
    }

    /**
     * @param array<string, mixed> $tournamentRuleset Normalized ruleset (see normalizeTournamentRuleset()).
     * @return array<string, mixed>|null Match config snapshot.
     */
    public static function buildMatchConfigSnapshot(array $tournamentRuleset, int $bestOf): ?array
    {
        $bestOf = (int)$bestOf;
        if ($bestOf <= 0) {
            return null;
        }

        $pool = $tournamentRuleset['pool'] ?? null;
        $stepsByBo = $tournamentRuleset['steps_by_best_of'] ?? null;
        if (!is_array($pool) || !is_array($stepsByBo)) {
            return null;
        }

        $steps = $stepsByBo[(string)$bestOf] ?? null;
        if (!is_array($steps) || $steps === []) {
            return null;
        }

        $normSteps = [];
        foreach ($steps as $s) {
            if (is_string($s)) {
                $action = strtolower(trim($s));
                $actor = $action === 'decider' ? 'any' : 'alternate';
                $normSteps[] = ['action' => $action, 'actor' => $actor];
                continue;
            }
            if (!is_array($s)) {
                continue;
            }

            $rawAction = $s['action'] ?? ($s['step'] ?? null);
            $rawActor = $s['actor'] ?? ($s['by'] ?? null);
            $action = is_string($rawAction) ? strtolower(trim((string)$rawAction)) : '';
            if ($action === '') {
                continue;
            }
            $actor = is_string($rawActor) ? strtolower(trim((string)$rawActor)) : '';
            if ($action === 'decider') {
                $actor = 'any';
            } elseif ($actor === '') {
                $actor = 'alternate';
            } elseif (!in_array($actor, ['starter', 'other', 'alternate'], true)) {
                $actor = 'alternate';
            }

            $normSteps[] = ['action' => $action, 'actor' => $actor];
        }

        if ($normSteps === []) {
            return null;
        }

        return [
            'kind' => 'map_veto',
            'version' => 1,
            'best_of' => $bestOf,
            'pool' => $pool,
            'steps' => array_values($normSteps),
        ];
    }

    /**
     * @param array<string, mixed> $config Match config snapshot.
     */
    public static function encodeMatchConfig(array $config): string
    {
        $json = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || $json === '') {
            throw new \RuntimeException('Pick/Ban config encode failed');
        }
        return $json;
    }

    public static function otherSlot(int $slot): int
    {
        return $slot === 1 ? 2 : 1;
    }

    /**
     * @param array<string, mixed> $config Match config snapshot.
     * @param list<array<string, mixed>> $actions DB actions ordered by step_index ASC.
     * @param list<array<string, mixed>> $sides DB sides (one row per played map).
     * @return array<string, mixed> Computed state for UI and validation.
     */
    public static function compute(array $config, ?int $firstTurnSlot, array $actions, array $sides = []): array
    {
        $steps = $config['steps'] ?? null;
        $pool = $config['pool'] ?? null;
        if (!is_array($steps) || !is_array($pool)) {
            return ['ok' => false, 'error' => 'Config pick/ban invalide.'];
        }

        $stepAction = static function ($step): string {
            if (is_string($step)) {
                return strtolower(trim($step));
            }
            if (is_array($step)) {
                $raw = $step['action'] ?? ($step['step'] ?? null);
                return is_string($raw) ? strtolower(trim((string)$raw)) : '';
            }
            return '';
        };

        $stepActor = static function ($step): string {
            if (is_array($step)) {
                $raw = $step['actor'] ?? ($step['by'] ?? null);
                return is_string($raw) ? strtolower(trim((string)$raw)) : '';
            }
            return '';
        };

        $poolMap = [];
        foreach ($pool as $m) {
            if (!is_array($m)) {
                continue;
            }
            $k = is_string($m['key'] ?? null) ? (string)$m['key'] : '';
            $n = is_string($m['name'] ?? null) ? (string)$m['name'] : '';
            if ($k !== '') {
                $poolMap[$k] = $n !== '' ? $n : $k;
            }
        }

        if ($poolMap === []) {
            return ['ok' => false, 'error' => 'Pool vide.'];
        }

        $stepCount = count($steps);
        $nextIndex = 0;
        $used = [];

        foreach ($actions as $a) {
            $idx = (int)($a['step_index'] ?? -1);
            if ($idx !== $nextIndex) {
                return ['ok' => false, 'error' => 'Etat pick/ban invalide (steps non consecutifs).'];
            }
            $nextIndex++;

            $key = is_string($a['map_key'] ?? null) ? (string)$a['map_key'] : '';
            if ($key !== '') {
                $used[$key] = true;
            }
        }

        $complete = $nextIndex >= $stepCount;

        $available = [];
        foreach ($poolMap as $k => $n) {
            if (!isset($used[$k])) {
                $available[] = ['key' => $k, 'name' => $n];
            }
        }

        $sideByMap = [];
        foreach ($sides as $s) {
            if (!is_array($s)) {
                continue;
            }
            $k = is_string($s['map_key'] ?? null) ? strtolower(trim((string)$s['map_key'])) : '';
            $side = is_string($s['side_for_slot1'] ?? null) ? strtolower(trim((string)$s['side_for_slot1'])) : '';
            if ($k === '' || $side === '') {
                continue;
            }
            $sideByMap[$k] = $side;
        }

        $currentStep = !$complete ? $stepAction($steps[$nextIndex] ?? null) : null;
        if ($currentStep !== null) {
            $currentStep = strtolower(trim((string)$currentStep));
        }

        // Side pick (attack/defense) must happen after each picked map (and decider).
        // This is not part of the "steps" sequence, so we gate the workflow here.
        $pendingSide = null;
        foreach ($actions as $a) {
            if (!is_array($a)) {
                continue;
            }
            $act = is_string($a['action'] ?? null) ? strtolower(trim((string)$a['action'])) : '';
            if (!in_array($act, ['pick', 'decider'], true)) {
                continue;
            }

            $k = is_string($a['map_key'] ?? null) ? strtolower(trim((string)$a['map_key'])) : '';
            if ($k === '') {
                continue;
            }
            if (isset($sideByMap[$k])) {
                continue;
            }

            $mapName = is_string($a['map_name'] ?? null) ? trim((string)$a['map_name']) : '';
            if ($mapName === '') {
                $mapName = $poolMap[$k] ?? $k;
            }

            if ($act === 'pick') {
                $slot = (int)($a['slot'] ?? 0);
                if ($slot !== 1 && $slot !== 2) {
                    return ['ok' => false, 'error' => 'Pick/Ban invalide: slot pick manquant.'];
                }
                $pendingSide = [
                    'mode' => 'choice',
                    'map_key' => $k,
                    'map_name' => $mapName,
                    'next_slot' => self::otherSlot($slot),
                ];
            } else {
                $turn = ($firstTurnSlot === 1 || $firstTurnSlot === 2) ? self::otherSlot((int)$firstTurnSlot) : null;
                $pendingSide = [
                    'mode' => 'choice',
                    'map_key' => $k,
                    'map_name' => $mapName,
                    'next_slot' => $turn,
                ];
            }
            break;
        }

        if (is_array($pendingSide)) {
            return [
                'ok' => true,
                'error' => null,
                'needs_coin_toss' => ($firstTurnSlot !== 1 && $firstTurnSlot !== 2),
                'steps' => $steps,
                'next_index' => $nextIndex,
                'next_step' => 'side',
                'next_slot' => $pendingSide['next_slot'],
                'available' => $available,
                'decider_key' => null,
                'side_mode' => $pendingSide['mode'],
                'side_map_key' => $pendingSide['map_key'],
                'side_map_name' => $pendingSide['map_name'],
            ];
        }

        $nextSlot = null;
        if (!$complete && $currentStep !== null && in_array($currentStep, ['ban', 'pick'], true)) {
            if ($firstTurnSlot !== 1 && $firstTurnSlot !== 2) {
                return [
                    'ok' => true,
                    'error' => null,
                    'needs_coin_toss' => true,
                    'steps' => $steps,
                    'next_index' => $nextIndex,
                    'next_step' => 'coin_toss',
                    'next_slot' => null,
                    'available' => $available,
                ];
            }

            $actor = $stepActor($steps[$nextIndex] ?? null);
            if ($actor === '' || $actor === 'alternate') {
                $slot = (int)$firstTurnSlot;
                for ($i = 0; $i < $nextIndex; $i++) {
                    $a = $stepAction($steps[$i] ?? null);
                    if ($a === 'ban' || $a === 'pick') {
                        $slot = self::otherSlot($slot);
                    }
                }
                $nextSlot = $slot;
            } elseif ($actor === 'starter') {
                $nextSlot = (int)$firstTurnSlot;
            } elseif ($actor === 'other') {
                $nextSlot = self::otherSlot((int)$firstTurnSlot);
            } else {
                return ['ok' => false, 'error' => 'Config pick/ban invalide (actor).'];
            }
        }

        $deciderKey = null;
        if (!$complete && $currentStep === 'decider') {
            if (count($available) === 1) {
                $deciderKey = (string)$available[0]['key'];
            } else {
                return ['ok' => false, 'error' => 'Decider impossible: nombre de maps restantes invalide.'];
            }
        }

        return [
            'ok' => true,
            'error' => null,
            'needs_coin_toss' => ($firstTurnSlot !== 1 && $firstTurnSlot !== 2),
            'steps' => $steps,
            'next_index' => $nextIndex,
            'next_step' => $complete ? null : $currentStep,
            'next_slot' => $nextSlot,
            'available' => $available,
            'decider_key' => $deciderKey,
        ];
    }

    /** @return array<string, mixed> */
    public static function template(string $id): array
    {
        $id = strtolower(trim($id));

        $defaultStepsByBo = [
            // Default: simple alternate sequence (A/B/A/B...).
            '1' => [
                ['action' => 'ban', 'actor' => 'alternate'],
                ['action' => 'ban', 'actor' => 'alternate'],
                ['action' => 'ban', 'actor' => 'alternate'],
                ['action' => 'ban', 'actor' => 'alternate'],
                ['action' => 'ban', 'actor' => 'alternate'],
                ['action' => 'ban', 'actor' => 'alternate'],
                ['action' => 'decider', 'actor' => 'any'],
            ],
            '3' => [
                ['action' => 'ban', 'actor' => 'alternate'],
                ['action' => 'ban', 'actor' => 'alternate'],
                ['action' => 'pick', 'actor' => 'alternate'],
                ['action' => 'pick', 'actor' => 'alternate'],
                ['action' => 'ban', 'actor' => 'alternate'],
                ['action' => 'ban', 'actor' => 'alternate'],
                ['action' => 'decider', 'actor' => 'any'],
            ],
            '5' => [
                ['action' => 'ban', 'actor' => 'alternate'],
                ['action' => 'ban', 'actor' => 'alternate'],
                ['action' => 'pick', 'actor' => 'alternate'],
                ['action' => 'pick', 'actor' => 'alternate'],
                ['action' => 'pick', 'actor' => 'alternate'],
                ['action' => 'pick', 'actor' => 'alternate'],
                ['action' => 'decider', 'actor' => 'any'],
            ],
        ];

        if ($id === 'valorant') {
            return [
                'kind' => 'map_veto',
                'pool' => [
                    ['key' => 'ascent', 'name' => 'Ascent'],
                    ['key' => 'bind', 'name' => 'Bind'],
                    ['key' => 'haven', 'name' => 'Haven'],
                    ['key' => 'split', 'name' => 'Split'],
                    ['key' => 'lotus', 'name' => 'Lotus'],
                    ['key' => 'sunset', 'name' => 'Sunset'],
                    ['key' => 'icebox', 'name' => 'Icebox'],
                ],
                'steps_by_best_of' => $defaultStepsByBo,
            ];
        }

        if ($id === 'cs2') {
            // CS2 "Major-style" pick/ban sequence (maps only).
            // Note: side choice is handled separately (after each pick + decider).
            $stepsByBo = [
                // BO1:
                // Team A removes 2, Team B removes 3, Team A removes 1, decider is last map.
                '1' => [
                    ['action' => 'ban', 'actor' => 'starter'],
                    ['action' => 'ban', 'actor' => 'starter'],
                    ['action' => 'ban', 'actor' => 'other'],
                    ['action' => 'ban', 'actor' => 'other'],
                    ['action' => 'ban', 'actor' => 'other'],
                    ['action' => 'ban', 'actor' => 'starter'],
                    ['action' => 'decider', 'actor' => 'any'],
                ],
                // BO3:
                // A ban, B ban, A pick, B pick, B ban, A ban, decider.
                '3' => [
                    ['action' => 'ban', 'actor' => 'starter'],
                    ['action' => 'ban', 'actor' => 'other'],
                    ['action' => 'pick', 'actor' => 'starter'],
                    ['action' => 'pick', 'actor' => 'other'],
                    ['action' => 'ban', 'actor' => 'other'],
                    ['action' => 'ban', 'actor' => 'starter'],
                    ['action' => 'decider', 'actor' => 'any'],
                ],
                // BO5:
                // A ban, B ban, A pick, B pick, A pick, B pick, decider.
                '5' => [
                    ['action' => 'ban', 'actor' => 'starter'],
                    ['action' => 'ban', 'actor' => 'other'],
                    ['action' => 'pick', 'actor' => 'starter'],
                    ['action' => 'pick', 'actor' => 'other'],
                    ['action' => 'pick', 'actor' => 'starter'],
                    ['action' => 'pick', 'actor' => 'other'],
                    ['action' => 'decider', 'actor' => 'any'],
                ],
            ];

            return [
                'kind' => 'map_veto',
                'pool' => [
                    ['key' => 'dust2', 'name' => 'Dust II'],
                    ['key' => 'ancient', 'name' => 'Ancient'],
                    ['key' => 'anubis', 'name' => 'Anubis'],
                    ['key' => 'inferno', 'name' => 'Inferno'],
                    ['key' => 'mirage', 'name' => 'Mirage'],
                    ['key' => 'nuke', 'name' => 'Nuke'],
                    ['key' => 'train', 'name' => 'Train'],
                ],
                'steps_by_best_of' => $stepsByBo,
            ];
        }

        // Default game template: CS2 pool + alternate steps.
        return [
            'kind' => 'map_veto',
            'pool' => [
                ['key' => 'dust2', 'name' => 'Dust II'],
                ['key' => 'ancient', 'name' => 'Ancient'],
                ['key' => 'anubis', 'name' => 'Anubis'],
                ['key' => 'inferno', 'name' => 'Inferno'],
                ['key' => 'mirage', 'name' => 'Mirage'],
                ['key' => 'nuke', 'name' => 'Nuke'],
                ['key' => 'train', 'name' => 'Train'],
            ],
            'steps_by_best_of' => $defaultStepsByBo,
        ];
    }
}
