<?php
require 'config/app.php';
require 'app/autoload.php';
use App\Core\Database;

$pdo = Database::pdo();
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute(['test.admin@mwu.edu.et']);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
var_dump($user);
?>
