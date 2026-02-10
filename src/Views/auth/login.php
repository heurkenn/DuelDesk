<?php

declare(strict_types=1);

use DuelDesk\View;

/** @var array{username:string} $old */
/** @var array<string,string> $errors */
/** @var string $csrfToken */
/** @var string $redirect */

function field_error(array $errors, string $key): ?string
{
    return isset($errors[$key]) ? (string)$errors[$key] : null;
}
?>

<div class="pagehead">
    <div>
        <h1 class="pagehead__title">Connexion</h1>
        <p class="pagehead__lead">Accede au dashboard admin si tu as les droits.</p>
    </div>
</div>

<form class="card form" method="post" action="/login" novalidate>
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
    <input type="hidden" name="redirect" value="<?= View::e($redirect) ?>">

    <div class="card__header">
        <h2 class="card__title">Identifiants</h2>
        <p class="card__subtitle">Username + mot de passe.</p>
    </div>

    <div class="card__body">
        <div class="form__grid">
            <label class="field">
                <span class="field__label">Username</span>
                <input class="input<?= field_error($errors, 'username') ? ' input--error' : '' ?>" name="username" value="<?= View::e($old['username']) ?>" placeholder="Ex: Alex" required maxlength="32" autocomplete="username">
                <?php if (field_error($errors, 'username')): ?>
                    <span class="field__error"><?= View::e((string)field_error($errors, 'username')) ?></span>
                <?php endif; ?>
            </label>

            <label class="field">
                <span class="field__label">Mot de passe</span>
                <input class="input<?= field_error($errors, 'password') ? ' input--error' : '' ?>" type="password" name="password" required autocomplete="current-password">
                <?php if (field_error($errors, 'password')): ?>
                    <span class="field__error"><?= View::e((string)field_error($errors, 'password')) ?></span>
                <?php endif; ?>
            </label>
        </div>

        <div class="form__hint">
            Pas de compte ? <a class="link" href="/register<?= $redirect !== '' ? '?redirect=' . View::e(urlencode($redirect)) : '' ?>">Inscription</a>
        </div>
    </div>

    <div class="card__footer">
        <a class="btn btn--ghost" href="/">Annuler</a>
        <button class="btn btn--primary" type="submit">Se connecter</button>
    </div>
</form>
