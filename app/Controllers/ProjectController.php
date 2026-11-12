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
use App\Models\ProjectModel;
use App\Services\NotificationService;
use App\Services\NumberingService;
use App\Services\WorkflowService;

/**
 * Research implementation (spec §35, §39–§44, §73).
 * Projects are created ONLY here — the application≠project distinction (§5).
 */
final class ProjectController
{
    // ---- Listing ------------------------------------------------------------

    public static function index(): void
    {
        Gate::authorize('project.view');
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $filters = [
            'status'  => (string) ($_GET['status'] ?? ''),
            'q'       => trim((string) ($_GET['q'] ?? '')),
        ];
        
        // Coordinator scoping: only their college
        if (Gate::hasRole('college_coordinator') && Gate::denies('research.manage')) {
             $filters['college_id'] = (int) (Auth::user()['college_id'] ?? 0);
        }
        
        if (Gate::allows('project.report') && Gate::denies('research.manage', 'research.screen', 'research.review', 'college_coordinator')) {
            $filters['pi'] = Auth::id(); // researchers see their own projects
        }
        $p = paginate(1, $page, 15);
        $result = $filters['pi'] ?? null
            ? ['total' => count(ProjectModel::forPi((int) $filters['pi'])), 'rows' => array_slice(ProjectModel::forPi((int) $filters['pi']), ($p['page'] - 1) * $p['limit'], $p['limit'])]
            : ProjectModel::paginate($filters, $p['page'], $p['limit']);
        $p = paginate($result['total'], $page, 15);
        $rows = $result['rows'];
        $counts = ProjectModel::statusCounts();
        $qs = $_SERVER['QUERY_STRING'] ?? '';
        include APP_PATH . '/views/projects/index.php';
    }

    // ---- Activation (§35) — creates the project from the approved application ----

