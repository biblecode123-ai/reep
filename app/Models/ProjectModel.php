<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Research projects — the approved, funded research entity (spec §35, §39–§44, §73).
 * Distinct from applications: a project row is created only at activation.
 */
final class ProjectModel
{
    // ---- Projects ----------------------------------------------------------

    public static function create(array $data): int
    {
        return Database::insert('research_projects', $data);
    }

    public static function find(int $id): ?array
    {
        return Database::one(
            'SELECT p.*, a.application_number, a.call_id, rc.title AS call_title,
                    u.full_name AS pi_name, u.email AS pi_email,
                    c.name AS college_name, d.name AS department_name,
                    t.name AS research_type_name, th.name AS research_theme_name,
                    ws.stage_name AS stage_label
             FROM research_projects p
             JOIN research_applications a ON a.id = p.application_id
             LEFT JOIN research_calls rc ON rc.id = a.call_id
             JOIN users u ON u.id = p.pi_user_id
             LEFT JOIN colleges c ON c.id = p.college_id
             LEFT JOIN departments d ON d.id = p.department_id
             LEFT JOIN research_types t ON t.id = p.research_type_id
             LEFT JOIN research_themes th ON th.id = p.research_theme_id
             LEFT JOIN workflow_stages ws ON ws.id = p.current_stage_id
             WHERE p.id = ?',
            [$id]
        );
    }

    public static function findByApplication(int $applicationId): ?array
    {
        return Database::one('SELECT * FROM research_projects WHERE application_id = ?', [$applicationId]);
    }

    public static function update(int $id, array $data): void
    {
        Database::update('research_projects', $data, 'id = ?', [$id]);
    }

    public static function paginate(array $filters, int $page, int $perPage): array
    {
        $where  = ['1=1'];
        $params = [];
        if (!empty($filters['status'])) {
            $where[]  = 'p.status_code = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['college_id'])) {
            $where[]  = 'p.college_id = ?';
            $params[] = (int) $filters['college_id'];
        }
        if (!empty($filters['q'])) {
            $where[]  = '(p.title LIKE ? OR p.project_number LIKE ?)';
            $like     = '%' . $filters['q'] . '%';
            $params[] = $like;
            $params[] = $like;
        }
        $whereSql = implode(' AND ', $where);

        $total = (int) Database::scalar("SELECT COUNT(*) FROM research_projects p WHERE $whereSql", $params);
        $rows  = Database::all(
            "SELECT p.id, p.project_number, p.title, p.status_code, p.planned_start, p.planned_end,
                    p.approved_budget, u.full_name AS pi_name, c.name AS college_name,
                    ws.stage_name AS stage_label
             FROM research_projects p
             JOIN users u ON u.id = p.pi_user_id
             LEFT JOIN colleges c ON c.id = p.college_id
             LEFT JOIN workflow_stages ws ON ws.id = p.current_stage_id
             WHERE $whereSql
             ORDER BY p.created_at DESC
             LIMIT " . (int) $perPage . ' OFFSET ' . (int) (($page - 1) * $perPage),
            $params
        );
        return ['total' => $total, 'rows' => $rows];
    }

    public static function forPi(int $piUserId): array
    {
        return Database::all(
            'SELECT p.id, p.project_number, p.title, p.status_code, p.planned_end, p.approved_budget,
                    u.full_name AS pi_name, c.name AS college_name,
                    ws.stage_name AS stage_label
             FROM research_projects p
             JOIN users u ON u.id = p.pi_user_id
             LEFT JOIN colleges c ON c.id = p.college_id
             LEFT JOIN workflow_stages ws ON ws.id = p.current_stage_id
             WHERE p.pi_user_id = ?
             ORDER BY p.created_at DESC',
            [$piUserId]
        );
    }

    public static function statusCounts(): array
    {
        return Database::all('SELECT status_code, COUNT(*) AS cnt FROM research_projects GROUP BY status_code');
    }

    // ---- Team members (§73, §74) -------------------------------------------

