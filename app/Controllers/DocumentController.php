<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Gate;
use App\Core\Session;
use App\Services\DocumentService;

/**
 * Upload/download endpoints for any entity type beyond applications
 * (projects, ethics, publications, repository, extension).
 */
final class DocumentController
{
    private const ENTITY_TYPES = ['research_application', 'research_project', 'ethics_application',
        'publication', 'repository_record', 'extension_project', 'user'];

    public static function upload(): void
    {
        Gate::authorize('document.upload');
        Csrf::verifyRequest();
        $entityType = (string) ($_POST['entity_type'] ?? '');
        $entityId   = (int) ($_POST['entity_id'] ?? 0);
        if (!in_array($entityType, self::ENTITY_TYPES, true)) {
            Session::flash('error', 'Unknown entity type.');
            redirect('index.php');
        }
        // Entity-specific ownership check (Rule 6).
        if (!self::canUploadTo($entityType, $entityId)) {
            Session::flash('error', 'You are not allowed to attach documents to this record.');
            redirect(self::backUrl($entityType, $entityId));
        }
        $typeId = (int) ($_POST['document_type_id'] ?? 0);
        [$ok, $err, $docId] = DocumentService::upload(
            $_FILES['document'] ?? [],
            $typeId,
            $entityType,
            $entityId,
            trim((string) ($_POST['title'] ?? '')) ?: null,
            (string) ($_POST['visibility'] ?? 'entity_team'),
            trim((string) ($_POST['reason'] ?? '')) ?: null
        );
        if ($ok) {
            Audit::log('document.uploaded', $entityType, $entityId, null, ['doc' => $docId]);
            Session::flash('success', 'Document uploaded.');
        } else {
            Session::flash('error', $err);
        }
        redirect(self::backUrl($entityType, $entityId));
    }

    public static function download(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        DocumentService::download($id);
    }

    public static function archive(): void
    {
        Gate::authorize('document.archive');
        Csrf::verifyRequest();
        $id = (int) ($_POST['id'] ?? 0);
        \App\Core\Database::update('documents', ['status' => 'archived'], 'id = ?', [$id]);
        Audit::log('document.archived', 'document', $id);
        Session::flash('success', 'Document archived.');
        redirect($_SERVER['HTTP_REFERER'] ?? 'index.php');
    }

    private static function canUploadTo(string $entityType, int $entityId): bool
    {
        $userId = Auth::id();
        switch ($entityType) {
            case 'research_application':
                return (bool) Database::scalar('SELECT COUNT(*) FROM research_applications WHERE id = ? AND (pi_user_id = ? OR ? IN (SELECT user_id FROM user_roles))',
                    [$entityId, $userId, $userId]) || Gate::allows('research.manage');
            case 'research_project':
                // The PI and active team members attach deliverables (§4) —
                // e.g. progress/final reports — to their own project.
                return (bool) Database::scalar(
                    'SELECT COUNT(*) FROM research_projects p WHERE p.id = ?
                       AND (p.pi_user_id = ?
                            OR EXISTS (SELECT 1 FROM research_project_members m
                                       WHERE m.project_id = p.id AND m.user_id = ? AND m.status = "active"))',
                    [$entityId, $userId, $userId]
                ) || Gate::allows('research.manage');
            default:
                return Gate::allows('research.manage', 'ethics.manage', 'repository.manage', 'publication.manage');
        }
    }

    private static function backUrl(string $entityType, int $entityId): string
    {
        return match ($entityType) {
            'research_application' => 'index.php?action=application_view&id=' . $entityId,
            'research_project'     => 'index.php?action=project_view&id=' . $entityId,
            'ethics_application'   => 'index.php?action=ethics_view&id=' . $entityId,
            'repository_record'    => 'index.php?action=repository_view&id=' . $entityId,
            default                => 'index.php',
        };
    }
}

