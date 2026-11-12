<?php
declare(strict_types=1);

namespace App\Core;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/** Central call-window rules using the institution's configured timezone. */
final class DeadlineHelper
{
    private static function timezone(): DateTimeZone
    {
        $name = Settings::get('timezone', 'Africa/Addis_Ababa') ?: 'Africa/Addis_Ababa';
        try {
            return new DateTimeZone($name);
        } catch (Throwable) {
            return new DateTimeZone('Africa/Addis_Ababa');
        }
    }

    public static function hasValidWindow(array $call): bool
    {
        $opensAt = trim((string) ($call['opens_at'] ?? ''));
        $closesAt = trim((string) ($call['closes_at'] ?? ''));
        if ($opensAt === '' || $closesAt === '') {
            return false;
        }
        try {
            $timezone = self::timezone();
            $opens = new DateTimeImmutable($opensAt, $timezone);
            $closes = new DateTimeImmutable($closesAt, $timezone);
            return $closes > $opens;
        } catch (Throwable) {
            return false;
        }
    }

    public static function isCallOpen(array $call): bool
    {
        if (!in_array((string) ($call['status'] ?? ''), ['published', 'open'], true)
            || !self::hasValidWindow($call)) {
            return false;
        }
        try {
            $timezone = self::timezone();
            $now = new DateTimeImmutable('now', $timezone);
            $opens = new DateTimeImmutable((string) $call['opens_at'], $timezone);
            $closes = new DateTimeImmutable((string) $call['closes_at'], $timezone);
            return $now >= $opens && $now < $closes;
        } catch (Throwable) {
            return false;
        }
    }

    public static function hasCallClosed(array $call): bool
    {
        if (in_array((string) ($call['status'] ?? ''), ['closed', 'archived', 'completed'], true)) {
            return true;
        }
        $closesAt = trim((string) ($call['closes_at'] ?? ''));
        if ($closesAt === '') {
            return false;
        }
        try {
            $timezone = self::timezone();
            return new DateTimeImmutable('now', $timezone) >= new DateTimeImmutable($closesAt, $timezone);
        } catch (Throwable) {
            return false;
        }
    }
}
