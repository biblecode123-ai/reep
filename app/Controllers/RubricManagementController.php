<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\EvaluationRubricService;
use App\Core\Request;
use App\Core\View;

/** Controller for Rubric Management by Research Director. */
final class RubricManagementController extends Controller
{
    public function index(): void
    {
        // List rubrics and versions
        $rubrics = \App\Core\Database::all('SELECT * FROM evaluation_rubrics');
        View::render('director/rubrics/index', ['rubrics' => $rubrics]);
    }

    public function create(): void
    {
        // Show rubric builder
        View::render('director/rubrics/builder');
    }

    public function storeVersion(int $rubricId): void
    {
        // 1. Validate total points from input (example: POST['points'])
        // 2. Use EvaluationRubricService to store and version
        // 3. Redirect back with success/error
        
        $totalPoints = (float) ($_POST['total_points'] ?? 0);
        
        if ($totalPoints !== 100.0) {
            // Return error response
            return;
        }

        // Proceed to save logic...
    }
}
