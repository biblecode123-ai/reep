<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Gate;
use App\Core\Session;
use App\Core\Validator;
use App\Models\ApplicationModel;
use App\Models\EvaluationModel;
use App\Models\UserModel;
use App\Services\NotificationService;
use App\Services\WorkflowService;

/**
 * College screening and evaluator assignment (spec §19–§21, §75).
 */
final class ScreeningController
{
    /**
     * College scoping (spec §5): a coordinator may only manage research that
     * belongs to their own college. Directors/officers/admin (research.manage)
     * and university-level roles are not restricted.
     */
    private static function assertCollegeScope(array $app, string $backAction): void
    {
        $isCoordinator = Gate::hasRole('college_coordinator') || Gate::hasRole('dept_coordinator');
        if (!$isCoordinator || Gate::allows('research.manage')) {
            return;
        }
        $myCollege = (int) (Auth::user()['college_id'] ?? 0);
        if ($myCollege === 0 || $myCollege !== (int) $app['college_id']) {
            Audit::log('college.scope_denied', 'research_application', (int) $app['id'], null,
                ['user' => Auth::id(), 'user_college' => $myCollege, 'app_college' => (int) $app['college_id']]);
            Session::flash('error', 'You cannot manage research belonging to another college (§5).');
            redirect($backAction);
        }
    }

    public static function receive(): void
    {
        Gate::authorize('research.screen');
        Csrf::verifyRequest();
        $id = (int) ($_POST['id'] ?? 0);
        $app = ApplicationModel::find($id);
        if ($app === null) {
            Session::flash('error', 'Application not found.');
            redirect('index.php?action=applications');
        }
        self::assertCollegeScope($app, 'index.php?action=application_view&id=' . $id);
        // The submit transition ends at SUBMITTED; coordinators may then either
        // acknowledge receipt (SUBMITTED → COLLEGE_RECEIVED) or go straight into
        // screening (SUBMITTED → UNDER_COLLEGE_SCREENING), depending on configured flow.
        $ok = false; $err = null;
        foreach (['College receives', 'Start screening'] as $attempt) {
            [$ok, $err] = WorkflowService::fire($app, 'research_application', $attempt, null);
            if ($ok) { break; }
        }
        if ($ok) {
            Session::flash('success', 'Application moved to screening.');
        } else {
            Session::flash('error', $err);
        }
        redirect('index.php?action=application_view&id=' . $id);
    }

    public static function decide(): void
    {
        Gate::authorize('research.screen');
        Csrf::verifyRequest();
        $id = (int) ($_POST['id'] ?? 0);
        $app = ApplicationModel::find($id);
        if ($app === null) {
            Session::flash('error', 'Application not found.');
            redirect('index.php?action=applications');
        }
        self::assertCollegeScope($app, 'index.php?action=application_view&id=' . $id);
        $decision = (string) ($_POST['decision'] ?? '');
        $comment  = trim((string) ($_POST['comment'] ?? '')) ?: null;

        // Record the screening decision row (§19, §27).
        $map = [
            'forward_to_evaluation' => 'forward_to_evaluation',
            'return_for_correction' => 'return_for_correction',
            'reject_ineligible'     => 'reject_ineligible',
        ];
        if (!isset($map[$decision])) {
            Session::flash('error', 'Invalid screening decision.');
            redirect('index.php?action=application_view&id=' . $id);
        }
        Database::insert('college_decisions', [
            'application_id'   => $id,
            'decision_context' => 'screening',
            'decision'         => $map[$decision],
            'comments'         => $comment,
            'decided_by'       => Auth::id(),
        ]);

        $transition = match ($decision) {
            'forward_to_evaluation' => 'Forward to evaluation',
            'return_for_correction' => 'Return for correction',
            'reject_ineligible'     => 'Reject as ineligible',
        };
        // Try the direct transition; if the application is still awaiting formal
        // receipt (state gap), perform the screening-start step first, then retry.
        $app = ApplicationModel::find($id);
        [$ok, $err] = WorkflowService::fire($app, 'research_application', $transition, $comment);
        if (!$ok) {
            $app = ApplicationModel::find($id);
            WorkflowService::fire($app, 'research_application', 'Start screening', null);
            $app = ApplicationModel::find($id);
            [$ok, $err] = WorkflowService::fire($app, 'research_application', $transition, $comment);
        }
        if (!$ok) {
            Session::flash('error', $err);
            redirect('index.php?action=application_view&id=' . $id);
        }
        if ($decision === 'return_for_correction') {
            NotificationService::send((int) $app['pi_user_id'], 'revision_requested', 'Correction requested',
                'Your application was returned for correction.', 'index.php?action=application_view&id=' . $id, 'high');
        }
        Session::flash('success', 'Screening decision recorded.');
        redirect('index.php?action=application_view&id=' . $id);
    }

