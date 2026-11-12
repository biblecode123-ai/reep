<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Gate;
use App\Core\Session;
use App\Core\Settings;
use App\Models\ApplicationModel;
use App\Models\EvaluationModel;
use App\Services\WorkflowService;

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

        include APP_PATH . '/views/research/university_evaluation_form.php';
    }

    public static function submitUniversityEvaluation(): void
    {
        Gate::authorize('research.evaluate');
        Csrf::verifyRequest();
        $appId = (int) ($_POST['application_id'] ?? 0);
        $recBudget = (float) ($_POST['recommended_budget'] ?? 0);

        // Authorization: only an actual assigned evaluator may submit (§7).
        $assignment = Database::one(
            'SELECT * FROM evaluator_assignments
             WHERE application_id = ? AND evaluator_user_id = ? AND assignment_level = "university"
               AND is_active = 1 AND status = "accepted"',
            [$appId, Auth::id()]
        );
        if ($assignment === null) {
            Session::flash('error', 'You are not assigned to evaluate this application.');
            redirect('index.php?action=my_evaluations');
        }

        // Prevent duplicate university submissions by the same evaluator (§7).
        $already = Database::scalar(
            'SELECT COUNT(*) FROM evaluations
             WHERE assignment_id = ? AND evaluator_user_id = ? AND evaluation_type = "proposal" AND status = "submitted"',
            [(int) $assignment['id'], Auth::id()]
        );
        if ((int) $already > 0) {
            Session::flash('error', 'You have already submitted your university evaluation for this application.');
            redirect('index.php?action=my_evaluations');
        }

        // Start the university evaluation stage only after every active
        // university evaluator has accepted their own assignment.
        $app = ApplicationModel::find($appId);
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

        Database::transaction(function () use ($appId, $recBudget, $assignment) {
            EvaluationModel::createEvaluation([
                'application_id'    => $appId,
                'assignment_id'     => (int) $assignment['id'],
                'evaluator_user_id' => Auth::id(),
                'evaluation_type'   => 'proposal',
                'status'            => 'submitted',
                'submitted_at'      => date('Y-m-d H:i:s')
            ]);

            Database::insert('university_budget_recommendations', [
                'application_id' => $appId,
                'evaluator_id'   => Auth::id(),
                'amount'         => $recBudget
            ]);

            EvaluationModel::setAssignmentStatus((int) $assignment['id'], 'completed');
        });
        
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
        
        Session::flash('success', 'University evaluation submitted.');
        redirect('index.php?action=my_evaluations');
    }
}
