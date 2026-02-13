<?php

declare(strict_types=1);

use DuelDesk\View;
use DuelDesk\Support\Auth;

/** @var array<string, mixed> $tournament */
/** @var list<array<string, mixed>> $players */
/** @var list<array<string, mixed>> $teams */
/** @var array<int, list<array{user_id:int,username:string,role:string}>> $teamMembers */
/** @var bool $isSignedUp */
/** @var int|null $mePlayerId */
/** @var array<string, mixed>|null $meTeam */
/** @var list<array<string, mixed>> $matches */
/** @var bool $isPublicView */
/** @var string $csrfToken */

$isPublicView = (bool)($isPublicView ?? false);

$tid = (int)($tournament['id'] ?? 0);
$slug = (string)($tournament['slug'] ?? '');
$publicPath = $slug !== '' ? ('/t/' . $slug) : ('/tournaments/' . $tid);
$participantType = (string)($tournament['participant_type'] ?? 'solo');
$teamSize = (int)($tournament['team_size'] ?? 0);
$format = (string)($tournament['format'] ?? 'single_elim');

$status = (string)($tournament['status'] ?? 'draft');
$isOpenStatus = in_array($status, ['published', 'running'], true);
$entrantCount = $participantType === 'team' ? count($teams) : count($players);

$maxEntrantsRaw = $tournament['max_entrants'] ?? null;
$maxEntrants = $maxEntrantsRaw !== null ? (int)$maxEntrantsRaw : 0;
$isFull = $maxEntrants > 0 && $entrantCount >= $maxEntrants;

$signupClosesAt = (string)($tournament['signup_closes_at'] ?? '');
$signupClosesAtPretty = '';
if ($signupClosesAt !== '') {
    $v = substr($signupClosesAt, 0, 16);
    $signupClosesAtPretty = ($v === false ? $signupClosesAt : $v) . ' UTC';
}
$signupClosed = false;
if ($signupClosesAt !== '') {
    $ts = strtotime($signupClosesAt);
    if ($ts !== false && $ts <= time()) {
        $signupClosed = true;
    }
}

$bracketGenerated = $matches !== [];

$isSignupOpen = $isOpenStatus && !$bracketGenerated && !$signupClosed && !$isFull;
$signupState = $isSignupOpen ? 'ouvertes' : 'fermees';
$signupHint = '';
if (!$isOpenStatus) {
    $signupHint = 'statut: ' . $status;
} elseif ($bracketGenerated) {
    $signupHint = 'bracket genere';
} elseif ($signupClosed) {
    $signupHint = 'date limite depassee';
} elseif ($isFull) {
    $signupHint = $maxEntrants > 0 ? "complet {$entrantCount}/{$maxEntrants}" : 'complet';
} elseif ($signupClosesAt !== '') {
    $signupHint = 'jusqu\'au ' . ($signupClosesAtPretty !== '' ? $signupClosesAtPretty : $signupClosesAt);
}

// Capabilities (admin bypasses locks).
$canSignupSolo = Auth::isAdmin() || ($isOpenStatus && !$bracketGenerated && !$signupClosed && !$isFull);
$canWithdrawSolo = Auth::isAdmin() || (!$bracketGenerated && !$signupClosed);
$canCreateTeam = Auth::isAdmin() || ($isOpenStatus && !$bracketGenerated && !$signupClosed && !$isFull);
$canJoinTeam = Auth::isAdmin() || ($isOpenStatus && !$bracketGenerated && !$signupClosed);
$canLeaveTeam = Auth::isAdmin() || (!$bracketGenerated && !$signupClosed);

/**
 * @param list<array{user_id:int,username:string,role:string}> $members
 */
function format_members(array $members): string
{
    if ($members === []) {
        return '-';
    }

    $names = [];
    foreach ($members as $m) {
        $name = (string)($m['username'] ?? '');
        if ($name === '') {
            continue;
        }
        $names[] = ($m['role'] ?? '') === 'captain' ? ($name . ' (c)') : $name;
    }

    return $names !== [] ? implode(', ', $names) : '-';
}

/** @return int */
function pow2(int $exp): int
{
    if ($exp <= 0) {
        return 1;
    }
    return 2 ** $exp;
}

/**
 * @param array<string, mixed> $m
 * @param 'solo'|'team' $participantType
 */
