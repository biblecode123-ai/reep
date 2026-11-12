<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Database-backed settings store (spec §103).
 * Values cached per request; never hardcoded in business logic.
 */
final class Settings
{
    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache === null) {
            $rows = Database::all('SELECT setting_key, setting_value FROM system_settings');
            self::$cache = array_column($rows, 'setting_value', 'setting_key');
        }
        return self::$cache;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        return self::all()[$key] ?? $default;
    }

    public static function int(string $key, int $default = 0): int
    {
        $v = self::get($key);
        return $v === null || $v === '' ? $default : (int) $v;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $v = self::get($key);
        if ($v === null) {
            return $default;
        }
        return in_array(strtolower($v), ['1', 'true', 'yes', 'on'], true);
    }

    public static function set(string $key, string $value, ?int $userId = null): void
    {
        Database::run(
            'UPDATE system_settings SET setting_value = ?, updated_by = ? WHERE setting_key = ?',
            [$value, $userId, $key]
        );
        self::$cache = null;
    }
}
