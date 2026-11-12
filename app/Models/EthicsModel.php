<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Ethics module model (spec §30–§34).
 * Screening → application → reviewer assignment → review → decision,
 * plus certificates, expiry tracking and amendments.
 */
final class EthicsModel
{
    // ---- Applications ------------------------------------------------------

    public static function create(array $data): int
    {
        return Database::insert('ethics_applications', $data);
    }

    public static function find(int $id): ?array
    {
        return Database::one(
            'SELECT e.*, a.title AS research_title, a.application_number, a.call_id,
                    u.full_name AS researcher_name, u.email AS researcher_email,
                    c.name AS college_name, ws.stage_name AS stage_label
             FROM ethics_applications e
             JOIN research_applications a ON a.id = e.research_application_id
             JOIN users u ON u.id = e.researcher_user_id
             LEFT JOIN colleges c ON c.id = a.college_id
             LEFT JOIN workflow_stages ws ON ws.id = e.current_stage_id
             WHERE e.id = ?',
            [$id]
        );
    }

    public static function findByResearchApplication(int $researchApplicationId): ?array
    {
        return Database::one('SELECT * FROM ethics_applications WHERE research_application_id = ?', [$researchApplicationId]);
    }

    public static function update(int $id, array $data): void
    {
        Database::update('ethics_applications', $data, 'id = ?', [$id]);
    }

    public static function paginate(array $filters, int $page, int $perPage): array
    {
        $where  = ['1=1'];
        $params = [];
        if (!empty($filters['status'])) {
            $where[]  = 'e.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['q'])) {
            $where[]  = '(e.application_number LIKE ? OR a.title LIKE ?)';
            $like     = '%' . $filters['q'] . '%';
            $params[] = $like;
            $params[] = $like;
        }
        $whereSql = implode(' AND ', $where);

        $total = (int) Database::scalar(
            "SELECT COUNT(*) FROM ethics_applications e
             JOIN research_applications a ON a.id = e.research_application_id
             WHERE $whereSql",
            $params
        );
        $rows = Database::all(
            "SELECT e.id, e.application_number, e.status, e.risk_level, e.approved_until, e.submitted_at,
                    a.title AS research_title, a.application_number AS research_app_number,
                    u.full_name AS researcher_name, ws.stage_name AS stage_label
             FROM ethics_applications e
             JOIN research_applications a ON a.id = e.research_application_id
             JOIN users u ON u.id = e.researcher_user_id
             LEFT JOIN workflow_stages ws ON ws.id = e.current_stage_id
             WHERE $whereSql
             ORDER BY e.submitted_at DESC, e.id DESC
             LIMIT " . (int) $perPage . ' OFFSET ' . (int) (($page - 1) * $perPage),
            $params
        );
        return ['total' => $total, 'rows' => $rows];
    }

    public static function statusCounts(): array
    {
        return Database::all('SELECT status, COUNT(*) AS cnt FROM ethics_applications GROUP BY status');
    }

    /** Applications whose approval expires within $days (or already expired). */
    public static function expiringWithin(int $days): array
    {
        if (Database::isSqlite()) {
            return Database::all(
                "SELECT e.*, a.title AS research_title, u.full_name AS researcher_name
                 FROM ethics_applications e
                 JOIN research_applications a ON a.id = e.research_application_id
                 JOIN users u ON u.id = e.researcher_user_id
                 WHERE e.status IN ('approved','approved_with_conditions')
                   AND e.approved_until IS NOT NULL
                   AND e.approved_until <= date('now', ?)
                 ORDER BY e.approved_until ASC",
                [$days . ' days']
            );
        }
        return Database::all(
            "SELECT e.*, a.title AS research_title, u.full_name AS researcher_name
             FROM ethics_applications e
             JOIN research_applications a ON a.id = e.research_application_id
             JOIN users u ON u.id = e.researcher_user_id
             WHERE e.status IN ('approved','approved_with_conditions')
               AND e.approved_until IS NOT NULL
               AND e.approved_until <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
             ORDER BY e.approved_until ASC",
            [$days]
        );
    }

    // ---- Reviews (§32) ------------------------------------------------------

    public static function reviews(int $ethicsApplicationId): array
    {
        return Database::all(
            'SELECT r.*, u.full_name AS reviewer_name, u.email AS reviewer_email
             FROM ethics_reviews r JOIN users u ON u.id = r.reviewer_user_id
             WHERE r.ethics_application_id = ?
             ORDER BY r.assigned_at',
            [$ethicsApplicationId]
        );
    }

    public static function reviewById(int $id): ?array
    {
        return Database::one(
            'SELECT r.*, e.application_number, e.status AS ethics_status, a.title AS research_title
             FROM ethics_reviews r
             JOIN ethics_applications e ON e.id = r.ethics_application_id
             JOIN research_applications a ON a.id = e.research_application_id
             WHERE r.id = ?',
            [$id]
        );
    }

