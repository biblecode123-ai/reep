<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('STORAGE_PATH', BASE_PATH . '/storage');

putenv('DB_NAME=' . BASE_PATH . '/database/mwu_rpee_test.sqlite');

// Init DB
$dbFile = BASE_PATH . '/database/mwu_rpee_test.sqlite';
if (file_exists($dbFile)) {
    unlink($dbFile);
}
$pdo = new PDO('sqlite:' . $dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Execute Schema
$pdo->exec(file_get_contents(BASE_PATH . '/database/sqlite_schema.sql'));
$pdo->exec(file_get_contents(BASE_PATH . '/database/sqlite_seed.sql'));

// Run role seeder
exec('php ' . BASE_PATH . '/scripts/seed_roles.php');

require BASE_PATH . '/app/autoload.php';
require BASE_PATH . '/app/helpers/functions.php';

