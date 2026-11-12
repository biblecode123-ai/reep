<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\EvaluationModel;

/**
 * Centralized service for evaluator management, assignment, and completion logic.
 */
final class EvaluatorAssignmentService
{
    /**
     * Assigns an evaluator to an application.
     * Enforces concurrency-safe capacity check and conflict validation.
     */
    public static function assign(int $applicationId, int $evaluatorUserId, int $assignedBy, ?string $deadline, string $level = 'college'): int
    {
        return Database::transaction(function () use ($applicationId, $evaluatorUserId, $assignedBy, $deadline, $level) {
            // 1. Check eligibility and conflicts
            if (!self::isEligible($applicationId, $evaluatorUserId)) {
                throw new \RuntimeException('Evaluator is ineligible for this assignment.');
            }

            // 2. Check Capacity (atomic check within transaction)
            if (self::isAtCapacity($evaluatorUserId)) {
                throw new \RuntimeException('Evaluator has reached maximum concurrent assignments.');
            }

            // 3. Perform assignment
            return EvaluationModel::assign($applicationId, $evaluatorUserId, $assignedBy, $deadline, $level);
        });
    }

    /**
     * Checks if evaluator is at capacity.
     */
    public static function isAtCapacity(int $evaluatorUserId): bool
    {
        $maxCapacity = (int) Database::scalar(
            'SELECT max_concurrent_evaluations FROM research_evaluators WHERE user_id = ?',
            [$evaluatorUserId]
        ) ?: 5; // Default fallback if not configured

        $current = EvaluationModel::workloadFor($evaluatorUserId);
        return $current >= $maxCapacity;
    }

    /**
     * Validates evaluator eligibility.
     */
    public static function isEligible(int $applicationId, int $evaluatorUserId): bool
    {
        // Check if evaluator has conflict
        if (EvaluationModel::detectConflicts($applicationId, $evaluatorUserId) !== null) {
            return false;
        }

        // Additional checks: PI/Co-I
        $isMember = (int) Database::scalar(
            'SELECT COUNT(*) FROM application_team_members WHERE application_id = ? AND user_id = ? AND status = "accepted"',
            [$applicationId, $evaluatorUserId]
        );
        if ($isMember > 0) {
            return false;
        }

        return true;
    }

    /**
     * Calculates if evaluations are complete for the given application.
     */
    public static function isComplete(int $applicationId): bool
    {
        $app = Database::one('SELECT call_id FROM research_applications WHERE id = ?', [$applicationId]);
        if (!$app) return false;

        $required = (int) Database::scalar(
            'SELECT COALESCE(required_evaluators, 2) FROM research_calls WHERE id = ?',
            [(int) $app['call_id']]
        );

        $completed = (int) Database::scalar(
            'SELECT COUNT(*) FROM evaluations WHERE application_id = ? AND status = "submitted"',
            [$applicationId]
        );

        return $completed >= $required;
    }
}
