<?php

declare(strict_types=1);

namespace DuelDesk\Http;

final class Response
{
    public static function redirect(string $path): never
    {
        header('Location: ' . $path);
        exit;
    }

    public static function notFound(): never
    {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo "404 Not Found\n";
        exit;
    }

    public static function badRequest(string $message = 'Bad Request'): never
    {
        http_response_code(400);
        header('Content-Type: text/plain; charset=utf-8');
        echo $message . "\n";
        exit;
    }

    public static function forbidden(string $message = 'Forbidden'): never
    {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo $message . "\n";
        exit;
    }
}
