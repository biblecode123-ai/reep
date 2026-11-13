<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/** Service to manage Research Proposal Evaluation Criteria Rubrics and Versions. */
final class EvaluationRubricService
{
    /** Creates a new rubric master record. */
    public static function createRubric(string $name, ?string $description): int
    {
        return Database::insert('evaluation_rubrics', [
            'name' => $name,
            'description' => $description,
        ]);
    }

    /** Creates a new version for an existing rubric. */
    public static function createVersion(int $rubricId, int $versionNumber, int $userId): int
    {
        return Database::insert('evaluation_rubric_versions', [
            'rubric_id' => $rubricId,
            'version_number' => $versionNumber,
            'status' => 'draft',
            'created_by' => $userId,
        ]);
    }

    /** Publishes a rubric version, validating the total score of 100. */
    public static function publishVersion(int $versionId): bool
    {
        // 1. Calculate total points
        $total = self::calculateTotalPoints($versionId);
        
        if ($total !== 100.0) {
            return false;
        }

        Database::run('UPDATE evaluation_rubric_versions SET status = "published", published_at = NOW() WHERE id = ?', [$versionId]);
        return true;
    }

    /** Calculates total points for a rubric version. */
    public static function calculateTotalPoints(int $versionId): float
    {
        $rows = Database::all(
            'SELECT weight_pct FROM evaluation_criteria WHERE rubric_version_id = ? AND parent_criterion_id IS NULL AND is_active = 1',
            [$versionId]
        );
        
        $total = 0.0;
        foreach ($rows as $row) {
            $total += (float) $row['weight_pct'];
        }
        return $total;
    }
}
