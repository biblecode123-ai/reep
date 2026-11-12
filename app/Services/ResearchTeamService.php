<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\InvitationModel;

final class ResearchTeamService
{
    /**
     * Check if a researcher can participate in a new proposal/project.
     * PI: 1 active, Co-I: 3 active.
     */
    public static function validateParticipationLimit(int $userId, string $role): bool
    {
        if ($role === 'principal_investigator') {
            $limit = 1;
            $count = (int) Database::scalar(
                'SELECT COUNT(*) FROM research_applications WHERE pi_user_id = ? AND status_code NOT IN ("REJECTED", "WITHDRAWN", "COMPLETED")',
                [$userId]
            );
            $count += (int) Database::scalar(
                'SELECT COUNT(*) FROM research_projects WHERE pi_user_id = ? AND status_code NOT IN ("COMPLETED", "TERMINATED")',
                [$userId]
            );
        } else {
            $limit = 3;
            $count = (int) Database::scalar(
                'SELECT COUNT(*) FROM application_team_members WHERE user_id = ? AND status = "accepted"',
                [$userId]
            );
            $count += (int) Database::scalar(
                'SELECT COUNT(*) FROM research_project_members WHERE user_id = ? AND status = "active"',
                [$userId]
            );
        }

        return $count < $limit;
    }

    public static function invite(int $applicationId, int $invitedUserId, int $invitingUserId): array
    {
        if (!self::validateParticipationLimit($invitedUserId, 'co_investigator')) {
            return [false, 'Researcher has reached the co-investigator participation limit.'];
        }

        // Add invitation...
        return [true, 'Invitation sent.'];
    }
}
