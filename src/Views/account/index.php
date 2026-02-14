<?php

declare(strict_types=1);

use DuelDesk\View;
use DuelDesk\Support\Discord;

/** @var array<string, mixed> $me */
/** @var string $csrfToken */
/** @var bool $discordConfigured */

$discordId = (string)($me['discord_user_id'] ?? '');
$discordUsername = (string)($me['discord_username'] ?? '');
$discordGlobalName = (string)($me['discord_global_name'] ?? '');
$discordAvatarHash = (string)($me['discord_avatar'] ?? '');
$discordAvatarUrl = $discordId !== '' ? (Discord::avatarCdnUrl($discordId, $discordAvatarHash, 96) ?? '') : '';
?>

<div class="pagehead">
    <div>
        <h1 class="pagehead__title">Compte</h1>
        <p class="pagehead__lead">Gerer ton profil DuelDesk et tes integrations.</p>
    </div>
    <div class="pagehead__actions">
        <a class="btn btn--ghost" href="/tournaments">Tournois</a>
    </div>
</div>

<section class="cards">
    <div class="card">
        <div class="card__header">
            <div class="card__title">DuelDesk</div>
            <div class="pill pill--soft"><?= View::e((string)($me['role'] ?? 'user')) ?></div>
        </div>
        <div class="card__body">
            <dl class="dl">
                <div class="dl__row">
                    <dt>Username</dt>
                    <dd><span class="mono"><?= View::e((string)($me['username'] ?? '')) ?></span></dd>
                </div>
                <div class="dl__row">
                    <dt>ID</dt>
                    <dd><?= (int)($me['id'] ?? 0) ?></dd>
                </div>
            </dl>
        </div>
    </div>

    <div class="card">
        <div class="card__header">
            <div class="card__title">
                Discord
                <?php if ($discordAvatarUrl !== ''): ?>
                    <img class="gameicon" src="<?= View::e($discordAvatarUrl) ?>" alt="" loading="lazy" width="22" height="22" referrerpolicy="no-referrer" style="margin-left: 10px; border-radius: 999px;">
                <?php endif; ?>
            </div>
            <?php if (!$discordConfigured): ?>
                <div class="pill pill--soft">non configure</div>
            <?php elseif ($discordId !== ''): ?>
                <div class="pill">lie</div>
            <?php else: ?>
                <div class="pill pill--soft">non lie</div>
            <?php endif; ?>
        </div>
        <div class="card__body">
            <?php if (!$discordConfigured): ?>
                <div class="empty">
                    <div class="empty__title">Discord non configure</div>
                    <div class="empty__hint">
                        Ajoute `DISCORD_CLIENT_ID`, `DISCORD_CLIENT_SECRET` et `DISCORD_REDIRECT_URI` dans l'env.
                    </div>
                </div>
            <?php elseif ($discordId === ''): ?>
                <p class="muted">Lie ton compte Discord pour activer les features (check-in, roles, annonces).</p>
                <a class="btn btn--primary" href="/account/discord/connect">Connecter Discord</a>
            <?php else: ?>
                <dl class="dl">
                    <div class="dl__row">
                        <dt>Discord ID</dt>
                        <dd><span class="mono"><?= View::e($discordId) ?></span></dd>
                    </div>
                    <div class="dl__row">
                        <dt>Username</dt>
                        <dd><?= $discordUsername !== '' ? View::e($discordUsername) : '<span class="muted">-</span>' ?></dd>
                    </div>
                    <div class="dl__row">
                        <dt>Global name</dt>
                        <dd><?= $discordGlobalName !== '' ? View::e($discordGlobalName) : '<span class="muted">-</span>' ?></dd>
                    </div>
                </dl>

                <form method="post" action="/account/discord/disconnect" class="inline" data-confirm="Deconnecter Discord ?">
                    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                    <button class="btn btn--ghost" type="submit">Deconnecter</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>
