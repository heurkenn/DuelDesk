<?php

declare(strict_types=1);

use DuelDesk\View;
use DuelDesk\Support\Auth;

/** @var array<string, mixed> $tournament */
/** @var array<string, mixed> $match */
/** @var 'solo'|'team' $participantType */
/** @var string $csrfToken */
/** @var bool $canReport */
/** @var bool $pickbanRequired */
/** @var bool $pickbanLocked */
/** @var bool $pickbanBlockingReport */
/** @var array<string, mixed>|null $pickbanState */
/** @var list<array<string, mixed>> $pickbanActions */
/** @var list<array<string, mixed>> $pickbanSides */
/** @var array<string, mixed>|null $pickbanComputed */
/** @var int|null $pickbanMySlot */
/** @var bool $pickbanIsMyTurn */
/** @var 'coin_toss'|'higher_seed' $pickbanStartMode */
/** @var int|null $pickbanHigherSeedSlot */
/** @var int|null $pickbanSeedA */
/** @var int|null $pickbanSeedB */

$tid = (int)($tournament['id'] ?? 0);
$mid = (int)($match['id'] ?? 0);
$bracket = (string)($match['bracket'] ?? 'winners');
$round = (int)($match['round'] ?? 0);
$pos = (int)($match['round_pos'] ?? 0);
$status = (string)($match['status'] ?? 'pending');
$bestOf = (int)($match['best_of'] ?? 0);
$scheduledAt = is_string($match['scheduled_at'] ?? null) ? (string)$match['scheduled_at'] : '';

$reportedScore1 = $match['reported_score1'] ?? null;
$reportedScore2 = $match['reported_score2'] ?? null;
$reportedWinnerSlot = $match['reported_winner_slot'] ?? null;
$reportedAt = is_string($match['reported_at'] ?? null) ? (string)$match['reported_at'] : '';
$reportedByUsername = (string)($match['reported_by_username'] ?? '');

$counterScore1 = $match['counter_reported_score1'] ?? null;
$counterScore2 = $match['counter_reported_score2'] ?? null;
$counterWinnerSlot = $match['counter_reported_winner_slot'] ?? null;
$counterAt = is_string($match['counter_reported_at'] ?? null) ? (string)$match['counter_reported_at'] : '';
$counterByUsername = (string)($match['counter_reported_by_username'] ?? '');

if ($participantType === 'team') {
    $aId = $match['team1_id'] !== null ? (int)$match['team1_id'] : null;
    $bId = $match['team2_id'] !== null ? (int)$match['team2_id'] : null;
    $aName = (string)($match['t1_name'] ?? '');
    $bName = (string)($match['t2_name'] ?? '');
    $win = $match['winner_team_id'] !== null ? (int)$match['winner_team_id'] : null;
} else {
    $aId = $match['player1_id'] !== null ? (int)$match['player1_id'] : null;
    $bId = $match['player2_id'] !== null ? (int)$match['player2_id'] : null;
    $aName = (string)($match['p1_name'] ?? '');
    $bName = (string)($match['p2_name'] ?? '');
    $win = $match['winner_id'] !== null ? (int)$match['winner_id'] : null;
}

$aLabel = $aId === null
    ? (($bId !== null && $win !== null && $win === $bId) ? 'BYE' : 'TBD')
    : ($aName !== '' ? $aName : '#');
$bLabel = $bId === null
    ? (($aId !== null && $win !== null && $win === $aId) ? 'BYE' : 'TBD')
    : ($bName !== '' ? $bName : '#');

$aWin = $win !== null && $aId !== null && $win === $aId;
$bWin = $win !== null && $bId !== null && $win === $bId;

$tag = $bracket === 'grand'
    ? ($round >= 2 ? 'GF2' : 'GF')
    : ($bracket === 'round_robin'
        ? ('RR' . $round . '#' . $pos)
        : (($bracket === 'losers' ? 'L' : 'W') . $round . '#' . $pos)
    );

$bracketLabel = match ($bracket) {
    'winners' => 'Gagnants',
    'losers' => 'Perdants',
    'grand' => $round >= 2 ? 'Finale (Reset)' : 'Finale',
    'round_robin' => 'Round robin',
    default => $bracket,
};

