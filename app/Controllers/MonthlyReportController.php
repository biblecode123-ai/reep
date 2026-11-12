<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Gate;
use App\Core\Session;
use App\Models\ProjectModel;
use App\Services\NotificationService;
use App\Services\NumberingService;

final class MonthlyReportController
{
    /**
     * Monthly reporting uses the canonical progress_reports table.
     * There is deliberately no parallel monthly_reports table or second
     * reporting repository to drift from the project dashboard.
     */
    public static function submit(): void
    {
        Gate::authorize('project.report');
        Csrf::verifyRequest();

        $projectId = (int) ($_POST['project_id'] ?? 0);
        $project = ProjectModel::find($projectId);
        if ($project === null) {
            Session::flash('error', 'Project not found.');
            redirect('index.php?action=projects');
        }

        $isOwner = (int) $project['pi_user_id'] === Auth::id();
        $isActiveMember = ProjectModel::isMember($projectId, Auth::id());
        if (!$isOwner && !$isActiveMember) {
            Session::flash('error', 'Only the PI or an active project team member may submit a monthly report.');
            redirect('index.php?action=project_view&id=' . $projectId);
        }

        if (!in_array((string) $project['status_code'], ['ACTIVE', 'PROGRESS_MONITORING'], true)) {
            Session::flash('error', 'Monthly reports are accepted only while the project is active and before closure.');
            redirect('index.php?action=project_view&id=' . $projectId);
        }

        $yearMonth = trim((string) ($_POST['year_month'] ?? ''));
        if (preg_match('/^(\\d{4})-(\\d{2})$/', $yearMonth, $m)) {
            $year = (int) $m[1];
            $month = (int) $m[2];
        } else {
            $month = (int) ($_POST['month'] ?? 0);
            $year = (int) ($_POST['year'] ?? 0);
        }
        if ($month < 1 || $month > 12 || $year < 2000 || $year > (int) date('Y') || !checkdate($month, 1, $year)) {
            Session::flash('error', 'Select a valid reporting month and year.');
            redirect('index.php?action=project_view&id=' . $projectId . '#reports');
        }

        $periodStart = sprintf('%04d-%02d-01', $year, $month);
        $periodEnd = date('Y-m-t', strtotime($periodStart));
        if ($periodStart > date('Y-m-d')) {
            Session::flash('error', 'A future reporting month cannot be submitted.');
            redirect('index.php?action=project_view&id=' . $projectId . '#reports');
        }
        if (!empty($project['actual_start']) && $periodEnd < $project['actual_start']) {
            Session::flash('error', 'The selected reporting month is before this project started.');
            redirect('index.php?action=project_view&id=' . $projectId . '#reports');
        }

        $period = sprintf('%04d-%02d', $year, $month);
        $duplicate = (int) Database::scalar(
            'SELECT COUNT(*) FROM progress_reports WHERE project_id = ? AND reporting_period = ?',
            [$projectId, $period]
        );
        if ($duplicate > 0) {
            Session::flash('error', 'A report for ' . $period . ' has already been submitted for this project.');
            redirect('index.php?action=project_view&id=' . $projectId . '#reports');
        }

        $data = is_array($_POST['report_data'] ?? null) ? $_POST['report_data'] : $_POST;
        $progress = is_numeric($data['progress_pct'] ?? null)
            ? (float) $data['progress_pct']
            : null;
        if ($progress === null || $progress < 0 || $progress > 100) {
            Session::flash('error', 'Project progress must be a number between 0 and 100.');
            redirect('index.php?action=project_view&id=' . $projectId . '#reports');
        }

        $completed = trim((string) ($data['completed_activities'] ?? ''));
        if ($completed === '') {
            Session::flash('error', 'Describe the activities completed during the reporting month.');
            redirect('index.php?action=project_view&id=' . $projectId . '#reports');
        }

        $financialProgress = ($data['financial_progress_pct'] ?? '') === ''
            ? null
            : (is_numeric($data['financial_progress_pct']) ? (float) $data['financial_progress_pct'] : -1);
        $amountSpent = ($data['amount_spent_to_date'] ?? $data['amount_spent'] ?? '') === ''
            ? null
            : (is_numeric($data['amount_spent_to_date'] ?? $data['amount_spent']) ? (float) ($data['amount_spent_to_date'] ?? $data['amount_spent']) : -1);
        if (($financialProgress !== null && ($financialProgress < 0 || $financialProgress > 100))
            || ($amountSpent !== null && $amountSpent < 0)) {
            Session::flash('error', 'Financial progress must be 0–100 and expenditure cannot be negative.');
            redirect('index.php?action=project_view&id=' . $projectId . '#reports');
        }

        $reportId = Database::transaction(function () use ($projectId, $period, $periodStart, $periodEnd, $progress, $completed, $data, $financialProgress, $amountSpent): int {
            return ProjectModel::createReport([
                'project_id'             => $projectId,
                'report_number'          => NumberingService::next('progress_report'),
                'reporting_period'       => $period,
                'period_start'           => $periodStart,
                'period_end'             => $periodEnd,
                'progress_pct'           => $progress,
                'completed_activities'   => $completed,
                'pending_activities'    => trim((string) ($data['pending_activities'] ?? '')) ?: null,
                'challenges'             => trim((string) ($data['challenges'] ?? '')) ?: null,
                'achievements'           => trim((string) ($data['achievements'] ?? '')) ?: null,
                'financial_progress_pct' => $financialProgress,
                'amount_spent_to_date'   => $amountSpent,
                'status'                 => 'submitted',
                'submitted_at'           => date('Y-m-d H:i:s'),
                'created_by'             => Auth::id(),
            ]);
        });

        Audit::log('monthly_report.submitted', 'research_project', $projectId, null, [
            'report_id' => $reportId,
            'reporting_period' => $period,
        ]);
        NotificationService::sendToCollegeRole((int) $project['college_id'], 'college_coordinator',
            'monthly_report_submitted', 'Monthly research report submitted',
            $project['project_number'] . ' — ' . $period,
            'index.php?action=project_view&id=' . $projectId . '#reports');
        Session::flash('success', 'Monthly report for ' . $period . ' submitted.');
        redirect('index.php?action=project_view&id=' . $projectId . '#reports');
    }
}

