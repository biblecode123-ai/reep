<?php
declare(strict_types=1);

use App\Core\Database;

require __DIR__ . '/../app/bootstrap.php';

/**
 * Remove academic workflow authority from the technical system_admin role.
 *
 * Safe to re-run. This intentionally does not grant academic permissions to
 * system_admin. Role assignment and academic policy changes remain controlled
 * by designated institutional authorities.
 */
$roleId = Database::scalar("SELECT id FROM roles WHERE name = 'system_admin'");
if ($roleId === null) {
    fwrite(STDERR, "system_admin role was not found.\n");
    exit(1);
}

$allowed = ['user.view', 'user.manage', 'user.roles', 'org.view', 'org.manage', 'workflow.manage', 'settings.manage', 'audit.view'];

Database::transaction(function () use ($roleId, $allowed): void {
    Database::run('DELETE FROM role_permissions WHERE role_id = ?', [(int) $roleId]);

    foreach ($allowed as $permissionName) {
        $permissionId = Database::scalar(
            'SELECT id FROM permissions WHERE name = ?',
            [$permissionName]
        );
        if ($permissionId === null) {
            throw new RuntimeException('Required technical permission is missing: ' . $permissionName);
        }
        Database::insert('role_permissions', [
            'role_id' => (int) $roleId,
            'permission_id' => (int) $permissionId,
        ]);
    }
});

echo "Technical system_admin permissions have been restricted. No academic permissions were granted.\n";
