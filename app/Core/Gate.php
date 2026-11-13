<?php
declare(strict_types=1);

namespace App\Core;

use App\Models\UserModel;

/**
 * Centralized authorization (spec §8, §95; Rule 5).
 * RBAC with per-request permission cache. Entity-level ownership decisions
 * are layered on top by services (e.g. assigned evaluator, PI of project).
 */
final class Gate
{
    private static ?array $permissionCache = null;
    private static ?array $roleCache = null;
    private static ?array $responsibilityCache = null;

    /** All permissions held by the current user (union of role grants). */
    public static function permissions(): array
    {
        if (self::$permissionCache !== null) {
            return self::$permissionCache;
        }
        $userId = Auth::id();
        self::$permissionCache = $userId === null ? [] : UserModel::permissionsFor((int) $userId);
        return self::$permissionCache;
    }

    public static function roles(): array
    {
        if (self::$roleCache !== null) {
            return self::$roleCache;
        }
        $userId = Auth::id();
        self::$roleCache = $userId === null ? [] : UserModel::roleNamesFor((int) $userId);
        return self::$roleCache;
    }

    public static function allows(string ...$permissionNames): bool
    {
        $held = self::permissions();
        foreach ($permissionNames as $p) {
            if (in_array($p, $held, true)) {
                return true;
            }
        }
        return false;
    }

    public static function denies(string ...$permissionNames): bool
    {
        return !self::allows(...$permissionNames);
    }

    public static function hasRole(string $roleName): bool
    {
        return in_array($roleName, self::roles(), true);
    }

    public static function responsibilities(): array
    {
        if (self::$responsibilityCache !== null) {
            return self::$responsibilityCache;
        }
        $userId = Auth::id();
        self::$responsibilityCache = $userId === null ? [] : UserModel::responsibilityNamesFor((int) $userId);
        return self::$responsibilityCache;
    }

    public static function hasResponsibility(string $name): bool
    {
        return in_array($name, self::responsibilities(), true);
    }

    public static function isAdmin(): bool
    {
        return self::hasRole('system_admin');
    }

    /** Reset caches after account-category, responsibility, or permission changes. */
    public static function flush(): void
    {
        self::$permissionCache = null;
        self::$roleCache = null;
        self::$responsibilityCache = null;
    }

    /** Enforce: abort with 403 unless the permission is held. */
    public static function authorize(string ...$permissionNames): void
    {
        if (!self::allows(...$permissionNames)) {
            Logger::security('Authorization denied', [
                'permission' => $permissionNames,
                'user_id'    => Auth::id(),
                'uri'        => $_SERVER['REQUEST_URI'] ?? '',
            ]);
            http_response_code(403);
            if (is_ajax_request()) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'message' => 'You are not authorized to perform this action.']);
            } else {
                include APP_PATH . '/views/errors/403.php';
            }
            exit;
        }
    }
}