    public static function members(int $projectId): array
    {
        $order = Database::isSqlite()
            ? "CASE m.member_role WHEN 'principal_investigator' THEN 1 WHEN 'co_investigator' THEN 2 WHEN 'research_assistant' THEN 3 WHEN 'other' THEN 4 ELSE 5 END, u.full_name"
            : 'FIELD(m.member_role, "principal_investigator", "co_investigator", "research_assistant", "other"), u.full_name';
        return Database::all(
            "SELECT m.*, u.full_name, u.email
             FROM research_project_members m JOIN users u ON u.id = m.user_id
             WHERE m.project_id = ? AND m.status = 'active'
             ORDER BY $order",
            [$projectId]
        );
    }

    public static function addMember(array $data): int
    {
        return Database::insert('research_project_members', $data);
    }

    public static function isMember(int $projectId, int $userId): bool
    {
        return (bool) Database::scalar(
            'SELECT COUNT(*) FROM research_project_members WHERE project_id = ? AND user_id = ? AND status = "active"',
            [$projectId, $userId]
        );
    }

    // ---- Milestones (§40) ----------------------------------------------------

    public static function milestones(int $projectId): array
    {
        return Database::all(
            'SELECT m.*, u.full_name AS responsible_name
             FROM research_milestones m LEFT JOIN users u ON u.id = m.responsible_user_id
             WHERE m.project_id = ? ORDER BY m.due_date IS NULL, m.due_date, m.sort_order',
            [$projectId]
        );
    }

    public static function addMilestone(array $data): int
    {
        return Database::insert('research_milestones', $data);
    }

    public static function updateMilestone(int $id, array $data): void
    {
        Database::update('research_milestones', $data, 'id = ?', [$id]);
    }

    public static function milestoneById(int $id): ?array
    {
        return Database::one('SELECT * FROM research_milestones WHERE id = ?', [$id]);
    }

    // ---- Progress reports (§41) ---------------------------------------------

    public static function reports(int $projectId): array
    {
        return Database::all(
            'SELECT r.*, u.full_name AS reviewer_name
             FROM progress_reports r LEFT JOIN users u ON u.id = r.reviewed_by
             WHERE r.project_id = ? ORDER BY r.id DESC',
            [$projectId]
        );
    }

    public static function reportById(int $id): ?array
    {
        return Database::one(
            'SELECT r.*, p.project_number, p.title AS project_title, p.pi_user_id
             FROM progress_reports r JOIN research_projects p ON p.id = r.project_id
             WHERE r.id = ?',
            [$id]
        );
    }

    public static function createReport(array $data): int
    {
        return Database::insert('progress_reports', $data);
    }

    public static function updateReport(int $id, array $data): void
    {
        Database::update('progress_reports', $data, 'id = ?', [$id]);
    }

    // ---- Budget (§36–§38) ------------------------------------------------------

    public static function budget(int $projectId): ?array
    {
        return Database::one(
            'SELECT b.*, u.full_name AS approved_by_name
             FROM research_budgets b LEFT JOIN users u ON u.id = b.approved_by
             WHERE b.project_id = ?',
            [$projectId]
        );
    }

    public static function budgetItems(int $budgetId): array
    {
        return Database::all(
            'SELECT * FROM research_budget_items WHERE budget_id = ? ORDER BY sort_order, id',
            [$budgetId]
        );
    }

    public static function createBudget(int $projectId, float $requested, int $userId): int
    {
        return Database::insert('research_budgets', [
            'project_id'       => $projectId,
            'requested_amount' => $requested,
            'status'           => 'submitted',
            'created_by'       => $userId,
        ]);
    }

    public static function updateBudget(int $budgetId, array $data): void
    {
        Database::update('research_budgets', $data, 'id = ?', [$budgetId]);
    }

    public static function budgetById(int $id): ?array
    {
        return Database::one('SELECT * FROM research_budgets WHERE id = ?', [$id]);
    }

