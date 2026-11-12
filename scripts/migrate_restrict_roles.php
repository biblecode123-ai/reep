<?php
declare(strict_types=1);

// Run role restriction migration

require_once __DIR__ . '/../app/bootstrap.php';

use App\Core\Database;

echo "Restricting system roles to approved four roles...\n";

Database::transaction(function () {
    // 1. Keep only approved roles, remove all others.
    // Approved: system_admin, rpee_director, college_coordinator, researcher
    $approvedRoles = ['system_admin', 'rpee_director', 'college_coordinator', 'researcher'];
    
    // Find IDs to keep
    $placeholders = implode(',', array_fill(0, count($approvedRoles), '?'));
    $approvedIds = Database::all("SELECT id FROM roles WHERE name IN ($placeholders)", $approvedRoles);
    $approvedIds = array_column($approvedIds, 'id');
    
    // Remove unauthorized roles from user_roles
    $idPlaceholders = implode(',', array_fill(0, count($approvedIds), '?'));
    $sql = "DELETE FROM user_roles WHERE role_id NOT IN ($idPlaceholders)";
    Database::run($sql, $approvedIds);
    
    echo "Restricted user role assignments to approved roles.\n";
    
    // 2. We are not deleting the roles from the 'roles' table, 
    // because other tables (like committee_members) might depend on them.
    // However, they are no longer assignable via user management.
});

echo "Role migration complete.\n";
