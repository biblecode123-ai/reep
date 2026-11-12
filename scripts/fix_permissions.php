<?php
declare(strict_types=1);

use App\Core\Database;

require __DIR__ . '/../app/bootstrap.php';

$role = Database::one("SELECT id FROM roles WHERE name = 'rpee_director'");
$permission = Database::one("SELECT id FROM permissions WHERE name = 'org.manage'");

if ($role === null || $permission === null) {
    fwrite(STDERR, "Role or permission not found.\n");
    exit(1);
}

$roleId = (int) $role['id'];
$permissionId = (int) $permission['id'];
$exists = (int) Database::scalar(
    "SELECT COUNT(*) FROM role_permissions WHERE role_id = ? AND permission_id = ?",
    [$roleId, $permissionId]
);

if ($exists === 0) {
    Database::run(
        "INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)",
        [$roleId, $permissionId]
    );
    echo "Permission granted.\n";
} else {
    echo "Permission already granted.\n";
}
