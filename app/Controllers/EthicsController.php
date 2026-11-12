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
use App\Models\EthicsModel;
use App\Services\NotificationService;
use App\Services\WorkflowService;

/**
 * Ethics module controller (spec §30–§34).
 */
final class EthicsController
{
    // ---- Listing & dashboard -------------------------------------------------

    public static function index(): void
    {
        Gate::authorize('ethics.view');
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $filters = [
            'status' => (string) ($_GET['status'] ?? ''),
            'q'      => trim((string) ($_GET['q'] ?? '')),
        ];
        // Researchers see only their own ethics applications.
        if (Gate::allows('ethics.submit') && Gate::denies('ethics.manage', 'ethics.review', 'ethics.screen')) {
            $filters['researcher'] = Auth::id();
        }
        $p = paginate(1, $page, 15);
        $result = EthicsModel::paginate($filters, $p['page'], $p['limit']);
        $p = paginate($result['total'], $page, 15);
        $rows = $result['rows'];
        $counts = EthicsModel::statusCounts();
        $expiring = Gate::allows('ethics.manage', 'ethics.decide') ? EthicsModel::expiringWithin(30) : [];
        $qs = $_SERVER['QUERY_STRING'] ?? '';
        include APP_PATH . '/views/ethics/index.php';
    }

    // ---- Creation from the research application (§30) --------------------------

    public static function createFromApplication(): void
    {
        Gate::authorize('ethics.submit');
        $appId = (int) ($_POST['research_application_id'] ?? 0);
        $app = ApplicationModel::find($appId);
        if ($app === null || (int) $app['pi_user_id'] !== Auth::id()) {
            Session::flash('error', 'Application not found or not yours.');
            redirect('index.php?action=applications');
        }
        if (EthicsModel::findByResearchApplication($appId) !== null) {
            Session::flash('error', 'An ethics application already exists for this research.');
            redirect('index.php?action=application_view&id=' . $appId);
        }
        $number = \App\Services\NumberingService::next('ethics_application');
        $id = EthicsModel::create([
            'application_number'      => $number,
            'research_application_id' => $appId,
            'researcher_user_id'      => Auth::id(),
            'participant_count'       => ($_POST['participant_count'] ?? '') !== '' ? (int) $_POST['participant_count'] : null,
            'participant_description' => trim((string) ($_POST['participant_description'] ?? '')) ?: null,
            'research_type_summary'   => trim((string) ($_POST['research_type_summary'] ?? '')) ?: null,
            'risk_level'              => in_array($_POST['risk_level'] ?? '', ['minimal', 'low', 'moderate', 'high'], true) ? $_POST['risk_level'] : null,
            'consent_process'         => trim((string) ($_POST['consent_process'] ?? '')) ?: null,
            'confidentiality_measures' => trim((string) ($_POST['confidentiality_measures'] ?? '')) ?: null,
            'data_collection_summary' => trim((string) ($_POST['data_collection_summary'] ?? '')) ?: null,
            'status'                  => 'draft',
            'created_by'              => Auth::id(),
        ]);
        WorkflowService::initialize('ethics_application', 'Ethics Review Workflow', $id);
        Audit::log('ethics.created', 'ethics_application', $id, null, ['research_app' => $appId]);
        Session::flash('success', 'Ethics application created. Complete it and submit.');
        redirect('index.php?action=ethics_view&id=' . $id);
    }

    public static function edit(): void
    {
        Gate::authorize('ethics.submit');
        $id = (int) ($_GET['id'] ?? 0);
        $eth = EthicsModel::find($id);
        if ($eth === null) {
            Session::flash('error', 'Ethics application not found.');
            redirect('index.php?action=ethics');
        }
        if ((int) $eth['researcher_user_id'] !== Auth::id() && Gate::denies('ethics.manage')) {
            Session::flash('error', 'Not authorized.');
            redirect('index.php?action=ethics');
        }
        include APP_PATH . '/views/ethics/form.php';
    }

