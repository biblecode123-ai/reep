<?php
declare(strict_types=1);

/**
 * Migration: My Evaluations feature (§20–§24).
 *
 * 1. evaluator_assignments.assignment_level — distinguishes college-level from
 *    university-level assignments so the My Evaluations page can label them.
 * 2. Grants 'research.evaluate' to the college_coordinator and researcher
 *    roles so they can open My Evaluations when they are personally assigned.
 *    (Every evaluation action still requires a real evaluator_assignments row.)
 *
 * Idempotent: safe to re-run.
 *
 * Run: php scripts/migrate_my_evaluations.php
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Database;

$sqlite = Database::isSqlite();

// ---- 1. assignment_level column ------------------------------------------
$hasColumn = $sqlite
    ? array_column(Database::all('PRAGMA table_info(evaluator_assignments)', []), 'name')
    : array_column(Database::all(
        "SELECT COLUMN_NAME AS name FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'evaluator_assignments'", []
    ), 'name');

if (in_array('assignment_level', $hasColumn, true)) {
    echo "  + evaluator_assignments.assignment_level already exists\n";
} else {
    if ($sqlite) {
        Database::run("ALTER TABLE evaluator_assignments ADD COLUMN assignment_level TEXT NOT NULL DEFAULT 'college'");
    } else {
        Database::run("ALTER TABLE evaluator_assignments ADD COLUMN assignment_level VARCHAR(20) NOT NULL DEFAULT 'college'");
    }
    echo "  + evaluator_assignments.assignment_level added\n";
}

// ---- 2. research.evaluate grants -----------------------------------------
foreach (['college_coordinator', 'researcher'] as $roleName) {
    $stmt = Database::run(
        "INSERT INTO role_permissions (role_id, permission_id)
         SELECT r.id, p.id FROM roles r JOIN permissions p ON p.name = 'research.evaluate'
         WHERE r.name = ?
           AND NOT EXISTS (
             SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id
           )",
        [$roleName]
    );
    echo '  + research.evaluate for ' . $roleName . ($stmt->rowCount() > 0 ? " granted\n" : " already granted\n");
}

echo "Migration completed.\n";

