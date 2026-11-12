<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Gate;
use App\Core\Session;
use App\Models\ApplicationModel;
use App\Models\EvaluationModel;
use App\Services\WorkflowService;

final class UniversityEvaluationController
{
    public static function assignEvaluatorsForm(): void
    {
        Gate::authorize('research.assign');
        $appId = (int) ($_GET['application_id'] ?? 0);
        $app = ApplicationModel::find($appId);
        
        if ($app === null) {
            Session::flash('error', 'Application not found.');
            redirect('index.php?action=applications');
        }
        
        $collegeScore = (float) Database::scalar(
            'SELECT AVG(weighted_score) FROM evaluations WHERE application_id = ? AND status = "submitted" AND evaluation_type = "proposal"',
            [$appId]
        );
        
        if ($collegeScore < 50.0) {
            Session::flash('error', 'Proposal did not pass college evaluation.');
            redirect('index.php?action=application_view&id=' . $appId);
        }

        $evaluators = Database::all('SELECT u.id, u.full_name FROM users u JOIN research_evaluators re ON re.user_id = u.id WHERE re.availability_status = "available"');
        $assignments = Database::all('SELECT evaluator_user_id FROM evaluator_assignments WHERE application_id = ? AND is_active = 1', [$appId]);
        $assignedIds = array_column($assignments, 'evaluator_user_id');

        include APP_PATH . '/views/research/university_evaluator_assignment.php';
    }

    public static function assignEvaluators(): void
    {
        Gate::authorize('research.assign');
        Csrf::verifyRequest();
        $appId = (int) ($_POST['application_id'] ?? 0);
        $evaluators = (array) ($_POST['evaluators'] ?? []);
        
        $app = ApplicationModel::find($appId);
        if ($app === null) {
            Session::flash('error', 'Application not found.');
            redirect('index.php?action=applications');
        }

        Database::transaction(function () use ($appId, $evaluators) {
            Database::run('UPDATE evaluator_assignments SET is_active = 0 WHERE application_id = ?', [$appId]);
            
            foreach ($evaluators as $evalId) {
                // UNIQUE(application_id, evaluator_user_id): reuse an existing row
                // (e.g. the same person evaluated at college level) instead of
                // inserting a duplicate, and mark it as a university assignment.
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
            'SELECT * FROM evaluator_assignments WHERE application_id = ? AND evaluator_user_id = ? AND is_active = 1',
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
            'SELECT * FROM evaluator_assignments WHERE application_id = ? AND evaluator_user_id = ? AND is_active = 1',
            [$appId, Auth::id()]
        );
        if ($assignment === null) {
            Session::flash('error', 'You are not assigned to evaluate this application.');
            redirect('index.php?action=my_evaluations');
        }

        // Prevent duplicate university submissions by the same evaluator (§7).
        $already = Database::scalar(
            'SELECT COUNT(*) FROM evaluations WHERE application_id = ? AND evaluator_user_id = ? AND evaluation_type = "university" AND status = "submitted"',
            [$appId, Auth::id()]
        );
        if ((int) $already > 0) {
            Session::flash('error', 'You have already submitted your university evaluation for this application.');
            redirect('index.php?action=my_evaluations');
        }

        Database::transaction(function () use ($appId, $recBudget, $assignment) {
            EvaluationModel::createEvaluation([
                'application_id'    => $appId,
                'assignment_id'     => (int) $assignment['id'],
                'evaluator_user_id' => Auth::id(),
                'evaluation_type'   => 'university',
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
        
        $required = 3; 
        $done = (int) Database::scalar(
            'SELECT COUNT(*) FROM evaluations WHERE application_id = ? AND status = "submitted" AND evaluation_type = "university"',
            [$appId]
        );
        
        if ($done >= $required) {
            $app = ApplicationModel::find($appId);
            WorkflowService::fire($app, 'research_application', 'Submit university evaluation', 'All university evaluations submitted');
        }
        
        Session::flash('success', 'University evaluation submitted.');
        redirect('index.php?action=my_evaluations');
    }
}