    public static function save(): void
    {
        Gate::authorize('ethics.submit');
        Csrf::verifyRequest();
        $id = (int) ($_POST['id'] ?? 0);
        $eth = EthicsModel::find($id);
        if ($eth === null || ((int) $eth['researcher_user_id'] !== Auth::id() && Gate::denies('ethics.manage'))) {
            Session::flash('error', 'Not authorized.');
            redirect('index.php?action=ethics');
        }
        if (!in_array($eth['status'], ['draft', 'revision_required'], true)) {
            Session::flash('error', 'Only draft or returned ethics applications can be edited.');
            redirect('index.php?action=ethics_view&id=' . $id);
        }
        EthicsModel::update($id, [
            'participant_count'        => ($_POST['participant_count'] ?? '') !== '' ? (int) $_POST['participant_count'] : null,
            'participant_description'  => trim((string) ($_POST['participant_description'] ?? '')) ?: null,
            'research_type_summary'    => trim((string) ($_POST['research_type_summary'] ?? '')) ?: null,
            'risk_level'               => in_array($_POST['risk_level'] ?? '', ['minimal', 'low', 'moderate', 'high'], true) ? $_POST['risk_level'] : null,
            'consent_process'          => trim((string) ($_POST['consent_process'] ?? '')) ?: null,
            'confidentiality_measures' => trim((string) ($_POST['confidentiality_measures'] ?? '')) ?: null,
            'data_collection_summary'  => trim((string) ($_POST['data_collection_summary'] ?? '')) ?: null,
        ]);
        Audit::log('ethics.updated', 'ethics_application', $id);
        Session::flash('success', 'Ethics application saved.');
        redirect('index.php?action=ethics_view&id=' . $id);
    }

    public static function submit(): void
    {
        Gate::authorize('ethics.submit');
        Csrf::verifyRequest();
        $id = (int) ($_POST['id'] ?? 0);
        $eth = EthicsModel::find($id);
        if ($eth === null || (int) $eth['researcher_user_id'] !== Auth::id()) {
            Session::flash('error', 'Not authorized.');
            redirect('index.php?action=ethics');
        }
        [$ok, $err] = WorkflowService::fire($eth, 'ethics_application', 'Submit ethics application', null);
        if ($ok) {
            // Mirror the timestamp on the row.
            EthicsModel::update($id, ['submitted_at' => date('Y-m-d H:i:s')]);
            Session::flash('success', 'Ethics application submitted for screening.');
        } else {
            Session::flash('error', $err);
        }
        redirect('index.php?action=ethics_view&id=' . $id);
    }

    public static function view(): void
    {
        Gate::authorize('ethics.view');
        $id = (int) ($_GET['id'] ?? 0);
        $eth = EthicsModel::find($id);
        if ($eth === null) {
            Session::flash('error', 'Ethics application not found.');
            redirect('index.php?action=ethics');
        }
        $isResearcher = (int) $eth['researcher_user_id'] === Auth::id();
        $canManage = Gate::allows('ethics.manage', 'ethics.screen', 'ethics.decide');
        $isReviewer = (bool) Database::scalar(
            'SELECT COUNT(*) FROM ethics_reviews WHERE ethics_application_id = ? AND reviewer_user_id = ?',
            [$id, Auth::id()]
        );
        if (!$isResearcher && !$canManage && !$isReviewer && Gate::denies('ethics.view')) {
            Session::flash('error', 'Not authorized.');
            redirect('index.php');
        }
        $reviews = EthicsModel::reviews($id);
        $decisions = EthicsModel::decisions($id);
        $certificates = EthicsModel::certificates($id);
        $amendments = EthicsModel::amendments($id);
        $documents = \App\Services\DocumentService::listFor('ethics_application', $id);
        $transitions = WorkflowService::availableTransitions($eth, 'ethics_application');
        $history = WorkflowService::history('ethics_application', $id);
        include APP_PATH . '/views/ethics/view.php';
    }

    // ---- Screening (§31) -------------------------------------------------------

