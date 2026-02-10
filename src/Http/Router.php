<?php

declare(strict_types=1);

namespace DuelDesk\Http;

use RuntimeException;

final class Router
{
    /** @var array<string, list<array{regex: string, handler: callable|array}>> */
    private array $routes = [];

    public function get(string $pattern, callable|array $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable|array $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    private function add(string $method, string $pattern, callable|array $handler): void
    {
        $regex = $this->compile($pattern);
        $this->routes[$method][] = ['regex' => $regex, 'handler' => $handler];
    }

    public function dispatch(string $method, string $uri): void
    {
        $method = strtoupper($method);

        if ($method === 'POST' && isset($_POST['_method'])) {
            $override = strtoupper((string)$_POST['_method']);
            if (in_array($override, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                $method = $override;
            }
        }

        $path = (string)(parse_url($uri, PHP_URL_PATH) ?? '/');
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        foreach ($this->routes[$method] ?? [] as $route) {
            $matches = [];
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }

            $params = [];
            foreach ($matches as $key => $value) {
                if (!is_string($key)) {
                    continue;
                }
                $params[$key] = $value;
            }

            $handler = $route['handler'];
            if (is_array($handler) && isset($handler[0], $handler[1]) && is_string($handler[0])) {
                $controller = new $handler[0]();
                $callable = [$controller, (string)$handler[1]];
            } else {
                $callable = $handler;
            }

            if (!is_callable($callable)) {
                throw new RuntimeException('Route handler is not callable');
            }

            $callable($params);
            return;
        }

        Response::notFound();
    }

    private function compile(string $pattern): string
    {
        // Support patterns like /tournaments/{id:\d+}. Everything else is treated literally.
        $parts = preg_split('/(\{[a-zA-Z_][a-zA-Z0-9_]*(?::[^}]+)?\})/', $pattern, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            throw new RuntimeException('Failed to compile route pattern');
        }

        $regex = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            if (preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_]*)(?::([^}]+))?\}$/', $part, $m) === 1) {
                $name = $m[1];
                $sub = $m[2] ?? '[^/]+';
                $regex .= '(?P<' . $name . '>' . $sub . ')';
                continue;
            }

            $regex .= preg_quote($part, '#');
        }

        return '#^' . $regex . '$#';
    }
}
