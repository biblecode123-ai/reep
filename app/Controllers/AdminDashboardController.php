<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Gate;
use App\Core\Database;

final class AdminDashboardController
{
    public static function index(): void
    {
        Gate::authorize('reports.view');
        
        // Institutional Overview
        $stats = [
            'users' => [
                'total' => (int)Database::scalar('SELECT COUNT(*) FROM users'),
                'active' => (int)Database::scalar('SELECT COUNT(*) FROM users WHERE status = "active"'),
                'inactive' => (int)Database::scalar('SELECT COUNT(*) FROM users WHERE status = "inactive"'),
                'suspended' => (int)Database::scalar('SELECT COUNT(*) FROM users WHERE status = "suspended"'),
                'pending' => (int)Database::scalar('SELECT COUNT(*) FROM users WHERE status = "pending"'),
            ],
            'org' => [
                'colleges' => (int)Database::scalar('SELECT COUNT(*) FROM colleges'),
                'active_colleges' => (int)Database::scalar('SELECT COUNT(*) FROM colleges WHERE status = "active"'),
                'departments' => (int)Database::scalar('SELECT COUNT(*) FROM departments'),
                'active_departments' => (int)Database::scalar('SELECT COUNT(*) FROM departments WHERE status = "active"'),
            ],
            'roles' => [],
        ];
        
        // Users by approved roles
        $roles = ['system_admin', 'rpee_director', 'college_coordinator', 'researcher'];
        foreach ($roles as $role) {
            $stats['roles'][$role] = (int)Database::scalar(
                'SELECT COUNT(DISTINCT ur.user_id) 
                 FROM user_roles ur 
                 JOIN roles r ON r.id = ur.role_id 
                 WHERE r.name = ?', 
                [$role]
            );
        }

        include APP_PATH . '/views/admin/dashboard.php';
    }
}