    public static function activate(): void
    {
        Gate::authorize('project.activate');
        Csrf::verifyRequest();
        $appId = (int) ($_POST['application_id'] ?? 0);
        $app = ApplicationModel::find($appId);
        if ($app === null) {
            Session::flash('error', 'Application not found.');
            redirect('index.php?action=applications');
        }
        if (!in_array($app['status_code'], ['FUNDED'], true)) {
            Session::flash('error', 'Only funded applications can be activated (current stage: ' . status_label($app['status_code']) . ').');
            redirect('index.php?action=application_view&id=' . $appId);
        }
        if (ProjectModel::findByApplication($appId) !== null) {
            Session::flash('error', 'A project already exists for this application.');
            redirect('index.php?action=project_view&id=' . ProjectModel::findByApplication($appId)['id']);
        }
        // Required conditions (§96): ethics approval when the research required it,
        // and a research agreement signed by the researcher (Rules 10–11).
        if (($app['requires_ethics'] ?? 'no') === 'yes') {
            $eth = EthicsModel::findByResearchApplication($appId);
            if ($eth === null || !in_array($eth['status'], ['approved', 'approved_with_conditions', 'exempt'], true)) {
                Session::flash('error', 'Ethics approval is required before activation (§35).');
                redirect('index.php?action=application_view&id=' . $appId);
            }
        }
        if ((int) Database::scalar('SELECT COUNT(*) FROM research_agreements WHERE application_id = ? AND status = "signed"', [$appId]) === 0) {
            Session::flash('error', 'The research agreement must be issued and signed by the researcher before activation (Rule 11).');
            redirect('index.php?action=application_view&id=' . $appId);
        }

        // Approved allocation (§28) — falls back to the requested amount for
        // records created before allocation recording existed.
        $approvedBudget = $app['budget_approved_amount'] !== null
            ? (float) $app['budget_approved_amount']
            : (float) $app['budget_requested'];

        $projectId = Database::transaction(function () use ($app, $approvedBudget) {
            $number = NumberingService::next('research_project');
            $projectId = ProjectModel::create([
                'project_number'    => $number,
                'application_id'    => (int) $app['id'],
                'title'             => $app['title'],
                'pi_user_id'        => (int) $app['pi_user_id'],
                'college_id'        => (int) $app['college_id'],
                'department_id'     => $app['department_id'] !== null ? (int) $app['department_id'] : null,
                'research_type_id'  => $app['research_type_id'] !== null ? (int) $app['research_type_id'] : null,
                'research_theme_id' => $app['research_theme_id'] !== null ? (int) $app['research_theme_id'] : null,
                'duration_months'   => $app['duration_months'] !== null ? (int) $app['duration_months'] : null,
                'planned_start'     => $app['proposed_start_date'],
                'planned_end'       => $app['proposed_end_date'],
                'approved_budget'   => $approvedBudget,
                'status_code'       => 'ACTIVE',
                'created_by'        => Auth::id(),
            ]);
            // Team seeded from the application: PI.
            ProjectModel::addMember([
                'project_id'  => $projectId,
                'user_id'     => (int) $app['pi_user_id'],
                'member_role' => 'principal_investigator',
                'joined_at'   => date('Y-m-d'),
                'status'      => 'active',
            ]);

            // Preserve the accepted proposal team when the approved application
            // becomes a project. Pending or declined invitations are not members.
            $acceptedTeam = Database::all(
                'SELECT user_id, role FROM application_team_members
                 WHERE application_id = ? AND status = "accepted" AND user_id <> ?',
                [(int) $app['id'], (int) $app['pi_user_id']]
            );
            foreach ($acceptedTeam as $member) {
                ProjectModel::addMember([
                    'project_id'   => $projectId,
                    'user_id'      => (int) $member['user_id'],
                    'member_role'  => $member['role'] === 'co_investigator' ? 'co_investigator' : 'other',
                    'joined_at'    => date('Y-m-d'),
                    'status'       => 'active',
                    'approved_by'  => Auth::id(),
                ]);
            }

            // Default milestones from the work plan (§40).
            foreach (\App\Models\ApplicationModel::workPlan((int) $app['id']) as $i => $wp) {
                ProjectModel::addMilestone([
                    'project_id'         => $projectId,
                    'milestone_name'     => $wp['activity'],
                    'due_date'           => $wp['end_date'],
                    'responsible_user_id' => (int) $app['pi_user_id'],
                    'sort_order'         => $i,
                ]);
            }
            // Initial budget: the university budget approval (FUNDED) already
            // recorded the approved allocation.
            ProjectModel::applyBudgetApproval($projectId, $approvedBudget, Auth::id());
            return [$projectId, $number];
        });
        [$projectId, $number] = $projectId;

        WorkflowService::initialize('research_project', 'Research Project Lifecycle', $projectId);
        $projectRow = ProjectModel::find($projectId);
        if ($projectRow !== null) {
            WorkflowService::fire($projectRow, 'research_project', 'Activate project', 'Project activated');
            ProjectModel::update($projectId, ['actual_start' => date('Y-m-d')]);
        }
        // Mirror the activation on the application workflow (FUNDED → ACTIVE) so
        // the application's stage stays aligned with its project (§35, §76).
        $appRow = ApplicationModel::find((int) $app['id']);
        if ($appRow !== null && $appRow['status_code'] === 'FUNDED') {
            WorkflowService::fire($appRow, 'research_application', 'Activate project', 'Project ' . $number . ' activated');
        }
        Audit::log('project.activated', 'research_project', $projectId, null, ['application' => $appId, 'number' => $number]);
        NotificationService::send((int) $app['pi_user_id'], 'project_activated', 'Research project activated',
            $number . ' — ' . $app['title'], 'index.php?action=project_view&id=' . $projectId, 'high');
        Session::flash('success', 'Project activated: ' . $number);
        redirect('index.php?action=project_view&id=' . $projectId);
    }

    // ---- Dashboard (§39) -------------------------------------------------------