    public static function assignEvaluatorForm(): void
    {
        Gate::authorize('research.assign');
        $id = (int) ($_GET['id'] ?? 0);
        $app = ApplicationModel::find($id);
        if ($app === null) {
            Session::flash('error', 'Application not found.');
            redirect('index.php?action=applications');
        }
        self::assertCollegeScope($app, 'index.php?action=application_view&id=' . $id);
        $assignments = EvaluationModel::assignmentsFor($id);

        // Get proposal team members (PI and co-investigators).
        // application_team_members is the proposal-stage team table; research_project_members
        // has no application_id column and only exists after project creation.
        $teamMembers = Database::all(
            'SELECT user_id FROM application_team_members WHERE application_id = ? AND status <> "declined"',
            [$id]
        );
        $teamIds = array_map('intval', array_column($teamMembers, 'user_id'));
        $teamIds[] = (int) $app['pi_user_id']; // Include PI

        // Get already assigned evaluator IDs
        $assignedIds = array_map('intval', array_column($assignments, 'evaluator_user_id'));

        // Get eligible evaluators from the same college (strict eligibility per requirements)
        $evaluators = Database::all(
            'SELECT u.id, u.full_name, u.email, re.expertise, re.availability_status, re.max_concurrent_evaluations,
                    d.name AS department_name, c.name AS college_name, c.id AS college_id
             FROM users u
             JOIN user_roles ur ON ur.user_id = u.id
             JOIN roles r ON r.id = ur.role_id
             LEFT JOIN research_evaluators re ON re.user_id = u.id
             LEFT JOIN departments d ON d.id = u.department_id
             LEFT JOIN colleges c ON c.id = u.college_id
             WHERE r.name = "evaluator" AND u.status = "active" AND u.college_id = ?
             ORDER BY u.full_name',
            [(int) $app['college_id']]
        );

        $eligibleEvaluators = [];
        $ineligibleReasons = [];

        foreach ($evaluators as &$ev) {
            $evId = (int) $ev['id'];
            $reasons = [];

            // Check 1: Cannot be PI or co-investigator
            if (in_array($evId, $teamIds, true)) {
                $reasons[] = 'is a member of the research team';
            }

            // Check 2: Cannot already be assigned
            if (in_array($evId, $assignedIds, true)) {
                $reasons[] = 'already assigned to this proposal';
            }

            // Check 3: Account must be active
            if (($ev['availability_status'] ?? 'available') === 'unavailable') {
                $reasons[] = 'currently unavailable';
            }

            // Check 4: Max concurrent evaluations not exceeded
            $currentLoad = EvaluationModel::workloadFor($evId);
            $maxLoad = (int) ($ev['max_concurrent_evaluations'] ?? 5);
            if ($currentLoad >= $maxLoad) {
                $reasons[] = 'at maximum capacity';
            }

            // Check 5: Conflict of interest detection
            $conflictWarning = EvaluationModel::detectConflicts($id, $evId);
            if ($conflictWarning !== null) {
                $reasons[] = 'potential conflict: ' . $conflictWarning;
            }

            $ev['current_load'] = $currentLoad;
            $ev['conflict_warning'] = $conflictWarning;

            if (empty($reasons)) {
                $eligibleEvaluators[] = $ev;
            } else {
                $ineligibleReasons[$evId] = implode('; ', $reasons);
            }
        }
        unset($ev);

        include APP_PATH . '/views/research/assign_evaluators.php';
    }