$score1 = (int)($match['score1'] ?? 0);
$score2 = (int)($match['score2'] ?? 0);
$scoreText = $status === 'confirmed' ? ($score1 . ' - ' . $score2) : 'TBD';
$who = $participantType === 'team' ? 'Equipe' : 'Joueur';

$hasReportA = in_array($status, ['reported', 'disputed'], true)
    && $reportedScore1 !== null
    && $reportedScore2 !== null
    && $reportedWinnerSlot !== null;

$hasReportB = $status === 'disputed'
    && $counterScore1 !== null
    && $counterScore2 !== null
    && $counterWinnerSlot !== null;

$me = Auth::user();
$meUsername = is_array($me) ? (string)($me['username'] ?? '') : '';
$meIsA = ($meUsername !== '' && $reportedByUsername !== '' && $meUsername === $reportedByUsername);
$meIsB = ($meUsername !== '' && $counterByUsername !== '' && $meUsername === $counterByUsername);

// Prefill: your own report if available, otherwise show existing report A (useful as a starting point).
$reportScore1 = $score1;
$reportScore2 = $score2;
$reportWinnerSlot = '';

if ($meIsB && $hasReportB) {
    $reportScore1 = (int)$counterScore1;
    $reportScore2 = (int)$counterScore2;
    $reportWinnerSlot = (string)(int)$counterWinnerSlot;
} elseif ($meIsA && $hasReportA) {
    $reportScore1 = (int)$reportedScore1;
    $reportScore2 = (int)$reportedScore2;
    $reportWinnerSlot = (string)(int)$reportedWinnerSlot;
} elseif ($hasReportA) {
    $reportScore1 = (int)$reportedScore1;
    $reportScore2 = (int)$reportedScore2;
    $reportWinnerSlot = (string)(int)$reportedWinnerSlot;
}

$matchComplete = $aId !== null && $bId !== null;
?>

<div
    data-dd-match-meta
    data-tournament-id="<?= (int)$tid ?>"
    data-match-id="<?= (int)$mid ?>"
    data-pickban-required="<?= $pickbanRequired ? '1' : '0' ?>"
    data-pickban-locked="<?= $pickbanLocked ? '1' : '0' ?>"
    data-status="<?= View::e($status) ?>"
    hidden
></div>

<div class="pagehead">
    <div>
        <h1 class="pagehead__title">Match <?= View::e($tag) ?></h1>
        <p class="pagehead__lead">
            <span class="table__strong"><?= View::e((string)($tournament['name'] ?? '')) ?></span>
            <span class="meta__dot" aria-hidden="true"></span>
            <span class="pill"><?= View::e($bracketLabel) ?></span>
            <span class="pill pill--soft"><?= View::e($status) ?></span>
            <?php if ($bestOf > 0): ?>
                <span class="pill pill--soft"><?= 'BO' . (int)$bestOf ?></span>
            <?php endif; ?>
            <?php if ($scheduledAt !== ''): ?>
                <span class="pill pill--soft"><?= View::e(substr($scheduledAt, 0, 16) . ' UTC') ?></span>
            <?php endif; ?>
        </p>
    </div>
    <div class="pagehead__actions">
        <?php if (Auth::isAdmin()): ?>
            <a class="btn btn--ghost" href="/admin/tournaments/<?= (int)$tid ?>">Admin</a>
        <?php endif; ?>
        <a class="btn btn--ghost" href="/tournaments/<?= (int)$tid ?>">Retour</a>
    </div>
</div>

