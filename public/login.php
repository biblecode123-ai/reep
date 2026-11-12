<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Controllers\AuthController;
use App\Core\Auth;
use App\Core\Csrf;

$action = (string) ($_GET['action'] ?? ($_SERVER['REQUEST_METHOD'] === 'POST' ? 'do_login' : 'form'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verifyRequest();
    match ($action) {
        'do_login' => AuthController::login(),
        default    => AuthController::showLogin(),
    };
    exit;
}

match ($action) {
    'forgot'    => AuthController::showForgot(),
    'send_reset'=> AuthController::sendReset(),
    'reset_password' => AuthController::showReset(),
    'do_reset'  => AuthController::doReset(),
    default     => AuthController::showLogin(),
};
