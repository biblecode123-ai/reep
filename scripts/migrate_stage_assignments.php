<?php
declare(strict_types=1);

use App\Core\Database;

require __DIR__ . '/../app/bootstrap.php';

if (Database::isSqlite()) {
    echo "SQLite fresh schema now uses stage-specific evaluator assignment uniqueness. Recreate the isolated test database from database/sqlite_schema.sql.\n";
    exit(0);
}

$legacyIndex = (int) Database::scalar(
    'SELECT COUNT(*) FROM information_schema.statistics
     WHERE table_schema = DATABASE()
       AND table_name = "evaluator_assignments"
       AND index_name = "uq_evalassign"'
);

if ($legacyIndex > 0) {
    Database::run('ALTER TABLE evaluator_assignments DROP INDEX uq_evalassign');
}

$stageIndex = (int) Database::scalar(
    'SELECT COUNT(*) FROM information_schema.statistics
     WHERE table_schema = DATABASE()
       AND table_name = "evaluator_assignments"
       AND index_name = "uq_evalassign_stage"'
);

if ($stageIndex === 0) {
    Database::run(
        'ALTER TABLE evaluator_assignments
         ADD UNIQUE KEY uq_evalassign_stage (application_id, evaluator_user_id, assignment_level)'
    );
}

echo "Evaluator assignments are now unique per application, evaluator, and evaluation stage.\n";
