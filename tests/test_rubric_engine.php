<?php
$dbPath = 'database/mwu_rpee.sqlite';
$db = new PDO("sqlite:$dbPath");

echo "=== Rubric Engine Test ===\n";

$rubric = $db->query("SELECT id, name FROM rubric_templates WHERE name = 'College Proposal Evaluation'")->fetch();
if (!$rubric) die("Rubric not found.\n");

$criteria = $db->query("SELECT id, name, max_points FROM rubric_criteria WHERE template_id = " . $rubric['id'])->fetchAll();
$total = 0;
foreach ($criteria as $c) {
    echo "Criterion: " . $c['name'] . " (" . $c['max_points'] . ")\n";
    $total += $c['max_points'];
}

echo "Total Points: $total\n";
if ($total == 100) {
    echo "✓ PASSED: Rubric totals 100 points.\n";
} else {
    echo "✗ FAILED: Rubric does not total 100 points.\n";
}

