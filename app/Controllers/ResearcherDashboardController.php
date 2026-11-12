<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Gate;
use App\Models\ApplicationModel;
use App\Models\CallModel;
use App\Services\NotificationService;
use App\Services\MonthlyReportService;

/**
 * Researcher Dashboard Controller
 */
final class ResearcherDashboardController
{
    public static function index(): void
    {
        Gate::authorize('researcher.view');
        $userId = (int) Auth::id();
        $scalar = fn(string $sql, array $p = []) => (int) Database::scalar($sql, $p);

        // Dashboard Summary Cards
        $period = MonthlyReportService::currentPeriod();
        $dueProjects = MonthlyReportService::dueProjects($period, $userId);

        $stats = [
            'draftProposals' => $scalar(
                'SELECT COUNT(*) FROM research_applications WHERE pi_user_id = ? AND status_code = "DRAFT"',
                [$userId]
            ),
            'submittedProposals' => $scalar(
                'SELECT COUNT(*) FROM research_applications WHERE pi_user_id = ? AND status_code NOT IN ("DRAFT","WITHDRAWN")',
                [$userId]
            ),
            'underRevision' => $scalar(
                'SELECT COUNT(*) FROM research_applications WHERE pi_user_id = ? AND status_code = "AWAITING_COLLEGE_REVISION"',
                [$userId]
            ),
            'approvedProposals' => $scalar(
                'SELECT COUNT(*) FROM research_applications WHERE pi_user_id = ? AND status_code IN ("APPROVED","FUNDED")',
                [$userId]
            ),
            'activeProjects' => $scalar(
                'SELECT COUNT(*) FROM research_projects p
                 JOIN research_project_members m ON m.project_id = p.id
                 WHERE m.user_id = ? AND m.status = "active" AND p.status_code IN ("ACTIVE", "PROGRESS_MONITORING")',
                [$userId]
            ),
            'pendingAgreements' => $scalar(
                'SELECT COUNT(*) FROM research_agreements ag
                 JOIN research_applications a ON a.id = ag.application_id
                 WHERE a.pi_user_id = ? AND ag.status IN ("draft","issued")',
                [$userId]
            ),
            'pendingReports' => count(array_filter($dueProjects, fn($p) => true)), // Simplification for now, will refine
            'overdueReports' => count(array_filter($dueProjects, fn($p) => true)),
            'pendingEvaluations' => $scalar(
                'SELECT COUNT(*) FROM evaluator_assignments WHERE evaluator_user_id = ? AND status IN ("assigned","accepted")',
                [$userId]
            ),
        ];

        // Draft Proposals
        $draftProposals = Database::all(
            'SELECT a.id, a.title, a.created_at, c.title as call_title
             FROM research_applications a
             LEFT JOIN research_calls c ON c.id = a.call_id
             WHERE a.pi_user_id = ? AND a.status_code = "DRAFT"
             ORDER BY a.created_at DESC LIMIT 10',
            [$userId]
        );

        // Submitted Proposals
        $submittedProposals = Database::all(
            'SELECT a.id, a.application_number, a.title, a.status_code, a.submitted_at, c.title as call_title
             FROM research_applications a
             LEFT JOIN research_calls c ON c.id = a.call_id
             WHERE a.pi_user_id = ? AND a.status_code NOT IN ("DRAFT","WITHDRAWN")
             ORDER BY a.submitted_at DESC LIMIT 10',
            [$userId]
        );

        // Under Revision
        $underRevision = Database::all(
            'SELECT a.id, a.application_number, a.title, rr.request_reason, rr.requested_at
             FROM research_applications a
             LEFT JOIN revision_requests rr ON rr.application_id = a.id AND rr.status = "open"
             WHERE a.pi_user_id = ? AND a.status_code = "AWAITING_COLLEGE_REVISION"
             ORDER BY rr.requested_at DESC LIMIT 10',
            [$userId]
        );

        // Active Projects
        $activeProjects = Database::all(
            'SELECT p.id, p.project_number, p.title, p.status_code, p.actual_start, p.planned_end,
                   (SELECT COUNT(*) FROM progress_reports pr WHERE pr.project_id = p.id AND pr.status = "submitted" AND pr.period_end >= date("now")) as pending_reports
             FROM research_projects p
             JOIN research_project_members m ON m.project_id = p.id
             WHERE m.user_id = ? AND m.status = "active" AND p.status_code IN ("ACTIVE", "PROGRESS_MONITORING")
             ORDER BY p.actual_start DESC LIMIT 10',
            [$userId]
        );

        // Pending Agreements
        $pendingAgreements = Database::all(
            'SELECT ag.id, ag.version, ag.status, a.application_number, a.title
             FROM research_agreements ag
             JOIN research_applications a ON a.id = ag.application_id
             WHERE a.pi_user_id = ? AND ag.status IN ("draft","issued")
             ORDER BY ag.created_at DESC LIMIT 10',
            [$userId]
        );

        // Pending Progress Reports
        $pendingReports = Database::all(
            'SELECT pr.id, pr.report_number, p.project_number, p.title, pr.period_end, pr.status
             FROM progress_reports pr
             JOIN research_projects p ON p.id = pr.project_id
             JOIN research_project_members m ON m.project_id = p.id
             WHERE m.user_id = ? AND m.status = "active" AND pr.status = "submitted" AND pr.period_end >= date("now")
             ORDER BY pr.period_end ASC LIMIT 10',
            [$userId]
        );

        // Overdue Reports
        $overdueReports = Database::all(
            'SELECT pr.id, pr.report_number, p.project_number, p.title, pr.period_end, pr.status,
                   julianday("now") - julianday(pr.period_end) as days_overdue
             FROM progress_reports pr
             JOIN research_projects p ON p.id = pr.project_id
             JOIN research_project_members m ON m.project_id = p.id
             WHERE m.user_id = ? AND m.status = "active" AND pr.status = "submitted" AND pr.period_end < date("now")
             ORDER BY pr.period_end ASC LIMIT 10',
            [$userId]
        );

        // Pending Evaluations
        $pendingEvaluations = Database::all(
            'SELECT ea.id, a.application_number, a.title, u.full_name as pi_name, ea.deadline
             FROM evaluator_assignments ea
             JOIN research_applications a ON a.id = ea.application_id
             JOIN users u ON u.id = a.pi_user_id
             WHERE ea.evaluator_user_id = ? AND ea.status IN ("assigned","accepted")
             ORDER BY ea.deadline ASC LIMIT 10',
            [$userId]
        );

        // Completed Evaluations
        $completedEvaluations = Database::all(
            'SELECT e.id, a.application_number, a.title, e.weighted_score, e.submitted_at
             FROM evaluations e
             JOIN research_applications a ON a.id = e.application_id
             WHERE e.evaluator_user_id = ? AND e.status = "submitted"
             ORDER BY e.submitted_at DESC LIMIT 10',
            [$userId]
        );

        // Open Calls
        $openCalls = CallModel::openCalls();

        // Notifications
        $unread = NotificationService::unreadCount($userId);
        $notifications = Database::all(
            'SELECT id, notification_type, title, body, link_url, created_at
             FROM notifications
             WHERE user_id = ? AND read_at IS NULL
             ORDER BY created_at DESC LIMIT 10',
            [$userId]
        );

        extract([
            'stats' => $stats,
            'draftProposals' => $draftProposals,
            'submittedProposals' => $submittedProposals,
            'underRevision' => $underRevision,
            'activeProjects' => $activeProjects,
            'pendingAgreements' => $pendingAgreements,
            'pendingReports' => $pendingReports,
            'overdueReports' => $overdueReports,
            'pendingEvaluations' => $pendingEvaluations,
            'completedEvaluations' => $completedEvaluations,
            'openCalls' => $openCalls,
            'unread' => $unread,
            'notifications' => $notifications,
            'profileCompletion' => self::getProfileCompletion($userId),
        ]);
        include APP_PATH . '/views/dashboards/researcher.php';
    }

    private static function getProfileCompletion(int $userId): int
    {
        $profile = Database::one('SELECT * FROM researchers WHERE user_id = ?', [$userId]);
        if (!$profile) return 0;
        
        $fields = ['academic_position', 'qualification', 'specialization', 'research_interests', 'research_areas', 'biography', 'orcid'];
        $filled = 0;
        foreach ($fields as $field) {
            if (!empty($profile[$field])) $filled++;
        }
        return (int) round(($filled / count($fields)) * 100);
    }
}

