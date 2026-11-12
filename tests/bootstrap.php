<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('STORAGE_PATH', BASE_PATH . '/storage');

// Always use an isolated temporary database. Never unlink or modify a tracked
// developer database when running tests locally or in CI.
$dbFile = tempnam(sys_get_temp_dir(), 'reep-test-');
if ($dbFile === false) {
    throw new RuntimeException('Unable to create an isolated test database file.');
}
putenv('DB_NAME=' . $dbFile);

register_shutdown_function(static function () use ($dbFile): void {
    foreach ([$dbFile, $dbFile . '-wal', $dbFile . '-shm'] as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }
});

// Init isolated DB
unlink($dbFile);
$pdo = new PDO('sqlite:' . $dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Execute Schema
$pdo->exec(file_get_contents(BASE_PATH . '/database/sqlite_schema.sql'));
$pdo->exec(file_get_contents(BASE_PATH . '/database/sqlite_seed.sql'));

// Run role seeder
exec('php ' . BASE_PATH . '/scripts/seed_roles.php');

require BASE_PATH . '/app/autoload.php';
require BASE_PATH . '/app/helpers/functions.php';