function render_matchcard(array $m, string $participantType, string $tag, ?int $col = null, ?int $rowStart = null, ?int $rowSpan = null): void
{
    $matchId = (int)($m['id'] ?? 0);
    $tournamentId = (int)($m['tournament_id'] ?? 0);
    $bracket = (string)($m['bracket'] ?? 'winners');
    $round = (int)($m['round'] ?? 0);
    $roundPos = (int)($m['round_pos'] ?? 0);
    $st = (string)($m['status'] ?? 'pending');
    $bestOf = (int)($m['best_of'] ?? 0);
    $score1 = (int)($m['score1'] ?? 0);
    $score2 = (int)($m['score2'] ?? 0);
    $scheduledAt = is_string($m['scheduled_at'] ?? null) ? (string)$m['scheduled_at'] : '';
    $reportedScore1 = $m['reported_score1'] ?? null;
    $reportedScore2 = $m['reported_score2'] ?? null;
    $reportedWinnerSlot = $m['reported_winner_slot'] ?? null;
    $reportedByUsername = (string)($m['reported_by_username'] ?? '');
    $reportedAt = is_string($m['reported_at'] ?? null) ? (string)$m['reported_at'] : '';
    $counterScore1 = $m['counter_reported_score1'] ?? null;
    $counterScore2 = $m['counter_reported_score2'] ?? null;
    $counterWinnerSlot = $m['counter_reported_winner_slot'] ?? null;
    $counterByUsername = (string)($m['counter_reported_by_username'] ?? '');
    $counterAt = is_string($m['counter_reported_at'] ?? null) ? (string)$m['counter_reported_at'] : '';

    if ($participantType === 'team') {
        $aId = $m['team1_id'] !== null ? (int)$m['team1_id'] : null;
        $bId = $m['team2_id'] !== null ? (int)$m['team2_id'] : null;
        $aName = (string)($m['t1_name'] ?? '');
        $bName = (string)($m['t2_name'] ?? '');
        $win = $m['winner_team_id'] !== null ? (int)$m['winner_team_id'] : null;
    } else {
        $aId = $m['player1_id'] !== null ? (int)$m['player1_id'] : null;
        $bId = $m['player2_id'] !== null ? (int)$m['player2_id'] : null;
        $aName = (string)($m['p1_name'] ?? '');
        $bName = (string)($m['p2_name'] ?? '');
        $win = $m['winner_id'] !== null ? (int)$m['winner_id'] : null;
    }

    $aLabel = $aId === null
        ? (($bId !== null && $win !== null && $win === $bId) ? 'BYE' : 'TBD')
        : ($aName !== '' ? $aName : '#');
    $bLabel = $bId === null
        ? (($aId !== null && $win !== null && $win === $aId) ? 'BYE' : 'TBD')
        : ($bName !== '' ? $bName : '#');

    $aWin = $win !== null && $aId !== null && $win === $aId;
    $bWin = $win !== null && $bId !== null && $win === $bId;
    $winnerSlot = $aWin ? 1 : ($bWin ? 2 : 0);

    $isReported = in_array($st, ['reported', 'disputed'], true)
        && ($aId !== null) && ($bId !== null)
        && ($reportedScore1 !== null) && ($reportedScore2 !== null);
    $showScores = (($st === 'confirmed') && ($aId !== null) && ($bId !== null)) || $isReported;
    $s1 = $showScores ? ($isReported ? (string)(int)$reportedScore1 : (string)$score1) : '-';
    $s2 = $showScores ? ($isReported ? (string)(int)$reportedScore2 : (string)$score2) : '-';

    $style = '';
    if ($col !== null && $rowStart !== null && $rowSpan !== null) {
        $style = "grid-column: {$col}; grid-row: {$rowStart} / span {$rowSpan};";
    }

    $href = $tournamentId > 0 && $matchId > 0 ? ("/tournaments/{$tournamentId}/matches/{$matchId}") : '#';
    ?>
    <a
        href="<?= View::e($href) ?>"
        class="matchcard matchcard--clickable<?= $st === 'confirmed' ? ' is-confirmed' : '' ?><?= $st === 'reported' ? ' is-reported' : '' ?><?= $st === 'disputed' ? ' is-disputed' : '' ?>"
        <?= $style !== '' ? ' style="' . View::e($style) . '"' : '' ?>
        data-match-id="<?= (int)$matchId ?>"
        data-key="<?= View::e($bracket . ':' . $round . ':' . $roundPos) ?>"
        data-bracket="<?= View::e($bracket) ?>"
        data-round="<?= (int)$round ?>"
        data-pos="<?= (int)$roundPos ?>"
        data-tag="<?= View::e($tag) ?>"
        data-status="<?= View::e($st) ?>"
        data-best-of="<?= (int)$bestOf ?>"
        data-scheduled-at="<?= View::e($scheduledAt) ?>"
        data-reported-score1="<?= $reportedScore1 !== null ? (int)$reportedScore1 : '' ?>"
        data-reported-score2="<?= $reportedScore2 !== null ? (int)$reportedScore2 : '' ?>"
        data-reported-winner-slot="<?= $reportedWinnerSlot !== null ? (int)$reportedWinnerSlot : '' ?>"
        data-reported-by="<?= View::e($reportedByUsername) ?>"
        data-reported-at="<?= View::e($reportedAt) ?>"
        data-counter-reported-score1="<?= $counterScore1 !== null ? (int)$counterScore1 : '' ?>"
        data-counter-reported-score2="<?= $counterScore2 !== null ? (int)$counterScore2 : '' ?>"
        data-counter-reported-winner-slot="<?= $counterWinnerSlot !== null ? (int)$counterWinnerSlot : '' ?>"
        data-counter-reported-by="<?= View::e($counterByUsername) ?>"
        data-counter-reported-at="<?= View::e($counterAt) ?>"
        data-a-name="<?= View::e($aLabel) ?>"
        data-b-name="<?= View::e($bLabel) ?>"
        data-score1="<?= (int)$score1 ?>"
        data-score2="<?= (int)$score2 ?>"
        data-winner-slot="<?= (int)$winnerSlot ?>"
        aria-label="<?= View::e("{$tag} {$aLabel} vs {$bLabel}") ?>"
    >
        <div class="matchcard__slot<?= $aId === null ? ' is-empty' : '' ?><?= $aWin ? ' is-winner' : '' ?>">
            <span class="matchcard__name"><?= View::e($aLabel) ?></span>
            <span class="matchcard__score"><?= View::e($s1) ?></span>
        </div>
        <div class="matchcard__slot<?= $bId === null ? ' is-empty' : '' ?><?= $bWin ? ' is-winner' : '' ?>">
            <span class="matchcard__name"><?= View::e($bLabel) ?></span>
            <span class="matchcard__score"><?= View::e($s2) ?></span>
        </div>
        <div class="matchcard__tag"><?= View::e($tag) ?></div>
    </a>
    <?php
}
?>

