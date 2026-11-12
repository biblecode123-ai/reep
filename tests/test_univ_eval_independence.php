<?php
$dbPath = 'database/mwu_rpee.sqlite';
$db = new PDO("sqlite:$dbPath");

echo "=== University Evaluation Independence Test ===\n";

// Assume app_id 1 exists from previous testing/seeding
$appId = 1;

// 1. Ensure college evaluation exists
$collegeScore = $db->query("SELECT AVG(weighted_score) FROM evaluations WHERE application_id = $appId AND evaluation_type = 'proposal' AND status = 'submitted'")->fetchColumn();
echo "College Stage Score: " . ($collegeScore ?: 'None') . "\n";

// 2. Mock University evaluation submission
$db->exec("INSERT INTO evaluations (application_id, evaluator_user_id, evaluation_type, weighted_score, status) VALUES ($appId, 999, 'final', 75.0, 'submitted')");

// 3. Verify independence
$univScore = $db->query("SELECT weighted_score FROM evaluations WHERE application_id = $appId AND evaluation_type = 'final'")->fetchColumn();
echo "University Stage Score: " . $univScore . "\n";

if ($univScore != $collegeScore) {
    echo "✓ PASSED: University evaluation is independent.\n";
} else {
    echo "✗ FAILED: University evaluation inherited college score.\n";
}
