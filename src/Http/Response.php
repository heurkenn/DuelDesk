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
        header('Content-Type: text/html; charset=utf-8');
        \DuelDesk\View::render('errors/error', [
            'title' => '404 | DuelDesk',
            'code' => 404,
            'heading' => '404',
            'message' => 'Page introuvable.',
        ]);
        exit;
    }

    public static function badRequest(string $message = 'Bad Request'): never
    {
        http_response_code(400);
        header('Content-Type: text/html; charset=utf-8');
        \DuelDesk\View::render('errors/error', [
            'title' => '400 | DuelDesk',
            'code' => 400,
            'heading' => 'Bad Request',
            'message' => $message,
        ]);
        exit;
    }

    public static function forbidden(string $message = 'Forbidden'): never
    {
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        \DuelDesk\View::render('errors/error', [
            'title' => '403 | DuelDesk',
            'code' => 403,
            'heading' => 'Forbidden',
            'message' => $message,
        ]);
        exit;
    }
}
