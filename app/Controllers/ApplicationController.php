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
use App\Models\CallModel;
use App\Services\DocumentService;
use App\Services\NotificationService;
use App\Services\NumberingService;
use App\Services\ResearchTeamService;
use App\Services\WorkflowService;

final class ApplicationController
{
    public static function index(): void
    {
        Gate::authorize('research.view');
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $filters = [
            'status'    => (string) ($_GET['status'] ?? ''),
            'call_id'   => (int) ($_GET['call_id'] ?? 0),
            'college_id' => (int) ($_GET['college_id'] ?? 0),
            'q'         => trim((string) ($_GET['q'] ?? '')),
        ];
        // College scoping for coordinators (institutional routing, §17).
        if (Gate::hasRole('college_coordinator') && !Gate::allows('research.manage')) {
            $filters['college_id'] = (int) (Auth::user()['college_id'] ?? 0);
        }
        
        // Researcher scoping: only see their own applications.
        if (Gate::hasRole('researcher') && !Gate::allows('research.manage')) {
            $filters['pi_user_id'] = Auth::id();
        }

        $p = paginate(1, $page, 15);
        $result = ApplicationModel::paginate($filters, $p['page'], $p['limit']);
        $p = paginate($result['total'], $page, 15);
        $rows = $result['rows'];
        $qs = $_SERVER['QUERY_STRING'] ?? '';
        include APP_PATH . '/views/research/applications.php';
    }

    public static function form(): void
    {
        Gate::authorize('research.create');
        $id = (int) ($_GET['id'] ?? 0);
        $callId = (int) ($_GET['call_id'] ?? 0);
        $app = $id ? ApplicationModel::find($id) : null;
        if ($app !== null && (int) $app['pi_user_id'] !== Auth::id() && Gate::denies('research.manage')) {
            Gate::authorize('research.manage');
        }
        // Block a second application to the same call (also enforced in save()).
        if ($id === 0 && $callId > 0) {
            $existing = ApplicationModel::countForCallByPi($callId, Auth::id());
            $callMax = (int) (Database::scalar('SELECT max_applications_per_researcher FROM research_calls WHERE id = ?', [$callId]) ?? 1);
            if ($callMax > 0 && $existing >= $callMax) {
                Session::flash('error', "You have already applied to this call. Only {$callMax} application(s) per researcher are allowed.");
                redirect('index.php?action=calls_public');
            }
        }
        $openCalls = CallModel::openCalls();
        $themes = \App\Models\OrgModel::themes();
        $types = \App\Models\OrgModel::types();
        $workPlan = $id ? ApplicationModel::workPlan($id) : [];
        $appTeamMembers = [];
        if ($id) {
            $appTeamMembers = Database::all(
                'SELECT tm.*, u.full_name, u.email FROM application_team_members tm
                 JOIN users u ON u.id = tm.user_id
                 WHERE tm.application_id = ?',
                [$id]
            );
        }
        $errors = [];
        include APP_PATH . '/views/research/application_form.php';
    }

