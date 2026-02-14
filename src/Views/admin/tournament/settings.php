<?php

declare(strict_types=1);

use DuelDesk\View;

/** @var array<string,mixed> $tournament */
/** @var int $tid */
/** @var int $gameId */
/** @var 'solo'|'team' $participantType */
/** @var int $teamSize */
/** @var string $teamMatchMode */
/** @var string $status */
/** @var string $format */
/** @var bool $canEditStructure */
/** @var int $matchCount */
/** @var int $confirmedCount */
/** @var list<array<string,mixed>> $games */
/** @var list<array<string,mixed>> $lanEvents */
/** @var list<array<string,mixed>> $rulesets */
/** @var string $csrfToken */
/** @var string $startsAtValue */
/** @var string $maxEntrantsValue */
/** @var string $signupClosesValue */
/** @var string $bestOfDefaultValue */
/** @var string $bestOfFinalValue */
?>

<div class="stack" style="margin-top: 18px;">
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

        <form class="card__body form" method="post" action="/admin/tournaments/<?= (int)$tid ?>/config" novalidate <?= ($matchCount > 0 && $confirmedCount === 0) ? 'data-confirm="Changer format/type/taille/mode peut reset le bracket. Continuer ?"' : '' ?>>
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

                <label class="field">
                    <span class="field__label">Mode equipe</span>
                    <select class="select" name="team_match_mode" <?= ($participantType === 'team' && $canEditStructure) ? '' : 'disabled' ?>>
                        <option value="standard" <?= $teamMatchMode === 'standard' ? 'selected' : '' ?>>standard</option>
                        <option value="lineup_duels" <?= $teamMatchMode === 'lineup_duels' ? 'selected' : '' ?>>lineup_duels</option>
                        <option value="multi_round" <?= $teamMatchMode === 'multi_round' ? 'selected' : '' ?>>multi_round</option>
                    </select>
                    <span class="muted"><?= $participantType === 'team' ? "Standard / crew battle / multi-round (ex: 2XKO, Fall Guys)." : 'N/A (solo)' ?></span>
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

        <form class="card__body form" method="post" action="/admin/tournaments/<?= (int)$tid ?>/settings" novalidate>
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

        <form class="card__body form" method="post" action="/admin/tournaments/<?= (int)$tid ?>/ruleset" novalidate>
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

    <section class="card">
        <div class="card__header">
            <div>
                <h2 class="card__title">Danger zone</h2>
                <p class="card__subtitle">Actions irreversibles.</p>
            </div>
        </div>
        <div class="card__body">
            <form method="post" action="/admin/tournaments/<?= (int)$tid ?>/delete" class="inline" data-confirm="Supprimer ce tournoi ? (action irreversible)">
                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                <button class="btn btn--danger" type="submit">Supprimer le tournoi</button>
            </form>
        </div>
    </section>
</div>

