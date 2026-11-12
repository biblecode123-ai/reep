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
use App\Models\FinalReportModel;
use App\Models\ProjectModel;
use App\Models\RubricModel;
use App\Services\DocumentService;
use App\Services\NotificationService;
use App\Services\RubricScoringService;

final class FinalReportController
{
    private static function directorOnly(): void
    {
        if (!Gate::hasRole('rpee_director')) {
            http_response_code(403);
            include APP_PATH . '/views/errors/403.php';
            exit;
        }
        Gate::authorize('research.manage');
    }

    private static function coordinatorFor(array $project): void
    {
        if (!Gate::hasRole('college_coordinator') || !Gate::allows('research.screen')) {
            http_response_code(403);
            include APP_PATH . '/views/errors/403.php';
            exit;
        }
        $user = Auth::user();
        if ($user === null || (int) ($user['college_id'] ?? 0) !== (int) $project['college_id']) {
            http_response_code(403);
            include APP_PATH . '/views/errors/403.php';
            exit;
        }
    }

    private static function evaluatorHasRole(int $userId): bool
    {
        return (int) Database::scalar(
            'SELECT COUNT(*) FROM user_roles ur
             JOIN roles r ON r.id = ur.role_id
             JOIN users u ON u.id = ur.user_id
             WHERE ur.user_id = ? AND r.name = "evaluator" AND u.status = "active"',
            [$userId]
        ) > 0;
    }

    private static function isProjectTeamMember(int $projectId, int $userId): bool
    {
        return (int) Database::scalar(
            'SELECT COUNT(*) FROM research_project_members
             WHERE project_id = ? AND user_id = ? AND status = "active"',
            [$projectId, $userId]
        ) > 0;
    }

    private static function assertAssignmentStage(array $report, array $assignment): void
    {
        $expected = $assignment['assignment_level'] === 'college' ? 'college_evaluation' : 'university_evaluation';
        if (($report['status'] ?? '') !== $expected) {
            Session::flash('error', 'This final-report evaluation stage is not currently open.');
            redirect('index.php?action=my_evaluations');
        }
        if (($assignment['status'] ?? '') !== 'accepted' || (int) $assignment['is_active'] !== 1) {
            Session::flash('error', 'Accept the active assignment before evaluating this report.');
            redirect('index.php?action=my_evaluations');
        }
    }

    public static function submit(): void
    {
        Gate::authorize('project.report');
        Csrf::verifyRequest();
        $projectId = (int) ($_POST['project_id'] ?? 0);
        $project = ProjectModel::find($projectId);
        if ($project === null || (int) $project['pi_user_id'] !== Auth::id()) {
            Session::flash('error', 'Only the Principal Investigator may submit the completed research report.');
            redirect('index.php?action=projects');
        }
        if (!in_array((string) $project['status_code'], ['ACTIVE', 'PROGRESS_MONITORING'], true)) {
            Session::flash('error', 'A completed report can only be submitted for an active project that has not been closed.');
            redirect('index.php?action=project_view&id=' . $projectId);
        }

        $latest = FinalReportModel::latestForProject($projectId);
        if ($latest !== null && !in_array($latest['status'], ['college_revision_required','university_revision_required'], true)) {
            Session::flash('error', 'A final report is already in evaluation or has reached a final decision.');
            redirect('index.php?action=project_view&id=' . $projectId);
        }

        $typeId = Database::scalar('SELECT id FROM document_types WHERE type_code = "final_report" AND status = "active"');
        if ($typeId === null) {
            Session::flash('error', 'The Final Report document type is not configured.');
            redirect('index.php?action=project_view&id=' . $projectId);
        }
        [$ok, $error, $documentId] = DocumentService::upload(
            $_FILES['final_report'] ?? [],
            (int) $typeId,
            'research_project',
            $projectId,
            'Completed Research Report',
            'entity_team',
            'Final completed research report submission'
        );
        if (!$ok || $documentId === null) {
            Session::flash('error', $error ?? 'Final report upload failed.');
            redirect('index.php?action=project_view&id=' . $projectId);
        }

        $version = (int) ($latest['version'] ?? 0) + 1;
        $status = $latest !== null && $latest['status'] === 'university_revision_required'
            ? 'university_revision_required'
            : 'submitted_to_college';

        $reportId = Database::transaction(function () use ($projectId, $documentId, $version, $status): int {
            return FinalReportModel::create([
                'project_id' => $projectId,
                'document_id' => $documentId,
                'version' => $version,
                'status' => $status,
                'submitted_by' => Auth::id(),
                'submitted_at' => date('Y-m-d H:i:s'),
            ]);
        });

        Audit::log('final_report.submitted', 'research_project', $projectId, null, [
            'final_report_id' => $reportId,
            'version' => $version,
            'status' => $status,
        ]);
        NotificationService::sendToCollegeRole((int) $project['college_id'], 'college_coordinator',
            'final_report_submitted', 'Completed research report submitted',
            $project['project_number'] . ' — version ' . $version,
            'index.php?action=final_report_view&id=' . $reportId);
        Session::flash('success', 'Completed research report submitted for evaluation.');
        redirect('index.php?action=final_report_view&id=' . $reportId);
    }