    public static function save(): void
    {
        Gate::authorize('research.create');
        Csrf::verifyRequest();
        $id = (int) ($_POST['id'] ?? 0);
        [$ok, $errors, $clean] = Validator::check($_POST, [
            'title'       => 'required|max:250',
            'call_id'     => 'required|integer',
            'budget'      => 'numeric',
            'requires_ethics' => 'in:yes,no,unsure',
        ]);
        if (!$ok) {
            Session::flash('error', implode(' ', $errors));
            redirect('index.php?action=application_form' . ($id ? '&id=' . $id : '&call_id=' . (int) ($_POST['call_id'] ?? 0)));
        }

        $call = CallModel::find((int) $clean['call_id']);
        if ($call === null || !in_array($call['status'], ['published', 'open'], true)) {
            Session::flash('error', 'The selected call is not open for applications.');
            redirect('index.php?action=calls_public');
        }
        if ($id === 0) {
            // Eligibility gates before creation (§12, §98)
            $count = ApplicationModel::countForCallByPi((int) $call['id'], Auth::id());
            $max = (int) ($call['max_applications_per_researcher'] ?? 1);
            if ($max > 0 && $count >= $max) {
                Session::flash('error', "You have reached the maximum number of applications ({$max}) for this call.");
                redirect('index.php?action=calls_public');
            }
            if (!empty($call['max_budget']) && (float) $clean['budget'] > (float) $call['max_budget']) {
                Session::flash('error', 'Requested budget exceeds the call maximum.');
                redirect('index.php?action=application_form&call_id=' . $call['id']);
            }
        }

        $user = Auth::user();
        // Routing comes from institutional data, never user choice (§17).
        $collegeId = (int) $user['college_id'];
        if ($collegeId === 0) {
            Session::flash('error', 'Your account has no college assigned. Contact the administrator.');
            redirect('index.php');
        }
        $data = [
            'call_id'         => (int) $call['id'],
            'title'           => $clean['title'],
            'college_id'      => $collegeId,
            'department_id'   => ((int) $user['department_id']) ?: null,
            'abstract'        => null,
            'background'      => null,
            'problem_statement' => null,
            'objectives'      => null,
            'research_questions' => null,
            'literature_review' => null,
            'methodology'     => null,
            'expected_results' => null,
            'significance'    => null,
            'innovation'      => null,
            'references_text' => null,
            'budget_requested' => (float) ($clean['budget'] ?? 0),
            'budget_justification' => trim((string) ($_POST['budget_justification'] ?? '')) ?: null,
            'requires_ethics' => $clean['requires_ethics'],
        ];

        try {
            if ($id > 0) {
                $existing = ApplicationModel::find($id);
                if ($existing === null) {
                    Session::flash('error', 'Application not found.');
                    redirect('index.php?action=applications');
                }
                if ((int) $existing['pi_user_id'] !== Auth::id() && Gate::denies('research.manage')) {
                    Gate::authorize('research.manage');
                }
                if ($existing['is_locked'] && Gate::denies('research.manage')) {
                    Session::flash('error', 'This application is locked and cannot be edited.');
                    redirect('index.php?action=application_view&id=' . $id);
                }
                ApplicationModel::update($id, $data);
                Audit::log('application.updated', 'research_application', $id);
                Session::flash('success', 'Application updated.');
            } else {
                $data['application_number'] = NumberingService::next('research_application');
                $data['pi_user_id'] = Auth::id();
                $data['created_by'] = Auth::id();
                $id = ApplicationModel::create($data);
                WorkflowService::initialize('research_application', 'Research Application Lifecycle', $id);
                Audit::log('application.created', 'research_application', $id, null, ['title' => $data['title']]);
                Session::flash('success', 'Draft application created. Attach the required documents, then submit.');
            }

            // Upload proposal document
            if (!empty($_FILES['proposal_document']['tmp_name'])) {
                [$ok, $err, $docId] = DocumentService::upload(
                    $_FILES['proposal_document'],
                    1, // proposal
                    'research_application',
                    $id,
                    'Proposal document',
                    'entity_team',
                    'Initial proposal submission'
                );
                if (!$ok) {
                    Session::flash('error', 'Proposal uploaded, but failed to save: ' . $err);
                }
            }
        } catch (\PDOException $e) {
            \App\Core\Logger::error('Application save failed', ['msg' => $e->getMessage()]);
            Session::flash('error', 'Could not save the application.');
            redirect('index.php?action=application_form' . ($id ? '&id=' . $id : ''));
        }

        // Work plan (§16)
        $items = [];
        $acts = (array) ($_POST['wp_activity'] ?? []);
        foreach ($acts as $i => $act) {
            if (trim((string) $act) === '') {
                continue;
            }
            $items[] = [
                'activity'        => (string) $act,
                'start_date'      => $_POST['wp_start'][$i] ?? null,
                'end_date'        => $_POST['wp_end'][$i] ?? null,
                'responsible'     => $_POST['wp_resp'][$i] ?? null,
                'expected_output' => $_POST['wp_out'][$i] ?? null,
            ];
        }
        ApplicationModel::setWorkPlan($id, $items);

        redirect('index.php?action=application_view&id=' . $id);
    }

