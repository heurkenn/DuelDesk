<?php

declare(strict_types=1);

use DuelDesk\View;

/** @var array<string, mixed> $tournament */
/** @var list<array<string, mixed>> $players */
/** @var list<array<string, mixed>> $teams */
/** @var array<int, list<array{user_id:int,username:string,role:string}>> $teamMembers */
/** @var string $csrfToken */
/** @var string $startsAtValue */
/** @var int $matchCount */
/** @var bool $canGenerateBracket */
/** @var list<string> $incompleteTeams */
/** @var list<array<string, mixed>> $matches */

$tid = (int)($tournament['id'] ?? 0);
$participantType = (string)($tournament['participant_type'] ?? 'solo');
$teamSize = (int)($tournament['team_size'] ?? 0);
$status = (string)($tournament['status'] ?? 'draft');
$isOpen = in_array($status, ['published', 'running'], true);
$format = (string)($tournament['format'] ?? 'single_elim');

/**
 * @param list<array{user_id:int,username:string,role:string}> $members
 */
function format_members_admin(array $members): string
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
?>

<div class="pagehead">
    <div>
        <h1 class="pagehead__title">Gerer le tournoi</h1>
        <p class="pagehead__lead">
            <?php if (!empty($tournament['game_image_path'])): ?>
                <img class="gameicon" src="<?= View::e((string)$tournament['game_image_path']) ?>" alt="" loading="lazy" width="22" height="22">
            <?php endif; ?>
            <span class="table__strong"><?= View::e((string)($tournament['name'] ?? '')) ?></span>
            <span class="meta__dot" aria-hidden="true"></span>
            <?= View::e((string)($tournament['game'] ?? '')) ?>
            <span class="meta__dot" aria-hidden="true"></span>
            <span class="pill"><?= View::e((string)($tournament['format'] ?? '')) ?></span>
            <span class="pill pill--soft"><?= View::e($status) ?></span>
        </p>
    </div>
    <div class="pagehead__actions">
        <a class="btn btn--ghost" href="/tournaments/<?= $tid ?>">Voir la page</a>
        <a class="btn btn--ghost" href="/admin">Retour admin</a>
    </div>
	</div>

	<div class="split">
	    <section class="card">
        <div class="card__header">
            <div>
                <h2 class="card__title">Parametres</h2>
                <p class="card__subtitle">Les inscriptions sont ouvertes en <span class="mono">published</span> / <span class="mono">running</span>.</p>
            </div>
        </div>

        <form class="card__body form" method="post" action="/admin/tournaments/<?= $tid ?>/settings" novalidate>
            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">

            <div class="form__grid">
                <label class="field">
                    <span class="field__label">Statut</span>
                    <select class="select" name="status">
                        <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>draft</option>
                        <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>published</option>
                        <option value="running" <?= $status === 'running' ? 'selected' : '' ?>>running</option>
                        <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>completed</option>
                    </select>
                </label>

                <label class="field">
                    <span class="field__label">Debut (optionnel)</span>
                    <input class="input" type="datetime-local" name="starts_at" value="<?= View::e($startsAtValue) ?>">
                </label>
            </div>

            <div class="card__footer">
                <button class="btn btn--primary" type="submit">Enregistrer</button>
            </div>
        </form>
	    </section>

	    <section class="card">
	        <div class="card__header">
	            <div>
	                <h2 class="card__title">Bracket</h2>
	                <p class="card__subtitle">
	                    <?= (int)$matchCount ?> match(s)
	                    <span class="meta__dot" aria-hidden="true"></span>
	                    <span class="muted"><?= View::e($format) ?></span>
	                </p>
	            </div>
	        </div>
	        <div class="card__body">
                <?php if (!in_array($format, ['single_elim', 'double_elim'], true)): ?>
                    <div class="muted">Generation dispo uniquement pour <span class="mono">single_elim</span> / <span class="mono">double_elim</span> (pour le moment).</div>
                <?php elseif ($matchCount > 0): ?>
                    <div class="muted">Bracket deja genere.</div>
                    <div style="margin-top: 12px;">
                        <form method="post" action="/admin/tournaments/<?= $tid ?>/bracket/reset" class="inline" data-confirm="Reset le bracket ? (supprime tous les matchs)">
                            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                            <button class="btn btn--danger btn--compact" type="submit">Reset bracket</button>
                        </form>
                    </div>
                <?php elseif ($participantType === 'team' && $incompleteTeams !== []): ?>
                    <div class="muted">Equipes incompletes: <?= View::e(implode(', ', $incompleteTeams)) ?></div>
                <?php elseif (!$canGenerateBracket): ?>
                    <div class="muted">Il faut au moins 2 participants pour generer.</div>
                <?php else: ?>
                    <form method="post" action="/admin/tournaments/<?= $tid ?>/bracket/generate" class="inline" data-confirm="Generer le bracket ?">
                        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                        <button class="btn btn--primary" type="submit">Generer <?= $format === 'double_elim' ? 'double elim' : 'single elim' ?></button>
                    </form>
                    <div class="form__hint">Tip: fixe les seeds avant de generer (sinon ordre par inscription).</div>
                <?php endif; ?>
	        </div>
	    </section>
	</div>

	<section class="section">
	    <div class="section__header">
	        <h2 class="section__title">Participants</h2>
	        <div class="section__meta">Seeds, check-in, et retrait.</div>
	    </div>

        <?php $rows = $participantType === 'team' ? $teams : $players; ?>
	    <?php if ($rows === []): ?>
	        <div class="empty">
	            <div class="empty__title">Aucun inscrit</div>
	            <div class="empty__hint">Les joueurs s'inscrivent depuis la page publique du tournoi.</div>
	        </div>
	    <?php else: ?>
	        <div class="tablewrap">
	            <table class="table table--compact">
	                <thead>
	                    <tr>
                            <?php if ($participantType === 'team'): ?>
                                <th>Equipe</th>
                                <th>Membres</th>
                            <?php else: ?>
	                            <th>Joueur</th>
                            <?php endif; ?>
	                        <th>Seed</th>
	                        <th>Check-in</th>
	                        <th>Inscrit le</th>
	                        <th></th>
	                    </tr>
	                </thead>
	                <tbody>
                        <?php if ($participantType === 'team'): ?>
                            <?php foreach ($teams as $t): ?>
                                <?php
                                    $teamId = (int)($t['team_id'] ?? 0);
                                    $seed = $t['seed'] !== null ? (int)$t['seed'] : null;
                                    $checkedIn = (int)($t['checked_in'] ?? 0) === 1;
                                    $members = $teamMembers[$teamId] ?? [];
                                ?>
                                <tr>
                                    <td class="table__strong"><?= View::e((string)($t['name'] ?? '')) ?></td>
                                    <td><?= View::e(format_members_admin($members)) ?></td>
                                    <td>
                                        <form method="post" action="/admin/tournaments/<?= $tid ?>/teams/<?= $teamId ?>/seed" class="inline">
                                            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                            <input class="input input--compact" type="number" inputmode="numeric" name="seed" min="1" step="1" value="<?= $seed !== null ? (int)$seed : '' ?>" placeholder="-">
                                            <button class="btn btn--ghost btn--compact" type="submit">Appliquer</button>
                                        </form>
                                    </td>
                                    <td>
                                        <span class="pill<?= $checkedIn ? '' : ' pill--soft' ?>"><?= $checkedIn ? 'OK' : 'non' ?></span>
                                        <?php if ($checkedIn): ?>
                                            <form method="post" action="/admin/tournaments/<?= $tid ?>/teams/<?= $teamId ?>/checkin" class="inline">
                                                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                                <input type="hidden" name="checked_in" value="0">
                                                <button class="btn btn--ghost btn--compact" type="submit">Annuler</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="post" action="/admin/tournaments/<?= $tid ?>/teams/<?= $teamId ?>/checkin" class="inline">
                                                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                                <input type="hidden" name="checked_in" value="1">
                                                <button class="btn btn--primary btn--compact" type="submit">Check-in</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                    <td class="mono"><?= View::e((string)($t['joined_at'] ?? '')) ?></td>
                                    <td class="table__right">
                                        <form method="post" action="/admin/tournaments/<?= $tid ?>/teams/<?= $teamId ?>/remove" class="inline" data-confirm="Retirer cette equipe du tournoi ?">
                                            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                            <button class="btn btn--danger btn--compact" type="submit">Retirer</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <?php foreach ($players as $p): ?>
                                <?php
                                    $pid = (int)($p['player_id'] ?? 0);
                                    $seed = $p['seed'] !== null ? (int)$p['seed'] : null;
                                    $checkedIn = (int)($p['checked_in'] ?? 0) === 1;
                                ?>
                                <tr>
                                    <td class="table__strong"><?= View::e((string)($p['handle'] ?? '')) ?></td>
                                    <td>
                                        <form method="post" action="/admin/tournaments/<?= $tid ?>/players/<?= $pid ?>/seed" class="inline">
                                            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                            <input class="input input--compact" type="number" inputmode="numeric" name="seed" min="1" step="1" value="<?= $seed !== null ? (int)$seed : '' ?>" placeholder="-">
                                            <button class="btn btn--ghost btn--compact" type="submit">Appliquer</button>
                                        </form>
                                    </td>
                                    <td>
                                        <span class="pill<?= $checkedIn ? '' : ' pill--soft' ?>"><?= $checkedIn ? 'OK' : 'non' ?></span>
                                        <?php if ($checkedIn): ?>
                                            <form method="post" action="/admin/tournaments/<?= $tid ?>/players/<?= $pid ?>/checkin" class="inline">
                                                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                                <input type="hidden" name="checked_in" value="0">
                                                <button class="btn btn--ghost btn--compact" type="submit">Annuler</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="post" action="/admin/tournaments/<?= $tid ?>/players/<?= $pid ?>/checkin" class="inline">
                                                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                                <input type="hidden" name="checked_in" value="1">
                                                <button class="btn btn--primary btn--compact" type="submit">Check-in</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                    <td class="mono"><?= View::e((string)($p['joined_at'] ?? '')) ?></td>
                                    <td class="table__right">
                                        <form method="post" action="/admin/tournaments/<?= $tid ?>/players/<?= $pid ?>/remove" class="inline" data-confirm="Retirer ce joueur du tournoi ?">
                                            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                            <button class="btn btn--danger btn--compact" type="submit">Retirer</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
	                </tbody>
	            </table>
	        </div>
	    <?php endif; ?>
	</section>

    <section class="section">
        <div class="section__header">
            <h2 class="section__title">Matchs</h2>
            <div class="section__meta"><?= (int)$matchCount ?> match(s)</div>
        </div>

        <?php if ($matches === []): ?>
            <div class="empty">
                <div class="empty__title">Aucun match</div>
                <div class="empty__hint">Genere le bracket pour creer les matchs.</div>
            </div>
        <?php else: ?>
            <div class="tablewrap">
                <table class="table table--compact">
                    <thead>
                        <tr>
                            <th>Bracket</th>
                            <th>Round</th>
                            <th>Match</th>
                            <th><?= $participantType === 'team' ? 'Equipe A' : 'Joueur A' ?></th>
                            <th>Score</th>
                            <th><?= $participantType === 'team' ? 'Equipe B' : 'Joueur B' ?></th>
                            <th>Statut</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($matches as $m): ?>
                            <?php
                                $mid = (int)($m['id'] ?? 0);
                                $bracket = (string)($m['bracket'] ?? 'winners');
                                $round = (int)($m['round'] ?? 0);
                                $pos = (int)($m['round_pos'] ?? 0);
                                $st = (string)($m['status'] ?? 'pending');
                                $score1 = (int)($m['score1'] ?? 0);
                                $score2 = (int)($m['score2'] ?? 0);

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

                                $aLabel = $aId === null ? 'TBD' : ($aName !== '' ? $aName : '#');
                                $bLabel = $bId === null ? 'TBD' : ($bName !== '' ? $bName : '#');
                                $winnerSlot = ($win !== null && $aId !== null && $win === $aId) ? '1' : (($win !== null && $bId !== null && $win === $bId) ? '2' : '');

                                $canReport = ($st !== 'confirmed') && ($aId !== null) && ($bId !== null);
                            ?>
                            <tr>
                                <td class="mono"><?= View::e($bracket) ?></td>
                                <td class="mono"><?= $round ?></td>
                                <td class="mono">#<?= $pos ?></td>
                                <td class="<?= $winnerSlot === '1' ? 'table__strong' : '' ?>"><?= View::e($aLabel) ?></td>
                                <td class="mono"><?= $score1 ?> - <?= $score2 ?></td>
                                <td class="<?= $winnerSlot === '2' ? 'table__strong' : '' ?>"><?= View::e($bLabel) ?></td>
                                <td>
                                    <?php if ($st === 'confirmed'): ?>
                                        <span class="pill">confirmed</span>
                                    <?php else: ?>
                                        <span class="pill pill--soft"><?= View::e($st) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="table__right">
                                    <?php if (!$canReport): ?>
                                        <span class="muted">-</span>
                                    <?php else: ?>
                                        <form method="post" action="/admin/tournaments/<?= $tid ?>/matches/<?= $mid ?>/report" class="inline">
                                            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                            <input class="input input--xs" type="number" name="score1" min="0" max="99" step="1" inputmode="numeric" value="<?= $score1 ?>">
                                            <input class="input input--xs" type="number" name="score2" min="0" max="99" step="1" inputmode="numeric" value="<?= $score2 ?>">
                                            <select class="select select--compact" name="winner_slot" required>
                                                <option value="" <?= $winnerSlot === '' ? 'selected' : '' ?>>Winner...</option>
                                                <option value="1" <?= $winnerSlot === '1' ? 'selected' : '' ?>>A</option>
                                                <option value="2" <?= $winnerSlot === '2' ? 'selected' : '' ?>>B</option>
                                            </select>
                                            <button class="btn btn--primary btn--compact" type="submit">Confirmer</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
