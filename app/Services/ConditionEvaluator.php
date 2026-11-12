<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Settings;

/**
 * Resolves workflow transition conditions (spec §96).
 * Each condition returns [ok(bool), error(string|null)].
 */
final class ConditionEvaluator
{
    public static function check(string $key, string $entityType, int $entityId, array $entity): array
    {
        return match ($key) {
            'documents_complete'      => self::documentsComplete($entityType, $entityId),
            'evaluations_passed'      => self::evaluationsPassed($entityType, $entityId),
            'univ_evaluations_complete'=> self::univEvaluationsComplete($entityType, $entityId),
            'ethics_required'         => self::ethicsRequired($entity),
            'ethics_cleared'          => self::ethicsCleared($entityType, $entityId, $entity),
            'budget_required'         => self::budgetRequired($entity),
            'coi_cleared'             => self::coiCleared($entityType, $entityId),
            'final_docs_complete'     => self::finalDocsComplete($entityType, $entityId),
            'funding_released'        => self::fundingReleased($entityType, $entityId),
            'ethics_docs_complete'    => self::ethicsDocsComplete($entityType, $entityId),
            'ethics_reviews_complete' => self::ethicsReviewsComplete($entityType, $entityId),
            'committee_ready'         => self::committeeReady($entityType, $entityId, $entity),
            'committee_approved'      => self::committeeDecision($entityId, ['approve', 'approve_with_conditions'], 'The university research committee has not approved this research yet.'),
            'committee_rejected'      => self::committeeDecision($entityId, ['reject'], 'The university research committee has not recorded a rejection.'),
            'committee_wants_revision'=> self::committeeDecision($entityId, ['revision_required'], 'The university research committee has not requested a revision.'),
            'agreement_signed'        => self::agreementSigned($entityType, $entityId),
            'budget_recorded'         => self::budgetRecorded($entityType, $entityId),
            default                   => [false, 'Unsupported workflow condition: ' . $key . '. Transition blocked until the condition is implemented.'],
        };
    }

    private static function documentsComplete(string $entityType, int $id): array
    {
        if ($entityType !== 'research_application') {
            return [true, null];
        }
        $missing = Database::scalar(
            'SELECT COUNT(*) FROM research_call_requirements r
             JOIN research_applications a ON a.call_id = r.call_id
             WHERE a.id = ? AND r.requirement = "required"
               AND NOT EXISTS (
                 SELECT 1 FROM documents d
                 WHERE d.entity_type = "research_application" AND d.entity_id = a.id
                   AND d.document_type_id = r.document_type_id AND d.status IN ("active","superseded")
               )',
            [$id]
        );
        return $missing > 0
            ? [false, 'Required documents are missing (' . $missing . '). Upload them before continuing.']
            : [true, null];
    }

    private static function evaluationsPassed(string $entityType, int $id): array
    {
        if ($entityType !== 'research_application') {
            return [true, null];
        }
        $avgScore = Database::scalar(
            'SELECT AVG(e.weighted_score) FROM evaluations e
             JOIN evaluator_assignments ea ON ea.id = e.assignment_id
             WHERE e.application_id = ? AND e.status = "submitted"
               AND e.evaluation_type = "proposal" AND ea.assignment_level = "college"',
            [$id]
        );
        $threshold = max(0.0, min(100.0, (float) Settings::get('college_proposal_passing_score', '50')));
        if ($avgScore === null) {
            return [false, 'No completed college proposal evaluations are available.'];
        }
        $score = (float) $avgScore;
        return $score >= $threshold
            ? [true, null]
            : [false, 'College proposal score is below the configured passing threshold (' . $threshold . '/100; current average: ' . round($score, 2) . ').'];
    }