    public static function assignEvaluator(): void
    {
        Gate::authorize('research.assign');
        Csrf::verifyRequest();
        $id = (int) ($_POST['id'] ?? 0);
        $app = ApplicationModel::find($id);
        if ($app === null) {
            Session::flash('error', 'Application not found.');
            redirect('index.php?action=applications');
        }
        self::assertCollegeScope($app, 'index.php?action=application_view&id=' . $id);
        $evaluatorIds = array_map('intval', (array) ($_POST['evaluator_ids'] ?? []));
        if (!$evaluatorIds) {
            Session::flash('error', 'Select at least one evaluator.');
            redirect('index.php?action=assign_evaluators&id=' . $id);
        }
        $deadline = $_POST['deadline'] ?: null;

        // Server-side eligibility validation
        $teamMembers = Database::all(
            'SELECT user_id FROM application_team_members WHERE application_id = ? AND status <> "declined"',
            [$id]
        );
        $teamIds = array_map('intval', array_column($teamMembers, 'user_id'));
        $teamIds[] = (int) $app['pi_user_id'];

        $assigned = 0;
        $rejected = [];

        foreach ($evaluatorIds as $uid) {
            // Check 1: Must be from same college
            $evaluatorCollege = Database::scalar('SELECT college_id FROM users WHERE id = ?', [$uid]);
            if ((int) $evaluatorCollege !== (int) $app['college_id']) {
                $rejected[] = "Evaluator $uid is not from the same college";
                continue;
            }

            // Check 2: Cannot be PI or co-investigator
            if (in_array($uid, $teamIds, true)) {
                $rejected[] = "Evaluator $uid is a member of the research team";
                continue;
            }

            // Check 3: Cannot already be assigned
            $existing = Database::scalar(
                'SELECT COUNT(*) FROM evaluator_assignments WHERE application_id = ? AND evaluator_user_id = ? AND is_active = 1',
                [$id, $uid]
            );
            if ((int) $existing > 0) {
                $rejected[] = "Evaluator $uid is already assigned";
                continue;
            }

            // Check 4: Account must be active
            $evaluatorStatus = Database::scalar('SELECT status FROM users WHERE id = ?', [$uid]);
            if ($evaluatorStatus !== 'active') {
                $rejected[] = "Evaluator $uid account is not active";
                continue;
            }

            // Check 5: Max concurrent evaluations
            $currentLoad = EvaluationModel::workloadFor($uid);
            $maxLoad = (int) Database::scalar('SELECT max_concurrent_evaluations FROM research_evaluators WHERE user_id = ?', [$uid]);
            if ($maxLoad > 0 && $currentLoad >= $maxLoad) {
                $rejected[] = "Evaluator $uid is at maximum capacity";
                continue;
            }

            // All checks passed - assign
            EvaluationModel::assign($id, $uid, Auth::id(), $deadline);
            NotificationService::notifyEvaluatorAssigned($uid, $id, (string) $app['title']);
            Audit::log('evaluator.assigned', 'research_application', $id, null, ['evaluator' => $uid]);
            $assigned++;
        }

        if ($rejected) {
            Session::flash('warning', "$assigned evaluator(s) assigned. " . count($rejected) . " skipped: " . implode('; ', $rejected));
        } else {
            Session::flash('success', $assigned . ' evaluator(s) assigned and notified.');
        }

        $app = ApplicationModel::find($id);
        if (in_array($app['status_code'], ['EVALUATOR_ASSIGNMENT_PENDING'], true)) {
            WorkflowService::fire($app, 'research_application', 'Assign evaluators', null);
        }
        redirect('index.php?action=application_view&id=' . $id);
    }

    /** Evaluator accept / decline / conflict declaration (§21). */
    public static function evaluatorRespond(): void
    {
        Gate::authorize('research.evaluate');
        Csrf::verifyRequest();
        $assignmentId = (int) ($_POST['assignment_id'] ?? 0);
        $assignment = EvaluationModel::assignmentById($assignmentId);
        if ($assignment === null || (int) $assignment['evaluator_user_id'] !== Auth::id()) {
            Session::flash('error', 'Assignment not found or not yours.');
            redirect('index.php');
        }
        $response = (string) ($_POST['response'] ?? '');
        $reason   = trim((string) ($_POST['reason'] ?? '')) ?: null;

        if ($response === 'accept') {
            EvaluationModel::setAssignmentStatus($assignmentId, 'accepted');
            Audit::log('evaluator.accepted', 'research_application', (int) $assignment['application_id'], null, ['evaluator' => Auth::id()]);
            Session::flash('success', 'You accepted the evaluation.');
        } elseif ($response === 'conflict') {
            EvaluationModel::recordConflict($assignmentId, Auth::id(), 'self_declared', $reason, null);
            EvaluationModel::setAssignmentStatus($assignmentId, 'conflict_declared', $reason);
            Audit::log('evaluator.conflict_declared', 'research_application', (int) $assignment['application_id'], null, ['evaluator' => Auth::id(), 'reason' => $reason]);
            NotificationService::sendToCollegeRole((int) (Database::scalar('SELECT college_id FROM research_applications WHERE id = ?', [(int) $assignment['application_id']]) ?? 0),
                'college_coordinator', 'evaluator_conflict', 'Evaluator declared a conflict',
                'Reassignment is required.', 'index.php?action=application_view&id=' . $assignment['application_id'], 'high');
            Session::flash('success', 'Conflict declared. The coordinator will reassign.');
        } elseif ($response === 'decline') {
            EvaluationModel::setAssignmentStatus($assignmentId, 'declined', $reason);
            Audit::log('evaluator.declined', 'research_application', (int) $assignment['application_id'], null, ['evaluator' => Auth::id()]);
            Session::flash('success', 'You declined the assignment.');
        } else {
            Session::flash('error', 'Invalid response.');
        }
        redirect('index.php?action=my_evaluations');
    }

