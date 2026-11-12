<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Gate;
use App\Core\Session;
use App\Models\ApplicationModel;

final class AppealController
{
    public static function submit(): void
    {
        Gate::authorize('appeal.submit');
        Csrf::verifyRequest();
        
        $appId = (int) ($_POST['application_id'] ?? 0);
        $type = (string) ($_POST['appeal_type'] ?? '');
        $grounds = trim((string) ($_POST['grounds'] ?? ''));

        // Basic validation
        if (!in_array($type, ['budget', 'rejection'], true)) {
            Session::flash('error', 'Invalid appeal type.');
            redirect('index.php?action=application_view&id=' . $appId);
        }

        // Add logic to check appeal eligibility (e.g., must be finalized, etc.)
        // This is a placeholder for the logic mentioned in requirements.
        
        Database::insert('research_appeals', [
            'application_id' => $appId,
            'appeal_type' => $type,
            'grounds' => $grounds,
            'status' => 'pending'
        ]);

        Session::flash('success', 'Appeal submitted successfully.');
        redirect('index.php?action=application_view&id=' . $appId);
    }
}
