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

<script>
(() => {
  const TEMPLATES = {
    cs2: <?= json_encode(PickBanEngine::template('cs2'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
    valorant: <?= json_encode(PickBanEngine::template('valorant'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
  };

  const poolBody = document.querySelector('[data-ruleset-pool]');
  const addMapBtn = document.querySelector('[data-ruleset-add-map]');
  const tplMap = document.getElementById('tplRulesetMapRow');

  const addMapRow = () => {
    if (!(poolBody instanceof HTMLElement)) return;
    if (!(tplMap instanceof HTMLTemplateElement)) return;
    const node = tplMap.content.firstElementChild?.cloneNode(true);
    if (!(node instanceof HTMLElement)) return;
    poolBody.appendChild(node);
  };

  if (addMapBtn instanceof HTMLButtonElement) {
    addMapBtn.addEventListener('click', addMapRow);
  }

  const addStepBtnEls = Array.from(document.querySelectorAll('[data-ruleset-add-step]'));
  const tplStep = document.getElementById('tplRulesetStepRow');

  const addStepRow = (bo) => {
    const body = document.querySelector(`[data-ruleset-steps="${CSS.escape(String(bo))}"]`);
    if (!(body instanceof HTMLElement)) return;
    if (!(tplStep instanceof HTMLTemplateElement)) return;

    const row = tplStep.content.firstElementChild?.cloneNode(true);
    if (!(row instanceof HTMLElement)) return;

    const selects = Array.from(row.querySelectorAll('select'));
    const actionSel = selects[0];
    const actorSel = selects[1];
    if (actionSel instanceof HTMLSelectElement) {
      actionSel.name = `steps[${bo}][action][]`;
    }
    if (actorSel instanceof HTMLSelectElement) {
      actorSel.name = `steps[${bo}][actor][]`;
    }

    body.appendChild(row);
  };

  for (const btn of addStepBtnEls) {
    if (!(btn instanceof HTMLButtonElement)) continue;
    const bo = btn.getAttribute('data-ruleset-add-step') || '';
    btn.addEventListener('click', () => addStepRow(bo));
  }

  document.addEventListener('click', (e) => {
    const t = e.target instanceof Element ? e.target : null;
    if (!t) return;

    const rm = t.closest('[data-row-remove]');
    if (rm instanceof HTMLButtonElement) {
      const tr = rm.closest('tr');
      if (tr) tr.remove();
      return;
    }

    const up = t.closest('[data-row-up]');
    if (up instanceof HTMLButtonElement) {
      const tr = up.closest('tr');
      if (!tr) return;
      const prev = tr.previousElementSibling;
      if (prev) prev.before(tr);
      return;
    }

    const down = t.closest('[data-row-down]');
    if (down instanceof HTMLButtonElement) {
      const tr = down.closest('tr');
      if (!tr) return;
      const next = tr.nextElementSibling;
      if (next) next.after(tr);
      return;
    }
  });

  const tplSelect = document.getElementById('rulesetTemplateSelect');
  const tplLoad = document.getElementById('rulesetTemplateLoad');

  const clearBody = (body) => {
    if (!(body instanceof HTMLElement)) return;
    while (body.firstChild) body.removeChild(body.firstChild);
  };

  const setInput = (el, value) => {
    if (!(el instanceof HTMLInputElement)) return;
    el.value = value || '';
  };

  const loadTemplate = (id) => {
    const tpl = TEMPLATES[id];
    if (!tpl || typeof tpl !== 'object') return;

    // Pool.
    clearBody(poolBody);
    if (Array.isArray(tpl.pool)) {
      for (const m of tpl.pool) {
        if (!m || typeof m !== 'object') continue;
        addMapRow();
        const last = poolBody instanceof HTMLElement ? poolBody.lastElementChild : null;
        if (!(last instanceof HTMLElement)) continue;
        const inputs = Array.from(last.querySelectorAll('input'));
        setInput(inputs[0], String(m.key || ''));
        setInput(inputs[1], String(m.name || ''));
      }
    }

    // Steps for BO1/3/5.
    const stepsByBo = tpl.steps_by_best_of || {};
    for (const bo of [1, 3, 5]) {
      const body = document.querySelector(`[data-ruleset-steps="${bo}"]`);
      clearBody(body);

      const seq = stepsByBo[String(bo)];
      if (!Array.isArray(seq)) continue;

      const normalizeStep = (s) => {
        if (typeof s === 'string') {
          const action = String(s || '').toLowerCase().trim();
          return { action, actor: action === 'decider' ? 'any' : 'alternate' };
        }
        if (s && typeof s === 'object') {
          const action = String(s.action || s.step || '').toLowerCase().trim();
          const actor = String(s.actor || s.by || '').toLowerCase().trim();
          return { action, actor };
        }
        return null;
      };

      const seq2 = [];
      for (const s of seq) {
        const st = normalizeStep(s);
        if (!st) continue;
        if (st.action === 'decider' || !st.action) continue;
        if (st.action !== 'ban' && st.action !== 'pick') continue;
        if (!st.actor || st.actor === 'any') st.actor = 'alternate';
        if (st.actor !== 'starter' && st.actor !== 'other' && st.actor !== 'alternate') st.actor = 'alternate';
        seq2.push(st);
      }

      // Exclude decider; it's always automatic.
      for (let i = 0; i < seq2.length; i++) {
        addStepRow(bo);
        const last = body instanceof HTMLElement ? body.lastElementChild : null;
        if (!(last instanceof HTMLElement)) continue;

        const actionSel = last.querySelector('select[name^="steps"]');
        const actorSel = last.querySelector('select[name*="[actor]"]');
        if (actionSel instanceof HTMLSelectElement) {
          actionSel.value = String(seq2[i].action || 'ban');
        }
        if (actorSel instanceof HTMLSelectElement) {
          actorSel.value = String(seq2[i].actor || ((i % 2 === 0) ? 'starter' : 'other'));
        }
      }
    }
  };

  if (tplLoad instanceof HTMLButtonElement) {
    tplLoad.addEventListener('click', () => {
      const id = (tplSelect instanceof HTMLSelectElement) ? (tplSelect.value || '') : '';
      if (!id) return;
      loadTemplate(id);
    });
  }
})();
</script>
