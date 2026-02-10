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
        <h1 class="pagehead__title">Inscription</h1>
        <p class="pagehead__lead">Cree ton compte pour acceder a l'espace admin (si tu es admin).</p>
    </div>
</div>

<form class="card form" method="post" action="/register" novalidate>
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
    <input type="hidden" name="redirect" value="<?= View::e($redirect) ?>">

    <div class="card__header">
        <h2 class="card__title">Compte</h2>
        <p class="card__subtitle">Username + mot de passe.</p>
    </div>

    <div class="card__body">
        <div class="form__grid">
            <label class="field field--full">
                <span class="field__label">Username</span>
                <input class="input<?= field_error($errors, 'username') ? ' input--error' : '' ?>" name="username" value="<?= View::e($old['username']) ?>" placeholder="Ex: Alex" required maxlength="32" autocomplete="username">
                <?php if (field_error($errors, 'username')): ?>
                    <span class="field__error"><?= View::e((string)field_error($errors, 'username')) ?></span>
                <?php endif; ?>
            </label>

            <label class="field">
                <span class="field__label">Mot de passe</span>
                <input class="input<?= field_error($errors, 'password') ? ' input--error' : '' ?>" type="password" name="password" placeholder="Min 8 caracteres" required autocomplete="new-password">
                <?php if (field_error($errors, 'password')): ?>
                    <span class="field__error"><?= View::e((string)field_error($errors, 'password')) ?></span>
                <?php endif; ?>
            </label>

            <label class="field">
                <span class="field__label">Confirmer</span>
                <input class="input<?= field_error($errors, 'password_confirm') ? ' input--error' : '' ?>" type="password" name="password_confirm" placeholder="Retape le mot de passe" required autocomplete="new-password">
                <?php if (field_error($errors, 'password_confirm')): ?>
                    <span class="field__error"><?= View::e((string)field_error($errors, 'password_confirm')) ?></span>
                <?php endif; ?>
            </label>
        </div>

        <div class="form__hint">
            Deja un compte ? <a class="link" href="/login<?= $redirect !== '' ? '?redirect=' . View::e(urlencode($redirect)) : '' ?>">Connexion</a>
        </div>
    </div>

    <div class="card__footer">
        <a class="btn btn--ghost" href="/">Annuler</a>
        <button class="btn btn--primary" type="submit">Creer le compte</button>
    </div>
</form>
