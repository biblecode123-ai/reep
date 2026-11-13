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
use App\Models\CommitteeModel;
use App\Services\WorkflowService;

final class DecisionController
{
    /**
     * The final institutional decision belongs exclusively to the RPEE Director.
     * Committee opinions are advisory; the Director records the institutional
     * decision through the configured workflow.
     */
    public static function finalizeDecision(): void
    {
        if (!Gate::hasRole('rpee_director')) {
            http_response_code(403);
            include APP_PATH . '/views/errors/403.php';
            exit;
        }
        Gate::authorize('research.manage');
        Csrf::verifyRequest();

        $appId = (int) ($_POST['application_id'] ?? 0);
        $decision = (string) ($_POST['decision'] ?? '');
        $justification = trim((string) ($_POST['justification'] ?? '')) ?: null;

        $app = ApplicationModel::find($appId);
        if ($app === null) {
            Session::flash('error', 'Application not found.');
            redirect('index.php?action=applications');
        }
        if (($app['status_code'] ?? '') !== 'UNIVERSITY_COMMITTEE_REVIEW') {
            Session::flash('error', 'A final Directorate decision can only be recorded after committee review.');
            redirect('index.php?action=application_view&id=' . $appId);
        }

        $minimumUniversityEvaluators = max(2, Settings::int('university_evaluators_minimum', 2));
        $completedUniversityEvaluators = (int) Database::scalar(
            'SELECT COUNT(DISTINCT ea.evaluator_user_id)
             FROM evaluations e
             JOIN evaluator_assignments ea ON ea.id = e.assignment_id
             WHERE e.application_id = ? AND e.status = "submitted"
               AND e.evaluation_type = "proposal"
               AND ea.assignment_level = "university"
               AND ea.is_active = 1 AND ea.status = "completed"',
            [$appId]
        );
        if ($completedUniversityEvaluators < $minimumUniversityEvaluators) {
            Session::flash('error', 'At least ' . $minimumUniversityEvaluators . ' independent university evaluations must be completed before the Director can make a final decision.');
            redirect('index.php?action=application_view&id=' . $appId);
        }

        $universityAverage = Database::scalar(
            'SELECT AVG(e.weighted_score)
             FROM evaluations e
             JOIN evaluator_assignments ea ON ea.id = e.assignment_id
             WHERE e.application_id = ? AND e.status = "submitted"
               AND e.evaluation_type = "proposal" AND ea.assignment_level = "university"
               AND ea.is_active = 1 AND ea.status = "completed"',
            [$appId]
        );
        $committeeDecision = CommitteeModel::latestDecision($appId);

        $map = [
            'accepted' => ['transition' => 'Approve (directorate)'],
            'not_accepted' => ['transition' => 'Reject (directorate)'],
            'further_review' => ['transition' => 'Request revision (directorate)'],
        ];
        if (!isset($map[$decision])) {
            Session::flash('error', 'Invalid final decision.');
            redirect('index.php?action=application_view&id=' . $appId);
        }
        try {
            Database::transaction(function () use ($appId, $decision, $justification, $app, $map, $committeeDecision, $universityAverage): void {
                [$ok, $error] = WorkflowService::fire(
                    $app,
                    'research_application',
                    $map[$decision]['transition'],
                    $justification
                );
                if (!$ok) {
                    throw new \RuntimeException($error ?? 'The final decision transition was rejected.');
                }

                Database::insert('university_decisions', [
                    'application_id' => $appId,
                    'decision'       => $decision,
                    'justification'  => $justification,
                    'decided_by'     => Auth::id(),
                    'decided_at'     => date('Y-m-d H:i:s'),
                ]);

                Audit::log('directorate.final_decision', 'research_application', $appId, null, [
                    'decision' => $decision,
                    'committee_recommendation' => $committeeDecision['decision'] ?? null,
                    'university_average_score' => $universityAverage,
                ]);
            });
        } catch (\Throwable $e) {
            Session::flash('error', 'Final decision was not recorded: ' . $e->getMessage());
            redirect('index.php?action=application_view&id=' . $appId);
        }

        Session::flash('success', 'Official Directorate decision recorded: ' . str_replace('_', ' ', $decision) . '.');
        redirect('index.php?action=application_view&id=' . $appId);
    }
}
