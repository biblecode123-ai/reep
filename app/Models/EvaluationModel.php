<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Evaluator assignments, conflicts of interest, evaluations, scores,
 * comments and revision requests (spec §20–§25).
 */
final class EvaluationModel
{
    // ---- Assignments (§20) ----------------------------------------------

    public static function assignmentsFor(int $applicationId): array
    {
        return Database::all(
            'SELECT ea.*, u.full_name AS evaluator_name, u.email AS evaluator_email,
                    d.name AS evaluator_department, c.name AS evaluator_college
             FROM evaluator_assignments ea
             JOIN users u ON u.id = ea.evaluator_user_id
             LEFT JOIN departments d ON d.id = u.department_id
             LEFT JOIN colleges c ON c.id = u.college_id
             WHERE ea.application_id = ?
             ORDER BY ea.assigned_at DESC',
            [$applicationId]
        );
    }

    public static function assignmentById(int $id): ?array
    {
        return Database::one(
            'SELECT ea.*, a.title AS application_title, a.status_code AS application_status
             FROM evaluator_assignments ea JOIN research_applications a ON a.id = ea.application_id
             WHERE ea.id = ?',
            [$id]
        );
    }

    public static function evaluationIdByAssignment(int $assignmentId): ?int
    {
        $id = Database::scalar(
            'SELECT id FROM evaluations WHERE assignment_id = ? AND status = "submitted" ORDER BY id DESC LIMIT 1',
            [$assignmentId]
        );
        return $id === null ? null : (int) $id;
    }

    public static function assign(int $applicationId, int $evaluatorUserId, int $assignedBy, ?string $deadline, string $level = 'college'): int
    {
        // Assignments are stage-specific: the same evaluator may be assigned
        // independently at college and university stages without overwriting history.
        $existing = Database::one(
            'SELECT id, is_active FROM evaluator_assignments
             WHERE application_id = ? AND evaluator_user_id = ? AND assignment_level = ?',
            [$applicationId, $evaluatorUserId, $level]
        );
        if ($existing !== null) {
            Database::update('evaluator_assignments', [
                'is_active' => 1, 'status' => 'assigned', 'assigned_by' => $assignedBy,
                'assigned_at' => date('Y-m-d H:i:s'), 'deadline' => $deadline,
                'assignment_level' => $level,
            ], 'id = ?', [(int) $existing['id']]);
            return (int) $existing['id'];
        }
        return Database::insert('evaluator_assignments', [
            'application_id'    => $applicationId,
            'evaluator_user_id' => $evaluatorUserId,
            'assignment_level'  => $level,
            'assigned_by'       => $assignedBy,
            'assigned_at'       => date('Y-m-d H:i:s'),
            'deadline'          => $deadline,
            'status'            => 'assigned',
            'is_active'         => 1,
        ]);
    }

    public static function setAssignmentStatus(int $id, string $status, ?string $reason = null): void
    {
        $data = ['status' => $status];
        if ($status === 'accepted')       { $data['accepted_at'] = date('Y-m-d H:i:s'); }
        if ($status === 'declined')       { $data['declined_at'] = date('Y-m-d H:i:s'); $data['decline_reason'] = $reason; }
        if ($status === 'conflict_declared') { $data['decline_reason'] = $reason; }
        if ($status === 'completed')      { $data['completed_at'] = date('Y-m-d H:i:s'); }
        if (in_array($status, ['declined', 'cancelled'], true)) { $data['is_active'] = 0; }
        Database::update('evaluator_assignments', $data, 'id = ?', [$id]);
    }

    public static function workloadFor(int $evaluatorUserId): int
    {
        return (int) Database::scalar(
            'SELECT COUNT(*) FROM evaluator_assignments WHERE evaluator_user_id = ? AND status IN ("assigned","accepted") AND is_active = 1',
            [$evaluatorUserId]
        );
    }