    public static function view(): void
    {
        Gate::authorize('research.view');
        $id = (int) ($_GET['id'] ?? 0);
        $app = ApplicationModel::find($id);
        if ($app === null) {
            Session::flash('error', 'Application not found.');
            redirect('index.php?action=applications');
        }
        // Entity-level authorization: a general research.view permission is
        // not sufficient to read every confidential application.
        $isPi = (int) $app['pi_user_id'] === Auth::id();
        $isAcceptedTeamMember = (bool) Database::scalar(
            'SELECT COUNT(*) FROM application_team_members WHERE application_id = ? AND user_id = ? AND status = "accepted"',
            [$id, Auth::id()]
        );
        $isAssignedEvaluator = (bool) Database::scalar(
            'SELECT COUNT(*) FROM evaluator_assignments
             WHERE application_id = ? AND evaluator_user_id = ? AND is_active = 1
               AND status NOT IN ("conflict_declared", "declined", "cancelled")',
            [$id, Auth::id()]
        );
        $isCoordinator = Gate::hasRole('college_coordinator') || Gate::hasRole('dept_coordinator');
        $isSameCollegeCoordinator = $isCoordinator
            && (int) (Auth::user()['college_id'] ?? 0) > 0
            && (int) (Auth::user()['college_id'] ?? 0) === (int) $app['college_id'];
        // University-level reviewers may see the institutional queue; college
        // coordinators are deliberately excluded from this global permission path.
        $isInstitutionalReviewer = Gate::allows('research.review')
            && !$isCoordinator;
        $canManage = Gate::allows('research.manage') || $isInstitutionalReviewer;
        $committeeAssignment = \\App\\Models\\CommitteeModel::activeAssignment($id);
        $isCommitteeMember = $committeeAssignment !== null
            && \\App\\Models\\CommitteeModel::isMember((int) $committeeAssignment['committee_id'], Auth::id());

        if (!$isPi && !$isAcceptedTeamMember && !$isAssignedEvaluator
            && !$isSameCollegeCoordinator && !$canManage && !$isCommitteeMember) {
            http_response_code(403);
            include APP_PATH . '/views/errors/403.php';
            exit;
        }

        $documents = DocumentService::listFor('research_application', $id);
        $assignments = \App\Models\EvaluationModel::assignmentsFor($id);
        $evaluations = \App\Models\EvaluationModel::evaluationsFor($id);
        $history = WorkflowService::history('research_application', $id);
        $transitions = WorkflowService::availableTransitions($app, 'research_application');
        $revisionRequests = \App\Models\EvaluationModel::revisionRequests($id);
        // Per-request responses + researcher-visible evaluation comments (§8).
        $revisionResponses = [];
        foreach ($revisionRequests as $rr) {
            $revisionResponses[(int) $rr['id']] = \App\Models\EvaluationModel::revisionResponses((int) $rr['id']);
        }
        $researcherComments = [];
        foreach ($evaluations as $ev) {
            if ($ev['status'] === 'submitted') {
                $researcherComments[(int) $ev['id']] = \App\Models\EvaluationModel::commentsFor((int) $ev['id'], 'to_researcher');
            }
        }
        $auditTrail = \App\Models\AuditLogModel::recentForEntity('research_application', $id);
        $blind = $app['evaluation_mode'] ?? 'single_blind';
        $canSeeEvaluatorNames = $blind === 'open'
            || Gate::allows('research.screen', 'research.review', 'research.manage')
            || $isAssignedEvaluator;
        // Committee stage (§12), agreement (§13), budget allocation (§28).
        $committeeAssignment = \App\Models\CommitteeModel::activeAssignment($id);
        $committeeDecisions = \App\Models\CommitteeModel::decisionsFor($id);
        $isCommitteeMember = $committeeAssignment !== null
            && \App\Models\CommitteeModel::isMember((int) $committeeAssignment['committee_id'], Auth::id());
        $assignableCommittees = Gate::allows('research.manage') && in_array($app['status_code'], ['UNIVERSITY_REVIEW', 'ETHICS_REVIEW'], true)
            ? \App\Models\CommitteeModel::assignableCommittees()
            : [];
        $agreementVersions = \App\Models\AgreementModel::versions($id);
        $agreement = $agreementVersions ? $agreementVersions[count($agreementVersions) - 1] : null;
        $appTeamMembers = Database::all(
            'SELECT tm.*, u.full_name FROM application_team_members tm
             JOIN users u ON u.id = tm.user_id
             WHERE tm.application_id = ?',
            [$id]
        );
        $budgetApproverName = $app['budget_approved_by'] !== null
            ? ((string) Database::scalar('SELECT full_name FROM users WHERE id = ?', [(int) $app['budget_approved_by']]) ?: '—')
            : null;
        $canRecordBudget = Gate::allows('budget.approve') && $app['status_code'] === 'BUDGET_REVIEW';
        $budgetCap = (float) $app['budget_requested'];
        $callMaxBudget = (float) (Database::scalar('SELECT max_budget FROM research_calls WHERE id = ?', [(int) $app['call_id']]) ?? 0);
        if ($callMaxBudget > 0) {
            $budgetCap = min($budgetCap, $callMaxBudget);
        }

        include APP_PATH . '/views/research/application_view.php';
    }

