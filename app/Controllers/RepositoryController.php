<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Gate;
use App\Core\Logger;
use App\Core\Session;
use App\Core\Validator;
use App\Services\DocumentService;
use App\Services\NumberingService;

/**
 * Institutional research repository (spec §69-§74, §99).
 * Uploads completed research to the repository and manages records:
 * create, edit, delete, publish/archive, attach research documents.
 */
final class RepositoryController
{
    private const VISIBILITIES = ['public', 'university_only', 'college_only', 'restricted', 'confidential', 'embargoed'];
    private const STATUSES     = ['draft', 'published', 'embargoed', 'archived', 'withdrawn'];

    /** A project is repository-eligible only once its research is FINISHED. */
    private const ELIGIBLE_PROJECT_STATUSES = ['COMPLETED', 'FINAL_REPORT_SUBMITTED', 'FINAL_EVALUATION', 'PRESENTED'];

    // ---- Listing -------------------------------------------------------------

    public static function index(): void
    {
        Gate::authorize('repository.view');
        $canManage = Gate::allows('repository.manage');
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $filters = [
            'q'          => trim((string) ($_GET['q'] ?? '')),
            'status'     => $canManage ? (string) ($_GET['status'] ?? '') : 'published',
            'visibility' => $canManage ? (string) ($_GET['visibility'] ?? '') : '',
        ];
        $p = paginate(0, $page, 15);
        $result = self::query($filters, $p);
        $p = paginate($result['total'], $page, 15);
        $rows = $result['rows'];
        $counts = [
            'total'     => (int) Database::scalar('SELECT COUNT(*) FROM repository_records' . ($canManage ? '' : ' WHERE status = "published"')),
            'published' => (int) Database::scalar('SELECT COUNT(*) FROM repository_records WHERE status = "published"'),
            'draft'     => $canManage ? (int) Database::scalar('SELECT COUNT(*) FROM repository_records WHERE status = "draft"') : 0,
            'archived'  => $canManage ? (int) Database::scalar('SELECT COUNT(*) FROM repository_records WHERE status = "archived"') : 0,
        ];
        $qs = $_SERVER['QUERY_STRING'] ?? '';
        include APP_PATH . '/views/repository/index.php';
    }

    // ---- Detail --------------------------------------------------------------

    public static function view(): void
    {
        Gate::authorize('repository.view');
        $id = (int) ($_GET['id'] ?? 0);
        $record = self::find($id);
        if ($record === null) {
            Session::flash('error', 'Repository record not found.');
            redirect('index.php?action=repository');
        }
        // Readers without manage rights may open PUBLISHED records only.
        if (!Gate::allows('repository.manage') && $record['status'] !== 'published') {
            Logger::security('Repository draft access denied', ['record' => $id, 'user' => Auth::id()]);
            Session::flash('error', 'This research record is not published yet.');
            redirect('index.php?action=repository');
        }
        $documents = DocumentService::listFor('repository_record', $id);
        $docTypes  = Database::all('SELECT * FROM document_types WHERE status = "active" ORDER BY name');
        include APP_PATH . '/views/repository/view.php';
    }

    // ---- Create / edit form --------------------------------------------------

    public static function form(): void
    {
        Gate::authorize('repository.manage');
        $id = (int) ($_GET['id'] ?? 0);
        $record = $id > 0 ? self::find($id) : null;
        if ($id > 0 && $record === null) {
            Session::flash('error', 'Repository record not found.');
            redirect('index.php?action=repository');
        }
        // Only FINISHED research may enter the repository (§69): completed or
        // in final reporting, never still-active projects.
        $projects = Database::all(
            'SELECT p.id, p.project_number, p.title, p.status_code
             FROM research_projects p
             WHERE p.status_code IN ("' . implode('","', self::ELIGIBLE_PROJECT_STATUSES) . '")
               AND NOT EXISTS (SELECT 1 FROM repository_records rr WHERE rr.project_id = p.id)
             ORDER BY p.id DESC'
        );
        $colleges    = Database::all('SELECT id, name FROM colleges ORDER BY name');
        $departments = Database::all('SELECT id, name, college_id FROM departments ORDER BY name');
        $types       = Database::all('SELECT id, name FROM research_types ORDER BY name');
        $themes      = Database::all('SELECT id, name FROM research_themes ORDER BY name');
        $errors = [];
        include APP_PATH . '/views/repository/form.php';
    }

    // ---- Save (create or update) ---------------------------------------------

