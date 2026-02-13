<?php

declare(strict_types=1);

namespace DuelDesk\Controllers;

use DuelDesk\Database\Db;
use DuelDesk\Http\Response;
use DuelDesk\Repositories\MatchRepository;
use DuelDesk\Repositories\PickBanRepository;
use DuelDesk\Repositories\PlayerRepository;
use DuelDesk\Repositories\TeamMemberRepository;
use DuelDesk\Repositories\TournamentRepository;
use DuelDesk\Repositories\TournamentPlayerRepository;
use DuelDesk\Repositories\TournamentTeamRepository;
use DuelDesk\Services\PickBanEngine;
use DuelDesk\Support\Auth;
use DuelDesk\Support\Csrf;
use DuelDesk\Support\Flash;
use Throwable;

final class PickBanController
{
    /** @param array<string, string> $params */
    public function toss(array $params = []): void
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

        $call = strtolower(trim((string)($_POST['call'] ?? '')));
        if (!in_array($call, ['heads', 'tails'], true)) {
            Flash::set('error', 'Choix pile/face invalide.');
            Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
        }

        $meId = Auth::id();
        if ($meId === null) {
            Response::forbidden('Connexion requise.');
        }

        $tRepo = new TournamentRepository();
        $t = $tRepo->findById($tournamentId);
        if ($t === null) {
            Response::notFound();
        }
        $startMode = (string)($t['pickban_start_mode'] ?? 'coin_toss');
        if ($startMode !== 'coin_toss') {
            Flash::set('error', 'Ce tournoi utilise le mode "higher seed" (pas de pile ou face).');
            Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
        }

        $mRepo = new MatchRepository();
        $match = $mRepo->findById($matchId);
        if (!is_array($match) || (int)($match['tournament_id'] ?? 0) !== $tournamentId) {
            Response::notFound();
        }

        $st = (string)($match['status'] ?? 'pending');
        if (in_array($st, ['confirmed', 'void'], true)) {
            Flash::set('error', 'Pick/Ban indisponible sur ce match.');
            Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
        }

        $participantType = (string)($t['participant_type'] ?? 'solo');
        if (!$this->isMatchComplete($participantType, $match)) {
            Flash::set('error', 'Pick/Ban: match incomplet (TBD).');
            Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
        }

        $slot = $this->resolveUserSlot($participantType, $match, $meId);
        if ($slot === null) {
            Response::forbidden('Tu ne peux pas effectuer le pick/ban pour ce match.');
        }

        $rulesetJson = is_string($t['ruleset_json'] ?? null) ? trim((string)$t['ruleset_json']) : '';
        if ($rulesetJson === '') {
            Flash::set('error', 'Pick/Ban non configure pour ce tournoi.');
            Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
        }

        $bestOf = (int)($match['best_of'] ?? 0);
        if (!in_array($bestOf, [1, 3, 5, 7, 9], true)) {
            $bestOf = (int)($t['best_of_default'] ?? 3);
        }
        if (!in_array($bestOf, [1, 3, 5, 7, 9], true)) {
            $bestOf = 3;
        }

        $parsed = PickBanEngine::parseTournamentRuleset($rulesetJson);
        if ($parsed['ruleset'] === null) {
            Flash::set('error', 'Pick/Ban: ruleset invalide (admin).');
            Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
        }

        $cfg = PickBanEngine::buildMatchConfigSnapshot($parsed['ruleset'], $bestOf);
        if ($cfg === null) {
            Flash::set('error', "Pick/Ban: non supporte pour BO{$bestOf}.");
            Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
        }

        $pdo = Db::pdo();
        $pdo->beginTransaction();

