<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\DeadlineHelper;
use App\Core\Settings;

/**
 * Research calls (spec §11–§14).
 */
final class CallModel
{
    public static function paginate(array $filters, int $page, int $perPage): array
    {
        $where  = ['1=1'];
        $params = [];
        if (!empty($filters['status'])) {
            $where[]  = 'c.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['q'])) {
            $where[]  = '(c.title LIKE ? OR c.reference_number LIKE ?)';
            $like     = '%' . $filters['q'] . '%';
            $params[] = $like;
            $params[] = $like;
        }
        $whereSql = implode(' AND ', $where);

        $total = (int) Database::scalar("SELECT COUNT(*) FROM research_calls c WHERE $whereSql", $params);
        $rows  = Database::all(
            "SELECT c.*, u.full_name AS created_by_name, th.name AS theme_name,
                    (SELECT COUNT(*) FROM research_applications a WHERE a.call_id = c.id) AS application_count
             FROM research_calls c
             LEFT JOIN users u ON u.id = c.created_by
             LEFT JOIN research_themes th ON th.id = c.research_theme_id
             LEFT JOIN (SELECT ct.call_id, GROUP_CONCAT(t.name, ', ') AS theme_names FROM research_call_themes ct JOIN research_themes t ON t.id = ct.theme_id GROUP BY ct.call_id) cthemes ON cthemes.call_id = c.id
             WHERE $whereSql
             ORDER BY c.created_at DESC
             LIMIT " . (int) $perPage . ' OFFSET ' . (int) (($page - 1) * $perPage),
            $params
        );
        return ['total' => $total, 'rows' => $rows];
    }

    public static function openCalls(): array
    {
        $calls = Database::all(
            "SELECT c.*, COALESCE(cthemes.theme_names, th.name) AS theme_name,
                    (SELECT COUNT(*) FROM research_applications a WHERE a.call_id = c.id) AS application_count
             FROM research_calls c
             LEFT JOIN research_themes th ON th.id = c.research_theme_id
             LEFT JOIN (SELECT ct.call_id, GROUP_CONCAT(t.name, ', ') AS theme_names FROM research_call_themes ct JOIN research_themes t ON t.id = ct.theme_id GROUP BY ct.call_id) cthemes ON cthemes.call_id = c.id
             WHERE c.status IN ('published','open')
             ORDER BY c.opens_at ASC"
        );
        return array_values(array_filter($calls, static fn(array $call): bool => DeadlineHelper::isCallOpen($call)));
    }

    public static function find(int $id): ?array
    {
        return Database::one(
            'SELECT c.*, u.full_name AS created_by_name, COALESCE(cthemes.theme_names, th.name) AS theme_name
             FROM research_calls c
             LEFT JOIN users u ON u.id = c.created_by
             LEFT JOIN research_themes th ON th.id = c.research_theme_id
             LEFT JOIN (SELECT ct.call_id, GROUP_CONCAT(t.name, ', ') AS theme_names FROM research_call_themes ct JOIN research_themes t ON t.id = ct.theme_id GROUP BY ct.call_id) cthemes ON cthemes.call_id = c.id
             WHERE c.id = ?',
            [$id]
        );
    }

    public static function create(array $data): int
    {
        if (!array_key_exists('required_evaluators', $data) || $data['required_evaluators'] === null) {
            $data['required_evaluators'] = max(1, Settings::int('college_evaluators_default', Settings::int('required_evaluators_default', 1)));
        }
        if (!isset($data['call_number'])) {
            $data['call_number'] = \App\Services\NumberingService::next('research_call');
        }
        return Database::insert('research_calls', $data);
    }

    public static function update(int $id, array $data): void
    {
        Database::update('research_calls', $data, 'id = ?', [$id]);
    }

    /** Associate one or more university-wide themes with a call. Legacy single-theme data is retained. */
    public static function setThemes(int $callId, array $themeIds): void
    {
        $themeIds = array_values(array_unique(array_filter(array_map('intval', $themeIds), static fn(int $id): bool => $id > 0)));
        if ($themeIds === []) {
            throw new \\InvalidArgumentException('Select at least one research theme for this call.');
        }
        foreach ($themeIds as $themeId) {
            if ((int) Database::scalar('SELECT COUNT(*) FROM research_themes WHERE id = ? AND status = "active"', [$themeId]) !== 1) {
                throw new \\InvalidArgumentException('One or more selected themes are unavailable.');
            }
        }
        Database::transaction(function () use ($callId, $themeIds): void {
            Database::run('DELETE FROM research_call_themes WHERE call_id = ?', [$callId]);
            foreach ($themeIds as $themeId) {
                Database::insert('research_call_themes', ['call_id' => $callId, 'theme_id' => $themeId]);
            }
            // Keep the original column populated for older reports/integrations.
            Database::run('UPDATE research_calls SET research_theme_id = ? WHERE id = ?', [$themeIds[0], $callId]);
        });
    }

    public static function themes(int $callId): array
    {
        return Database::all(
            'SELECT t.* FROM research_themes t
             JOIN research_call_themes ct ON ct.theme_id = t.id
             WHERE ct.call_id = ? ORDER BY t.name',
            [$callId]
        );
    }

    public static function setEligibility(int $callId, array $rows): void
    {
        Database::transaction(function () use ($callId, $rows): void {
            Database::run('DELETE FROM research_call_eligibility WHERE call_id = ?', [$callId]);
            foreach ($rows as $r) {
                Database::insert('research_call_eligibility', [
                    'call_id'         => $callId,
                    'eligibility_key' => $r['key'],
                    'ref_id'          => $r['ref_id'] ?? null,
                    'ref_value'       => $r['value'] ?? null,
                ]);
            }
        });
    }

    public static function eligibility(int $callId): array
    {
        return Database::all('SELECT * FROM research_call_eligibility WHERE call_id = ?', [$callId]);
    }

    public static function setRequirements(int $callId, array $rows): void
    {
        Database::transaction(function () use ($callId, $rows): void {
            Database::run('DELETE FROM research_call_requirements WHERE call_id = ?', [$callId]);
            foreach ($rows as $i => $r) {
                Database::insert('research_call_requirements', [
                    'call_id'          => $callId,
                    'document_type_id' => (int) $r['document_type_id'],
                    'requirement'      => $r['requirement'],
                    'condition_note'   => $r['condition_note'] ?? null,
                    'sort_order'       => $i,
                ]);
            }
        });
    }

    public static function requirements(int $callId): array
    {
        return Database::all(
            'SELECT r.*, dt.type_code, dt.name AS type_name
             FROM research_call_requirements r JOIN document_types dt ON dt.id = r.document_type_id
             WHERE r.call_id = ? ORDER BY r.sort_order',
            [$callId]
        );
    }

    public static function setCriteria(int $callId, array $rows): void
    {
        Database::transaction(function () use ($callId, $rows): void {
            Database::run('DELETE FROM research_call_criteria WHERE call_id = ?', [$callId]);
            foreach ($rows as $i => $r) {
                Database::insert('research_call_criteria', [
                    'call_id'        => $callId,
                    'criterion_name' => $r['name'],
                    'description'    => $r['description'] ?? null,
                    'weight_pct'     => $r['weight'],
                    'sort_order'     => $i,
                ]);
            }
        });
    }

    public static function criteria(int $callId): array
    {
        return Database::all(
            'SELECT * FROM research_call_criteria WHERE call_id = ? ORDER BY sort_order',
            [$callId]
        );
    }

    /** Alias used by the evaluation flow. */
    public static function criteriaFor(int $callId): array
    {
        return self::criteria($callId);
    }
}
