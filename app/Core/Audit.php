<?php
declare(strict_types=1);

namespace App\Core;

use App\Models\AuditLogModel;

/**
 * Institutional audit trail (spec §65; Rules 7 & 9).
 * Write-only from the application; no UI edits these rows.
 */
final class Audit
{
    public static function log(
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $old = null,
        ?array $new = null,
        ?string $comment = null
    ): void {
        AuditLogModel::log(Auth::id(), $action, $entityType, $entityId, $old, $new, $comment);
    }
}

