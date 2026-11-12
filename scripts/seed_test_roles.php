<?php
// scripts/seed_test_roles.php
require_once __DIR__ . '/../app/bootstrap.php';

use App\Core\Database;

// --- DANGER: FOR TESTING ONLY ---
// Change this to a secure password before running!
$TEST_PASSWORD = 'ChangeMeSecurely123!';
$hash = password_hash($TEST_PASSWORD, PASSWORD_BCRYPT, ['cost' => 12]);

$testAccounts = [
    ['email' => 'admin@mwu.edu.et', 'name' => 'System Admin', 'role' => 'system_admin'],
    ['email' => 'director@mwu.edu.et', 'name' => 'Research Director', 'role' => 'research_director'],
    ['email' => 'coordinator@mwu.edu.et', 'name' => 'College Coordinator', 'role' => 'college_coordinator'],
    ['email' => 'researcher@mwu.edu.et', 'name' => 'Test Researcher', 'role' => 'researcher'],
];

foreach ($testAccounts as $account) {
    // 1. Check if user exists
    $user = Database::one('SELECT id FROM users WHERE email = ?', [$account['email']]);
    
    if (!$user) {
        $userId = Database::insert('users', [
            'full_name' => $account['name'],
            'email' => $account['email'],
            'password_hash' => $hash,
            'status' => 'active',
            'must_change_password' => 1 // Force change on first login
        ]);
        
        $roleId = Database::scalar('SELECT id FROM roles WHERE name = ?', [$account['role']]);
        
        if ($roleId) {
            Database::insert('user_roles', ['user_id' => $userId, 'role_id' => $roleId]);
            echo "Created: " . $account['email'] . " (Role: " . $account['role'] . ")\n";
        }
    } else {
        echo "Skipped: " . $account['email'] . " (Already exists)\n";
    }
}
echo "\nTest accounts processed. Users must change their password on first login.\n";