    public static function setBudgetItems(int $budgetId, array $items): void
    {
        Database::transaction(function () use ($budgetId, $items): void {
            Database::run('DELETE FROM research_budget_items WHERE budget_id = ?', [$budgetId]);
            foreach ($items as $i => $it) {
                if (trim((string) ($it['description'] ?? '')) === '') {
                    continue;
                }
                Database::insert('research_budget_items', [
                    'budget_id'        => $budgetId,
                    'item_category'    => $it['category'],
                    'description'      => $it['description'],
                    'requested_amount' => (float) ($it['amount'] ?? 0),
                    'justification'    => $it['justification'] ?? null,
                    'sort_order'       => $i,
                ]);
            }
        });
    }

    public static function fundingReleases(int $projectId): array
    {
        return Database::all(
            'SELECT f.*, u.full_name AS released_by_name
             FROM funding_releases f LEFT JOIN users u ON u.id = f.released_by
             WHERE f.project_id = ? ORDER BY f.released_at DESC',
            [$projectId]
        );
    }

    public static function totalReleased(int $projectId): float
    {
        return (float) (Database::scalar(
            'SELECT COALESCE(SUM(amount), 0) FROM funding_releases WHERE project_id = ?',
            [$projectId]
        ) ?? 0);
    }

    public static function recordRelease(array $data): int
    {
        return Database::insert('funding_releases', $data);
    }

    /** Apply an approved budget decision inside one transaction (§104). */
    public static function applyBudgetApproval(int $projectId, float $approvedAmount, int $approverId): void
    {
        Database::transaction(function () use ($projectId, $approvedAmount, $approverId): void {
            $budget = self::budget($projectId);
            $requested = $budget !== null ? (float) $budget['requested_amount'] : $approvedAmount;
            if ($budget === null) {
                $budgetId = self::createBudget($projectId, $approvedAmount, $approverId);
            } else {
                $budgetId = (int) $budget['id'];
            }
            $ratio = $approvedAmount > 0 ? $approvedAmount / max(0.01, $requested) : 1;
            Database::update('research_budgets', [
                'approved_amount' => $approvedAmount,
                'status'          => 'approved',
                'approved_by'     => $approverId,
                'approved_at'     => date('Y-m-d H:i:s'),
            ], 'id = ?', [$budgetId]);
            // Scale item-level approvals proportionally when the total was reduced.
            if ($ratio < 1) {
                Database::run(
                    'UPDATE research_budget_items SET approved_amount = ROUND(requested_amount * ?, 2) WHERE budget_id = ?',
                    [$ratio, $budgetId]
                );
            } else {
                Database::run(
                    'UPDATE research_budget_items SET approved_amount = requested_amount WHERE budget_id = ?',
                    [$budgetId]
                );
            }
        });
    }

    // ---- Extension requests (§43) ---------------------------------------------

    public static function extensionRequests(int $projectId): array
    {
        return Database::all(
            'SELECT x.*, u.full_name AS requested_by_name, du.full_name AS decided_by_name
             FROM research_extension_requests x
             LEFT JOIN users u ON u.id = x.requested_by
             LEFT JOIN users du ON du.id = x.decided_by
             WHERE x.project_id = ? ORDER BY x.requested_at DESC',
            [$projectId]
        );
    }

    public static function createExtensionRequest(array $data): int
    {
        return Database::insert('research_extension_requests', $data);
    }

    public static function extensionById(int $id): ?array
    {
        return Database::one('SELECT * FROM research_extension_requests WHERE id = ?', [$id]);
    }

    public static function updateExtensionRequest(int $id, array $data): void
    {
        Database::update('research_extension_requests', $data, 'id = ?', [$id]);
    }

    // ---- Project actions (§44) ---------------------------------------------------

    public static function actions(int $projectId): array
    {
        return Database::all(
            'SELECT pa.*, u.full_name AS approved_by_name
             FROM project_actions pa LEFT JOIN users u ON u.id = pa.approved_by
             WHERE pa.project_id = ? ORDER BY pa.action_at DESC',
            [$projectId]
        );
    }

    public static function recordAction(array $data): int
    {
        return Database::insert('project_actions', $data);
    }
}
