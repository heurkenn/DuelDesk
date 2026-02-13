<?php

declare(strict_types=1);

namespace DuelDesk\Controllers;

use DuelDesk\Http\Response;
use DuelDesk\Repositories\GameRepository;
use DuelDesk\Repositories\RulesetRepository;
use DuelDesk\Services\PickBanEngine;
use DuelDesk\Support\Auth;
use DuelDesk\Support\Csrf;
use DuelDesk\Support\Flash;
use DuelDesk\Support\Str;
use DuelDesk\View;

final class AdminRulesetController
{
    /** @param array<string, string> $params */
    public function index(array $params = []): void
    {
        Auth::requireAdmin();

        $gameIdRaw = trim((string)($_GET['game_id'] ?? ''));
        $gameId = (ctype_digit($gameIdRaw) && (int)$gameIdRaw > 0) ? (int)$gameIdRaw : null;

        $gRepo = new GameRepository();
        $games = $gRepo->all();

        $rRepo = new RulesetRepository();
        $rulesets = $rRepo->listAll($gameId);

        View::render('admin/rulesets', [
            'title' => 'Rulesets | DuelDesk',
            'games' => $games,
            'rulesets' => $rulesets,
            'filterGameId' => $gameId,
            'csrfToken' => Csrf::token(),
        ]);
    }

    /** @param array<string, string> $params */
    public function new(array $params = []): void
    {
        Auth::requireAdmin();

        $gRepo = new GameRepository();
        $games = $gRepo->all();

        $gameIdRaw = trim((string)($_GET['game_id'] ?? ''));
        $gameId = (ctype_digit($gameIdRaw) && (int)$gameIdRaw > 0) ? (int)$gameIdRaw : null;

        View::render('admin/ruleset_edit', [
            'title' => 'Nouveau ruleset | DuelDesk',
            'isNew' => true,
            'ruleset' => null,
            'games' => $games,
            'old' => [
                'name' => '',
                'game_id' => $gameId !== null ? (string)$gameId : '',
                'pool' => [],
                'steps_by_best_of' => [
                    '1' => [],
                    '3' => [],
                    '5' => [],
                ],
            ],
            'errors' => [],
            'csrfToken' => Csrf::token(),
        ]);
    }

    /** @param array<string, string> $params */
    public function create(array $params = []): void
    {
        Auth::requireAdmin();

        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            Response::badRequest('Invalid CSRF token');
        }
        Csrf::rotate();

        $gRepo = new GameRepository();
        $games = $gRepo->all();

        $built = $this->buildFromPost($games);
        if ($built['errors'] !== []) {
            View::render('admin/ruleset_edit', [
                'title' => 'Nouveau ruleset | DuelDesk',
                'isNew' => true,
                'ruleset' => null,
                'games' => $games,
                'old' => $built['old'],
                'errors' => $built['errors'],
                'csrfToken' => Csrf::token(),
            ]);
            return;
        }

        $rRepo = new RulesetRepository();
        try {
            $id = $rRepo->create($built['gameId'], $built['name'], 'map_veto', $built['rulesetJson']);
        } catch (\PDOException $e) {
            $errs = $built['errors'];
            if ((string)$e->getCode() === '23000') {
                $errs['name'] = 'Nom deja utilise pour ce jeu.';
            } else {
                $errs['ruleset'] = "Echec creation ruleset.";
            }

            View::render('admin/ruleset_edit', [
                'title' => 'Nouveau ruleset | DuelDesk',
                'isNew' => true,
                'ruleset' => null,
                'games' => $games,
                'old' => $built['old'],
                'errors' => $errs,
                'csrfToken' => Csrf::token(),
            ]);
            return;
        }

