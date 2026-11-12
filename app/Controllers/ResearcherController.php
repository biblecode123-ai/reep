<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Gate;
use App\Core\Session;
use App\Core\Validator;
use App\Services\DocumentService;
use App\Services\ResearcherPolicy;
use App\Services\ResearcherTransformer;

/**
 * Researcher directory & profile pages + JSON API (spec §7, §8, §9, §11).
 *
 * All scoping is enforced server-side via ResearcherPolicy:
 *   - researcher          → own profile only
 *   - college coordinator → own-college researchers
 *   - director / admin    → whole university
 */
final class ResearcherController
{
    // ---- HTML: directory -----------------------------------------------------

    public static function index(): void
    {
        Gate::authorize('researcher.view');
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $canManageAll = ResearcherPolicy::isDirectorate();

        $filters = [
            'q'       => trim((string) ($_GET['q'] ?? '')),
            'college' => $canManageAll ? (int) ($_GET['college_id'] ?? 0) : 0,
            'dept'    => (int) ($_GET['department_id'] ?? 0),
            'rank'    => trim((string) ($_GET['rank'] ?? '')),
            'qual'    => $canManageAll ? trim((string) ($_GET['qual'] ?? '')) : '',
            'status'  => $canManageAll ? trim((string) ($_GET['status'] ?? '')) : '',
        ];

        $p = paginate(0, $page, 20);
        $result = self::query($filters, $p);
        $total  = $result['total'];
        $p      = paginate($total, $page, 20);
        $rows   = $result['rows'];

        $colleges    = Gate::hasRole('college_coordinator') ? [] : Database::all('SELECT id, name FROM colleges ORDER BY name');
        $departments = Database::all(
            'SELECT d.id, d.name, d.college_id FROM departments d JOIN users u ON u.department_id = d.id
             WHERE u.status = "active" AND u.id IN (SELECT user_id FROM researchers)
             ' . (Gate::hasRole('college_coordinator') ? 'AND d.college_id = ' . (int) (Auth::user()['college_id'] ?? 0) : '') . '
             GROUP BY d.id ORDER BY d.name'
        );
        $ranks = Database::all(
            'SELECT DISTINCT r.academic_position AS v FROM researchers r WHERE r.academic_position IS NOT NULL AND r.academic_position != "" ORDER BY v'
        );
        $quals = $canManageAll ? Database::all(
            'SELECT DISTINCT r.qualification AS v FROM researchers r WHERE r.qualification IS NOT NULL AND r.qualification != "" ORDER BY v'
        ) : [];
        $qs = $_SERVER['QUERY_STRING'] ?? '';
        $coordinatorCollege = Gate::hasRole('college_coordinator')
            ? Database::one('SELECT name FROM colleges WHERE id = ?', [Auth::user()['college_id'] ?? 0])
            : null;
        include APP_PATH . '/views/researchers/index.php';
    }

    // ---- HTML: profile detail -------------------------------------------------