    public static function assignReviewer(int $ethicsApplicationId, int $reviewerUserId, int $assignedBy, ?string $deadline): void
    {
        $existing = Database::one(
            'SELECT id FROM ethics_reviews WHERE ethics_application_id = ? AND reviewer_user_id = ?',
            [$ethicsApplicationId, $reviewerUserId]
        );
        if ($existing !== null) {
            Database::update('ethics_reviews', [
                'status' => 'assigned', 'assigned_by' => $assignedBy, 'assigned_at' => date('Y-m-d H:i:s'), 'review_deadline' => $deadline,
            ], 'id = ?', [(int) $existing['id']]);
            return;
        }
        Database::insert('ethics_reviews', [
            'ethics_application_id' => $ethicsApplicationId,
            'reviewer_user_id'      => $reviewerUserId,
            'assigned_by'           => $assignedBy,
            'assigned_at'           => date('Y-m-d H:i:s'),
            'review_deadline'       => $deadline,
            'status'                => 'assigned',
        ]);
    }

    public static function setReviewStatus(int $id, array $data): void
    {
        Database::update('ethics_reviews', $data, 'id = ?', [$id]);
    }

    public static function forReviewer(int $reviewerUserId): array
    {
        return Database::all(
            'SELECT r.*, e.application_number, e.status AS ethics_status, a.title AS research_title
             FROM ethics_reviews r
             JOIN ethics_applications e ON e.id = r.ethics_application_id
             JOIN research_applications a ON a.id = e.research_application_id
             WHERE r.reviewer_user_id = ?
             ORDER BY ' . (Database::isSqlite()
                ? "CASE r.status WHEN 'assigned' THEN 1 WHEN 'accepted' THEN 2 WHEN 'completed' THEN 3 WHEN 'declined' THEN 4 ELSE 5 END, r.review_deadline IS NULL, r.review_deadline"
                : 'FIELD(r.status, "assigned", "accepted", "completed", "declined"), r.review_deadline IS NULL, r.review_deadline'),
            [$reviewerUserId]
        );
    }

    public static function reviewerCompleted(int $ethicsApplicationId, int $reviewerUserId): bool
    {
        return (bool) Database::scalar(
            'SELECT COUNT(*) FROM ethics_reviews WHERE ethics_application_id = ? AND reviewer_user_id = ? AND status = "completed"',
            [$ethicsApplicationId, $reviewerUserId]
        );
    }

    // ---- Decisions (§31, §32) ------------------------------------------------

    public static function decisions(int $ethicsApplicationId): array
    {
        return Database::all(
            'SELECT d.*, u.full_name AS decided_by_name
             FROM ethics_decisions d LEFT JOIN users u ON u.id = d.decided_by
             WHERE d.ethics_application_id = ? ORDER BY d.decided_at DESC',
            [$ethicsApplicationId]
        );
    }

    public static function recordDecision(int $ethicsApplicationId, array $data): int
    {
        $data['ethics_application_id'] = $ethicsApplicationId;
        return Database::insert('ethics_decisions', $data);
    }

    // ---- Certificates (§32) ----------------------------------------------------

    public static function certificates(int $ethicsApplicationId): array
    {
        return Database::all(
            'SELECT c.*, u.full_name AS issued_by_name
             FROM ethics_certificates c LEFT JOIN users u ON u.id = c.issued_by
             WHERE c.ethics_application_id = ? ORDER BY c.issued_date DESC',
            [$ethicsApplicationId]
        );
    }

    public static function createCertificate(int $ethicsApplicationId, ?int $issuedBy, ?string $expiresAt): int
    {
        $number = \App\Services\NumberingService::next('ethics_certificate');
        return Database::insert('ethics_certificates', [
            'ethics_application_id' => $ethicsApplicationId,
            'certificate_number'    => $number,
            'issued_date'           => date('Y-m-d'),
            'expires_at'            => $expiresAt,
            'issued_by'             => $issuedBy,
        ]);
    }

    // ---- Amendments (§34) -------------------------------------------------------

    public static function amendments(int $ethicsApplicationId): array
    {
        return Database::all(
            'SELECT am.*, u.full_name AS requested_by_name, du.full_name AS decided_by_name
             FROM ethics_amendments am
             LEFT JOIN users u ON u.id = am.requested_by
             LEFT JOIN users du ON du.id = am.decided_by
             WHERE am.ethics_application_id = ? ORDER BY am.requested_at DESC',
            [$ethicsApplicationId]
        );
    }

    public static function createAmendment(int $ethicsApplicationId, int $userId, string $type, string $description): int
    {
        $seq = (int) (Database::scalar(
            'SELECT COUNT(*) FROM ethics_amendments WHERE ethics_application_id = ?',
            [$ethicsApplicationId]
        ) ?? 0) + 1;
        $base = (string) Database::scalar('SELECT application_number FROM ethics_applications WHERE id = ?', [$ethicsApplicationId]);
        return Database::insert('ethics_amendments', [
            'ethics_application_id' => $ethicsApplicationId,
            'amendment_number'      => $base . '-AM' . str_pad((string) $seq, 2, '0', STR_PAD_LEFT),
            'amendment_type'        => $type,
            'description'           => $description,
            'status'                => 'submitted',
            'requested_by'          => $userId,
            'requested_at'          => date('Y-m-d H:i:s'),
        ]);
    }

    public static function updateAmendment(int $id, array $data): void
    {
        Database::update('ethics_amendments', $data, 'id = ?', [$id]);
    }
}

