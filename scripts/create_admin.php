<?php
declare(strict_types=1);

/**
 * Script to create an administrator user.
 * Edit the configuration variables below to set the desired admin details.
 * Run with: php scripts/create_admin.php
 */

require __DIR__ . '/../config/app.php';
require __DIR__ . '/../app/autoload.php';

// Configuration
$adminEmail = 'admin@mwu.edu.et';
$adminPass  = 'ChangeMe123!';
$adminName  = 'System Administrator';

use App\Core\Database;

echo "=== Creating Admin User ===\n";

$pdo = Database::pdo();

// Check if user already exists
$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
$stmt->execute([$adminEmail]);
if ($stmt->fetch()) {
    exit("Error: User with email $adminEmail already exists.\n");
}

$hash = password_hash($adminPass, PASSWORD_BCRYPT, ['cost' => 12]);

// Get RPEE/RM college/dept IDs (assuming they were created during install)
$cid = (int) $pdo->query("SELECT id FROM colleges WHERE code = 'RPEE'")->fetchColumn();
$did = (int) $pdo->query("SELECT id FROM departments WHERE code = 'RM'")->fetchColumn();

if (!$cid || !$did) {
    exit("Error: Could not find RPEE college/department. Ensure installation is complete.\n");
}

$stmt = $pdo->prepare(
    'INSERT INTO users (employee_id, full_name, email, password_hash, status, position_title, college_id, department_id, must_change_password)
     VALUES (?, ?, ?, ?, "active", ?, ?, ?, 1)'
);
$stmt->execute(['EMP-ADMIN', $adminName, $adminEmail, $hash, 'System Administrator', $cid, $did]);

$adminId = (int) $pdo->lastInsertId();
$roleId  = (int) $pdo->query("SELECT id FROM roles WHERE name = 'system_admin'")->fetchColumn();

$pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)')->execute([$adminId, $roleId]);

echo "Success: Admin user created: {$adminEmail}\n";
echo "Password set to: {$adminPass} (Must change at first login)\n";