    public static function show(): void
    {
        Gate::authorize('researcher.view');
        $id = (int) ($_GET['id'] ?? 0) ?: (int) Auth::id();
        $profile = self::findProfile($id);
        if ($profile === null || !self::isResearcherRow($profile)) {
            Session::flash('error', 'Researcher not found.');
            redirect('index.php?action=researchers');
        }
        ResearcherPolicy::authorizeView($profile);
        $isSelf = ResearcherPolicy::canEdit($profile);

        // Contact details: self, directorate, or same-college coordinator (§6, §12).
        $isDirector = ResearcherPolicy::isDirectorate();
        $sameCollege = Gate::hasRole('college_coordinator')
            && in_array((int) ($profile['college_id'] ?? 0), ResearcherPolicy::coordinatorCollegeIds(), true);
        $canSeeContact = $isSelf || $isDirector || $sameCollege;

        // Research history (profile visibility ≠ document access; links respect their own rules)
        $projects = Database::all(
            'SELECT p.id, p.project_number, p.title, p.status_code, p.planned_end, m.member_role
             FROM research_project_members m JOIN research_projects p ON p.id = m.project_id
             WHERE m.user_id = ? AND m.status = "active"
             ORDER BY p.id DESC',
            [$id]
        );
        $applications = Database::all(
            'SELECT id, application_number, title, status_code FROM research_applications
             WHERE pi_user_id = ? ORDER BY id DESC LIMIT 10',
            [$id]
        );
        $publications = Database::all(
            'SELECT id, title, journal_name, publication_year, status FROM publications
             WHERE created_by = ? ORDER BY publication_year DESC, id DESC LIMIT 10',
            [$id]
        );
        $outputs = Database::all(
            'SELECT ro.id, ro.title, ro.output_type, ro.status, p.project_number
             FROM research_outputs ro JOIN research_projects p ON p.id = ro.project_id
             JOIN research_project_members m ON m.project_id = p.id AND m.user_id = ? AND m.status = "active"
             ORDER BY ro.id DESC LIMIT 10',
            [$id]
        );
        $repoRecords = Database::all(
            'SELECT rr.id, rr.repository_id_code, rr.title, rr.status
             FROM repository_records rr JOIN research_projects p ON p.id = rr.project_id
             JOIN research_project_members m ON m.project_id = p.id AND m.user_id = ? AND m.status = "active"
             ORDER BY rr.id DESC LIMIT 10',
            [$id]
        );
        $stats = [
            'projects'  => count($projects),
            'active'    => count(array_filter($projects, static fn ($x) => in_array($x['status_code'], ['ACTIVE', 'PROGRESS_MONITORING'], true))),
            'completed' => count(array_filter($projects, static fn ($x) => in_array($x['status_code'], ['COMPLETED', 'FINAL_REPORT_SUBMITTED'], true))),
        ];

        $photo = $profile['profile_photo_document_id']
            ? Database::one('SELECT id FROM documents WHERE id = ? AND status != "deleted"', [$profile['profile_photo_document_id']])
            : null;

        // Profile completion status (§3) — computed from the actual fields.
        $completionFields = [
            'photo' => $photo !== null,
            'rank' => (string) ($profile['academic_position'] ?? '') !== '',
            'qualification' => (string) ($profile['qualification'] ?? '') !== '',
            'specialization' => (string) ($profile['specialization'] ?? '') !== '',
            'interests' => (string) ($profile['research_interests'] ?? '') !== '',
            'areas' => (string) ($profile['research_areas'] ?? '') !== '',
            'biography' => (string) ($profile['biography'] ?? '') !== '',
            'orcid' => (string) ($profile['orcid'] ?? '') !== '',
            'scholar' => (string) ($profile['google_scholar'] ?? '') !== '',
        ];
        $completion = (int) round(100 * count(array_filter($completionFields)) / count($completionFields));
        include APP_PATH . '/views/researchers/view.php';
    }

    // ---- Self-service profile update (§15) -------------------------------------

    public static function updateOwn(): void
    {
        Csrf::verifyRequest();
        $userId = (int) Auth::id();
        [$ok, $errors, $clean] = Validator::check($_POST, [
            'position'       => 'max:100',
            'qualification'  => 'max:150',
            'specialization' => 'max:200',
            'interests'      => 'max:2000',
            'areas'          => 'max:1000',
            'biography'      => 'max:5000',
            'gender'         => 'in:,female,male,other',
            'orcid'          => 'max:50',
            'scholar'        => 'max:255',
        ]);
        if (!$ok) {
            Session::flash('error', implode(' ', $errors));
            redirect('index.php?action=profile');
        }
        $data = [
            'academic_position'  => $clean['position'] ?: null,
            'qualification'      => $clean['qualification'] ?: null,
            'specialization'     => $clean['specialization'] ?: null,
            'research_interests' => $clean['interests'] ?: null,
            'research_areas'     => $clean['areas'] ?: null,
            'biography'          => $clean['biography'] ?: null,
            'gender'             => ($clean['gender'] ?? '') ?: null,
            'orcid'              => $clean['orcid'] ?: null,
            'google_scholar'     => $clean['scholar'] ?: null,
            'updated_at'         => date('Y-m-d H:i:s'),
        ];
        $before = Database::one('SELECT * FROM researchers WHERE user_id = ?', [$userId]);
        if ($before === null) {
            Database::insert('researchers', array_merge(['user_id' => $userId, 'created_at' => date('Y-m-d H:i:s')], $data));
        } else {
            Database::update('researchers', $data, 'user_id = ?', [$userId]);
        }

        // Audit the changed fields (§16) — names only, no sensitive payloads.
        $changed = [];
        $map = ['academic_position' => 'academic rank', 'qualification' => 'qualification', 'specialization' => 'specialization',
            'research_interests' => 'research interests', 'research_areas' => 'research areas', 'biography' => 'biography',
            'gender' => 'gender', 'orcid' => 'ORCID', 'google_scholar' => 'Google Scholar'];
        foreach ($map as $k => $label) {
            $old = (string) ($before[$k] ?? '');
            $new = (string) ($data[$k] ?? '');
            if ($old !== $new) {
                $changed[$label] = ['old' => $old !== '' ? $old : null, 'new' => $new !== '' ? $new : null];
            }
        }
        $oldVals = $newVals = [];
        foreach ($changed as $label => $pair) {
            $oldVals[$label] = $pair['old'];
            $newVals[$label] = $pair['new'];
        }
        \App\Core\Audit::log(
            'researcher.profile_updated',
            'user',
            $userId,
            $oldVals ?: null,   // old values keyed by field (§16)
            $newVals ?: null,   // new values keyed by field (§16)
            $changed ? ('changed: ' . implode(', ', array_keys($changed))) : 'no field changes'
        );
        Session::flash('success', 'Researcher profile saved.');
        redirect('index.php?action=profile');
    }

