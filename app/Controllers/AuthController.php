<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\Validator;

final class AuthController
{
    public static function showLogin(): void
    {
        if (Auth::check()) {
            redirect('index.php');
        }
        $error = Session::flash('error');
        $warning = Session::flash('warning');
        $success = Session::flash('success');
        include APP_PATH . '/views/auth/login.php';
    }

    public static function login(): void
    {
        Csrf::verifyRequest();
        [$ok, $errors, $clean] = Validator::check($_POST, [
            'identifier' => 'required|max:190',
            'password'   => 'required|max:200',
        ]);
        if (!$ok) {
            Session::flash('error', 'Please enter your credentials.');
            redirect('login.php');
        }
        [$success, $message] = Auth::login($clean['identifier'], (string) $_POST['password']);
        if ($success) {
            \App\Core\Logger::info('Login successful', ['id' => Auth::id(), 'user_id_in_session' => \App\Core\Session::get('user_id')]);
            Session::flash('success', $message);
            redirect('index.php');
        }
        Session::flash('error', $message);
        redirect('login.php');
    }

    public static function logout(): void
    {
        Auth::logout();
        Session::start();
        Session::flash('success', 'You have been logged out.');
        redirect('login.php');
    }

    public static function showForgot(): void
    {
        $message = Session::flash('info');
        $error = Session::flash('error');
        include APP_PATH . '/views/auth/forgot.php';
    }

    public static function sendReset(): void
    {
        Csrf::verifyRequest();
        $email = trim((string) ($_POST['email'] ?? ''));
        $token = Auth::createPasswordReset($email);
        // Never reveal whether the account exists (§9).
        if ($token !== null) {
            $link = base_url('index.php?action=reset_password&token=' . $token);
            // Email dispatch is settings-gated (§103); when disabled the link is logged for admins.
            \App\Core\Logger::info('Password reset link generated', ['email' => $email, 'link' => $link]);
            Session::flash('info', 'If the email exists, a reset link has been generated. Contact the administrator to receive it (email delivery is disabled).');
        } else {
            Session::flash('info', 'If the email exists, a reset link has been generated.');
        }
        redirect('index.php?action=forgot');
    }

    public static function showReset(): void
    {
        $token = (string) ($_GET['token'] ?? '');
        include APP_PATH . '/views/auth/reset.php';
    }

    public static function doReset(): void
    {
        Csrf::verifyRequest();
        $token = (string) ($_POST['token'] ?? '');
        $new   = (string) ($_POST['password'] ?? '');
        $conf  = (string) ($_POST['password_confirm'] ?? '');
        if (strlen($new) < 8) {
            Session::flash('error', 'Password must be at least 8 characters.');
            redirect('index.php?action=reset_password&token=' . urlencode($token));
        }
        if ($new !== $conf) {
            Session::flash('error', 'Passwords do not match.');
            redirect('index.php?action=reset_password&token=' . urlencode($token));
        }
        if (Auth::resetPassword($token, $new)) {
            Session::flash('success', 'Password updated. You can log in now.');
            redirect('login.php');
        }
        Session::flash('error', 'Invalid or expired reset link.');
        redirect('login.php');
    }
}

