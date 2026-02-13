<?php

declare(strict_types=1);

use DuelDesk\View;

/** @var array<string, mixed> $tournament */
/** @var list<array<string, mixed>> $players */
/** @var list<array<string, mixed>> $teams */
/** @var array<int, list<array{user_id:int,username:string,role:string}>> $teamMembers */
/** @var list<array<string, mixed>> $games */
/** @var list<array<string, mixed>> $lanEvents */
/** @var string $csrfToken */
/** @var string $startsAtValue */
/** @var string $maxEntrantsValue */
/** @var string $signupClosesValue */
/** @var string $bestOfDefaultValue */
/** @var string $bestOfFinalValue */
/** @var int $matchCount */
/** @var int $confirmedCount */
/** @var bool $canGenerateBracket */
/** @var list<string> $incompleteTeams */
/** @var list<array<string, mixed>> $matches */
/** @var list<array<string, mixed>> $auditLogs */

$tid = (int)($tournament['id'] ?? 0);
$gameId = (int)($tournament['game_id'] ?? 0);
$participantType = (string)($tournament['participant_type'] ?? 'solo');
$teamSize = (int)($tournament['team_size'] ?? 0);
$status = (string)($tournament['status'] ?? 'draft');
$isOpen = in_array($status, ['published', 'running'], true);
$format = (string)($tournament['format'] ?? 'single_elim');
$canEditStructure = (int)$confirmedCount === 0;

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