    public static function submit(): void
    {
        Gate::authorize('research.submit');
        Csrf::verifyRequest();
        $id = (int) ($_POST['id'] ?? 0);
        $app = ApplicationModel::find($id);
        if ($app === null || (int) $app['pi_user_id'] !== Auth::id()) {
            Session::flash('error', 'Application not found or not yours.');
            redirect('index.php?action=applications');
        }
        if ($app['status_code'] !== 'DRAFT') {
            Session::flash('error', 'Only draft applications can be submitted.');
            redirect('index.php?action=application_view&id=' . $id);
        }
        // documents_complete condition verified inside the workflow fire (§96).
        [$ok, $err] = WorkflowService::fire($app, 'research_application', 'Submit application', null);
        if (!$ok) {
            Session::flash('error', $err);
        } else {
            Session::flash('success', 'Application submitted. It has been routed to your college for screening.');
            // College receipt is performed by the coordinator (permission research.screen);
            // coordinators were notified by NotificationService::notifyStageChange().
        }
        redirect('index.php?action=application_view&id=' . $id);
    }

    public static function withdraw(): void
    {
        Gate::authorize('research.submit');
        Csrf::verifyRequest();
        $id = (int) ($_POST['id'] ?? 0);
        $app = ApplicationModel::find($id);
        if ($app === null || (int) $app['pi_user_id'] !== Auth::id()) {
            Session::flash('error', 'Application not found or not yours.');
            redirect('index.php?action=applications');
        }
        [$ok, $err] = WorkflowService::fire($app, 'research_application', 'Withdraw', null);
        if (!$ok) {
            Session::flash('error', $err);
        } else {
            Session::flash('success', 'Application withdrawn.');
        }
        redirect('index.php?action=application_view&id=' . $id);
    }

    public static function uploadDocument(): void
    {
        Gate::authorize('document.upload');
        Csrf::verifyRequest();
        $id = (int) ($_POST['entity_id'] ?? 0);
        $app = ApplicationModel::find($id);
        if ($app === null) {
            Session::flash('error', 'Application not found.');
            redirect('index.php?action=applications');
        }
        $isPi = (int) $app['pi_user_id'] === Auth::id();
        if (!$isPi && Gate::denies('research.manage')) {
            Session::flash('error', 'You cannot upload documents to this application.');
            redirect('index.php?action=application_view&id=' . $id);
        }
        if ($app['is_locked'] && Gate::denies('research.manage') && !in_array($app['status_code'], ['REVISION_REQUIRED'], true)) {
            Session::flash('error', 'Application is locked.');
            redirect('index.php?action=application_view&id=' . $id);
        }
        $typeId = (int) ($_POST['document_type_id'] ?? 0);
        [$ok, $err, $docId] = DocumentService::upload(
            $_FILES['document'] ?? [],
            $typeId,
            'research_application',
            $id,
            trim((string) ($_POST['title'] ?? '')) ?: null,
            'entity_team',
            trim((string) ($_POST['reason'] ?? '')) ?: null
        );
        if ($ok) {
            Session::flash('success', 'Document uploaded as a new version.');
        } else {
            Session::flash('error', $err);
        }
        redirect('index.php?action=application_view&id=' . $id . '#documents');
    }

