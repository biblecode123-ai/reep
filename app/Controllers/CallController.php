<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Gate;
use App\Core\Session;
use App\Core\Validator;
use App\Models\CallModel;
use App\Services\NumberingService;

final class CallController
{
    public static function index(): void
    {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $filters = ['status' => (string) ($_GET['status'] ?? ''), 'q' => trim((string) ($_GET['q'] ?? ''))];
        $p = paginate(1, $page, 15);
        $result = CallModel::paginate($filters, $p['page'], $p['limit']);
        $p = paginate($result['total'], $page, 15);
        $rows = $result['rows'];
        $qs = $_SERVER['QUERY_STRING'] ?? '';
        $appliedCallIds = self::appliedCallIds();
        include APP_PATH . '/views/research/calls.php';
    }

    public static function form(): void
    {
        Gate::authorize('research.manage');
        $id = (int) ($_GET['id'] ?? 0);
        $call = $id ? CallModel::find($id) : null;
        $eligibility = $id ? CallModel::eligibility($id) : [];
        $requirements = $id ? CallModel::requirements($id) : [];
        $criteria = $id ? CallModel::criteria($id) : [];
        $colleges = \App\Models\OrgModel::colleges(true);
        $themes = \App\Models\OrgModel::themes();
        $docTypes = \App\Models\OrgModel::documentTypes();
        include APP_PATH . '/views/research/call_form.php';
    }

    public static function save(): void
    {
        Gate::authorize('research.manage');
        Csrf::verifyRequest();
        $id = (int) ($_POST['id'] ?? 0);
        [$ok, $errors, $clean] = Validator::check($_POST, [
            'title'            => 'required|max:200',
            'reference_number' => 'required|max:60',
        ]);
        if (!$ok) {
            Session::flash('error', implode(' ', $errors));
            redirect('index.php?action=call_form' . ($id ? '&id=' . $id : ''));
        }
        // Every call must state its research theme (§20).
        $themeId = (int) ($_POST['theme'] ?? 0);
        if ($themeId === 0 || Database::scalar('SELECT COUNT(*) FROM research_themes WHERE id = ?', [$themeId]) == 0) {
            Session::flash('error', 'Please select the research theme for this call.');
            redirect('index.php?action=call_form' . ($id ? '&id=' . $id : ''));
        }
        $data = [
            'title'            => $clean['title'],
            'reference_number' => $clean['reference_number'],
            'description'      => trim((string) ($_POST['description'] ?? '')) ?: null,
            'research_priorities' => trim((string) ($_POST['priorities'] ?? '')) ?: null,
            'opens_at'         => ($_POST['opens_at'] ?? '') ?: null,
            'closes_at'        => ($_POST['closes_at'] ?? '') ?: null,
            'research_theme_id' => (int) ($_POST['theme'] ?? 0) ?: null,
        ];
        try {
            if ($id > 0) {
                CallModel::update($id, $data);
                Audit::log('call.updated', 'research_call', $id);
                Session::flash('success', 'Call updated.');
            } else {
                $data['call_number'] = NumberingService::next('research_call');
                $data['created_by']  = Auth::id();
                $id = CallModel::create($data);
                Audit::log('call.created', 'research_call', $id, null, ['title' => $data['title']]);
                Session::flash('success', 'Call created as draft.');
            }
        } catch (\PDOException $e) {
            Session::flash('error', str_contains($e->getMessage(), 'Duplicate') ? 'Reference number already exists.' : 'Database error.');
            redirect('index.php?action=call_form' . ($id ? '&id=' . $id : ''));
        }
        redirect('index.php?action=call_view&id=' . $id);
    }