    public static function save(): void
    {
        Gate::authorize('repository.manage');
        Csrf::verifyRequest();
        $id = (int) ($_POST['id'] ?? 0);
        [$ok, $errors, $clean] = Validator::check($_POST, [
            'title'       => 'required|max:255',
            'abstract'    => 'max:5000',
            'keywords'    => 'max:500',
            'year'        => 'integer',
            'citation_text' => 'max:2000',
            'visibility'  => 'in:' . implode(',', self::VISIBILITIES),
            'status'      => 'in:' . implode(',', self::STATUSES),
        ]);
        if (!$ok) {
            Session::flash('error', implode(' ', $errors));
            redirect('index.php?action=repository_form' . ($id ? '&id=' . $id : ''));
        }

        $year = (int) ($_POST['year'] ?? 0) ?: null;
        $data = [
            'title'          => $clean['title'],
            'abstract'       => trim((string) ($_POST['abstract'] ?? '')) ?: null,
            'keywords'       => trim((string) ($_POST['keywords'] ?? '')) ?: null,
            'year'           => $year,
            'research_type_id'  => (int) ($_POST['research_type_id'] ?? 0) ?: null,
            'research_theme_id' => (int) ($_POST['research_theme_id'] ?? 0) ?: null,
            'college_id'     => (int) ($_POST['college_id'] ?? 0) ?: null,
            'department_id'  => (int) ($_POST['department_id'] ?? 0) ?: null,
            'citation_text'  => trim((string) ($_POST['citation_text'] ?? '')) ?: null,
            'visibility'     => $clean['visibility'] ?: 'university_only',
            'status'         => $clean['status'] ?: 'draft',
            'updated_at'     => date('Y-m-d H:i:s'),
        ];

        try {
            if ($id > 0) {
                $record = self::find($id);
                if ($record === null) {
                    Session::flash('error', 'Repository record not found.');
                    redirect('index.php?action=repository');
                }
                if ($data['status'] === 'published' && $record['status'] !== 'published') {
                    $data['published_at'] = date('Y-m-d H:i:s');
                }
                if ($data['status'] === 'archived' && $record['status'] !== 'archived') {
                    $data['archived_at'] = date('Y-m-d H:i:s');
                }
                Database::update('repository_records', $data, 'id = ?', [$id]);
                Audit::log('repository.updated', 'repository_record', $id, null, ['status' => $data['status']]);
                Session::flash('success', 'Repository record updated.');
                redirect('index.php?action=repository_view&id=' . $id);
            }

            $projectId = (int) ($_POST['project_id'] ?? 0);
            $project = Database::one('SELECT id, status_code FROM research_projects WHERE id = ?', [$projectId]);
            if ($project === null) {
                Session::flash('error', 'Select a research project to add to the repository.');
                redirect('index.php?action=repository_form');
            }
            if (!in_array($project['status_code'], self::ELIGIBLE_PROJECT_STATUSES, true)) {
                Session::flash('error', 'Only finished research (final report submitted or completed) can be uploaded to the repository. This project is still ' . strtolower(str_replace('_', ' ', $project['status_code'])) . '.');
                redirect('index.php?action=repository_form');
            }
            $duplicate = Database::scalar('SELECT COUNT(*) FROM repository_records WHERE project_id = ?', [$projectId]);
            if ((int) $duplicate > 0) {
                Session::flash('error', 'That project already has a repository record.');
                redirect('index.php?action=repository_form');
            }
            $data['project_id'] = $projectId;
            $data['repository_id_code'] = NumberingService::next('repository_record');
            $data['created_by'] = Auth::id();
            $data['created_at'] = date('Y-m-d H:i:s');
            // New uploads always enter as DRAFT — they go live only after the
            // administrator approves them (Approve button on the record page).
            $data['status'] = 'draft';
            unset($data['published_at']);
            $newId = Database::insert('repository_records', $data);
            Audit::log('repository.created', 'repository_record', $newId, null, ['code' => $data['repository_id_code'], 'project' => $projectId]);
            Session::flash('success', 'Research added to the repository (' . $data['repository_id_code'] . '). You can now attach the research document below.');
            redirect('index.php?action=repository_view&id=' . $newId);
        } catch (\PDOException $e) {
            Session::flash('error', str_contains($e->getMessage(), 'UNIQUE') ? 'That project already has a repository record.' : 'Database error.');
            redirect('index.php?action=repository_form' . ($id ? '&id=' . $id : ''));
        }
    }

    // ---- Admin approval (draft → published) -----------------------------------

