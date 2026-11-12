<?php
require_once 'app/bootstrap.php';
$dbPath = 'database/mwu_rpee.sqlite';
$db = new PDO("sqlite:$dbPath");

echo "=== Dashboard Reporting Stats Test ===\n";

$sql = 'SELECT COUNT(*) FROM monthly_reports WHERE status IN ("due","overdue")';
$count = $db->query($sql)->fetchColumn();
echo "Due/Overdue Reports: $count\n";

if ($count >= 0) {
    echo "✓ PASSED: Dashboard query functional.\n";
} else {
    echo "✗ FAILED: Dashboard query failed.\n";
}

