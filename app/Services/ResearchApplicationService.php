<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Auth;
use App\Core\Audit;
use App\Core\Logger;

final class ResearchApplicationService
{
    /**
     * Create a new research application draft.
     */
    public static function createDraft(array $data): int
    {
        return Database::transaction(function () use ($data) {
            $applicationId = Database::insert('research_applications', [
                'application_number' => NumberingService::generate('research_app'),
                'call_id'            => (int)$data['call_id'],
                'pi_user_id'         => Auth::id(),
                'title'              => $data['title'],
                'college_id'         => (int)$data['college_id'],
                'status_code'        => 'DRAFT',
            ]);

            WorkflowService::initialize('research_application', 'Research Application Lifecycle', $applicationId);
            
            Audit::log('research.application_created', 'research_application', $applicationId, null, ['title' => $data['title']]);
            return (int)$applicationId;
        });
    }

    /**
     * Submit an application (transition from DRAFT to SUBMITTED).
     */
    public static function submit(int $applicationId, string $comment = ''): array
    {
        $app = Database::one('SELECT * FROM research_applications WHERE id = ?', [$applicationId]);
        if (!$app) return [false, 'Application not found.'];
        
        // Ensure user is the PI
        if ((int)$app['pi_user_id'] !== Auth::id()) {
            return [false, 'Unauthorized.'];
        }

        return WorkflowService::fire($app, 'research_application', 'Submit application', $comment);
    }
}