    public static function approve(): void
    {
        Gate::authorize('repository.publish');
        Csrf::verifyRequest();
        $id = (int) ($_POST['id'] ?? 0);
        $record = self::find($id);
        if ($record === null) {
            Session::flash('error', 'Repository record not found.');
            redirect('index.php?action=repository');
        }
        if ($record['status'] === 'published') {
            Session::flash('error', 'This record is already published.');
            redirect('index.php?action=repository_view&id=' . $id);
        }
        // A record may only be approved once the research document is attached.
        $hasDoc = (bool) Database::scalar(
            'SELECT COUNT(*) FROM documents WHERE entity_type = "repository_record" AND entity_id = ? AND status = "active"',
            [$id]
        );
        if (!$hasDoc) {
            Session::flash('error', 'Attach the research document before approving this record.');
            redirect('index.php?action=repository_view&id=' . $id);
        }
        Database::update('repository_records', [
            'status'       => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);
        Audit::log('repository.approved', 'repository_record', $id, null, ['code' => $record['repository_id_code']]);
        Session::flash('success', 'Repository record approved and published (' . $record['repository_id_code'] . ').');
        redirect('index.php?action=repository_view&id=' . $id);
    }

    /** Send a published record back to draft (admin revision). */
    public static function unpublish(): void
    {
        Gate::authorize('repository.publish');
        Csrf::verifyRequest();
        $id = (int) ($_POST['id'] ?? 0);
        $record = self::find($id);
        if ($record === null) {
            Session::flash('error', 'Repository record not found.');
            redirect('index.php?action=repository');
        }
        Database::update('repository_records', [
            'status'     => 'draft',
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);
        Audit::log('repository.unpublished', 'repository_record', $id);
        Session::flash('success', 'Record returned to draft. It is hidden from the published repository until approved again.');
        redirect('index.php?action=repository_view&id=' . $id);
    }

    // ---- Delete --------------------------------------------------------------

    public static function delete(): void
    {
        Gate::authorize('repository.manage');
        Csrf::verifyRequest();
        $id = (int) ($_POST['id'] ?? 0);
        $record = self::find($id);
        if ($record === null) {
            Session::flash('error', 'Repository record not found.');
            redirect('index.php?action=repository');
        }

        $docs = Database::all(
            'SELECT id, file_path FROM documents WHERE entity_type = "repository_record" AND entity_id = ?',
            [$id]
        );
        Database::transaction(function () use ($id, $docs): void {
            if ($docs) {
                $ids = array_map(static fn (array $d): int => (int) $d['id'], $docs);
                Database::run(
                    'DELETE FROM documents WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')',
                    $ids
                );
            }
            Database::run('DELETE FROM repository_records WHERE id = ?', [$id]);
        });
        // Remove stored files after the rows are gone.
        foreach ($docs as $d) {
            $abs = STORAGE_PATH . '/' . $d['file_path'];
            $real = realpath($abs);
            $base = realpath(STORAGE_PATH);
            if ($real !== false && $base !== false && str_starts_with($real, $base) && is_file($real)) {
                @unlink($real);
            }
        }
        Audit::log('repository.deleted', 'repository_record', $id, ['code' => $record['repository_id_code'], 'title' => $record['title']]);
        Session::flash('success', 'Repository record "' . $record['title'] . '" deleted.');
        redirect('index.php?action=repository');
    }

    // ---- Helpers -------------------------------------------------------------

    private static function find(int $id): ?array
    {
        return Database::one(
            'SELECT rr.*, p.project_number, p.title AS project_title, u.full_name AS created_by_name,
                    c.name AS college_name, d.name AS department_name, rt.name AS type_name, th.name AS theme_name
             FROM repository_records rr
             JOIN research_projects p ON p.id = rr.project_id
             LEFT JOIN users u       ON u.id = rr.created_by
             LEFT JOIN colleges c    ON c.id = rr.college_id
             LEFT JOIN departments d ON d.id = rr.department_id
             LEFT JOIN research_types rt  ON rt.id = rr.research_type_id
             LEFT JOIN research_themes th ON th.id = rr.research_theme_id
             WHERE rr.id = ?',
            [$id]
        );
    }

    /** @return array{total:int, rows:array} */
    private static function query(array $filters, array $p): array
    {
        $where = ['1=1'];
        $params = [];
        if ($filters['q'] !== '') {
            $where[] = '(rr.title LIKE ? OR rr.repository_id_code LIKE ? OR p.project_number LIKE ? OR rr.keywords LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            array_push($params, $like, $like, $like, $like);
        }
        if (in_array($filters['status'], self::STATUSES, true)) {
            $where[] = 'rr.status = ?';
            $params[] = $filters['status'];
        }
        if (in_array($filters['visibility'], self::VISIBILITIES, true)) {
            $where[] = 'rr.visibility = ?';
            $params[] = $filters['visibility'];
        }
        $sql = 'FROM repository_records rr JOIN research_projects p ON p.id = rr.project_id WHERE ' . implode(' AND ', $where);
        $total = (int) Database::scalar('SELECT COUNT(*) ' . $sql, $params);
        $rows = Database::all(
            'SELECT rr.*, p.project_number ' . $sql . ' ORDER BY rr.id DESC LIMIT ? OFFSET ?',
            array_merge($params, [$p['limit'], $p['offset']])
        );
        return ['total' => $total, 'rows' => $rows];
    }
}

