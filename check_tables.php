<?php
require_once 'app/bootstrap.php';
$db = \App\Core\Database::pdo();
$tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
print_r($tables);
