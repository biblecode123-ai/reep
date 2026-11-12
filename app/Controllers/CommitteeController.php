<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Gate;
use App\Core\Session;
use App\Models\ApplicationModel;
use App\Models\CommitteeModel;
use App\Services\NotificationService;
use App\Services\WorkflowService;

/**
 * University research committee stage (spec §11–§12; Rules 8 & 9).
 *
 *  - The Research Directorate (research.manage) assigns research to a committee.
 *  - Active committee members record the committee decision and — separately —
 *    its funding recommendation. That recommendation is NOT the approved
 *    budget; the directorate records the approved allocation separately.
 *  - Entity-level authorization (Rule 5): only active members of the assigned
 *    committee may record its decision.
 */
final class CommitteeController
{
    /** Director: assign the application to a university research committee. */
    public static function assign(): void
    {
        Gate::authorize('research.manage');
        Csrf::verifyRequest();
        $appId = (int) ($_POST['application_id'] ?? 0);
        $app = ApplicationModel::find($appId);
        if ($app === null) {
            Session::flash('error', 'Application not found.');
            redirect('index.php?action=applications');
        }
        if (!in_array($app['status_code'], ['UNIVERSITY_REVIEW', 'ETHICS_REVIEW'], true)) {
            Session::flash('error', 'Applications are assigned to committees during university review (current: ' . status_label($app['status_code']) . ').');
            redirect('index.php?action=application_view&id=' . $appId);
        }
        $committeeId = (int) (($_POST['committee_id'][$appId] ?? null) ?: ($_POST['committee_id'] ?? 0));
        $committee = Database::one('SELECT * FROM committees WHERE id = ? AND status = "active"', [$committeeId]);
        if ($committee === null) {
            Session::flash('error', 'Select an active committee.');
            redirect('index.php?action=application_view&id=' . $appId);
        }
        $meetingDate = ($_POST['meeting_date'][$appId] ?? $_POST['meeting_date'] ?? '') ?: null;
        $notes = trim((string) (($_POST['notes'][$appId] ?? null) ?: ($_POST['notes'] ?? ''))) ?: null;

        $assignmentId = CommitteeModel::assign($appId, $committeeId, Auth::id(), $meetingDate, $notes);
        Audit::log('committee.assigned', 'research_application', $appId, null, [
            'committee' => $committeeId, 'assignment' => $assignmentId,
        ]);

        foreach (CommitteeModel::members($committeeId) as $m) {
            NotificationService::send((int) $m['user_id'], 'committee_assigned', 'Research assigned to your committee',
                $committee['name'] . ' — ' . $app['title'],
                'index.php?action=application_view&id=' . $appId, 'high');
        }

        // Advance the workflow when the conditions (committee + ethics) hold.
        $app = ApplicationModel::find($appId);
        [$ok, $err] = WorkflowService::fire($app, 'research_application', 'Assign to research committee', 'Committee: ' . $committee['name']);
        if ($ok) {
            Session::flash('success', 'Research assigned to ' . $committee['name'] . ' and routed for committee review.');
        } else {
            Session::flash('warning', 'Committee recorded. ' . ($err ?? 'Complete the remaining conditions to route the research.'));
        }
        redirect('index.php?action=application_view&id=' . $appId);
    }

    public static function assignForm(): void
    {
        Gate::authorize('research.manage');
        $applications = Database::all('SELECT id, application_number, title, status_code FROM research_applications WHERE status_code = ?', ['UNIVERSITY_REVIEW']);
        include APP_PATH . '/views/research/committee_assign.php';
    }

    /** Committee member: record the committee decision + funding recommendation. */
    public static function decide(): void
    {
        Gate::authorize('research.review');
        Csrf::verifyRequest();
        $appId = (int) ($_POST['application_id'] ?? 0);
        $app = ApplicationModel::find($appId);
        if ($app === null) {
            Session::flash('error', 'Application not found.');
            redirect('index.php?action=applications');
        }
        $assignment = CommitteeModel::activeAssignment($appId);
        if ($assignment === null) {
            Session::flash('error', 'No committee is assigned to this research yet.');
            redirect('index.php?action=application_view&id=' . $appId);
        }
        // Entity-level rule (Rule 5): only active members of THIS committee.
        if (!CommitteeModel::isMember((int) $assignment['committee_id'], Auth::id())) {
            Audit::log('committee.decision_denied', 'research_application', $appId, null,
                ['user' => Auth::id(), 'committee' => (int) $assignment['committee_id']]);
            Session::flash('error', 'Only active members of the assigned committee can record its decision.');
            redirect('index.php?action=application_view&id=' . $appId);
        }
        if ($app['status_code'] !== 'UNIVERSITY_COMMITTEE_REVIEW') {
            Session::flash('error', 'The research is not in committee review (current: ' . status_label($app['status_code']) . ').');
            redirect('index.php?action=application_view&id=' . $appId);
        }

        $decision = (string) ($_POST['decision'] ?? '');
        if (!in_array($decision, ['approve', 'approve_with_conditions', 'revision_required', 'reject'], true)) {
            Session::flash('error', 'Select a valid committee decision.');
            redirect('index.php?action=application_view&id=' . $appId);
        }
        $comments = trim((string) ($_POST['comments'] ?? '')) ?: null;
        $meetingDate = ($_POST['meeting_date'] ?? '') ?: null;
        $reference = trim((string) ($_POST['decision_reference'] ?? '')) ?: null;

        // Funding recommendation (Rule 9) — advisory, stored separately from
        // the directorate-approved allocation.
        $recommended = null;
        if (($_POST['recommended_amount'] ?? '') !== '') {
            if (!is_numeric($_POST['recommended_amount']) || (float) $_POST['recommended_amount'] < 0) {
                Session::flash('error', 'Recommended funding must be a non-negative amount.');
                redirect('index.php?action=application_view&id=' . $appId);
            }
            $recommended = (float) $_POST['recommended_amount'];
        }
        $recNote = trim((string) ($_POST['recommendation_note'] ?? '')) ?: null;

        $decisionId = CommitteeModel::recordDecision([
            'assignment_id'       => (int) $assignment['id'],
            'application_id'      => $appId,
            'committee_id'        => (int) $assignment['committee_id'],
            'decision'            => $decision,
            'comments'            => $comments,
            'meeting_date'        => $meetingDate,
            'decision_reference'  => $reference,
            'recommended_amount'  => $recommended,
            'recommendation_note' => $recNote,
            'decided_by'          => Auth::id(),
            'decided_at'          => date('Y-m-d H:i:s'),
        ]);
        Audit::log('committee.decided', 'research_application', $appId, null, [
            'decision' => $decision, 'recommended_amount' => $recommended, 'row' => $decisionId,
        ]);
        NotificationService::sendToRoles(['rpee_director', 'rpee_officer'], 'committee_decision',
            'Committee decision recorded',
            $assignment['committee_name'] . ': ' . str_replace('_', ' ', $decision) . ' — ' . $app['title'],
            'index.php?action=application_view&id=' . $appId, 'high');
        Session::flash('success', 'Committee decision recorded' . ($recommended !== null ? ' with a funding recommendation.' : '.'));
        redirect('index.php?action=application_view&id=' . $appId);
    }

    /** Work queue: applications assigned to committees the user belongs to. */
    public static function queue(): void
    {
        Gate::authorize('research.review');
        $rows = CommitteeModel::queueForUser(Auth::id());
        include APP_PATH . '/views/research/committee_queue.php';
    }
}

