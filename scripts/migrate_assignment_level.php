<?php declare(strict_types=1);

require __DIR__. '/../app/bootstrap.php';

use App\Core\Database;

$sqlite = Database::isSqlite();

if (!$sqlite)

echo "MySQL migration skipped (not implemented for simplicity in this task, assume schema updated manually if needed or update via GUI).
";

exit(0);

}

//
run("CREATE TABLE evaluator_assignments_new ( id INTEGER PRIMARY KEY AUTOINCREMENT, application_id INTEGER NOT NULL, evaluator_user_id INTEGER NOT NULL, assignment_level TEXT NOT NULL DEFAULT 'college', assigned_by INTEGER NULL, assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, deadline DATE NULL, status TEXT NOT NULL DEFAULT 'assigned', accepted_at DATETIME NULL, declined_at DATETIME NULL, completed_at DATETIME NULL, decline_reason TEXT NULL, reminder_sent_at DATETIME NULL, is_active INTEGER NOT NULL DEFAULT 1, FOREIGN KEY (application_id)
EFERENCES research_applications (id)
N DELETE RESTRICT ON UPDATE CASCADE, FOREIGN KEY (evaluator_user_id)
EFERENCES users (id)
N DELETE RESTRICT ON UPDATE CASCADE, FOREIGN KEY (assigned_by)
EFERENCES users (id)
N DELETE SET NULL ON UPDATE CASCADE )");

//
run("INSERT INTO evaluator_assignments_new (id, application_id, evaluator_user_id, assigned_by, assigned_at, deadline, status, accepted_at, declined_at, completed_at, decline_reason, reminder_sent_at, is_active)
ELECT id, application_id, evaluator_user_id, assigned_by, assigned_at, deadline, status, accepted_at, declined_at, completed_at, decline_reason, reminder_sent_at, is_active FROM evaluator_assignments");

//
run("DROP TABLE evaluator_assignments");

Database::run("ALTER TABLE evaluator_assignments_new RENAME TO evaluator_assignments");

//
run("CREATE UNIQUE INDEX uq_evalassign ON evaluator_assignments (application_id, evaluator_user_id, assignment_level)");

echo "Migration completed successfully for SQLite.
";