    public static function resolveConflict(): void
    {
        Gate::authorize('research.assign');
        Csrf::verifyRequest();
        $conflictId = (int) ($_POST['conflict_id'] ?? 0);
        $resolution = (string) ($_POST['resolution'] ?? '');
        $conflict = Database::one('SELECT c.*, ea.application_id FROM evaluator_conflicts c JOIN evaluator_assignments ea ON ea.id = c.assignment_id WHERE c.id = ?', [$conflictId]);
        if ($conflict === null) {
            Session::flash('error', 'Conflict not found.');
            redirect('index.php');
        }
        $resolvedValue = $resolution === 'override' ? 'resolved_override' : 'resolved_reassigned';
        Database::update('evaluator_conflicts', [
            'resolved' => $resolvedValue, 'resolved_by' => Auth::id(), 'resolved_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$conflictId]);
        Audit::log('evaluator.conflict_resolved', 'research_application', (int) $conflict['application_id'], null, ['resolution' => $resolution]);
        Session::flash('success', 'Conflict marked resolved.');
        redirect('index.php?action=application_view&id=' . (int) $conflict['application_id']);
    }

    /** Extend evaluation deadline */
    public static function extendDeadline(): void
    {
        Gate::authorize('research.assign');
        Csrf::verifyRequest();
        $assignmentId = (int) ($_POST['assignment_id'] ?? 0);
        $newDeadline = $_POST['new_deadline'] ?? '';
        $reason = trim((string) ($_POST['reason'] ?? '')) ?: null;

        if (empty($newDeadline)) {
            Session::flash('error', 'Provide a new deadline.');
            redirect('index.php');
        }

        $assignment = Database::one(
            'SELECT ea.*, a.college_id FROM evaluator_assignments ea JOIN research_applications a ON a.id = ea.application_id WHERE ea.id = ?',
            [$assignmentId]
        );
        if ($assignment === null) {
            Session::flash('error', 'Assignment not found.');
            redirect('index.php');
        }

        // College scope check
        $isCoordinator = Gate::hasRole('college_coordinator') || Gate::hasRole('dept_coordinator');
        if ($isCoordinator && Gate::denies('research.manage')) {
            $myCollege = (int) (Auth::user()['college_id'] ?? 0);
            if ($myCollege !== (int) $assignment['college_id']) {
                Session::flash('error', 'You cannot manage assignments for another college.');
                redirect('index.php');
            }
        }

        Database::update('evaluator_assignments', ['deadline' => $newDeadline], 'id = ?', [$assignmentId]);
        Audit::log('deadline.extended', 'evaluator_assignment', $assignmentId, null, ['new_deadline' => $newDeadline, 'reason' => $reason]);
        NotificationService::send((int) $assignment['evaluator_user_id'], 'deadline_extended', 'Evaluation deadline extended',
            "Your evaluation deadline has been extended to " . format_date($newDeadline), 'index.php?action=my_evaluations', 'normal');

        Session::flash('success', 'Deadline extended and evaluator notified.');
        redirect('index.php?action=application_view&id=' . (int) $assignment['application_id']);
    }

    /** Reassign evaluator */
    public static function reassignEvaluator(): void
    {
        Gate::authorize('research.assign');
        Csrf::verifyRequest();
        $assignmentId = (int) ($_POST['assignment_id'] ?? 0);
        $newEvaluatorId = (int) ($_POST['new_evaluator_id'] ?? 0);
        $reason = trim((string) ($_POST['reason'] ?? ''));

        if ($newEvaluatorId === 0) {
            Session::flash('error', 'Select a new evaluator.');
            redirect('index.php');
        }
        if (empty($reason)) {
            Session::flash('error', 'Provide a reason for reassignment.');
            redirect('index.php');
        }

        $assignment = Database::one(
            'SELECT ea.*, a.college_id, a.title FROM evaluator_assignments ea JOIN research_applications a ON a.id = ea.application_id WHERE ea.id = ?',
            [$assignmentId]
        );
        if ($assignment === null) {
            Session::flash('error', 'Assignment not found.');
            redirect('index.php');
        }

        // College scope check
        $isCoordinator = Gate::hasRole('college_coordinator') || Gate::hasRole('dept_coordinator');
        if ($isCoordinator && Gate::denies('research.manage')) {
            $myCollege = (int) (Auth::user()['college_id'] ?? 0);
            if ($myCollege !== (int) $assignment['college_id']) {
                Session::flash('error', 'You cannot manage assignments for another college.');
                redirect('index.php');
            }
        }

        // Validate new evaluator is from same college
        $newEvaluatorCollege = Database::scalar('SELECT college_id FROM users WHERE id = ?', [$newEvaluatorId]);
        if ((int) $newEvaluatorCollege !== (int) $assignment['college_id']) {
            Session::flash('error', 'New evaluator must be from the same college.');
            redirect('index.php');
        }

        // Mark old assignment as cancelled (preserve history)
        Database::update('evaluator_assignments', ['status' => 'cancelled', 'is_active' => 0], 'id = ?', [$assignmentId]);
        Audit::log('evaluator.reassigned', 'evaluator_assignment', $assignmentId, null, ['old_evaluator' => $assignment['evaluator_user_id'], 'new_evaluator' => $newEvaluatorId, 'reason' => $reason]);

        // Create new assignment
        $newAssignmentId = Database::insert('evaluator_assignments', [
            'application_id' => (int) $assignment['application_id'],
            'evaluator_user_id' => $newEvaluatorId,
            'assigned_by' => Auth::id(),
            'deadline' => $assignment['deadline'],
            'status' => 'assigned',
            'is_active' => 1,
        ]);

        // Notify new evaluator
        NotificationService::notifyEvaluatorAssigned($newEvaluatorId, (int) $assignment['application_id'], (string) $assignment['title']);

        Session::flash('success', 'Evaluator reassigned. Old assignment preserved in history.');
        redirect('index.php?action=application_view&id=' . (int) $assignment['application_id']);
    }

    /** Send reminder to evaluator */
    public static function sendReminder(): void
    {
        Gate::authorize('research.assign');
        Csrf::verifyRequest();
        $assignmentId = (int) ($_POST['assignment_id'] ?? 0);

        $assignment = Database::one(
            'SELECT ea.*, a.college_id, a.title FROM evaluator_assignments ea JOIN research_applications a ON a.id = ea.application_id WHERE ea.id = ?',
            [$assignmentId]
        );
        if ($assignment === null) {
            Session::flash('error', 'Assignment not found.');
            redirect('index.php');
        }

        // College scope check
        $isCoordinator = Gate::hasRole('college_coordinator') || Gate::hasRole('dept_coordinator');
        if ($isCoordinator && Gate::denies('research.manage')) {
            $myCollege = (int) (Auth::user()['college_id'] ?? 0);
            if ($myCollege !== (int) $assignment['college_id']) {
                Session::flash('error', 'You cannot manage assignments for another college.');
                redirect('index.php');
            }
        }

        // Update reminder timestamp
        Database::update('evaluator_assignments', ['reminder_sent_at' => date('Y-m-d H:i:s')], 'id = ?', [$assignmentId]);
        Audit::log('reminder.sent', 'evaluator_assignment', $assignmentId, null, null);

        // Send notification
        NotificationService::send((int) $assignment['evaluator_user_id'], 'evaluation_reminder', 'Evaluation reminder',
            "Please complete your evaluation for: " . $assignment['title'], 'index.php?action=my_evaluations', 'high');

        Session::flash('success', 'Reminder sent to evaluator.');
        redirect('index.php?action=application_view&id=' . (int) $assignment['application_id']);
    }

    /**
     * College-level decision and forwarding to university evaluation.
     * Enforces the finalized institutional rule:
     *  - Any number of evaluators may be assigned
     *  - Minimum 2 submitted evaluations required
     *  - Average is calculated from submitted evaluations only (pending does not block)
     *  - >= 50% PASS -> Forward to University
     *  - < 50% REJECTED
     *  - Coordinator cannot manually override the calculated pass/rejection result
     */
    public static function collegeDecision(): void
    {
        Gate::authorize('research.screen');
        Csrf::verifyRequest();
        $id = (int) ($_POST['id'] ?? 0);
        $app = ApplicationModel::find($id);
        if ($app === null) {
            Session::flash('error', 'Application not found.');
            redirect('index.php?action=applications');
        }
        self::assertCollegeScope($app, 'index.php?action=application_view&id=' . $id);
        
        $comment = trim((string) ($_POST['comment'] ?? '')) ?: null;
        
        // Retrieve submitted evaluations
        $submittedEvals = Database::all(
            'SELECT weighted_score FROM evaluations WHERE application_id = ? AND status = "submitted"',
            [$id]
        );
        $submittedCount = count($submittedEvals);
        $pendingCount = (int) Database::scalar(
            'SELECT COUNT(*) FROM evaluator_assignments WHERE application_id = ? AND status IN ("assigned","accepted") AND is_active = 1',
            [$id]
        );

        // Minimum submission rule: at least TWO submitted evaluations
        if ($submittedCount < 2) {
            Session::flash('error', "At least TWO evaluators must submit their assessments before the college decision can be calculated (currently: $submittedCount submitted, $pendingCount pending).");
            redirect('index.php?action=application_view&id=' . $id);
        }

        // Calculate average mark from submitted evaluations only
        $sum = array_sum(array_column($submittedEvals, 'weighted_score'));
        $avgScore = round($sum / $submittedCount, 2);

        // Mandatory 50% threshold: >= 50% PASS, < 50% REJECTED
        $isPass = ($avgScore >= 50.0);
        $systemDecision = $isPass ? 'forward_to_university' : 'reject_at_college';
        $summaryNote = "Calculated Average: {$avgScore}% ({$submittedCount} submitted, {$pendingCount} pending; Threshold: 50.0%)";
        $fullComment = $comment ? ($comment . "\n" . $summaryNote) : $summaryNote;

        // Record the college decision
        Database::insert('college_decisions', [
            'application_id'   => $id,
            'decision_context' => 'committee',
            'decision'         => $isPass ? 'forward_to_university' : 'reject',
            'comments'         => $fullComment,
            'decided_by'       => Auth::id(),
        ]);
        
        // Fire workflow transition
        $transition = $isPass ? 'Forward to university' : 'College reject';
        $app = ApplicationModel::find($id);
        [$ok, $err] = WorkflowService::fire($app, 'research_application', $transition, $fullComment);
        
        if (!$ok) {
            // If the application is in UNDER_COLLEGE_EVALUATION or EVALUATOR_ACCEPTED, try completing evaluation first
            if (in_array($app['status_code'], ['UNDER_COLLEGE_EVALUATION', 'UNDER_EVALUATION'], true)) {
                WorkflowService::fire($app, 'research_application', 'Submit evaluation', $summaryNote);
                $app = ApplicationModel::find($id);
                [$ok, $err] = WorkflowService::fire($app, 'research_application', $transition, $fullComment);
            }
        }

        if (!$ok) {
            Session::flash('error', $err);
            redirect('index.php?action=application_view&id=' . $id);
        }
        
        // Notifications
        if ($isPass) {
            NotificationService::send((int) $app['pi_user_id'], 'proposal_forwarded', 'Proposal passed college review',
                "Your proposal achieved an average mark of {$avgScore}% and has been forwarded to university evaluation.", 'index.php?action=application_view&id=' . $id, 'high');
            NotificationService::sendToRoles(['rpee_director', 'rpee_officer'], 'university_evaluation_pending',
                'College proposal forwarded', "Proposal {$app['application_number']} passed college review ({$avgScore}%) and awaits university review.", 'index.php?action=application_view&id=' . $id, 'high');
            Session::flash('success', "College evaluation complete: Average mark is {$avgScore}%. Result: PASS (>= 50%). Proposal forwarded to University Evaluation.");
        } else {
            NotificationService::send((int) $app['pi_user_id'], 'proposal_rejected', 'Proposal rejected at college level',
                "Your proposal achieved an average mark of {$avgScore}% which is below the 50% passing threshold.", 'index.php?action=application_view&id=' . $id, 'high');
            Session::flash('warning', "College evaluation complete: Average mark is {$avgScore}%. Result: REJECTED (< 50%). Proposal will not proceed to university evaluation.");
        }
        
        Audit::log('college.decision', 'research_application', $id, null, ['decision' => $systemDecision, 'average_score' => $avgScore, 'submitted' => $submittedCount]);
        redirect('index.php?action=application_view&id=' . $id);
    }

    public static function myEvaluations(): void
    {
        if (Gate::isAdmin()) {
            http_response_code(403);
            include APP_PATH . '/views/errors/403.php';
            exit;
        }
        Gate::authorize('research.evaluate');
        $userId = Auth::id();
        $filters = [
            'status'      => trim((string) ($_GET['status'] ?? '')),
            'level'       => trim((string) ($_GET['level'] ?? '')),
            'q'           => trim((string) ($_GET['q'] ?? '')),
            'overdue_only' => !empty($_GET['overdue_only']),
        ];
        if (!in_array($filters['level'], ['', 'college', 'university'], true)) {
            $filters['level'] = '';
        }
        $assignments = EvaluationModel::forEvaluator($userId, $filters);
        $counts = EvaluationModel::countByStatusForEvaluator($userId);
        $overdueCount = EvaluationModel::overdueCountForEvaluator($userId);
        include APP_PATH . '/views/research/my_evaluations.php';
    }

    /**
     * Coordinator: create an evaluator account belonging to their own college
     * (spec §5, §6). Role assignment is limited to the evaluator role — the
     * account is created with a temporary password that must be changed.
     */
    public static function createEvaluator(): void
    {
        Gate::authorize('research.assign');
        Csrf::verifyRequest();
        $appId = (int) ($_POST['application_id'] ?? 0);
        $back = 'index.php?action=assign_evaluators&id=' . $appId;

        [$ok, $errors, $clean] = Validator::check($_POST, [
            'full_name' => 'required|max:150',
            'email'     => 'required|email|max:190',
            'phone'     => 'phone',
        ]);
        if (!$ok) {
            Session::flash('error', implode(' ', $errors));
            redirect($back);
        }
        $password = (string) ($_POST['password'] ?? '');
        if (strlen($password) < 8) {
            Session::flash('error', 'Temporary password must be at least 8 characters.');
            redirect($back);
        }
        // College scoping (§5): coordinators manage evaluators of their college.
        $me = Auth::user();
        $collegeId = (int) ($me['college_id'] ?? 0);
        if (Gate::allows('research.manage') && (int) ($_POST['college_id'] ?? 0) > 0) {
            $collegeId = (int) $_POST['college_id'];
        }
        if ($collegeId === 0) {
            Session::flash('error', 'Your account has no college assigned — an administrator must set it first.');
            redirect($back);
        }
        $roleId = Database::scalar('SELECT id FROM roles WHERE name = "evaluator"');
        if ($roleId === null) {
            Session::flash('error', 'The evaluator role is not configured.');
            redirect($back);
        }
        $data = [
            'full_name'           => $clean['full_name'],
            'email'               => strtolower($clean['email']),
            'phone'               => $clean['phone'] ?: null,
            'college_id'          => $collegeId,
            'department_id'       => ((int) ($_POST['department_id'] ?? 0)) ?: null,
            'status'              => 'active',
            'password_hash'       => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
            'must_change_password' => 1,
        ];
        try {
            $userId = UserModel::create($data, [(int) $roleId]);
        } catch (\PDOException $e) {
            Session::flash('error', str_contains($e->getMessage(), 'Duplicate') || str_contains($e->getMessage(), 'UNIQUE')
                ? 'An account with that email already exists.' : 'Could not create the evaluator account.');
            redirect($back);
        }
        Database::insert('research_evaluators', [
            'user_id'            => $userId,
            'expertise'          => trim((string) ($_POST['expertise'] ?? '')) ?: null,
            'availability_status' => 'available',
        ]);
        Audit::log('evaluator.created', 'user', $userId, null, ['email' => $data['email'], 'college' => $collegeId]);
        NotificationService::send($userId, 'welcome_evaluator', 'Evaluator account created',
            'You have been registered as an evaluator at Madda Walabu University. You must change your password at first login.');
        Session::flash('success', 'Evaluator account created for ' . $data['email'] . ' (temporary password set; first login forces a change).');
        redirect($back);
    }
}