    public static function document(): void
    {
        // Served through download.php; kept for in-app links.
        $docId = (int) ($_GET['id'] ?? 0);
        DocumentService::download($docId);
    }

    // ---- Revision loop (spec §8, §25) ---------------------------------------

    /** Coordinator/directorate: request a revision with a structured reason. */
    public static function requestRevision(): void
    {
        Gate::authorize('research.request_revision');
        Csrf::verifyRequest();
        $id = (int) ($_POST['id'] ?? 0);
        $app = ApplicationModel::find($id);
        if ($app === null) {
            Session::flash('error', 'Application not found.');
            redirect('index.php?action=applications');
        }
        $reason = trim((string) ($_POST['reason'] ?? ''));
        if ($reason === '') {
            Session::flash('error', 'Describe the required corrections.');
            redirect('index.php?action=application_view&id=' . $id);
        }
        // College scoping (§5): coordinators may only request revisions on
        // their own college's research (Rule 5 layer).
        if ((Gate::hasRole('college_coordinator') || Gate::hasRole('dept_coordinator'))
            && Gate::denies('research.manage')
            && (int) (Auth::user()['college_id'] ?? 0) !== (int) $app['college_id']) {
            Session::flash('error', 'You cannot manage research belonging to another college (§5).');
            redirect('index.php?action=application_view&id=' . $id);
        }
        $deadline = ($_POST['deadline'] ?? '') ?: null;
        // Fire first (validates stage + permission), then record the request.
        [$ok, $err] = WorkflowService::fire($app, 'research_application', 'Request revision', $reason);
        if (!$ok) {
            Session::flash('error', $err);
            redirect('index.php?action=application_view&id=' . $id);
        }
        \App\Models\EvaluationModel::createRevisionRequest($id, Auth::id(), $reason, $deadline);
        Audit::log('revision.requested', 'research_application', $id, null, ['deadline' => $deadline]);
        Session::flash('success', 'Revision requested — the researcher has been notified.');
        redirect('index.php?action=application_view&id=' . $id);
    }

    /** Researcher: respond to evaluator comments / a revision request. */
    public static function respondRevision(): void
    {
        Gate::authorize('research.submit');
        Csrf::verifyRequest();
        $id = (int) ($_POST['application_id'] ?? 0);
        $app = ApplicationModel::find($id);
        if ($app === null || (int) $app['pi_user_id'] !== Auth::id()) {
            Session::flash('error', 'This is not your application.');
            redirect('index.php?action=applications');
        }
        $requestId = (int) ($_POST['revision_request_id'] ?? 0);
        $request = \App\Models\EvaluationModel::revisionRequestById($requestId);
        if ($request === null || (int) $request['application_id'] !== $id) {
            Session::flash('error', 'Revision request not found.');
            redirect('index.php?action=application_view&id=' . $id);
        }
        $response = trim((string) ($_POST['response'] ?? ''));
        if ($response === '') {
            Session::flash('error', 'Write your response.');
            redirect('index.php?action=application_view&id=' . $id);
        }
        $commentId = ((int) ($_POST['comment_id'] ?? 0)) ?: null;
        if ($commentId !== null) {
            $owns = (int) Database::scalar(
                'SELECT COUNT(*) FROM evaluation_comments ec JOIN evaluations e ON e.id = ec.evaluation_id
                 WHERE ec.id = ? AND e.application_id = ?',
                [$commentId, $id]
            );
            if ($owns === 0) {
                $commentId = null;
            }
        }
        \App\Models\EvaluationModel::addRevisionResponse($requestId, $commentId, $response, Auth::id());
        \App\Models\EvaluationModel::closeRevisionRequest($requestId, 'responded');
        Audit::log('revision.responded', 'research_application', $id, null, ['request' => $requestId, 'comment' => $commentId]);
        NotificationService::sendToCollegeRole((int) $app['college_id'], 'college_coordinator', 'revision_responded',
            'Researcher responded to revision request', $app['title'], 'index.php?action=application_view&id=' . $id);
        Session::flash('success', 'Response recorded. Upload the revised document version, then submit the revision.');
        redirect('index.php?action=application_view&id=' . $id . '#revision');
    }

