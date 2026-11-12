<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Gate;

final class AdminHealthController
{
    public static function index(): void
    {
        Gate::authorize('settings.manage');
        
        $db = Database::pdo();
        // Database agnostic health check
        $db_ok = (bool)$db->query('SELECT 1')->fetchColumn();
        
        $data = [
            'php_version' => PHP_VERSION,
            'db_connected' => $db_ok,
            'integrity' => 'Supported',
            'storage_writable' => is_writable(STORAGE_PATH),
            'disk_free' => disk_free_space(STORAGE_PATH),
        ];
        
        extract($data);
        include APP_PATH . '/views/admin/health.php';
    }
}

