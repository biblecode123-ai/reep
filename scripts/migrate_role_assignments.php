<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../config/app.php';
require __DIR__ . '/../app/autoload.php';
require __DIR__ . '/../app/helpers/functions.php';

use App\Core\Database;

$primaryNames = ['system_admin', 'rpee_director', 'college_coordinator', 'researcher'];
$priority = ['system_admin' => 4, 'rpee_director' => 3, 'college_coordinator' => 2, 'researcher' => 1];

if (Database::isSqlite()) {
    $columns = array_column(Database::all('PRAGMA table_info(roles)'), 'name');
    if (!in_array('role_type', $columns, true)) {
        Database::run("ALTER TABLE roles ADD COLUMN role_type TEXT NOT NULL DEFAULT 'assignment'");
    }
    $idType = 'INTEGER';
} else {
    $column = Database::one("SHOW COLUMNS FROM roles LIKE 'role_type'");
    if ($column === null) {
        Database::run("ALTER TABLE roles ADD COLUMN role_type ENUM('primary','assignment') NOT NULL DEFAULT 'assignment'");
    }
    $idType = 'BIGINT UNSIGNED';
}

Database::run("UPDATE roles SET role_type = CASE WHEN name IN ('system_admin','rpee_director','college_coordinator','researcher') THEN 'primary' ELSE 'assignment' END");
Database::run("CREATE TABLE IF NOT EXISTS user_responsibilities (
    user_id $idType NOT NULL,
    role_id $idType NOT NULL,
    assigned_by $idType NULL,
    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, role_id)
)");
Database::run("CREATE TABLE IF NOT EXISTS user_permission_grants (
    user_id $idType NOT NULL,
    permission_id $idType NOT NULL,
    granted_by $idType NULL,
    source_role_id $idType NULL,
    granted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, permission_id)
)");
Database::run("CREATE TABLE IF NOT EXISTS user_role_migration_archive (
    user_id $idType NOT NULL,
    role_id $idType NOT NULL,
    assigned_by $idType NULL,
    assigned_at DATETIME NULL,
    migrated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, role_id)
)");
Database::run("CREATE TABLE IF NOT EXISTS user_role_migration_users (
    user_id $idType NOT NULL PRIMARY KEY,
    migrated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
)");

$roleRows = Database::all('SELECT id, name, role_type FROM roles');
$rolesByName = [];
foreach ($roleRows as $role) {
    $rolesByName[$role['name']] = $role;
}
foreach ($primaryNames as $requiredName) {
    if (!isset($rolesByName[$requiredName])) {
        fwrite(STDERR, "Required primary category '{$requiredName}' is missing from roles. Apply the updated seed first.\n");
        exit(1);
    }
}

Database::transaction(function () use ($primaryNames, $priority, $rolesByName): void {
    $users = Database::all('SELECT id FROM users ORDER BY id');
    foreach ($users as $user) {
        $userId = (int) $user['id'];
        Database::run(Database::insertIgnore('user_role_migration_users') . ' (user_id) VALUES (?)', [$userId]);
        $oldMemberships = Database::all(
            'SELECT ur.role_id, ur.assigned_by, ur.assigned_at, r.name, r.role_type
             FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ?',
            [$userId]
        );

        foreach ($oldMemberships as $membership) {
            Database::run(Database::insertIgnore('user_role_migration_archive') .
                ' (user_id, role_id, assigned_by, assigned_at) VALUES (?, ?, ?, ?)',
                [$userId, (int) $membership['role_id'], $membership['assigned_by'], $membership['assigned_at']]
            );
            Database::run(Database::insertIgnore('user_permission_grants') .
                ' (user_id, permission_id, granted_by, source_role_id)
                 SELECT ?, rp.permission_id, ?, ? FROM role_permissions rp WHERE rp.role_id = ?',
                [$userId, $membership['assigned_by'], (int) $membership['role_id'], (int) $membership['role_id']]
            );
        }

        $candidates = array_values(array_filter(
            $oldMemberships,
            static fn (array $membership): bool => in_array($membership['name'], $primaryNames, true)
        ));
        usort($candidates, static fn (array $a, array $b): int => $priority[$b['name']] <=> $priority[$a['name']]);
        $primaryName = $candidates[0]['name'] ?? 'researcher';
        $primaryId = (int) $rolesByName[$primaryName]['id'];

        foreach ($oldMemberships as $membership) {
            if ($membership['role_type'] === 'assignment') {
                Database::run(Database::insertIgnore('user_responsibilities') .
                    ' (user_id, role_id, assigned_by, assigned_at) VALUES (?, ?, ?, ?)',
                    [$userId, (int) $membership['role_id'], $membership['assigned_by'], $membership['assigned_at']]
                );
            } elseif ($membership['name'] !== $primaryName) {
                Database::run(Database::insertIgnore('user_permission_grants') .
                    ' (user_id, permission_id, granted_by, source_role_id)
                     SELECT ?, rp.permission_id, ?, NULL FROM role_permissions rp WHERE rp.role_id = ?',
                    [$userId, $membership['assigned_by'], (int) $membership['role_id']]
                );
            }
        }

        Database::run('DELETE FROM user_roles WHERE user_id = ?', [$userId]);
        Database::run(Database::insertIgnore('user_roles') . ' (user_id, role_id) VALUES (?, ?)', [$userId, $primaryId]);
    }
});

Database::run("UPDATE workflow_transitions SET condition_key = NULL
    WHERE transition_name IN ('Approve (directorate)', 'Reject (directorate)', 'Request revision (directorate)')
    AND condition_key IN ('committee_approved', 'committee_rejected', 'committee_wants_revision')");

if (Database::isSqlite()) {
    Database::run('CREATE UNIQUE INDEX IF NOT EXISTS uq_user_primary_role ON user_roles(user_id)');
} else {
    $index = Database::scalar("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'user_roles' AND index_name = 'uq_user_primary_role'");
    if ((int) $index === 0) {
        Database::run('ALTER TABLE user_roles ADD UNIQUE KEY uq_user_primary_role (user_id)');
    }
}

echo 'Role migration complete: ' . count(Database::all('SELECT id FROM users')) . " accounts now have one primary category. Original memberships are archived.\n";