    /** Researcher: submit the new version (v+1) for re-evaluation (§9). */
    public static function submitRevision(): void
    {
        Gate::authorize('research.submit');
        Csrf::verifyRequest();
        $id = (int) ($_POST['id'] ?? 0);
        $app = ApplicationModel::find($id);
        if ($app === null || (int) $app['pi_user_id'] !== Auth::id()) {
            Session::flash('error', 'This is not your application.');
            redirect('index.php?action=applications');
        }
        if ($app['status_code'] !== 'REVISION_REQUIRED') {
            Session::flash('error', 'No revision is currently required.');
            redirect('index.php?action=application_view&id=' . $id);
        }
        $requests = \App\Models\EvaluationModel::revisionRequests($id);
        foreach ($requests as $rr) {
            if ($rr['status'] === 'cancelled') {
                continue;
            }
            $answered = (int) Database::scalar(
                'SELECT COUNT(*) FROM revision_responses WHERE revision_request_id = ?',
                [(int) $rr['id']]
            );
            if ($answered === 0) {
                Session::flash('error', 'Respond to every revision request before submitting the new version.');
                redirect('index.php?action=application_view&id=' . $id . '#revision');
            }
        }

        [$ok, $err] = WorkflowService::fire($app, 'research_application', 'Submit revision', 'Resubmitting corrected version');
        if (!$ok) {
            Session::flash('error', $err);
            redirect('index.php?action=application_view&id=' . $id);
        }
        $newVersion = (int) $app['version'] + 1;
        Database::update('research_applications', ['version' => $newVersion], 'id = ?', [$id]);
        foreach ($requests as $rr) {
            if ($rr['status'] !== 'cancelled') {
                \App\Models\EvaluationModel::closeRevisionRequest((int) $rr['id'], 'closed');
            }
        }
        // Re-open the evaluator assignments for re-evaluation of the new version.
        Database::run(
            'UPDATE evaluator_assignments SET status = "assigned" WHERE application_id = ? AND is_active = 1 AND status = "completed"',
            [$id]
        );
        foreach (\App\Models\EvaluationModel::assignmentsFor($id) as $as) {
            if ((int) $as['is_active'] === 1 && $as['status'] === 'assigned') {
                NotificationService::send((int) $as['evaluator_user_id'], 're_evaluation_requested',
                    'Re-evaluation requested — version ' . $newVersion,
                    $app['title'], 'index.php?action=my_evaluations', 'high');
            }
        }
        Audit::log('revision.submitted', 'research_application', $id, ['version' => (int) $app['version']], ['version' => $newVersion]);
        Session::flash('success', 'Version ' . $newVersion . ' submitted for re-evaluation. Version ' . (int) $app['version'] . ' is preserved.');
        redirect('index.php?action=application_view&id=' . $id);
    }

    // ---- Budget allocation (spec §28 step 32; Rule 13) ---------------------

