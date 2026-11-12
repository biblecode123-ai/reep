<?php
require 'config/app.php';
require 'app/autoload.php';
use App\Models\UserModel;

$identifier = 'test.admin@mwu.edu.et';
$password = 'AdminPassword123!';

$user = UserModel::findByIdentifier($identifier);
echo "Hash: " . ($user['password_hash'] ?? 'NULL') . "\n";
echo "Verify: " . (password_verify($password, $user['password_hash'] ?? '') ? 'True' : 'False') . "\n";
?>
