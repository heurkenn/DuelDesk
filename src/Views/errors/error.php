<?php

declare(strict_types=1);

use DuelDesk\View;

/** @var int $code */
/** @var string $heading */
/** @var string $message */
?>

<div class="empty">
    <div class="empty__title"><?= View::e($heading) ?></div>
    <div class="empty__hint"><?= View::e($message) ?></div>
    <div class="inline" style="margin-top: 14px;">
        <a class="btn btn--primary" href="/">Accueil</a>
        <a class="btn btn--ghost" href="/tournaments">Tournois</a>
    </div>
    <div class="muted mono" style="margin-top: 12px;">Code: <?= (int)$code ?></div>
</div>

