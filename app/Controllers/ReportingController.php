<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Gate;
use App\Core\Database;

/**
 * Institutional reporting (§75).
 */
final class ReportingController
{
    public static function index(): void
    {
        Gate::authorize('report.view');
        include APP_PATH . '/views/reporting/index.php';
    }

    public static function export(): void
    {
        Gate::authorize('report.export');
        $type = $_GET['type'] ?? 'proposals';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="'.$type.'_export.csv"');
        
        $output = fopen('php://output', 'w');
        
        if ($type === 'proposals') {
            $data = Database::all('SELECT * FROM research_applications');
            if (!empty($data)) fputcsv($output, array_keys($data[0]));
            foreach ($data as $row) fputcsv($output, $row);
        } elseif ($type === 'projects') {
            $data = Database::all('SELECT * FROM research_projects');
            if (!empty($data)) fputcsv($output, array_keys($data[0]));
            foreach ($data as $row) fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
    }
}

