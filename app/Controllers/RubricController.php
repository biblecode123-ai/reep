<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Gate;
use App\Core\Session;
use App\Core\Validator;

/**
 * Manages versioned evaluation rubric templates (§9).
 */
final class RubricController
{
    public static function index(): void
    {
        Gate::authorize('research.manage');
        $templates = Database::all('SELECT * FROM rubric_templates WHERE is_active = 1 ORDER BY name');
        include APP_PATH . '/views/research/rubrics.php';
    }

    public static function save(): void
    {
        Gate::authorize('research.manage');
        Csrf::verifyRequest();

        // Transactional creation of template, criteria, and indicators
        Database::transaction(function () {
            $templateId = Database::insert('rubric_templates', [
                'name'    => $_POST['name'],
                'version' => (int) ($_POST['version'] ?? 1)
            ]);

            $criteria = (array) ($_POST['criteria'] ?? []);
            foreach ($criteria as $c) {
                $criterionId = Database::insert('rubric_criteria', [
                    'template_id' => $templateId,
                    'name'        => $c['name'],
                    'max_points'  => (float) $c['max_points']
                ]);

                foreach ((array) ($c['indicators'] ?? []) as $i) {
                    Database::insert('rubric_indicators', [
                        'criterion_id' => $criterionId,
                        'name'         => $i['name'],
                        'max_points'   => (float) $i['max_points']
                    ]);
                }
            }
        });

        Session::flash('success', 'Rubric template saved.');
        redirect('index.php?action=rubrics');
    }
}
