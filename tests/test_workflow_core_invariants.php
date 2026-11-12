<?php
declare(strict_types=1);

use App\Core\Database;
use App\Core\DeadlineHelper;
use App\Core\Settings;
use App\Models\CallModel;
use App\Models\RubricModel;
use App\Services\RubricScoringService;
use App\Services\ConditionEvaluator;

require __DIR__ . '/bootstrap.php';

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
$check('draft call cannot accept submissions', !DeadlineHelper::isCallOpen(array_merge($baseCall, ['status' => 'draft'])));
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

$seedSql = (string) file_get_contents(BASE_PATH . '/database/sqlite_seed.sql');
$transitionStart = strpos($seedSql, 'INSERT INTO workflow_transitions');
$transitionEnd = $transitionStart === false ? false : strpos($seedSql, ';', $transitionStart);
$transitionSql = ($transitionStart !== false && $transitionEnd !== false)
    ? substr($seedSql, $transitionStart, $transitionEnd - $transitionStart)
    : '';
preg_match_all(
    "/,\\s*'[^']*'\\s*,\\s*(?:'[^']*'|NULL)\\s*,\\s*'([^']*)'\\s*,\\s*\\d+\\s*\\)/",
    $transitionSql,
    $conditionMatches
);
$workflowConditions = array_values(array_unique($conditionMatches[1] ?? []));
$check('workflow transition seed contains condition rules', count($workflowConditions) > 0);
foreach ($workflowConditions as $key) {
    [, $error] = ConditionEvaluator::check($key, 'not_a_research_entity', 999999, []);
    $check(
        'configured workflow condition is implemented: ' . $key,
        !is_string($error) || !str_starts_with($error, 'Unsupported workflow condition:')
    );
}

$validRubric = [
    ['name' => 'Criterion A', 'max_points' => 40, 'indicators' => [
        ['name' => 'Indicator A1', 'max_points' => 15],
        ['name' => 'Indicator A2', 'max_points' => 25],
    ]],
    ['name' => 'Criterion B', 'max_points' => 60, 'indicators' => [
        ['name' => 'Indicator B1', 'max_points' => 30],
        ['name' => 'Indicator B2', 'max_points' => 30],
    ]],
];
$check('complete indicator rubric with 100 points is valid', RubricModel::validateDefinition($validRubric) === []);
$badTotal = $validRubric;
$badTotal[1]['max_points'] = 59;
$check('rubric criterion totals other than 100 are rejected', RubricModel::validateDefinition($badTotal) !== []);
$badIndicators = $validRubric;
$badIndicators[0]['indicators'][0]['max_points'] = 14;
$check('indicator maxima must equal their criterion maximum', RubricModel::validateDefinition($badIndicators) !== []);
$missingIndicators = $validRubric;
$missingIndicators[0]['indicators'] = [];
$check('criteria without indicators are rejected', RubricModel::validateDefinition($missingIndicators) !== []);
$duplicateIndicators = $validRubric;
$duplicateIndicators[0]['indicators'][1]['name'] = 'Indicator A1';
$check('duplicate indicator names within a criterion are rejected', RubricModel::validateDefinition($duplicateIndicators) !== []);

$rubricFixture = [
    'criteria' => [
        ['indicators' => [
            ['id' => 101, 'name' => 'Indicator one', 'max_points' => 3, 'is_required' => 1],
            ['id' => 102, 'name' => 'Indicator two', 'max_points' => 2, 'is_required' => 1],
        ]],
    ],
];
$validScores = RubricScoringService::validate($rubricFixture, ['score_101' => '2.5', 'score_102' => '2'], true);
$check('indicator scores accept valid raw points and total them', $validScores['errors'] === [] && abs($validScores['total'] - 4.5) < 0.001);
$missingScores = RubricScoringService::validate($rubricFixture, ['score_101' => '2.5'], true);
$check('submission requires every required indicator score', $missingScores['errors'] !== []);
$excessScores = RubricScoringService::validate($rubricFixture, ['score_101' => '3.1', 'score_102' => '2'], true);
$check('indicator scores above their individual maximum are rejected', $excessScores['errors'] !== []);
$schemaHasRubrics = (int) Database::scalar(
    "SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name IN ('rubric_templates','rubric_criteria','rubric_indicators','rubric_indicator_scores')"
);
$check('SQLite schema contains all versioned rubric tables', $schemaHasRubrics === 4);
$evaluationColumns = Database::all("PRAGMA table_info(evaluations)");
$evaluationColumnNames = array_column($evaluationColumns, 'name');
$check('evaluations retain a rubric version and immutable snapshot', in_array('rubric_template_id', $evaluationColumnNames, true) && in_array('rubric_snapshot', $evaluationColumnNames, true));

$seedOutput = [];
$seedExit = 1;
exec('php ' . escapeshellarg(BASE_PATH . '/scripts/seed_institutional_rubrics.php'), $seedOutput, $seedExit);
$check('all four master-prompt rubric versions seed successfully', $seedExit === 0);
foreach (array_keys(RubricModel::TYPES) as $rubricType) {
    $activeRubric = RubricModel::activeFor($rubricType);
    $check('active rubric exists: ' . $rubricType, $activeRubric !== null);
    if ($activeRubric !== null) {
        $criterionTotal = array_sum(array_map(static fn(array $criterion): float => (float) $criterion['max_points'], $activeRubric['criteria']));
        $indicatorsMatch = true;
        foreach ($activeRubric['criteria'] as $criterion) {
            $indicatorTotal = array_sum(array_map(static fn(array $indicator): float => (float) $indicator['max_points'], $criterion['indicators']));
            if (abs($indicatorTotal - (float) $criterion['max_points']) > 0.001) {
                $indicatorsMatch = false;
            }
        }
        $check('rubric totals 100: ' . $rubricType, abs($criterionTotal - 100.0) < 0.001);
        $check('indicator maxima match criteria: ' . $rubricType, $indicatorsMatch);
    }
}

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