    public static function screen(): void
    {
        Gate::authorize('ethics.screen');
        Csrf::verifyRequest();
        $id = (int) ($_POST['id'] ?? 0);
        $eth = EthicsModel::find($id);
        if ($eth === null) {
            Session::flash('error', 'Ethics application not found.');
            redirect('index.php?action=ethics');
        }
        $track = (string) ($_POST['track'] ?? 'full');
        $note  = trim((string) ($_POST['screening_note'] ?? '')) ?: null;
        EthicsModel::update($id, ['review_track' => in_array($track, ['screening', 'full'], true) ? $track : 'full', 'screening_note' => $note]);

        // Record the screening as a decision when exempt/insufficient.
        if ($track === 'screening') {
            EthicsModel::recordDecision($id, [
                'decision'     => 'exempt',
                'comments'     => $note ?? 'Exempt after ethics screening.',
                'decided_by'   => Auth::id(),
                'decision_reference' => 'SCREEN-' . $eth['application_number'],
            ]);
            EthicsModel::update($id, ['status' => 'exempt', 'decided_at' => date('Y-m-d H:i:s')]);
            Audit::log('ethics.exempt', 'ethics_application', $id, null, ['note' => $note]);
            Session::flash('success', 'Marked exempt after screening.');
        } else {
            [$ok, $err] = WorkflowService::fire($eth, 'ethics_application', 'Ethics screening', $note);
            if (!$ok) {
                Session::flash('error', $err);
            } else {
                Session::flash('success', 'Ethics application moved to review.');
            }
        }
        redirect('index.php?action=ethics_view&id=' . $id);
    }

    // ---- Reviewer assignment (§32) ----------------------------------------------

    public static function assignForm(): void
    {
        Gate::authorize('ethics.manage');
        $id = (int) ($_GET['id'] ?? 0);
        $eth = EthicsModel::find($id);
        if ($eth === null) {
            Session::flash('error', 'Ethics application not found.');
            redirect('index.php?action=ethics');
        }
        $reviews = EthicsModel::reviews($id);
        $reviewers = Database::all(
            'SELECT DISTINCT u.id, u.full_name, u.email, d.name AS department_name
             FROM users u
             JOIN user_roles ur ON ur.user_id = u.id
             JOIN roles r ON r.id = ur.role_id
             LEFT JOIN departments d ON d.id = u.department_id
             WHERE r.name IN ("ethics_reviewer", "ethics_member") AND u.status = "active"
             ORDER BY u.full_name'
        );
        include APP_PATH . '/views/ethics/assign.php';
    }

    public static function assign(): void
    {
        Gate::authorize('ethics.manage');
        Csrf::verifyRequest();
        $id = (int) ($_POST['id'] ?? 0);
        $eth = EthicsModel::find($id);
        if ($eth === null) {
            Session::flash('error', 'Ethics application not found.');
            redirect('index.php?action=ethics');
        }
        $reviewerIds = array_map('intval', (array) ($_POST['reviewer_ids'] ?? []));
        if (!$reviewerIds) {
            Session::flash('error', 'Select at least one reviewer.');
            redirect('index.php?action=ethics_assign&id=' . $id);
        }
        $deadline = ($_POST['deadline'] ?? '') ?: null;
        foreach ($reviewerIds as $uid) {
            EthicsModel::assignReviewer($id, $uid, Auth::id(), $deadline);
            NotificationService::send($uid, 'ethics_review_assigned', 'Ethics review assigned',
                'Application ' . $eth['application_number'], 'index.php?action=ethics_review_form&id=' . $id, 'high');
        }
        // Ensure the application sits in UNDER_REVIEW once reviewers exist.
        // Bridge the state gap left by submission (SUBMITTED → UNDER_SCREENING → UNDER_REVIEW).
        $eth = EthicsModel::find($id);
        if (in_array($eth['status_code'], ['SUBMITTED', 'UNDER_SCREENING'], true)) {
            WorkflowService::fire($eth, 'ethics_application', 'Ethics screening', null);
            $eth = EthicsModel::find($id);
        }
        if (in_array($eth['status_code'], ['UNDER_SCREENING'], true)) {
            WorkflowService::fire($eth, 'ethics_application', 'Assign reviewers', null);
        } elseif (in_array($eth['status_code'], ['REVISION_REQUIRED'], true)) {
            WorkflowService::fire($eth, 'ethics_application', 'Resubmit for review', null);
        }
        Audit::log('ethics.reviewers_assigned', 'ethics_application', $id, null, ['reviewers' => $reviewerIds]);
        Session::flash('success', count($reviewerIds) . ' reviewer(s) assigned.');
        redirect('index.php?action=ethics_view&id=' . $id);
    }

