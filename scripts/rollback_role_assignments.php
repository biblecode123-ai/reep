<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../config/app.php';
require __DIR__ . '/../app/autoload.php';
require __DIR__ . '/../app/helpers/functions.php';

use App\Core\Database;

$migratedUsers = Database::all('SELECT user_id FROM user_role_migration_users ORDER BY user_id');
if ($migratedUsers === []) {
    fwrite(STDERR, "No role migration checkpoint was found; nothing was changed.\n");
    exit(1);
}

if (Database::isSqlite()) {
    Database::run('DROP INDEX IF EXISTS uq_user_primary_role');
} else {
    $index = Database::scalar("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'user_roles' AND index_name = 'uq_user_primary_role'");
    if ((int) $index > 0) {
        Database::run('ALTER TABLE user_roles DROP INDEX uq_user_primary_role');
    }
}

Database::transaction(function () use ($migratedUsers): void {
    foreach ($migratedUsers as $row) {
        $userId = (int) $row['user_id'];
        Database::run('DELETE FROM user_roles WHERE user_id = ?', [$userId]);
        Database::run(Database::insertIgnore('user_roles') .
            ' (user_id, role_id, assigned_by, assigned_at)
             SELECT user_id, role_id, assigned_by, assigned_at
             FROM user_role_migration_archive WHERE user_id = ?', [$userId]);
    }

    Database::run('DELETE FROM user_responsibilities
        WHERE EXISTS (
            SELECT 1 FROM user_role_migration_archive a
            WHERE a.user_id = user_responsibilities.user_id
              AND a.role_id = user_responsibilities.role_id
        )');
});

echo 'Role membership rollback complete: ' . count($migratedUsers) . " accounts restored from the migration archive.\n";
