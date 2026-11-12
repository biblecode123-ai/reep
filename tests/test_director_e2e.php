<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Models\UserModel;
use App\Models\CallModel;

echo "Running Research Director E2E Suite...\n";

// Test 1: Director Authentication
$director = UserModel::findByIdentifier('director@mwu.edu.et');
if (!$director || !in_array('rpee_director', UserModel::roleNamesFor((int)$director['id']), true)) {
    die("Director account verification failed.\n");
}
echo "PASS: Director login and role verified.\n";

// Test 2: Dashboard Statistics (Simple check)
$stats = Database::scalar('SELECT COUNT(*) FROM research_calls');
echo "PASS: Dashboard stats query successful: $stats calls found.\n";

// Test 3: Rubric Creation
// Creating a simple university proposal rubric
$rubricData = [
    ['name' => 'Criterion 1', 'max_points' => 50, 'indicators' => [['name' => 'Ind 1', 'max_points' => 50]]],
    ['name' => 'Criterion 2', 'max_points' => 50, 'indicators' => [['name' => 'Ind 2', 'max_points' => 50]]]
];
try {
    $id = \App\Models\RubricModel::createVersion('university_proposal', $rubricData, (int)$director['id']);
    echo "PASS: Rubric version published: ID $id.\n";
} catch (\Exception $e) {
    die("FAIL: Rubric creation failed: " . $e->getMessage() . "\n");
}

echo "Research Director E2E Suite passed (partial).\n";
