<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Gate;
use App\Core\Session;
use App\Models\ApplicationModel;

final class DecisionController
{
    public static function finalizeDecision(): void
    {
        Gate::authorize('research.manage');
        Csrf::verifyRequest();
        
        $appId = (int) ($_POST['application_id'] ?? 0);
        $decision = (string) ($_POST['decision'] ?? ''); // 'accepted', 'not_accepted', 'further_review'
        $justification = trim((string) ($_POST['justification'] ?? ''));
        
        if (!in_array($decision, ['accepted', 'not_accepted', 'further_review'], true)) {
            Session::flash('error', 'Invalid decision.');
            redirect('index.php?action=application_view&id=' . $appId);
        }

        Database::transaction(function () use ($appId, $decision, $justification) {
            Database::insert('university_decisions', [
                'application_id' => $appId,
                'decision'       => $decision,
                'justification'  => $justification,
                'decided_by'     => Auth::id(),
                'decided_at'     => date('Y-m-d H:i:s')
            ]);
            
            // Update application status
            Database::update('research_applications', ['status_code' => strtoupper($decision)], 'id = ?', [$appId]);
        });
        
        Session::flash('success', 'Official university decision recorded: ' . ucfirst($decision));
        redirect('index.php?action=application_view&id=' . $appId);
    }
}