<div class="pagehead">
    <div>
        <h1 class="pagehead__title"><?= View::e((string)($tournament['name'] ?? 'Tournoi')) ?></h1>
        <p class="pagehead__lead">
            <?php if (!empty($tournament['game_image_path'])): ?>
                <img class="gameicon" src="<?= View::e((string)$tournament['game_image_path']) ?>" alt="" loading="lazy" width="24" height="24">
            <?php endif; ?>
            <?php $bracketPill = $bracketGenerated ? 'bracket: genere' : 'bracket: en attente'; ?>
            <?= View::e((string)($tournament['game'] ?? '')) ?> · <span class="pill"><?= View::e((string)($tournament['format'] ?? '')) ?></span> <span class="pill pill--soft"><?= View::e((string)($tournament['status'] ?? '')) ?></span> <span class="pill pill--soft"><?= View::e($bracketPill) ?></span>
        </p>
    </div>
    <div class="pagehead__actions">
        <a class="btn btn--ghost" href="<?= View::e($publicPath) ?>" title="Lien public partageable">Lien public</a>
        <button class="btn btn--ghost" type="button" data-copy="<?= View::e($publicPath) ?>" title="Copier le lien du tournoi">Copier lien</button>
        <?php if (Auth::isAdmin() && !$isPublicView): ?>
            <a class="btn btn--ghost" href="/admin/tournaments/<?= (int)$tournament['id'] ?>">Gerer</a>
        <?php endif; ?>
        <a class="btn btn--ghost" href="/tournaments">Retour</a>
    </div>
</div>

<?php $tabRoster = $participantType === 'team' ? 'Equipes' : 'Inscriptions'; ?>

<div class="paneltabs" role="tablist" aria-label="Sections du tournoi" data-tournament-tabs>
    <button class="paneltabs__tab is-active" type="button" role="tab" id="tab-registrations" aria-controls="panel-registrations" aria-selected="true" data-tab="registrations"><?= View::e($tabRoster) ?></button>
    <button class="paneltabs__tab" type="button" role="tab" id="tab-bracket" aria-controls="panel-bracket" aria-selected="false" data-tab="bracket">Bracket</button>
    <button class="paneltabs__tab" type="button" role="tab" id="tab-details" aria-controls="panel-details" aria-selected="false" data-tab="details">Details</button>
</div>

<noscript>
    <style>
        [data-tpanel][hidden] { display: block !important; }
    </style>
</noscript>

