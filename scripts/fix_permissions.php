<?php require __DIR__. '/../app/bootstrap.php';

use App\Core\Database;

$role = Database::one("SELECT id FROM roles WHERE name = 'rpee_director'");

$perm = Database::one("SELECT id FROM permissions WHERE name = 'org.manage'");

if ($role && $perm)

$roleId = (int)$role['id'];

$permId = (int)$perm['id'];

$exists = Database::scalar("SELECT COUNT(*)
FROM role_permissions WHERE role_id = ? AND permission_id = ?", [$roleId, $permId]);

if ($exists == 0)

Database::run("INSERT INTO role_permissions (role_id, permission_id)
ALUES (?, ?)", [$roleId, $permId]);

echo "Permission granted.";

}

else {
echo "Permission already granted.";

}

}

else {
echo "Role or permission not found.";

}

