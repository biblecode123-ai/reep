<?php declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Gate;
use App\Core\Session;
use App\Core\Validator;

final class ProjectInvitationController {
    public static function invite(): void {
        Gate::authorize('project.report');

        Csrf::verifyRequest();

        $projectId = (int) ($_POST['project_id'] ?? 0);

        $project = Database::one('SELECT * FROM research_projects WHERE id = ?', [$projectId]);

        if ($project === null || (int) $project['pi_user_id'] !== Auth::id()) {
            Session::flash('error', 'Not authorized.');
            redirect('index.php?action=projects');
        }

        [$ok, $errors, $clean] = Validator::check($_POST, [
            'email' => 'required|email|max:190',
            'role' => 'required|max:50',
        ]);

        if (!$ok) {
            Session::flash('error', implode(' ', $errors));
            redirect('index.php?action=project_view&id=' . $projectId);
        }

        $user = Database::one('SELECT * FROM users WHERE email = ?', [strtolower($clean['email'])]);

        if ($user === null) {
            Session::flash('error', 'No user found with that email address.');
            redirect('index.php?action=project_view&id=' . $projectId);
        }

        $existing = Database::one('SELECT id FROM project_invitations WHERE project_id = ? AND researcher_id = ? AND status = "pending"', [$projectId, $user['id']]);

        if ($existing !== null) {
            Session::flash('error', 'A pending invitation already exists for this user.');
            redirect('index.php?action=project_view&id=' . $projectId);
        }

        Database::insert('project_invitations', [
            'project_id' => $projectId,
            'researcher_id' => $user['id'],
            'role' => $clean['role'],
            'status' => 'pending',
            'invited_by' => Auth::id(),
            'invited_at' => date('Y-m-d H:i:s'),
        ]);

        Audit::log('project.invitation_sent', 'research_project', $projectId, null, ['researcher' => $user['id']]);

        Session::flash('success', 'Invitation sent to ' . $clean['email'] . '.');

        redirect('index.php?action=project_view&id=' . $projectId);
    }

    public static function respond(): void {
        Gate::authorize('researcher.view');

        Csrf::verifyRequest();

        $invitationId = (int) ($_POST['invitation_id'] ?? 0);

        $invitation = Database::one('SELECT * FROM project_invitations WHERE id = ? AND status = "pending"', [$invitationId]);

        if ($invitation === null || (int) $invitation['researcher_id'] !== Auth::id()) {
            Session::flash('error', 'Invitation not found or already responded to.');
            redirect('index.php?action=projects');
        }

        $response = (string) ($_POST['response'] ?? '');

        if ($response === 'accept') {
            Database::update('project_invitations', ['status' => 'accepted', 'responded_at' => date('Y-m-d H:i:s')], 'id = ?', [$invitationId]);

            Database::insert('research_project_members', [
                'project_id' => $invitation['project_id'],
                'user_id' => $invitation['researcher_id'],
                'member_role' => $invitation['role'],
                'status' => 'active',
                'joined_at' => date('Y-m-d H:i:s'),
            ]);

            Session::flash('success', 'Invitation accepted. You are now a team member.');
        } elseif ($response === 'decline') {
            Database::update('project_invitations', ['status' => 'declined', 'responded_at' => date('Y-m-d H:i:s')], 'id = ?', [$invitationId]);

            Session::flash('success', 'Invitation declined.');
        } else {
            Session::flash('error', 'Invalid response.');
        }

        redirect('index.php?action=projects');
    }
}

