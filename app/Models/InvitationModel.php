<?php declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class InvitationModel {
    public static function create(int $applicationId, int $researcherId, string $role): int {
        return Database::insert('research_application_invitations', [
            'application_id' => $applicationId,
            'researcher_id' => $researcherId,
            'role' => $role,
            'status' => 'pending',
        ]);
    }

    public static function find(int $id): ?array {
        return Database::one('SELECT * FROM research_application_invitations WHERE id = ?', [$id]);
    }

    public static function updateStatus(int $id, string $status): void {
        Database::update('research_application_invitations', [
            'status' => $status,
            'responded_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);
    }

    public static function forApplication(int $applicationId): array {
        return Database::all(
            'SELECT i.*, u.full_name, u.email FROM research_application_invitations i JOIN users u ON u.id = i.researcher_id WHERE i.application_id = ?',
            [$applicationId]
        );
    }
}
