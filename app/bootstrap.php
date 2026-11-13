<?php
declare(strict_types=1);

/**
 * MWU RPEE — Application bootstrap.
 * Every entry point includes this file. Sets error handling, loads config,
 * autoloader and global helpers, starts the hardened session.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../app/autoload.php';

use App\Core\Auth;
use App\Core\Session;

// ---- Error handling: never leak internals to users (spec §109, §110) --------
if (true) { // APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    if (!is_dir(LOG_PATH)) {
        @mkdir(LOG_PATH, 0775, true);
    }
    ini_set('error_log', LOG_PATH . '/php_error.log');
    error_reporting(E_ALL);
}

set_exception_handler(static function (Throwable $e): void {
    \App\Core\Logger::error('Uncaught exception', ['msg' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
    if (APP_DEBUG) {
        http_response_code(500);
        echo '<pre>' . htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') . '</pre>';
        exit;
    }
    $ref = \App\Core\Logger::errorRef();
    http_response_code(500);
    if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest' || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'message' => 'Something went wrong. Reference: ' . $ref]);
    } else {
        $errorMessage = 'Something went wrong. Reference: ' . $ref;
        include APP_PATH . '/views/errors/error.php';
    }
    exit;
});

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// ---- Timezone / locale ------------------------------------------------------
\App\Core\Logger::info('Bootstrap calling Session::start()');
Session::start();     // hardened session (config/security.php)

// Helpers must load BEFORE checkTimeout: an expired-session logout writes an
// audit row that uses client_ip() and other global helpers.
require_once APP_PATH . '/helpers/functions.php';

Auth::checkTimeout(); // idle timeout enforcement (spec §9)

// Global class aliases so views (global namespace) can use Gate::, Auth::, etc.
// without per-file imports.
\class_alias(\App\Core\Auth::class, 'Auth');
\class_alias(\App\Core\Gate::class, 'Gate');
\class_alias(\App\Core\Database::class, 'Database');
\class_alias(\App\Core\Session::class, 'Session');
\class_alias(\App\Core\Csrf::class, 'Csrf');
\class_alias(\App\Core\Settings::class, 'Settings');
\class_alias(\App\Core\Validator::class, 'Validator');
\class_alias(\App\Core\Audit::class, 'Audit');