    public static function assignCollegeEvaluators(): void
    {
        Gate::authorize('research.assign');
        Csrf::verifyRequest();
        $reportId = (int) ($_POST['final_report_id'] ?? 0);
        $report = FinalReportModel::find($reportId);
        if ($report === null) {
            Session::flash('error', 'Final report not found.');
            redirect('index.php?action=projects');
        }
        self::coordinatorFor($report);

        if (!in_array($report['status'], ['submitted_to_college','college_evaluation'], true)) {
            Session::flash('error', 'College evaluators can only be assigned before or during college report evaluation.');
            redirect('index.php?action=final_report_view&id=' . $reportId);
        }

        $required = max(1, Settings::int('college_evaluators_default', 1));
        $counts = FinalReportModel::assignmentCounts($reportId, 'college');
        $needed = max(0, $required - $counts['required']);
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['evaluator_ids'] ?? [])))));
        if ($needed < 1 || count($ids) !== $needed) {
            Session::flash('error', 'Select exactly ' . $needed . ' additional eligible college evaluator(s).');
            redirect('index.php?action=final_report_view&id=' . $reportId);
        }

        foreach ($ids as $userId) {
            $evaluator = Database::one('SELECT id, college_id, status FROM users WHERE id = ?', [$userId]);
            if ($evaluator === null || $evaluator['status'] !== 'active' || (int) $evaluator['college_id'] !== (int) $report['college_id']
                || !self::evaluatorHasRole($userId) || $userId === (int) $report['pi_user_id']
                || self::isProjectTeamMember((int) $report['project_id'], $userId)) {
                Session::flash('error', 'One or more selected evaluators are inactive, outside the college, not evaluators, or have a project-team conflict.');
                redirect('index.php?action=final_report_view&id=' . $reportId);
            }
            if (FinalReportModel::assignmentForUser($reportId, $userId, 'college') !== null) {
                Session::flash('error', 'An evaluator already has an assignment record for this report.');
                redirect('index.php?action=final_report_view&id=' . $reportId);
            }
        }

        Database::transaction(function () use ($reportId, $ids): void {
            foreach ($ids as $userId) {
                FinalReportModel::assign($reportId, $userId, 'college', Auth::id(), null);
            }
            Database::update('project_final_reports', ['status' => 'college_evaluation'], 'id = ?', [$reportId]);
        });

        Audit::log('final_report.college_evaluators_assigned', 'project_final_report', $reportId, null, ['evaluator_ids' => $ids]);
        Session::flash('success', 'College report evaluators assigned.');
        redirect('index.php?action=final_report_view&id=' . $reportId);
    }

    public static function assignUniversityEvaluators(): void
    {
        self::directorOnly();
        Csrf::verifyRequest();
        $reportId = (int) ($_POST['final_report_id'] ?? 0);
        $report = FinalReportModel::find($reportId);
        if ($report === null || !in_array($report['status'], ['college_passed','university_revision_required'], true)) {
            Session::flash('error', 'The report must pass college evaluation before university evaluators can be assigned.');
            redirect('index.php?action=projects');
        }

        $required = max(1, Settings::int('university_evaluators_default', 1));
        $counts = FinalReportModel::assignmentCounts($reportId, 'university');
        $needed = max(0, $required - $counts['required']);
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['evaluator_ids'] ?? [])))));
        if ($needed < 1 || count($ids) !== $needed) {
            Session::flash('error', 'Select exactly ' . $needed . ' additional eligible university evaluator(s).');
            redirect('index.php?action=final_report_view&id=' . $reportId);
        }

        foreach ($ids as $userId) {
            $evaluator = Database::one('SELECT id, status FROM users WHERE id = ?', [$userId]);
            if ($evaluator === null || $evaluator['status'] !== 'active' || !self::evaluatorHasRole($userId)
                || $userId === (int) $report['pi_user_id'] || self::isProjectTeamMember((int) $report['project_id'], $userId)) {
                Session::flash('error', 'One or more selected evaluators are inactive, not evaluators, or have a project-team conflict.');
                redirect('index.php?action=final_report_view&id=' . $reportId);
            }
            if (FinalReportModel::assignmentForUser($reportId, $userId, 'university') !== null) {
                Session::flash('error', 'An evaluator already has an assignment record for this report.');
                redirect('index.php?action=final_report_view&id=' . $reportId);
            }
        }

        Database::transaction(function () use ($reportId, $ids): void {
            foreach ($ids as $userId) {
                FinalReportModel::assign($reportId, $userId, 'university', Auth::id(), null);
            }
            Database::update('project_final_reports', ['status' => 'university_evaluation'], 'id = ?', [$reportId]);
        });

        Audit::log('final_report.university_evaluators_assigned', 'project_final_report', $reportId, null, ['evaluator_ids' => $ids]);
        Session::flash('success', 'University report evaluators assigned.');
        redirect('index.php?action=final_report_view&id=' . $reportId);
    }

    public static function respond(): void
    {
        Gate::authorize('research.evaluate');
        Csrf::verifyRequest();
        $assignmentId = (int) ($_POST['assignment_id'] ?? 0);
        $assignment = Database::one('SELECT * FROM project_evaluator_assignments WHERE id = ?', [$assignmentId]);
        if ($assignment === null || (int) $assignment['evaluator_user_id'] !== Auth::id()
            || (int) $assignment['is_active'] !== 1 || $assignment['status'] !== 'assigned') {
            Session::flash('error', 'This assignment is not available for response.');
            redirect('index.php?action=my_evaluations');
        }

        $response = (string) ($_POST['response'] ?? '');
        $reason = trim((string) ($_POST['reason'] ?? '')) ?: null;
        if (!in_array($response, ['accepted','declined','conflict_declared'], true)
            || ($response !== 'accepted' && $reason === null)) {
            Session::flash('error', 'Choose accept, decline, or declare a conflict; provide a reason for decline or conflict.');
            redirect('index.php?action=my_evaluations');
        }

        FinalReportModel::updateAssignment($assignmentId, [
            'status' => $response,
            'response_reason' => $reason,
            'responded_at' => date('Y-m-d H:i:s'),
            'is_active' => $response === 'declined' ? 0 : 1,
        ]);
        Audit::log('final_report.assignment_response', 'project_evaluator_assignment', $assignmentId, null, ['response' => $response]);
        Session::flash('success', 'Assignment response recorded.');
        redirect('index.php?action=my_evaluations');
    }

    public static function evaluationForm(): void
    {
        Gate::authorize('research.evaluate');
        $reportId = (int) ($_GET['final_report_id'] ?? 0);
        $assignmentId = (int) ($_GET['assignment_id'] ?? 0);
        $report = FinalReportModel::find($reportId);
        $assignment = Database::one('SELECT * FROM project_evaluator_assignments WHERE id = ?', [$assignmentId]);
        if ($report === null || $assignment === null || (int) $assignment['final_report_id'] !== $reportId
            || (int) $assignment['evaluator_user_id'] !== Auth::id()) {
            Session::flash('error', 'Evaluation assignment not found.');
            redirect('index.php?action=my_evaluations');
        }
        self::assertAssignmentStage($report, $assignment);

        $rubricType = $assignment['assignment_level'] === 'college'
            ? 'college_completed_report'
            : 'university_completed_report';
        $evaluation = FinalReportModel::evaluationForAssignment($assignmentId);
        $rubric = $evaluation !== null
            ? RubricModel::findById((int) $evaluation['rubric_template_id'])
            : RubricModel::activeFor($rubricType);
        if ($rubric === null) {
            Session::flash('error', 'The Research Director must publish the completed-report rubric before evaluation can begin.');
            redirect('index.php?action=my_evaluations');
        }
        $draftScores = $evaluation !== null && $evaluation['status'] === 'draft'
            ? FinalReportModel::indicatorScores((int) $evaluation['id'])
            : [];
        include APP_PATH . '/views/research/project_final_report_evaluation_form.php';
    }

    private static function evaluationContext(): array
    {
        $reportId = (int) ($_POST['final_report_id'] ?? 0);
        $assignmentId = (int) ($_POST['assignment_id'] ?? 0);
        $report = FinalReportModel::find($reportId);
        $assignment = Database::one('SELECT * FROM project_evaluator_assignments WHERE id = ?', [$assignmentId]);
        if ($report === null || $assignment === null || (int) $assignment['final_report_id'] !== $reportId
            || (int) $assignment['evaluator_user_id'] !== Auth::id()) {
            throw new \RuntimeException('Evaluation assignment not found.');
        }
        self::assertAssignmentStage($report, $assignment);
        $rubricType = $assignment['assignment_level'] === 'college'
            ? 'college_completed_report'
            : 'university_completed_report';
        $evaluation = FinalReportModel::evaluationForAssignment($assignmentId);
        $rubric = $evaluation !== null
            ? RubricModel::findById((int) $evaluation['rubric_template_id'])
            : RubricModel::activeFor($rubricType);
        if ($rubric === null) {
            throw new \RuntimeException('No published completed-report rubric is available.');
        }
        return [$report, $assignment, $evaluation, $rubric];
    }

    public static function saveDraft(): void
    {
        Gate::authorize('research.evaluate');
        Csrf::verifyRequest();
        try {
            [$report, $assignment, $evaluation, $rubric] = self::evaluationContext();
            $validated = RubricScoringService::validate($rubric, $_POST, false);
            if ($validated['errors'] !== []) {
                throw new \RuntimeException(implode(' ', $validated['errors']));
            }

            $evaluationId = Database::transaction(function () use ($report, $assignment, $evaluation, $rubric, $validated): int {
                $id = $evaluation === null
                    ? FinalReportModel::createEvaluation([
                        'final_report_id' => (int) $report['id'],
                        'assignment_id' => (int) $assignment['id'],
                        'evaluator_user_id' => Auth::id(),
                        'rubric_template_id' => (int) $rubric['id'],
                        'rubric_snapshot' => RubricModel::snapshot($rubric),
                        'evaluation_round' => 1,
                        'status' => 'draft',
                    ])
                    : (int) $evaluation['id'];
                FinalReportModel::replaceIndicatorScores($id, $validated['scores']);
                FinalReportModel::updateEvaluation($id, [
                    'weighted_score' => $validated['total'],
                    'max_score' => 100,
                    'status' => 'draft',
                ]);
                return $id;
            });
            echo json_encode(['ok' => true, 'message' => 'Draft saved.', 'evaluation_id' => $evaluationId]);
        } catch (\Throwable $e) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    public static function submitEvaluation(): void
    {
        Gate::authorize('research.evaluate');
        Csrf::verifyRequest();
        try {
            [$report, $assignment, $evaluation, $rubric] = self::evaluationContext();
            if ($evaluation !== null && $evaluation['status'] === 'submitted') {
                throw new \RuntimeException('This evaluation has already been submitted.');
            }
            $validated = RubricScoringService::validate($rubric, $_POST, true);
            if ($validated['errors'] !== []) {
                throw new \RuntimeException(implode(' ', $validated['errors']));
            }

            $recommendation = (string) ($_POST['recommendation'] ?? '');
            $strengths = trim((string) ($_POST['strengths'] ?? ''));
            $weaknesses = trim((string) ($_POST['weaknesses'] ?? ''));
            $overall = trim((string) ($_POST['overall_comment'] ?? ''));
            if (!in_array($recommendation, ['accept','accept_with_revision','major_revision','reject'], true)
                || $strengths === '' || $weaknesses === '' || $overall === '') {
                throw new \RuntimeException('Select a recommendation and complete strengths, weaknesses, and overall evaluation.');
            }

            $level = $assignment['assignment_level'];
            Database::transaction(function () use ($report, $assignment, $evaluation, $rubric, $validated, $recommendation, $strengths, $weaknesses, $overall): void {
                $id = $evaluation === null
                    ? FinalReportModel::createEvaluation([
                        'final_report_id' => (int) $report['id'],
                        'assignment_id' => (int) $assignment['id'],
                        'evaluator_user_id' => Auth::id(),
                        'rubric_template_id' => (int) $rubric['id'],
                        'rubric_snapshot' => RubricModel::snapshot($rubric),
                        'evaluation_round' => 1,
                        'status' => 'draft',
                    ])
                    : (int) $evaluation['id'];
                FinalReportModel::replaceIndicatorScores($id, $validated['scores']);
                FinalReportModel::updateEvaluation($id, [
                    'weighted_score' => $validated['total'],
                    'max_score' => 100,
                    'recommendation' => $recommendation,
                    'strengths' => $strengths,
                    'weaknesses' => $weaknesses,
                    'overall_comment' => $overall,
                    'status' => 'submitted',
                    'submitted_at' => date('Y-m-d H:i:s'),
                ]);
                FinalReportModel::updateAssignment((int) $assignment['id'], ['status' => 'completed']);
            });

            $counts = FinalReportModel::assignmentCounts((int) $report['id'], $level);
            if ($counts['required'] > 0 && $counts['completed'] >= $counts['required']) {
                $average = FinalReportModel::average((int) $report['id'], $level);
                $thresholdKey = $level === 'college' ? 'college_report_passing_score' : 'university_report_passing_score';
                $threshold = max(0.0, min(100.0, (float) Settings::get($thresholdKey, '50')));
                if ($level === 'college') {
                    $status = $average !== null && $average >= $threshold ? 'college_passed' : 'college_revision_required';
                    Database::update('project_final_reports', [
                        'college_average_score' => $average,
                        'status' => $status,
                    ], 'id = ?', [(int) $report['id']]);
                    NotificationService::send((int) $report['pi_user_id'], 'final_report_college_result',
                        'College completed-report evaluation completed',
                        $status === 'college_passed' ? 'Your completed report passed college evaluation.' : 'Your completed report requires revision after college evaluation.',
                        'index.php?action=final_report_view&id=' . (int) $report['id'], 'high');
                } else {
                    $status = $average !== null && $average >= $threshold ? 'university_passed' : 'university_revision_required';
                    Database::update('project_final_reports', [
                        'university_average_score' => $average,
                        'status' => $status,
                    ], 'id = ?', [(int) $report['id']]);
                    NotificationService::send((int) $report['pi_user_id'], 'final_report_university_result',
                        'University completed-report evaluation completed',
                        $status === 'university_passed' ? 'Your completed report passed university evaluation and is awaiting the Director decision.' : 'Your completed report requires revision after university evaluation.',
                        'index.php?action=final_report_view&id=' . (int) $report['id'], 'high');
                }
            }

            Audit::log('final_report.evaluation_submitted', 'project_final_report', (int) $report['id'], null, [
                'assignment_id' => (int) $assignment['id'],
                'assignment_level' => $level,
                'score' => $validated['total'],
                'rubric_version' => (int) $rubric['version'],
            ]);
            Session::flash('success', 'Completed-report evaluation submitted and locked.');
            redirect('index.php?action=my_evaluations');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
            redirect('index.php?action=my_evaluations');
        }
    }

    public static function forwardToUniversity(): void
    {
        Gate::authorize('research.screen');
        Csrf::verifyRequest();
        $reportId = (int) ($_POST['final_report_id'] ?? 0);
        $report = FinalReportModel::find($reportId);
        if ($report === null) {
            Session::flash('error', 'Final report not found.');
            redirect('index.php?action=projects');
        }
        self::coordinatorFor($report);
        if ($report['status'] !== 'college_passed') {
            Session::flash('error', 'Only a report that passed college evaluation can be forwarded.');
            redirect('index.php?action=final_report_view&id=' . $reportId);
        }
        $comments = trim((string) ($_POST['comments'] ?? '')) ?: null;
        FinalReportModel::recordDecision($reportId, 'college', 'forward_to_university', $comments, Auth::id());
        NotificationService::sendToRoles(['rpee_director'], 'final_report_ready_for_university',
            'Completed report ready for university evaluation',
            $report['project_number'] . ' — ' . $report['document_title'],
            'index.php?action=final_report_view&id=' . $reportId, 'high');
        Audit::log('final_report.forwarded_to_university', 'project_final_report', $reportId);
        Session::flash('success', 'Final report forwarded to the Research Directorate.');
        redirect('index.php?action=final_report_view&id=' . $reportId);
    }

    public static function finalDecision(): void
    {
        self::directorOnly();
        Csrf::verifyRequest();
        $reportId = (int) ($_POST['final_report_id'] ?? 0);
        $report = FinalReportModel::find($reportId);
        if ($report === null || $report['status'] !== 'university_passed') {
            Session::flash('error', 'The report must complete university evaluation before the Director can decide.');
            redirect('index.php?action=projects');
        }

        $decision = (string) ($_POST['decision'] ?? '');
        $comments = trim((string) ($_POST['comments'] ?? '')) ?: null;
        if (!in_array($decision, ['approve','revision_required','reject'], true)) {
            Session::flash('error', 'Invalid final report decision.');
            redirect('index.php?action=final_report_view&id=' . $reportId);
        }
        $average = FinalReportModel::average($reportId, 'university');
        $threshold = max(0.0, min(100.0, (float) Settings::get('university_report_passing_score', '50')));
        if ($average === null || ($decision === 'approve' && $average < $threshold)) {
            Session::flash('error', 'The university completed-report average is below the Director-configured passing threshold.');
            redirect('index.php?action=final_report_view&id=' . $reportId);
        }

        Database::transaction(function () use ($reportId, $decision, $comments, $average, $report): void {
            FinalReportModel::recordDecision($reportId, 'directorate', $decision, $comments, Auth::id());
            Database::update('project_final_reports', [
                'status' => $decision === 'approve' ? 'approved' : ($decision === 'reject' ? 'rejected' : 'university_revision_required'),
                'director_decision' => $decision,
                'director_comments' => $comments,
                'director_user_id' => Auth::id(),
                'director_decided_at' => date('Y-m-d H:i:s'),
                'university_average_score' => $average,
            ], 'id = ?', [$reportId]);
            if ($decision === 'approve') {
                Database::update('research_projects', [
                    'status_code' => 'COMPLETED',
                    'actual_end' => date('Y-m-d'),
                ], 'id = ?', [(int) $report['project_id']]);
            }
        });

        Audit::log('final_report.director_decision', 'project_final_report', $reportId, null, [
            'decision' => $decision,
            'average_score' => $average,
        ]);
        NotificationService::send((int) $report['pi_user_id'], 'final_report_director_decision',
            'Research Director decision recorded',
            'Your completed research report decision is: ' . str_replace('_', ' ', $decision) . '.',
            'index.php?action=final_report_view&id=' . $reportId, 'high');
        Session::flash('success', 'Final report decision recorded.');
        redirect('index.php?action=final_report_view&id=' . $reportId);
    }

    private static function eligibleEvaluators(array $report, string $level): array
    {
        $sql = 'SELECT u.id, u.full_name, u.email, u.college_id
                FROM users u
                JOIN user_roles ur ON ur.user_id = u.id
                JOIN roles r ON r.id = ur.role_id AND r.name = "evaluator"
                WHERE u.status = "active" AND u.id <> ?
                  AND NOT EXISTS (
                    SELECT 1 FROM research_project_members pm
                    WHERE pm.project_id = ? AND pm.user_id = u.id AND pm.status = "active"
                  )
                  AND NOT EXISTS (
                    SELECT 1 FROM project_evaluator_assignments pea
                    WHERE pea.final_report_id = ? AND pea.evaluator_user_id = u.id AND pea.assignment_level = ?
                  )';
        $params = [(int) $report['pi_user_id'], (int) $report['project_id'], (int) $report['id'], $level];
        if ($level === 'college') {
            $sql .= ' AND u.college_id = ?';
            $params[] = (int) $report['college_id'];
        }
        $sql .= ' ORDER BY u.full_name';
        return Database::all($sql, $params);
    }

    public static function view(): void
    {
        Gate::authorize('research.view');
        $reportId = (int) ($_GET['id'] ?? 0);
        $report = FinalReportModel::find($reportId);
        if ($report === null) {
            http_response_code(404);
            include APP_PATH . '/views/errors/404.php';
            return;
        }
        $userId = Auth::id();
        $isPi = (int) $report['pi_user_id'] === $userId;
        $isTeam = self::isProjectTeamMember((int) $report['project_id'], $userId);
        $isCoordinator = Gate::hasRole('college_coordinator') && (int) (Auth::user()['college_id'] ?? 0) === (int) $report['college_id'];
        $isDirectorateOfficer = Gate::hasRole('rpee_director') || Gate::hasRole('rpee_officer');
        $isDirector = Gate::hasRole('rpee_director');
        $userAssignment = FinalReportModel::assignmentForUser($reportId, $userId);
        $isAssigned = $userAssignment !== null
            && !in_array($userAssignment['status'], ['conflict_declared','declined','cancelled'], true)
            && (int) $userAssignment['is_active'] === 1;
        if (!$isPi && !$isTeam && !$isCoordinator && !$isDirectorateOfficer && !$isAssigned) {
            http_response_code(403);
            include APP_PATH . '/views/errors/403.php';
            return;
        }
        $assignments = FinalReportModel::assignments($reportId);
        $documentUrl = base_url('download.php?id=' . (int) $report['document_id']);
        $eligibleCollegeEvaluators = $isCoordinator
            ? self::eligibleEvaluators($report, 'college')
            : [];
        $eligibleUniversityEvaluators = $isDirector
            ? self::eligibleEvaluators($report, 'university')
            : [];
        $myAssignments = array_values(array_filter($assignments, static fn(array $a): bool => (int) $a['evaluator_user_id'] === $userId));
        $canSubmitRevision = $isPi && in_array($report['status'], ['college_revision_required','university_revision_required'], true);
        $canAssignCollege = $isCoordinator && in_array($report['status'], ['submitted_to_college','college_evaluation'], true);
        $canForward = $isCoordinator && $report['status'] === 'college_passed';
        $canAssignUniversity = $isDirector && in_array($report['status'], ['college_passed','university_revision_required'], true);
        $canDecide = $isDirector && $report['status'] === 'university_passed';
        include APP_PATH . '/views/research/project_final_report_view.php';
    }
}
