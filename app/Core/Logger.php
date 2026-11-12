<?php
declare(strict_types=1);

namespace App\Core;

/**
 * File logger — application/security/error logs are files (spec §110),
 * kept separate from the DB audit trail (audit_logs table).
 */
final class Logger
{
    private static function write(string $channel, string $message, array $context = []): void
    {
        if (!is_dir(LOG_PATH)) {
            @mkdir(LOG_PATH, 0775, true);
        }
        $line = sprintf(
            "[%s] %s %s %s\n",
            date('Y-m-d H:i:s'),
            strtoupper($channel),
            $message,
            $context ? json_encode($context, JSON_UNESCAPED_SLASHES) : ''
        );
        @file_put_contents(LOG_PATH . '/' . $channel . '.log', $line, FILE_APPEND | LOCK_EX);
    }

    public static function info(string $message, array $context = []): void  { self::write('app', $message, $context); }
    public static function error(string $message, array $context = []): void { self::write('error', $message, $context); }

    public static function security(string $message, array $context = []): void
    {
        self::write('security', $message, $context);
    }

    /** Generate a user-facing error reference id (spec §109). */
    public static function errorRef(): string
    {
        $ref = 'ERR-' . date('Y') . '-' . str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);
        self::error('Error reference ' . $ref . ' generated', ['uri' => $_SERVER['REQUEST_URI'] ?? '']);
        return $ref;
    }
}
