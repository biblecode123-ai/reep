<?php
declare(strict_types=1);

require __DIR__ . '/../config/app.php';
require __DIR__ . '/../app/autoload.php';

use App\Core\Auth;
use App\Models\UserModel;

$identifier = 'test.admin@mwu.edu.et';
$password   = 'AdminPassword123!';

echo "Testing login for: $identifier\n";

$user = UserModel::findByIdentifier($identifier);
if (!$user) {
    echo "FAILED: User not found in database.\n";
    exit;
}
echo "User found. ID: {$user['id']}, Status: {$user['status']}\n";

$verify = password_verify($password, $user['password_hash']);
echo "Password verify: " . ($verify ? "Success" : "Fail") . "\n";

if ($user['status'] !== 'active') {
    echo "FAILED: User status is not active.\n";
    exit;
}

if ($verify && $user['status'] === 'active') {
    echo "SUCCESS: Credentials are valid and user is active. Auth::login should work.\n";
} else {
    echo "FAILED: Logic check failed.\n";
}
?>

