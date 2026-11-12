<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Research applications (spec §16–§18).
 * A research PROJECT row is created only at university approval (§5, §35).
 */
final class ApplicationModel
{
    public static function find(int $id): ?array
    {
        return Database::one(
            'SELECT a.*, u.full_name AS pi_name, u.email AS pi_email,
                    c.name AS college_name, d.name AS department_name,
                    rc.title AS call_title, rc.reference_number AS call_reference,
                    rc.evaluation_mode, rc.required_evaluators,
                    t.name AS research_type_name, th.name AS research_theme_name,
                    ws.stage_name AS stage_label
             FROM research_applications a
             JOIN users u ON u.id = a.pi_user_id
             LEFT JOIN colleges c ON c.id = a.college_id
             LEFT JOIN departments d ON d.id = a.department_id
             LEFT JOIN research_calls rc ON rc.id = a.call_id
             LEFT JOIN research_types t ON t.id = a.research_type_id
             LEFT JOIN research_themes th ON th.id = a.research_theme_id
             LEFT JOIN workflow_stages ws ON ws.id = a.current_stage_id
             WHERE a.id = ?',
            [$id]
        );
    }

    public static function findForPi(int $id, int $piUserId): ?array
    {
        return Database::one(
            'SELECT id FROM research_applications WHERE id = ? AND pi_user_id = ?',
            [$id, $piUserId]
        );
    }

    public static function create(array $data): int
    {
        return Database::insert('research_applications', $data);
    }

    public static function update(int $id, array $data): void
    {
        Database::update('research_applications', $data, 'id = ?', [$id]);
    }

    /** Work plan (§16). */
    public static function setWorkPlan(int $applicationId, array $items): void
    {
        Database::transaction(function () use ($applicationId, $items): void {
            Database::run('DELETE FROM research_work_plan_items WHERE application_id = ?', [$applicationId]);
            foreach ($items as $i => $it) {
                if (trim((string) ($it['activity'] ?? '')) === '') {
                    continue;
                }
                Database::insert('research_work_plan_items', [
                    'application_id'  => $applicationId,
                    'activity'        => $it['activity'],
                    'start_date'      => $it['start_date'] ?: null,
                    'end_date'        => $it['end_date'] ?: null,
                    'responsible'     => $it['responsible'] ?? null,
                    'expected_output' => $it['expected_output'] ?? null,
                    'sort_order'      => $i,
                ]);
            }
        });
    }

    public static function workPlan(int $applicationId): array
    {
        return Database::all(
            'SELECT * FROM research_work_plan_items WHERE application_id = ? ORDER BY sort_order',
            [$applicationId]
        );
    }

    /** Duplicate detection warning (spec §98) — warning only, never blocking. */
    public static function duplicatesFor(string $title, int $piUserId, ?string $keywords = null): array
    {
        $titleWords = array_filter(explode(' ', preg_replace('/[^A-Za-z0-9 ]/', ' ', strtolower($title))));
        $out = [];
        if (count($titleWords) >= 3) {
            $like = '%' . implode('%', array_slice($titleWords, 0, 4)) . '%';
            $rows = Database::all(
                'SELECT id, application_number, title, status_code, created_at
                 FROM research_applications
                 WHERE title LIKE ? AND id <> COALESCE(?, -1) LIMIT 5',
                [$like, null]
            );
            foreach ($rows as $r) {
                $out[] = ['kind' => 'similar_title', 'id' => (int) $r['id'], 'ref' => $r['application_number'], 'title' => $r['title'], 'status' => $r['status_code']];
            }
        }
        $mine = Database::all(
            'SELECT id, application_number, title, status_code FROM research_applications WHERE pi_user_id = ? ORDER BY id DESC LIMIT 5',
            [$piUserId]
        );
        foreach ($mine as $r) {
            $out[] = ['kind' => 'same_researcher', 'id' => (int) $r['id'], 'ref' => $r['application_number'], 'title' => $r['title'], 'status' => $r['status_code']];
        }
        return $out;
    }

    public static function countForCallByPi(int $callId, int $piUserId): int
    {
        return (int) Database::scalar(
            'SELECT COUNT(*) FROM research_applications WHERE call_id = ? AND pi_user_id = ? AND status_code <> "DRAFT"',
            [$callId, $piUserId]
        );
    }

    public static function forPi(int $piUserId, int $limit = 100): array
    {
        return Database::all(
            'SELECT a.id, a.application_number, a.title, a.status_code, a.budget_requested, a.submitted_at, a.created_at,
                    rc.title AS call_title, ws.stage_name AS stage_label
             FROM research_applications a
             LEFT JOIN research_calls rc ON rc.id = a.call_id
             LEFT JOIN workflow_stages ws ON ws.id = a.current_stage_id
             WHERE a.pi_user_id = ?
             ORDER BY a.created_at DESC LIMIT ' . (int) $limit,
            [$piUserId]
        );
    }

    public static function paginate(array $filters, int $page, int $perPage): array
    {
        $where  = ['1=1'];
        $params = [];
        if (!empty($filters['status'])) {
            $where[]  = 'a.status_code = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['call_id'])) {
            $where[]  = 'a.call_id = ?';
            $params[] = (int) $filters['call_id'];
        }
        if (!empty($filters['college_id'])) {
            $where[]  = 'a.college_id = ?';
            $params[] = (int) $filters['college_id'];
        }
        if (!empty($filters['pi_user_id'])) {
            $where[]  = 'a.pi_user_id = ?';
            $params[] = (int) $filters['pi_user_id'];
        }
        if (!empty($filters['q'])) {
            $where[]  = '(a.title LIKE ? OR a.application_number LIKE ?)';
            $like     = '%' . $filters['q'] . '%';
            $params[] = $like;
            $params[] = $like;
        }
        $whereSql = implode(' AND ', $where);

        $total = (int) Database::scalar("SELECT COUNT(*) FROM research_applications a WHERE $whereSql", $params);
        $rows  = Database::all(
            "SELECT a.id, a.application_number, a.title, a.status_code, a.budget_requested, a.submitted_at, a.call_id,
                    u.full_name AS pi_name, c.name AS college_name, rc.title AS call_title,
                    ws.stage_name AS stage_label
             FROM research_applications a
             JOIN users u ON u.id = a.pi_user_id
             LEFT JOIN colleges c ON c.id = a.college_id
             LEFT JOIN research_calls rc ON rc.id = a.call_id
             LEFT JOIN workflow_stages ws ON ws.id = a.current_stage_id
             WHERE $whereSql
             ORDER BY a.submitted_at DESC, a.created_at DESC
             LIMIT " . (int) $perPage . ' OFFSET ' . (int) (($page - 1) * $perPage),
            $params
        );
        return ['total' => $total, 'rows' => $rows];
    }

    public static function statusCounts(): array
    {
        return Database::all(
            'SELECT a.status_code, COUNT(*) AS cnt
             FROM research_applications a
             GROUP BY a.status_code ORDER BY cnt DESC'
        );
    }
}

