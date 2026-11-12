<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class AuditLogModel
{
    /**
     * Write one audit row. $userId may be null (system action / pre-auth).
     * Audit failures are logged to file, never fatal (spec §65).
     */
    public static function log(
        ?int $userId,
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $old = null,
        ?array $new = null,
        ?string $comment = null
    ): void {
        try {
            Database::insert('audit_logs', [
                'user_id'     => $userId,
                'action'      => mb_substr($action, 0, 100),
                'entity_type' => $entityType,
                'entity_id'   => $entityId,
                'old_values'  => $old !== null ? json_encode($old, JSON_UNESCAPED_UNICODE) : null,
                'new_values'  => json_encode(array_filter([
                    'values'  => $new,
                    'comment' => $comment,
                    'uri'     => $_SERVER['REQUEST_URI'] ?? null,
                ], static fn ($v) => $v !== null), JSON_UNESCAPED_UNICODE),
                'ip_address'  => client_ip(),
                'user_agent'  => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            ]);
        } catch (\Throwable $e) {
            \App\Core\Logger::error('Audit log write failed', ['msg' => $e->getMessage(), 'action' => $action]);
        }
    }

    public static function paginate(array $filters, int $page, int $perPage): array
    {
        $where  = ['1=1'];
        $params = [];
        if (!empty($filters['action'])) {
            $where[]  = 'a.action LIKE ?';
            $params[] = '%' . $filters['action'] . '%';
        }
        if (!empty($filters['entity'])) {
            $where[]  = 'a.entity_type = ?';
            $params[] = $filters['entity'];
        }
        if (!empty($filters['user_id'])) {
            $where[]  = 'a.user_id = ?';
            $params[] = (int) $filters['user_id'];
        }
        if (!empty($filters['date_from'])) {
            $where[]  = 'a.created_at >= ?';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[]  = 'a.created_at <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }
        $whereSql = implode(' AND ', $where);

        $total = (int) Database::scalar("SELECT COUNT(*) FROM audit_logs a WHERE $whereSql", $params);
        $rows  = Database::all(
            "SELECT a.*, u.full_name AS user_name
             FROM audit_logs a
             LEFT JOIN users u ON u.id = a.user_id
             WHERE $whereSql
             ORDER BY a.id DESC
             LIMIT " . (int) $perPage . ' OFFSET ' . (int) (($page - 1) * $perPage),
            $params
        );
        return ['total' => $total, 'logs' => $rows];
    }

    public static function recentForEntity(string $entityType, int $entityId, int $limit = 50): array
    {
        return Database::all(
            'SELECT a.*, u.full_name AS user_name
             FROM audit_logs a
             LEFT JOIN users u ON u.id = a.user_id
             WHERE a.entity_type = ? AND a.entity_id = ?
             ORDER BY a.id DESC
             LIMIT ' . (int) $limit,
            [$entityType, $entityId]
        );
    }
}
