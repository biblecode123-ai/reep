<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Gate;
use App\Core\Session;
use App\Models\AgreementModel;
use App\Models\ApplicationModel;
use App\Services\NotificationService;
use App\Services\NumberingService;

/**
 * Official research agreement lifecycle (spec §13, §33–§37; Rules 10–13).
 *
 *  - The Research Directorate prepares and issues the agreement (research.manage).
 *  - The researcher views and signs it (own application only).
 *  - Re-issuing creates a NEW version; previous versions are preserved (Rule 12).
 *  - A signed agreement is required before the research becomes ACTIVE (Rule 11).
 *  - The agreed amount can never exceed the approved allocation (Rule 13).
 */
final class AgreementController
{
    /** Directorate: prepare + issue the agreement (optionally a new version). */
    public static function issue(): void
    {
        Gate::authorize('research.manage');
        Csrf::verifyRequest();
        $appId = (int) ($_POST['application_id'] ?? 0);
        $app = ApplicationModel::find($appId);
        if ($app === null) {
            Session::flash('error', 'Application not found.');
            redirect('index.php?action=applications');
        }
        if (!in_array($app['status_code'], ['BUDGET_APPROVED', 'FUNDED'], true)) {
            Session::flash('error', 'Agreements are issued after budget approval (current: ' . status_label($app['status_code']) . ').');
            redirect('index.php?action=application_view&id=' . $appId);
        }
        if (AgreementModel::signed($appId) !== null) {
            Session::flash('error', 'This research agreement has already been signed.');
            redirect('index.php?action=application_view&id=' . $appId);
        }

        $terms = trim((string) ($_POST['terms'] ?? ''));
        if ($terms === '') {
            Session::flash('error', 'Agreement terms are required.');
            redirect('index.php?action=application_view&id=' . $appId);
        }
        $title = trim((string) ($_POST['title'] ?? '')) ?: ('Research Agreement — ' . $app['title']);

        // Rule 13: never above the approved allocation (or requested amount
        // when no separate allocation was recorded).
        $cap = $app['budget_approved_amount'] !== null ? (float) $app['budget_approved_amount'] : (float) $app['budget_requested'];
        $amount = $cap;
        if (($_POST['agreed_amount'] ?? '') !== '') {
            if (!is_numeric($_POST['agreed_amount']) || (float) $_POST['agreed_amount'] <= 0) {
                Session::flash('error', 'Agreed amount must be a positive number.');
                redirect('index.php?action=application_view&id=' . $appId);
            }
            $amount = (float) $_POST['agreed_amount'];
        }
        if ($amount > $cap + 0.001) {
            Session::flash('error', sprintf('Agreed amount %.2f exceeds the approved allocation %.2f (Rule 13).', $amount, $cap));
            redirect('index.php?action=application_view&id=' . $appId);
        }

        $agreementId = Database::transaction(function () use ($app, $appId, $terms, $title, $amount): int {
            // Preserve prior issued version (Rule 12): it becomes superseded
            // once the new version is issued.
            $previous = AgreementModel::latest($appId);
            if ($previous !== null && $previous['status'] === 'issued') {
                AgreementModel::update((int) $previous['id'], ['status' => 'superseded']);
            }
            $previousId = $previous !== null ? (int) $previous['id'] : null;
            return AgreementModel::create([
                'agreement_number'    => NumberingService::next('research_agreement'),
                'application_id'      => $appId,
                'version'             => AgreementModel::nextVersion($appId),
                'status'              => 'issued',
                'title'               => $title,
                'terms'               => $terms,
                'agreed_amount'       => $amount,
                'previous_version_id' => $previousId,
                'prepared_by'         => Auth::id(),
                'prepared_at'         => date('Y-m-d H:i:s'),
                'issued_by'           => Auth::id(),
                'issued_at'           => date('Y-m-d H:i:s'),
            ]);
        });

        Audit::log('agreement.issued', 'research_application', $appId, null,
            ['agreement' => $agreementId, 'amount' => $amount]);
        NotificationService::send((int) $app['pi_user_id'], 'agreement_issued', 'Research agreement issued',
            'Please review and sign the agreement for: ' . $app['title'],
            'index.php?action=application_view&id=' . $appId, 'high');
        Session::flash('success', 'Research agreement issued to the researcher.');
        redirect('index.php?action=application_view&id=' . $appId);
    }

    /** Researcher: sign the issued agreement (own application only). */
    public static function sign(): void
    {
        Gate::authorize('research.submit');
        Csrf::verifyRequest();
        $agreementId = (int) ($_POST['agreement_id'] ?? 0);
        $agreement = AgreementModel::find($agreementId);
        if ($agreement === null) {
            Session::flash('error', 'Agreement not found.');
            redirect('index.php?action=applications');
        }
        $appId = (int) $agreement['application_id'];
        $app = ApplicationModel::find($appId);
        // Entity-level authorization (Rule 5): only the principal investigator.
        if ($app === null || (int) $app['pi_user_id'] !== Auth::id()) {
            Session::flash('error', 'You can only sign the agreement of your own research.');
            redirect('index.php?action=applications');
        }
        if ($agreement['status'] !== 'issued') {
            Session::flash('error', 'This agreement version is not awaiting signature (status: ' . $agreement['status'] . ').');
            redirect('index.php?action=application_view&id=' . $appId);
        }
        if (!in_array($app['status_code'], ['BUDGET_APPROVED', 'FUNDED'], true)) {
            Session::flash('error', 'The agreement cannot be signed at this stage.');
            redirect('index.php?action=application_view&id=' . $appId);
        }
        $signatureName = trim((string) ($_POST['signature_name'] ?? ''));
        if ($signatureName === '') {
            Session::flash('error', 'Type your full name as the signature.');
            redirect('index.php?action=application_view&id=' . $appId);
        }

        AgreementModel::update($agreementId, [
            'status'        => 'signed',
            'signed_by'     => Auth::id(),
            'signed_at'     => date('Y-m-d H:i:s'),
            'signature_name' => $signatureName,
            'signature_note' => trim((string) ($_POST['signature_note'] ?? '')) ?: null,
        ]);
        Audit::log('agreement.signed', 'research_application', $appId, null,
            ['agreement' => $agreementId, 'signature' => $signatureName]);
        NotificationService::sendToRoles(['rpee_director', 'rpee_officer'], 'agreement_signed',
            'Research agreement signed', $app['title'],
            'index.php?action=application_view&id=' . $appId, 'high');
        Session::flash('success', 'Agreement signed. The research can now be activated.');
        redirect('index.php?action=application_view&id=' . $appId);
    }
}

