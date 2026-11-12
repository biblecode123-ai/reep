<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class FinalReportModel
{
    public static function create(array $data): int
    {
        return Database::insert('project_final_reports', $data);
    }

    public static function find(int $id): ?array
    {
        return Database::one(
            'SELECT fr.*, p.project_number, p.title AS project_title, p.pi_user_id, p.college_id,
                    p.status_code AS project_status, p.actual_start, p.actual_end,
                    d.title AS document_title, d.version AS document_version,
                    u.full_name AS submitter_name
             FROM project_final_reports fr
             JOIN research_projects p ON p.id = fr.project_id
             JOIN documents d ON d.id = fr.document_id
             JOIN users u ON u.id = fr.submitted_by
             WHERE fr.id = ?',
            [$id]
        );
    }

    public static function latestForProject(int $projectId): ?array
    {
        return Database::one(
            'SELECT * FROM project_final_reports WHERE project_id = ? ORDER BY version DESC LIMIT 1',
            [$projectId]
        );
    }

    public static function assignments(int $reportId, ?string $level = null): array
    {
        $sql = 'SELECT pea.*, u.full_name AS evaluator_name, u.email AS evaluator_email
                FROM project_evaluator_assignments pea
                JOIN users u ON u.id = pea.evaluator_user_id
                WHERE pea.final_report_id = ?';
        $params = [$reportId];
        if ($level !== null) {
            $sql .= ' AND pea.assignment_level = ?';
            $params[] = $level;
        }
        $sql .= ' ORDER BY pea.assignment_level, pea.assigned_at, pea.id';
        return Database::all($sql, $params);
    }

    public static function assignmentForUser(int $reportId, int $userId, ?string $level = null): ?array
    {
        $sql = 'SELECT * FROM project_evaluator_assignments
                WHERE final_report_id = ? AND evaluator_user_id = ? AND is_active = 1';
        $params = [$reportId, $userId];
        if ($level !== null) {
            $sql .= ' AND assignment_level = ?';
            $params[] = $level;
        }
        $sql .= ' ORDER BY id DESC LIMIT 1';
        return Database::one($sql, $params);
    }

    public static function assign(int $reportId, int $userId, string $level, int $assignedBy, ?string $deadline): int
    {
        return Database::insert('project_evaluator_assignments', [
            'final_report_id' => $reportId,
            'evaluator_user_id' => $userId,
            'assignment_level' => $level,
            'assigned_by' => $assignedBy,
            'deadline' => $deadline,
            'status' => 'assigned',
            'is_active' => 1,
        ]);
    }

    public static function updateAssignment(int $id, array $data): void
    {
        Database::update('project_evaluator_assignments', $data, 'id = ?', [$id]);
    }

    public static function evaluationForAssignment(int $assignmentId): ?array
    {
        return Database::one(
            'SELECT * FROM project_evaluations WHERE assignment_id = ? ORDER BY evaluation_round DESC LIMIT 1',
            [$assignmentId]
        );
    }

    public static function createEvaluation(array $data): int
    {
        return Database::insert('project_evaluations', $data);
    }

    public static function updateEvaluation(int $id, array $data): void
    {
        Database::update('project_evaluations', $data, 'id = ?', [$id]);
    }

    public static function replaceIndicatorScores(int $evaluationId, array $scores): void
    {
        Database::run('DELETE FROM project_evaluation_indicator_scores WHERE project_evaluation_id = ?', [$evaluationId]);
        foreach ($scores as $score) {
            Database::insert('project_evaluation_indicator_scores', [
                'project_evaluation_id' => $evaluationId,
                'indicator_id' => (int) $score['indicator_id'],
                'score' => (float) $score['score'],
                'comment' => $score['comment'] ?? null,
            ]);
        }
    }

    public static function indicatorScores(int $evaluationId): array
    {
        $rows = Database::all(
            'SELECT indicator_id, score, comment FROM project_evaluation_indicator_scores WHERE project_evaluation_id = ?',
            [$evaluationId]
        );
        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['indicator_id']] = ['score' => $row['score'], 'comment' => $row['comment']];
        }
        return $result;
    }

    public static function average(int $reportId, string $level): ?float
    {
        $value = Database::scalar(
            'SELECT AVG(pe.weighted_score)
             FROM project_evaluations pe
             JOIN project_evaluator_assignments pea ON pea.id = pe.assignment_id
             WHERE pe.final_report_id = ? AND pea.assignment_level = ?
               AND pea.is_active = 1 AND pea.status = "completed" AND pe.status = "submitted"',
            [$reportId, $level]
        );
        return $value === null ? null : (float) $value;
    }

    public static function assignmentCounts(int $reportId, string $level): array
    {
        $required = (int) Database::scalar(
            'SELECT COUNT(*) FROM project_evaluator_assignments
             WHERE final_report_id = ? AND assignment_level = ? AND is_active = 1
               AND status NOT IN ("cancelled","declined","conflict_declared")',
            [$reportId, $level]
        );
        $completed = (int) Database::scalar(
            'SELECT COUNT(*) FROM project_evaluator_assignments
             WHERE final_report_id = ? AND assignment_level = ? AND is_active = 1 AND status = "completed"',
            [$reportId, $level]
        );
        return ['required' => $required, 'completed' => $completed];
    }

    public static function recordDecision(int $reportId, string $stage, string $decision, ?string $comments, int $userId): int
    {
        return Database::insert('project_final_report_decisions', [
            'final_report_id' => $reportId,
            'stage' => $stage,
            'decision' => $decision,
            'comments' => $comments,
            'decided_by' => $userId,
            'decided_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
