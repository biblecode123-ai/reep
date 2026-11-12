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
     * It confirms the committee recommendation through the configured workflow;
     * it must never write status_code directly.
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

        $requiredUniversityEvaluators = (int) Database::scalar(
            'SELECT COUNT(*) FROM evaluator_assignments
             WHERE application_id = ? AND assignment_level = "university"
               AND is_active = 1 AND status NOT IN ("cancelled","declined","conflict_declared")',
            [$appId]
        );
        $completedUniversityEvaluators = (int) Database::scalar(
            'SELECT COUNT(DISTINCT e.assignment_id)
             FROM evaluations e
             JOIN evaluator_assignments ea ON ea.id = e.assignment_id
             WHERE e.application_id = ? AND e.status = "submitted"
               AND e.evaluation_type = "proposal"
               AND ea.assignment_level = "university"
               AND ea.is_active = 1 AND ea.status = "completed"',
            [$appId]
        );
        if ($requiredUniversityEvaluators < 1 || $completedUniversityEvaluators < $requiredUniversityEvaluators) {
            Session::flash('error', 'All currently assigned university evaluations must be completed before the Director can make a final decision.');
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
        if ($universityAverage === null) {
            Session::flash('error', 'The university evaluator scores must be complete before the Director makes a final decision.');
            redirect('index.php?action=application_view&id=' . $appId);
        }
        $universityPassingScore = max(0.0, min(100.0, (float) Settings::get('university_proposal_passing_score', '50')));

        $committeeDecision = CommitteeModel::latestDecision($appId);
        if ($committeeDecision === null) {
            Session::flash('error', 'The assigned university committee must record its decision before Directorate confirmation.');
            redirect('index.php?action=application_view&id=' . $appId);
        }

        $map = [
            'accepted' => [
                'committee' => ['approve', 'approve_with_conditions'],
                'transition' => 'Approve (directorate)',
            ],
            'not_accepted' => [
                'committee' => ['reject'],
                'transition' => 'Reject (directorate)',
            ],
            'further_review' => [
                'committee' => ['revision_required'],
                'transition' => 'Request revision (directorate)',
            ],
        ];
        if (!isset($map[$decision])) {
            Session::flash('error', 'Invalid final decision.');
            redirect('index.php?action=application_view&id=' . $appId);
        }
        if ($decision === 'accepted' && (float) $universityAverage < $universityPassingScore) {
            Session::flash('error', 'The university proposal average (' . round((float) $universityAverage, 2)
                . '/100) is below the Director-configured passing threshold (' . $universityPassingScore . '/100). Acceptance is blocked; reject or return the proposal for revision.');
            redirect('index.php?action=application_view&id=' . $appId);
        }
        if (!in_array($committeeDecision['decision'], $map[$decision]['committee'], true)) {
            Session::flash('error', 'The Directorate decision must be consistent with the latest committee recommendation.');
            redirect('index.php?action=application_view&id=' . $appId);
        }

        try {
            Database::transaction(function () use ($appId, $decision, $justification, $app, $map): void {
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
                    'committee_decision' => $map[$decision]['committee'],
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