function to_datetime_local_admin(mixed $dbValue): string
{
    if (!is_string($dbValue) || $dbValue === '') {
        return '';
    }

    // DB: YYYY-MM-DD HH:MM:SS -> input: YYYY-MM-DDTHH:MM
    if (!preg_match('/^\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}:\\d{2}$/', $dbValue)) {
        return '';
    }

    $v = substr($dbValue, 0, 16);
    if ($v === false) {
        return '';
    }

    return str_replace(' ', 'T', $v);
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
            <?php if (!empty($tournament['lan_event_id'])): ?>
                <span class="meta__dot" aria-hidden="true"></span>
                <a class="pill pill--soft" href="/admin/lan/<?= (int)$tournament['lan_event_id'] ?>">
                    LAN: <?= View::e((string)($tournament['lan_event_name'] ?? ('#' . (int)$tournament['lan_event_id']))) ?>
                </a>
            <?php endif; ?>
            <span class="meta__dot" aria-hidden="true"></span>
            <span class="pill"><?= View::e((string)($tournament['format'] ?? '')) ?></span>
            <span class="pill pill--soft"><?= View::e($status) ?></span>
        </p>
    </div>
    <div class="pagehead__actions">
        <a class="btn btn--ghost" href="/tournaments/<?= $tid ?>">Voir la page</a>
        <form method="post" action="/admin/tournaments/<?= $tid ?>/delete" class="inline" data-confirm="Supprimer ce tournoi ? (action irreversible)">
            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
            <button class="btn btn--danger" type="submit">Supprimer</button>
        </form>
        <a class="btn btn--ghost" href="/admin">Retour admin</a>
    </div>
</div>

<div class="split">
    <div class="stack">
        <section class="card">
            <div class="card__header">
                <div>
                    <h2 class="card__title">Configuration</h2>
                    <p class="card__subtitle">
                        Nom, jeu, format, participants.
                        <?php if ($confirmedCount > 0): ?>
                            <span class="pill pill--soft">verrouille (match(s) confirmes)</span>
                        <?php else: ?>
                            <span class="pill">editable</span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <form class="card__body form" method="post" action="/admin/tournaments/<?= $tid ?>/config" novalidate <?= ($matchCount > 0 && $confirmedCount === 0) ? 'data-confirm="Changer format/type/taille reset le bracket. Continuer ?"' : '' ?>>
                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">

                <div class="form__grid">
                    <label class="field field--full">
                        <span class="field__label">Nom du tournoi</span>
                        <input class="input" name="name" value="<?= View::e((string)($tournament['name'] ?? '')) ?>" required maxlength="120">
                    </label>

                    <label class="field field--full">
                        <span class="field__label">Jeu</span>
                        <select class="select" name="game_id" required>
                            <?php foreach ($games as $g): ?>
                                <?php $gid = (int)($g['id'] ?? 0); ?>
                                <option value="<?= (int)$gid ?>" <?= $gid === $gameId ? 'selected' : '' ?>><?= View::e((string)($g['name'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="field field--full">
                        <span class="field__label">LAN (optionnel)</span>
                        <?php $lanEventIdValue = $tournament['lan_event_id'] !== null ? (string)(int)$tournament['lan_event_id'] : ''; ?>
                        <select class="select" name="lan_event_id">
                            <option value="" <?= $lanEventIdValue === '' ? 'selected' : '' ?>>Aucun</option>
                            <?php foreach ($lanEvents as $e): ?>
                                <?php $eid = (string)($e['id'] ?? ''); ?>
                                <option value="<?= (int)$eid ?>" <?= $eid !== '' && $eid === $lanEventIdValue ? 'selected' : '' ?>>
                                    <?= View::e((string)($e['name'] ?? ('LAN #' . $eid))) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="muted">Associe ce tournoi a un evenement LAN.</span>
                    </label>

                    <label class="field">
                        <span class="field__label">Format</span>
                        <select class="select" name="format" <?= $canEditStructure ? '' : 'disabled' ?>>
                            <option value="single_elim" <?= $format === 'single_elim' ? 'selected' : '' ?>>single_elim</option>
                            <option value="double_elim" <?= $format === 'double_elim' ? 'selected' : '' ?>>double_elim</option>
                            <option value="round_robin" <?= $format === 'round_robin' ? 'selected' : '' ?>>round_robin</option>
                        </select>
                        <?php if (!$canEditStructure): ?>
                            <span class="muted">Verrouille apres confirmation.</span>
                        <?php endif; ?>
                    </label>

                    <label class="field">
                        <span class="field__label">Participants</span>
                        <select class="select" name="participant_type" <?= $canEditStructure ? '' : 'disabled' ?>>
                            <option value="solo" <?= $participantType === 'solo' ? 'selected' : '' ?>>solo</option>
                            <option value="team" <?= $participantType === 'team' ? 'selected' : '' ?>>team</option>
                        </select>
                    </label>

                    <label class="field">
                        <span class="field__label">Taille equipe</span>
                        <input class="input" type="number" name="team_size" min="2" max="16" step="1" inputmode="numeric" value="<?= (int)($teamSize > 0 ? $teamSize : 2) ?>" <?= ($participantType === 'team' && $canEditStructure) ? '' : 'disabled' ?>>
                        <span class="muted"><?= $participantType === 'team' ? '2 a 16' : 'N/A (solo)' ?></span>
                    </label>
                </div>

                <?php if ($matchCount > 0 && $confirmedCount === 0): ?>
                    <div class="form__hint">Si tu modifies format/type/taille, le bracket sera reset automatiquement.</div>
                <?php endif; ?>

                <div class="card__footer">
                    <button class="btn btn--primary" type="submit">Enregistrer</button>
                </div>
            </form>
        </section>

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
                        <span class="muted">UTC.</span>
                    </label>

                    <label class="field">
                        <span class="field__label">Max entrants (optionnel)</span>
                        <input class="input" type="number" name="max_entrants" min="2" max="1024" step="1" inputmode="numeric" value="<?= View::e($maxEntrantsValue) ?>" placeholder="-">
                    </label>

                    <label class="field">
                        <span class="field__label">Best-of (defaut)</span>
                        <select class="select" name="best_of_default">
                            <?php foreach (['1', '3', '5', '7', '9'] as $bo): ?>
                                <option value="<?= View::e($bo) ?>" <?= $bestOfDefaultValue === $bo ? 'selected' : '' ?>>BO<?= View::e($bo) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="field">
                        <span class="field__label">Best-of (finale)</span>
                        <select class="select" name="best_of_final">
                            <option value="" <?= $bestOfFinalValue === '' ? 'selected' : '' ?>>Defaut (BO<?= View::e($bestOfDefaultValue) ?>)</option>
                            <?php foreach (['1', '3', '5', '7', '9'] as $bo): ?>
                                <option value="<?= View::e($bo) ?>" <?= $bestOfFinalValue === $bo ? 'selected' : '' ?>>BO<?= View::e($bo) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="muted">Override uniquement pour la finale (GF/GF2).</span>
                    </label>

                    <?php $pickbanStartModeValue = (string)($tournament['pickban_start_mode'] ?? 'coin_toss'); ?>
                    <label class="field">
                        <span class="field__label">Pick/Ban: qui commence</span>
                        <select class="select" name="pickban_start_mode">
                            <option value="coin_toss" <?= $pickbanStartModeValue === 'coin_toss' ? 'selected' : '' ?>>Pile ou face</option>
                            <option value="higher_seed" <?= $pickbanStartModeValue === 'higher_seed' ? 'selected' : '' ?>>Higher seed choisit Team A/B</option>
                        </select>
                        <span class="muted">Utilise uniquement si un ruleset Pick/Ban est actif.</span>
                    </label>

                    <label class="field">
                        <span class="field__label">Fermeture inscriptions (optionnel)</span>
                        <input class="input" type="datetime-local" name="signup_closes_at" value="<?= View::e($signupClosesValue) ?>">
                        <span class="muted">UTC.</span>
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
                    <h2 class="card__title">Ruleset (Pick/Ban)</h2>
                    <p class="card__subtitle">Configurer le pick/ban de maps (CS2, Valorant, etc.).</p>
                </div>
                <?php $rulesetJsonValue = trim((string)($tournament['ruleset_json'] ?? '')); ?>
                <div class="pill<?= $rulesetJsonValue !== '' ? '' : ' pill--soft' ?>"><?= $rulesetJsonValue !== '' ? 'actif' : 'inactif' ?></div>
            </div>

            <form class="card__body form" method="post" action="/admin/tournaments/<?= $tid ?>/ruleset" novalidate>
                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">

                <div class="form__grid">
                    <label class="field field--full">
                        <span class="field__label">Source</span>
                        <select class="select" name="ruleset_source">
                            <?php if ($rulesetJsonValue !== ''): ?>
                                <option value="keep" selected>Actuel (ne pas changer)</option>
                            <?php endif; ?>
                            <option value="none" <?= $rulesetJsonValue === '' ? 'selected' : '' ?>>Aucun</option>
                            <option value="template:cs2">CS2 (template)</option>
                            <option value="template:valorant">Valorant (template)</option>
                            <?php if (!empty($rulesets)): ?>
                                <optgroup label="Rulesets sauvegardes">
                                    <?php foreach ($rulesets as $r): ?>
                                        <?php $rid = (int)($r['id'] ?? 0); $rname = (string)($r['name'] ?? ''); ?>
                                        <option value="ruleset:<?= (int)$rid ?>"><?= View::e($rname !== '' ? $rname : ('#' . $rid)) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endif; ?>
                        </select>
                        <span class="muted">Le ruleset determine les maps + l'ordre pick/ban. Pour en creer: <a class="link" href="/admin/rulesets/new?game_id=<?= (int)($tournament['game_id'] ?? 0) ?>">Nouveau ruleset</a> ou <a class="link" href="/admin/rulesets?game_id=<?= (int)($tournament['game_id'] ?? 0) ?>">liste</a>.</span>
                    </label>
                </div>

                <div class="card__footer">
                    <button class="btn btn--primary" type="submit">Enregistrer</button>
                </div>
            </form>
        </section>
    </div>

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
                <?php if (!in_array($format, ['single_elim', 'double_elim', 'round_robin'], true)): ?>
                    <div class="muted">Generation dispo uniquement pour <span class="mono">single_elim</span> / <span class="mono">double_elim</span> / <span class="mono">round_robin</span>.</div>
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
                            <th>BO</th>
                            <th><?= $participantType === 'team' ? 'Equipe A' : 'Joueur A' ?></th>
                            <th>Score</th>
                            <th><?= $participantType === 'team' ? 'Equipe B' : 'Joueur B' ?></th>
                            <th>Horaire (UTC)</th>
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
                                $bestOf = (int)($m['best_of'] ?? 0);
                                if ($bestOf <= 0) {
                                    $bestOf = 3;
                                }
                                $score1 = (int)($m['score1'] ?? 0);
                                $score2 = (int)($m['score2'] ?? 0);
                                $reportedScore1 = $m['reported_score1'] ?? null;
                                $reportedScore2 = $m['reported_score2'] ?? null;
                                $reportedWinnerSlot = $m['reported_winner_slot'] ?? null;
                                $reportedByUsername = (string)($m['reported_by_username'] ?? '');
                                $counterScore1 = $m['counter_reported_score1'] ?? null;
                                $counterScore2 = $m['counter_reported_score2'] ?? null;
                                $counterWinnerSlot = $m['counter_reported_winner_slot'] ?? null;
                                $counterByUsername = (string)($m['counter_reported_by_username'] ?? '');
                                $scheduledAt = $m['scheduled_at'] ?? null;
                                $scheduledValue = to_datetime_local_admin($scheduledAt);

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

                                $hasReportA = $reportedScore1 !== null && $reportedScore2 !== null && $reportedWinnerSlot !== null;
                                $hasReportB = $counterScore1 !== null && $counterScore2 !== null && $counterWinnerSlot !== null;

                                $prefAScore1 = $hasReportA ? (int)$reportedScore1 : $score1;
                                $prefAScore2 = $hasReportA ? (int)$reportedScore2 : $score2;
                                $prefAWinnerSlot = $hasReportA ? (string)(int)$reportedWinnerSlot : $winnerSlot;

                                $prefBScore1 = $hasReportB ? (int)$counterScore1 : $score1;
                                $prefBScore2 = $hasReportB ? (int)$counterScore2 : $score2;
                                $prefBWinnerSlot = $hasReportB ? (string)(int)$counterWinnerSlot : $winnerSlot;

                                if ($st === 'disputed' && $hasReportA && $hasReportB) {
                                    $scoreLabel = 'A: ' . (int)$reportedScore1 . '-' . (int)$reportedScore2 . ' / B: ' . (int)$counterScore1 . '-' . (int)$counterScore2;
                                } elseif ($st === 'reported' && $hasReportA) {
                                    $scoreLabel = (int)$reportedScore1 . ' - ' . (int)$reportedScore2;
                                } else {
                                    $scoreLabel = $score1 . ' - ' . $score2;
                                }
                            ?>
                            <tr>
                                <td class="mono"><?= View::e($bracket) ?></td>
                                <td class="mono"><?= $round ?></td>
                                <td class="mono">#<?= $pos ?></td>
                                <td class="mono">
                                    <?php if ($st === 'confirmed'): ?>
                                        <span class="pill pill--soft">BO<?= (int)$bestOf ?></span>
                                    <?php else: ?>
                                        <form method="post" action="/admin/tournaments/<?= $tid ?>/matches/<?= $mid ?>/bestof" class="inline">
                                            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                            <select class="select select--compact" name="best_of">
                                                <?php foreach ([1, 3, 5, 7, 9] as $bo): ?>
                                                    <option value="<?= (int)$bo ?>" <?= (int)$bestOf === (int)$bo ? 'selected' : '' ?>>BO<?= (int)$bo ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button class="btn btn--ghost btn--compact" type="submit">OK</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                                <td class="<?= $winnerSlot === '1' ? 'table__strong' : '' ?>"><?= View::e($aLabel) ?></td>
                                <td class="mono"><?= View::e($scoreLabel) ?></td>
                                <td class="<?= $winnerSlot === '2' ? 'table__strong' : '' ?>"><?= View::e($bLabel) ?></td>
                                <td class="mono">
                                    <?php if ($st === 'confirmed'): ?>
                                        <?php if (is_string($scheduledAt) && $scheduledAt !== ''): ?>
                                            <?= View::e(substr($scheduledAt, 0, 16) . ' UTC') ?>
                                        <?php else: ?>
                                            <span class="muted">-</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <form method="post" action="/admin/tournaments/<?= $tid ?>/matches/<?= $mid ?>/schedule" class="inline">
                                            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                            <input class="input input--xs input--date" type="datetime-local" name="scheduled_at" value="<?= View::e($scheduledValue) ?>">
                                            <button class="btn btn--ghost btn--compact" type="submit">OK</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($st === 'confirmed'): ?>
                                        <span class="pill">confirmed</span>
                                    <?php else: ?>
                                        <?php if ($st === 'disputed'): ?>
                                            <span class="pill">disputed</span>
                                            <?php if ($reportedByUsername !== '' || $counterByUsername !== ''): ?>
                                                <span class="muted" style="margin-left: 8px;">
                                                    <?php if ($reportedByUsername !== ''): ?>A: <?= View::e($reportedByUsername) ?><?php endif; ?>
                                                    <?php if ($counterByUsername !== ''): ?><?= $reportedByUsername !== '' ? ' · ' : '' ?>B: <?= View::e($counterByUsername) ?><?php endif; ?>
                                                </span>
                                            <?php endif; ?>
                                        <?php elseif ($st === 'reported'): ?>
                                            <span class="pill">reported</span>
                                            <?php if ($reportedByUsername !== ''): ?>
                                                <span class="muted" style="margin-left: 8px;">par <?= View::e($reportedByUsername) ?></span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="pill pill--soft"><?= View::e($st) ?></span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="table__right">
                                    <?php if (!$canReport): ?>
                                        <span class="muted">-</span>
                                    <?php else: ?>
                                        <?php if ($st === 'disputed' && $hasReportA && $hasReportB): ?>
                                            <form method="post" action="/admin/tournaments/<?= $tid ?>/matches/<?= $mid ?>/report" class="inline">
                                                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                                <input class="input input--xs" type="number" name="score1" min="0" max="99" step="1" inputmode="numeric" value="<?= (int)$prefAScore1 ?>">
                                                <input class="input input--xs" type="number" name="score2" min="0" max="99" step="1" inputmode="numeric" value="<?= (int)$prefAScore2 ?>">
                                                <select class="select select--compact" name="winner_slot" required>
                                                    <option value="" <?= $prefAWinnerSlot === '' ? 'selected' : '' ?>>Winner...</option>
                                                    <option value="1" <?= $prefAWinnerSlot === '1' ? 'selected' : '' ?>>A</option>
                                                    <option value="2" <?= $prefAWinnerSlot === '2' ? 'selected' : '' ?>>B</option>
                                                </select>
                                                <button class="btn btn--primary btn--compact" type="submit">Confirmer A</button>
                                            </form>

                                            <form method="post" action="/admin/tournaments/<?= $tid ?>/matches/<?= $mid ?>/report" class="inline">
                                                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                                <input class="input input--xs" type="number" name="score1" min="0" max="99" step="1" inputmode="numeric" value="<?= (int)$prefBScore1 ?>">
                                                <input class="input input--xs" type="number" name="score2" min="0" max="99" step="1" inputmode="numeric" value="<?= (int)$prefBScore2 ?>">
                                                <select class="select select--compact" name="winner_slot" required>
                                                    <option value="" <?= $prefBWinnerSlot === '' ? 'selected' : '' ?>>Winner...</option>
                                                    <option value="1" <?= $prefBWinnerSlot === '1' ? 'selected' : '' ?>>A</option>
                                                    <option value="2" <?= $prefBWinnerSlot === '2' ? 'selected' : '' ?>>B</option>
                                                </select>
                                                <button class="btn btn--primary btn--compact" type="submit">Confirmer B</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="post" action="/admin/tournaments/<?= $tid ?>/matches/<?= $mid ?>/report" class="inline">
                                                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                                <input class="input input--xs" type="number" name="score1" min="0" max="99" step="1" inputmode="numeric" value="<?= (int)$prefAScore1 ?>">
                                                <input class="input input--xs" type="number" name="score2" min="0" max="99" step="1" inputmode="numeric" value="<?= (int)$prefAScore2 ?>">
                                                <select class="select select--compact" name="winner_slot" required>
                                                    <option value="" <?= $prefAWinnerSlot === '' ? 'selected' : '' ?>>Winner...</option>
                                                    <option value="1" <?= $prefAWinnerSlot === '1' ? 'selected' : '' ?>>A</option>
                                                    <option value="2" <?= $prefAWinnerSlot === '2' ? 'selected' : '' ?>>B</option>
                                                </select>
                                                <button class="btn btn--primary btn--compact" type="submit">Confirmer</button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if (in_array($st, ['reported', 'disputed'], true)): ?>
                                            <form method="post" action="/admin/tournaments/<?= $tid ?>/matches/<?= $mid ?>/report/reject" class="inline" style="margin-left: 6px;">
                                                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                                <button class="btn btn--ghost btn--compact" type="submit">Rejeter</button>
                                            </form>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="section">
        <div class="section__header">
            <h2 class="section__title">Audit</h2>
            <div class="section__meta">Actions recentes</div>
        </div>

        <?php if ($auditLogs === []): ?>
            <div class="empty empty--compact">
                <div class="empty__title">Aucun log</div>
                <div class="empty__hint">Les actions admin (bracket, confirmations, etc.) apparaitront ici.</div>
            </div>
        <?php else: ?>
            <div class="tablewrap">
                <table class="table table--compact">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Cible</th>
                            <th>Meta</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($auditLogs as $a): ?>
                            <?php
                                $when = (string)($a['created_at'] ?? '');
                                $who = (string)($a['username'] ?? '');
                                $action = (string)($a['action'] ?? '');
                                $etype = (string)($a['entity_type'] ?? '');
                                $eid = $a['entity_id'] !== null ? (int)$a['entity_id'] : 0;
                                $metaJson = (string)($a['meta_json'] ?? '');

                                $metaLabel = '';
                                if ($metaJson !== '') {
                                    $decoded = json_decode($metaJson, true);
                                    if (is_array($decoded)) {
                                        $pairs = [];
                                        foreach (['status', 'score1', 'score2', 'winner_slot', 'bracket', 'round', 'round_pos', 'note'] as $k) {
                                            if (!array_key_exists($k, $decoded)) {
                                                continue;
                                            }
                                            $v = $decoded[$k];
                                            if (is_bool($v)) {
                                                $v = $v ? 'true' : 'false';
                                            } elseif (is_array($v) || is_object($v)) {
                                                $v = '[...]';
                                            }
                                            $pairs[] = $k . '=' . (string)$v;
                                        }
                                        $metaLabel = $pairs !== [] ? implode(', ', $pairs) : substr($metaJson, 0, 160);
                                    } else {
                                        $metaLabel = substr($metaJson, 0, 160);
                                    }
                                }
                            ?>
                            <tr>
                                <td class="mono"><?= View::e($when) ?></td>
                                <td><?= View::e($who !== '' ? $who : '-') ?></td>
                                <td class="mono"><?= View::e($action !== '' ? $action : '-') ?></td>
                                <td class="mono">
                                    <?php if ($etype === 'match' && $eid > 0): ?>
                                        <a href="/tournaments/<?= (int)$tid ?>/matches/<?= (int)$eid ?>">match#<?= (int)$eid ?></a>
                                    <?php elseif ($etype !== '' && $eid > 0): ?>
                                        <?= View::e($etype) ?>#<?= (int)$eid ?>
                                    <?php elseif ($etype !== ''): ?>
                                        <?= View::e($etype) ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td class="muted"><?= View::e($metaLabel) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
