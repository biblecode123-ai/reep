<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Csrf;

/**
 * Verifies CSRF tokens on every unsafe request method (spec §87).
 */
final class CsrfMiddleware
{
    public static function handle(): void
    {
        Csrf::verifyRequest();
    }
}

