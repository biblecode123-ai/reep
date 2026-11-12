<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;

/**
 * Enforces authentication for all non-public routes (spec §87).
 */
final class AuthMiddleware
{
    public static function handle(): void
    {
        if (!Auth::check()) {
            \App\Core\Session::flash('warning', 'Please log in to continue.');
            if (is_ajax_request()) {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'message' => 'Authentication required.']);
                exit;
            }
            redirect('login.php');
        }
        if ((int) Auth::user()['must_change_password'] === 1) {
            // Allow only dashboard, profile and password change until the password is changed.
            $action = (string) ($_GET['action'] ?? '');
            $allowedActions = ['', 'profile', 'profile_password', 'profile_researcher', 'logout'];
            if (!in_array($action, $allowedActions, true)) {
                \App\Core\Session::flash('warning', 'You must change your initial password first.');
                redirect('index.php?action=profile');
            }
        }
    }
}

