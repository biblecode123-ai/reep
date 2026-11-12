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
use App\Models\UserModel;

/**
 * Completed research presentation and evaluation (spec §45–§49).
 */
final class PresentationController
{
    public static function index(): void
    {
        Gate::authorize('presentation.view');
        $presentations = Database::all('SELECT p.*, rp.title AS project_title, rp.project_number 
                                      FROM presentations p 
                                      JOIN research_projects rp ON rp.id = p.project_id 
                                      ORDER BY p.scheduled_at DESC');
        include APP_PATH . '/views/presentations/index.php';
    }

    public static function schedule(): void
    {
        Gate::authorize('presentation.manage');
        $projectId = (int) ($_GET['project_id'] ?? 0);
        $project = ProjectModel::find($projectId);
        if (!$project) {
            Session::flash('error', 'Project not found.');
            redirect('index.php?action=projects');
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verifyRequest();
            $scheduledAt = $_POST['scheduled_at'] ?? '';
            $venue = $_POST['venue'] ?? '';
            
            Database::run('INSERT INTO presentations (project_id, scheduled_at, venue, status, scheduled_by) VALUES (?, ?, ?, "scheduled", ?)',
                [$projectId, $scheduledAt, $venue, Auth::id()]);
            
            Session::flash('success', 'Presentation scheduled.');
            redirect('index.php?action=presentations');
        }
        include APP_PATH . '/views/presentations/schedule.php';
    }

    public static function panel(): void
    {
        Gate::authorize('presentation.manage');
        $presentationId = (int) ($_GET['id'] ?? 0);
        $presentation = Database::one('SELECT * FROM presentations WHERE id = ?', [$presentationId]);
        if (!$presentation) {
            Session::flash('error', 'Presentation not found.');
            redirect('index.php?action=presentations');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verifyRequest();
            $userId = (int) ($_POST['user_id'] ?? 0);
            $role = $_POST['role'] ?? 'member';
            $name = $_POST['name'] ?? '';
            $affiliation = $_POST['affiliation'] ?? '';

            Database::run('INSERT INTO presentation_panels (presentation_id, user_id, member_name, member_role, affiliation) VALUES (?, ?, ?, ?, ?)',
                [$presentationId, $userId > 0 ? $userId : null, $name, $role, $affiliation]);

            Session::flash('success', 'Panel member added.');
            redirect('index.php?action=presentation_panel&id=' . $presentationId);
        }

        $panel = Database::all('SELECT * FROM presentation_panels WHERE presentation_id = ?', [$presentationId]);
        include APP_PATH . '/views/presentations/panel.php';
    }

    public static function evaluate(): void
    {
        Gate::authorize('presentation.evaluate');
        $presentationId = (int) ($_GET['id'] ?? 0);
        $presentation = Database::one('SELECT * FROM presentations WHERE id = ?', [$presentationId]);
        if (!$presentation) {
            Session::flash('error', 'Presentation not found.');
            redirect('index.php?action=presentations');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verifyRequest();
            $recommendation = $_POST['recommendation'] ?? '';
            $comment = $_POST['overall_comment'] ?? '';

            Database::run('INSERT INTO presentation_evaluations (presentation_id, evaluator_user_id, recommendation, overall_comment, status, submitted_at) VALUES (?, ?, ?, ?, "submitted", ?)',
                [$presentationId, Auth::id(), $recommendation, $comment, date('Y-m-d H:i:s')]);

            Session::flash('success', 'Evaluation submitted.');
            redirect('index.php?action=presentations');
        }
        include APP_PATH . '/views/presentations/evaluate.php';
    }

    public static function decide(): void
    {
        Gate::authorize('presentation.decide');
        $presentationId = (int) ($_GET['id'] ?? 0);
        
        $presentation = Database::one('SELECT p.*, rp.college_id FROM presentations p JOIN research_projects rp ON rp.id = p.project_id WHERE p.id = ?', [$presentationId]);
        if (!$presentation) {
            Session::flash('error', 'Presentation not found.');
            redirect('index.php?action=presentations');
        }

        // College coordinator scoping (§5)
        if (Gate::hasRole('college_coordinator') && Gate::denies('research.manage') && (int) Auth::user()['college_id'] !== (int) $presentation['college_id']) {
            Session::flash('error', 'Unauthorized: Record belongs to another college.');
            redirect('index.php?action=presentations');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verifyRequest();
            $status = $_POST['status'] ?? '';
            
            Database::run('UPDATE presentations SET status = ? WHERE id = ?', [$status, $presentationId]);
            
            // TODO: Integrate forwarding workflow if status is 'presented'
            
            Session::flash('success', 'Presentation status updated.');
            redirect('index.php?action=presentations');
        }
    }
}

