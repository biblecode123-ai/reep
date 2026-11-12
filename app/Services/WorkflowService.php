<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Gate;
use App\Core\Session;

/**
 * Generic, DB-driven workflow engine (spec §92–§96; Rules 4 & 9).
 * Stages and transitions live in the database; every fired transition is
 * validated (current stage, permission, condition) and recorded in
 * workflow_history + audit_logs inside one transaction.
 */
final class WorkflowService
{
    /**
     * Fire a named transition for an entity.
     * @param array  $entity       Row with id, workflow_id, current_stage_id (+ status_code)
     * @param string $entityType   e.g. research_application
     * @param string $transitionName Transition name as configured (or "*" to allow any outbound)
     * @return array [ok(bool), error(string|null), newStage(array|null)]
     */
    public static function fire(
        array $entity,
        string $entityType,
        string $transitionName,
        ?string $comment = null,
        array $extraData = []
    ): array {
        if (empty($entity['workflow_id']) || empty($entity['current_stage_id'])) {
            return [false, 'Entity is not attached to a workflow.', null];
        }

        $transition = Database::one(
            'SELECT t.*, fs.stage_code AS from_code, ts.stage_code AS to_code, ts.stage_name AS to_name
             FROM workflow_transitions t
             JOIN workflow_stages fs ON fs.id = t.from_stage_id
             JOIN workflow_stages ts ON ts.id = t.to_stage_id
             WHERE t.workflow_id = ? AND t.from_stage_id = ? AND t.is_active = 1
               AND (t.transition_name = ? OR ? = "*")',
            [(int) $entity['workflow_id'], (int) $entity['current_stage_id'], $transitionName, $transitionName]
        );
        if ($transition === null) {
            return [false, 'Transition "' . $transitionName . '" is not available from the current stage.', null];
        }

        // Permission check (transition may be open to any authenticated handler — NULL).
        if (!empty($transition['required_permission'])) {
            if (Gate::denies($transition['required_permission'])) {
                return [false, 'You are not permitted to perform this transition.', null];
            }
        }

        // Condition engine (spec §96) — conditions resolved by ConditionEvaluator.
        if (!empty($transition['condition_key'])) {
            [$ok, $err] = ConditionEvaluator::check($transition['condition_key'], $entityType, (int) $entity['id'], $entity);
            if (!$ok) {
                return [false, $err ?? 'Required conditions are not satisfied.', null];
            }
        }

        Database::transaction(function () use ($entity, $entityType, $transition, $comment, $extraData): void {
            // Update entity pointer + status mirror.
            $table = self::tableFor($entityType);
            Database::update(
                $table,
                ['current_stage_id' => (int) $transition['to_stage_id'], 'status_code' => $transition['to_code']],
                'id = ?',
                [(int) $entity['id']]
            );

            // Entity-specific timestamp hooks (spec §76).
            self::applyTimestampHooks($entityType, (int) $entity['id'], $transition['to_code'], $extraData);

            // Immutable history row (spec §94).
            Database::insert('workflow_history', [
                'workflow_id'   => (int) $entity['workflow_id'],
                'entity_type'   => $entityType,
                'entity_id'     => (int) $entity['id'],
                'from_stage_id' => (int) $entity['current_stage_id'],
                'to_stage_id'   => (int) $transition['to_stage_id'],
                'from_status'   => $entity['status_code'] ?? $transition['from_code'],
                'to_status'     => $transition['to_code'],
                'user_id'       => Auth::id(),
                'comment'       => $comment,
            ]);

            Audit::log(
                'workflow.transition',
                $entityType,
                (int) $entity['id'],
                ['status' => $entity['status_code'] ?? $transition['from_code']],
                ['status' => $transition['to_code']],
                $transition['transition_name'] . ($comment !== null ? ' — ' . $comment : '')
            );
        });

        NotificationService::notifyStageChange($entityType, (int) $entity['id'], $transition);

        return [true, null, ['stage_code' => $transition['to_code'], 'stage_name' => $transition['to_name']]];
    }

