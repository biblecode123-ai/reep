<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Gate;
use App\Models\ApplicationModel;
use App\Models\ProjectModel;

final class CollegeCoordinatorController
{
    public static function index(): void
    {
        Gate::authorize('research.view');
        if (!Gate::hasRole('college_coordinator')) {
            http_response_code(403);
            exit('Access Denied');
        }

        $collegeId = (int) (Auth::user()['college_id'] ?? 0);
        $scalar = fn(string $sql, array $p = []) => (int) Database::scalar($sql, $p);

        // Dashboard Summary Cards
        $stats = [
            'totalProposals' => $scalar(
                'SELECT COUNT(*) FROM research_applications WHERE college_id = ?',
                [$collegeId]
            ),
            'newSubmissions' => $scalar(
                'SELECT COUNT(*) FROM research_applications WHERE college_id = ? AND status_code IN ("SUBMITTED","COLLEGE_RECEIVED","UNDER_COLLEGE_SCREENING","SUBMITTED_TO_RPEE","AWAITING_COLLEGE_REVISION")',
                [$collegeId]
            ),
            'awaitingAssignment' => $scalar(
                'SELECT COUNT(*) FROM research_applications WHERE college_id = ? AND status_code = "EVALUATOR_ASSIGNMENT_PENDING"',
                [$collegeId]
            ),
            'underEvaluation' => $scalar(
                'SELECT COUNT(*) FROM research_applications WHERE college_id = ? AND status_code IN ("UNDER_COLLEGE_EVALUATION","UNDER_EVALUATION","EVALUATOR_ASSIGNED","EVALUATOR_ACCEPTED")',
                [$collegeId]
            ),
            'awaitingDecision' => $scalar(
                'SELECT COUNT(*) FROM research_applications WHERE college_id = ? AND status_code = "AWAITING_COLLEGE_DECISION"',
                [$collegeId]
            ),
            'forwardedToUniversity' => $scalar(
                'SELECT COUNT(*) FROM research_applications WHERE college_id = ? AND status_code IN ("AWAITING_UNIVERSITY_SUBMISSION","SUBMITTED_TO_RPEE","AWAITING_UNIVERSITY_EVALUATION","UNDER_UNIVERSITY_EVALUATION","UNIVERSITY_REVIEW","AWAITING_COMMITTEE_DECISION","APPROVED","FUNDED")',
                [$collegeId]
            ),
            'collegeApproved' => $scalar(
                'SELECT COUNT(*) FROM research_applications WHERE college_id = ? AND status_code IN ("AWAITING_UNIVERSITY_SUBMISSION","SUBMITTED_TO_RPEE","AWAITING_UNIVERSITY_EVALUATION","UNDER_UNIVERSITY_EVALUATION","UNIVERSITY_REVIEW","APPROVED","FUNDED")',
                [$collegeId]
            ),
            'collegeRejected' => $scalar(
                'SELECT COUNT(*) FROM research_applications WHERE college_id = ? AND status_code IN ("REJECTED_BY_COLLEGE","COLLEGE_REJECTED")',
                [$collegeId]
            ),
            'pendingAssessments' => $scalar(
                'SELECT COUNT(*) FROM evaluator_assignments ea JOIN research_applications a ON a.id = ea.application_id WHERE a.college_id = ? AND ea.status IN ("assigned","accepted") AND ea.is_active = 1',
                [$collegeId]
            ),
            'overdueAssessments' => $scalar(
                'SELECT COUNT(*) FROM evaluator_assignments ea JOIN research_applications a ON a.id = ea.application_id WHERE a.college_id = ? AND ea.status IN ("assigned","accepted") AND ea.deadline IS NOT NULL AND ea.deadline < date("now")',
                [$collegeId]
            ),
            'activeProjects' => $scalar(
                'SELECT COUNT(*) FROM research_projects p
                 JOIN research_applications a ON a.id = p.application_id
                 WHERE a.college_id = ? AND p.status_code = "ACTIVE"',
                [$collegeId]
            ),
            'reportsSubmitted' => $scalar(
                'SELECT COUNT(*) FROM progress_reports pr
                 JOIN research_projects p ON p.id = pr.project_id
                 JOIN research_applications a ON a.id = p.application_id
                 WHERE a.college_id = ? AND pr.status = "submitted"',
                [$collegeId]
            ),
            'reportsOverdue' => $scalar(
                'SELECT COUNT(*) FROM progress_reports pr
                 JOIN research_projects p ON p.id = pr.project_id
                 JOIN research_applications a ON a.id = p.application_id
                 WHERE a.college_id = ? AND pr.status = "submitted" AND pr.period_end < date("now")',
                [$collegeId]
            ),
        ];

        // Proposals by Status
        $newSubmissions = Database::all(
            'SELECT a.id, a.application_number, a.title, a.status_code, u.full_name as pi_name, a.submitted_at
             FROM research_applications a
             JOIN users u ON u.id = a.pi_user_id
             WHERE a.college_id = ? AND a.status_code IN ("SUBMITTED_TO_RPEE","AWAITING_COLLEGE_REVISION")
             ORDER BY a.submitted_at DESC LIMIT 20',
            [$collegeId]
        );

        $underEvaluation = Database::all(
            'SELECT a.id, a.application_number, a.title, a.status_code, u.full_name as pi_name,
                   (SELECT COUNT(*) FROM evaluator_assignments ea WHERE ea.application_id = a.id AND ea.status IN ("assigned","accepted")) as pending_evaluators,
                   (SELECT COUNT(*) FROM evaluator_assignments ea WHERE ea.application_id = a.id AND ea.status = "completed") as completed_evaluators
             FROM research_applications a
             JOIN users u ON u.id = a.pi_user_id
             WHERE a.college_id = ? AND a.status_code = "UNDER_COLLEGE_EVALUATION"
             ORDER BY a.submitted_at DESC LIMIT 20',
            [$collegeId]
        );

        $awaitingDecision = Database::all(
            'SELECT a.id, a.application_number, a.title, u.full_name as pi_name,
                   (SELECT AVG(e.weighted_score) FROM evaluations e WHERE e.application_id = a.id) as avg_score,
                   (SELECT COUNT(*) FROM evaluations e WHERE e.application_id = a.id) as eval_count
             FROM research_applications a
             JOIN users u ON u.id = a.pi_user_id
             WHERE a.college_id = ? AND a.status_code = "AWAITING_COLLEGE_DECISION"
             ORDER BY a.submitted_at DESC LIMIT 20',
            [$collegeId]
        );

        // Active Projects
        $activeProjects = Database::all(
            'SELECT p.id, p.project_number, p.title, p.status_code, u.full_name as pi_name, p.actual_start,
                   (SELECT COUNT(*) FROM progress_reports pr WHERE pr.project_id = p.id AND pr.status = "submitted") as pending_reports
             FROM research_projects p
             JOIN research_applications a ON a.id = p.application_id
             JOIN users u ON u.id = a.pi_user_id
             WHERE a.college_id = ? AND p.status_code = "ACTIVE"
             ORDER BY p.actual_start DESC LIMIT 20',
            [$collegeId]
        );

        // Progress Reports Awaiting Review
        $pendingReports = Database::all(
            'SELECT pr.id, pr.report_number, p.project_number, p.title, u.full_name as pi_name,
                   pr.period_end, pr.submitted_at, pr.status
             FROM progress_reports pr
             JOIN research_projects p ON p.id = pr.project_id
             JOIN research_applications a ON a.id = p.application_id
             JOIN users u ON u.id = a.pi_user_id
             WHERE a.college_id = ? AND pr.status = "submitted"
             ORDER BY pr.submitted_at DESC LIMIT 20',
            [$collegeId]
        );

        // Overdue Reports
        $overdueReports = Database::all(
            'SELECT pr.id, pr.report_number, p.project_number, p.title, u.full_name as pi_name,
                   pr.period_end, pr.status,
                   julianday("now") - julianday(pr.period_end) as days_overdue
             FROM progress_reports pr
             JOIN research_projects p ON p.id = pr.project_id
             JOIN research_applications a ON a.id = p.application_id
             JOIN users u ON u.id = a.pi_user_id
             WHERE a.college_id = ? AND pr.status = "submitted" AND pr.period_end < date("now")
             ORDER BY pr.period_end ASC LIMIT 20',
            [$collegeId]
        );

        // Evaluators in College
        $collegeEvaluators = Database::all(
            'SELECT u.id, u.full_name, d.name as department_name,
                   (SELECT COUNT(*) FROM evaluator_assignments ea WHERE ea.evaluator_user_id = u.id AND ea.status IN ("assigned","accepted")) as active_assignments,
                   re.availability_status
             FROM users u
             LEFT JOIN departments d ON d.id = u.department_id
             LEFT JOIN research_evaluators re ON re.user_id = u.id
             WHERE u.college_id = ? AND re.id IS NOT NULL
             ORDER BY u.full_name ASC',
            [$collegeId]
        );

        // Priority Action Items
        $priorityActions = [
            'screeningNeeded' => Database::all(
                'SELECT a.id, a.application_number, a.title, a.status_code, a.submitted_at, u.full_name as pi_name
                 FROM research_applications a
                 JOIN users u ON u.id = a.pi_user_id
                 WHERE a.college_id = ? AND a.status_code IN ("SUBMITTED","COLLEGE_RECEIVED","UNDER_COLLEGE_SCREENING","SUBMITTED_TO_RPEE")
                 ORDER BY a.submitted_at ASC LIMIT 10',
                [$collegeId]
            ),
            'needsEvaluators' => Database::all(
                'SELECT a.id, a.application_number, a.title, a.status_code,
                       (SELECT COUNT(*) FROM evaluator_assignments ea WHERE ea.application_id = a.id AND ea.status IN ("assigned","accepted") AND ea.is_active = 1) as assigned_count
                 FROM research_applications a
                 WHERE a.college_id = ? AND a.status_code = "EVALUATOR_ASSIGNMENT_PENDING"
                 ORDER BY a.submitted_at ASC LIMIT 10',
                [$collegeId]
            ),
            'overdueEvaluations' => Database::all(
                'SELECT ea.id, a.application_number, a.title, ea.deadline, u.full_name as evaluator_name, ea.status
                 FROM evaluator_assignments ea
                 JOIN research_applications a ON a.id = ea.application_id
                 JOIN users u ON u.id = ea.evaluator_user_id
                 WHERE a.college_id = ? AND ea.status IN ("assigned","accepted")
                 AND ea.deadline IS NOT NULL AND ea.deadline < date("now")
                 ORDER BY ea.deadline ASC LIMIT 10',
                [$collegeId]
            ),
            'approachingDeadlines' => Database::all(
                'SELECT ea.id, a.application_number, a.title, ea.deadline, u.full_name as evaluator_name, ea.status
                 FROM evaluator_assignments ea
                 JOIN research_applications a ON a.id = ea.application_id
                 JOIN users u ON u.id = ea.evaluator_user_id
                 WHERE a.college_id = ? AND ea.status IN ("assigned","accepted")
                 AND ea.deadline IS NOT NULL AND ea.deadline >= date("now") AND ea.deadline <= date("now", "+3 days")
                 ORDER BY ea.deadline ASC LIMIT 10',
                [$collegeId]
            ),
            'readyForDecision' => Database::all(
                'SELECT a.id, a.application_number, a.title, a.status_code, u.full_name as pi_name,
                       (SELECT COUNT(*) FROM evaluations e WHERE e.application_id = a.id AND e.status = "submitted") as completed_count,
                       (SELECT COUNT(*) FROM evaluator_assignments ea WHERE ea.application_id = a.id AND ea.status IN ("assigned","accepted") AND ea.is_active = 1) as pending_count,
                       (SELECT ROUND(AVG(e.weighted_score), 2) FROM evaluations e WHERE e.application_id = a.id AND e.status = "submitted") as avg_score
                 FROM research_applications a
                 JOIN users u ON u.id = a.pi_user_id
                 WHERE a.college_id = ?
                 AND a.status_code IN ("UNDER_COLLEGE_EVALUATION","UNDER_EVALUATION","AWAITING_COLLEGE_DECISION")
                 AND (SELECT COUNT(*) FROM evaluations e WHERE e.application_id = a.id AND e.status = "submitted") >= 2
                 ORDER BY a.submitted_at ASC LIMIT 15',
                [$collegeId]
            ),
        ];

        extract([
            'stats' => $stats,
            'newSubmissions' => $newSubmissions,
            'underEvaluation' => $underEvaluation,
            'awaitingDecision' => $awaitingDecision,
            'activeProjects' => $activeProjects,
            'pendingReports' => $pendingReports,
            'overdueReports' => $overdueReports,
            'collegeEvaluators' => $collegeEvaluators,
            'priorityActions' => $priorityActions,
        ]);
        include APP_PATH . '/views/coordinator/dashboard.php';
    }

    public static function proposals(): void
    {
        Gate::authorize('research.view');
        if (!Gate::hasRole('college_coordinator')) {
            http_response_code(403);
            exit('Access Denied');
        }
        include APP_PATH . '/views/coordinator/proposals.php';
    }
}
