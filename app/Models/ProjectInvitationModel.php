<?php declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class ProjectInvitationModel {
    public static function create(int $projectId, int $researcherId, string $role): int {
        return Database::insert('project_invitations', [
            'project_id' => $projectId,
            'researcher_id' => $researcherId,
            'role' => $role,
            'status' => 'pending',
        ]);
    }

    public static function find(int $id): ?array {
        return Database::one('SELECT * FROM project_invitations WHERE id = ?', [$id]);
    }

    public static function updateStatus(int $id, string $status): void {
        Database::update('project_invitations', [
            'status' => $status,
            'responded_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);
    }

    public static function forProject(int $projectId): array {
        return Database::all(
            'SELECT i.*, u.full_name, u.email FROM project_invitations i JOIN users u ON u.id = i.researcher_id WHERE i.project_id = ?',
            [$projectId]
        );
    }
}

