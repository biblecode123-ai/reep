<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Shared service for project monthly reporting obligations.
 */
final class MonthlyReportingService
{
    /**
     * Gets stats for progress reports.
     */
    public static function getStats(): array
    {
        return [
            'expected' => (int) Database::scalar('SELECT COUNT(*) FROM progress_reports WHERE status IN ("submitted", "revision_required") AND period_end < date("now")'),
            'submitted' => (int) Database::scalar('SELECT COUNT(*) FROM progress_reports WHERE status = "submitted"'),
            'reviewed' => (int) Database::scalar('SELECT COUNT(*) FROM progress_reports WHERE status = "approved"'),
            'overdue' => (int) Database::scalar('SELECT COUNT(*) FROM progress_reports WHERE status IN ("submitted", "revision_required") AND period_end < date("now")'),
        ];
    }
}