    /** Directorate/finance: record the approved budget allocation. */
    public static function recordBudgetDecision(): void
    {
        Gate::authorize('budget.approve');
        Csrf::verifyRequest();
        $id = (int) ($_POST['id'] ?? 0);
        $app = ApplicationModel::find($id);
        if ($app === null) {
            Session::flash('error', 'Application not found.');
            redirect('index.php?action=applications');
        }
        if ($app['status_code'] !== 'BUDGET_REVIEW') {
            Session::flash('error', 'The application is not in budget review.');
            redirect('index.php?action=application_view&id=' . $id);
        }
        if (!is_numeric($_POST['approved_amount'] ?? null)) {
            Session::flash('error', 'Enter the approved amount.');
            redirect('index.php?action=application_view&id=' . $id);
        }
        $amount = (float) $_POST['approved_amount'];
        $cap = (float) $app['budget_requested'];
        $callMax = (float) (Database::scalar('SELECT max_budget FROM research_calls WHERE id = ?', [(int) $app['call_id']]) ?? 0);
        if ($callMax > 0) {
            $cap = min($cap, $callMax);
        }
        if ($amount <= 0 || $amount > $cap + 0.001) {
            Audit::log('budget.allocation_rejected', 'research_application', $id, null, ['amount' => $amount, 'cap' => $cap]);
            Session::flash('error', sprintf('Allocation %.2f exceeds the available budget %.2f (Rule 13).', $amount, $cap));
            redirect('index.php?action=application_view&id=' . $id);
        }
        $note = trim((string) ($_POST['note'] ?? '')) ?: null;
        // Record first (transition condition: budget_recorded), fire second;
        // compensate if the transition is unavailable.
        $prevAmount = $app['budget_approved_amount'];
        $prevBy = $app['budget_approved_by'];
        Database::update('research_applications', [
            'budget_approved_amount' => $amount,
            'budget_approved_by'     => Auth::id(),
        ], 'id = ?', [$id]);
        [$ok, $err] = WorkflowService::fire($app, 'research_application', 'Budget approved', $note);
        if (!$ok) {
            Database::update('research_applications', [
                'budget_approved_amount' => $prevAmount,
                'budget_approved_by'     => $prevBy,
            ], 'id = ?', [$id]);
            Session::flash('error', $err);
            redirect('index.php?action=application_view&id=' . $id);
        }
        Audit::log('budget.allocation_recorded', 'research_application', $id, null, ['amount' => $amount, 'note' => $note]);
        NotificationService::send((int) $app['pi_user_id'], 'budget_approved', 'Budget allocation approved',
            sprintf('%s — approved: %s', $app['title'], format_money($amount)),
            'index.php?action=application_view&id=' . $id, 'high');
        Session::flash('success', 'Approved allocation recorded: ' . format_money($amount));
        redirect('index.php?action=application_view&id=' . $id);
    }

    public static function apiAddMember(): void
    {
        Gate::authorize('research.create');
        Csrf::verifyRequest();
        $applicationId = (int) ($_POST['application_id'] ?? 0);
        $userId = (int) ($_POST['user_id'] ?? 0);
        $app = ApplicationModel::find($applicationId);
        
        if ($app === null || (int) $app['pi_user_id'] !== Auth::id()) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
            return;
        }

        // Validate limit
        if (!ResearchTeamService::validateParticipationLimit($userId, 'co_investigator')) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Researcher has reached the co-investigator participation limit (3 active).']);
            return;
        }

        if ($userId === Auth::id()) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Cannot add yourself as a co-investigator.']);
            return;
        }
        $exists = Database::scalar('SELECT id FROM application_team_members WHERE application_id = ? AND user_id = ?', [$applicationId, $userId]);
        if ($exists) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Researcher already added.']);
            return;
        }
        Database::insert('application_team_members', [
            'application_id' => $applicationId,
            'user_id' => $userId,
            'role' => 'co_investigator',
            'status' => 'pending',
            'invited_at' => date('Y-m-d H:i:s')
        ]);
        echo json_encode(['ok' => true]);
    }

    public static function apiRemoveMember(): void
    {
        Gate::authorize('research.create');
        Csrf::verifyRequest();
        $applicationId = (int) ($_POST['application_id'] ?? 0);
        $memberId = (int) ($_POST['member_id'] ?? 0);
        $app = ApplicationModel::find($applicationId);
        if ($app === null || (int) $app['pi_user_id'] !== Auth::id()) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
            return;
        }
        Database::run('DELETE FROM application_team_members WHERE id = ? AND application_id = ?', [$memberId, $applicationId]);
        echo json_encode(['ok' => true]);
    }

    public static function apiRespondInvitation(): void
    {
        Gate::authorize('research.create');
        Csrf::verifyRequest();
        $memberId = (int) ($_POST['member_id'] ?? 0);
        $action = $_POST['action'] ?? ''; // 'accept' or 'decline'
        
        $member = Database::one('SELECT * FROM application_team_members WHERE id = ?', [$memberId]);
        if (!$member || (int) $member['user_id'] !== Auth::id()) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
            return;
        }

        if ($action === 'accept') {
            Database::update('application_team_members', [
                'status' => 'accepted',
                'responded_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$memberId]);
        } elseif ($action === 'decline') {
            Database::update('application_team_members', [
                'status' => 'declined',
                'responded_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$memberId]);
        } else {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid action']);
            return;
        }
        echo json_encode(['ok' => true]);
    }
}