    private static function univEvaluationsComplete(string $entityType, int $id): array
    {
        if ($entityType !== 'research_application') {
            return [true, null];
        }
        $required = (int) Database::scalar(
            'SELECT COUNT(*) FROM evaluator_assignments
             WHERE application_id = ? AND assignment_level = "university"
               AND is_active = 1 AND status NOT IN ("cancelled","declined","conflict_declared")',
            [$id]
        );
        $openConflicts = (int) Database::scalar(
            'SELECT COUNT(*) FROM evaluator_assignments ea
             LEFT JOIN evaluator_conflicts ec ON ec.assignment_id = ea.id AND ec.resolved = "open"
             WHERE ea.application_id = ? AND ea.assignment_level = "university"
               AND ea.is_active = 1
               AND (ea.status = "conflict_declared" OR ec.id IS NOT NULL)',
            [$id]
        );
        if ($openConflicts > 0) {
            return [false, 'University evaluator conflicts must be resolved before this stage can be finalized.'];
        }
        $done = (int) Database::scalar(
            'SELECT COUNT(DISTINCT e.assignment_id)
             FROM evaluations e
             JOIN evaluator_assignments ea ON ea.id = e.assignment_id
             WHERE e.application_id = ? AND e.status = "submitted"
               AND e.evaluation_type = "proposal" AND ea.assignment_level = "university"
               AND ea.is_active = 1 AND ea.status = "completed"',
            [$id]
        );
        return $required > 0 && $done >= $required
            ? [true, null]
            : [false, 'University evaluation submissions are incomplete (' . $done . '/' . $required . ' active assignments completed).'];
    }

    private static function evaluationsComplete(string $entityType, int $id): array
    {
        if ($entityType !== 'research_application') {
            return [true, null];
        }
        $app = Database::one('SELECT call_id, status_code, revision_submitted_at FROM research_applications WHERE id = ?', [$id]);
        $required = (int) (Database::scalar(
            'SELECT required_evaluators FROM research_calls WHERE id = ?', [(int) $app['call_id']]
        ) ?? Settings::int('college_evaluators_default', 1));
        // After a revision only evaluations submitted for the NEW version count,
        // so a stale round-1 evaluation can never satisfy re-evaluation (§25).
        if ($app['revision_submitted_at'] !== null
            && in_array($app['status_code'], ['REVISION_SUBMITTED', 'RE_EVALUATION'], true)) {
            $done = (int) Database::scalar(
                'SELECT COUNT(DISTINCT evaluator_user_id) FROM evaluations
                 WHERE application_id = ? AND status = "submitted" AND submitted_at > ? AND evaluation_type = "proposal"',
                [$id, $app['revision_submitted_at']]
            );
            return $done >= $required
                ? [true, null]
                : [false, 'Re-evaluations of the revised version are not complete (' . $done . '/' . $required . ').'];
        }
        $done = (int) Database::scalar(
            'SELECT COUNT(*) FROM evaluations WHERE application_id = ? AND status = "submitted" AND evaluation_type = "proposal"',
            [$id]
        );
        return $done >= $required
            ? [true, null]
            : [false, 'Required evaluations not complete (' . $done . '/' . $required . ').'];
    }

    private static function ethicsRequired(array $entity): array
    {
        return ($entity['requires_ethics'] ?? 'no') === 'yes'
            ? [true, null]
            : [false, 'Ethics review is not required for this application.'];
    }

    private static function ethicsCleared(string $entityType, int $id, array $entity): array
    {
        if ($entityType !== 'research_application') {
            return [true, null];
        }
        if (($entity['requires_ethics'] ?? 'no') !== 'yes') {
            return [true, null]; // not required → cleared (§93)
        }
        $status = Database::scalar(
            'SELECT status FROM ethics_applications WHERE research_application_id = ?',
            [$id]
        );
        if ($status === null) {
            return [false, 'An ethics application has not been created yet.'];
        }
        return in_array($status, ['approved', 'approved_with_conditions', 'exempt'], true)
            ? [true, null]
            : [false, 'Ethics approval has not been granted yet (current status: ' . $status . ').'];
    }

    private static function budgetRequired(array $entity): array
    {
        return [true, null]; // funding stage always available; skip only if configured 0-budget later
    }

