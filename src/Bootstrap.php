<?php

declare(strict_types=1);

// Project root.
define('DUELDESK_ROOT', dirname(__DIR__));

error_reporting(E_ALL);

spl_autoload_register(static function (string $class): void {
    $prefix = 'DuelDesk\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($path)) {
        require $path;
    }
});

// Load .env if present (useful outside Docker).
\DuelDesk\Support\Env::load(DUELDESK_ROOT . '/.env');

$env = getenv('APP_ENV') ?: 'dev';
ini_set('display_errors', $env === 'dev' ? '1' : '0');
ini_set('log_errors', '1');
$logDir = DUELDESK_ROOT . '/storage/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}
if (is_dir($logDir) && is_writable($logDir)) {
    ini_set('error_log', $logDir . '/php-error.log');
} else {
    // In Docker the mounted volume is often not writable by php-fpm's user.
    ini_set('error_log', 'php://stderr');
}

session_name('dueldesk');
// NOTE: behind a reverse proxy, HTTPS might be terminated upstream.
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
if (!$isHttps) {
    $xfp = strtolower(trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
    if ($xfp === 'https') {
        $isHttps = true;
    }
}
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => $isHttps,
]);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

set_exception_handler(static function (Throwable $e): void {
    http_response_code(500);

    $env = getenv('APP_ENV') ?: 'dev';
    if ($env === 'dev') {
        header('Content-Type: text/plain; charset=utf-8');
        echo "Uncaught exception: {$e->getMessage()}\n\n{$e->getTraceAsString()}\n";
        return;
    }

    header('Content-Type: text/html; charset=utf-8');
    try {
        \DuelDesk\View::render('errors/error', [
            'title' => '500 | DuelDesk',
            'code' => 500,
            'heading' => '500',
            'message' => 'Erreur interne du serveur.',
        ]);
    } catch (Throwable) {
        header('Content-Type: text/plain; charset=utf-8');
        echo "Internal Server Error\n";
    }
});