    public static function forEvaluator(int $evaluatorUserId, array $filters = []): array
    {
        $sql = 'SELECT ea.*, a.title AS application_title, a.application_number, a.college_id,
                       a.department_id, a.submitted_at, a.pi_user_id,
                       c.name AS college_name, d.name AS department_name,
                       pi.full_name AS pi_name, rc.title AS call_title,
                       (SELECT COUNT(*) FROM evaluations ev
                         WHERE ev.application_id = ea.application_id
                           AND ev.evaluator_user_id = ea.evaluator_user_id
                           AND ev.status = "draft") AS draft_count
                FROM evaluator_assignments ea
                JOIN research_applications a ON a.id = ea.application_id
                JOIN users pi ON pi.id = a.pi_user_id
                LEFT JOIN colleges c ON c.id = a.college_id
                LEFT JOIN departments d ON d.id = a.department_id
                LEFT JOIN research_calls rc ON rc.id = a.call_id
                WHERE ea.evaluator_user_id = ?';
        $params = [$evaluatorUserId];
        $status = $filters['status'] ?? null;
        if ($status !== null && $status !== '') {
            $sql .= ' AND ea.status = ?';
            $params[] = $status;
        }
        $level = $filters['level'] ?? '';
        if ($level !== '') {
            $sql .= ' AND ea.assignment_level = ?';
            $params[] = $level;
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $sql .= ' AND (a.title LIKE ? OR a.application_number LIKE ? OR pi.full_name LIKE ?)';
            $like = '%' . $q . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if (!empty($filters['overdue_only'])) {
            $sql .= ' AND ea.deadline IS NOT NULL AND ea.deadline < ?
                      AND ea.status NOT IN ("completed","declined","cancelled","conflict_declared")';
            $params[] = date('Y-m-d');
        }
        $sql .= ' ORDER BY ea.deadline IS NULL, ea.deadline, ea.assigned_at DESC';
        return Database::all($sql, $params);
    }

    public static function countByStatusForEvaluator(int $evaluatorUserId): array
    {
        $rows = Database::all(
            'SELECT status, COUNT(*) AS cnt FROM evaluator_assignments WHERE evaluator_user_id = ? GROUP BY status',
            [$evaluatorUserId]
        );
        return array_column($rows, 'cnt', 'status');
    }

    /** Assignments past deadline that are still open (not completed/declined). */
    public static function overdueCountForEvaluator(int $evaluatorUserId): int
    {
        return (int) Database::scalar(
            'SELECT COUNT(*) FROM evaluator_assignments
             WHERE evaluator_user_id = ? AND deadline IS NOT NULL AND deadline < ?
               AND status NOT IN ("completed","declined","cancelled","conflict_declared")',
            [$evaluatorUserId, date('Y-m-d')]
        );
    }

    // ---- Conflict of interest (§21, §75) ----------------------------------

    public static function recordConflict(int $assignmentId, ?int $userId, string $type, ?string $description, ?string $detectedReason): int
    {
        $id = Database::insert('evaluator_conflicts', [
            'assignment_id'    => $assignmentId,
            'conflict_type'    => $type,
            'description'      => $description,
            'detected_reason'  => $detectedReason,
            'declared_by'      => $userId,
            'resolved'         => 'open',
        ]);
        return $id;
    }

    public static function openConflictsForApplication(int $applicationId): array
    {
        return Database::all(
            'SELECT c.*, ea.evaluator_user_id, u.full_name AS evaluator_name
             FROM evaluator_conflicts c
             JOIN evaluator_assignments ea ON ea.id = c.assignment_id
             LEFT JOIN users u ON u.id = ea.evaluator_user_id
             JOIN research_applications a ON a.id = ea.application_id
             WHERE a.id = ? AND c.resolved = "open"',
            [$applicationId]
        );
    }

    /** System-detected conflict warnings (§75) — informational, coordinator decides. */
    public static function detectConflicts(int $applicationId, int $evaluatorUserId): ?string
    {
        $app = Database::one('SELECT pi_user_id, department_id, college_id FROM research_applications WHERE id = ?', [$applicationId]);
        if ($app === null) {
            return null;
        }
        $ev = Database::one('SELECT department_id, college_id FROM users WHERE id = ?', [$evaluatorUserId]);
        if ($ev === null) {
            return null;
        }
        $reasons = [];
        if ((int) $app['pi_user_id'] === $evaluatorUserId) {
            $reasons[] = 'evaluator is the principal investigator of this application';
        }
        $sameDept = Database::scalar(
            'SELECT COUNT(*) FROM research_project_members WHERE user_id = ? AND status = "active"
             AND project_id IN (SELECT id FROM research_projects WHERE application_id = ?)',
            [$evaluatorUserId, $applicationId]
        );
        if ($sameDept > 0) {
            $reasons[] = 'evaluator is a member of this research team';
        }
        if ((int) $app['department_id'] > 0 && (int) $ev['department_id'] === (int) $app['department_id']) {
            $reasons[] = 'evaluator is in the same department as the application';
        }
        return $reasons ? implode('; ', $reasons) : null;
    }

    // ---- Evaluations (§22) ------------------------------------------------

    public static function evaluationsFor(int $applicationId, ?int $round = null): array
    {
        $sql = 'SELECT e.*, u.full_name AS evaluator_name
                FROM evaluations e JOIN users u ON u.id = e.evaluator_user_id
                WHERE e.application_id = ?';
        $params = [$applicationId];
        if ($round !== null) {
            $sql .= ' AND e.evaluation_round = ?';
            $params[] = $round;
        }
        $sql .= ' ORDER BY e.evaluation_round, e.id';
        return Database::all($sql, $params);
    }

    public static function evaluationById(int $id): ?array
    {
        return Database::one(
            'SELECT e.*, u.full_name AS evaluator_name, a.title AS application_title
             FROM evaluations e
             JOIN users u ON u.id = e.evaluator_user_id
             JOIN research_applications a ON a.id = e.application_id
             WHERE e.id = ?',
            [$id]
        );
    }

    public static function findDraft(int $applicationId, int $evaluatorUserId, int $round): ?array
    {
        return Database::one(
            'SELECT * FROM evaluations WHERE application_id = ? AND evaluator_user_id = ? AND evaluation_round = ? AND status = "draft"',
            [$applicationId, $evaluatorUserId, $round]
        );
    }

    /**
     * Round for the next evaluation: submitted evaluations define completed
     * rounds, so a saved draft is reused instead of spawning a new round on
     * every auto-save (§6: drafts save, submission must not duplicate).
     */
    public static function nextRound(int $applicationId, int $evaluatorUserId): int
    {
        $v = Database::scalar(
            'SELECT MAX(evaluation_round) FROM evaluations
             WHERE application_id = ? AND evaluator_user_id = ? AND status = "submitted"',
            [$applicationId, $evaluatorUserId]
        );
        return ((int) $v) + 1;
    }

    public static function createEvaluation(array $data): int
    {
        // Must include rubric_template_version_id
        return Database::insert('evaluations', $data);
    }

    public static function updateEvaluation(int $id, array $data): void
    {
        Database::update('evaluations', $data, 'id = ?', [$id]);
    }

    public static function setScore(int $evaluationId, int $criterionId, float $score, ?string $comment): void
    {
        Database::upsert(
            'evaluation_scores',
            ['evaluation_id' => $evaluationId, 'criterion_id' => $criterionId, 'score' => $score, 'comment' => $comment],
            ['evaluation_id', 'criterion_id'],
            ['score' => 'score', 'comment' => 'comment']
        );
    }

    public static function scores(int $evaluationId): array
    {
        return Database::all(
            'SELECT s.*, cc.criterion_name, cc.weight_pct
             FROM evaluation_scores s JOIN research_call_criteria cc ON cc.id = s.criterion_id
             WHERE s.evaluation_id = ? ORDER BY cc.sort_order',
            [$evaluationId]
        );
    }

    public static function addComment(int $evaluationId, string $comment, string $visibility, ?int $userId): void
    {
        Database::insert('evaluation_comments', [
            'evaluation_id' => $evaluationId,
            'visibility'    => $visibility === 'committee_only' ? 'committee_only' : 'to_researcher',
            'comment'       => $comment,
            'created_by'    => $userId,
        ]);
    }

    public static function commentsFor(int $evaluationId, ?string $visibility = null): array
    {
        $sql = 'SELECT ec.*, u.full_name AS author_name FROM evaluation_comments ec
                LEFT JOIN users u ON u.id = ec.created_by WHERE ec.evaluation_id = ?';
        $params = [$evaluationId];
        if ($visibility !== null) {
            $sql .= ' AND ec.visibility = ?';
            $params[] = $visibility;
        }
        return Database::all($sql . ' ORDER BY ec.id', $params);
    }

    // ---- Revisions (§25) ---------------------------------------------------

    public static function revisionRequests(int $applicationId): array
    {
        return Database::all(
            'SELECT rr.*, u.full_name AS requested_by_name
             FROM revision_requests rr LEFT JOIN users u ON u.id = rr.requested_by
             WHERE rr.application_id = ? ORDER BY rr.id DESC',
            [$applicationId]
        );
    }

    public static function revisionRequestById(int $id): ?array
    {
        return Database::one('SELECT * FROM revision_requests WHERE id = ?', [$id]);
    }

    public static function createRevisionRequest(int $applicationId, int $requestedBy, string $reason, ?string $deadline): int
    {
        return Database::insert('revision_requests', [
            'application_id' => $applicationId,
            'requested_by'   => $requestedBy,
            'request_reason' => $reason,
            'deadline'       => $deadline,
            'status'         => 'open',
            'requested_at'   => date('Y-m-d H:i:s'),
        ]);
    }

    public static function addRevisionResponse(int $revisionRequestId, ?int $commentId, string $text, int $userId): void
    {
        Database::insert('revision_responses', [
            'revision_request_id' => $revisionRequestId,
            'comment_id'          => $commentId,
            'response_text'       => $text,
            'submitted_by'        => $userId,
            'submitted_at'        => date('Y-m-d H:i:s'),
        ]);
    }

    public static function revisionResponses(int $revisionRequestId): array
    {
        return Database::all(
            'SELECT rresp.*, u.full_name AS respondent_name, ec.comment AS commented_on
             FROM revision_responses rresp
             LEFT JOIN users u ON u.id = rresp.submitted_by
             LEFT JOIN evaluation_comments ec ON ec.id = rresp.comment_id
             WHERE rresp.revision_request_id = ? ORDER BY rresp.id',
            [$revisionRequestId]
        );
    }

    public static function closeRevisionRequest(int $id, string $status = 'responded'): void
    {
        $data = ['status' => $status, 'responded_at' => date('Y-m-d H:i:s')];
        if ($status === 'closed') {
            $data['closed_at'] = date('Y-m-d H:i:s');
        }
        Database::update('revision_requests', $data, 'id = ?', [$id]);
    }
}
