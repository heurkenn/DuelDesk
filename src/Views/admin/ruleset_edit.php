<?php

declare(strict_types=1);

use DuelDesk\View;
use DuelDesk\Services\PickBanEngine;

/** @var bool $isNew */
/** @var array<string, mixed>|null $ruleset */
/** @var list<array<string, mixed>> $games */
/** @var array{name:string,game_id:string,pool:list<array{key:string,name:string}>,steps_by_best_of:array{1:list<array{action:string,actor:string}>,3:list<array{action:string,actor:string}>,5:list<array{action:string,actor:string}>}} $old */
/** @var array<string,string> $errors */
/** @var string $csrfToken */

function field_error(array $errors, string $key): ?string
{
    return isset($errors[$key]) ? (string)$errors[$key] : null;
}

$id = is_array($ruleset) ? (int)($ruleset['id'] ?? 0) : 0;
$action = $isNew ? '/admin/rulesets' : ('/admin/rulesets/' . $id);
$pageTitle = $isNew ? 'Nouveau ruleset' : 'Edit ruleset';

$boList = [1, 3, 5];

$actorLabel = static fn (string $v): string => match ($v) {
    'starter' => 'Team A (starter)',
    'other' => 'Team B (other)',
    'alternate' => 'Auto (alterne)',
    default => $v,
};
?>

