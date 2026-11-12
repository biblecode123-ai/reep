<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

use App\Core\Database;
use App\Models\EvaluationModel;

echo "=== My Evaluations verification ===\n";

// 1. The previously broken queries now use application_team_members
$queries = [
    'assignEvaluator team query (application_team_members)' =>
        'SELECT user_id FROM application_team_members WHERE application_id = 1 AND status <> "declined"',
    'EvaluationController::form team query (application_team_members)' =>
        'SELECT atm.user_id, u.full_name, atm.role
         FROM application_team_members atm
         JOIN users u ON u.id = atm.user_id
         WHERE atm.application_id = 1 AND atm.status <> "declined"',
    'forEvaluator new query (PI/dept/level/draft)' =>
        'SELECT ea.*, a.title AS application_title, a.application_number, a.college_id,
                a.department_id, a.submitted_at, a.pi_user_id,
                c.name AS college_name, d.name AS department_name,
                pi.full_name AS pi_name, rc.title AS call_title,
                (SELECT COUNT(*) FROM evaluations ev
                  WHERE ev.application_id = ea.application_id
                    AND ev.evaluator_user_id = ea.evaluator_user_id
                    AND ev.status = "draft") AS draft_count
         FROM evaluator_assignments ea
         JOIN research_applications a ON a.id = ea.application_id
         JOIN users pi ON pi.id = a.pi_user_id
         LEFT JOIN colleges c ON c.id = a.college_id
         LEFT JOIN departments d ON d.id = a.department_id
         LEFT JOIN research_calls rc ON rc.id = a.call_id
         WHERE ea.evaluator_user_id = 1
         ORDER BY ea.deadline IS NULL, ea.deadline, ea.assigned_at DESC',
    'assignment_level column probe' =>
        'SELECT assignment_level FROM evaluator_assignments LIMIT 1',
];

foreach ($queries as $label => $q) {
    try {
        Database::all($q, []);
        echo "OK    $label\n";
    } catch (\Throwable $e) {
        echo "FAIL  $label: " . $e->getMessage() . "\n";
    }
}

// 2. Model methods run cleanly
try {
    $rows = EvaluationModel::forEvaluator(999999, ['status' => '', 'level' => 'college', 'q' => 'test', 'overdue_only' => true]);
    echo "OK    forEvaluator with all filters (" . count($rows) . " rows for nonexistent user)\n";
} catch (\Throwable $e) {
    echo "FAIL  forEvaluator: " . $e->getMessage() . "\n";
}
try {
    $c = EvaluationModel::overdueCountForEvaluator(999999);
    echo "OK    overdueCountForEvaluator ($c)\n";
} catch (\Throwable $e) {
    echo "FAIL  overdueCountForEvaluator: " . $e->getMessage() . "\n";
}
try {
    $c = EvaluationModel::countByStatusForEvaluator(999999);
    echo "OK    countByStatusForEvaluator\n";
} catch (\Throwable $e) {
    echo "FAIL  countByStatusForEvaluator: " . $e->getMessage() . "\n";
}

// 3. assign() reuses the unique (application_id, evaluator_user_id) row and updates level
// Uses a real user id (FK) inside a transaction that is always rolled back.
$testUser = (int) Database::scalar('SELECT id FROM users ORDER BY id DESC LIMIT 1');
try {
    Database::transaction(function () use ($testUser) {
        $id = EvaluationModel::assign(1, $testUser, $testUser, null, 'college');
        $row1 = Database::one('SELECT * FROM evaluator_assignments WHERE id = ?', [$id]);
        $id2 = EvaluationModel::assign(1, $testUser, $testUser, null, 'university');
        $row2 = Database::one('SELECT * FROM evaluator_assignments WHERE id = ?', [$id2]);
        if ($id !== $id2) { throw new \RuntimeException('expected same row reused'); }
        if ($row2['assignment_level'] !== 'university') { throw new \RuntimeException('expected level=university after reassign'); }
        if ((int) $row2['is_active'] !== 1 || $row2['status'] !== 'assigned') { throw new \RuntimeException('expected reactivated'); }
        throw new \RuntimeException('rollback-intentional'); // roll back test data
    });
    echo "FAIL  assign() reuse test did not run\n";
} catch (\Throwable $e) {
    if (str_contains($e->getMessage(), 'rollback-intentional')) {
        echo "OK    assign() reuses row and updates level (rolled back)\n";
    } else {
        echo "FAIL  assign(): " . $e->getMessage() . "\n";
    }
}
$leftover = (int) Database::scalar('SELECT COUNT(*) FROM evaluator_assignments WHERE evaluator_user_id = ?', [$testUser]);
echo ($leftover === 0 ? "OK" : "FAIL") . "    test rows rolled back ($leftover left)\n";

// 4. Permission checks
foreach (['system_admin', 'college_coordinator', 'researcher', 'evaluator'] as $r) {
    $n = (int) Database::scalar(
        'SELECT COUNT(*) FROM role_permissions rp JOIN roles r ON r.id = rp.role_id JOIN permissions p ON p.id = rp.permission_id
         WHERE r.name = ? AND p.name = "research.evaluate"',
        [$r]
    );
    echo ($n > 0 ? 'OK' : 'FAIL') . "    $r has research.evaluate\n";
}

echo "=== done ===\n";

