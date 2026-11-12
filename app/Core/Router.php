<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Minimal route table: method + handler + optional permission.
 * Routes live in /routes/web.php (spec §91). Controllers handle their own
 * action routing internally to keep one page = one controller file.
 */
final class Router
{
    /** @var array<int, array{method:string, pattern:string, handler:string, permission:?string}> */
    private static array $routes = [];

    public static function get(string $pattern, string $handler, ?string $permission = null): void
    {
        self::add('GET', $pattern, $handler, $permission);
    }

    public static function post(string $pattern, string $handler, ?string $permission = null): void
    {
        self::add('POST', $pattern, $handler, $permission);
    }

    private static function add(string $method, string $pattern, string $handler, ?string $permission): void
    {
        self::$routes[] = ['method' => $method, 'pattern' => $pattern, 'handler' => $handler, 'permission' => $permission];
    }

    /** Dispatch the current request; returns handler name or null for fallback. */
    public static function dispatch(): ?array
    {
        $uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $base   = rtrim(dirname($script), '/');
        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        $uri = '/' . ltrim($uri, '/');
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        foreach (self::$routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $route['pattern']) . '$#';
            if (preg_match($regex, $uri, $m)) {
                if ($route['permission'] !== null) {
                    Gate::authorize($route['permission']);
                }
                return ['route' => $route, 'params' => $m];
            }
        }
        return null;
    }
}

