<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Gate;
use App\Core\Session;
use App\Core\Settings;
use App\Models\ApplicationModel;
use App\Models\EvaluationModel;
use App\Models\RubricModel;
use App\Services\WorkflowService;
use App\Services\RubricScoringService;

final class UniversityEvaluationController
{
    private static function authorizeDirectorate(): void
    {
        if (!Gate::hasRole('rpee_director') && !Gate::hasRole('rpee_officer')) {
            http_response_code(403);
            include APP_PATH . '/views/errors/403.php';
            exit;
        }
        Gate::authorize('research.assign');
    }

    public static function assignEvaluatorsForm(): void
    {
        self::authorizeDirectorate();
        $appId = (int) ($_GET['application_id'] ?? 0);
        $app = ApplicationModel::find($appId);
        
        if ($app === null) {
            Session::flash('error', 'Application not found.');
            redirect('index.php?action=applications');
        }
        if (($app['status_code'] ?? '') !== 'UNIV_EVAL_ASSIGNMENT_PENDING') {
            Session::flash('error', 'Only college-endorsed proposals in the university evaluator assignment stage can be assigned.');
            redirect('index.php?action=application_view&id=' . $appId);
        }
        
        $collegeScore = Database::scalar(
            'SELECT AVG(weighted_score) FROM evaluations e
             JOIN evaluator_assignments ea ON ea.id = e.assignment_id
             WHERE e.application_id = ? AND e.status = "submitted"
               AND e.evaluation_type = "proposal" AND ea.assignment_level = "college"',
            [$appId]
        );
        $passingScore = max(0.0, min(100.0, (float) Settings::get('college_proposal_passing_score', '50')));

        if ($collegeScore === null || (float) $collegeScore < $passingScore) {
            Session::flash('error', 'Proposal did not pass college evaluation.');
            redirect('index.php?action=application_view&id=' . $appId);
        }

        $evaluators = Database::all('SELECT u.id, u.full_name FROM users u JOIN research_evaluators re ON re.user_id = u.id WHERE re.availability_status = "available"');
        $assignments = Database::all('SELECT evaluator_user_id FROM evaluator_assignments WHERE application_id = ? AND assignment_level = "university" AND is_active = 1', [$appId]);
        $assignedIds = array_column($assignments, 'evaluator_user_id');

        include APP_PATH . '/views/research/university_evaluator_assignment.php';
    }

    public static function assignEvaluators(): void
    {
        self::authorizeDirectorate();
        Csrf::verifyRequest();
        $appId = (int) ($_POST['application_id'] ?? 0);
        $evaluators = (array) ($_POST['evaluators'] ?? []);
        
        $app = ApplicationModel::find($appId);
        if ($app === null) {
            Session::flash('error', 'Application not found.');
            redirect('index.php?action=applications');
        }
        if (($app['status_code'] ?? '') !== 'UNIV_EVAL_ASSIGNMENT_PENDING') {
            Session::flash('error', 'Only college-endorsed proposals in the university evaluator assignment stage can be assigned.');
            redirect('index.php?action=application_view&id=' . $appId);
        }
        $collegeScore = Database::scalar(
            'SELECT AVG(e.weighted_score) FROM evaluations e
             JOIN evaluator_assignments ea ON ea.id = e.assignment_id
             WHERE e.application_id = ? AND e.status = "submitted"
               AND e.evaluation_type = "proposal" AND ea.assignment_level = "college"',
            [$appId]
        );
        $passingScore = max(0.0, min(100.0, (float) Settings::get('college_proposal_passing_score', '50')));
        if ($collegeScore === null || (float) $collegeScore < $passingScore) {
            Session::flash('error', 'The college proposal evaluation has not met the configured passing threshold.');
            redirect('index.php?action=application_view&id=' . $appId);
        }

        $required = max(1, Settings::int('university_evaluators_default', 1));
        $evaluators = array_values(array_unique(array_filter(array_map('intval', $evaluators), static fn(int $id): bool => $id > 0)));
        if (count($evaluators) !== $required) {
            Session::flash('error', "Assign exactly {$required} university evaluator(s), based on the current Directorate setting.");
            redirect('index.php?action=application_view&id=' . $appId);
        }

        foreach ($evaluators as $evaluatorId) {
            $eligible = (int) Database::scalar(
                'SELECT COUNT(*) FROM users u
                 JOIN research_evaluators re ON re.user_id = u.id
                 WHERE u.id = ? AND u.status = "active" AND re.availability_status = "available"',
                [$evaluatorId]
            );
            $isResearchTeamMember = (int) Database::scalar(
                'SELECT COUNT(*) FROM application_team_members
                 WHERE application_id = ? AND user_id = ? AND status = "accepted"',
                [$appId, $evaluatorId]
            );
            $isPi = (int) Database::scalar(
                'SELECT COUNT(*) FROM research_applications WHERE id = ? AND pi_user_id = ?',
                [$appId, $evaluatorId]
            );
            if ($eligible !== 1 || $isResearchTeamMember > 0 || $isPi > 0) {
                Session::flash('error', "Evaluator {$evaluatorId} is unavailable, not qualified, or has a conflict with this research team.");
                redirect('index.php?action=application_view&id=' . $appId);
            }
        }

        Database::transaction(function () use ($appId, $evaluators) {
            Database::run('UPDATE evaluator_assignments SET is_active = 0, status = "cancelled" WHERE application_id = ? AND assignment_level = "university" AND is_active = 1', [$appId]);
            
            foreach ($evaluators as $evalId) {
                // Assignment rows are unique per evaluator AND stage.
                EvaluationModel::assign($appId, (int) $evalId, Auth::id(), null, 'university');
            }
        });

        WorkflowService::fire($app, 'research_application', 'Assign university evaluators', 'Research Director assigned university evaluators');
        
        Session::flash('success', 'University evaluators assigned.');
        redirect('index.php?action=application_view&id=' . $appId);
    }