    // ---- Self-service profile photo (§3, §15) ----------------------------------

    public static function uploadPhoto(): void
    {
        Csrf::verifyRequest();
        $userId = (int) Auth::id();
        $typeId = (int) Database::scalar('SELECT id FROM document_types WHERE type_code = "profile_photo"');
        if (!$typeId) {
            Session::flash('error', 'Profile photo document type is not configured.');
            redirect('index.php?action=profile');
        }
        [$ok, $err, $docId] = DocumentService::upload(
            $_FILES['photo'] ?? [],
            $typeId,
            'user',
            $userId,
            'Profile photo',
            'private'
        );
        if (!$ok) {
            Session::flash('error', $err);
            redirect('index.php?action=profile');
        }
        // Supersede the previous photo row.
        $old = Database::scalar('SELECT profile_photo_document_id FROM researchers WHERE user_id = ?', [$userId]);
        if ($old) {
            Database::update('documents', ['status' => 'archived'], 'id = ?', [(int) $old]);
        }
        $exists = Database::scalar('SELECT user_id FROM researchers WHERE user_id = ?', [$userId]);
        if ($exists) {
            Database::update('researchers', ['profile_photo_document_id' => $docId, 'updated_at' => date('Y-m-d H:i:s')], 'user_id = ?', [$userId]);
        } else {
            Database::insert('researchers', ['user_id' => $userId, 'profile_photo_document_id' => $docId, 'created_at' => date('Y-m-d H:i:s')]);
        }
        \App\Core\Audit::log('researcher.photo_updated', 'user', $userId, null, ['document_id' => $docId]);
        Session::flash('success', 'Profile photo updated.');
        redirect('index.php?action=profile');
    }

    /** Serve the profile photo (inline) — same policy as the profile itself. */
    public static function photo(): void
    {
        $userId = (int) ($_GET['user_id'] ?? 0);
        $owner = Database::one('SELECT id, college_id FROM users WHERE id = ? AND status != "deleted"', [$userId]);
        if ($owner === null) {
            http_response_code(404);
            exit('No profile photo.');
        }
        ResearcherPolicy::authorizeView($owner);
        $docId = (int) Database::scalar(
            'SELECT profile_photo_document_id FROM researchers WHERE user_id = ?',
            [$userId]
        );
        if (!$docId) {
            http_response_code(404);
            exit('No profile photo.');
        }
        DocumentService::download($docId, true); // enforces document rules itself
    }

    // ---- JSON API (§11) ---------------------------------------------------------

    public static function apiIndex(): void
    {
        Gate::authorize('researcher.view');
        $filters = [
            'q'       => trim((string) ($_GET['q'] ?? '')),
            'college' => (int) ($_GET['college_id'] ?? 0),
            'dept'    => (int) ($_GET['department_id'] ?? 0),
            'rank'    => trim((string) ($_GET['rank'] ?? '')),
            'qual'    => trim((string) ($_GET['qual'] ?? '')),
            'status'  => trim((string) ($_GET['status'] ?? '')),
        ];
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $p = paginate(0, $page, 50);
        $result = self::query($filters, $p);
        header('Content-Type: application/json');
        echo json_encode([
            'ok'   => true,
            'page' => $p['page'],
            'total' => $result['total'],
            'data' => array_map(
                static fn (array $r): array => ResearcherTransformer::transform($r, Auth::user()),
                $result['rows']
            ),
        ]);
    }

