<?php

declare(strict_types=1);

use DuelDesk\View;
use DuelDesk\Services\PickBanEngine;

/** @var list<array<string, mixed>> $games */
/** @var list<array<string, mixed>> $rulesets */
/** @var int|null $filterGameId */
/** @var string $csrfToken */

$filterGameIdValue = $filterGameId !== null && $filterGameId > 0 ? (string)$filterGameId : '';

$summarize = static function (string $json): array {
    $decoded = PickBanEngine::decodeJson($json);
    if (!is_array($decoded)) {
        return ['maps' => 0, 'bos' => ''];
    }

    $pool = isset($decoded['pool']) && is_array($decoded['pool']) ? $decoded['pool'] : [];
    $maps = count($pool);
    $bos = [];
    if (isset($decoded['steps_by_best_of']) && is_array($decoded['steps_by_best_of'])) {
        foreach ($decoded['steps_by_best_of'] as $k => $_steps) {
            $bo = is_int($k) || is_string($k) ? (int)$k : 0;
            if ($bo > 0) {
                $bos[] = $bo;
            }
        }
    }
    sort($bos);
    $boText = $bos !== [] ? implode(', ', array_map(static fn (int $bo): string => 'BO' . $bo, $bos)) : '';
    return ['maps' => $maps, 'bos' => $boText];
};
?>

<div class="pagehead">
    <div>
        <h1 class="pagehead__title">Rulesets</h1>
        <p class="pagehead__lead">Presets de pick/ban (maps) reutilisables par tournoi.</p>
    </div>
    <div class="pagehead__actions">
        <a class="btn btn--ghost" href="/admin">Admin</a>
        <a class="btn btn--primary" href="/admin/rulesets/new">Nouveau ruleset</a>
    </div>
</div>

<section class="card">
    <div class="card__header">
        <div>
            <h2 class="card__title">Liste</h2>
            <p class="card__subtitle">Filtrer et editer.</p>
        </div>
    </div>
    <div class="card__body">
        <form class="form" method="get" action="/admin/rulesets">
            <div class="form__grid">
                <label class="field">
                    <span class="field__label">Jeu</span>
                    <select class="select" name="game_id">
                        <option value="">Tous</option>
                        <?php foreach ($games as $g): ?>
                            <?php $gid = (int)($g['id'] ?? 0); ?>
                            <option value="<?= (int)$gid ?>" <?= (string)$gid === $filterGameIdValue ? 'selected' : '' ?>>
                                <?= View::e((string)($g['name'] ?? '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div class="field" style="align-self: end;">
                    <button class="btn btn--ghost" type="submit">Filtrer</button>
                </div>
            </div>
        </form>

        <?php if ($rulesets === []): ?>
            <div class="empty" style="margin-top: 12px;">
                <div class="empty__title">Aucun ruleset</div>
                <div class="empty__hint">Cree un ruleset pour activer le pick/ban sur tes tournois.</div>
            </div>
        <?php else: ?>
            <div class="tablewrap" style="margin-top: 12px;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Jeu</th>
                            <th>Pool</th>
                            <th>BO</th>
                            <th class="table__right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rulesets as $r): ?>
                            <?php
                                $id = (int)($r['id'] ?? 0);
                                $name = (string)($r['name'] ?? '');
                                $gameName = (string)($r['game_name'] ?? '');
                                $json = is_string($r['ruleset_json'] ?? null) ? (string)$r['ruleset_json'] : '';
                                $sum = $summarize($json);
                            ?>
                            <tr>
                                <td class="table__strong"><?= View::e($name !== '' ? $name : '#') ?></td>
                                <td><?= View::e($gameName !== '' ? $gameName : '-') ?></td>
                                <td class="mono"><?= (int)$sum['maps'] ?> map(s)</td>
                                <td class="mono"><?= View::e((string)$sum['bos']) ?></td>
                                <td class="table__right">
                                    <a class="btn btn--ghost btn--compact" href="/admin/rulesets/<?= (int)$id ?>">Edit</a>
                                    <form class="inline" method="post" action="/admin/rulesets/<?= (int)$id ?>/delete" data-confirm="Supprimer ce ruleset ?">
                                        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                        <button class="btn btn--danger btn--compact" type="submit">Supprimer</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>

