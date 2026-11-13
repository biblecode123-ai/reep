<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Research agreements (spec §13; Rules 10–12).
 * Issued by the Research Directorate, signed by the researcher before the
 * research becomes ACTIVE. Re-issuing creates a NEW version row — previous
 * versions are never overwritten (Rule 12).
 */
final class AgreementModel
{
    /** Latest agreement version for an application (any status). */
    public static function latest(int $applicationId): ?array
    {
        return Database::one(
            'SELECT ag.*, iu.full_name AS issued_by_name, su.full_name AS signed_by_name, pu.full_name AS prepared_by_name
             FROM research_agreements ag
             LEFT JOIN users iu ON iu.id = ag.issued_by
             LEFT JOIN users su ON su.id = ag.signed_by
             LEFT JOIN users pu ON pu.id = ag.prepared_by
             WHERE ag.application_id = ?
             ORDER BY ag.version DESC LIMIT 1',
            [$applicationId]
        );
    }

    /** All versions, oldest first (Rule 12 — history preserved). */
    public static function versions(int $applicationId): array
    {
        return Database::all(
            'SELECT ag.*, iu.full_name AS issued_by_name, su.full_name AS signed_by_name
             FROM research_agreements ag
             LEFT JOIN users iu ON iu.id = ag.issued_by
             LEFT JOIN users su ON su.id = ag.signed_by
             WHERE ag.application_id = ?
             ORDER BY ag.version ASC',
            [$applicationId]
        );
    }

    public static function signed(int $applicationId): ?array
    {
        return Database::one(
            'SELECT * FROM research_agreements WHERE application_id = ? AND status = "signed" ORDER BY version DESC LIMIT 1',
            [$applicationId]
        );
    }

    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM research_agreements WHERE id = ?', [$id]);
    }

    public static function create(array $data): int
    {
        return Database::insert('research_agreements', $data);
    }

    public static function update(int $id, array $data): void
    {
        Database::update('research_agreements', $data, 'id = ?', [$id]);
    }

    /** Next version number for an application. */
    public static function nextVersion(int $applicationId): int
    {
        return ((int) Database::scalar('SELECT COALESCE(MAX(version), 0) FROM research_agreements WHERE application_id = ?', [$applicationId])) + 1;
    }
}
