<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * University research committee assignments and decisions (spec §12).
 * The committee recommendation (Rule 8) and its funding recommendation
 * (Rule 9) are stored separately from the Research Directorate's approval
 * and from the directorate-approved allocation
 * (research_applications.budget_approved_amount).
 */
final class CommitteeModel
{
    // ---- Assignments ------------------------------------------------------

    /** Active committee assignment for an application (with committee info). */
    public static function activeAssignment(int $applicationId): ?array
    {
        return Database::one(
            'SELECT a.*, c.name AS committee_name, c.code AS committee_code, c.committee_type,
                    u.full_name AS assigned_by_name
             FROM application_committee_assignments a
             JOIN committees c ON c.id = a.committee_id
             LEFT JOIN users u ON u.id = a.assigned_by
             WHERE a.application_id = ? AND a.status = "active"
             ORDER BY a.id DESC LIMIT 1',
            [$applicationId]
        );
    }

    public static function assignmentById(int $id): ?array
    {
        return Database::one(
            'SELECT a.*, c.name AS committee_name FROM application_committee_assignments a
             JOIN committees c ON c.id = a.committee_id WHERE a.id = ?',
            [$id]
        );
    }

    public static function assign(int $applicationId, int $committeeId, int $assignedBy, ?string $meetingDate, ?string $notes): int
    {
        $existing = Database::one(
            'SELECT id FROM application_committee_assignments WHERE application_id = ? AND status = "active"',
            [$applicationId]
        );
        if ($existing !== null) {
            return (int) $existing['id'];
        }
        return Database::insert('application_committee_assignments', [
            'application_id' => $applicationId,
            'committee_id'   => $committeeId,
            'assigned_by'    => $assignedBy,
            'assigned_at'    => date('Y-m-d H:i:s'),
            'meeting_date'   => $meetingDate ?: null,
            'notes'          => $notes ?: null,
            'status'         => 'active',
        ]);
    }

    // ---- Decisions --------------------------------------------------------

    /** Latest committee decision for an application. */
    public static function latestDecision(int $applicationId): ?array
    {
        return Database::one(
            'SELECT d.*, c.name AS committee_name, u.full_name AS decided_by_name
             FROM committee_decisions d
             JOIN committees c ON c.id = d.committee_id
             LEFT JOIN users u ON u.id = d.decided_by
             WHERE d.application_id = ?
             ORDER BY d.id DESC LIMIT 1',
            [$applicationId]
        );
    }

    public static function decisionsFor(int $applicationId): array
    {
        return Database::all(
            'SELECT d.*, c.name AS committee_name, u.full_name AS decided_by_name
             FROM committee_decisions d
             JOIN committees c ON c.id = d.committee_id
             LEFT JOIN users u ON u.id = d.decided_by
             WHERE d.application_id = ?
             ORDER BY d.id ASC',
            [$applicationId]
        );
    }

    public static function recordDecision(array $data): int
    {
        return Database::insert('committee_decisions', $data);
    }

    // ---- Membership (entity-level authorization, Rule 5) ------------------

    /** Is the user an active member of the committee? */
    public static function isMember(int $committeeId, int $userId): bool
    {
        return (int) Database::scalar(
            'SELECT COUNT(*) FROM committee_members
             WHERE committee_id = ? AND user_id = ? AND status = "active"
               AND (appointed_from IS NULL OR appointed_from <= ?)
               AND (appointed_to IS NULL OR appointed_to >= ?)',
            [$committeeId, $userId, date('Y-m-d'), date('Y-m-d')]
        ) > 0;
    }

    public static function members(int $committeeId): array
    {
        return Database::all(
            'SELECT m.*, u.full_name FROM committee_members m JOIN users u ON u.id = m.user_id
             WHERE m.committee_id = ? AND m.status = "active" ORDER BY m.member_role, u.full_name',
            [$committeeId]
        );
    }

    /** Applications assigned to committees where the user is an active member. */
    public static function queueForUser(int $userId): array
    {
        return Database::all(
            'SELECT a.id, a.application_number, a.title, a.status_code, a.version, a.submitted_at,
                    c.name AS committee_name, asg.assigned_at, asg.meeting_date,
                    pi.full_name AS pi_name, col.name AS college_name,
                    (SELECT d.id FROM committee_decisions d WHERE d.application_id = a.id ORDER BY d.id DESC LIMIT 1) AS last_decision_id
             FROM application_committee_assignments asg
             JOIN research_applications a ON a.id = asg.application_id
             JOIN committees c ON c.id = asg.committee_id
             JOIN users pi ON pi.id = a.pi_user_id
             LEFT JOIN colleges col ON col.id = a.college_id
             WHERE asg.status = "active"
               AND EXISTS (
                   SELECT 1 FROM committee_members m
                   WHERE m.committee_id = asg.committee_id AND m.user_id = ? AND m.status = "active"
               )
             ORDER BY asg.assigned_at DESC',
            [$userId]
        );
    }

    /** Active university research committees available for assignment (§11). */
    public static function assignableCommittees(): array
    {
        return Database::all(
            "SELECT c.*, (SELECT COUNT(*) FROM committee_members m WHERE m.committee_id = c.id AND m.status = 'active') AS member_count
             FROM committees c
             WHERE c.status = 'active'
             ORDER BY CASE WHEN c.committee_type = 'university_research' THEN 0 ELSE 1 END, c.name"
        );
    }
}
