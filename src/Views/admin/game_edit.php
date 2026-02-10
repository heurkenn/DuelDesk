<?php

declare(strict_types=1);

use DuelDesk\View;

/** @var array<string, mixed> $game */
/** @var array{name:string} $old */
/** @var array<string,string> $errors */
/** @var string $csrfToken */
/** @var array{width:int,height:int,mime:string,ext:string,label:string} $imageReq */

$id = (int)($game['id'] ?? 0);

function field_error_edit(array $errors, string $key): ?string
{
    return isset($errors[$key]) ? (string)$errors[$key] : null;
}
?>

<div class="pagehead">
    <div>
        <h1 class="pagehead__title">Modifier un jeu</h1>
        <p class="pagehead__lead">Change le nom et/ou remplace l'image.</p>
    </div>
    <div class="pagehead__actions">
        <a class="btn btn--ghost" href="/admin/games">Retour</a>
    </div>
</div>

<div class="split">
    <form class="card form" method="post" action="/admin/games/<?= (int)$id ?>" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">

        <div class="card__header">
            <h2 class="card__title">Infos</h2>
            <p class="card__subtitle">Nom + image (optionnel) au format unique (<?= View::e($imageReq['label']) ?>).</p>
        </div>

        <div class="card__body">
            <div class="meta" style="margin-bottom: 14px;">
                <img class="gameicon" src="<?= View::e((string)($game['image_path'] ?? '')) ?>" alt="" width="28" height="28" loading="lazy">
                <span class="mono">#<?= (int)$id ?></span>
                <span class="pill pill--soft"><?= View::e((string)($game['slug'] ?? '')) ?></span>
            </div>

            <label class="field">
                <span class="field__label">Nom</span>
                <input class="input<?= field_error_edit($errors, 'name') ? ' input--error' : '' ?>" name="name" value="<?= View::e($old['name'] ?? '') ?>" required maxlength="80">
                <?php if (field_error_edit($errors, 'name')): ?>
                    <span class="field__error"><?= View::e((string)field_error_edit($errors, 'name')) ?></span>
                <?php endif; ?>
            </label>

            <label class="field" style="margin-top: 14px;">
                <span class="field__label">Remplacer l'image (optionnel)</span>
                <input class="input<?= field_error_edit($errors, 'image') ? ' input--error' : '' ?>" type="file" name="image" accept="image/png">
                <span class="muted">Format requis: <?= View::e($imageReq['label']) ?> (<?= View::e($imageReq['mime']) ?>).</span>
                <?php if (field_error_edit($errors, 'image')): ?>
                    <span class="field__error"><?= View::e((string)field_error_edit($errors, 'image')) ?></span>
                <?php endif; ?>
            </label>
        </div>

        <div class="card__footer">
            <a class="btn btn--ghost" href="/admin/games">Annuler</a>
            <button class="btn btn--primary" type="submit">Enregistrer</button>
        </div>
    </form>

    <section class="card">
        <div class="card__header">
            <h2 class="card__title">Suppression</h2>
            <p class="card__subtitle">Supprime le jeu (les tournois gardent leur nom, mais l'image n'apparaitra plus).</p>
        </div>
        <div class="card__body">
            <div class="empty empty--compact">
                <div class="empty__title">Action irreversible</div>
                <div class="empty__hint">Pense a verifier qu'il n'est plus utilise.</div>
            </div>
        </div>
        <div class="card__footer">
            <form method="post" action="/admin/games/<?= (int)$id ?>/delete" class="inline" data-confirm="Supprimer ce jeu ?">
                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                <button class="btn btn--danger" type="submit">Supprimer</button>
            </form>
        </div>
    </section>
</div>

