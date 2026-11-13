<?php
declare(strict_types=1);

/**
 * Project root entry point (XAMPP convenience).
 * Opening http://localhost/REEP/ lands here and forwards to the application
 * front controller in public/ — the login page for guests, the dashboard
 * for signed-in users.
 */

header('Location: public/index.php');
exit;