<section class="card">
    <div class="card__header">
        <div>
            <h2 class="card__title"><?= View::e($who) ?> A vs <?= View::e($who) ?> B</h2>
            <p class="card__subtitle"><?= View::e($bracketLabel) ?> · Tour <?= (int)$round ?> · Match #<?= (int)$pos ?></p>
        </div>
        <div class="pill<?= $status === 'confirmed' ? '' : ' pill--soft' ?>"><?= View::e($scoreText) ?></div>
    </div>
    <div class="card__body">
        <div class="split">
            <div class="card card--nested">
                <div class="card__header">
                    <h3 class="card__title"><?= View::e($who) ?> A</h3>
                    <?php if ($aWin): ?><span class="pill">WIN</span><?php endif; ?>
                </div>
                <div class="card__body">
                    <div class="table__strong"><?= View::e($aLabel) ?></div>
                    <div class="muted mono" style="margin-top: 6px;">score: <?= (int)$score1 ?></div>
                </div>
            </div>

            <div class="card card--nested">
                <div class="card__header">
                    <h3 class="card__title"><?= View::e($who) ?> B</h3>
                    <?php if ($bWin): ?><span class="pill">WIN</span><?php endif; ?>
                </div>
                <div class="card__body">
                    <div class="table__strong"><?= View::e($bLabel) ?></div>
                    <div class="muted mono" style="margin-top: 6px;">score: <?= (int)$score2 ?></div>
                </div>
            </div>
        </div>

        <div class="section" style="margin-top: 18px;">
            <div class="codeblock"><code>ID match: <?= (int)$mid ?></code></div>
        </div>
    </div>
</section>

