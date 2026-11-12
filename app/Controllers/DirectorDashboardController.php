<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Gate;
use App\Services\MonthlyReportingService;

final class DirectorDashboardController
{
    public static function index(): void
    {
        Gate::authorize('research.manage');

        $scalar = fn(string $sql, array $p=[]) => (int) Database::scalar($sql, $p);

        // 1. Dashboard Summary Cards
        $stats = [
            'activeCalls' => $scalar('SELECT COUNT(*) FROM research_calls WHERE status IN ("open","active")'),
            'awaitingCommitteeAssignment' => $scalar('SELECT COUNT(*) FROM research_applications WHERE status_code IN ("SUBMITTED_TO_RPEE","AWAITING_UNIVERSITY_EVALUATION")'),
            'univEvaluationsInProgress' => $scalar('SELECT COUNT(*) FROM research_applications WHERE status_code = "UNDER_UNIVERSITY_EVALUATION"'),
            'completedAwaitingDecision' => $scalar('SELECT COUNT(*) FROM research_applications WHERE status_code IN ("AWAITING_UNIVERSITY_DECISION","AWAITING_COMMITTEE_DECISION")'),
            'fundingDecisionsPending' => $scalar('SELECT COUNT(*) FROM research_applications WHERE status_code IN ("AWAITING_FUNDING_DECISION","FUNDING_PENDING")'),
            'openBudgetAppeals' => $scalar('SELECT COUNT(*) FROM appeals WHERE appealable_type = "budget" AND status IN ("submitted","under_review")'),
            'openRejectedAppeals' => $scalar('SELECT COUNT(*) FROM appeals WHERE appealable_type = "rejection" AND status IN ("submitted","under_review")'),
            'appealsAwaitingCommittee' => $scalar('SELECT COUNT(*) FROM appeals WHERE status = "submitted"'),
            'agreementsPending' => $scalar('SELECT COUNT(*) FROM research_agreements WHERE status IN ("draft","issued","awaiting_signature")'),
            'activeProjectsAttention' => $scalar('SELECT COUNT(*) FROM research_projects WHERE status_code IN ("ACTIVE", "PROGRESS_MONITORING")'),
            'reportingStats' => MonthlyReportingService::getStats(),
            'overdueReports' => $scalar('SELECT COUNT(*) FROM progress_reports WHERE status != "approved" AND period_end < date("now")'),
        ];

        // 2. Priority Action Center - Proposals awaiting committee assignment
        $pendingCommitteeAssignment = Database::all(
            'SELECT a.id, a.application_number, a.title, a.status_code, u.full_name as pi_name, c.name as college_name, a.submitted_at
             FROM research_applications a
             JOIN users u ON u.id = a.pi_user_id
             LEFT JOIN colleges c ON c.id = a.college_id
             WHERE a.status_code = "UNIV_EVAL_ASSIGNMENT_PENDING"
             ORDER BY a.submitted_at DESC LIMIT 20'
        );

        // Committees missing required members
        $committeesNeedingMembers = Database::all(
            'SELECT c.id, c.name, c.code, c.committee_type, COUNT(cm.id) as member_count, c.quorum_size
             FROM committees c
             LEFT JOIN committee_members cm ON cm.committee_id = c.id AND cm.status = "active"
             WHERE c.status = "active"
             GROUP BY c.id
             HAVING member_count < COALESCE(c.quorum_size, 3)'
        );

        // Evaluations approaching deadlines or (if deadline exists and is overdue)
        $overdueEvaluations = Database::all(
            'SELECT ea.id, a.application_number, a.title, ea.deadline, u.full_name as evaluator_name, ea.status
             FROM evaluator_assignments ea
             JOIN research_applications a ON a.id = ea.application_id
             JOIN users u ON u.id = ea.evaluator_user_id
             WHERE ea.status IN ("assigned","accepted")
             AND ea.deadline IS NOT NULL
             AND ea.deadline < DATE("now")
             ORDER BY ea.deadline ASC LIMIT 20'
        );

        // Completed evaluations awaiting official decisions
        $completedAwaitingDecision = Database::all(
            'SELECT a.id, a.application_number, a.title, a.status_code, a.evaluation_completed_at, u.full_name as pi_name
             FROM research_applications a
             JOIN users u ON u.id = a.pi_user_id
             WHERE a.status_code IN ("AWAITING_UNIVERSITY_DECISION","AWAITING_COMMITTEE_DECISION")
             ORDER BY a.evaluation_completed_at DESC LIMIT 20'
        );

        // Funding decisions awaiting authorization
        $fundingDecisionsPending = Database::all(
            'SELECT a.id, a.application_number, a.title, a.budget_requested, a.budget_approved_amount, u.full_name as pi_name
             FROM research_applications a
             JOIN users u ON u.id = a.pi_user_id
             WHERE a.status_code IN ("AWAITING_FUNDING_DECISION","FUNDING_PENDING")
             ORDER BY a.budget_approved_at DESC LIMIT 20'
        );

        // Appeals awaiting review or committee appointment
        $appealsAwaitingReview = Database::all(
            'SELECT ap.id, ap.appealable_type, ap.status, ap.submitted_at, a.application_number, a.title, u.full_name as pi_name
             FROM appeals ap
             JOIN research_applications a ON a.id = ap.appealable_id
             JOIN users u ON u.id = ap.appellant_user_id
             WHERE ap.status IN ("submitted","under_review")
             ORDER BY ap.submitted_at DESC LIMIT 20'
        );

        // Agreements awaiting signature
        $agreementsAwaitingSignature = Database::all(
            'SELECT ag.id, ag.version, ag.status, a.application_number, a.title, u.full_name as pi_name
             FROM research_agreements ag
             JOIN research_applications a ON a.id = ag.application_id
             JOIN users u ON u.id = a.pi_user_id
             WHERE ag.status IN ("draft","issued")
             ORDER BY ag.created_at DESC LIMIT 20'
        );

        // Active projects with overdue reports
        $projectsWithOverdueReports = Database::all(
            'SELECT p.id, p.project_number, p.title, p.status_code, pr.period_end as due_date, pr.status as report_status
             FROM research_projects p
             JOIN progress_reports pr ON pr.project_id = p.id
             WHERE p.status_code = "ACTIVE"
             AND pr.status = "submitted"
             ORDER BY pr.period_end ASC LIMIT 20'
        );

        // 3. University Evaluation Management
        $evaluationSummary = [
            'proposalsReceived' => $scalar('SELECT COUNT(*) FROM research_applications WHERE status_code IN ("SUBMITTED_TO_RPEE","AWAITING_UNIVERSITY_EVALUATION","UNDER_UNIVERSITY_EVALUATION","AWAITING_UNIVERSITY_DECISION")'),
            'activeCommittees' => $scalar('SELECT COUNT(*) FROM committees WHERE status = "active" AND committee_type = "university_research"'),
            'assignedEvaluators' => $scalar('SELECT COUNT(*) FROM evaluator_assignments WHERE status IN ("assigned","accepted")'),
            'completedEvaluations' => $scalar('SELECT COUNT(*) FROM evaluations WHERE status = "submitted"'),
            'pendingAssessments' => $scalar('SELECT COUNT(*) FROM evaluator_assignments WHERE status IN ("assigned","accepted")'),
        ];

        $recentCommitteeAssignments = Database::all(
            'SELECT aca.id, aca.assigned_at, aca.meeting_date, c.name as committee_name, a.application_number, a.title
             FROM application_committee_assignments aca
             JOIN committees c ON c.id = aca.committee_id
             JOIN research_applications a ON a.id = aca.application_id
             WHERE aca.status = "active"
             ORDER BY aca.assigned_at DESC LIMIT 10'
        );

        // 4. Funding Overview
        $fundingOverview = [
            'requestedBudget' => $scalar('SELECT COALESCE(SUM(budget_requested), 0) FROM research_applications WHERE status_code NOT IN ("DRAFT","WITHDRAWN")'),
            'committeeRecommended' => $scalar('SELECT COALESCE(SUM(recommended_amount), 0) FROM committee_decisions WHERE recommended_amount IS NOT NULL'),
            'officiallyApproved' => $scalar('SELECT COALESCE(SUM(budget_approved_amount), 0) FROM research_applications WHERE budget_approved_amount IS NOT NULL'),
            'allocatedFunds' => $scalar('SELECT COALESCE(SUM(approved_amount), 0) FROM research_budgets WHERE status = "approved"'),
            'disbursedFunds' => $scalar('SELECT COALESCE(SUM(amount), 0) FROM funding_releases'),
            'reportedExpenditure' => $scalar('SELECT COALESCE(SUM(amount_spent_to_date), 0) FROM progress_reports WHERE amount_spent_to_date IS NOT NULL'),
        ];

        // 5. Appeals Overview
        $appealsSummary = [
            'budgetAppeals' => $scalar('SELECT COUNT(*) FROM appeals WHERE appealable_type = "budget"'),
            'rejectedAppeals' => $scalar('SELECT COUNT(*) FROM appeals WHERE appealable_type = "rejection"'),
            'pendingReview' => $scalar('SELECT COUNT(*) FROM appeals WHERE status IN ("submitted","under_review")'),
            'underReview' => $scalar('SELECT COUNT(*) FROM appeals WHERE status = "under_review"'),
            'awaitingImplementation' => $scalar('SELECT COUNT(*) FROM appeals WHERE status = "upheld"'),
            'closedAppeals' => $scalar('SELECT COUNT(*) FROM appeals WHERE status IN ("rejected","withdrawn")'),
        ];

        // 6. Agreements and Project Monitoring
        $agreementsSummary = [
            'awaitingPreparation' => $scalar('SELECT COUNT(*) FROM research_applications WHERE status_code IN ("APPROVED","FUNDED") AND id NOT IN (SELECT application_id FROM research_agreements)'),
            'awaitingSignature' => $scalar('SELECT COUNT(*) FROM research_agreements WHERE status IN ("draft","issued")'),
            'awaitingActivation' => $scalar('SELECT COUNT(*) FROM research_projects WHERE status_code = "APPROVED"'),
            'activeProjects' => $scalar('SELECT COUNT(*) FROM research_projects WHERE status_code IN ("ACTIVE", "PROGRESS_MONITORING")'),
            'extensionRequests' => $scalar('SELECT COUNT(*) FROM research_extension_requests WHERE status = "pending"'),
            'amendmentRequests' => $scalar('SELECT COUNT(*) FROM budget_revisions WHERE status = "pending"'),
            'awaitingFinalReport' => $scalar('SELECT COUNT(*) FROM research_projects WHERE status_code = "APPROVED"'),
            'awaitingRepository' => $scalar('SELECT COUNT(*) FROM research_projects WHERE status_code = "APPROVED" AND id NOT IN (SELECT project_id FROM repository_records)'),
        ];

        // 7. Research Calls and Themes
        $callsSummary = [
            'activeCalls' => $scalar('SELECT COUNT(*) FROM research_calls WHERE status = "open"'),
            'upcomingCalls' => $scalar('SELECT COUNT(*) FROM research_calls WHERE status = "published" AND opens_at > DATE("now")'),
            'closedCalls' => $scalar('SELECT COUNT(*) FROM research_calls WHERE status = "closed"'),
            'archivedCalls' => $scalar('SELECT COUNT(*) FROM research_calls WHERE status = "archived"'),
        ];

        $activeCalls = Database::all(
            'SELECT id, title, reference_number, opens_at, closes_at, status
             FROM research_calls
             WHERE status IN ("open","published")
             ORDER BY closes_at ASC LIMIT 10'
        );

        // 8. Recent Activity and Alerts
        $recentActivity = Database::all(
            'SELECT al.id, al.action, al.entity_type, al.entity_id, al.created_at, u.full_name as actor_name
             FROM audit_logs al
             LEFT JOIN users u ON u.id = al.user_id
             WHERE al.action IN ("committee.assigned","evaluation.submitted","decision.recorded","budget.approved","appeal.submitted","agreement.issued","agreement.signed","project.activated","report.submitted")
             ORDER BY al.created_at DESC LIMIT 20'
        );

        $data = compact(
            'stats',
            'pendingCommitteeAssignment',
            'committeesNeedingMembers',
            'overdueEvaluations',
            'completedAwaitingDecision',
            'fundingDecisionsPending',
            'appealsAwaitingReview',
            'agreementsAwaitingSignature',
            'projectsWithOverdueReports',
            'evaluationSummary',
            'recentCommitteeAssignments',
            'fundingOverview',
            'appealsSummary',
            'agreementsSummary',
            'callsSummary',
            'activeCalls',
            'recentActivity'
        );

        extract($data);
        include APP_PATH . '/views/dashboards/director.php';
    }
}

