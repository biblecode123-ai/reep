<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class UserModel
{
    public static function findActiveById(int $id): ?array
    {
        return Database::one(
            'SELECT u.*, c.name AS college_name, d.name AS department_name
             FROM users u
             LEFT JOIN colleges c ON c.id = u.college_id
             LEFT JOIN departments d ON d.id = u.department_id
             WHERE u.id = ? AND u.status = "active"',
            [$id]
        );
    }

    public static function findByIdentifier(string $identifier): ?array
    {
        return Database::one(
            'SELECT * FROM users WHERE (email = ? OR employee_id = ?) LIMIT 1',
            [$identifier, $identifier]
        );
    }

    public static function findByEmail(string $email): ?array
    {
        return Database::one('SELECT * FROM users WHERE email = ? LIMIT 1', [$email]);
    }

    public static function findById(int $id): ?array
    {
        return Database::one(
            'SELECT u.*, c.name AS college_name, d.name AS department_name
             FROM users u
             LEFT JOIN colleges c ON c.id = u.college_id
             LEFT JOIN departments d ON d.id = u.department_id
             WHERE u.id = ?',
            [$id]
        );
    }

    public static function permissionsFor(int $userId): array
    {
        $rows = Database::all(
            'SELECT DISTINCT p.name
             FROM permissions p
             JOIN role_permissions rp ON rp.permission_id = p.id
             JOIN user_roles ur ON ur.role_id = rp.role_id
             WHERE ur.user_id = ?
             UNION
             SELECT DISTINCT p.name
             FROM permissions p
             JOIN role_permissions rp ON rp.permission_id = p.id
             JOIN user_responsibilities ur ON ur.role_id = rp.role_id
             WHERE ur.user_id = ?
             UNION
             SELECT DISTINCT p.name
             FROM permissions p
             JOIN user_permission_grants ug ON ug.permission_id = p.id
             WHERE ug.user_id = ?
               AND (ug.source_role_id IS NULL
                    OR EXISTS (SELECT 1 FROM user_roles ur WHERE ur.user_id = ug.user_id AND ur.role_id = ug.source_role_id)
                    OR EXISTS (SELECT 1 FROM user_responsibilities ur WHERE ur.user_id = ug.user_id AND ur.role_id = ug.source_role_id))',
            [$userId, $userId, $userId]
        );
        return array_column($rows, 'name');
    }

    public static function roleNamesFor(int $userId): array
    {
        $rows = Database::all(
            "SELECT r.name FROM roles r
             JOIN user_roles ur ON ur.role_id = r.id
             WHERE ur.user_id = ? AND r.role_type = 'primary'",
            [$userId]
        );
        return array_column($rows, 'name');
    }

    public static function responsibilityNamesFor(int $userId): array
    {
        $rows = Database::all(
            "SELECT r.name FROM roles r
             JOIN user_responsibilities ur ON ur.role_id = r.id
             WHERE ur.user_id = ? AND r.role_type = 'assignment'",
            [$userId]
        );
        return array_column($rows, 'name');
    }

    public static function roleIdsFor(int $userId): array
    {
        return array_map('intval', array_column(
            Database::all("SELECT ur.role_id FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ? AND r.role_type = 'primary'", [$userId]),
            'role_id'
        ));
    }

    public static function responsibilityIdsFor(int $userId): array
    {
        return array_map('intval', array_column(
            Database::all("SELECT ur.role_id FROM user_responsibilities ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ? AND r.role_type = 'assignment'", [$userId]),
            'role_id'
        ));
    }

    // ---- Login throttling (spec §9) ------------------------------------

    public static function isLockedOut(string $identifier, string $ip, int $maxAttempts, int $lockoutMinutes): bool
    {
        // Compare against the database clock (not PHP's local time) so the window
        // is correct regardless of server timezone — SQLite CURRENT_TIMESTAMP is UTC.
        $timeCond = Database::isSqlite()
            ? "datetime('now', '-' || ? || ' minutes')"
            : 'DATE_SUB(NOW(), INTERVAL ? MINUTE)';
        $count = (int) Database::scalar(
            "SELECT COUNT(*) FROM login_attempts
             WHERE successful = 0 AND attempted_at > $timeCond
               AND (identifier = ? OR ip_address = ?)",
            [$lockoutMinutes, $identifier, $ip]
        );
        return $count >= $maxAttempts;
    }

    public static function recordLoginAttempt(string $identifier, string $ip, bool $success): void
    {
        Database::insert('login_attempts', [
            'identifier' => mb_substr($identifier, 0, 190),
            'ip_address' => $ip,
            'successful' => $success ? 1 : 0,
        ]);
    }

    public static function touchLastLogin(int $userId, string $ip): void
    {
        Database::update('users', ['last_login_at' => date('Y-m-d H:i:s'), 'last_login_ip' => $ip], 'id = ?', [$userId]);
    }

    // ---- Administration (spec §10, §102) -------------------------------

    public static function create(array $data, array $roleIds, array $responsibilityIds = []): int
    {
        return Database::transaction(function () use ($data, $roleIds, $responsibilityIds): int {
            $userId = Database::insert('users', $data);
            self::replaceMemberships($userId, $roleIds, $responsibilityIds);
            return $userId;
        });
    }

    public static function update(
        int $id,
        array $data,
        ?array $roleIds = null,
        ?array $responsibilityIds = null
    ): void {
        Database::transaction(function () use ($id, $data, $roleIds, $responsibilityIds): void {
            if ($data !== []) {
                Database::update('users', $data, 'id = ?', [$id]);
            }
            if ($roleIds !== null || $responsibilityIds !== null) {
                self::replaceMemberships($id, $roleIds ?? self::roleIdsFor($id), $responsibilityIds ?? self::responsibilityIdsFor($id));
            }
        });
    }

    private static function replaceMemberships(int $userId, array $roleIds, array $responsibilityIds): void
    {
        $primaryRoleIds = array_values(array_unique(array_map('intval', $roleIds)));
        $validPrimaryCount = $primaryRoleIds === [] ? 0 : (int) Database::scalar(
            "SELECT COUNT(*) FROM roles WHERE id = ? AND role_type = 'primary'",
            [$primaryRoleIds[0]]
        );
        if (count($primaryRoleIds) !== 1 || $validPrimaryCount !== 1) {
            throw new \InvalidArgumentException('Each user must have exactly one primary account category.');
        }

        Database::run('DELETE FROM user_roles WHERE user_id = ?', [$userId]);
        Database::run('DELETE FROM user_responsibilities WHERE user_id = ?', [$userId]);

        foreach (array_unique(array_map('intval', $roleIds)) as $roleId) {
            if (Database::scalar("SELECT COUNT(*) FROM roles WHERE id = ? AND role_type = 'primary'", [$roleId])) {
                Database::insert('user_roles', ['user_id' => $userId, 'role_id' => $roleId]);
            }
        }
        foreach (array_unique(array_map('intval', $responsibilityIds)) as $roleId) {
            if (Database::scalar("SELECT COUNT(*) FROM roles WHERE id = ? AND role_type = 'assignment'", [$roleId])) {
                Database::insert('user_responsibilities', ['user_id' => $userId, 'role_id' => $roleId]);
            }
        }
    }

    public static function hardDelete(int $id): void
    {
        // Perform soft delete to avoid Foreign Key constraint violations
        self::update($id, ['status' => 'inactive']);
    }

    public static function paginate(array $filters, int $page, int $perPage): array
    {
        $where  = ['1=1'];
        $params = [];
        if (!empty($filters['q'])) {
            $where[] = '(u.full_name LIKE ? OR u.email LIKE ? OR u.employee_id LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            $params[] = $like; $params[] = $like; $params[] = $like;
        }
        if (!empty($filters['college_id'])) {
            $where[] = 'u.college_id = ?';
            $params[] = (int) $filters['college_id'];
        }
        if (!empty($filters['department_id'])) {
            $where[] = 'u.department_id = ?';
            $params[] = (int) $filters['department_id'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'u.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['role'])) {
            $where[] = "EXISTS (SELECT 1 FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = u.id AND r.role_type = 'primary' AND r.name = ?)";
            $params[] = $filters['role'];
        }
        $whereSql = implode(' AND ', $where);

        $total = (int) Database::scalar("SELECT COUNT(*) FROM users u WHERE $whereSql", $params);
        $roleListSql = Database::isSqlite()
            ? "(SELECT group_concat(r.display_name, ', ') FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = u.id AND r.role_type = 'primary')"
            : "(SELECT GROUP_CONCAT(r.display_name SEPARATOR ', ') FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = u.id AND r.role_type = 'primary')";
        $assignmentListSql = Database::isSqlite()
            ? "(SELECT group_concat(r.display_name, ', ') FROM user_responsibilities ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = u.id AND r.role_type = 'assignment')"
            : "(SELECT GROUP_CONCAT(r.display_name SEPARATOR ', ') FROM user_responsibilities ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = u.id AND r.role_type = 'assignment')";
        $rows  = Database::all(
            "SELECT u.*, c.name AS college_name, d.name AS department_name,
                    $roleListSql AS role_names, $assignmentListSql AS responsibility_names
             FROM users u
             LEFT JOIN colleges c ON c.id = u.college_id
             LEFT JOIN departments d ON d.id = u.department_id
             WHERE $whereSql
             ORDER BY u.full_name
             LIMIT " . (int) $perPage . ' OFFSET ' . (int) (($page - 1) * $perPage),
            $params
        );
        return ['total' => $total, 'rows' => $rows];
    }

    public static function listForSelect(?int $collegeId = null): array
    {
        $sql = 'SELECT id, full_name, employee_id FROM users WHERE status = "active"';
        $params = [];
        if ($collegeId !== null) {
            $sql .= ' AND college_id = ?';
            $params[] = $collegeId;
        }
        $sql .= ' ORDER BY full_name';
        return Database::all($sql, $params);
    }
}
