<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Session;
use App\Core\Validator;
use App\Models\UserModel;
use App\Services\NotificationService;
use App\Services\ResearcherPolicy;

final class ProfileController
{
    public static function show(): void
    {
        $userId = (int) Auth::id();
        $user = Auth::user();
        $researcher = Database::one(
            'SELECT r.*, (SELECT id FROM documents WHERE id = r.profile_photo_document_id AND status != "deleted") AS photo_ok
             FROM researchers r WHERE r.user_id = ?',
            [$userId]
        );
        $roles = Database::all(
            'SELECT r.display_name FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ?',
            [$userId]
        );
        $forcePassword = (string) ($_GET['force_password'] ?? '') === '1';
        include APP_PATH . '/views/profile/profile.php';
    }

    public static function updateResearcher(): void
    {
        // Delegate to the shared self-service implementation (fields + audit).
        $_POST += ['position' => '', 'qualification' => '', 'specialization' => '', 'interests' => '',
            'areas' => '', 'biography' => '', 'gender' => '', 'orcid' => '', 'scholar' => ''];
        ResearcherController::updateOwn();
    }

    public static function changePassword(): void
    {
        Csrf::verifyRequest();
        $current = (string) ($_POST['current_password'] ?? '');
        $new     = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');
        if (strlen($new) < 8) {
            Session::flash('error', 'New password must be at least 8 characters.');
            redirect('index.php?action=profile');
        }
        if ($new !== $confirm) {
            Session::flash('error', 'New passwords do not match.');
            redirect('index.php?action=profile');
        }
        if (!Auth::changePassword(Auth::id(), $current, $new)) {
            Session::flash('error', 'Current password is incorrect.');
            redirect('index.php?action=profile');
        }
        Session::flash('success', 'Password changed successfully.');
        redirect('index.php?action=profile');
    }

    public static function notifications(): void
    {
        $userId = (int) Auth::id();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $p = paginate(1, $page, 25);
        $total = (int) Database::scalar('SELECT COUNT(*) FROM notifications WHERE user_id = ?', [$userId]);
        $p = paginate($total, $page, 25);
        $rows = Database::all(
            'SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ' . (int) $p['limit'] . ' OFFSET ' . (int) $p['offset'],
            [$userId]
        );
        $qs = 'action=notifications';
        include APP_PATH . '/views/profile/notifications.php';
    }

    public static function markRead(): void
    {
        Csrf::verifyRequest();
        $id = (int) ($_POST['id'] ?? 0);
        Database::update('notifications', ['read_at' => date('Y-m-d H:i:s')], 'id = ? AND user_id = ?', [$id, Auth::id()]);
        redirect('index.php?action=notifications');
    }

    public static function markAllRead(): void
    {
        Csrf::verifyRequest();
        Database::update('notifications', ['read_at' => date('Y-m-d H:i:s')], 'user_id = ? AND read_at IS NULL', [Auth::id()]);
        redirect('index.php?action=notifications');
    }
}
