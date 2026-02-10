<?php

declare(strict_types=1);

use DuelDesk\View;
use DuelDesk\Support\Auth;
use DuelDesk\Support\Csrf;

$path = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
if ($path !== '/') {
    $path = rtrim($path, '/');
}

$isPublicTournamentRoute = str_starts_with($path, '/t/');

function nav_is_active(string $currentPath, string $prefix): bool
{
    if ($prefix === '/') {
        return $currentPath === '/';
    }

    return $currentPath === $prefix || str_starts_with($currentPath, $prefix . '/');
}

$me = Auth::user();
$isAuthed = $me !== null;
$isAdmin = Auth::isAdmin();
$csrfToken = Csrf::token();
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark">

    <title><?= View::e($title) ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Oxanium:wght@400;500;600;700&family=Silkscreen:wght@400;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    <div class="bg">
        <div class="orb orb-a" aria-hidden="true"></div>
        <div class="orb orb-b" aria-hidden="true"></div>
        <div class="grid" aria-hidden="true"></div>
    </div>

    <header class="topbar">
        <div class="topbar__inner">
            <a class="brand" href="/" aria-label="DuelDesk">
                <span class="brand__mark" aria-hidden="true"></span>
                <span class="brand__name">DuelDesk</span>
            </a>

            <nav class="nav" aria-label="Navigation">
                <a class="nav__link" href="/" <?= nav_is_active($path, '/') ? 'aria-current="page"' : '' ?>>Dashboard</a>
                <?php $isTournaments = nav_is_active($path, '/tournaments') || nav_is_active($path, '/t'); ?>
                <a class="nav__link" href="/tournaments" <?= $isTournaments ? 'aria-current="page"' : '' ?>>Tournois</a>
                <?php if ($isAdmin && !$isPublicTournamentRoute): ?>
                    <a class="nav__link" href="/admin" <?= nav_is_active($path, '/admin') ? 'aria-current="page"' : '' ?>>Admin</a>
                <?php endif; ?>
            </nav>

            <div class="topbar__cta">
                <?php if ($isAdmin && !$isPublicTournamentRoute): ?>
                    <a class="btn btn--primary" href="/tournaments/new">Nouveau tournoi</a>
                <?php endif; ?>

                <?php if ($isAuthed): ?>
                    <span class="userchip" title="<?= View::e((string)($me['username'] ?? '')) ?>">
                        <span class="userchip__dot" aria-hidden="true"></span>
                        <span class="userchip__name"><?= View::e((string)($me['username'] ?? 'compte')) ?></span>
                        <?php if ($isAdmin): ?><span class="pill pill--soft">admin</span><?php endif; ?>
                    </span>

                    <form method="post" action="/logout" class="inline">
                        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                        <button class="btn btn--ghost" type="submit">Logout</button>
                    </form>
                <?php else: ?>
                    <a class="btn btn--ghost" href="/login">Connexion</a>
                    <a class="btn btn--primary" href="/register">Inscription</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="container">
        <?php require __DIR__ . '/partials/flash.php'; ?>
        <?= $content ?>
    </main>

    <footer class="footer">
        <div class="footer__inner">
            <span class="footer__muted">DuelDesk</span>
            <span class="footer__muted">Prototype PHP/JS/SQL</span>
        </div>
    </footer>

    <script src="/assets/js/app.js" defer></script>
</body>
</html>