    /** Transitions currently available from the entity's stage, for UI buttons. */
    public static function availableTransitions(array $entity, string $entityType): array
    {
        if (empty($entity['workflow_id']) || empty($entity['current_stage_id'])) {
            return [];
        }
        $rows = Database::all(
            'SELECT t.id, t.transition_name, t.required_permission, t.condition_key, ts.stage_code AS to_code, ts.stage_name AS to_name
             FROM workflow_transitions t
             JOIN workflow_stages ts ON ts.id = t.to_stage_id
             WHERE t.workflow_id = ? AND t.from_stage_id = ? AND t.is_active = 1
             ORDER BY t.sort_order',
            [(int) $entity['workflow_id'], (int) $entity['current_stage_id']]
        );
        $out = [];
        foreach ($rows as $t) {
            $permitted = empty($t['required_permission']) || Gate::allows($t['required_permission']);
            $out[] = [
                'id'         => (int) $t['id'],
                'name'       => $t['transition_name'],
                'to_code'    => $t['to_code'],
                'to_name'    => $t['to_name'],
                'permitted'  => $permitted,
                'condition'  => $t['condition_key'],
            ];
        }
        return $out;
    }

    public static function history(string $entityType, int $entityId): array
    {
        return Database::all(
            'SELECT h.*, u.full_name AS user_name, ts.stage_name AS to_stage_name
             FROM workflow_history h
             LEFT JOIN users u ON u.id = h.user_id
             LEFT JOIN workflow_stages ts ON ts.id = h.to_stage_id
             WHERE h.entity_type = ? AND h.entity_id = ?
             ORDER BY h.created_at ASC, h.id ASC',
            [$entityType, $entityId]
        );
    }

    /** Attach an entity to a workflow at its start stage. */
    public static function initialize(string $entityType, string $workflowName, int $entityId): void
    {
        $wf = Database::one('SELECT id FROM workflows WHERE entity_type = ? AND name = ? AND is_active = 1', [$entityType, $workflowName]);
        if ($wf === null) {
            throw new \RuntimeException('Workflow not configured: ' . $workflowName);
        }
        $stage = Database::one(
            'SELECT id, stage_code FROM workflow_stages WHERE workflow_id = ? AND stage_type = "start" ORDER BY sort_order LIMIT 1',
            [(int) $wf['id']]
        );
        if ($stage === null) {
            throw new \RuntimeException('Workflow has no start stage: ' . $workflowName);
        }
        Database::update(
            self::tableFor($entityType),
            ['workflow_id' => (int) $wf['id'], 'current_stage_id' => (int) $stage['id'], 'status_code' => $stage['stage_code']],
            'id = ?',
            [$entityId]
        );
    }

    private static function tableFor(string $entityType): string
    {
        return match ($entityType) {
            'research_application' => 'research_applications',
            'research_project'     => 'research_projects',
            'ethics_application'   => 'ethics_applications',
            'extension_application'=> 'extension_applications',
            default => throw new \RuntimeException('No table mapping for ' . $entityType),
        };
    }

    private static function applyTimestampHooks(string $entityType, int $id, string $toCode, array $extra): void
    {
        if ($entityType !== 'research_application') {
            return;
        }
        $col = match ($toCode) {
            'SUBMITTED'                 => 'submitted_at',
            'COLLEGE_RECEIVED'          => 'college_received_at',
            'EVALUATOR_ASSIGNED'        => 'evaluator_assigned_at',
            'UNDER_EVALUATION'          => 'evaluation_started_at',
            'EVALUATION_COMPLETED'      => 'evaluation_completed_at',
            'REVISION_REQUIRED'         => 'revision_requested_at',
            'REVISION_SUBMITTED'        => 'revision_submitted_at',
            'COLLEGE_APPROVED'          => 'college_approved_at',
            'SUBMITTED_TO_UNIVERSITY'   => 'university_submitted_at',
            'APPROVED'                  => 'university_approved_at',
            'BUDGET_APPROVED'           => 'budget_approved_at',
            'FUNDED'                    => 'funded_at',
            'ACTIVE'                    => 'started_at',
            'PROGRESS_MONITORING'       => 'progress_report_at',
            'FINAL_REPORT_SUBMITTED'    => 'final_submitted_at',
            'PRESENTED'                 => 'presented_at',
            'COMPLETED'                 => 'completed_at',
            'REPOSITORY'                => 'repository_at',
            default                     => null,
        };
        if ($col !== null) {
            // actual_start on the future research_projects row is set by the
            // project-activation service (Phase 10), not on the application.
            Database::update('research_applications', [$col => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        }
    }
}
