<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

use App\Models\UserModel;
use App\Core\Auth;
use App\Core\Database;

echo "Running Admin Smoke Tests...\n";

// 1. Auth Test
$user = UserModel::findByIdentifier('admin@mwu.edu.et');
if (!$user) die("Admin user not found\n");
echo "Admin user found: " . $user['full_name'] . "\n";

// 2. User Management Test
$testEmail = 'test.user@mwu.edu.et';
$data = [
    'full_name' => 'Test User',
    'email' => $testEmail,
    'password_hash' => password_hash('Password123!', PASSWORD_BCRYPT),
    'status' => 'active',
];
$userId = UserModel::create($data, []);
if (!$userId) die("Failed to create test user\n");
echo "Test user created with ID: $userId\n";

// 3. Status Change Test
UserModel::update($userId, ['status' => 'inactive']);
$updatedUser = UserModel::findById($userId);
if ($updatedUser['status'] !== 'inactive') die("Failed to deactivate user\n");
echo "User deactivated successfully\n";

echo "All Admin Smoke Tests passed.\n";

