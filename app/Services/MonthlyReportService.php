<?php
declare(strict_types=1);

namespace AppServices;

use AppCoreAudit;
use AppCoreDatabase;

/**
 * Canonical monthly-report scheduler.
 *
 * A reporting period is represented by progress_reports.reporting_period (YYYY-MM).
 * No separate monthly_reports table is used.
 */
final class MonthlyReportService
{
    public static function currentPeriod(?\DateTimeImmutable $now = null): string
    {
        $now ??= new \DateTimeImmutable('now');
        return $now->format('Y-m');
    }

    /**
     * Return active projects that have no submitted/approved report for the period.
     */
    public static function dueProjects(?string $period = null): array
    {
        $period ??= self::currentPeriod();

        $periodStart = \DateTimeImmutable::createFromFormat('!Y-m-d', $period . '-01');
        if ($periodStart === false) {
            throw new \InvalidArgumentException('Invalid reporting period: ' . $period);
        }
        $periodEnd = $periodStart->modify('last day of this month')->format('Y-m-d');

        return Database::all(
            'SELECT p.id, p.project_number, p.title, p.pi_user_id, p.college_id
             FROM research_projects p
             WHERE p.status_code IN ("ACTIVE","PROGRESS_MONITORING")
               AND COALESCE(p.actual_start, p.planned_start, DATE(p.created_at)) <= ?
               AND NOT EXISTS (
                 SELECT 1 FROM progress_reports pr
                 WHERE pr.project_id = p.id
                   AND pr.reporting_period = ?
                   AND pr.status IN ("submitted","approved","revision_required")
               )
             ORDER BY p.id',
            [$periodEnd, $period]
        );
    }

    /**
     * Generate a compliance snapshot for the current period.
     *
     * The canonical report table remains progress_reports; this method does not
     * create placeholder rows that could be mistaken for submitted reports.
     */
    public static function compliance(?string $period = null): array
    {
        $period ??= self::currentPeriod();
        $active = (int) Database::scalar(
            'SELECT COUNT(*) FROM research_projects
             WHERE status_code IN ("ACTIVE","PROGRESS_MONITORING")'
        );
        $submitted = (int) Database::scalar(
            'SELECT COUNT(*) FROM progress_reports
             WHERE reporting_period = ? AND status IN ("submitted","approved","revision_required")',
            [$period]
        );
        return [
            'period' => $period,
            'active_projects' => $active,
            'submitted_reports' => $submitted,
            'due_reports' => max(0, $active - $submitted),
        ];
    }

    public static function overdueProjects(?string $period = null): array
    {
        $period ??= self::currentPeriod();
        return self::dueProjects($period);
    }



    /**
     * Notify PIs on the configured reminder and due dates.
     * The method is intentionally deterministic so a daily cron does not
     * send duplicate reminders on every run.
     */
    public static function notifySchedule(?\DateTimeImmutable $now = null): int
    {
        $timezoneName = Settings::get('timezone', 'Africa/Addis_Ababa') ?: 'Africa/Addis_Ababa';
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone($timezoneName));
        $dueDay = max(1, min(28, Settings::int('monthly_report_due_day', 5)));
        $reminderDays = max(0, min(14, Settings::int('monthly_report_reminder_days', 3)));

        $day = (int) $now->format('j');
        $lastDay = (int) $now->format('t');
        $reminderDay = max(1, $dueDay - $reminderDays);
        if ($day !== $reminderDay && $day !== $dueDay) {
            return 0;
        }

        $period = $now->format('Y-m');
        $due = self::dueProjects($period);
        $sent = 0;
        foreach ($due as $project) {
            $kind = $day === $dueDay ? 'due' : 'reminder';
            $message = $kind === 'due'
                ? 'Your monthly research progress report for ' . $period . ' is due today.'
                : 'Your monthly research progress report for ' . $period . ' is due on day ' . $dueDay . '.';
            NotificationService::send(
                (int) $project['pi_user_id'],
                'monthly_report_' . $kind,
                'Monthly research report ' . ($kind === 'due' ? 'due today' : 'reminder'),
                $project['project_number'] . ' — ' . $message,
                'index.php?action=project_view&id=' . (int) $project['id'] . '#reports',
                $kind === 'due' ? 'high' : 'normal'
            );
            $sent++;
        }
        return $sent;
    }

    /**
     * Emit one audit event for scheduler execution; safe to run repeatedly.
     */
    public static function recordSchedulerRun(?string $period = null): void
    {
        $period ??= self::currentPeriod();
        Audit::log('monthly_report.scheduler_run', 'reporting_period', 0, null, [
            'period' => $period,
            'due_count' => count(self::dueProjects($period)),
        ]);
    }
}