    public static function apiShow(): void
    {
        Gate::authorize('researcher.view');
        $id = (int) ($_GET['id'] ?? 0);
        $profile = self::findProfile($id);
        header('Content-Type: application/json');
        if ($profile === null || !self::isResearcherRow($profile)) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Researcher not found']);
            return;
        }
        if (!ResearcherPolicy::canView($profile)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'You are not authorized to view this researcher profile.']);
            return;
        }
        echo json_encode(['ok' => true, 'data' => ResearcherTransformer::transform($profile, Auth::user(), true)]);
    }

    public static function apiOwn(): void
    {
        Gate::authorize('researcher.view');
        $profile = self::findProfile((int) Auth::id());
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'data' => $profile !== null ? ResearcherTransformer::transform($profile, Auth::user(), true) : null]);
    }

    // ---- Internals ---------------------------------------------------------------

    private static function findProfile(int $userId): ?array
    {
        return Database::one(
            'SELECT u.id, u.employee_id, u.full_name, u.email, u.phone, u.status, u.position_title,
                    u.college_id, u.department_id, u.created_at AS user_created_at,
                    r.user_id AS has_profile,
                    r.academic_position, r.qualification, r.specialization, r.research_interests,
                    r.research_areas, r.biography, r.gender, r.orcid, r.google_scholar,
                    r.profile_photo_document_id, r.created_at, r.updated_at,
                    c.name AS college_name, d.name AS department_name
             FROM users u
             LEFT JOIN researchers r ON r.user_id = u.id
             LEFT JOIN colleges c    ON c.id = u.college_id
             LEFT JOIN departments d ON d.id = u.department_id
             WHERE u.id = ? AND u.status != "deleted"',
            [$userId]
        );
    }

    /** A profile detail page only exists for actual researchers — except your own (§15 "create profile"). */
    private static function isResearcherRow(array $profile): bool
    {
        return $profile['has_profile'] !== null
            || (int) $profile['id'] === (int) Auth::id();
    }

    /** Directory query — always scoped by ResearcherPolicy::listScope() (§13). */
    private static function query(array $filters, array $p): array
    {
        $scope = ResearcherPolicy::listScope();
        $where = ['1=1', $scope['where']];
        $params = $scope['params'];

        if ($filters['q'] !== '') {
            $where[] = '(u.full_name LIKE ? OR u.employee_id LIKE ? OR r.specialization LIKE ? OR r.research_interests LIKE ? OR r.research_areas LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            array_push($params, $like, $like, $like, $like, $like);
        }
        if (!empty($filters['college'])) {
            $where[] = 'u.college_id = ?';
            $params[] = (string) $filters['college'];
        }
        if (!empty($filters['dept'])) {
            $where[] = 'u.department_id = ?';
            $params[] = (string) $filters['dept'];
        }
        if ($filters['rank'] !== '') {
            $where[] = 'r.academic_position = ?';
            $params[] = $filters['rank'];
        }
        if (!empty($filters['qual'])) {
            $where[] = 'r.qualification = ?';
            $params[] = $filters['qual'];
        }
        if (!empty($filters['status']) && in_array($filters['status'], ['active', 'inactive', 'suspended'], true)) {
            $where[] = 'u.status = ?';
            $params[] = $filters['status'];
        }

        $sql = 'FROM users u
                JOIN researchers r ON r.user_id = u.id
                LEFT JOIN colleges c ON c.id = u.college_id
                LEFT JOIN departments d ON d.id = u.department_id
                WHERE u.status != "deleted" AND ' . implode(' AND ', $where);

        $total = (int) Database::scalar('SELECT COUNT(*) ' . $sql, $params);
        $rows = Database::all(
            'SELECT u.id, u.employee_id, u.full_name, u.email, u.status, u.college_id, u.department_id,
                    r.academic_position, r.qualification, r.specialization, r.research_interests,
                    r.research_areas, r.orcid, r.google_scholar, r.profile_photo_document_id,
                    c.name AS college_name, d.name AS department_name,
                    (SELECT COUNT(*) FROM research_project_members m WHERE m.user_id = u.id AND m.status = "active") AS project_count
             ' . $sql . '
             ORDER BY u.full_name
             LIMIT ' . (int) $p['limit'] . ' OFFSET ' . (int) $p['offset'],
            $params
        );
        return ['total' => $total, 'rows' => $rows];
    }
}