    public static function saveConfiguration(): void
    {
        Gate::authorize('research.manage');
        Csrf::verifyRequest();
        $id = (int) ($_POST['id'] ?? 0);
        if (!CallModel::find($id)) {
            Session::flash('error', 'Call not found.');
            redirect('index.php?action=calls');
        }
        // Criteria are referenced by evaluation_scores (RESTRICT) — once any
        // evaluation is scored, the criteria set is locked to keep recorded
        // scores meaningful (changing them would silently rescale past scores).
        $scored = (int) Database::scalar(
            'SELECT COUNT(*) FROM evaluation_scores es
             JOIN research_call_criteria cc ON cc.id = es.criterion_id
             WHERE cc.call_id = ?',
            [$id]
        );
        if ($scored > 0 && count((array) ($_POST['crit_name'] ?? [])) > 0) {
            Session::flash('error', 'Evaluation criteria cannot be changed because evaluations have already been scored against them.');
            redirect('index.php?action=call_view&id=' . $id);
        }
        $elig = [];
        foreach ((array) ($_POST['eligible_colleges'] ?? []) as $cid) {
            $elig[] = ['key' => 'eligible_college', 'ref_id' => (int) $cid];
        }
        foreach ((array) ($_POST['eligible_departments'] ?? []) as $did) {
            $elig[] = ['key' => 'eligible_department', 'ref_id' => (int) $did];
        }
        foreach ((array) ($_POST['themes'] ?? []) as $tid) {
            $elig[] = ['key' => 'research_theme', 'ref_id' => (int) $tid];
        }
        foreach ((array) ($_POST['researcher_types'] ?? []) as $rt) {
            $elig[] = ['key' => 'researcher_type', 'ref_value' => (string) $rt];
        }
        CallModel::setEligibility($id, $elig);

        $reqs = [];
        $docTypes = (array) ($_POST['req_type'] ?? []);
        $reqKinds = (array) ($_POST['req_kind'] ?? []);
        foreach ($docTypes as $i => $typeId) {
            if (!(int) $typeId) {
                continue;
            }
            $reqs[] = [
                'document_type_id' => (int) $typeId,
                'requirement'      => in_array($reqKinds[$i] ?? '', ['required', 'optional', 'conditional'], true) ? $reqKinds[$i] : 'required',
                'condition_note'   => trim((string) ($_POST['req_note'][$i] ?? '')) ?: null,
            ];
        }
        CallModel::setRequirements($id, $reqs);

        $criteria = [];
        $names = (array) ($_POST['crit_name'] ?? []);
        $weights = (array) ($_POST['crit_weight'] ?? []);
        foreach ($names as $i => $name) {
            if (trim((string) $name) === '') {
                continue;
            }
            $criteria[] = [
                'name'     => trim((string) $name),
                'weight'   => is_numeric($weights[$i] ?? null) ? (float) $weights[$i] : 0,
                'description' => trim((string) ($_POST['crit_desc'][$i] ?? '')) ?: null,
            ];
        }
        CallModel::setCriteria($id, $criteria);

        Audit::log('call.configured', 'research_call', $id, null, ['criteria' => count($criteria), 'requirements' => count($reqs)]);
        Session::flash('success', 'Call configuration saved.');
        redirect('index.php?action=call_view&id=' . $id);
    }

    public static function publish(): void
    {
        Gate::authorize('research.manage');
        Csrf::verifyRequest();
        $id = (int) ($_POST['id'] ?? 0);
        $call = CallModel::find($id);
        if ($call === null) {
            Session::flash('error', 'Call not found.');
            redirect('index.php?action=calls');
        }
        if ($call['status'] !== 'draft') {
            Session::flash('error', 'Only draft calls can be published.');
            redirect('index.php?action=call_view&id=' . $id);
        }
        CallModel::update($id, ['status' => 'published', 'published_at' => date('Y-m-d H:i:s')]);
        \App\Services\NotificationService::sendToRoles(['researcher'], 'new_call', 'New research call: ' . $call['title'],
            'A research call was published.', 'index.php?action=calls_public', 'high');
        Audit::log('call.published', 'research_call', $id);
        Session::flash('success', 'Call published. Researchers were notified.');
        redirect('index.php?action=call_view&id=' . $id);
    }

    /** Public list of open calls for researchers (route: calls_public). */
    public static function indexPublic(): void
    {
        $rows = CallModel::openCalls();
        $p = ['page' => 1, 'pages' => 1, 'offset' => 0, 'limit' => max(1, count($rows))];
        $qs = '';
        $filters = ['status' => '', 'q' => ''];
        $appliedCallIds = self::appliedCallIds();
        include APP_PATH . '/views/research/calls.php';
    }

    /**
     * Call ids the current user already has a (non-draft) application for.
     * Used by the calls list to hide the Apply button once applied (§12 limit).
     */
    private static function appliedCallIds(): array
    {
        if (!Auth::check()) {
            return [];
        }
        $rows = Database::all(
            'SELECT DISTINCT call_id FROM research_applications WHERE pi_user_id = ?',
            [Auth::id()]
        );
        return array_map(static fn(array $r): int => (int) $r['call_id'], $rows);
    }

    public static function view(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $call = CallModel::find($id);
        if ($call === null) {
            Session::flash('error', 'Call not found.');
            redirect('index.php?action=calls');
        }
        $eligibility = CallModel::eligibility($id);
        $requirements = CallModel::requirements($id);
        $criteria = CallModel::criteria($id);
        $colleges = \App\Models\OrgModel::colleges();
        $themes = \App\Models\OrgModel::themes();
        $docTypes = \App\Models\OrgModel::documentTypes();
        include APP_PATH . '/views/research/call_view.php';
    }

    public static function changeStatus(): void
    {
        Gate::authorize('research.manage');
        Csrf::verifyRequest();
        $id = (int) ($_POST['id'] ?? 0);
        $status = (string) ($_POST['status'] ?? '');
        $allowed = ['draft', 'published', 'open', 'closed', 'under_evaluation', 'completed', 'archived'];
        if (!in_array($status, $allowed, true)) {
            Session::flash('error', 'Invalid status.');
            redirect('index.php?action=call_view&id=' . $id);
        }
        CallModel::update($id, ['status' => $status, 'closed_at' => $status === 'closed' ? date('Y-m-d H:i:s') : null]);
        Audit::log('call.status_changed', 'research_call', $id, null, ['status' => $status]);
        Session::flash('success', 'Call status updated.');
        redirect('index.php?action=call_view&id=' . $id);
    }
}