    public static function view(): void
    {
        Gate::authorize('project.view');
        $id = (int) ($_GET['id'] ?? 0);
        $project = ProjectModel::find($id);
        if ($project === null) {
            Session::flash('error', 'Project not found.');
            redirect('index.php?action=projects');
        }
        // Entity-level authorization: project.view alone must not expose
        // every project's confidential progress, budget, and attachments.
        $isPi = (int) $project['pi_user_id'] === Auth::id();
        $isMember = $isPi || ProjectModel::isMember($id, Auth::id());
        $isCoordinator = Gate::hasRole('college_coordinator') || Gate::hasRole('dept_coordinator');
        $isSameCollegeCoordinator = $isCoordinator
            && (int) (Auth::user()['college_id'] ?? 0) > 0
            && (int) (Auth::user()['college_id'] ?? 0) === (int) $project['college_id'];
        // Technical system administrators and committee reviewers do not
        // automatically receive institution-wide project access.
        $isDirectorateOfficer = Gate::hasRole('rpee_director') || Gate::hasRole('rpee_officer');
        $isInstitutionalManager = $isDirectorateOfficer
            && (Gate::allows('research.manage') || Gate::allows('project.activate'));
        if (!$isMember && !$isSameCollegeCoordinator && !$isInstitutionalManager) {
            http_response_code(403);
            include APP_PATH . '/views/errors/403.php';
            exit;
        }
        $members = ProjectModel::members($id);
        $milestones = ProjectModel::milestones($id);
        $reports = ProjectModel::reports($id);
        $budget = ProjectModel::budget($id);
        $budgetItems = $budget ? ProjectModel::budgetItems((int) $budget['id']) : [];
        $releases = ProjectModel::fundingReleases($id);
        $extensions = ProjectModel::extensionRequests($id);
        $actions = ProjectModel::actions($id);
        $documents = \App\Services\DocumentService::listFor('research_project', $id);
        $transitions = WorkflowService::availableTransitions($project, 'research_project');
        $history = WorkflowService::history('research_project', $id);
        $released = ProjectModel::totalReleased($id);
        include APP_PATH . '/views/projects/view.php';
    }

    // ---- Milestones (§40) -------------------------------------------------------

    public static function addMilestone(): void
    {
        Gate::authorize('project.activate');
        Csrf::verifyRequest();
        $projectId = (int) ($_POST['project_id'] ?? 0);
        $name = trim((string) ($_POST['milestone_name'] ?? ''));
        if ($name === '') {
            Session::flash('error', 'Milestone name is required.');
            redirect('index.php?action=project_view&id=' . $projectId);
        }
        ProjectModel::addMilestone([
            'project_id'         => $projectId,
            'milestone_name'     => $name,
            'description'        => trim((string) ($_POST['description'] ?? '')) ?: null,
            'due_date'           => ($_POST['due_date'] ?? '') ?: null,
            'responsible_user_id' => ((int) ($_POST['responsible_user_id'] ?? 0)) ?: null,
            'sort_order'         => 99,
        ]);
        Audit::log('milestone.added', 'research_project', $projectId, null, ['name' => $name]);
        Session::flash('success', 'Milestone added.');
        redirect('index.php?action=project_view&id=' . $projectId . '#milestones');
    }

    public static function updateMilestone(): void
    {
        Gate::authorize('project.report');
        Csrf::verifyRequest();
        $id = (int) ($_POST['id'] ?? 0);
        $m = ProjectModel::milestoneById($id);
        if ($m === null) {
            Session::flash('error', 'Milestone not found.');
            redirect('index.php?action=projects');
        }
        $status = (string) ($_POST['status'] ?? $m['status']);
        $data = [
            'status'        => in_array($status, ['pending', 'in_progress', 'completed', 'delayed', 'cancelled'], true) ? $status : $m['status'],
            'progress_pct'  => is_numeric($_POST['progress_pct'] ?? null) ? min(100, max(0, (float) $_POST['progress_pct'])) : $m['progress_pct'],
            'completion_date' => $status === 'completed' ? date('Y-m-d') : null,
        ];
        ProjectModel::updateMilestone($id, $data);
        Audit::log('milestone.updated', 'research_project', (int) $m['project_id'], ['status' => $m['status']], $data);
        Session::flash('success', 'Milestone updated.');
        redirect('index.php?action=project_view&id=' . (int) $m['project_id'] . '#milestones');
    }

    // ---- Progress reports (§41) ----------------------------------------------------

