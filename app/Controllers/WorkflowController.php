<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Session;
use App\Models\ApplicationModel;
use App\Services\WorkflowService;

/**
 * Workflow administration (spec §92–§96) and the generic transition endpoint.
 */
final class WorkflowController
{
    public static function viewWorkflows(): void
    {
        Gate::authorize('workflow.manage');
        $workflows = \App\Core\Database::all(
            'SELECT w.*,
                    (SELECT COUNT(*) FROM workflow_stages s WHERE s.workflow_id = w.id) AS stage_count,
                    (SELECT COUNT(*) FROM workflow_transitions t WHERE t.workflow_id = w.id) AS transition_count
             FROM workflows w ORDER BY w.entity_type, w.name'
        );
        $selectedId = (int) ($_GET['id'] ?? 0);
        $selected = null;
        $stages = [];
        $transitions = [];
        if ($selectedId) {
            $selected = \App\Core\Database::one('SELECT * FROM workflows WHERE id = ?', [$selectedId]);
            $stages = \App\Core\Database::all(
                'SELECT s.*, r.display_name AS responsible_role
                 FROM workflow_stages s LEFT JOIN roles r ON r.id = s.responsible_role_id
                 WHERE s.workflow_id = ? ORDER BY s.sort_order',
                [$selectedId]
            );
            $transitions = \App\Core\Database::all(
                'SELECT t.*, fs.stage_name AS from_name, ts.stage_name AS to_name
                 FROM workflow_transitions t
                 JOIN workflow_stages fs ON fs.id = t.from_stage_id
                 JOIN workflow_stages ts ON ts.id = t.to_stage_id
                 WHERE t.workflow_id = ? ORDER BY t.sort_order',
                [$selectedId]
            );
        }
        include APP_PATH . '/views/admin/workflows.php';
    }

    public static function toggleTransition(): void
    {
        Gate::authorize('workflow.manage');
        Csrf::verifyRequest();
        $id = (int) ($_POST['id'] ?? 0);
        $active = (int) ($_POST['active'] ?? 1);
        \App\Core\Database::update('workflow_transitions', ['is_active' => $active ? 1 : 0], 'id = ?', [$id]);
        Audit::log('workflow.transition_toggled', 'workflow_transition', $id, null, ['active' => $active]);
        Session::flash('success', 'Transition updated.');
        redirect('index.php?action=workflows');
    }

    /** Generic POST endpoint for firing a named transition on an application. */
    public static function fire(): void
    {
        Auth::check() || Gate::authorize('research.view');
        Csrf::verifyRequest();
        $entityType = (string) ($_POST['entity_type'] ?? 'research_application');
        $entityId   = (int) ($_POST['entity_id'] ?? 0);
        $transition = (string) ($_POST['transition'] ?? '');
        $comment    = trim((string) ($_POST['comment'] ?? '')) ?: null;
        if ($entityType !== 'research_application') {
            Session::flash('error', 'Unsupported entity type.');
            redirect('index.php');
        }
        $entity = ApplicationModel::find($entityId);
        if ($entity === null) {
            Session::flash('error', 'Application not found.');
            redirect('index.php?action=applications');
        }
        // College scoping (§5): coordinators only manage their own college's
        // research — enforced here as an entity-level layer over RBAC (Rule 5).
        if ((Gate::hasRole('college_coordinator') || Gate::hasResponsibility('dept_coordinator'))
            && Gate::denies('research.manage')) {
            $myCollege = (int) (Auth::user()['college_id'] ?? 0);
            if ($myCollege === 0 || $myCollege !== (int) $entity['college_id']) {
                Audit::log('college.scope_denied', 'research_application', $entityId, null,
                    ['user' => Auth::id(), 'transition' => $transition, 'app_college' => (int) $entity['college_id']]);
                Session::flash('error', 'You cannot manage research belonging to another college (§5).');
                redirect('index.php?action=application_view&id=' . $entityId);
            }
        }
        [$ok, $err] = WorkflowService::fire($entity, $entityType, $transition, $comment);
        if ($ok) {
            Session::flash('success', 'Status updated: ' . $transition);
        } else {
            Session::flash('error', $err);
        }
        redirect('index.php?action=application_view&id=' . $entityId);
    }

    /** Transition endpoint for research projects (permission enforced per transition). */
    public static function fireProject(): void
    {
        Auth::check() || Gate::authorize('project.view');
        Csrf::verifyRequest();
        $entityId   = (int) ($_POST['entity_id'] ?? 0);
        $transition = (string) ($_POST['transition'] ?? '');
        $comment    = trim((string) ($_POST['comment'] ?? '')) ?: null;
        $entity = \App\Models\ProjectModel::find($entityId);
        if ($entity === null) {
            Session::flash('error', 'Project not found.');
            redirect('index.php?action=projects');
        }
        [$ok, $err] = WorkflowService::fire($entity, 'research_project', $transition, $comment);
        if ($ok) {
            Session::flash('success', 'Status updated: ' . $transition);
        } else {
            Session::flash('error', $err);
        }
        redirect('index.php?action=project_view&id=' . $entityId);
    }

    /** Transition endpoint for ethics applications (permission enforced per transition). */
    public static function fireEthics(): void
    {
        Auth::check() || Gate::authorize('ethics.view');
        Csrf::verifyRequest();
        $entityId   = (int) ($_POST['entity_id'] ?? 0);
        $transition = (string) ($_POST['transition'] ?? '');
        $comment    = trim((string) ($_POST['comment'] ?? '')) ?: null;
        $entity = \App\Models\EthicsModel::find($entityId);
        if ($entity === null) {
            Session::flash('error', 'Ethics application not found.');
            redirect('index.php?action=ethics');
        }
        [$ok, $err] = WorkflowService::fire($entity, 'ethics_application', $transition, $comment);
        if ($ok) {
            Session::flash('success', 'Status updated: ' . $transition);
        } else {
            Session::flash('error', $err);
        }
        redirect('index.php?action=ethics_view&id=' . $entityId);
    }
}