    public static function universityEvaluationForm(): void
    {
        Gate::authorize('research.evaluate');
        $appId = (int) ($_GET['application_id'] ?? 0);
        $app = ApplicationModel::find($appId);
        if ($app === null) {
            Session::flash('error', 'Application not found.');
            redirect('index.php?action=my_evaluations');
        }

        $assignment = Database::one(
            'SELECT * FROM evaluator_assignments
             WHERE application_id = ? AND evaluator_user_id = ? AND assignment_level = "university"
               AND is_active = 1 AND status IN ("assigned","accepted")',
            [$appId, Auth::id()]
        );
        if ($assignment === null) {
            Session::flash('error', 'You are not assigned to evaluate this application.');
            redirect('index.php?action=my_evaluations');
        }

        $round = EvaluationModel::nextRound($appId, Auth::id());
        $draft = EvaluationModel::findDraft($appId, Auth::id(), $round);
        $rubric = !empty($draft['rubric_template_id'])
            ? RubricModel::findById((int) $draft['rubric_template_id'])
            : RubricModel::activeFor('university_proposal');
        if ($rubric === null) {
            Session::flash('error', 'The Research Director must publish a complete University Proposal rubric before evaluation can begin.');
            redirect('index.php?action=my_evaluations');
        }
        $draftScores = $draft ? RubricScoringService::scoresForEvaluation((int) $draft['id']) : [];

        include APP_PATH . '/views/research/university_evaluation_form.php';
    }