<div data-partial="pickban">
    <?php if ($pickbanRequired && $matchComplete): ?>
        <?php
            $pbStatus = $pickbanLocked ? 'locked' : (is_array($pickbanState) ? 'running' : 'not_started');
            $pbNextStep = is_array($pickbanComputed) && ($pickbanComputed['ok'] ?? false) ? (string)($pickbanComputed['next_step'] ?? '') : '';
            $pbNextSlot = is_array($pickbanComputed) ? (int)($pickbanComputed['next_slot'] ?? 0) : 0;
            $pbAvailable = is_array($pickbanComputed) ? ($pickbanComputed['available'] ?? []) : [];
            $pbDeciderKey = is_array($pickbanComputed) ? (string)($pickbanComputed['decider_key'] ?? '') : '';
            $pbDeciderName = '';
            if ($pbDeciderKey !== '' && $pbAvailable !== [] && count($pbAvailable) === 1 && is_array($pbAvailable[0] ?? null)) {
                $pbDeciderName = (string)($pbAvailable[0]['name'] ?? '');
            }

            $pbSideMapKey = is_array($pickbanComputed) ? (string)($pickbanComputed['side_map_key'] ?? '') : '';
            $pbSideMapName = is_array($pickbanComputed) ? (string)($pickbanComputed['side_map_name'] ?? '') : '';
            $pbSideMode = is_array($pickbanComputed) ? (string)($pickbanComputed['side_mode'] ?? '') : '';

            $coinCallSlot = is_array($pickbanState) ? (int)($pickbanState['coin_call_slot'] ?? 0) : 0;
            $coinCall = is_array($pickbanState) ? (string)($pickbanState['coin_call'] ?? '') : '';
            $coinResult = is_array($pickbanState) ? (string)($pickbanState['coin_result'] ?? '') : '';
            $firstTurnSlot = is_array($pickbanState) ? (int)($pickbanState['first_turn_slot'] ?? 0) : 0;

            $slotLabel = static fn (int $s): string => $s === 1 ? 'A' : 'B';
            $coinLabel = static function (string $v): string {
                return $v === 'heads' ? 'Pile' : ($v === 'tails' ? 'Face' : '-');
            };

            // Once pick/ban has started, infer the "start mode" from the stored state:
            // coin_toss always has coin_call_slot=1|2, higher_seed uses a dummy value (0).
            $pbStartedMode = $pickbanStartMode;
            if (is_array($pickbanState)) {
                $pbStartedMode = ($coinCallSlot === 1 || $coinCallSlot === 2) ? 'coin_toss' : 'higher_seed';
            }

            $nextActor = '';
            if (!$pickbanLocked && $pbNextStep !== '' && ($pbNextSlot === 1 || $pbNextSlot === 2)) {
                if (in_array($pbNextStep, ['ban', 'pick'], true)) {
                    $verb = $pbNextStep === 'ban' ? 'bannir' : 'pick';
                    $nextActor = $slotLabel($pbNextSlot) . ' doit ' . $verb . ' une map.';
                } elseif ($pbNextStep === 'side' && $pbSideMapKey !== '') {
                    $mapLabel = $pbSideMapName !== '' ? $pbSideMapName : $pbSideMapKey;
                    $nextActor = $slotLabel($pbNextSlot) . ' doit choisir le cote sur ' . $mapLabel . '.';
                }
            }

            $isMyMapTurn = $pickbanIsMyTurn && in_array($pbNextStep, ['ban', 'pick'], true);

            $sideByKey = [];
            foreach ($pickbanSides as $s) {
                if (!is_array($s)) continue;
                $k = is_string($s['map_key'] ?? null) ? strtolower(trim((string)$s['map_key'])) : '';
                if ($k === '') continue;
                $sideByKey[$k] = [
                    'side_for_slot1' => is_string($s['side_for_slot1'] ?? null) ? (string)$s['side_for_slot1'] : '',
                    'source' => is_string($s['source'] ?? null) ? (string)$s['source'] : '',
                    'chosen_by_slot' => $s['chosen_by_slot'] !== null ? (int)$s['chosen_by_slot'] : null,
                ];
            }
        ?>

        <section class="card" style="margin-top: 16px;">
            <div class="card__header">
                <div>
                    <h2 class="card__title">Pick / Ban (maps)</h2>
                    <p class="card__subtitle">
                        Obligatoire avant de pouvoir reporter le score
                        <?php if ($bestOf > 0): ?>
                            <span class="meta__dot" aria-hidden="true"></span>
                            BO<?= (int)$bestOf ?>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="pill<?= $pbStatus === 'locked' ? '' : ' pill--soft' ?>"><?= View::e($pbStatus) ?></div>
            </div>
            <div class="card__body">
                <?php if (!is_array($pickbanState)): ?>
                    <?php if ($pickbanStartMode === 'higher_seed'): ?>
                        <div class="split" style="align-items: flex-start;">
                            <div>
                                <div class="table__strong">Higher seed</div>
                                <div class="muted" style="margin-top: 6px;">
                                    Le higher seed choisit d'etre Team A (start) ou Team B (second).
                                    <span class="meta__dot" aria-hidden="true"></span>
                                    <?php if (($pickbanSeedA ?? null) !== null || ($pickbanSeedB ?? null) !== null): ?>
                                        Seeds: A<?= $pickbanSeedA !== null ? (' #' . (int)$pickbanSeedA) : '' ?> / B<?= $pickbanSeedB !== null ? (' #' . (int)$pickbanSeedB) : '' ?>
                                    <?php else: ?>
                                        Seeds indisponibles.
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div>
                                <?php if ($pickbanHigherSeedSlot === 1 || $pickbanHigherSeedSlot === 2): ?>
                                    <?php if ($pickbanMySlot !== null && $pickbanMySlot === $pickbanHigherSeedSlot): ?>
                                        <form method="post" action="/tournaments/<?= (int)$tid ?>/matches/<?= (int)$mid ?>/pickban/start" class="inline" data-ajax="1">
                                            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                            <button class="btn btn--primary btn--compact" type="submit" name="team" value="a">Je choisis Team A</button>
                                            <button class="btn btn--ghost btn--compact" type="submit" name="team" value="b">Je choisis Team B</button>
                                        </form>
                                    <?php else: ?>
                                        <div class="empty__hint">En attente du higher seed (slot <?= View::e($slotLabel((int)$pickbanHigherSeedSlot)) ?>).</div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="empty__hint">Impossible de determiner le higher seed.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="split" style="align-items: flex-start;">
                            <div>
                                <div class="table__strong">Pile ou face</div>
                                <div class="muted" style="margin-top: 6px;">Le vainqueur commence le pick/ban.</div>
                            </div>
                            <div>
                                <?php if ($pickbanMySlot !== null): ?>
                                    <form method="post" action="/tournaments/<?= (int)$tid ?>/matches/<?= (int)$mid ?>/pickban/toss" class="inline" data-ajax="1">
                                        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                        <select class="select select--compact" name="call" required>
                                            <option value="heads">Pile</option>
                                            <option value="tails">Face</option>
                                        </select>
                                        <button class="btn btn--primary btn--compact" type="submit">Lancer</button>
                                    </form>
                                <?php else: ?>
                                    <div class="empty__hint">Seuls les participants (joueurs/capitaines) peuvent lancer le pile ou face.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="split" style="align-items: flex-start;">
                        <div>
                            <?php if ($pbStartedMode === 'higher_seed'): ?>
                                <?php
                                    $hs = ($pickbanHigherSeedSlot === 1 || $pickbanHigherSeedSlot === 2) ? (int)$pickbanHigherSeedSlot : 0;
                                    $starterSlot = ($firstTurnSlot === 1 || $firstTurnSlot === 2) ? (int)$firstTurnSlot : 0;
                                    $chosenTeam = ($hs > 0 && $starterSlot > 0) ? ($hs === $starterSlot ? 'A' : 'B') : '';
                                ?>
                                <div class="table__strong">Higher seed</div>
                                <div class="muted" style="margin-top: 8px; display: flex; flex-wrap: wrap; gap: 8px;">
                                    <span class="pill pill--soft">Higher seed: <?= $hs > 0 ? View::e($slotLabel($hs)) : '-' ?></span>
                                    <?php if ($chosenTeam !== ''): ?><span class="pill pill--soft">Choix: Team <?= View::e($chosenTeam) ?></span><?php endif; ?>
                                    <span class="pill">Start: <?= View::e($slotLabel($starterSlot === 2 ? 2 : 1)) ?></span>
                                </div>
                            <?php else: ?>
                                <div class="table__strong">Pile ou face</div>
                                <div class="muted" style="margin-top: 8px; display: flex; flex-wrap: wrap; gap: 8px;">
                                    <?php if ($coinCallSlot === 1 || $coinCallSlot === 2): ?>
                                        <span class="pill pill--soft">Call: <?= $slotLabel($coinCallSlot) ?> (<?= View::e($coinLabel($coinCall)) ?>)</span>
                                        <span class="pill pill--soft">Resultat: <?= View::e($coinLabel($coinResult)) ?></span>
                                        <span class="pill">Start: <?= View::e($slotLabel($firstTurnSlot === 2 ? 2 : 1)) ?></span>
                                    <?php else: ?>
                                        <span class="pill pill--soft">Pile ou face effectue</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($pickbanActions !== []): ?>
                                <div class="tablewrap" style="margin-top: 12px;">
                                    <table class="table table--compact">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Action</th>
                                                <th>Slot</th>
                                                <th>Map</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($pickbanActions as $a): ?>
                                                <?php
                                                    $i = (int)($a['step_index'] ?? 0);
                                                    $act = strtoupper((string)($a['action'] ?? ''));
                                                    $s = $a['slot'] !== null ? (int)$a['slot'] : null;
                                                    $mapName = (string)($a['map_name'] ?? '');
                                                ?>
                                                <tr>
                                                    <td class="mono"><?= (int)($i + 1) ?></td>
                                                    <td class="mono"><?= View::e($act !== '' ? $act : '-') ?></td>
                                                    <td class="mono">
                                                        <?php if ($s === 1 || $s === 2): ?>
                                                            <?= View::e($slotLabel($s)) ?>
                                                        <?php else: ?>
                                                            <span class="muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?= View::e($mapName !== '' ? $mapName : '-') ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div>
                            <?php if ($pickbanLocked): ?>
                                <div class="table__strong">Maps a jouer</div>
                                <div class="muted" style="margin-top: 6px;">
                                    <?php
                                        $toPlay = [];
                                        foreach ($pickbanActions as $a) {
                                            $act = (string)($a['action'] ?? '');
                                            if (!in_array($act, ['pick', 'decider'], true)) {
                                                continue;
                                            }
                                            $n = (string)($a['map_name'] ?? '');
                                            if ($n !== '') {
                                                $k = is_string($a['map_key'] ?? null) ? strtolower((string)$a['map_key']) : '';
                                                $toPlay[] = ['key' => $k, 'name' => $n];
                                            }
                                        }
                                    ?>
                                    <?php if ($toPlay === []): ?>
                                        <span class="muted">-</span>
                                    <?php else: ?>
                                        <?php foreach ($toPlay as $m): ?>
                                            <?php
                                                $k = (string)($m['key'] ?? '');
                                                $n = (string)($m['name'] ?? '');
                                                $meta = $k !== '' ? ($sideByKey[$k] ?? null) : null;
                                                $s1 = is_array($meta) ? (string)($meta['side_for_slot1'] ?? '') : '';
                                                $aSide = $s1 === 'attack' ? 'Attaque' : ($s1 === 'defense' ? 'Defense' : '-');
                                                $bSide = $s1 === 'attack' ? 'Defense' : ($s1 === 'defense' ? 'Attaque' : '-');
                                                $src = is_array($meta) ? (string)($meta['source'] ?? '') : '';
                                                $chosenBy = is_array($meta) ? ($meta['chosen_by_slot'] ?? null) : null;
                                                $by = ($src === 'coin') ? 'coin' : (($chosenBy === 1 || $chosenBy === 2) ? ('choisi par ' . $slotLabel((int)$chosenBy)) : '');
                                            ?>
                                            <div style="margin-top: 6px;">
                                                <span class="table__strong"><?= View::e($n) ?></span>
                                                <span class="meta__dot" aria-hidden="true"></span>
                                                A: <?= View::e($aSide) ?>
                                                <span class="meta__dot" aria-hidden="true"></span>
                                                B: <?= View::e($bSide) ?>
                                                <?php if ($by !== ''): ?>
                                                    <span class="meta__dot" aria-hidden="true"></span>
                                                    <?= View::e($by) ?>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>

                                <?php if (Auth::isAdmin()): ?>
                                    <form method="post" action="/tournaments/<?= (int)$tid ?>/matches/<?= (int)$mid ?>/pickban/reset" class="inline" style="margin-top: 12px;" data-confirm="Reset pick/ban ?" data-ajax="1">
                                        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                        <button class="btn btn--ghost btn--compact" type="submit">Reset (admin)</button>
                                    </form>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php if ($nextActor !== ''): ?>
                                    <div class="pill"><?= View::e($nextActor) ?></div>
                                <?php endif; ?>

                                <?php if ($pbNextStep === 'side' && $pbSideMapKey !== '' && $pbSideMode === 'choice'): ?>
                                    <div class="muted" style="margin-top: 10px;">
                                        Cote: <?= View::e($pbSideMapName !== '' ? $pbSideMapName : $pbSideMapKey) ?>
                                    </div>
                                    <?php if ($pickbanMySlot !== null): ?>
                                        <form method="post" action="/tournaments/<?= (int)$tid ?>/matches/<?= (int)$mid ?>/pickban/side" class="inline" style="margin-top: 10px;" data-ajax="1">
                                            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                            <input type="hidden" name="map_key" value="<?= View::e($pbSideMapKey) ?>">
                                            <button class="btn btn--primary btn--compact" type="submit" name="side" value="attack" <?= ($pbNextSlot === (int)$pickbanMySlot) ? '' : 'disabled' ?>>Je commence Attaque</button>
                                            <button class="btn btn--ghost btn--compact" type="submit" name="side" value="defense" <?= ($pbNextSlot === (int)$pickbanMySlot) ? '' : 'disabled' ?>>Je commence Defense</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($pickbanMySlot === null || $pbNextSlot !== (int)$pickbanMySlot): ?>
                                        <div class="empty__hint" style="margin-top: 10px;">En attente de l'autre slot.</div>
                                    <?php endif; ?>
                                <?php elseif ($pbNextStep === 'decider' && $pbDeciderKey !== ''): ?>
                                    <div class="muted" style="margin-top: 10px;">Decider: <?= View::e($pbDeciderName !== '' ? $pbDeciderName : $pbDeciderKey) ?></div>
                                    <?php if ($pickbanMySlot !== null): ?>
                                        <form method="post" action="/tournaments/<?= (int)$tid ?>/matches/<?= (int)$mid ?>/pickban/act" class="inline" style="margin-top: 10px;" data-ajax="1">
                                            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                            <button class="btn btn--primary btn--compact" type="submit" name="map_key" value="<?= View::e($pbDeciderKey) ?>">Verrouiller</button>
                                        </form>
                                    <?php endif; ?>
                                <?php elseif ($pbAvailable !== []): ?>
                                    <form method="post" action="/tournaments/<?= (int)$tid ?>/matches/<?= (int)$mid ?>/pickban/act" style="margin-top: 12px;" data-ajax="1">
                                        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                        <div class="pickban-grid">
                                            <?php foreach ($pbAvailable as $m): ?>
                                                <?php if (!is_array($m)) continue; ?>
                                                <?php $k = (string)($m['key'] ?? ''); $n = (string)($m['name'] ?? $k); ?>
                                                <button class="btn btn--ghost btn--compact" type="submit" name="map_key" value="<?= View::e($k) ?>" <?= $isMyMapTurn ? '' : 'disabled' ?>>
                                                    <?= View::e(($pbNextStep === 'ban' ? 'BAN ' : ($pbNextStep === 'pick' ? 'PICK ' : '')) . $n) ?>
                                                </button>
                                            <?php endforeach; ?>
                                        </div>
                                    </form>
                                    <?php if (!$isMyMapTurn): ?>
                                        <div class="empty__hint" style="margin-top: 10px;">En attente de l'autre slot.</div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="empty__hint">Aucune map disponible.</div>
                                <?php endif; ?>

                                <?php if (Auth::isAdmin()): ?>
                                    <form method="post" action="/tournaments/<?= (int)$tid ?>/matches/<?= (int)$mid ?>/pickban/reset" class="inline" style="margin-top: 12px;" data-confirm="Reset pick/ban ?" data-ajax="1">
                                        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                        <button class="btn btn--ghost btn--compact" type="submit">Reset (admin)</button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>
