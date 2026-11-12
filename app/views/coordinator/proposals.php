<?php
use App\Core\Auth;
use App\Core\Gate;
$title = 'College Research Proposals';
include APP_PATH . '/views/partials/header.php';

$collegeId = (int) (Auth::user()['college_id'] ?? 0);
if ($collegeId === 0) {
    echo '<div class="alert alert-error">Your account has no college assigned. Contact an administrator.</div>';
    include APP_PATH . '/views/partials/footer.php';
    exit;
}

// Filters
$search = $_GET['q'] ?? '';
$callFilter = $_GET['call_id'] ?? '';
$deptFilter = $_GET['department_id'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$eligibilityFilter = $_GET['eligibility'] ?? '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;

// Build query
$where = ['a.college_id = ?'];
$params = [$collegeId];

if (!empty($search)) {
    $where[] = '(a.title LIKE ? OR a.application_number LIKE ? OR u.full_name LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if (!empty($callFilter)) {
    $where[] = 'a.call_id = ?';
    $params[] = (int) $callFilter;
}

if (!empty($deptFilter)) {
    $where[] = 'a.department_id = ?';
    $params[] = (int) $deptFilter;
}

if (!empty($statusFilter)) {
    $where[] = 'a.status_code = ?';
    $params[] = $statusFilter;
}

if (!empty($eligibilityFilter)) {
    if ($eligibilityFilter === 'pending') {
        $where[] = 'cd.id IS NULL AND a.status_code NOT IN ("REJECTED_BY_COLLEGE","FORWARDED_TO_UNIVERSITY")';
    } elseif ($eligibilityFilter === 'eligible') {
        $where[] = "cd.decision = 'forward_to_evaluation'";
    } elseif ($eligibilityFilter === 'returned') {
        $where[] = "cd.decision = 'return_for_correction'";
    } elseif ($eligibilityFilter === 'rejected') {
        $where[] = "cd.decision = 'reject_ineligible'";
    }
}

$whereSql = implode(' AND ', $where);

// Get total count
$total = (int) \App\Core\Database::scalar(
    "SELECT COUNT(*) FROM research_applications a JOIN users u ON u.id = a.pi_user_id WHERE $whereSql",
    $params
);

// Get proposals with evaluation progress
$offset = ($page - 1) * $perPage;
$proposals = \App\Core\Database::all(
    "SELECT a.id, a.application_number, a.title, a.status_code, a.submitted_at,
            u.full_name AS pi_name, d.name AS department_name,
            rc.title AS call_title, rc.required_evaluators,
            rc.required_documents,
            ws.stage_name AS stage_label,
            cd.decision AS screening_decision,
            cd.comments AS screening_comments,
            cd.decided_at AS screening_decided_at,
            (SELECT COUNT(*) FROM documents de WHERE de.entity_type = 'research_application' AND de.entity_id = a.id AND de.status = 'active') AS documents_uploaded,
            (SELECT COUNT(*) FROM evaluator_assignments ea WHERE ea.application_id = a.id AND ea.status IN ('assigned','accepted')) AS assigned_evaluators,
            (SELECT COUNT(*) FROM evaluator_assignments ea WHERE ea.application_id = a.id AND ea.status = 'completed') AS completed_evaluators,
            (SELECT COUNT(*) FROM evaluations e WHERE e.application_id = a.id AND e.status = 'submitted') AS submitted_evaluations,
            (SELECT MAX(ea.deadline) FROM evaluator_assignments ea WHERE ea.application_id = a.id AND ea.status IN ('assigned','accepted')) AS evaluation_deadline
     FROM research_applications a
     JOIN users u ON u.id = a.pi_user_id
     LEFT JOIN departments d ON d.id = a.department_id
     LEFT JOIN research_calls rc ON rc.id = a.call_id
     LEFT JOIN workflow_stages ws ON ws.id = a.current_stage_id
     LEFT JOIN college_decisions cd ON cd.application_id = a.id AND cd.decision_context = 'screening'
     WHERE $whereSql
     ORDER BY a.submitted_at DESC, a.created_at DESC
     LIMIT $perPage OFFSET $offset",
    $params
);

// Get available filters
$calls = \App\Core\Database::all(
    "SELECT DISTINCT rc.id, rc.title, rc.reference_number
     FROM research_calls rc
     JOIN research_applications a ON a.call_id = rc.id
     WHERE a.college_id = ?
     ORDER BY rc.title",
    [$collegeId]
);

$departments = \App\Core\Database::all(
    "SELECT DISTINCT d.id, d.name
     FROM departments d
     JOIN research_applications a ON a.department_id = d.id
     WHERE a.college_id = ?
     ORDER BY d.name",
    [$collegeId]
);

// Status options for filter
$statusOptions = [
    'SUBMITTED_TO_RPEE' => 'Submitted to RPEE',
    'COLLEGE_RECEIVED' => 'College Received',
    'UNDER_COLLEGE_SCREENING' => 'Under College Screening',
    'EVALUATOR_ASSIGNMENT_PENDING' => 'Awaiting Evaluator Assignment',
    'EVALUATOR_ASSIGNED' => 'Evaluators Assigned',
    'UNDER_COLLEGE_EVALUATION' => 'Under College Evaluation',
    'AWAITING_COLLEGE_DECISION' => 'Awaiting College Decision',
    'AWAITING_COLLEGE_REVISION' => 'Awaiting College Revision',
    'REJECTED_BY_COLLEGE' => 'Rejected by College',
    'FORWARDED_TO_UNIVERSITY' => 'Forwarded to University',
];

// Eligibility filter options
$eligibilityOptions = [
    'pending' => 'Pending Screening',
    'eligible' => 'Eligible for Evaluation',
    'returned' => 'Returned for Correction',
    'rejected' => 'Rejected at College',
];
?>

<div class="page-head">
  <div>
    <h1>College Research Proposals</h1>
    <p>Manage proposals submitted to your college</p>
  </div>
</div>

<!-- Filters -->
<form id="filter-form" method="get" action="<?= e(url('action=coordinator_proposals')) ?>">
  <div class="filters">
    <div>
      <label>Search</label>
      <input type="text" name="q" value="<?= e($search) ?>" placeholder="Title, number, or PI">
    </div>
    <div>
      <label>Research Call</label>
      <select name="call_id">
        <option value="">All Calls</option>
        <?php foreach ($calls as $c): ?>
          <option value="<?= (int) $c['id'] ?>" <?= $callFilter == (string) $c['id'] ? 'selected' : '' }}>
            <?= e($c['title']) ?> (<?= e($c['reference_number']) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label>Department</label>
      <select name="department_id">
        <option value="">All Departments</option>
        <?php foreach ($departments as $d): ?>
          <option value="<?= (int) $d['id'] ?>" <?= $deptFilter == (string) $d['id'] ? 'selected' : '' ?>">
            <?= e($d['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label>Status</label>
      <select name="status">
        <option value="">All Statuses</option>
        <?php foreach ($statusOptions as $code => $label): ?>
          <option value="<?= e($code) ?>" <?= $statusFilter === $code ? 'selected' : '' ?>">
            <?= e($label) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label>Eligibility</label>
      <select name="eligibility">
        <option value="">All Eligibility</option>
        <?php foreach ($eligibilityOptions as $code => $label): ?>
          <option value="<?= e($code) ?>" <?= $eligibilityFilter === $code ? 'selected' : '' ?>">
            <?= e($label) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <button class="btn" type="submit">Filter</button>
      <a class="btn btn-outline" href="<?= e(url('action=coordinator_proposals')) ?>">Clear</a>
    </div>
  </div>
</form>

<!-- Proposals Table -->
<?php if ($proposals): ?>
<div class="card">
  <table class="tbl">
    <thead>
      <tr>
        <th>Number</th>
        <th>Title</th>
        <th>Principal Investigator</th>
        <th>Department</th>
        <th>Call</th>
        <th>Submitted</th>
        <th>Evaluation Progress</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($proposals as $p): ?>
        <?php
        $required = (int) ($p['required_evaluators'] ?? 2);
        $assigned = (int) $p['assigned_evaluators'];
        $completed = (int) $p['submitted_evaluations'];
        $progress = $required > 0 ? min(100, round(($completed / $required) * 100)) : 0;
        ?>
        <tr>
          <td><?= e($p['application_number']) ?></td>
          <td><?= e($p['title']) ?></td>
          <td><?= e($p['pi_name']) ?></td>
          <td><?= e($p['department_name'] ?? '—') ?></td>
          <td><?= e($p['call_title'] ?? '—') ?></td>
          <td><?= e(format_date($p['submitted_at'])) ?></td>
          <td>
            <div style="display:flex;align-items:center;gap:8px;">
              <div style="flex:1;height:6px;background:var(--line);border-radius:3px;overflow:hidden;">
                <div style="height:100%;background:var(--brand);width:<?= $progress ?>%;"></div>
              </div>
              <span style="font-size:12px;"><?= $completed ?>/<?= $required ?> completed</span>
            </div>
            <?php if ($p['evaluation_deadline']): ?>
              <div style="font-size:11px;color:var(--muted);">Deadline: <?= e(format_date($p['evaluation_deadline'])) ?></div>
            <?php endif; ?>
          </td>
          <td><span class="badge <?= status_badge($p['status_code']) ?>"><?= e($p['stage_label'] ?? status_label($p['status_code'])) ?></span></td>
          <td class="actions">
            <a class="btn btn-sm" href="<?= e(url('action=application_view&id=' . $p['id'])) ?>">View</a>
            <?php if (Gate::allows('research.screen') && in_array($p['status_code'], ['SUBMITTED_TO_RPEE', 'COLLEGE_RECEIVED', 'UNDER_COLLEGE_SCREENING'], true)): ?>
              <form method="post" action="<?= e(url('action=screening_decide')) ?>" style="display:inline;">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                <input type="hidden" name="decision" value="forward_to_evaluation">
                <button class="btn btn-sm btn-outline" type="submit" data-confirm="Mark this proposal as eligible for evaluation?">Screen</button>
              </form>
            <?php endif; ?>
            <?php if (Gate::allows('research.assign') && $p['status_code'] === 'EVALUATOR_ASSIGNMENT_PENDING'): ?>
              <a class="btn btn-sm" href="<?= e(url('action=assign_evaluators&id=' . $p['id'])) ?>">Assign</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Pagination -->
<div class="pagination">
  <?php if ($page > 1): ?>
    <a class="page-item" href="<?= e(url('action=coordinator_proposals&page=' . ($page - 1) . '&q=' . urlencode($search) . '&call_id=' . $callFilter . '&department_id=' . $deptFilter . '&status=' . $statusFilter)) ?>">Previous</a>
  <?php endif; ?>
  <span class="page-item">Page <?= $page ?> of <?= max(1, ceil($total / $perPage)) ?></span>
  <?php if ($page * $perPage < $total): ?>
    <a class="page-item" href="<?= e(url('action=coordinator_proposals&page=' . ($page + 1) . '&q=' . urlencode($search) . '&call_id=' . $callFilter . '&department_id=' . $deptFilter . '&status=' . $statusFilter)) ?>">Next</a>
  <?php endif; ?>
</div>

<?php else: ?>
<div class="card">
  <p class="muted">No proposals found matching your filters.</p>
  <?php if ($search || $callFilter || $deptFilter || $statusFilter): ?>
    <a class="btn btn-outline" href="<?= e(url('action=coordinator_proposals')) ?>">Clear filters</a>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php include APP_PATH . '/views/partials/footer.php'; ?>
