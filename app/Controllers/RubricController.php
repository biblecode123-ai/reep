<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Session;
use App\Models\RubricModel;
use InvalidArgumentException;
use JsonException;

final class RubricController
{
    private static function authorizeDirector(): void
    {
        if (!Gate::hasRole('rpee_director')) {
            http_response_code(403);
            include APP_PATH . '/views/errors/403.php';
            exit;
        }
        Gate::authorize('research.manage');
    }

    public static function index(): void
    {
        self::authorizeDirector();
        $templates = RubricModel::allVersions();
        $rubricTypes = RubricModel::TYPES;
        include APP_PATH . '/views/research/rubrics.php';
    }

    /**
     * Publish a new immutable version. Editing an existing version in place is
     * deliberately unsupported; a valid new version supersedes the active one.
     */
    public static function save(): void
    {
        self::authorizeDirector();
        Csrf::verifyRequest();

        $type = trim((string) ($_POST['rubric_type'] ?? ''));
        $json = trim((string) ($_POST['criteria_json'] ?? ''));
        try {
            $criteria = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($criteria)) {
                throw new InvalidArgumentException('Rubric criteria must be a JSON array.');
            }
            $templateId = RubricModel::createVersion($type, $criteria, Auth::id());
        } catch (JsonException $e) {
            Session::flash('error', 'Invalid rubric JSON: ' . $e->getMessage());
            redirect('index.php?action=rubrics');
        } catch (InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
            redirect('index.php?action=rubrics');
        }

        Audit::log('rubric.version_published', 'rubric_template', $templateId, null, [
            'rubric_type' => $type,
        ]);
        Session::flash('success', 'A new immutable rubric version was published and is now active for this stage.');
        redirect('index.php?action=rubrics');
    }
}

