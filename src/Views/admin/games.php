<?php

declare(strict_types=1);

use DuelDesk\View;

/** @var list<array<string, mixed>> $games */
/** @var array{name:string} $old */
/** @var array<string,string> $errors */
/** @var string $csrfToken */
/** @var array{width:int,height:int,mime:string,ext:string,label:string} $imageReq */

function field_error(array $errors, string $key): ?string
{
    return isset($errors[$key]) ? (string)$errors[$key] : null;
}
?>

<div class="pagehead">
    <div>
        <h1 class="pagehead__title">Jeux</h1>
        <p class="pagehead__lead">Catalogue des jeux disponibles pour creer des tournois.</p>
    </div>
    <div class="pagehead__actions">
        <a class="btn btn--ghost" href="/admin">Retour</a>
    </div>
</div>

<form class="card form" method="post" action="/admin/games" enctype="multipart/form-data" novalidate>
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">

    <div class="card__header">
        <h2 class="card__title">Ajouter un jeu</h2>
        <p class="card__subtitle">Nom + image au format unique (<?= View::e($imageReq['label']) ?>).</p>
    </div>

    <div class="card__body">
        <div class="form__grid">
            <label class="field field--full">
                <span class="field__label">Nom du jeu</span>
                <input class="input<?= field_error($errors, 'name') ? ' input--error' : '' ?>" name="name" value="<?= View::e($old['name']) ?>" placeholder="Ex: Street Fighter 6" required maxlength="80">
                <?php if (field_error($errors, 'name')): ?>
                    <span class="field__error"><?= View::e((string)field_error($errors, 'name')) ?></span>
                <?php endif; ?>
            </label>

            <label class="field field--full">
                <span class="field__label">Image</span>
                <input class="input<?= field_error($errors, 'image') ? ' input--error' : '' ?>" type="file" name="image" accept="image/png" required>
                <span class="muted">Format requis: <?= View::e($imageReq['label']) ?> (<?= View::e($imageReq['mime']) ?>).</span>
                <?php if (field_error($errors, 'image')): ?>
                    <span class="field__error"><?= View::e((string)field_error($errors, 'image')) ?></span>
                <?php endif; ?>
            </label>
        </div>
    </div>

    <div class="card__footer">
        <button class="btn btn--primary" type="submit">Ajouter</button>
    </div>
</form>

<section class="section">
    <div class="section__header">
        <h2 class="section__title">Jeux existants</h2>
        <div class="section__meta"><?= count($games) ?> jeu(x)</div>
    </div>

    <?php if ($games === []): ?>
        <div class="empty">
            <div class="empty__title">Aucun jeu</div>
            <div class="empty__hint">Ajoute un jeu pour debloquer la creation de tournois.</div>
        </div>
    <?php else: ?>
        <div class="gamegrid">
            <?php foreach ($games as $g): ?>
                <?php $gid = (int)($g['id'] ?? 0); ?>
                <div class="card gamecard">
                    <div class="gamecard__media">
                        <img class="gamecard__img" src="<?= View::e((string)$g['image_path']) ?>" alt="<?= View::e((string)$g['name']) ?>">
                    </div>
                    <div class="card__body">
                        <div class="gamecard__name"><?= View::e((string)$g['name']) ?></div>
                        <div class="muted mono"><?= View::e((string)$g['slug']) ?></div>
                    </div>
                    <div class="card__footer">
                        <a class="btn btn--ghost btn--compact" href="/admin/games/<?= (int)$gid ?>">Modifier</a>
                        <form method="post" action="/admin/games/<?= (int)$gid ?>/delete" class="inline" data-confirm="Supprimer ce jeu ?">
                            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                            <button class="btn btn--danger btn--compact" type="submit">Supprimer</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
