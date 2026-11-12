<?php
declare(strict_types=1);

/**
 * Production-safe migration for canonical monthly reporting.
 *
 * Adds a unique project/reporting-period constraint so concurrent submissions
 * cannot create duplicate monthly reports.
 *
 * Run after backing up the database:
 *   php scripts/migrate_monthly_reporting.php
 */
require dirname(__DIR__) . '/bootstrap.php';

use App\Core\Database;

echo "=== Monthly reporting migration ===\n";

$duplicates = Database::all(
    'SELECT project_id, reporting_period, COUNT(*) AS copies
     FROM progress_reports
     WHERE reporting_period IS NOT NULL AND TRIM(reporting_period) <> ""
     GROUP BY project_id, reporting_period
     HAVING COUNT(*) > 1'
);
if ($duplicates !== []) {
    echo "ABORTED: duplicate project/reporting periods exist. Resolve these first:\n";
    foreach ($duplicates as $row) {
        echo "  project={$row['project_id']} period={$row['reporting_period']} copies={$row['copies']}\n";
    }
    exit(1);
}

$indexes = Database::all('SHOW INDEX FROM progress_reports');
$exists = false;
foreach ($indexes as $index) {
    if (($index['Key_name'] ?? '') === 'uq_progress_project_period') {
        $exists = true;
        break;
    }
}
if (!$exists) {
    Database::run(
        'ALTER TABLE progress_reports
         ADD UNIQUE KEY uq_progress_project_period (project_id, reporting_period)'
    );
    echo "Added uq_progress_project_period.\n";
} else {
    echo "uq_progress_project_period already exists.\n";
}

echo "Monthly reporting migration complete.\n";