    // ---- Reviewer actions ---------------------------------------------------------

    public static function myReviews(): void
    {
        Gate::authorize('ethics.review');
        $reviews = EthicsModel::forReviewer(Auth::id());
        include APP_PATH . '/views/ethics/my_reviews.php';
    }

    public static function reviewForm(): void
    {
        Gate::authorize('ethics.review');
        $id = (int) ($_GET['id'] ?? 0);
        $review = EthicsModel::reviewById($id);
        if ($review === null || (int) $review['reviewer_user_id'] !== Auth::id()) {
            Session::flash('error', 'Review not found or not yours.');
            redirect('index.php?action=ethics_my');
        }
        if ($review['status'] === 'completed') {
            Session::flash('error', 'This review is already completed and locked.');
            redirect('index.php?action=ethics_my');
        }
        $eth = EthicsModel::find((int) $review['ethics_application_id']);
        include APP_PATH . '/views/ethics/review_form.php';
    }

    public static function reviewRespond(): void
    {
        Gate::authorize('ethics.review');
        Csrf::verifyRequest();
        $id = (int) ($_POST['id'] ?? 0);
        $review = EthicsModel::reviewById($id);
        if ($review === null || (int) $review['reviewer_user_id'] !== Auth::id()) {
            Session::flash('error', 'Review not found or not yours.');
            redirect('index.php?action=ethics_my');
        }
        $action = (string) ($_POST['action_type'] ?? '');
        if ($action === 'accept') {
            EthicsModel::setReviewStatus($id, ['status' => 'accepted']);
            Audit::log('ethics.review_accepted', 'ethics_application', (int) $review['ethics_application_id']);
            Session::flash('success', 'Review accepted.');
            redirect('index.php?action=ethics_review_form&id=' . $id);
        }
        if ($action === 'decline') {
            EthicsModel::setReviewStatus($id, ['status' => 'declined']);
            Audit::log('ethics.review_declined', 'ethics_application', (int) $review['ethics_application_id']);
            Session::flash('success', 'Review declined.');
            redirect('index.php?action=ethics_my');
        }
        if ($action === 'submit') {
            $rec = (string) ($_POST['recommendation'] ?? '');
            if (!in_array($rec, ['approve', 'approve_with_conditions', 'revision', 'reject', 'exempt'], true)) {
                Session::flash('error', 'Select a valid recommendation.');
                redirect('index.php?action=ethics_review_form&id=' . $id);
            }
            EthicsModel::setReviewStatus($id, [
                'status'         => 'completed',
                'comments'       => trim((string) ($_POST['comments'] ?? '')) ?: null,
                'recommendation' => $rec,
                'submitted_at'   => date('Y-m-d H:i:s'),
            ]);
            Audit::log('ethics.review_completed', 'ethics_application', (int) $review['ethics_application_id'], null, ['recommendation' => $rec]);
            Session::flash('success', 'Review submitted.');
            redirect('index.php?action=ethics_my');
        }
        Session::flash('error', 'Invalid action.');
        redirect('index.php?action=ethics_my');
    }

    // ---- Decision (§31, §32) -------------------------------------------------------