    private static function coiCleared(string $entityType, int $id): array
    {
        if ($entityType !== 'research_application') {
            return [true, null];
        }
        $open = Database::scalar(
            'SELECT COUNT(*) FROM evaluator_assignments a
             LEFT JOIN evaluator_conflicts c ON c.assignment_id = a.id AND c.resolved = "open"
             WHERE a.application_id = ? AND a.is_active = 1
               AND a.status IN ("assigned","conflict_declared")
               AND c.id IS NOT NULL',
            [$id]
        );
        return $open > 0
            ? [false, 'Open conflicts of interest must be resolved (reassign evaluator) first.']
            : [true, null];
    }

    private static function finalDocsComplete(string $entityType, int $id): array
    {
        $has = Database::scalar(
            'SELECT COUNT(*) FROM documents WHERE entity_type = ? AND entity_id = ?
               AND document_type_id = (SELECT id FROM document_types WHERE type_code = "final_report")
               AND status IN ("active","superseded")',
            [$entityType, $id]
        );
        return $has > 0 ? [true, null] : [false, 'A final report document must be uploaded first.'];
    }

    private static function fundingReleased(string $entityType, int $id): array
    {
        if ($entityType !== 'research_project') {
            return [true, null];
        }
        // The university-level funding decision (§35) gates activation; tranche
        // releases to the PI happen after the project exists.
        $fundedAt = Database::scalar(
            'SELECT a.funded_at FROM research_projects p JOIN research_applications a ON a.id = p.application_id WHERE p.id = ?',
            [$id]
        );
        return $fundedAt !== null ? [true, null] : [false, 'University funding must be recorded before activation.'];
    }

    private static function ethicsDocsComplete(string $entityType, int $id): array
    {
        return [true, null]; // document requirements configurable per module later
    }

    /** Committee assignment exists AND ethics (when required) has cleared (§12, §30). */
    private static function committeeReady(string $entityType, int $id, array $entity): array
    {
        if ($entityType !== 'research_application') {
            return [true, null];
        }
        $assigned = Database::scalar(
            'SELECT COUNT(*) FROM application_committee_assignments WHERE application_id = ? AND status = "active"',
            [$id]
        );
        if ($assigned < 1) {
            return [false, 'Assign this research to a university research committee first (§12).'];
        }
        return self::ethicsCleared($entityType, $id, $entity);
    }

    /** Latest committee decision must be one of the allowed outcomes (Rule 8). */
    private static function committeeDecision(int $appId, array $allowed, string $error): array
    {
        $decision = Database::scalar(
            'SELECT decision FROM committee_decisions WHERE application_id = ? ORDER BY id DESC LIMIT 1',
            [$appId]
        );
        if ($decision === null) {
            return [false, 'The university research committee has not recorded a decision yet.'];
        }
        return in_array($decision, $allowed, true)
            ? [true, null]
            : [false, $error . ' (latest decision: ' . $decision . ')'];
    }

    /** The directorate must have recorded the approved allocation (spec §28, Rule 13). */
    private static function budgetRecorded(string $entityType, int $id): array
    {
        if ($entityType !== 'research_application') {
            return [true, null];
        }
        $amount = Database::scalar('SELECT budget_approved_amount FROM research_applications WHERE id = ?', [$id]);
        return $amount !== null
            ? [true, null]
            : [false, 'Record the approved budget allocation first (Research Directorate budget approval).'];
    }

    /** The researcher must have signed the issued research agreement (Rules 10–11). */
    private static function agreementSigned(string $entityType, int $id): array
    {
        if ($entityType !== 'research_application') {
            return [true, null];
        }
        $signed = Database::scalar(
            'SELECT COUNT(*) FROM research_agreements WHERE application_id = ? AND status = "signed"',
            [$id]
        );
        return $signed > 0
            ? [true, null]
            : [false, 'The research agreement must be issued and signed by the researcher before activation (Rule 11).'];
    }

    private static function ethicsReviewsComplete(string $entityType, int $id): array
    {
        $pending = Database::scalar(
            'SELECT COUNT(*) FROM ethics_reviews WHERE ethics_application_id = ? AND status IN ("assigned","accepted")',
            [$id]
        );
        return $pending > 0
            ? [false, 'Assigned reviewers have not completed their reviews.']
            : [true, null];
    }
}
