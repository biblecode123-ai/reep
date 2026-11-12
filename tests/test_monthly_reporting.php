<?php
require_once 'app/bootstrap.php';
$dbPath = 'database/mwu_rpee.sqlite';
$db = new PDO("sqlite:$dbPath");

echo "=== Monthly Reporting Test ===\n";

// Ensure at least one active project exists
$db->exec("UPDATE research_projects SET status_code = 'ACTIVE' WHERE id = 1");

require_once 'app/Services/MonthlyReportService.php';
\App\Services\MonthlyReportService::generateDueReports();

$report = $db->query("SELECT * FROM monthly_reports WHERE project_id = 1 AND status = 'due'")->fetch();
if ($report) {
    echo "✓ PASSED: Due report generated for active project.\n";
} else {
    echo "✗ FAILED: Due report not generated.\n";
}