    public static function decide(): void
    {
        Gate::authorize('ethics.decide');
        Csrf::verifyRequest();
        $id = (int) ($_POST['id'] ?? 0);
        $eth = EthicsModel::find($id);
        if ($eth === null) {
            Session::flash('error', 'Ethics application not found.');
            redirect('index.php?action=ethics');
        }
        $decision = (string) ($_POST['decision'] ?? '');
        $map = [
            'approved'                 => 'approved',
            'approved_with_conditions' => 'approved_with_conditions',
            'revision_required'        => 'revision_required',
            'rejected'                 => 'rejected',
        ];
        if (!isset($map[$decision])) {
            Session::flash('error', 'Invalid decision.');
            redirect('index.php?action=ethics_view&id=' . $id);
        }
        $conditions = trim((string) ($_POST['conditions'] ?? '')) ?: null;
        $comments   = trim((string) ($_POST['comments'] ?? '')) ?: null;
        $expiry     = ($_POST['expires_at'] ?? '') ?: null;

        // A decision may only be recorded from the UNDER_REVIEW stage (§31).
        // Approve-type decisions fire the matching transition; revision keeps the
        // application in a reviewable state.
        if (!in_array($eth['status_code'], ['UNDER_REVIEW'], true)) {
            Session::flash('error', 'Decisions require the application to be under review (assign reviewers first).');
            redirect('index.php?action=ethics_view&id=' . $id);
        }
        $transition = match ($decision) {
            'revision_required'        => 'Request revision',
            'approved'                 => 'Approve',
            'approved_with_conditions' => 'Approve with conditions',
            'rejected'                 => 'Reject',
        };

        // Transition first (validates state + reviews complete), then record.
        [$ok, $err] = WorkflowService::fire($eth, 'ethics_application', $transition, $comments);
        if (!$ok) {
            Session::flash('error', $err);
            redirect('index.php?action=ethics_view&id=' . $id);
        }

        $status = $map[$decision];
        EthicsModel::update($id, ['status' => $status, 'decided_at' => date('Y-m-d H:i:s'), 'approved_until' => $status === 'revision_required' ? null : $expiry]);
        EthicsModel::recordDecision($id, [
            'decision'           => $decision,
            'conditions'         => $conditions,
            'comments'           => $comments,
            'decision_reference' => trim((string) ($_POST['decision_reference'] ?? '')) ?: null,
            'decided_by'         => Auth::id(),
            'expires_at'         => $expiry,
        ]);
        Audit::log('ethics.decision', 'ethics_application', $id, null, ['decision' => $decision, 'expires' => $expiry]);

        NotificationService::send((int) $eth['researcher_user_id'], 'ethics_decision', 'Ethics decision recorded',
            'Application ' . $eth['application_number'] . ': ' . str_replace('_', ' ', $decision),
            'index.php?action=ethics_view&id=' . $id, 'high');
        Session::flash('success', 'Ethics decision recorded: ' . str_replace('_', ' ', $decision));
        redirect('index.php?action=ethics_view&id=' . $id);
    }

    // ---- Certificate (§32) ----------------------------------------------------------

    public static function certificate(): void
    {
        Gate::authorize('ethics.decide');
        Csrf::verifyRequest();
        $id = (int) ($_POST['id'] ?? 0);
        $eth = EthicsModel::find($id);
        if ($eth === null || !in_array($eth['status'], ['approved', 'approved_with_conditions'], true)) {
            Session::flash('error', 'A certificate can only be issued for approved applications.');
            redirect('index.php?action=ethics_view&id=' . $id);
        }
        $certId = EthicsModel::createCertificate($id, Auth::id(), ($_POST['expires_at'] ?? '') ?: $eth['approved_until']);
        Audit::log('ethics.certificate_issued', 'ethics_application', $id, null, ['certificate' => $certId]);
        NotificationService::send((int) $eth['researcher_user_id'], 'ethics_certificate', 'Ethics certificate issued',
            'Certificate for ' . $eth['application_number'] . ' is available.', 'index.php?action=ethics_view&id=' . $id);
        Session::flash('success', 'Certificate issued.');
        redirect('index.php?action=ethics_view&id=' . $id);
    }

    // ---- Renewal / expiry (§33) -------------------------------------------------------

