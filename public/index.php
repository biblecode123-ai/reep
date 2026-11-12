<?php
declare(strict_types=1);

/**
 * Front controller (spec §91). All pages flow through:
 * bootstrap → AuthMiddleware → CsrfMiddleware (POST) → permission check → controller.
 */

require_once __DIR__ . '/../app/bootstrap.php';
if (!defined('BASE_PATH')) {
    die('BASE_PATH is not defined. Check bootstrap.php and config/app.php');
}
\App\Core\Logger::info('Index request session', ['session' => $_SESSION]);
use App\Core\Gate;

$routes = require BASE_PATH . '/routes/web.php';
$action = (string) ($_GET['action'] ?? '');

// Public routes never require login.
$publicActions = ['forgot', 'send_reset', 'reset_password', 'do_reset'];
if (!in_array($action, $publicActions, true)) {
    \App\Middleware\AuthMiddleware::handle();
}

if (!isset($routes[$action])) {
    if ($action === '') {
        header('Location: index.php');
        exit;
    }
    http_response_code(404);
    include APP_PATH . '/views/errors/404.php';
    exit;
}

[$controllerClass, $method, $permission] = $routes[$action];

// Server-side authorization before any controller code runs (§87, Rule 5).
if ($permission !== null) {
    Gate::authorize($permission);
}

// CSRF verification for every state-changing request (§87).
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    \App\Middleware\CsrfMiddleware::handle();
}

$controllerClass::$method();
