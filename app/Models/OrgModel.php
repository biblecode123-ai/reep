<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Colleges, departments, committees, classifications (spec §10, §68, §102).
 */
final class OrgModel
{
    // ---- Colleges -------------------------------------------------------

    public static function colleges(bool $activeOnly = false): array
    {
        $sql = 'SELECT c.*, (SELECT COUNT(*) FROM departments d WHERE d.college_id = c.id) AS dept_count
                FROM colleges c';
        if ($activeOnly) {
            $sql .= ' WHERE c.status = "active"';
        }
        $sql .= ' ORDER BY c.name';
        return Database::all($sql);
    }

    public static function college(int $id): ?array
    {
        return Database::one('SELECT * FROM colleges WHERE id = ?', [$id]);
    }

    public static function createCollege(array $data): int
    {
        return Database::insert('colleges', $data);
    }

    public static function updateCollege(int $id, array $data): void
    {
        Database::update('colleges', $data, 'id = ?', [$id]);
    }

    // ---- Departments ----------------------------------------------------

    public static function departments(?int $collegeId = null): array
    {
        $sql = 'SELECT d.*, c.name AS college_name
                FROM departments d JOIN colleges c ON c.id = d.college_id';
        $params = [];
        if ($collegeId !== null) {
            $sql .= ' WHERE d.college_id = ?';
            $params[] = $collegeId;
        }
        $sql .= ' ORDER BY c.name, d.name';
        return Database::all($sql, $params);
    }

    public static function department(int $id): ?array
    {
        return Database::one(
            'SELECT d.*, c.name AS college_name FROM departments d JOIN colleges c ON c.id = d.college_id WHERE d.id = ?',
            [$id]
        );
    }

    public static function createDepartment(array $data): int
    {
        return Database::insert('departments', $data);
    }

    public static function updateDepartment(int $id, array $data): void
    {
        Database::update('departments', $data, 'id = ?', [$id]);
    }

    // ---- Committees (§68) ------------------------------------------------

    public static function committees(?string $type = null): array
    {
        $sql = 'SELECT cm.*, c.name AS college_name,
                  (SELECT COUNT(*) FROM committee_members m WHERE m.committee_id = cm.id AND m.status = "active") AS member_count
                FROM committees cm LEFT JOIN colleges c ON c.id = cm.college_id';
        $params = [];
        if ($type !== null) {
            $sql .= ' WHERE cm.committee_type = ?';
            $params[] = $type;
        }
        $sql .= ' ORDER BY cm.name';
        return Database::all($sql, $params);
    }

    public static function committee(int $id): ?array
    {
        return Database::one(
            'SELECT cm.*, c.name AS college_name FROM committees cm LEFT JOIN colleges c ON c.id = cm.college_id WHERE cm.id = ?',
            [$id]
        );
    }

    public static function createCommittee(array $data): int
    {
        return Database::insert('committees', $data);
    }

    public static function updateCommittee(int $id, array $data): void
    {
        Database::update('committees', $data, 'id = ?', [$id]);
    }

    public static function committeeMembers(int $committeeId): array
    {
        $order = Database::isSqlite()
            ? "CASE m.member_role WHEN 'chair' THEN 1 WHEN 'secretary' THEN 2 WHEN 'member' THEN 3 ELSE 4 END, u.full_name"
            : 'FIELD(m.member_role, "chair", "secretary", "member"), u.full_name';
        return Database::all(
            "SELECT m.*, u.full_name, u.email
             FROM committee_members m JOIN users u ON u.id = m.user_id
             WHERE m.committee_id = ?
             ORDER BY $order",
            [$committeeId]
        );
    }

    public static function addCommitteeMember(array $data): int
    {
        return Database::insert('committee_members', $data);
    }

    public static function updateCommitteeMember(int $id, array $data): void
    {
        Database::update('committee_members', $data, 'id = ?', [$id]);
    }

    // ---- Classifications (Rule 2; §102) ----------------------------------

    public static function themes(): array
    {
        return Database::all('SELECT * FROM research_themes ORDER BY name');
    }

    public static function types(): array
    {
        return Database::all('SELECT * FROM research_types ORDER BY name');
    }

    public static function documentTypes(): array
    {
        return Database::all('SELECT * FROM document_types WHERE status = "active" ORDER BY name');
    }

    public static function createTheme(array $data): int  { return Database::insert('research_themes', $data); }
    public static function updateTheme(int $id, array $data): void { Database::update('research_themes', $data, 'id = ?', [$id]); }
    public static function createType(array $data): int   { return Database::insert('research_types', $data); }
    public static function updateType(int $id, array $data): void { Database::update('research_types', $data, 'id = ?', [$id]); }
    public static function createDocumentType(array $data): int   { return Database::insert('document_types', $data); }
    public static function updateDocumentType(int $id, array $data): void { Database::update('document_types', $data, 'id = ?', [$id]); }

    // ---- Dashboard statistics (§77, §78) ----------------------------------

    public static function statsForDashboard(): array
    {
        return [
            'colleges'        => (int) Database::scalar('SELECT COUNT(*) FROM colleges WHERE status = "active"'),
            'departments'     => (int) Database::scalar('SELECT COUNT(*) FROM departments WHERE status = "active"'),
            'users'           => (int) Database::scalar('SELECT COUNT(*) FROM users WHERE status = "active"'),
            'researchers'     => (int) Database::scalar("SELECT COUNT(*) FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE r.name = 'researcher' AND r.role_type = 'primary'"),
            'evaluators'      => (int) Database::scalar('SELECT COUNT(*) FROM research_evaluators')
        ];
    }
}
