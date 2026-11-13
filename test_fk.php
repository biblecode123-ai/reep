<?php
define('BASE_PATH', __DIR__);
require_once __DIR__ . '/app/Core/Database.php';

$pdo = App\Core\Database::pdo();
echo "Initial foreign_keys: " . $pdo->query('PRAGMA foreign_keys')->fetchColumn() . "\n";

// Inside transaction
$pdo->beginTransaction();
$pdo->exec('PRAGMA foreign_keys = OFF');
echo "Inside tx after OFF: " . $pdo->query('PRAGMA foreign_keys')->fetchColumn() . "\n";
$pdo->commit();

// Outside transaction
$pdo->exec('PRAGMA foreign_keys = OFF');
echo "Outside tx after OFF: " . $pdo->query('PRAGMA foreign_keys')->fetchColumn() . "\n";

$pdo->exec('PRAGMA foreign_keys = ON');
echo "Outside tx after ON: " . $pdo->query('PRAGMA foreign_keys')->fetchColumn() . "\n";

// Let's check user 10
$user = $pdo->query('SELECT * FROM users WHERE id = 10')->fetch(PDO::FETCH_ASSOC);
echo "User 10 exists: " . ($user ? $user['email'] : 'no') . "\n";
