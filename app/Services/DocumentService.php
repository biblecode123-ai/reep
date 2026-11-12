<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Gate;
use App\Core\Logger;
use App\Core\Session;
use App\Core\Settings;

/**
 * Centralized document management (spec §26, §63–§65, §90, §105; Rule 6).
 * ONE code path uploads and ONE code path serves files. Files live outside
 * the web root under storage/ and are streamed only after authorization.
 */
final class DocumentService
{
    private const MODULE_MAP = [
        'research_application' => 'research',
        'research_project'     => 'research',
        'ethics_application'   => 'ethics',
        'publication'          => 'publication',
        'extension_project'    => 'extension',
        'repository_record'    => 'repository',
        'user'                 => 'private',
    ];

    public static function allowedExtensions(): array
    {
        $types = (string) Settings::get('allowed_file_types', 'pdf,doc,docx,xls,xlsx,csv,ppt,pptx,txt,zip');
        $blocked = (require BASE_PATH . '/config/security.php')['uploads']['blocked_extensions'];
        $allowed = array_filter(array_map('trim', explode(',', strtolower($types))));
        return array_values(array_diff($allowed, $blocked));
    }

    /**
     * Store an uploaded file as a NEW document version.
     * Never overwrites an existing document row (spec §26).
     * @return array [ok(bool), error|null, documentId|null]
     */
    public static function upload(
        array $file,
        int $documentTypeId,
        string $entityType,
        int $entityId,
        ?string $title = null,
        string $visibility = 'entity_team',
        ?string $reason = null
    ): array {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return [false, self::uploadErrorMessage((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE)), null];
        }
        if (!is_uploaded_file($file['tmp_name'] ?? '')) {
            return [false, 'Invalid upload.', null];
        }

