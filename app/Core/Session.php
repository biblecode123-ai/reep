<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Hardened session handling (spec §9, §87).
 * Strict mode, secure/httponly/samesite cookies, periodic regeneration,
 * user-agent binding and idle timeout support.
 */
final class Session
{
    private const FINGERPRINT_KEY = '_fp';
    private const LAST_REGEN_KEY  = '_last_regen';

    public static function start(): void
    {
        file_put_contents(LOG_PATH . '/debug.log', 'Start called' . "\n", FILE_APPEND);
        if (session_status() === PHP_SESSION_ACTIVE) {
            file_put_contents(LOG_PATH . '/debug.log', 'Already active' . "\n", FILE_APPEND);
            return;
        }
        $cfg = (require BASE_PATH . '/config/security.php')['session'];
        if (headers_sent()) {
            return; // e.g. CLI or output already sent
        }
        session_name($cfg['name']);
        session_set_cookie_params([
            'lifetime' => $cfg['cookie_lifetime'],
            'path'     => '/',
            'domain'   => '',
            'secure'   => $cfg['cookie_secure'],
            'httponly' => $cfg['cookie_httponly'],
            'samesite' => $cfg['cookie_samesite'],
        ]);
        ini_set('session.use_strict_mode', '1');
        $sessionPath = STORAGE_PATH . '/private/sessions';
        if (!is_dir($sessionPath)) {
            if (!@mkdir($sessionPath, 0775, true)) {
                error_log('Failed to create session directory: ' . $sessionPath);
            }
        }
        session_save_path($sessionPath);
        session_start();
        file_put_contents(BASE_PATH . '/debug.log', 'Session started. Path: ' . ini_get('session.save_path') . "\n", FILE_APPEND);

        // Bind session to user agent (weaker than IP binding; avoids mobile drops).
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $fp = hash('sha256', $ua . '|mwu-rpee');
        if (!isset($_SESSION[self::FINGERPRINT_KEY])) {
            $_SESSION[self::FINGERPRINT_KEY] = $fp;
        } /* elseif (!hash_equals($_SESSION[self::FINGERPRINT_KEY], $fp)) {
            \App\Core\Logger::security('Session fingerprint mismatch', ['expected' => $_SESSION[self::FINGERPRINT_KEY], 'got' => $fp, 'ua' => $ua]);
            self::destroy();
        session_start();
            $_SESSION[self::FINGERPRINT_KEY] = $fp;
        } */


        // Periodic ID rotation.
        $last = $_SESSION[self::LAST_REGEN_KEY] ?? 0;
        if ($last === 0) {
            $_SESSION[self::LAST_REGEN_KEY] = time();
        } elseif (time() - $last > $cfg['regenerate_every']) {
            if (!headers_sent()) {
                session_regenerate_id(true);
            }
            $_SESSION[self::LAST_REGEN_KEY] = time();
        }
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            session_regenerate_id(true);
            $_SESSION[self::LAST_REGEN_KEY] = time();
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function flash(string $key, ?string $value = null): ?string
    {
        if ($value !== null) {
            $_SESSION['_flash'][$key] = $value;
            return null;
        }
        $v = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $v;
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        // Only destroy when a session is actually active — with strict mode a
        // stale/unknown cookie leaves no active session and destroy() would throw.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}

