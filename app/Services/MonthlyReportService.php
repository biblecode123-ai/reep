<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class MonthlyReportService
{
    /**
     * Generate 'due' reports for all active projects where a report
     * for the current period has not yet been created.
     */
    public static function generateDueReports(): void
    {
        $year = (int) date('Y');
        $month = (int) date('m');
        
        $activeProjects = Database::all('SELECT id FROM research_projects WHERE status_code = "ACTIVE"');
        
        foreach ($activeProjects as $p) {
            $exists = Database::scalar(
                'SELECT id FROM monthly_reports WHERE project_id = ? AND reporting_month = ? AND reporting_year = ?',
                [(int) $p['id'], $month, $year]
            );
            
            if (!$exists) {
                Database::insert('monthly_reports', [
                    'project_id'      => (int) $p['id'],
                    'reporting_month' => $month,
                    'reporting_year'  => $year,
                    'status'          => 'due'
                ]);
            }
        }
    }
}
