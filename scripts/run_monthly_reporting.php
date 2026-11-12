<?php
declare(strict_types=1);

/**
 * Daily monthly-report scheduler.
 *
 * Configure cron to run this once per day:
 *   php scripts/run_monthly_reporting.php
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\MonthlyReportService;

$sent = MonthlyReportService::notifySchedule();
MonthlyReportService::recordSchedulerRun();

echo 'Monthly reporting scheduler completed. Notifications sent: ' . $sent . PHP_EOL;