        try {
            $pbRepo = new PickBanRepository();
            $existing = $pbRepo->findStateForUpdate($matchId);
            if ($existing !== null) {
                throw new \RuntimeException('Pick/Ban deja demarre.');
            }

            $coinResult = random_int(0, 1) === 0 ? 'heads' : 'tails';
            $firstTurnSlot = ($coinResult === $call) ? $slot : PickBanEngine::otherSlot($slot);

            $pbRepo->createState(
                $matchId,
                PickBanEngine::encodeMatchConfig($cfg),
                $slot,
                $call,
                $coinResult,
                $firstTurnSlot
            );

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            Flash::set('error', $e->getMessage());
            Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
        }

        $resultLabel = $coinResult === 'heads' ? 'Pile' : 'Face';
        $starter = $firstTurnSlot === 1 ? 'A' : 'B';
        Flash::set('success', "Pile ou face: {$resultLabel}. {$starter} commence.");
        Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
    }

    /** @param array<string, string> $params */
    public function start(array $params = []): void
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

        $team = strtolower(trim((string)($_POST['team'] ?? '')));
        if (!in_array($team, ['a', 'b'], true)) {
            Flash::set('error', 'Choix Team A/B invalide.');
            Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
        }

        $meId = Auth::id();
        if ($meId === null) {
            Response::forbidden('Connexion requise.');
        }

        $tRepo = new TournamentRepository();
        $t = $tRepo->findById($tournamentId);
        if ($t === null) {
            Response::notFound();
        }

        $startMode = (string)($t['pickban_start_mode'] ?? 'coin_toss');
        if ($startMode !== 'higher_seed') {
            Flash::set('error', 'Ce tournoi utilise le mode "pile ou face".');
            Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
        }

        $mRepo = new MatchRepository();
        $match = $mRepo->findById($matchId);
        if (!is_array($match) || (int)($match['tournament_id'] ?? 0) !== $tournamentId) {
            Response::notFound();
        }

        $st = (string)($match['status'] ?? 'pending');
        if (in_array($st, ['confirmed', 'void'], true)) {
            Flash::set('error', 'Pick/Ban indisponible sur ce match.');
            Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
        }

        $participantType = (string)($t['participant_type'] ?? 'solo');
        if (!$this->isMatchComplete($participantType, $match)) {
            Flash::set('error', 'Pick/Ban: match incomplet (TBD).');
            Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
        }

        $slot = $this->resolveUserSlot($participantType, $match, $meId);
        if ($slot === null) {
            Response::forbidden('Tu ne peux pas effectuer le pick/ban pour ce match.');
        }

        $higherSeedSlot = $this->resolveHigherSeedSlot($participantType, $tournamentId, $match);
        if ($slot !== $higherSeedSlot) {
            Response::forbidden("Seul le higher seed peut choisir Team A/B.");
        }

        $firstTurnSlot = $team === 'a' ? $higherSeedSlot : PickBanEngine::otherSlot($higherSeedSlot);

        $rulesetJson = is_string($t['ruleset_json'] ?? null) ? trim((string)$t['ruleset_json']) : '';
        if ($rulesetJson === '') {
            Flash::set('error', 'Pick/Ban non configure pour ce tournoi.');
            Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
        }

        $bestOf = (int)($match['best_of'] ?? 0);
        if (!in_array($bestOf, [1, 3, 5, 7, 9], true)) {
            $bestOf = (int)($t['best_of_default'] ?? 3);
        }
        if (!in_array($bestOf, [1, 3, 5, 7, 9], true)) {
            $bestOf = 3;
        }

        $parsed = PickBanEngine::parseTournamentRuleset($rulesetJson);
        if ($parsed['ruleset'] === null) {
            Flash::set('error', 'Pick/Ban: ruleset invalide (admin).');
            Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
        }

        $cfg = PickBanEngine::buildMatchConfigSnapshot($parsed['ruleset'], $bestOf);
        if ($cfg === null) {
            Flash::set('error', "Pick/Ban: non supporte pour BO{$bestOf}.");
            Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
        }

        $pdo = Db::pdo();
        $pdo->beginTransaction();

        try {
            $pbRepo = new PickBanRepository();
            $existing = $pbRepo->findStateForUpdate($matchId);
            if ($existing !== null) {
                throw new \RuntimeException('Pick/Ban deja demarre.');
            }

            // Store dummy coin fields (we display a different UI in higher_seed mode).
            $pbRepo->createState(
                $matchId,
                PickBanEngine::encodeMatchConfig($cfg),
                0,
                'heads',
                'heads',
                $firstTurnSlot
            );

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            Flash::set('error', $e->getMessage());
            Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
        }

        $starter = $firstTurnSlot === 1 ? 'A' : 'B';
        $teamLabel = strtoupper($team);
        Flash::set('success', "Start: higher seed a choisi Team {$teamLabel}. {$starter} commence.");
        Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
    }

    /** @param array<string, string> $params */
    public function act(array $params = []): void
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
            Response::forbidden('Connexion requise.');
        }

        $tRepo = new TournamentRepository();
        $t = $tRepo->findById($tournamentId);
        if ($t === null) {
            Response::notFound();
        }

        $mRepo = new MatchRepository();
        $match = $mRepo->findById($matchId);
        if (!is_array($match) || (int)($match['tournament_id'] ?? 0) !== $tournamentId) {
            Response::notFound();
        }

        $st = (string)($match['status'] ?? 'pending');
        if (in_array($st, ['confirmed', 'void'], true)) {
            Flash::set('error', 'Pick/Ban indisponible sur ce match.');
            Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
        }

        $participantType = (string)($t['participant_type'] ?? 'solo');
        if (!$this->isMatchComplete($participantType, $match)) {
            Flash::set('error', 'Pick/Ban: match incomplet (TBD).');
            Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
        }

        $slot = $this->resolveUserSlot($participantType, $match, $meId);
        if ($slot === null) {
            Response::forbidden('Tu ne peux pas effectuer le pick/ban pour ce match.');
        }

        $mapKey = strtolower(trim((string)($_POST['map_key'] ?? '')));
        if ($mapKey === '') {
            Flash::set('error', 'Map invalide.');
            Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
        }

        $pdo = Db::pdo();
        $pdo->beginTransaction();

        try {
            $pbRepo = new PickBanRepository();
            $state = $pbRepo->findStateForUpdate($matchId);
            if ($state === null) {
                throw new \RuntimeException('Pick/Ban non demarre (start requis).');
            }
            if (((string)($state['status'] ?? 'running')) === 'locked') {
                throw new \RuntimeException('Pick/Ban deja verrouille.');
            }

            $config = PickBanEngine::decodeJson((string)($state['config_json'] ?? ''));
            if ($config === null) {
                throw new \RuntimeException('Pick/Ban: config invalide.');
            }

            $actions = $pbRepo->listActionsForUpdate($matchId);
            $sides = $pbRepo->listSidesForUpdate($matchId);
            $firstTurnSlot = (int)($state['first_turn_slot'] ?? 0);
            $computed = PickBanEngine::compute($config, $firstTurnSlot, $actions, $sides);
            if (!($computed['ok'] ?? false)) {
                throw new \RuntimeException((string)($computed['error'] ?? 'Pick/Ban: erreur.'));
            }

            $nextStep = $computed['next_step'] ?? null;
            $nextStep = is_string($nextStep) ? $nextStep : null;

            if ($nextStep === null) {
                // No more steps -> lock.
                $pbRepo->lock($matchId);
                $pdo->commit();
                Flash::set('success', 'Pick/Ban verrouille.');
                Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
            }

            if ($nextStep === 'side') {
                throw new \RuntimeException('Choix du cote requis avant de continuer.');
            }

            if ($nextStep === 'decider') {
                $deciderKey = (string)($computed['decider_key'] ?? '');
                if ($deciderKey === '') {
                    throw new \RuntimeException('Decider impossible.');
                }
                $mapName = $this->mapNameFromConfig($config, $deciderKey);
                $pbRepo->addAction($matchId, (int)$computed['next_index'], null, 'decider', $deciderKey, $mapName, null);
                $pdo->commit();

                Flash::set('success', 'Decider choisi. Choix du cote requis.');
                Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
            }

            if (!in_array($nextStep, ['ban', 'pick'], true)) {
                throw new \RuntimeException('Step pick/ban invalide.');
            }

            $expectedSlot = (int)($computed['next_slot'] ?? 0);
            if ($expectedSlot !== $slot) {
                throw new \RuntimeException("Ce n'est pas ton tour.");
            }

            $available = $computed['available'] ?? [];
            $allowed = false;
            foreach ($available as $m) {
                if (is_array($m) && strtolower((string)($m['key'] ?? '')) === $mapKey) {
                    $allowed = true;
                    break;
                }
            }
            if (!$allowed) {
                throw new \RuntimeException('Map indisponible (deja prise ou inconnue).');
            }

            $mapName = $this->mapNameFromConfig($config, $mapKey);
            $pbRepo->addAction($matchId, (int)$computed['next_index'], $slot, $nextStep, $mapKey, $mapName, $meId);

            // Auto-decider if needed.
            $actions2 = $pbRepo->listActionsForUpdate($matchId);
            $computed2 = PickBanEngine::compute($config, $firstTurnSlot, $actions2, $sides);
            if (!($computed2['ok'] ?? false)) {
                throw new \RuntimeException((string)($computed2['error'] ?? 'Pick/Ban: erreur.'));
            }

            $next2 = $computed2['next_step'] ?? null;
            $next2 = is_string($next2) ? $next2 : null;

            if ($next2 === 'decider') {
                $deciderKey = (string)($computed2['decider_key'] ?? '');
                if ($deciderKey === '') {
                    throw new \RuntimeException('Decider impossible.');
                }
                $mapName = $this->mapNameFromConfig($config, $deciderKey);
                $pbRepo->addAction($matchId, (int)$computed2['next_index'], null, 'decider', $deciderKey, $mapName, null);
            } elseif ($next2 === null) {
                $pbRepo->lock($matchId);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            Flash::set('error', $e->getMessage());
            Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
        }

        Flash::set('success', 'Pick/Ban mis a jour.');
        Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
    }

    /** @param array<string, string> $params */
    public function side(array $params = []): void
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
            Response::forbidden('Connexion requise.');
        }

        $mapKey = strtolower(trim((string)($_POST['map_key'] ?? '')));
        if ($mapKey === '') {
            Flash::set('error', 'Map invalide.');
            Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
        }

        $sideChoice = strtolower(trim((string)($_POST['side'] ?? '')));
        if (!in_array($sideChoice, ['attack', 'defense'], true)) {
            Flash::set('error', 'Cote invalide.');
            Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
        }

        $tRepo = new TournamentRepository();
        $t = $tRepo->findById($tournamentId);
        if ($t === null) {
            Response::notFound();
        }

        $mRepo = new MatchRepository();
        $match = $mRepo->findById($matchId);
        if (!is_array($match) || (int)($match['tournament_id'] ?? 0) !== $tournamentId) {
            Response::notFound();
        }

        $st = (string)($match['status'] ?? 'pending');
        if (in_array($st, ['confirmed', 'void'], true)) {
            Flash::set('error', 'Pick/Ban indisponible sur ce match.');
            Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
        }

        $participantType = (string)($t['participant_type'] ?? 'solo');
        if (!$this->isMatchComplete($participantType, $match)) {
            Flash::set('error', 'Pick/Ban: match incomplet (TBD).');
            Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
        }

        $slot = $this->resolveUserSlot($participantType, $match, $meId);
        if ($slot === null) {
            Response::forbidden('Tu ne peux pas effectuer le pick/ban pour ce match.');
        }

        $pdo = Db::pdo();
        $pdo->beginTransaction();

        try {
            $pbRepo = new PickBanRepository();
            $state = $pbRepo->findStateForUpdate($matchId);
            if ($state === null) {
                throw new \RuntimeException('Pick/Ban non demarre.');
            }
            if (((string)($state['status'] ?? 'running')) === 'locked') {
                throw new \RuntimeException('Pick/Ban deja verrouille.');
            }

            $config = PickBanEngine::decodeJson((string)($state['config_json'] ?? ''));
            if ($config === null) {
                throw new \RuntimeException('Pick/Ban: config invalide.');
            }

            $actions = $pbRepo->listActionsForUpdate($matchId);
            $sides = $pbRepo->listSidesForUpdate($matchId);
            $firstTurnSlot = (int)($state['first_turn_slot'] ?? 0);
            $computed = PickBanEngine::compute($config, $firstTurnSlot, $actions, $sides);
            if (!($computed['ok'] ?? false)) {
                throw new \RuntimeException((string)($computed['error'] ?? 'Pick/Ban: erreur.'));
            }

            $nextStep = $computed['next_step'] ?? null;
            $nextStep = is_string($nextStep) ? $nextStep : null;
            if ($nextStep !== 'side') {
                throw new \RuntimeException('Aucun choix de cote en attente.');
            }

            $expectedMap = strtolower(trim((string)($computed['side_map_key'] ?? '')));
            if ($expectedMap === '' || $expectedMap !== $mapKey) {
                throw new \RuntimeException('Map invalide pour ce step.');
            }

            $expectedSlot = (int)($computed['next_slot'] ?? 0);
            if ($expectedSlot !== 1 && $expectedSlot !== 2) {
                throw new \RuntimeException('Step cote invalide.');
            }
            if ($expectedSlot !== $slot) {
                throw new \RuntimeException("Ce n'est pas ton tour.");
            }

            $sideForSlot1 = $slot === 1 ? $sideChoice : $this->otherSide($sideChoice);
            $pbRepo->addSide($matchId, $mapKey, $sideForSlot1, $slot, $meId, 'choice');

            // Auto-decider/lock if needed after side selection.
            $sides2 = $pbRepo->listSidesForUpdate($matchId);
            $computed2 = PickBanEngine::compute($config, $firstTurnSlot, $actions, $sides2);
            if (!($computed2['ok'] ?? false)) {
                throw new \RuntimeException((string)($computed2['error'] ?? 'Pick/Ban: erreur.'));
            }

            $next2 = $computed2['next_step'] ?? null;
            $next2 = is_string($next2) ? $next2 : null;

            if ($next2 === 'decider') {
                $deciderKey = (string)($computed2['decider_key'] ?? '');
                if ($deciderKey === '') {
                    throw new \RuntimeException('Decider impossible.');
                }
                $mapName = $this->mapNameFromConfig($config, $deciderKey);
                $pbRepo->addAction($matchId, (int)$computed2['next_index'], null, 'decider', $deciderKey, $mapName, null);
            } elseif ($next2 === null) {
                $pbRepo->lock($matchId);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            Flash::set('error', $e->getMessage());
            Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
        }

        Flash::set('success', 'Cote mis a jour.');
        Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
    }

    /** @param array<string, string> $params */
    public function reset(array $params = []): void
    {
        Auth::requireAdmin();

        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            Response::badRequest('Invalid CSRF token');
        }
        Csrf::rotate();

        $tournamentId = (int)($params['id'] ?? 0);
        $matchId = (int)($params['matchId'] ?? 0);
        if ($tournamentId <= 0 || $matchId <= 0) {
            Response::notFound();
        }

        $mRepo = new MatchRepository();
        $match = $mRepo->findById($matchId);
        if (!is_array($match) || (int)($match['tournament_id'] ?? 0) !== $tournamentId) {
            Response::notFound();
        }

        $pbRepo = new PickBanRepository();
        $pbRepo->reset($matchId);

        Flash::set('success', 'Pick/Ban reset.');
        Response::redirect('/tournaments/' . $tournamentId . '/matches/' . $matchId);
    }

    /** @param array<string, mixed> $match */
    private function isMatchComplete(string $participantType, array $match): bool
    {
        if ($participantType === 'team') {
            return $match['team1_id'] !== null && $match['team2_id'] !== null;
        }

        return $match['player1_id'] !== null && $match['player2_id'] !== null;
    }

    /** @param array<string, mixed> $match */
    private function resolveUserSlot(string $participantType, array $match, int $userId): ?int
    {
        if ($participantType === 'team') {
            $t1 = $match['team1_id'] !== null ? (int)$match['team1_id'] : 0;
            $t2 = $match['team2_id'] !== null ? (int)$match['team2_id'] : 0;
            if ($t1 <= 0 || $t2 <= 0) {
                return null;
            }

            $tmRepo = new TeamMemberRepository();
            if ($tmRepo->isCaptain($t1, $userId)) {
                return 1;
            }
            if ($tmRepo->isCaptain($t2, $userId)) {
                return 2;
            }

            return null;
        }

        $pRepo = new PlayerRepository();
        $p = $pRepo->findByUserId($userId);
        if ($p === null) {
            return null;
        }
        $pid = (int)($p['id'] ?? 0);
        if ($pid <= 0) {
            return null;
        }

        $a = $match['player1_id'] !== null ? (int)$match['player1_id'] : 0;
        $b = $match['player2_id'] !== null ? (int)$match['player2_id'] : 0;
        if ($pid === $a) {
            return 1;
        }
        if ($pid === $b) {
            return 2;
        }

        return null;
    }

    /** @param array<string, mixed> $config */
    private function mapNameFromConfig(array $config, string $mapKey): string
    {
        $pool = $config['pool'] ?? null;
        if (!is_array($pool)) {
            return $mapKey;
        }

        foreach ($pool as $m) {
            if (!is_array($m)) {
                continue;
            }
            $k = is_string($m['key'] ?? null) ? strtolower((string)$m['key']) : '';
            if ($k !== $mapKey) {
                continue;
            }
            $n = is_string($m['name'] ?? null) ? trim((string)$m['name']) : '';
            return $n !== '' ? $n : $mapKey;
        }

        return $mapKey;
    }

    /** @param array<string, mixed> $match */
    private function resolveHigherSeedSlot(string $participantType, int $tournamentId, array $match): int
    {
        $seedA = null;
        $seedB = null;

        if ($participantType === 'team') {
            $aId = $match['team1_id'] !== null ? (int)$match['team1_id'] : 0;
            $bId = $match['team2_id'] !== null ? (int)$match['team2_id'] : 0;
            if ($aId > 0 && $bId > 0) {
                $ttRepo = new TournamentTeamRepository();
                $seedA = $ttRepo->findSeed($tournamentId, $aId);
                $seedB = $ttRepo->findSeed($tournamentId, $bId);
            }
        } else {
            $aId = $match['player1_id'] !== null ? (int)$match['player1_id'] : 0;
            $bId = $match['player2_id'] !== null ? (int)$match['player2_id'] : 0;
            if ($aId > 0 && $bId > 0) {
                $tpRepo = new TournamentPlayerRepository();
                $seedA = $tpRepo->findSeed($tournamentId, $aId);
                $seedB = $tpRepo->findSeed($tournamentId, $bId);
            }
        }

        if ($seedA !== null && $seedB !== null) {
            return $seedA <= $seedB ? 1 : 2;
        }
        if ($seedA !== null) {
            return 1;
        }
        if ($seedB !== null) {
            return 2;
        }

        return 1;
    }

    private function otherSide(string $side): string
    {
        return $side === 'attack' ? 'defense' : 'attack';
    }
}
