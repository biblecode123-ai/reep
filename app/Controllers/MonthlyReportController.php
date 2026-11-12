<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Gate;
use App\Core\Session;

final class MonthlyReportController
{
    public static function submit(): void
    {
        Gate::authorize('research.submit');
        Csrf::verifyRequest();

        $projectId = (int) ($_POST['project_id'] ?? 0);
        $reportingMonth = (int) ($_POST['month'] ?? date('m'));
        $reportingYear = (int) ($_POST['year'] ?? date('Y'));

        // Validate active project and deadline (§13)
        // ... implementation ...

        Database::insert('monthly_reports', [
            'project_id'      => $projectId,
            'reporting_month' => $reportingMonth,
            'reporting_year'  => $reportingYear,
            'status'          => 'submitted',
            'submitted_at'    => date('Y-m-d H:i:s'),
            'content_data'    => json_encode($_POST['report_data'])
        ]);

        Session::flash('success', 'Monthly report submitted.');
        redirect('index.php?action=project_view&id=' . $projectId);
    }
}