<div class="pagehead">
    <div>
        <h1 class="pagehead__title"><?= View::e($pageTitle) ?></h1>
        <p class="pagehead__lead">Builder sans JSON: pool de maps + ordre pick/ban par BO.</p>
    </div>
    <div class="pagehead__actions">
        <a class="btn btn--ghost" href="/admin/rulesets">Retour</a>
        <?php if (!$isNew): ?>
            <form method="post" action="/admin/rulesets/<?= (int)$id ?>/delete" class="inline" data-confirm="Supprimer ce ruleset ?">
                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                <button class="btn btn--danger" type="submit">Supprimer</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<form class="form" method="post" action="<?= View::e($action) ?>" novalidate>
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">

    <section class="card">
        <div class="card__header">
            <div>
                <h2 class="card__title">Infos</h2>
                <p class="card__subtitle">Nom + jeu (optionnel).</p>
            </div>
        </div>
        <div class="card__body">
            <div class="form__grid">
                <label class="field">
                    <span class="field__label">Nom</span>
                    <input class="input<?= field_error($errors, 'name') ? ' input--error' : '' ?>" name="name" value="<?= View::e($old['name']) ?>" required maxlength="120">
                    <?php if (field_error($errors, 'name')): ?>
                        <span class="field__error"><?= View::e((string)field_error($errors, 'name')) ?></span>
                    <?php endif; ?>
                </label>

                <label class="field">
                    <span class="field__label">Jeu</span>
                    <select class="select<?= field_error($errors, 'game_id') ? ' input--error' : '' ?>" name="game_id">
                        <option value="" disabled <?= $old['game_id'] === '' ? 'selected' : '' ?>>Choisir...</option>
                        <?php foreach ($games as $g): ?>
                            <?php $gid = (int)($g['id'] ?? 0); ?>
                            <option value="<?= (int)$gid ?>" <?= (string)$gid === (string)$old['game_id'] ? 'selected' : '' ?>>
                                <?= View::e((string)($g['name'] ?? '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (field_error($errors, 'game_id')): ?>
                        <span class="field__error"><?= View::e((string)field_error($errors, 'game_id')) ?></span>
                    <?php endif; ?>
                </label>

                <label class="field field--full">
                    <span class="field__label">Template (remplissage)</span>
                    <div class="split" style="gap: 10px; align-items: flex-end;">
                        <select class="select" id="rulesetTemplateSelect">
                            <option value="">(Aucun)</option>
                            <option value="cs2">CS2</option>
                            <option value="valorant">Valorant</option>
                        </select>
                        <button class="btn btn--ghost" type="button" id="rulesetTemplateLoad">Charger</button>
                    </div>
                    <span class="muted">Remplit automatiquement le pool et les sequences (BO1/BO3/BO5).</span>
                </label>

                <?php if (field_error($errors, 'ruleset')): ?>
                    <div class="field field--full">
                        <div class="alert alert--error" role="alert">
                            <div class="alert__icon" aria-hidden="true"></div>
                            <div class="alert__text"><?= View::e((string)field_error($errors, 'ruleset')) ?></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="card" style="margin-top: 14px;">
        <div class="card__header">
            <div>
                <h2 class="card__title">Pool de maps</h2>
                <p class="card__subtitle">Key + nom (key auto si vide).</p>
            </div>
            <div class="pill pill--soft"><?= count($old['pool']) ?> map(s)</div>
        </div>
        <div class="card__body">
            <div class="tablewrap">
                <table class="table table--compact">
                    <thead>
                        <tr>
                            <th style="width: 200px;">Key</th>
                            <th>Nom</th>
                            <th class="table__right" style="width: 120px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody data-ruleset-pool>
                        <?php $poolRows = $old['pool'] !== [] ? $old['pool'] : [['key' => '', 'name' => '']]; ?>
                        <?php foreach ($poolRows as $m): ?>
                            <?php $k = (string)($m['key'] ?? ''); $n = (string)($m['name'] ?? ''); ?>
                            <tr>
                                <td><input class="input mono" name="pool_key[]" value="<?= View::e($k) ?>" placeholder="ex: dust2"></td>
                                <td><input class="input" name="pool_name[]" value="<?= View::e($n) ?>" placeholder="ex: Dust II"></td>
                                <td class="table__right">
                                    <button class="btn btn--ghost btn--compact" type="button" data-row-remove>Retirer</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div style="margin-top: 10px;">
                <button class="btn btn--ghost btn--compact" type="button" data-ruleset-add-map>+ Ajouter une map</button>
            </div>
        </div>
    </section>

    <section class="card" style="margin-top: 14px;">
        <div class="card__header">
            <div>
                <h2 class="card__title">Ordre Pick / Ban</h2>
                <p class="card__subtitle">Pour chaque BO: define les bans/picks. Le decider est ajoute automatiquement en dernier.</p>
            </div>
        </div>
        <div class="card__body">
            <div class="muted" style="margin-bottom: 12px;">
                Regle de validation:
                <span class="mono">picks + 1 = BO</span>
                <span class="meta__dot" aria-hidden="true"></span>
                <span class="mono">bans + picks = pool - 1</span>
            </div>

            <?php foreach ($boList as $bo): ?>
                <?php $steps = $old['steps_by_best_of'][(string)$bo] ?? []; ?>
                <div class="card card--nested" style="margin-top: 12px;">
                    <div class="card__header">
                        <div>
                            <h3 class="card__title">BO<?= (int)$bo ?></h3>
                            <p class="card__subtitle">Ordre des actions (ban/pick) + acteur.</p>
                        </div>
                        <div class="pill pill--soft"><?= count($steps) ?> step(s)</div>
                    </div>
                    <div class="card__body">
                        <div class="tablewrap">
                            <table class="table table--compact">
                                <thead>
                                    <tr>
                                        <th style="width: 130px;">Action</th>
                                        <th>Acteur</th>
                                        <th class="table__right" style="width: 160px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody data-ruleset-steps="<?= (int)$bo ?>">
                                    <?php foreach ($steps as $s): ?>
                                        <?php
                                            $act = is_string($s['action'] ?? null) ? (string)$s['action'] : '';
                                            $actor = is_string($s['actor'] ?? null) ? (string)$s['actor'] : 'alternate';
                                        ?>
                                        <tr>
                                            <td>
                                                <select class="select select--compact mono" name="steps[<?= (int)$bo ?>][action][]">
                                                    <option value="ban" <?= $act === 'ban' ? 'selected' : '' ?>>BAN</option>
                                                    <option value="pick" <?= $act === 'pick' ? 'selected' : '' ?>>PICK</option>
                                                </select>
                                            </td>
                                            <td>
                                                <select class="select select--compact" name="steps[<?= (int)$bo ?>][actor][]">
                                                    <option value="starter" <?= $actor === 'starter' ? 'selected' : '' ?>><?= View::e($actorLabel('starter')) ?></option>
                                                    <option value="other" <?= $actor === 'other' ? 'selected' : '' ?>><?= View::e($actorLabel('other')) ?></option>
                                                    <option value="alternate" <?= $actor === 'alternate' ? 'selected' : '' ?>><?= View::e($actorLabel('alternate')) ?></option>
                                                </select>
                                            </td>
                                            <td class="table__right">
                                                <button class="btn btn--ghost btn--compact" type="button" data-row-up>↑</button>
                                                <button class="btn btn--ghost btn--compact" type="button" data-row-down>↓</button>
                                                <button class="btn btn--ghost btn--compact" type="button" data-row-remove>Retirer</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div style="margin-top: 10px;">
                            <button class="btn btn--ghost btn--compact" type="button" data-ruleset-add-step="<?= (int)$bo ?>">+ Ajouter un step</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="card__footer">
            <button class="btn btn--primary" type="submit">Enregistrer</button>
        </div>
    </section>
</form>

<template id="tplRulesetMapRow">
    <tr>
        <td><input class="input mono" name="pool_key[]" value="" placeholder="ex: dust2"></td>
        <td><input class="input" name="pool_name[]" value="" placeholder="ex: Dust II"></td>
        <td class="table__right">
            <button class="btn btn--ghost btn--compact" type="button" data-row-remove>Retirer</button>
        </td>
    </tr>
</template>

<template id="tplRulesetStepRow">
    <tr>
        <td>
            <select class="select select--compact mono" name="">
                <option value="ban">BAN</option>
                <option value="pick">PICK</option>
            </select>
        </td>
        <td>
            <select class="select select--compact" name="">
                <option value="starter"><?= View::e($actorLabel('starter')) ?></option>
                <option value="other"><?= View::e($actorLabel('other')) ?></option>
                <option value="alternate"><?= View::e($actorLabel('alternate')) ?></option>
            </select>
        </td>
        <td class="table__right">
            <button class="btn btn--ghost btn--compact" type="button" data-row-up>↑</button>
            <button class="btn btn--ghost btn--compact" type="button" data-row-down>↓</button>
            <button class="btn btn--ghost btn--compact" type="button" data-row-remove>Retirer</button>
        </td>
    </tr>
</template>

<textarea id="ddRulesetTemplates" hidden><?= json_encode([
    'cs2' => PickBanEngine::template('cs2'),
    'valorant' => PickBanEngine::template('valorant'),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></textarea>