    public static function submitUniversityEvaluation(): void
    {
        Gate::authorize('research.evaluate');
        Csrf::verifyRequest();
        $appId = (int) ($_POST['application_id'] ?? 0);
        $app = ApplicationModel::find($appId);
        if ($app === null) {
            Session::flash('error', 'Application not found.');
            redirect('index.php?action=my_evaluations');
        }

        $assignment = Database::one(
            'SELECT * FROM evaluator_assignments
             WHERE application_id = ? AND evaluator_user_id = ? AND assignment_level = "university"
               AND is_active = 1 AND status = "accepted"',
            [$appId, Auth::id()]
        );
        if ($assignment === null) {
            Session::flash('error', 'You are not assigned to evaluate this application or have not accepted the assignment.');
            redirect('index.php?action=my_evaluations');
        }

        $already = (int) Database::scalar(
            'SELECT COUNT(*) FROM evaluations
             WHERE assignment_id = ? AND evaluator_user_id = ? AND evaluation_type = "proposal" AND status = "submitted"',
            [(int) $assignment['id'], Auth::id()]
        );
        if ($already > 0) {
            Session::flash('error', 'You have already submitted your university evaluation for this application.');
            redirect('index.php?action=my_evaluations');
        }

        $round = EvaluationModel::nextRound($appId, Auth::id());
        $draft = EvaluationModel::findDraft($appId, Auth::id(), $round);
        $rubric = !empty($draft['rubric_template_id'])
            ? RubricModel::findById((int) $draft['rubric_template_id'])
            : RubricModel::activeFor('university_proposal');
        if ($rubric === null) {
            Session::flash('error', 'No published University Proposal rubric is available.');
            redirect('index.php?action=my_evaluations');
        }
        $validated = RubricScoringService::validate($rubric, $_POST, true);
        if ($validated['errors'] !== []) {
            Session::flash('error', implode(' ', $validated['errors']));
            redirect('index.php?action=univ_evaluation_form&application_id=' . $appId);
        }

        $recBudgetRaw = $_POST['recommended_budget'] ?? '';
        $budgetJustification = trim((string) ($_POST['budget_justification'] ?? ''));
        if (!is_numeric($recBudgetRaw) || (float) $recBudgetRaw < 0 || $budgetJustification === '') {
            Session::flash('error', 'Enter a valid non-negative recommended budget and its justification.');
            redirect('index.php?action=univ_evaluation_form&application_id=' . $appId);
        }
        $recBudget = (float) $recBudgetRaw;
        $recommendation = (string) ($_POST['recommendation'] ?? '');
        if (!in_array($recommendation, ['accept', 'accept_with_revision', 'major_revision', 'reject'], true)) {
            Session::flash('error', 'Select a final university evaluation recommendation.');
            redirect('index.php?action=univ_evaluation_form&application_id=' . $appId);
        }

        // The university evaluation stage starts only after all assigned
        // university evaluators have accepted their own assignments.
        if (($app['status_code'] ?? '') === 'UNIV_EVAL_ASSIGNED') {
            $notAccepted = (int) Database::scalar(
                'SELECT COUNT(*) FROM evaluator_assignments
                 WHERE application_id = ? AND assignment_level = "university"
                   AND is_active = 1 AND status <> "accepted"',
                [$appId]
            );
            if ($notAccepted === 0) {
                WorkflowService::fire($app, 'research_application', 'Univ evaluator accepts', null);
                $app = ApplicationModel::find($appId);
                if (($app['status_code'] ?? '') === 'UNIV_EVAL_ACCEPTED') {
                    WorkflowService::fire($app, 'research_application', 'Start university evaluation', null);
                }
            }
        }
        $app = ApplicationModel::find($appId);
        if (($app['status_code'] ?? '') !== 'UNIV_UNDER_EVALUATION') {
            Session::flash('error', 'University evaluation has not been formally started. Ensure all assigned evaluators have accepted.');
            redirect('index.php?action=my_evaluations');
        }

        $weighted = $validated['total'];
        $evaluationId = Database::transaction(function () use (
            $appId, $assignment, $draft, $round, $rubric, $validated, $weighted,
            $recBudget, $budgetJustification, $recommendation
        ): int {
            if ($draft === null) {
                $evaluationId = EvaluationModel::createEvaluation([
                    'application_id' => $appId,
                    'assignment_id' => (int) $assignment['id'],
                    'rubric_template_id' => (int) $rubric['id'],
                    'rubric_snapshot' => RubricModel::snapshot($rubric),
                    'evaluator_user_id' => Auth::id(),
                    'evaluation_type' => 'proposal',
                    'evaluation_round' => $round,
                    'status' => 'draft',
                ]);
            } else {
                $evaluationId = (int) $draft['id'];
                if (empty($draft['rubric_template_id'])) {
                    EvaluationModel::updateEvaluation($evaluationId, [
                        'rubric_template_id' => (int) $rubric['id'],
                        'rubric_snapshot' => RubricModel::snapshot($rubric),
                    ]);
                }
            }

            RubricScoringService::replaceScores($evaluationId, $validated['scores']);
            EvaluationModel::updateEvaluation($evaluationId, [
                'recommendation' => $recommendation,
                'weighted_score' => $weighted,
                'max_score' => 100,
                'strengths' => trim((string) ($_POST['strengths'] ?? '')) ?: null,
                'weaknesses' => trim((string) ($_POST['weaknesses'] ?? '')) ?: null,
                'overall_comment' => trim((string) ($_POST['overall_comment'] ?? '')) ?: null,
                'status' => 'submitted',
                'submitted_at' => date('Y-m-d H:i:s'),
            ]);

            Database::insert('university_budget_recommendations', [
                'application_id' => $appId,
                'evaluator_id' => Auth::id(),
                'amount' => $recBudget,
                'justification' => $budgetJustification,
            ]);
            EvaluationModel::setAssignmentStatus((int) $assignment['id'], 'completed');
            return $evaluationId;
        });

        Audit::log('university.evaluation_submitted', 'research_application', $appId, null, [
            'evaluation_id' => $evaluationId,
            'score' => $weighted,
            'rubric_version' => (int) $rubric['version'],
        ]);

        $required = (int) Database::scalar(
            'SELECT COUNT(*) FROM evaluator_assignments
             WHERE application_id = ? AND assignment_level = "university"
               AND is_active = 1 AND status NOT IN ("cancelled","declined","conflict_declared")',
            [$appId]
        );
        $done = (int) Database::scalar(
            'SELECT COUNT(DISTINCT e.assignment_id) FROM evaluations e
             JOIN evaluator_assignments ea ON ea.id = e.assignment_id
             WHERE e.application_id = ? AND e.status = "submitted"
               AND e.evaluation_type = "proposal" AND ea.assignment_level = "university"
               AND ea.is_active = 1 AND ea.status = "completed"',
            [$appId]
        );

        if ($required > 0 && $done >= $required) {
            $app = ApplicationModel::find($appId);
            [$transitioned, $transitionError] = WorkflowService::fire(
                $app,
                'research_application',
                'Submit university evaluation',
                'All assigned university evaluations submitted'
            );
            if (!$transitioned) {
                Session::flash('warning', $transitionError);
            }
        }

        Session::flash('success', 'University indicator-level evaluation submitted and locked.');
        redirect('index.php?action=my_evaluations');
    }
}