    public static function renew(): void
    {
        Gate::authorize('ethics.decide');
        Csrf::verifyRequest();
        $id = (int) ($_POST['id'] ?? 0);
        $newExpiry = ($_POST['new_expiry'] ?? '') ?: null;
        $eth = EthicsModel::find($id);
        if ($eth === null || $newExpiry === null) {
            Session::flash('error', 'Invalid renewal request.');
            redirect('index.php?action=ethics_view&id=' . $id);
        }
        EthicsModel::update($id, [
            'status'         => 'approved',
            'approved_until' => $newExpiry,
        ]);
        EthicsModel::recordDecision($id, [
            'decision'   => 'approved',
            'comments'   => 'Renewal granted. New expiry ' . $newExpiry . '.',
            'decided_by' => Auth::id(),
            'expires_at' => $newExpiry,
        ]);
        Audit::log('ethics.renewed', 'ethics_application', $id, null, ['new_expiry' => $newExpiry]);
        NotificationService::send((int) $eth['researcher_user_id'], 'ethics_expiry', 'Ethics approval renewed',
            'New expiry: ' . $newExpiry, 'index.php?action=ethics_view&id=' . $id);
        Session::flash('success', 'Ethics approval renewed until ' . $newExpiry . '.');
        redirect('index.php?action=ethics_view&id=' . $id);
    }

    // ---- Amendments (§34) -------------------------------------------------------------

    public static function amendmentRequest(): void
    {
        Gate::authorize('ethics.submit');
        Csrf::verifyRequest();
        $id = (int) ($_POST['id'] ?? 0);
        $eth = EthicsModel::find($id);
        if ($eth === null || (int) $eth['researcher_user_id'] !== Auth::id()) {
            Session::flash('error', 'Not authorized.');
            redirect('index.php?action=ethics');
        }
        if (!in_array($eth['status'], ['approved', 'approved_with_conditions'], true)) {
            Session::flash('error', 'Amendments require an approved ethics application.');
            redirect('index.php?action=ethics_view&id=' . $id);
        }
        $type = (string) ($_POST['amendment_type'] ?? 'other');
        if (!in_array($type, ['methodology', 'participants', 'location', 'data_collection', 'instruments', 'other'], true)) {
            $type = 'other';
        }
        $description = trim((string) ($_POST['description'] ?? ''));
        if ($description === '') {
            Session::flash('error', 'Describe the amendment.');
            redirect('index.php?action=ethics_view&id=' . $id);
        }
        $amId = EthicsModel::createAmendment($id, Auth::id(), $type, $description);
        Audit::log('ethics.amendment_requested', 'ethics_application', $id, null, ['amendment' => $amId, 'type' => $type]);
        NotificationService::sendToRoles(['ethics_member', 'ethics_reviewer', 'rpee_officer'], 'ethics_amendment',
            'Ethics amendment requested', $eth['application_number'], 'index.php?action=ethics_view&id=' . $id, 'high');
        Session::flash('success', 'Amendment submitted for ethics review.');
        redirect('index.php?action=ethics_view&id=' . $id);
    }

    public static function amendmentDecide(): void
    {
        Gate::authorize('ethics.decide');
        Csrf::verifyRequest();
        $amId = (int) ($_POST['amendment_id'] ?? 0);
        $decision = (string) ($_POST['decision'] ?? '');
        $map = ['approved' => 'approved', 'revision_required' => 'revision_required', 'rejected' => 'rejected'];
        if (!isset($map[$decision])) {
            Session::flash('error', 'Invalid amendment decision.');
            redirect('index.php');
        }
        $am = Database::one('SELECT * FROM ethics_amendments WHERE id = ?', [$amId]);
        if ($am === null) {
            Session::flash('error', 'Amendment not found.');
            redirect('index.php');
        }
        EthicsModel::updateAmendment($amId, [
            'status'            => $map[$decision],
            'decided_by'        => Auth::id(),
            'decided_at'        => date('Y-m-d H:i:s'),
            'decision_comments' => trim((string) ($_POST['comments'] ?? '')) ?: null,
        ]);
        Audit::log('ethics.amendment_decided', 'ethics_application', (int) $am['ethics_application_id'], null, ['amendment' => $amId, 'decision' => $decision]);
        Session::flash('success', 'Amendment ' . str_replace('_', ' ', $decision) . '.');
        redirect('index.php?action=ethics_view&id=' . (int) $am['ethics_application_id']);
    }
}

