<?php

declare(strict_types=1);

namespace DuelDesk;

use RuntimeException;

final class View
{
    /** @param array<string, mixed> $params */
    public static function render(string $view, array $params = []): void
    {
        $viewPath = __DIR__ . '/Views/' . $view . '.php';
        if (!is_file($viewPath)) {
            throw new RuntimeException("View not found: {$view}");
        }

        $title = (string)($params['title'] ?? 'DuelDesk');

        // Expose params as local variables to the view.
        extract($params, EXTR_SKIP);

        ob_start();
        require $viewPath;
        $content = (string)ob_get_clean();

        require __DIR__ . '/Views/layout.php';
    }

    public static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