        Flash::set('success', 'Ruleset cree.');
        Response::redirect('/admin/rulesets/' . $id);
    }

    /** @param array<string, string> $params */
    public function edit(array $params = []): void
    {
        Auth::requireAdmin();

        $id = (int)($params['id'] ?? 0);
        if ($id <= 0) {
            Response::notFound();
        }

        $rRepo = new RulesetRepository();
        $r = $rRepo->findById($id);
        if ($r === null) {
            Response::notFound();
        }

        $gRepo = new GameRepository();
        $games = $gRepo->all();

        $old = $this->oldFromRulesetRow($r);

        View::render('admin/ruleset_edit', [
            'title' => 'Edit ruleset | DuelDesk',
            'isNew' => false,
            'ruleset' => $r,
            'games' => $games,
            'old' => $old,
            'errors' => [],
            'csrfToken' => Csrf::token(),
        ]);
    }

    /** @param array<string, string> $params */
    public function update(array $params = []): void
    {
        Auth::requireAdmin();

        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            Response::badRequest('Invalid CSRF token');
        }
        Csrf::rotate();

        $id = (int)($params['id'] ?? 0);
        if ($id <= 0) {
            Response::notFound();
        }

        $rRepo = new RulesetRepository();
        $existing = $rRepo->findById($id);
        if ($existing === null) {
            Response::notFound();
        }

        $gRepo = new GameRepository();
        $games = $gRepo->all();

        $built = $this->buildFromPost($games);
        if ($built['errors'] !== []) {
            View::render('admin/ruleset_edit', [
                'title' => 'Edit ruleset | DuelDesk',
                'isNew' => false,
                'ruleset' => $existing,
                'games' => $games,
                'old' => $built['old'],
                'errors' => $built['errors'],
                'csrfToken' => Csrf::token(),
            ]);
            return;
        }

        try {
            $rRepo->update($id, $built['gameId'], $built['name'], 'map_veto', $built['rulesetJson']);
        } catch (\PDOException $e) {
            $errs = $built['errors'];
            if ((string)$e->getCode() === '23000') {
                $errs['name'] = 'Nom deja utilise pour ce jeu.';
            } else {
                $errs['ruleset'] = "Echec mise a jour ruleset.";
            }

            View::render('admin/ruleset_edit', [
                'title' => 'Edit ruleset | DuelDesk',
                'isNew' => false,
                'ruleset' => $existing,
                'games' => $games,
                'old' => $built['old'],
                'errors' => $errs,
                'csrfToken' => Csrf::token(),
            ]);
            return;
        }

        Flash::set('success', 'Ruleset mis a jour.');
        Response::redirect('/admin/rulesets/' . $id);
    }

    /** @param array<string, string> $params */
    public function delete(array $params = []): void
    {
        Auth::requireAdmin();

        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            Response::badRequest('Invalid CSRF token');
        }
        Csrf::rotate();

        $id = (int)($params['id'] ?? 0);
        if ($id <= 0) {
            Response::notFound();
        }

        $rRepo = new RulesetRepository();
        $existing = $rRepo->findById($id);
        if ($existing === null) {
            Response::notFound();
        }

        $rRepo->delete($id);

        Flash::set('success', 'Ruleset supprime.');
        Response::redirect('/admin/rulesets');
    }

    /**
     * @param list<array<string, mixed>> $games
     * @return array{
     *   errors: array<string, string>,
     *   old: array<string, mixed>,
     *   name: string,
     *   gameId: int|null,
     *   rulesetJson: string
     * }
     */
    private function buildFromPost(array $games): array
    {
        $errors = [];

        $name = trim((string)($_POST['name'] ?? ''));
        $gameIdRaw = trim((string)($_POST['game_id'] ?? ''));
        $gameId = null;
        if ($gameIdRaw === '') {
            $errors['game_id'] = 'Jeu requis.';
        } elseif (!ctype_digit($gameIdRaw) || (int)$gameIdRaw <= 0) {
            $errors['game_id'] = 'Jeu invalide.';
        } else {
            $gid = (int)$gameIdRaw;
            $ok = false;
            foreach ($games as $g) {
                if ((int)($g['id'] ?? 0) === $gid) {
                    $ok = true;
                    break;
                }
            }
            if (!$ok) {
                $errors['game_id'] = 'Jeu invalide.';
            } else {
                $gameId = $gid;
            }
        }

        if ($name === '' || $this->strlenSafe($name) > 120) {
            $errors['name'] = 'Nom requis (max 120).';
        }

        $poolKeys = $_POST['pool_key'] ?? [];
        $poolNames = $_POST['pool_name'] ?? [];
        $pool = [];
        if (is_array($poolKeys) && is_array($poolNames)) {
            $n = max(count($poolKeys), count($poolNames));
            for ($i = 0; $i < $n; $i++) {
                $key = isset($poolKeys[$i]) ? trim((string)$poolKeys[$i]) : '';
                $mapName = isset($poolNames[$i]) ? trim((string)$poolNames[$i]) : '';
                if ($key === '' && $mapName === '') {
                    continue;
                }
                if ($key === '' && $mapName !== '') {
                    $key = Str::slug($mapName);
                }
                $pool[] = ['key' => $key, 'name' => $mapName];
            }
        }

        $stepsByBoPost = $_POST['steps'] ?? [];
        $stepsByBo = [];
        if (is_array($stepsByBoPost)) {
            foreach ($stepsByBoPost as $boKey => $raw) {
                $bo = is_int($boKey) || is_string($boKey) ? (int)$boKey : 0;
                if (!in_array($bo, [1, 3, 5, 7, 9], true)) {
                    continue;
                }
                if (!is_array($raw)) {
                    continue;
                }
                $actions = $raw['action'] ?? [];
                $actors = $raw['actor'] ?? [];
                if (!is_array($actions) || !is_array($actors)) {
                    continue;
                }

                $steps = [];
                $n = max(count($actions), count($actors));
                for ($i = 0; $i < $n; $i++) {
                    $act = isset($actions[$i]) ? strtolower(trim((string)$actions[$i])) : '';
                    $by = isset($actors[$i]) ? strtolower(trim((string)$actors[$i])) : '';
                    if ($act === '' && $by === '') {
                        continue;
                    }
                    if ($act === '') {
                        continue;
                    }
                    if (!in_array($act, ['ban', 'pick'], true)) {
                        continue;
                    }
                    if (!in_array($by, ['starter', 'other', 'alternate'], true)) {
                        $by = 'alternate';
                    }
                    $steps[] = ['action' => $act, 'actor' => $by];
                }

                // Decider is always last and automatic.
                if ($steps !== []) {
                    $steps[] = ['action' => 'decider', 'actor' => 'any'];
                }

                if ($steps !== []) {
                    $stepsByBo[(string)$bo] = $steps;
                }
            }
        }

        $old = [
            'name' => $name,
            'game_id' => $gameIdRaw,
            'pool' => $pool,
            'steps_by_best_of' => [
                '1' => $this->stripDecider($stepsByBo['1'] ?? []),
                '3' => $this->stripDecider($stepsByBo['3'] ?? []),
                '5' => $this->stripDecider($stepsByBo['5'] ?? []),
            ],
        ];

        $ruleset = [
            'kind' => 'map_veto',
            'pool' => $pool,
            'steps_by_best_of' => $stepsByBo,
        ];

        $norm = PickBanEngine::normalizeTournamentRuleset($ruleset);
        if (!is_array($norm['ruleset'] ?? null)) {
            $errors['ruleset'] = (string)($norm['error'] ?? 'Ruleset invalide.');
        }

        $rulesetJson = '';
        if ($errors === []) {
            $rulesetJson = json_encode($norm['ruleset'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!is_string($rulesetJson) || $rulesetJson === '') {
                $errors['ruleset'] = "Echec d'encodage ruleset.";
            }
        }

        return [
            'errors' => $errors,
            'old' => $old,
            'name' => $name,
            'gameId' => $gameId,
            'rulesetJson' => $rulesetJson,
        ];
    }

    /** @param array<string, mixed> $row */
    private function oldFromRulesetRow(array $row): array
    {
        $json = is_string($row['ruleset_json'] ?? null) ? trim((string)$row['ruleset_json']) : '';
        $parsed = $json !== '' ? PickBanEngine::decodeJson($json) : null;

        $pool = [];
        $stepsByBo = [
            '1' => [],
            '3' => [],
            '5' => [],
        ];

        if (is_array($parsed)) {
            if (isset($parsed['pool']) && is_array($parsed['pool'])) {
                foreach ($parsed['pool'] as $m) {
                    if (!is_array($m)) {
                        continue;
                    }
                    $key = is_string($m['key'] ?? null) ? trim((string)$m['key']) : '';
                    $name = is_string($m['name'] ?? null) ? trim((string)$m['name']) : '';
                    if ($key !== '' && $name !== '') {
                        $pool[] = ['key' => $key, 'name' => $name];
                    }
                }
            }

            if (isset($parsed['steps_by_best_of']) && is_array($parsed['steps_by_best_of'])) {
                foreach (['1', '3', '5'] as $bo) {
                    $steps = $parsed['steps_by_best_of'][$bo] ?? null;
                    if (!is_array($steps)) {
                        continue;
                    }
                    $out = [];
                    foreach ($steps as $s) {
                        $act = '';
                        $actor = '';
                        if (is_string($s)) {
                            $act = strtolower(trim($s));
                            $actor = 'alternate';
                        } elseif (is_array($s)) {
                            $act = is_string($s['action'] ?? null) ? strtolower(trim((string)$s['action'])) : '';
                            $actor = is_string($s['actor'] ?? null) ? strtolower(trim((string)$s['actor'])) : '';
                        } else {
                            continue;
                        }
                        if ($act === '' || $act === 'decider') {
                            continue;
                        }
                        if (!in_array($act, ['ban', 'pick'], true)) {
                            continue;
                        }
                        if (!in_array($actor, ['starter', 'other', 'alternate'], true)) {
                            $actor = 'alternate';
                        }
                        $out[] = ['action' => $act, 'actor' => $actor];
                    }
                    $stepsByBo[$bo] = $out;
                }
            }
        }

        $gameId = $row['game_id'] ?? null;
        $gameId = (is_int($gameId) || is_string($gameId)) ? (int)$gameId : 0;

        return [
            'name' => (string)($row['name'] ?? ''),
            'game_id' => $gameId > 0 ? (string)$gameId : '',
            'pool' => $pool,
            'steps_by_best_of' => $stepsByBo,
        ];
    }

    /** @param list<array<string, mixed>> $steps */
    private function stripDecider(array $steps): array
    {
        $out = [];
        foreach ($steps as $s) {
            if (!is_array($s)) {
                continue;
            }
            $act = is_string($s['action'] ?? null) ? strtolower(trim((string)$s['action'])) : '';
            if ($act === '' || $act === 'decider') {
                continue;
            }
            $out[] = $s;
        }
        return $out;
    }

    private function strlenSafe(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return (int)mb_strlen($value);
        }
        return strlen($value);
    }
}