        $maxMb   = Settings::int('max_upload_size_mb', 25);
        $maxSize = $maxMb * 1024 * 1024;
        $size    = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > $maxSize) {
            return [false, "File exceeds the maximum size of {$maxMb} MB.", null];
        }

        $originalName = $file['name'] ?? 'upload.bin';           // metadata only (§90)
        $ext          = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, self::allowedExtensions(), true)) {
            return [false, 'File type not allowed: .' . $ext, null];
        }

        // Real MIME check (deny-by-default against sneaky content).
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);
        $allowedMimes = [
            'pdf' => 'application/pdf', 'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'csv' => 'text/csv', 'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'txt' => 'text/plain', 'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed', 'rar' => 'application/vnd.rar',
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
        ];
        if (isset($allowedMimes[$ext]) && !in_array($mime, [$allowedMimes[$ext], 'application/octet-stream'], true)) {
            return [false, 'File content does not match its extension.', null];
        }

        $typeRow = Database::one('SELECT id FROM document_types WHERE id = ? AND status = "active"', [$documentTypeId]);
        if ($typeRow === null) {
            return [false, 'Unknown document type.', null];
        }

        $module = self::MODULE_MAP[$entityType] ?? 'private';
        $relDir = $module . '/' . date('Y');
        $absDir = STORAGE_PATH . '/' . $relDir;
        if (!is_dir($absDir) && !@mkdir($absDir, 0775, true)) {
            Logger::error('Failed to create storage dir', ['dir' => $relDir]);
            return [false, 'Storage error. Please contact the administrator.', null];
        }

        // Safe generated name (spec §90).
        $safeName = $entityType . '_' . $entityId . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $target   = $absDir . '/' . $safeName;
        if (!move_uploaded_file($file['tmp_name'], $target)) {
            return [false, 'Could not store the uploaded file.', null];
        }
        @chmod($target, 0664);

        $docId = Database::transaction(function () use ($file, $entityType, $entityId, $documentTypeId, $title, $visibility, $reason, $relDir, $safeName, $size, $mime, $originalName): int {
            // Versioning: find current head for this entity+type.
            $head = Database::one(
                'SELECT id, version FROM documents
                 WHERE entity_type = ? AND entity_id = ? AND document_type_id = ?
                   AND superseded_by_id IS NULL AND status = "active"
                 ORDER BY version DESC LIMIT 1',
                [$entityType, $entityId, $documentTypeId]
            );
            $rootId  = $head['id'] ?? null;
            $version = $head !== null ? (int) $head['version'] + 1 : 1;

            $docId = Database::insert('documents', [
                'document_type_id' => $documentTypeId,
                'entity_type'      => $entityType,
                'entity_id'        => $entityId,
                'title'            => mb_substr($title ?: $originalName, 0, 250),
                'version'          => $version,
                'root_document_id' => $rootId,
                'file_path'        => $relDir . '/' . $safeName,
                'original_filename' => $originalName,
                'file_size'        => $size,
                'mime_type'        => $mime,
                'file_hash'        => hash_file('sha256', STORAGE_PATH . '/' . $relDir . '/' . $safeName),
                'visibility'       => in_array($visibility, ['private', 'entity_team', 'college', 'university', 'public'], true) ? $visibility : 'entity_team',
                'status'           => 'active',
                'uploaded_by'      => Auth::id(),
                'uploaded_at'      => date('Y-m-d H:i:s'),
            ]);

            if ($head !== null) {
                Database::update('documents', ['status' => 'superseded', 'superseded_by_id' => $docId], 'id = ?', [(int) $head['id']]);
            }

            Database::insert('document_access_log', [
                'document_id' => $docId,
                'user_id'     => Auth::id(),
                'access_type' => 'upload',
                'ip_address'  => client_ip(),
                'details'     => 'v' . $version . ($reason ? ' — ' . $reason : ''),
            ]);
            Audit::log('document.upload', $entityType, $entityId, null, ['document_id' => $docId, 'type_id' => $documentTypeId, 'version' => $version]);
            return $docId;
        });

        return [true, null, $docId];
    }

    /** List documents (all versions) for an entity, newest first. */
    public static function listFor(string $entityType, int $entityId): array
    {
        return Database::all(
            'SELECT d.*, dt.type_code, dt.name AS type_name, u.full_name AS uploader_name
             FROM documents d
             JOIN document_types dt ON dt.id = d.document_type_id
             LEFT JOIN users u ON u.id = d.uploaded_by
             WHERE d.entity_type = ? AND d.entity_id = ? AND d.status != "deleted"
             ORDER BY d.document_type_id, d.version DESC',
            [$entityType, $entityId]
        );
    }

    /** Determine whether the current user may access a document (Rule 6, §64, §114). */
    public static function canAccess(array $doc): bool
    {
        if (!Auth::check()) {
            return false;
        }
        if (Gate::denies('document.view')) {
            return false;
        }
        $userId = Auth::id();

        // Uploader always retains access to own uploads.
        if ((int) ($doc['uploaded_by'] ?? 0) === $userId) {
            return true;
        }

        // Visibility ladder (§61, §63).
        switch ($doc['visibility']) {
            case 'public':
                return true;
            case 'university':
                return Gate::allows('document.view'); // any staff with document.view
            case 'college':
                return self::userInCollegeOfEntity($doc);
            case 'entity_team':
            case 'private':
            default:
                return self::userIsEntityMember($doc) || self::userIsEntityReviewer($doc);
        }
    }

    /** Serve the file after re-checking everything (spec §64). */
    public static function download(int $documentId, bool $inline = false): void
    {
        $doc = Database::one(
            'SELECT d.*, dt.name AS type_name FROM documents d JOIN document_types dt ON dt.id = d.document_type_id WHERE d.id = ?',
            [$documentId]
        );
        if ($doc === null || $doc['status'] === 'deleted') {
            http_response_code(404);
            exit('Document not found.');
        }
        if (!self::canAccess($doc)) {
            Database::insert('document_access_log', [
                'document_id' => $documentId, 'user_id' => Auth::id(),
                'access_type' => 'failed_access',                 'ip_address'  => client_ip(), 'details' => 'denied',
            ]);
            Logger::security('Unauthorized download attempt', ['doc' => $documentId, 'user' => Auth::id(), 'ip' => client_ip()]);
            http_response_code(403);
            exit('You are not authorized to access this document.');
        }

        $abs = STORAGE_PATH . '/' . $doc['file_path'];
        $real = realpath($abs);
        $base = realpath(STORAGE_PATH);
        if ($real === false || $base === false || !str_starts_with($real, $base) || !is_file($real)) {
            http_response_code(404);
            exit('File missing.');
        }

        Database::insert('document_access_log', [
            'document_id' => $documentId, 'user_id' => Auth::id(),
            'access_type' => 'download',                 'ip_address'  => client_ip(), 'details' => null,
        ]);

        header('Content-Type: ' . ($doc['mime_type'] ?: 'application/octet-stream'));
        header('Content-Length: ' . (string) filesize($real));
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . self::safeDownloadName($doc) . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=0, no-cache');
        readfile($real);
        exit;
    }

    private static function safeDownloadName(array $doc): string
    {
        $ext = strtolower(pathinfo($doc['original_filename'], PATHINFO_EXTENSION));
        $base = preg_replace('/[^A-Za-z0-9 _\-\.]/u', '_', $doc['title'] ?? 'document');
        return $base . ($ext !== '' ? '.' . $ext : '');
    }

    private static function userIsEntityMember(array $doc): bool
    {
        return match ($doc['entity_type']) {
            // Profile photo: governed by the researcher-profile policy, NOT by
            // generic document access (profile visibility ≠ document access).
            'user' => (int) $doc['entity_id'] === (int) Auth::id()
                || (Gate::allows('researcher.view') && ResearcherPolicy::canView(
                    Database::one('SELECT id, college_id FROM users WHERE id = ?', [(int) $doc['entity_id']]) ?: ['id' => 0]
                )),
            'research_application' => (bool) Database::scalar(
                'SELECT COUNT(*) FROM research_applications WHERE id = ? AND pi_user_id = ?',
                [(int) $doc['entity_id'], Auth::id()])
                || (bool) Database::scalar(
                    'SELECT COUNT(*) FROM application_team_members WHERE application_id = ? AND user_id = ? AND status = "accepted"',
                    [(int) $doc['entity_id'], Auth::id()]),
            'research_project' => (bool) Database::scalar(
                'SELECT COUNT(*) FROM research_project_members WHERE project_id = ? AND user_id = ? AND status = "active"',
                [(int) $doc['entity_id'], Auth::id()]),
            'research_dataset' => (bool) Database::scalar(
                'SELECT COUNT(*) FROM research_datasets WHERE id = ? AND created_by = ?',
                [(int) $doc['entity_id'], Auth::id()]),
            'ethics_application' => (bool) Database::scalar(
                'SELECT COUNT(*) FROM ethics_applications WHERE id = ? AND researcher_user_id = ?',
                [(int) $doc['entity_id'], Auth::id()]),
            default => false,
        };
    }

    private static function userIsEntityReviewer(array $doc): bool
    {
        if ($doc['entity_type'] === 'research_application') {
            $applicationId = (int) $doc['entity_id'];
            $assigned = (bool) Database::scalar(
                'SELECT COUNT(*) FROM evaluator_assignments
                 WHERE application_id = ? AND evaluator_user_id = ? AND is_active = 1
                   AND status NOT IN ("conflict_declared", "declined", "cancelled")',
                [$applicationId, Auth::id()]
            );
            if ($assigned) {
                return true;
            }

            $college = Database::scalar(
                'SELECT college_id FROM research_applications WHERE id = ?',
                [$applicationId]
            );
            $isCoordinator = Gate::hasRole('college_coordinator') || Gate::hasRole('dept_coordinator');
            if ($isCoordinator && !Gate::allows('research.manage')) {
                $user = Auth::user();
                return $college !== null && $user !== null
                    && (int) ($user['college_id'] ?? 0) > 0
                    && (int) ($user['college_id'] ?? 0) === (int) $college
                    && Gate::allows('research.screen');
            }

            // Only Directorate officers receive institution-wide reviewer access.
            // A committee member must have an active assignment to THIS application.
            $isDirectorateOfficer = Gate::hasRole('rpee_director') || Gate::hasRole('rpee_officer');
            if ($isDirectorateOfficer && Gate::allows('research.review', 'research.screen', 'research.manage')) {
                return true;
            }

            $committeeAssignment = \App\Models\CommitteeModel::activeAssignment($applicationId);
            return $committeeAssignment !== null
                && \App\Models\CommitteeModel::isMember(
                    (int) $committeeAssignment['committee_id'],
                    (int) Auth::id()
                );
        }

        if ($doc['entity_type'] === 'research_project') {
            $projectId = (int) $doc['entity_id'];
            $assigned = (bool) Database::scalar(
                'SELECT COUNT(*) FROM project_evaluator_assignments pea
                 JOIN project_final_reports pfr ON pfr.id = pea.final_report_id
                 WHERE pfr.project_id = ? AND pea.evaluator_user_id = ? AND pea.is_active = 1
                   AND pea.status NOT IN ("conflict_declared", "declined", "cancelled")',
                [$projectId, Auth::id()]
            );
            if ($assigned) {
                return true;
            }

            $college = Database::scalar('SELECT college_id FROM research_projects WHERE id = ?', [$projectId]);
            $user = Auth::user();
            $isCoordinator = Gate::hasRole('college_coordinator') || Gate::hasRole('dept_coordinator');
            if ($isCoordinator && Gate::allows('research.screen') && $college !== null
                && $user !== null && (int) ($user['college_id'] ?? 0) === (int) $college) {
                return true;
            }

            return (Gate::hasRole('rpee_director') || Gate::hasRole('rpee_officer'))
                && Gate::allows('research.manage', 'research.review');
        }

        if ($doc['entity_type'] === 'research_dataset') {
            return (bool) Database::scalar(
                'SELECT COUNT(*) FROM research_datasets d JOIN research_project_members m ON d.project_id = m.project_id
                 WHERE d.id = ? AND m.user_id = ? AND m.status = "active"',
                [(int) $doc['entity_id'], Auth::id()]
            );
        }
        if ($doc['entity_type'] === 'ethics_application') {
            $rev = (bool) Database::scalar(
                'SELECT COUNT(*) FROM ethics_reviews WHERE ethics_application_id = ? AND reviewer_user_id = ?',
                [(int) $doc['entity_id'], Auth::id()]
            );
            return $rev || Gate::allows('ethics.manage');
        }
        return Gate::allows('research.manage') && (Gate::hasRole('rpee_director') || Gate::hasRole('rpee_officer'));
    }

    private static function userInCollegeOfEntity(array $doc): bool
    {
        $collegeId = match ($doc['entity_type']) {
            'research_application' => Database::scalar('SELECT college_id FROM research_applications WHERE id = ?', [(int) $doc['entity_id']]),
            'research_project'     => Database::scalar('SELECT college_id FROM research_projects WHERE id = ?', [(int) $doc['entity_id']]),
            default                => null,
        };
        if ($collegeId === null) {
            return false;
        }
        $user = Auth::user();
        if ($user !== null && (int) $user['college_id'] === (int) $collegeId) {
            return true;
        }
        // Institutional oversight roles may cross colleges.
        return Gate::allows('research.manage', 'research.review', 'ethics.manage', 'reports.view');
    }

    private static function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The uploaded file is too large.',
            UPLOAD_ERR_PARTIAL => 'The upload was interrupted. Please retry.',
            UPLOAD_ERR_NO_FILE => 'No file was selected.',
            default => 'Upload failed. Please retry.',
        };
    }
}