<section class="card tpanel" id="panel-registrations" role="tabpanel" aria-labelledby="tab-registrations" tabindex="0" data-tpanel="registrations">
    <div class="card__header">
        <div>
            <h2 class="card__title"><?= $participantType === 'team' ? 'Equipes' : 'Inscriptions' ?></h2>
            <p class="card__subtitle">
                <?php if ($participantType === 'team'): ?>
                    <?= count($teams) ?> equipe(s)<?= $teamSize >= 2 ? ' · ' . (int)$teamSize . ' par equipe' : '' ?>
                <?php else: ?>
                    <?= count($players) ?> participant(s)
                <?php endif; ?>
                <span class="meta__dot" aria-hidden="true"></span>
                <span class="muted">inscriptions <?= View::e($signupState) ?></span>
                <?php if ($signupHint !== ''): ?>
                    <span class="meta__dot" aria-hidden="true"></span>
                    <span class="muted"><?= View::e($signupHint) ?></span>
                <?php endif; ?>
            </p>
        </div>

        <div class="inline">
            <?php if (!Auth::check()): ?>
                <a class="btn btn--primary" href="/login?redirect=<?= View::e(urlencode($publicPath)) ?>">Se connecter</a>
            <?php else: ?>
                <?php if ($participantType === 'team'): ?>
                    <?php if ($isSignedUp && is_array($meTeam)): ?>
                        <?php if ($canLeaveTeam): ?>
                            <form method="post" action="/tournaments/<?= $tid ?>/teams/<?= (int)$meTeam['id'] ?>/leave" class="inline" data-confirm="Quitter ton equipe ?">
                                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                <button class="btn btn--ghost" type="submit">Quitter</button>
                            </form>
                        <?php else: ?>
                            <span class="muted">Retrait bloque</span>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php else: ?>
                    <?php if ($isSignedUp): ?>
                        <?php if ($canWithdrawSolo): ?>
                            <form method="post" action="/tournaments/<?= $tid ?>/withdraw" class="inline">
                                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                <button class="btn btn--ghost" type="submit">Se desinscrire</button>
                            </form>
                        <?php else: ?>
                            <span class="muted">Retrait bloque</span>
                        <?php endif; ?>
                    <?php elseif ($canSignupSolo): ?>
                        <form method="post" action="/tournaments/<?= $tid ?>/signup" class="inline">
                            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                            <button class="btn btn--primary" type="submit">S'inscrire</button>
                        </form>
                    <?php else: ?>
                        <?php
                            $msg = 'Inscriptions fermees';
                            if ($isFull) {
                                $msg = 'Tournoi complet';
                            } elseif ($bracketGenerated) {
                                $msg = 'Inscriptions verrouillees';
                            } elseif ($signupClosed) {
                                $msg = 'Inscriptions fermees';
                            }
                        ?>
                        <span class="muted"><?= View::e($msg) ?></span>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="card__body">
        <?php if ($participantType === 'team'): ?>
            <?php if (Auth::check() && !$isSignedUp && ($canCreateTeam || $canJoinTeam)): ?>
                <?php $wrapClass = ($canCreateTeam && $canJoinTeam) ? 'split' : ''; ?>
                <div class="<?= View::e($wrapClass) ?>">
                    <?php if ($canCreateTeam): ?>
                        <form class="card card--nested form" method="post" action="/tournaments/<?= $tid ?>/teams/create" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                            <div class="card__header">
                                <h3 class="card__title">Creer une equipe</h3>
                                <p class="card__subtitle">Tu deviens capitaine.</p>
                            </div>
                            <div class="card__body">
                                <label class="field">
                                    <span class="field__label">Nom</span>
                                    <input class="input" name="team_name" placeholder="Ex: Night Owls" required maxlength="80">
                                </label>
                            </div>
                            <div class="card__footer">
                                <button class="btn btn--primary" type="submit">Creer</button>
                            </div>
                        </form>
                    <?php endif; ?>

                    <?php if ($canJoinTeam): ?>
                        <form class="card card--nested form" method="post" action="/tournaments/<?= $tid ?>/teams/join" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                            <div class="card__header">
                                <h3 class="card__title">Rejoindre</h3>
                                <p class="card__subtitle">Avec un code.</p>
                            </div>
                            <div class="card__body">
                                <label class="field">
                                    <span class="field__label">Code</span>
                                    <input class="input mono" name="join_code" placeholder="Ex: 8KQ7M2ZP4A" required maxlength="16">
                                </label>
                            </div>
                            <div class="card__footer">
                                <button class="btn btn--ghost" type="submit">Rejoindre</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            <?php elseif (Auth::check() && $isSignedUp && is_array($meTeam)): ?>
                <div class="empty empty--compact">
                    <div class="empty__title">Ton equipe: <a class="link" href="/teams/<?= (int)($meTeam['id'] ?? 0) ?>"><?= View::e((string)$meTeam['name']) ?></a></div>
                    <div class="empty__hint">
                        Code: <span class="mono"><?= View::e((string)$meTeam['join_code']) ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($teams === []): ?>
                <div class="empty empty--compact">
                    <div class="empty__title">Aucune equipe</div>
                    <div class="empty__hint">
                        <?php if ($isSignupOpen): ?>
                            Cree une equipe ou rejoins-en une avec un code.
                        <?php else: ?>
                            Inscriptions <?= View::e($signupState) ?><?= $signupHint !== '' ? ' · ' . View::e($signupHint) : '' ?>.
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="tablewrap" style="margin-top: 14px;">
                    <table class="table table--compact">
                        <thead>
                            <tr>
                                <th>Equipe</th>
                                <th>Membres</th>
                                <th>Seed</th>
                                <th>Check-in</th>
                                <th>Inscrit le</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($teams as $t): ?>
                                <?php
                                    $teamId = (int)($t['team_id'] ?? 0);
                                    $members = $teamMembers[$teamId] ?? [];
                                    $isMine = is_array($meTeam) && (int)($meTeam['id'] ?? 0) === $teamId;
                                ?>
                                <tr class="<?= $isMine ? 'row--me' : '' ?>">
                                    <td class="table__strong">
                                        <a class="link" href="/teams/<?= (int)$teamId ?>"><?= View::e((string)($t['name'] ?? '')) ?></a>
                                        <?php if ($isMine): ?><span class="pill pill--soft">toi</span><?php endif; ?>
                                    </td>
                                    <td><?= View::e(format_members($members)) ?></td>
                                    <td><?= $t['seed'] !== null ? (int)$t['seed'] : '<span class="muted">-</span>' ?></td>
                                    <td><?= (int)($t['checked_in'] ?? 0) === 1 ? '<span class="pill">OK</span>' : '<span class="pill pill--soft">non</span>' ?></td>
                                    <td class="mono"><?= View::e((string)($t['joined_at'] ?? '')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <?php if ($players === []): ?>
                <div class="empty empty--compact">
                    <div class="empty__title">Aucun inscrit</div>
                    <div class="empty__hint">
                        <?php if ($isSignupOpen): ?>
                            Les joueurs peuvent s'inscrire depuis cette page.
                        <?php else: ?>
                            Inscriptions <?= View::e($signupState) ?><?= $signupHint !== '' ? ' · ' . View::e($signupHint) : '' ?>.
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="tablewrap">
                    <table class="table table--compact">
                        <thead>
                            <tr>
                                <th>Joueur</th>
                                <th>Seed</th>
                                <th>Check-in</th>
                                <th>Inscrit le</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($players as $p): ?>
                                <?php $isMe = $mePlayerId !== null && (int)$p['player_id'] === (int)$mePlayerId; ?>
                                <tr class="<?= $isMe ? 'row--me' : '' ?>">
                                    <td class="table__strong">
                                        <?= View::e((string)$p['handle']) ?>
                                        <?php if ($isMe): ?><span class="pill pill--soft">toi</span><?php endif; ?>
                                    </td>
                                    <td><?= $p['seed'] !== null ? (int)$p['seed'] : '<span class="muted">-</span>' ?></td>
                                    <td><?= (int)($p['checked_in'] ?? 0) === 1 ? '<span class="pill">OK</span>' : '<span class="pill pill--soft">non</span>' ?></td>
                                    <td class="mono"><?= View::e((string)$p['joined_at']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<section class="card tpanel" id="panel-bracket" role="tabpanel" aria-labelledby="tab-bracket" tabindex="0" data-tpanel="bracket" hidden>
    <div class="card__header">
        <div>
            <h2 class="card__title">Bracket</h2>
            <p class="card__subtitle">
                <?php if ($format === 'double_elim'): ?>
                    Double elimination
                <?php elseif ($format === 'round_robin'): ?>
                    Round robin
                <?php else: ?>
                    Simple elimination
                <?php endif; ?>
            </p>
        </div>
        <div class="inline">
            <?php if ($format === 'double_elim' && $matches !== []): ?>
                <button class="btn btn--ghost btn--compact" type="button" data-toggle-drop-lines aria-pressed="false">Afficher drop lines</button>
            <?php endif; ?>
            <?php if ($format !== 'round_robin' && $matches !== []): ?>
                <button class="btn btn--ghost btn--compact" type="button" data-bracket-zoom="out" title="Zoom out">-</button>
                <button class="btn btn--ghost btn--compact" type="button" data-bracket-zoom="in" title="Zoom in">+</button>
                <button class="btn btn--ghost btn--compact" type="button" data-bracket-zoom="reset" title="Zoom 100%">100%</button>
                <button class="btn btn--ghost btn--compact" type="button" data-bracket-zoom="fit" title="Fit to view">Fit</button>
                <button class="btn btn--ghost btn--compact" type="button" data-bracket-zoom="center" title="Center">Center</button>
                <button class="btn btn--ghost btn--compact" type="button" data-bracket-zoom="current" title="Center sur le round en cours">Round</button>
                <span class="pill pill--soft mono" data-bracket-zoom-label>100%</span>
                <span class="meta__dot" aria-hidden="true"></span>
                <button class="btn btn--ghost btn--compact" type="button" data-bracket-export="svg" title="Exporter en SVG">SVG</button>
                <button class="btn btn--ghost btn--compact" type="button" data-bracket-export="pdf" title="Imprimer / PDF">PDF</button>
            <?php endif; ?>
            <?php if (Auth::isAdmin() && !$isPublicView): ?>
                <a class="btn btn--ghost" href="/admin/tournaments/<?= $tid ?>">Admin bracket</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="card__body">
        <?php if ($format === 'round_robin'): ?>
            <?php
                $rr = [];
                foreach ($matches as $m) {
                    if (($m['bracket'] ?? '') !== 'round_robin') {
                        continue;
                    }
                    $r = (int)($m['round'] ?? 0);
                    $p = (int)($m['round_pos'] ?? 0);
                    if ($r <= 0 || $p <= 0) {
                        continue;
                    }
                    $rr[$r][$p] = $m;
                }
                ksort($rr);
                foreach ($rr as $r => $list) {
                    ksort($rr[$r]);
                }

                // Participants map: id -> display name.
                $pmap = [];
                if ($participantType === 'team') {
                    foreach ($teams as $t) {
                        $id = (int)($t['team_id'] ?? 0);
                        if ($id <= 0) {
                            continue;
                        }
                        $name = (string)($t['name'] ?? ('#' . $id));
                        $pmap[$id] = $name;
                    }
                } else {
                    foreach ($players as $p) {
                        $id = (int)($p['player_id'] ?? 0);
                        if ($id <= 0) {
                            continue;
                        }
                        $name = (string)($p['handle'] ?? ('#' . $id));
                        $pmap[$id] = $name;
                    }
                }

                /** @var array<int, array{id:int,name:string,played:int,wins:int,losses:int,pf:int,pa:int}> $stats */
                $stats = [];
                foreach ($pmap as $id => $name) {
                    $stats[$id] = [
                        'id' => (int)$id,
                        'name' => (string)$name,
                        'played' => 0,
                        'wins' => 0,
                        'losses' => 0,
                        'pf' => 0,
                        'pa' => 0,
                    ];
                }

                foreach ($rr as $r => $list) {
                    foreach ($list as $p => $m) {
                        if (($m['status'] ?? 'pending') !== 'confirmed') {
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

                        $s1 = (int)($m['score1'] ?? 0);
                        $s2 = (int)($m['score2'] ?? 0);

                        if (!isset($stats[$a])) {
                            $stats[$a] = ['id' => $a, 'name' => (string)($pmap[$a] ?? ('#' . $a)), 'played' => 0, 'wins' => 0, 'losses' => 0, 'pf' => 0, 'pa' => 0];
                        }
                        if (!isset($stats[$b])) {
                            $stats[$b] = ['id' => $b, 'name' => (string)($pmap[$b] ?? ('#' . $b)), 'played' => 0, 'wins' => 0, 'losses' => 0, 'pf' => 0, 'pa' => 0];
                        }

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
            ?>

            <?php if ($rr === []): ?>
                <div class="empty empty--compact">
                    <div class="empty__title">Calendrier non genere</div>
                    <div class="empty__hint">Un admin doit generer le round robin depuis la page de gestion.</div>
                </div>
            <?php else: ?>
                <div class="bracketview rrview" data-participant-type="<?= View::e($participantType) ?>">
                    <div class="bracketview__scroll">
                        <div class="rrmatrix">
                            <section class="card card--nested rrstandings">
                                <div class="card__header">
                                    <div>
                                        <h3 class="card__title">Classement</h3>
                                        <p class="card__subtitle">Trie: W desc, L asc, diff desc.</p>
                                    </div>
                                </div>
                                <div class="card__body">
                                    <div class="tablewrap">
                                        <table class="table table--compact">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th><?= $participantType === 'team' ? 'Equipe' : 'Joueur' ?></th>
                                                    <th class="table__right">P</th>
                                                    <th class="table__right">W</th>
                                                    <th class="table__right">L</th>
                                                    <th class="table__right">Diff</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php $rank = 1; ?>
                                                <?php foreach ($standings as $row): ?>
                                                    <?php $diff = (int)($row['pf'] ?? 0) - (int)($row['pa'] ?? 0); ?>
                                                    <tr>
                                                        <td class="mono"><?= (int)$rank ?></td>
                                                        <td class="table__strong"><?= View::e((string)($row['name'] ?? '')) ?></td>
                                                        <td class="mono table__right"><?= (int)($row['played'] ?? 0) ?></td>
                                                        <td class="mono table__right"><?= (int)($row['wins'] ?? 0) ?></td>
                                                        <td class="mono table__right"><?= (int)($row['losses'] ?? 0) ?></td>
                                                        <td class="mono table__right"><?= $diff >= 0 ? '+' . $diff : (string)$diff ?></td>
                                                    </tr>
                                                    <?php $rank++; ?>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </section>

                            <div class="rrrounds">
                                <?php foreach ($rr as $r => $list): ?>
                                    <div class="rrround">
                                        <div class="rrround__head">Round <?= (int)$r ?></div>
                                        <div class="rrround__list">
                                            <?php foreach ($list as $p => $m): ?>
                                                <?php render_matchcard($m, $participantType, "RR{$r}#{$p}"); ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php elseif ($matches === []): ?>
            <div class="empty empty--compact">
                <div class="empty__title">Bracket non genere</div>
                <div class="empty__hint">Un admin doit generer le bracket depuis la page de gestion.</div>
            </div>
        <?php else: ?>
            <?php
                $winners = [];
                $losers = [];
                $grand1 = null;
                $grand2 = null;
                $wRounds = 0;

                foreach ($matches as $m) {
                    $b = (string)($m['bracket'] ?? 'winners');
                    $r = (int)($m['round'] ?? 0);
                    $p = (int)($m['round_pos'] ?? 0);
                    if ($b === 'grand') {
                        if ($r === 1) {
                            $grand1 = $m;
                        } elseif ($r === 2) {
                            $grand2 = $m;
                        }
                        continue;
                    }
                    if ($r <= 0 || $p <= 0) {
                        continue;
                    }

                    if ($b === 'losers') {
                        $losers[$r][$p] = $m;
                    } else {
                        $winners[$r][$p] = $m;
                        if ($r > $wRounds) {
                            $wRounds = $r;
                        }
                    }
                }

                $bracketSize = $wRounds > 0 ? pow2($wRounds) : 0;
                $losersRounds = $format === 'double_elim' ? ((2 * $wRounds) - 2) : 0;
                if ($losersRounds < 0) {
                    $losersRounds = 0;
                }
            ?>

            <div class="bracketview" data-format="<?= View::e($format) ?>" data-participant-type="<?= View::e($participantType) ?>">
                <div class="bracketview__scroll">
                    <?php if ($format === 'double_elim' && $wRounds > 0 && $bracketSize > 0 && $losersRounds > 0): ?>
                        <div class="bracketview__matrix">
                            <svg class="bracketlines" aria-hidden="true"></svg>
                            <div class="bracketview__main">
                                <div>
                                    <div class="bracketsection__head">Bracket gagnants</div>
                                    <div class="bracketrail" style="--rail-cols: <?= (int)$wRounds ?>;">
                                        <div class="bracketgrid" style="--cols: <?= (int)$wRounds ?>; --rows: <?= (int)$bracketSize ?>;">
                                            <?php for ($r = 1; $r <= $wRounds; $r++): ?>
                                                <?php $matchesInRound = (int)($bracketSize / pow2($r)); ?>
                                                <?php for ($p = 1; $p <= $matchesInRound; $p++): ?>
                                                    <?php
                                                        $m = $winners[$r][$p] ?? null;
                                                        if (!is_array($m)) {
                                                            continue;
                                                        }
                                                        $span = pow2($r);
                                                        $rowStart = (($p - 1) * $span) + 1;
                                                        render_matchcard($m, $participantType, "W{$r}#{$p}", $r, $rowStart, $span);
                                                    ?>
                                                <?php endfor; ?>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <div class="bracketsection__head">Bracket perdants</div>
                                    <div class="bracketrail" style="--rail-cols: <?= (int)$losersRounds ?>;">
                                        <div class="bracketgrid" style="--cols: <?= (int)$losersRounds ?>; --rows: <?= (int)$bracketSize ?>;">
                                            <?php for ($lr = 1; $lr <= $losersRounds; $lr++): ?>
                                                <?php
                                                    $phase = intdiv($lr + 1, 2); // ceil(lr/2)
                                                    $exp = $phase + 1;
                                                    $span = pow2($exp);
                                                    $matchesInRound = $span > 0 ? (int)($bracketSize / $span) : 0;
                                                ?>
                                                <?php for ($p = 1; $p <= $matchesInRound; $p++): ?>
                                                    <?php
                                                        $m = $losers[$lr][$p] ?? null;
                                                        if (!is_array($m)) {
                                                            continue;
                                                        }
                                                        $rowStart = (($p - 1) * $span) + 1;
                                                        render_matchcard($m, $participantType, "L{$lr}#{$p}", $lr, $rowStart, $span);
                                                    ?>
                                                <?php endfor; ?>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="bracketfinal">
                                <?php $finalCols = is_array($grand2) ? 2 : 1; ?>
                                <div class="bracketfinal__cols" style="--final-cols: <?= (int)$finalCols ?>;">
                                    <div class="bracketfinal__col">
                                        <div class="bracketsection__head">Finale</div>
                                        <?php if (is_array($grand1)): ?>
                                            <?php render_matchcard($grand1, $participantType, 'GF'); ?>
                                        <?php else: ?>
                                            <div class="muted">TBD</div>
                                        <?php endif; ?>
                                    </div>

                                    <?php if (is_array($grand2)): ?>
                                        <div class="bracketfinal__col">
                                            <div class="bracketsection__head">Reset (si besoin)</div>
                                            <?php render_matchcard($grand2, $participantType, 'GF2'); ?>
                                            <div class="muted bracketfinal__note">Joue ce match uniquement si le gagnant du losers bat le gagnant du winners en GF.</div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php elseif ($wRounds > 0 && $bracketSize > 0): ?>
                        <div class="bracketview__matrix">
                            <svg class="bracketlines" aria-hidden="true"></svg>
                            <div class="bracketview__main">
                                <div>
                                    <div class="bracketsection__head">Bracket</div>
                                    <div class="bracketrail" style="--rail-cols: <?= (int)$wRounds ?>;">
                                        <div class="bracketgrid" style="--cols: <?= (int)$wRounds ?>; --rows: <?= (int)$bracketSize ?>;">
                                            <?php for ($r = 1; $r <= $wRounds; $r++): ?>
                                                <?php $matchesInRound = (int)($bracketSize / pow2($r)); ?>
                                                <?php for ($p = 1; $p <= $matchesInRound; $p++): ?>
                                                    <?php
                                                        $m = $winners[$r][$p] ?? null;
                                                        if (!is_array($m)) {
                                                            continue;
                                                        }
                                                        $span = pow2($r);
                                                        $rowStart = (($p - 1) * $span) + 1;
                                                        render_matchcard($m, $participantType, "R{$r}#{$p}", $r, $rowStart, $span);
                                                    ?>
                                                <?php endfor; ?>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="empty empty--compact">
                            <div class="empty__title">Bracket invalide</div>
                            <div class="empty__hint">Reset et regenere le bracket.</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<dialog class="modal" id="matchModal">
    <form method="dialog" class="modal__inner card">
        <div class="modal__header">
            <div>
                <div class="modal__title" id="matchModalTitle">Match</div>
                <div class="modal__subtitle" id="matchModalMeta"></div>
            </div>
            <div class="modal__actions">
                <a class="btn btn--ghost btn--compact" id="matchModalLink" href="#">Ouvrir</a>
                <button class="btn btn--ghost btn--compact" value="close">Fermer</button>
            </div>
        </div>
        <div class="modal__body">
            <div class="modal__row">
                <div class="modal__side">
                    <div class="modal__label" id="matchModalALabel">A</div>
                    <div class="modal__name" id="matchModalAName">-</div>
                </div>
                <div class="modal__score mono" id="matchModalScore">-</div>
                <div class="modal__side modal__side--right">
                    <div class="modal__label" id="matchModalBLabel">B</div>
                    <div class="modal__name" id="matchModalBName">-</div>
                </div>
            </div>
            <div class="modal__small" id="matchModalStatus">-</div>
        </div>
    </form>
</dialog>

<div class="tpanel" id="panel-details" role="tabpanel" aria-labelledby="tab-details" tabindex="0" data-tpanel="details" hidden>
    <div class="split">
        <section class="card">
            <div class="card__header">
                <h2 class="card__title">Details</h2>
                <p class="card__subtitle">Infos de base du tournoi.</p>
            </div>
            <div class="card__body">
                <dl class="dl">
                    <div class="dl__row">
                        <dt>ID</dt>
                        <dd><?= (int)$tournament['id'] ?></dd>
                    </div>
                    <div class="dl__row">
                        <dt>Slug</dt>
                        <dd><span class="mono"><?= View::e((string)$tournament['slug']) ?></span></dd>
                    </div>
                    <div class="dl__row">
                        <dt>Jeu</dt>
                        <dd><?= View::e((string)$tournament['game']) ?></dd>
                    </div>
                <div class="dl__row">
                    <dt>Format</dt>
                    <dd><?= View::e((string)$tournament['format']) ?></dd>
                </div>
                <div class="dl__row">
                    <dt>Best-of</dt>
                    <dd>BO<?= (int)($tournament['best_of_default'] ?? 3) ?></dd>
                </div>
                <div class="dl__row">
                    <dt>Finale</dt>
                    <?php $boFinal = $tournament['best_of_final'] ?? null; ?>
                    <dd>
                        <?php if ($boFinal === null): ?>
                            <span class="muted">defaut</span>
                        <?php else: ?>
                            BO<?= (int)$boFinal ?>
                        <?php endif; ?>
                    </dd>
                </div>
                <div class="dl__row">
                    <dt>Participants</dt>
                    <dd>
                        <?php if ($participantType === 'team'): ?>
                            equipe<?= $teamSize >= 2 ? ' (' . (int)$teamSize . ')' : '' ?>
                        <?php else: ?>
                            solo
                        <?php endif; ?>
                    </dd>
                </div>
                <div class="dl__row">
                    <dt>Max entrants</dt>
                    <dd><?= $maxEntrants > 0 ? View::e("{$entrantCount}/{$maxEntrants}") : '<span class="muted">-</span>' ?></dd>
                </div>
                <div class="dl__row">
                    <dt>Fermeture</dt>
                    <dd><?= $signupClosesAt !== '' ? View::e($signupClosesAtPretty !== '' ? $signupClosesAtPretty : $signupClosesAt) : '<span class="muted">-</span>' ?></dd>
                </div>
                <div class="dl__row">
                    <dt>Lien public</dt>
                    <dd><a class="link mono" href="<?= View::e($publicPath) ?>"><?= View::e($publicPath) ?></a></dd>
                </div>
                <div class="dl__row">
                    <dt>Timezone</dt>
                    <dd><span class="mono">UTC</span></dd>
                </div>
                <div class="dl__row">
                    <dt>Statut</dt>
                    <dd><?= View::e((string)$tournament['status']) ?></dd>
                </div>
                    <div class="dl__row">
                        <dt>Debut</dt>
                        <?php
                            $startsAt = is_string($tournament['starts_at'] ?? null) ? (string)$tournament['starts_at'] : '';
                            $startsAtPretty = '';
                            if ($startsAt !== '') {
                                $v = substr($startsAt, 0, 16);
                                $startsAtPretty = ($v === false ? $startsAt : $v) . ' UTC';
                            }
                        ?>
                        <dd><?= $startsAt !== '' ? View::e($startsAtPretty !== '' ? $startsAtPretty : $startsAt) : '<span class="muted">-</span>' ?></dd>
                    </div>
                    <div class="dl__row">
                        <dt>Cree le</dt>
                        <dd><?= View::e((string)$tournament['created_at']) ?></dd>
                    </div>
                </dl>
            </div>
        </section>

        <section class="card">
            <div class="card__header">
                <h2 class="card__title">Prochaines etapes</h2>
                <p class="card__subtitle">On complete ensuite l'outil.</p>
            </div>
            <div class="card__body">
                <ul class="checklist">
                    <li>Inscriptions (solo ou equipes)</li>
                    <li>Generation du bracket (single/double elim, round robin)</li>
                    <li>Reporting + validation des scores</li>
                    <li>Vue publique partageable</li>
                    <li>Connexion Discord (roles + check-in)</li>
                </ul>
            </div>
            <div class="card__footer">
                <?php if (Auth::isAdmin() && !$isPublicView): ?>
                    <a class="btn btn--primary" href="/tournaments/new" title="Creer un autre tournoi">Nouveau tournoi</a>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>