</div>

<div data-partial="report">
    <?php if ($status === 'reported' && $hasReportA): ?>
        <section class="card" style="margin-top: 16px;">
            <div class="card__header">
                <div>
                    <h2 class="card__title">Score reporte</h2>
                    <p class="card__subtitle">En attente de validation admin</p>
                </div>
                <div class="pill">reported</div>
            </div>
            <div class="card__body">
                <div class="split" style="align-items: flex-start;">
                    <div>
                        <div class="table__strong"><?= View::e((string)$reportScore1) ?> - <?= View::e((string)$reportScore2) ?></div>
                        <div class="muted" style="margin-top: 6px;">
                            Winner: <?= $reportWinnerSlot === '1' ? 'A' : 'B' ?>
                            <?php if ($reportedByUsername !== ''): ?>
                                <span class="meta__dot" aria-hidden="true"></span>
                                par <?= View::e($reportedByUsername) ?>
                            <?php endif; ?>
                            <?php if ($reportedAt !== ''): ?>
                                <span class="meta__dot" aria-hidden="true"></span>
                                <?= View::e(substr($reportedAt, 0, 16) . ' UTC') ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="muted">
                        L'admin doit confirmer ce score dans le dashboard.
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($status === 'disputed' && $hasReportA && $hasReportB): ?>
        <section class="card" style="margin-top: 16px;">
            <div class="card__header">
                <div>
                    <h2 class="card__title">Litige sur le score</h2>
                    <p class="card__subtitle">Deux reports differents ont ete soumis (validation admin requise)</p>
                </div>
                <div class="pill">disputed</div>
            </div>
            <div class="card__body">
                <div class="split">
                    <div class="card card--nested">
                        <div class="card__header">
                            <h3 class="card__title">Report A</h3>
                            <?php if ($reportedByUsername !== ''): ?><span class="pill pill--soft"><?= View::e($reportedByUsername) ?></span><?php endif; ?>
                        </div>
                        <div class="card__body">
                            <div class="table__strong"><?= (int)$reportedScore1 ?> - <?= (int)$reportedScore2 ?></div>
                            <div class="muted" style="margin-top: 6px;">
                                Winner: <?= ((string)(int)$reportedWinnerSlot) === '1' ? 'A' : 'B' ?>
                                <?php if ($reportedAt !== ''): ?>
                                    <span class="meta__dot" aria-hidden="true"></span>
                                    <?= View::e(substr($reportedAt, 0, 16) . ' UTC') ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="card card--nested">
                        <div class="card__header">
                            <h3 class="card__title">Report B</h3>
                            <?php if ($counterByUsername !== ''): ?><span class="pill pill--soft"><?= View::e($counterByUsername) ?></span><?php endif; ?>
                        </div>
                        <div class="card__body">
                            <div class="table__strong"><?= (int)$counterScore1 ?> - <?= (int)$counterScore2 ?></div>
                            <div class="muted" style="margin-top: 6px;">
                                Winner: <?= ((string)(int)$counterWinnerSlot) === '1' ? 'A' : 'B' ?>
                                <?php if ($counterAt !== ''): ?>
                                    <span class="meta__dot" aria-hidden="true"></span>
                                    <?= View::e(substr($counterAt, 0, 16) . ' UTC') ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="muted" style="margin-top: 12px;">
                    L'admin doit confirmer la bonne version dans le dashboard.
                </div>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($status !== 'confirmed' && $status !== 'void'): ?>
        <section class="card" style="margin-top: 16px;">
            <div class="card__header">
                <div>
                    <h2 class="card__title">Reporter le score</h2>
                    <p class="card__subtitle">
                        <?php if ($bestOf > 0): ?>BO<?= (int)$bestOf ?><?php else: ?>Best-of<?php endif; ?> · validation admin requise
                    </p>
                </div>
                <?php if ($pickbanBlockingReport): ?>
                    <div class="pill pill--soft">pick/ban requis</div>
                <?php elseif (!$canReport): ?>
                    <div class="pill pill--soft">restreint</div>
                <?php endif; ?>
            </div>
            <div class="card__body">
                <?php if (!$matchComplete): ?>
                    <div class="empty__hint">Ce match n'est pas encore defini (TBD).</div>
                <?php elseif ($pickbanBlockingReport): ?>
                    <div class="empty__hint">
                        Pick/Ban obligatoire avant le debut du match. Complete le pick/ban ci-dessus, puis reporte le score.
                    </div>
                <?php elseif (!$canReport): ?>
                    <div class="empty__hint">
                        Seuls les joueurs (solo) ou les capitaines (team) peuvent reporter ce match.
                    </div>
                <?php else: ?>
                    <form method="post" action="/tournaments/<?= (int)$tid ?>/matches/<?= (int)$mid ?>/report" class="split" style="gap: 10px; align-items: flex-end;" data-ajax="1">
                        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                        <div>
                            <label class="field__label" for="score1">Score A</label>
                            <input class="input" id="score1" type="number" name="score1" min="0" max="99" step="1" inputmode="numeric" value="<?= (int)$reportScore1 ?>" required>
                        </div>
                        <div>
                            <label class="field__label" for="score2">Score B</label>
                            <input class="input" id="score2" type="number" name="score2" min="0" max="99" step="1" inputmode="numeric" value="<?= (int)$reportScore2 ?>" required>
                        </div>
                        <div>
                            <label class="field__label" for="winner_slot">Winner</label>
                            <select class="select" id="winner_slot" name="winner_slot" required>
                                <option value="" <?= $reportWinnerSlot === '' ? 'selected' : '' ?>>Choisir...</option>
                                <option value="1" <?= $reportWinnerSlot === '1' ? 'selected' : '' ?>>A</option>
                                <option value="2" <?= $reportWinnerSlot === '2' ? 'selected' : '' ?>>B</option>
                            </select>
                        </div>
                        <div>
                            <button class="btn btn--primary" type="submit">Reporter</button>
                        </div>
                    </form>
                    <div class="muted" style="margin-top: 10px;">
                        Astuce: si c'est un score BO (ex BO3), indique les manches gagnees (ex: 2-1) pas les rounds.
                    </div>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>
</div>
