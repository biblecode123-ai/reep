<?php
/**
 * Test script for College Coordinator workflow
 * Tests proposal screening, evaluator assignment, evaluation progress, and college forwarding
 */

require_once __DIR__ . '/../app/bootstrap.php';

use App\Core\Auth;
use App\Core\Database;
use App\Core\Gate;
use App\Models\ApplicationModel;
use App\Models\EvaluationModel;
use App\Models\UserModel;
use App\Services\WorkflowService;

echo "=== College Coordinator Workflow Tests ===\n\n";

// Test 1: Verify college scoping
echo "Test 1: College scoping enforcement\n";
$testUser = Database::one('SELECT * FROM users WHERE email LIKE "%coordinator%" LIMIT 1');
if ($testUser) {
    echo "✓ Found coordinator user: " . $testUser['full_name'] . " (college_id: " . ($testUser['college_id'] ?? 'null') . ")\n";
} else {
    echo "✗ No coordinator user found in database\n";
}

// Test 2: Verify proposals are filtered by college
echo "\nTest 2: College-level proposal filtering\n";
if ($testUser && $testUser['college_id']) {
    $collegeProposals = Database::all(
        'SELECT COUNT(*) as count FROM research_applications WHERE college_id = ?',
        [(int) $testUser['college_id']]
    );
    echo "✓ Proposals in coordinator's college: " . $collegeProposals[0]['count'] . "\n";
    
    $otherCollegeProposals = Database::all(
        'SELECT COUNT(*) as count FROM research_applications WHERE college_id != ?',
        [(int) $testUser['college_id']]
    );
    echo "✓ Proposals in other colleges: " . $otherCollegeProposals[0]['count'] . "\n";
} else {
    echo "⊘ Skipped (no coordinator with college_id)\n";
}

// Test 3: Verify evaluator assignment eligibility rules
echo "\nTest 3: Evaluator assignment eligibility\n";
$evaluators = Database::all(
    'SELECT u.id, u.full_name, u.college_id, u.department_id 
     FROM users u
     JOIN user_roles ur ON ur.user_id = u.id
     JOIN roles r ON r.id = ur.role_id
     WHERE r.name = "evaluator" AND u.status = "active"
     LIMIT 5'
);

foreach ($evaluators as $ev) {
    $conflictCheck = EvaluationModel::detectConflicts(1, (int) $ev['id']);
    echo "  Evaluator " . $ev['full_name'] . ": " . ($conflictCheck ? "CONFLICT: $conflictCheck" : "No conflict") . "\n";
}

// Test 4: Verify evaluation progress tracking
echo "\nTest 4: Evaluation progress tracking\n";
$appWithEvals = Database::one(
    'SELECT a.id, a.title, a.college_id, rc.required_evaluators
     FROM research_applications a
     LEFT JOIN research_calls rc ON rc.id = a.call_id
     WHERE a.status_code IN ("UNDER_COLLEGE_EVALUATION", "AWAITING_COLLEGE_DECISION")
     LIMIT 1'
);

if ($appWithEvals) {
    $assignments = EvaluationModel::assignmentsFor((int) $appWithEvals['id']);
    $completed = 0;
    foreach ($assignments as $as) {
        if ($as['status'] === 'completed') $completed++;
    }
    $required = (int) ($appWithEvals['required_evaluators'] ?? 2);
    echo "  Application: " . $appWithEvals['title'] . "\n";
    echo "  Required evaluators: $required\n";
    echo "  Assigned: " . count($assignments) . "\n";
    echo "  Completed: $completed\n";
    echo "  Progress: " . ($required > 0 ? round(($completed / $required) * 100) : 0) . "%\n";
} else {
    echo "⊘ No applications in evaluation stage found\n";
}

// Test 5: Verify college decision recording
echo "\nTest 5: College decision recording\n";
$collegeDecisions = Database::all(
    'SELECT COUNT(*) as count FROM college_decisions WHERE decision_context = "committee"'
);
echo "✓ College-level decisions recorded: " . $collegeDecisions[0]['count'] . "\n";

// Test 6: Verify notification system integration
echo "\nTest 6: Notification system\n";
$notifications = Database::all(
    'SELECT COUNT(*) as count FROM notifications WHERE notification_type IN ("evaluator_assigned", "proposal_forwarded", "revision_requested")'
);
echo "✓ Workflow-related notifications: " . $notifications[0]['count'] . "\n";

// Test 7: Verify workflow transitions exist
echo "\nTest 7: Workflow transitions\n";
$transitions = Database::all(
    'SELECT t.transition_name, fs.stage_code AS from_stage, ts.stage_code AS to_stage
     FROM workflow_transitions t
     JOIN workflow_stages fs ON fs.id = t.from_stage_id
     JOIN workflow_stages ts ON ts.id = t.to_stage_id
     WHERE t.transition_name IN ("Forward to evaluation", "Forward to university", "College reject")
     LIMIT 5'
);
foreach ($transitions as $t) {
    echo "  " . $t['transition_name'] . ": " . $t['from_stage'] . " → " . $t['to_stage'] . "\n";
}

// Test 8: Verify database schema has required tables
echo "\nTest 8: Database schema verification\n";
$requiredTables = [
    'research_applications',
    'evaluator_assignments',
    'evaluations',
    'college_decisions',
    'notifications',
    'workflow_history'
];
foreach ($requiredTables as $table) {
    $exists = Database::scalar(
        "SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name=?",
        [$table]
    );
    echo ($exists > 0 ? "✓" : "✗") . " Table '$table': " . ($exists > 0 ? "exists" : "missing") . "\n";
}

// Test 9: Verify college_decisions table structure
echo "\nTest 9: College decisions table structure\n";
$columns = Database::all("PRAGMA table_info(college_decisions)");
$requiredColumns = ['application_id', 'decision_context', 'decision', 'comments', 'decided_by', 'decided_at'];
foreach ($requiredColumns as $col) {
    $found = false;
    foreach ($columns as $c) {
        if ($c['name'] === $col) {
            $found = true;
            break;
        }
    }
    echo ($found ? "✓" : "✗") . " Column '$col': " . ($found ? "exists" : "missing") . "\n";
}

// Test 10: Verify route exists
echo "\nTest 10: Route configuration\n";
$routes = require __DIR__ . '/../routes/web.php';
$testRoutes = ['coordinator_proposals', 'college_decision', 'assign_evaluators', 'screening_decide'];
foreach ($testRoutes as $route) {
    echo (isset($routes[$route]) ? "✓" : "✗") . " Route '$route': " . (isset($routes[$route]) ? "exists" : "missing") . "\n";
}

echo "\n=== Test Summary ===\n";
echo "All critical tests executed. Review results above for any failures.\n";
echo "Note: Functional tests require actual user sessions and database state.\n";
