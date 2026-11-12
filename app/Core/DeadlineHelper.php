<?php
declare(strict_types=1);

namespace App\Core;

final class DeadlineHelper
{
    public static function isCallOpen(array $call): bool
    {
        // Set timezone to Africa/Addis_Ababa (§5.2)
        date_default_timezone_set('Africa/Addis_Ababa');
        $now = date('Y-m-d H:i:s');
        
        $opensAt = $call['opens_at'] ?? '1970-01-01 00:00:00';
        $closesAt = $call['closes_at'] ?? '9999-12-31 23:59:59';
        
        return ($now >= $opensAt && $now <= $closesAt);
    }

    public static function hasCallClosed(array $call): bool
    {
        date_default_timezone_set('Africa/Addis_Ababa');
        $now = date('Y-m-d H:i:s');
        $closesAt = $call['closes_at'] ?? '9999-12-31 23:59:59';
        
        return ($now > $closesAt);
    }
}
