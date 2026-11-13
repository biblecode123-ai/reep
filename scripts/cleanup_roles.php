<?php require __DIR__. '/../app/bootstrap.php';

use App\Core\Database;

$keepRoles = ['system_admin', 'rpee_director', 'college_coordinator', 'researcher'];

//
all("SELECT id, name FROM roles WHERE name NOT IN (". implode(',', array_map(fn($r)
> "'$r'", $keepRoles)). ")");

echo "Removing roles:
";

foreach ($rolesToRemove as $r)

echo "- ". $r['name']. "
";

Database::run("DELETE FROM roles WHERE id = ?", [$r['id']]);

}

echo "Roles cleanup complete.
";

