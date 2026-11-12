<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Core\DeadlineHelper;
use App\Core\Settings;
use App\Models\CallModel;
use App\Services\ConditionEvaluator;

$passed = 0;
$failed = 0;
$check = static function (string $name, bool $ok) use (&$passed, &$failed): void {
    if ($ok) {
        $passed++;
        echo "PASS: {$name}\n";
    } else {
        $failed++;
        fwrite(STDERR, "FAIL: {$name}\n");
    }
};

$timezone = new DateTimeZone(Settings::get('timezone', 'Africa/Addis_Ababa') ?: 'Africa/Addis_Ababa');
$now = new DateTimeImmutable('now', $timezone);
$baseCall = [
    'status' => 'published',
    'opens_at' => $now->modify('-1 hour')->format('Y-m-d H:i:s'),
    'closes_at' => $now->modify('+1 hour')->format('Y-m-d H:i:s'),
];

$check('published call is open inside its official window', DeadlineHelper::isCallOpen($baseCall));
$check('call is not open before opening time', !DeadlineHelper::isCallOpen([
    'status' => 'published',
    'opens_at' => $now->modify('+1 hour')->format('Y-m-d H:i:s'),
    'closes_at' => $now->modify('+2 hours')->format('Y-m-d H:i:s'),
]));
$check('closing instant is exclusive', !DeadlineHelper::isCallOpen([
    'status' => 'published',
    'opens_at' => $now->modify('-2 hours')->format('Y-m-d H:i:s'),
    'closes_at' => $now->format('Y-m-d H:i:s'),
]));
$check('draft call cannot accept submissions', !DeadlineHelper::isCallOpen($baseCall + ['status' => 'draft']));
$check('call with missing date boundary is rejected', !DeadlineHelper::isCallOpen(['status' => 'published', 'opens_at' => $baseCall['opens_at']]));
$check('reversed call window is rejected', !DeadlineHelper::hasValidWindow([
    'opens_at' => $now->modify('+2 hours')->format('Y-m-d H:i:s'),
    'closes_at' => $now->modify('+1 hour')->format('Y-m-d H:i:s'),
]));
$check('closed status remains closed even if close date is future', DeadlineHelper::hasCallClosed([
    'status' => 'closed',
    'closes_at' => $now->modify('+1 day')->format('Y-m-d H:i:s'),
]));

[$conditionOk, $conditionError] = ConditionEvaluator::check('not_a_real_condition', 'research_application', 999999, []);
$check('unknown workflow conditions fail closed', $conditionOk === false && is_string($conditionError) && $conditionError !== '');

$callId = CallModel::create([
    'call_number' => 'TEST-WF-' . bin2hex(random_bytes(4)),
    'reference_number' => 'TEST-WF-REF-' . bin2hex(random_bytes(4)),
    'title' => 'Workflow default test call',
]);
$requiredEvaluators = (int) Database::scalar('SELECT required_evaluators FROM research_calls WHERE id = ?', [$callId]);
$check('new research calls default to one college evaluator', $requiredEvaluators === 1);

$schemaSql = (string) Database::scalar(
    "SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'evaluator_assignments'"
);
$check(
    'evaluator assignment uniqueness is stage-specific in SQLite schema',
    str_contains($schemaSql, 'application_id, evaluator_user_id, assignment_level')
);

echo "\nWorkflow core invariant tests: {$passed} passed, {$failed} failed.\n";
exit($failed === 0 ? 0 : 1);