    public static function submitReport(): void
    {
        Gate::authorize('project.report');
        Csrf::verifyRequest();
        $projectId = (int) ($_POST['project_id'] ?? 0);
        $project = ProjectModel::find($projectId);
        if ($project === null || ((int) $project['pi_user_id'] !== Auth::id() && !ProjectModel::isMember($projectId, Auth::id()) && Gate::denies('research.manage'))) {
            Session::flash('error', 'Not authorized to report on this project.');
            redirect('index.php?action=projects');
        }
        if (!in_array((string) $project['status_code'], ['ACTIVE', 'PROGRESS_MONITORING'], true)) {
            Session::flash('error', 'Reports are accepted only while the project is active and before closure.');
            redirect('index.php?action=project_view&id=' . $projectId);
        }

        $periodStartRaw = trim((string) ($_POST['period_start'] ?? ''));
        $periodEndRaw = trim((string) ($_POST['period_end'] ?? ''));
        $periodStartDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $periodStartRaw);
        $periodEndDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $periodEndRaw);
        if (!$periodStartDate || !$periodEndDate
            || $periodStartDate->format('Y-m-d') !== $periodStartRaw
            || $periodEndDate->format('Y-m-d') !== $periodEndRaw
            || $periodStartDate->format('d') !== '01'
            || $periodEndDate->format('Y-m') !== $periodStartDate->format('Y-m')
            || $periodEndDate->format('d') !== $periodEndDate->format('t')) {
            Session::flash('error', 'A progress report must cover one complete calendar month (first through last day).');
            redirect('index.php?action=project_view&id=' . $projectId . '#reports');
        }
        $period = $periodStartDate->format('Y-m');
        if ($periodStartRaw > date('Y-m-d')
            || (!empty($project['actual_start']) && $periodEndRaw < $project['actual_start'])) {
            Session::flash('error', 'The reporting period must not be in the future or before project activation.');
            redirect('index.php?action=project_view&id=' . $projectId . '#reports');
        }
        $duplicate = (int) Database::scalar(
            'SELECT COUNT(*) FROM progress_reports WHERE project_id = ? AND reporting_period = ?',
            [$projectId, $period]
        );
        if ($duplicate > 0) {
            Session::flash('error', 'A report for ' . $period . ' has already been submitted.');
            redirect('index.php?action=project_view&id=' . $projectId . '#reports');
        }
        if (!is_numeric($_POST['progress_pct'] ?? null)
            || (float) $_POST['progress_pct'] < 0 || (float) $_POST['progress_pct'] > 100) {
            Session::flash('error', 'Project progress must be a number between 0 and 100.');
            redirect('index.php?action=project_view&id=' . $projectId . '#reports');
        }
        $progress = (float) $_POST['progress_pct'];
        $reportId = ProjectModel::createReport([
            'project_id'             => $projectId,
            'report_number'          => NumberingService::next('progress_report'),
            'reporting_period'       => $period,
            'period_start'           => $periodStartRaw,
            'period_end'             => $periodEndRaw,
            'progress_pct'           => $progress,
            'completed_activities'   => trim((string) ($_POST['completed_activities'] ?? '')) ?: null,
            'pending_activities'     => trim((string) ($_POST['pending_activities'] ?? '')) ?: null,
            'challenges'             => trim((string) ($_POST['challenges'] ?? '')) ?: null,
            'achievements'           => trim((string) ($_POST['achievements'] ?? '')) ?: null,
            'financial_progress_pct' => is_numeric($_POST['financial_progress_pct'] ?? null) ? (float) $_POST['financial_progress_pct'] : null,
            'amount_spent_to_date'   => is_numeric($_POST['amount_spent'] ?? null) ? (float) $_POST['amount_spent'] : null,
            'status'                 => 'submitted',
            'submitted_at'           => date('Y-m-d H:i:s'),
            'created_by'             => Auth::id(),
        ]);
        // Attach supporting documents uploaded inline.
        if (!empty($_FILES['report_document']['name'])) {
            \App\Services\DocumentService::upload(
                $_FILES['report_document'],
                (int) (Database::scalar('SELECT id FROM document_types WHERE type_code = "progress_report"') ?? 0),
                'research_project',
                $projectId,
                'Progress report #' . $reportId,
                'entity_team',
                'Progress report submission'
            );
        }
        Audit::log('progress_report.submitted', 'research_project', $projectId, null, ['report' => $reportId, 'progress' => $progress]);
        NotificationService::sendToCollegeRole((int) $project['college_id'], 'college_coordinator', 'progress_report_submitted',
            'Progress report submitted', $project['project_number'], 'index.php?action=project_view&id=' . $projectId);
        Session::flash('success', 'Progress report submitted.');
        redirect('index.php?action=project_view&id=' . $projectId . '#reports');
    }

    public static function reviewReport(): void
    {
        Gate::authorize('research.screen');
        Csrf::verifyRequest();
        $id = (int) ($_POST['id'] ?? 0);
        $report = ProjectModel::reportById($id);
        if ($report === null) {
            Session::flash('error', 'Report not found.');
            redirect('index.php?action=projects');
        }
        $decision = (string) ($_POST['decision'] ?? '');
        $map = ['approved' => 'approved', 'revision_required' => 'revision_required', 'rejected' => 'rejected'];
        if (!isset($map[$decision])) {
            Session::flash('error', 'Invalid decision.');
            redirect('index.php?action=project_view&id=' . (int) $report['project_id']);
        }
        ProjectModel::updateReport($id, [
            'status'          => $map[$decision],
            'reviewed_by'     => Auth::id(),
            'reviewed_at'     => date('Y-m-d H:i:s'),
            'review_comments' => trim((string) ($_POST['comments'] ?? '')) ?: null,
        ]);
        Audit::log('progress_report.reviewed', 'research_project', (int) $report['project_id'], null, ['report' => $id, 'decision' => $decision]);
        NotificationService::send((int) $report['pi_user_id'], 'progress_report_reviewed', 'Progress report ' . str_replace('_', ' ', $decision),
            $report['project_number'], 'index.php?action=project_view&id=' . (int) $report['project_id']);
        Session::flash('success', 'Report ' . str_replace('_', ' ', $decision) . '.');
        redirect('index.php?action=project_view&id=' . (int) $report['project_id'] . '#reports');
    }

    // ---- Funding release (§36) ------------------------------------------------------

    public static function releaseFunds(): void
    {
        Gate::authorize('budget.release');
        Csrf::verifyRequest();
        $projectId = (int) ($_POST['project_id'] ?? 0);
        $project = ProjectModel::find($projectId);
        if ($project === null) {
            Session::flash('error', 'Project not found.');
            redirect('index.php?action=projects');
        }
        $budget = ProjectModel::budget($projectId);
        if ($budget === null || $budget['status'] !== 'approved') {
            Session::flash('error', 'Budget must be approved before releasing funds.');
            redirect('index.php?action=project_view&id=' . $projectId);
        }
        $amount = is_numeric($_POST['amount'] ?? null) ? (float) $_POST['amount'] : 0;
        $released = ProjectModel::totalReleased($projectId);
        $remaining = (float) $budget['approved_amount'] - $released;
        if ($amount <= 0 || $amount > $remaining + 0.001) {
            Session::flash('error', sprintf('Invalid amount. Remaining releasable: %.2f.', max(0, $remaining)));
            redirect('index.php?action=project_view&id=' . $projectId);
        }
        ProjectModel::recordRelease([
            'project_id'        => $projectId,
            'budget_id'         => (int) $budget['id'],
            'amount'            => $amount,
            'release_reference' => trim((string) ($_POST['reference'] ?? '')) ?: null,
            'note'              => trim((string) ($_POST['note'] ?? '')) ?: null,
            'released_by'       => Auth::id(),
            'released_at'       => date('Y-m-d H:i:s'),
        ]);
        Database::update('research_budgets', ['released_amount' => $released + $amount], 'id = ?', [(int) $budget['id']]);
        Audit::log('funds.released', 'research_project', $projectId, null, ['amount' => $amount, 'total' => $released + $amount]);
        NotificationService::send((int) $project['pi_user_id'], 'funding_released', 'Funds released',
            sprintf('%s: %.2f released (%.2f total)', $project['project_number'], $amount, $released + $amount),
            'index.php?action=project_view&id=' . $projectId, 'high');
        Session::flash('success', sprintf('Funds released: %.2f', $amount));
        redirect('index.php?action=project_view&id=' . $projectId . '#budget');
    }

    // ---- Extension requests (§43) ------------------------------------------------------

    public static function requestExtension(): void
    {
        Gate::authorize('project.report');
        Csrf::verifyRequest();
        $projectId = (int) ($_POST['project_id'] ?? 0);
        $project = ProjectModel::find($projectId);
        if ($project === null || ((int) $project['pi_user_id'] !== Auth::id() && Gate::denies('research.manage'))) {
            Session::flash('error', 'Not authorized.');
            redirect('index.php?action=projects');
        }
        $original = $project['planned_end'] ?? date('Y-m-d');
        $requested = ($_POST['requested_deadline'] ?? '');
        $reason = trim((string) ($_POST['reason'] ?? ''));
        if ($requested === '' || $reason === '') {
            Session::flash('error', 'New deadline and reason are required.');
            redirect('index.php?action=project_view&id=' . $projectId . '#extensions');
        }
        ProjectModel::createExtensionRequest([
            'project_id'         => $projectId,
            'original_deadline'  => $original,
            'requested_deadline' => $requested,
            'reason'             => $reason,
            'status'             => 'pending',
            'requested_by'       => Auth::id(),
            'requested_at'       => date('Y-m-d H:i:s'),
        ]);
        Audit::log('extension.requested', 'research_project', $projectId, null, ['to' => $requested]);
        NotificationService::sendToCollegeRole((int) $project['college_id'], 'college_coordinator', 'extension_requested',
            'Deadline extension requested', $project['project_number'], 'index.php?action=project_view&id=' . $projectId, 'high');
        Session::flash('success', 'Extension request submitted.');
        redirect('index.php?action=project_view&id=' . $projectId . '#extensions');
    }

    public static function decideExtension(): void
    {
        Gate::authorize('research.screen');
        Csrf::verifyRequest();
        $id = (int) ($_POST['id'] ?? 0);
        $x = ProjectModel::extensionById($id);
        if ($x === null) {
            Session::flash('error', 'Extension request not found.');
            redirect('index.php?action=projects');
        }
        $decision = (string) ($_POST['decision'] ?? '');
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            Session::flash('error', 'Invalid decision.');
            redirect('index.php?action=project_view&id=' . (int) $x['project_id']);
        }
        $approvedDeadline = $decision === 'approved' ? ($_POST['approved_deadline'] ?? $x['requested_deadline']) : null;
        ProjectModel::updateExtensionRequest($id, [
            'status'            => $decision,
            'approved_deadline' => $approvedDeadline,
            'decided_by'        => Auth::id(),
            'decided_at'        => date('Y-m-d H:i:s'),
            'decision_comments' => trim((string) ($_POST['comments'] ?? '')) ?: null,
        ]);
        if ($decision === 'approved' && $approvedDeadline) {
            ProjectModel::update((int) $x['project_id'], ['planned_end' => $approvedDeadline]);
        }
        Audit::log('extension.decided', 'research_project', (int) $x['project_id'], null, ['decision' => $decision, 'new_end' => $approvedDeadline]);
        Session::flash('success', 'Extension ' . $decision . '.');
        redirect('index.php?action=project_view&id=' . (int) $x['project_id'] . '#extensions');
    }

    // ---- Suspension / cancellation (§44) ---------------------------------------------

    public static function action(): void
    {
        Gate::authorize('research.manage');
        Csrf::verifyRequest();
        $projectId = (int) ($_POST['project_id'] ?? 0);
        $project = ProjectModel::find($projectId);
        if ($project === null) {
            Session::flash('error', 'Project not found.');
            redirect('index.php?action=projects');
        }
        $type = (string) ($_POST['action_type'] ?? '');
        $reason = trim((string) ($_POST['reason'] ?? ''));
        if (!in_array($type, ['suspend', 'resume', 'cancel', 'terminate'], true) || $reason === '') {
            Session::flash('error', 'Valid action type and reason are required.');
            redirect('index.php?action=project_view&id=' . $projectId);
        }
        $transitionMap = ['suspend' => 'Suspend project', 'resume' => 'Resume project', 'cancel' => 'Cancel project', 'terminate' => 'Terminate project'];
        [$ok, $err] = WorkflowService::fire($project, 'research_project', $transitionMap[$type], $reason);
        if (!$ok) {
            Session::flash('error', $err);
            redirect('index.php?action=project_view&id=' . $projectId);
        }
        ProjectModel::recordAction([
            'project_id'    => $projectId,
            'action_type'   => $type,
            'reason'        => $reason,
            'comments'      => trim((string) ($_POST['comments'] ?? '')) ?: null,
            'previous_status' => $project['status_code'],
            'new_status'    => ProjectModel::find($projectId)['status_code'] ?? null,
            'approved_by'   => Auth::id(),
            'action_at'     => date('Y-m-d H:i:s'),
        ]);
        if (in_array($type, ['cancel', 'terminate'], true)) {
            ProjectModel::update($projectId, ['actual_end' => date('Y-m-d')]);
        }
        Audit::log('project.' . $type, 'research_project', $projectId, null, ['reason' => $reason]);
        NotificationService::send((int) $project['pi_user_id'], 'project_' . $type, 'Project ' . $type . ' action',
            $project['project_number'] . ': ' . $reason, 'index.php?action=project_view&id=' . $projectId, 'high');
        Session::flash('success', 'Project ' . $type . ' recorded.');
        redirect('index.php?action=project_view&id=' . $projectId);
    }
}
