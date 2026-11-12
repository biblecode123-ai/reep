<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Gate;
use App\Services\NotificationService;

final class DashboardController
{
    public static function index(): void
    {
        if (Gate::hasRole('rpee_director')) {
            \App\Controllers\DirectorDashboardController::index();
            return;
        }

        if (Gate::hasRole('system_admin')) {
            self::adminDashboard();
            return;
        }

        if (Gate::hasRole('researcher') && Gate::denies('research.manage') && !Gate::hasRole('college_coordinator')) {
            redirect('index.php?action=researcher_dashboard');
        }
        
        if (Gate::hasRole('college_coordinator') && Gate::denies('research.manage')) {
            redirect('index.php?action=coordinator_dashboard');
        }

        $userId = (int) Auth::id();
        $data   = [
            'unread'     => NotificationService::unreadCount($userId),
            'statuses'   => [],
            'openCalls'  => [],
            'myApps'     => [],
            'stats'      => [],
            'evalStats'  => [],
            'collegeQueue' => [],
        ];

        if (Gate::allows('research.view') && (Gate::hasRole('college_coordinator') || Gate::hasRole('dept_coordinator'))) {
            $collegeId = (int) (Auth::user()['college_id'] ?? 0);
            $data['collegeQueue'] = Database::all(
                'SELECT a.id, a.application_number, a.title, a.status_code, u.full_name AS pi_name
                 FROM research_applications a JOIN users u ON u.id = a.pi_user_id
                 WHERE a.college_id = ? AND a.status_code IN ("SUBMITTED","COLLEGE_RECEIVED","UNDER_COLLEGE_SCREENING","REVISION_SUBMITTED")
                 ORDER BY a.submitted_at LIMIT 10',
                [$collegeId]
            );
        }

        if (Gate::allows('research.evaluate')) {
            $data['evalStats'] = \App\Models\EvaluationModel::countByStatusForEvaluator($userId);
        }

        if (Gate::allows('research.create')) {
            $data['openCalls'] = \App\Models\CallModel::openCalls();
            $data['myApps']    = \App\Models\ApplicationModel::forPi($userId, 8);
        }

        // Module overviews for specialist roles (§45–§49, §55). Module-view holders
        // without research.manage get their own dashboard blocks; directors and
        // officers already see the institutional stats grid above.
        if (Gate::allows('publication.view') && Gate::denies('research.manage')) {
            $data['pubStats'] = [
                'published' => (int) Database::scalar('SELECT COUNT(*) FROM publications WHERE status = "published"'),
                'pipeline'  => (int) Database::scalar('SELECT COUNT(*) FROM publications WHERE status IN ("draft","submitted","under_review","revision_required","accepted")'),
            ];
            $data['recentPubs'] = Database::all(
                'SELECT p.title, p.journal_name, p.publication_year, p.status
                 FROM publications p ORDER BY p.updated_at DESC, p.id DESC LIMIT 8'
            );
        }

        if (Gate::allows('extension.view') && Gate::denies('research.manage')) {
            $data['extStats'] = [
                'active'    => (int) Database::scalar('SELECT COUNT(*) FROM extension_projects WHERE status = "active"'),
                'planned'   => (int) Database::scalar('SELECT COUNT(*) FROM extension_activities WHERE status = "planned"'),
                'completed' => (int) Database::scalar('SELECT COUNT(*) FROM extension_activities WHERE status = "completed"'),
                'beneficiaries' => (int) Database::scalar('SELECT COALESCE(SUM(beneficiary_count), 0) FROM extension_activities'),
            ];
            $data['recentExt'] = Database::all(
                'SELECT ep.project_number, ep.title, ep.community, ep.status, ep.planned_end
                 FROM extension_projects ep ORDER BY ep.id DESC LIMIT 8'
            );
        }

        if (Gate::allows('presentation.view') && Gate::denies('research.manage')) {
            $data['panels'] = Database::all(
                'SELECT pp.member_role, pr.scheduled_at, pr.venue, pr.status AS presentation_status, rp.title
                 FROM presentation_panels pp
                 JOIN presentations pr ON pr.id = pp.presentation_id
                 JOIN research_projects rp ON rp.id = pr.project_id
                 WHERE pp.user_id = ?
                 ORDER BY pr.scheduled_at DESC LIMIT 8',
                [$userId]
            );
            $data['upcomingPanels'] = Database::all(
                'SELECT pr.scheduled_at, pr.venue, pr.status, rp.title
                 FROM presentations pr JOIN research_projects rp ON rp.id = pr.project_id
                 WHERE pr.status = "scheduled"
                 ORDER BY pr.scheduled_at LIMIT 8'
            );
        }

        if (Gate::allows('repository.view') && Gate::denies('research.manage')) {
            $data['repoStats'] = [
                'published' => (int) Database::scalar('SELECT COUNT(*) FROM repository_records WHERE status = "published"'),
                'draft'     => (int) Database::scalar('SELECT COUNT(*) FROM repository_records WHERE status = "draft"'),
                'embargoed' => (int) Database::scalar('SELECT COUNT(*) FROM repository_records WHERE status = "embargoed"'),
            ];
            $data['recentRepo'] = Database::all(
                'SELECT rr.repository_id_code, rr.title, rr.year, rr.visibility, rr.status
                 FROM repository_records rr ORDER BY rr.id DESC LIMIT 8'
            );
        }

        if (Gate::allows('research.manage') || Gate::isAdmin()) {
            $data['statuses'] = \App\Models\ApplicationModel::statusCounts();
            $data['stats'] = [
                'calls'        => (int) Database::scalar('SELECT COUNT(*) FROM research_calls'),
                'applications' => (int) Database::scalar('SELECT COUNT(*) FROM research_applications'),
                'projects'     => (int) Database::scalar('SELECT COUNT(*) FROM research_projects'),
                'active'       => (int) Database::scalar('SELECT COUNT(*) FROM research_projects WHERE status_code IN ("ACTIVE","PROGRESS_MONITORING")'),
                'pendingEval'  => (int) Database::scalar('SELECT COUNT(*) FROM research_applications WHERE status_code IN ("EVALUATOR_ASSIGNED","EVALUATOR_ACCEPTED","UNDER_EVALUATION")'),
                'published'    => (int) Database::scalar('SELECT COUNT(*) FROM publications WHERE status = "published"'),
                'ethicsPending'=> (int) Database::scalar('SELECT COUNT(*) FROM ethics_applications WHERE status IN ("submitted","under_screening","under_review")'),
                'repoRecords'  => (int) Database::scalar('SELECT COUNT(*) FROM repository_records WHERE status = "published"'),
            ];
        }

        // Make $data entries available to the view as variables.
        extract($data, EXTR_OVERWRITE);
        include APP_PATH . '/views/dashboards/home.php';
    }

    private static function adminDashboard(): void
    {
        $data = [
            'stats' => [
                'total_users'  => (int) Database::scalar('SELECT COUNT(*) FROM users'),
                'active_users' => (int) Database::scalar('SELECT COUNT(*) FROM users WHERE status = "active"'),
                'colleges'     => (int) Database::scalar('SELECT COUNT(*) FROM colleges'),
                'depts'        => (int) Database::scalar('SELECT COUNT(*) FROM departments'),
            ],
            'roles' => Database::all('SELECT r.name, COUNT(ur.user_id) as cnt FROM roles r LEFT JOIN user_roles ur ON ur.role_id = r.id WHERE r.name IN ("system_admin", "researcher", "college_coordinator", "rpee_director") GROUP BY r.id'),
            'recentLogs' => \App\Models\AuditLogModel::paginate([], 1, 10)['logs'],
        ];
        extract($data);
        include APP_PATH . '/views/dashboards/admin.php';
    }
}
