<?php
declare(strict_types=1);

namespace App\Core;

use App\Models\AuditLogModel;
use App\Models\UserModel;

/**
 * Authentication service (spec §9).
 * Login throttling, password reset, secure session bootstrapping.
 */
final class Auth
{
    private static ?array $user = null;

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): ?int
    {
        $u = self::user();
        return $u === null ? null : (int) $u['id'];
    }

    public static function user(): ?array
    {
        if (self::$user !== null) {
            return self::$user;
        }
        $userId = Session::get('user_id');
        if (!is_int($userId) && !ctype_digit((string) $userId)) {
            return null;
        }
        $user = UserModel::findActiveById((int) $userId);
        if ($user === null) {
            Session::forget('user_id');
            return null;
        }
        self::$user = $user;
        return $user;
    }

    public static function login(string $identifier, string $password): array
    {
        $identifier = trim($identifier);
        $maxAttempts = (int) Settings::get('max_login_attempts', '5');
        $lockoutMin  = (int) Settings::get('lockout_minutes', '15');
        $ip          = client_ip();

        if (UserModel::isLockedOut($identifier, $ip, $maxAttempts, $lockoutMin)) {
            Logger::security('Login lockout hit', ['identifier' => $identifier, 'ip' => $ip]);
            return [false, 'Too many failed attempts. Try again in ' . $lockoutMin . ' minutes.'];
        }

        $user = UserModel::findByIdentifier($identifier);
        if ($user === null || !password_verify($password, $user['password_hash'])) {
            UserModel::recordLoginAttempt($identifier, $ip, false);
            Logger::security('Failed login', ['identifier' => $identifier, 'ip' => $ip]);
            return [false, 'Invalid credentials.'];
        }
        if ($user['status'] !== 'active') {
            UserModel::recordLoginAttempt($identifier, $ip, false);
            return [false, 'Your account is not active. Contact the system administrator.'];
        }

        UserModel::recordLoginAttempt($identifier, $ip, true);
        Session::regenerate();                       // session fixation defense (§87)
        Session::set('user_id', (int) $user['id']);
        Session::set('logged_in_at', time());
        UserModel::touchLastLogin((int) $user['id'], $ip);
        AuditLogModel::log(null, 'login', 'user', (int) $user['id'], null, ['ip' => $ip]);
        self::$user = null; // reload fresh
        return [true, 'Welcome back, ' . $user['full_name'] . '.'];
    }

    public static function logout(): void
    {
        $userId = Session::get('user_id');
        if ($userId !== null) {
            AuditLogModel::log(null, 'logout', 'user', (int) $userId, null, null);
        }
        self::$user = null;
        Session::destroy();
    }

    public static function checkTimeout(): void
    {
        $timeoutMin = (int) Settings::get('session_timeout_minutes', '30');
        if (!Session::get('user_id')) {
            return;
        }
        $last = Session::get('last_activity', time());
        if (time() - $last > $timeoutMin * 60) {
            self::logout();
            Session::flash('warning', 'Your session expired. Please log in again.');
            return;
        }
        Session::set('last_activity', time());
    }

    /** Create a single-use password reset token; returns raw token or null if user unknown. */
    public static function createPasswordReset(string $email): ?string
    {
        $user = UserModel::findByEmail($email);
        if ($user === null) {
            return null; // do not reveal existence
        }
        $token = bin2hex(random_bytes(32));
        Database::insert('password_resets', [
            'user_id'    => (int) $user['id'],
            'token_hash' => hash('sha256', $token),
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
        ]);
        Logger::security('Password reset requested', ['user_id' => $user['id']]);
        return $token;
    }

    public static function resetPassword(string $token, string $newPassword): bool
    {
        $row = Database::one(
            'SELECT * FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()',
            [hash('sha256', $token)]
        );
        if ($row === null) {
            return false;
        }
        $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        Database::update('users', ['password_hash' => $hash, 'must_change_password' => 0], 'id = ?', [(int) $row['user_id']]);
        Database::update('password_resets', ['used_at' => date('Y-m-d H:i:s')], 'id = ?', [(int) $row['id']]);
        AuditLogModel::log(null, 'password_reset', 'user', (int) $row['user_id'], null, null);
        return true;
    }

    public static function changePassword(int $userId, string $current, string $new): bool
    {
        $user = UserModel::findActiveById($userId);
        if ($user === null || !password_verify($current, $user['password_hash'])) {
            return false;
        }
        $hash = password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]);
        Database::update('users', ['password_hash' => $hash, 'must_change_password' => 0], 'id = ?', [$userId]);
        AuditLogModel::log(null, 'password_changed', 'user', $userId, null, null);
        return true;
    }
}
