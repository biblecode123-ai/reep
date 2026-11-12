<?php $title = 'My evaluations'; include APP_PATH . '/views/partials/header.php'; ?>

<div class="page-head">
  <div>
    <h1>My evaluations</h1>
    <p>Review and evaluate research proposals assigned to you (§20–§23). You see only your own assignments.</p>
  </div>
</div>

<!-- Summary Cards -->
<div class="grid grid-4">
  <div class="stat">
    <div class="num"><?= array_sum($counts) ?></div>
    <div class="lbl">Total Assigned</div>
  </div>
  <div class="stat">
    <div class="num"><?= (int) ($counts['assigned'] ?? 0) + (int) ($counts['accepted'] ?? 0) ?></div>
    <div class="lbl">Pending Evaluation</div>
  </div>
  <div class="stat">
    <div class="num"><?= (int) ($counts['completed'] ?? 0) ?></div>
    <div class="lbl">Submitted Evaluations</div>
  </div>
  <div class="stat">
    <div class="num"><?= (int) $overdueCount ?></div>
    <div class="lbl">Overdue Evaluations</div>
  </div>
</div>

<!-- Filters -->
<div class="card" style="margin-bottom: 20px;">
  <form method="get" action="index.php">
    <input type="hidden" name="action" value="my_evaluations">
    <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
      <div>
        <label>Search</label>
        <input type="text" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Title, number or investigator">
      </div>
      <div>
        <label>Evaluation level</label>
        <select name="level">
          <option value="">All levels</option>
          <option value="college" <?= ($_GET['level'] ?? '') === 'college' ? 'selected' : '' ?>>College level</option>
          <option value="university" <?= ($_GET['level'] ?? '') === 'university' ? 'selected' : '' ?>>University level</option>
        </select>
      </div>
      <div>
        <label>Filter by status</label>
        <select name="status">
          <option value="">All evaluations</option>
          <option value="assigned" <?= ($_GET['status'] ?? '') === 'assigned' ? 'selected' : '' ?>>Pending acceptance</option>
          <option value="accepted" <?= ($_GET['status'] ?? '') === 'accepted' ? 'selected' : '' ?>>In progress</option>
          <option value="completed" <?= ($_GET['status'] ?? '') === 'completed' ? 'selected' : '' ?>>Submitted</option>
          <option value="declined" <?= ($_GET['status'] ?? '') === 'declined' ? 'selected' : '' ?>>Declined</option>
          <option value="conflict_declared" <?= ($_GET['status'] ?? '') === 'conflict_declared' ? 'selected' : '' ?>>Conflict declared</option>
        </select>
      </div>
      <div>
        <label>Show overdue only</label>
        <input type="checkbox" name="overdue_only" value="1" <?= !empty($_GET['overdue_only']) ? 'checked' : '' ?>>
      </div>
      <div style="align-self:flex-end;">
        <button class="btn btn-outline" type="submit">Filter</button>
        <a class="btn btn-outline" href="<?= e(url('action=my_evaluations')) ?>">Clear</a>
      </div>
    </div>
  </form>
</div>

<?php if (!$assignments && !$projectAssignments): ?>
<div class="card">
  <p class="muted">
    <?php if (!empty($_GET['status']) || !empty($_GET['level']) || !empty($_GET['q']) || !empty($_GET['overdue_only'])): ?>
      No evaluations match your current filters. <a href="<?= e(url('action=my_evaluations')) ?>">Clear filters</a> to see all assignments.
    <?php else: ?>
      You have no evaluation assignments at this time. When a coordinator assigns you to evaluate a proposal, it will appear here.
    <?php endif; ?>
  </p>
</div>
<?php elseif ($assignments): ?>
<table class="tbl">
  <thead>
    <tr>
      <th>Proposal</th>
      <th>Principal investigator</th>
      <th>College / Department</th>
      <th>Level</th>
      <th>Assigned</th>
      <th>Deadline</th>
      <th>Status</th>
      <th class="actions">Actions</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($assignments as $as): ?>
    <?php
    $isOverdue = $as['deadline'] && strtotime($as['deadline']) < time() && !in_array($as['status'], ['completed','declined','cancelled','conflict_declared'], true);
    $hasDraft = (int) ($as['draft_count'] ?? 0) > 0;
    ?>
  <tr <?= $isOverdue ? 'class="danger"' : '' ?>>
    <td>
      <a href="<?= e(url('action=application_view&id=' . $as['application_id'])) ?>">
        <strong><?= e($as['application_number']) ?></strong>
      </a>
      <div><?= e(mb_strimwidth($as['application_title'], 0, 60, '…')) ?></div>
      <?php if (!empty($as['submitted_at'])): ?>
        <div class="muted" style="font-size:12px;">Submitted <?= e(format_date($as['submitted_at'])) ?></div>
      <?php endif; ?>
    </td>
    <td><?= e($as['pi_name'] ?? '—') ?></td>
    <td><?= e($as['college_name'] ?? '—') ?><div class="muted" style="font-size:12px;"><?= e($as['department_name'] ?? '—') ?></div></td>
    <td>
      <span class="badge <?= ($as['assignment_level'] ?? 'college') === 'university' ? 'badge-info' : 'badge-secondary' ?>">
        <?= ($as['assignment_level'] ?? 'college') === 'university' ? 'University' : 'College' ?>
      </span>
    </td>
    <td><?= e(format_date($as['assigned_at'])) ?></td>
    <td>
      <?= $as['deadline'] ? e(format_date($as['deadline'])) : '—' ?>
      <?php if ($isOverdue): ?>
        <span class="badge badge-danger">overdue</span>
      <?php elseif ($as['deadline'] && strtotime($as['deadline']) < strtotime('+3 days') && !in_array($as['status'], ['completed','declined','cancelled'], true)): ?>
        <span class="badge badge-warning">due soon</span>
      <?php endif; ?>
    </td>
    <td>
      <?php if ($as['status'] === 'completed'): ?>
        <span class="badge badge-success">Submitted</span>
      <?php elseif ($hasDraft): ?>
        <span class="badge badge-info">Draft saved</span>
      <?php else: ?>
        <span class="badge <?= $as['status'] === 'accepted' ? 'badge-info' : 'badge-secondary' ?>">
          <?= e(ucfirst(str_replace('_', ' ', $as['status']))) ?>
        </span>
      <?php endif; ?>
    </td>
    <td class="actions">
      <?php if (in_array($as['status'], ['assigned'], true)): ?>
        <form class="inline-form" method="post" action="<?= e(url('action=evaluator_respond')) ?>">
          <?= \App\Core\Csrf::field() ?>
          <input type="hidden" name="assignment_id" value="<?= (int) $as['id'] ?>">
          <input type="hidden" name="response" value="accept">
          <button class="btn btn-sm" type="submit">Accept</button>
        </form>
        <form class="inline-form" method="post" action="<?= e(url('action=evaluator_respond')) ?>" data-confirm="Decline this assignment?">
          <?= \App\Core\Csrf::field() ?>
          <input type="hidden" name="assignment_id" value="<?= (int) $as['id'] ?>">
          <input type="hidden" name="response" value="decline">
          <button class="btn btn-outline btn-sm" type="submit">Decline</button>
        </form>
        <form class="inline-form" method="post" action="<?= e(url('action=evaluator_respond')) ?>" data-confirm="Declare a conflict of interest for this assignment?">
          <?= \App\Core\Csrf::field() ?>
          <input type="hidden" name="assignment_id" value="<?= (int) $as['id'] ?>">
          <input type="hidden" name="response" value="conflict">
          <button class="btn btn-outline btn-sm" type="submit">Conflict</button>
        </form>
      <?php elseif (in_array($as['status'], ['accepted'], true)): ?>
        <a class="btn btn-sm" href="<?= e(url('action=evaluation_form&application_id=' . $as['application_id'])) ?>">
          <?= $hasDraft ? 'Continue Evaluation' : 'Open Evaluation' ?>
        </a>
      <?php elseif ($as['status'] === 'completed'): ?>
        <a class="btn btn-sm btn-outline" href="<?= e(url('action=evaluation_view&id=' . \App\Models\EvaluationModel::evaluationIdByAssignment((int) $as['id']))) ?>">View Evaluation</a>
      <?php elseif ($as['status'] === 'declined'): ?>
        <span class="muted">Declined</span>
      <?php elseif ($as['status'] === 'conflict_declared'): ?>
        <span class="muted">Conflict declared</span>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<?php if ($projectAssignments): ?>
<div class="card" style="margin-top:24px;">
  <h2 class="mt0">Completed Research Report Assignments</h2>
  <p class="muted">Independent College and University evaluation of completed research reports.</p>
  <table class="tbl">
    <thead><tr><th>Project / Report</th><th>PI / College</th><th>Stage</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($projectAssignments as $pa): ?>
      <tr>
        <td>
          <strong><?= e($pa['project_number']) ?> · v<?= (int) $pa['version'] ?></strong>
          <div><?= e($pa['project_title']) ?></div>
        </td>
        <td><?= e($pa['pi_name']) ?><div class="muted"><?= e($pa['college_name'] ?? '—') ?></div></td>
        <td><?= e(ucfirst($pa['assignment_level'])) ?></td>
        <td><?= e(str_replace('_', ' ', $pa['status'])) ?></td>
        <td class="actions">
          <?php if ($pa['status'] === 'assigned'): ?>
            <form method="post" action="<?= e(url('action=final_report_assignment_respond')) ?>">
              <?= \App\Core\Csrf::field() ?>
              <input type="hidden" name="assignment_id" value="<?= (int) $pa['id'] ?>">
              <select name="response" required>
                <option value="accepted">Accept</option>
                <option value="declined">Decline</option>
                <option value="conflict_declared">Declare conflict</option>
              </select>
              <input type="text" name="reason" maxlength="1000" placeholder="Reason required for decline/conflict">
              <button class="btn btn-sm" type="submit">Submit</button>
            </form>
          <?php elseif ($pa['status'] === 'accepted'): ?>
            <a class="btn btn-sm" href="<?= e(url('action=final_report_evaluation_form&final_report_id=' . (int) $pa['final_report_id'] . '&assignment_id=' . (int) $pa['id'])) ?>">Open evaluation</a>
          <?php elseif ($pa['status'] === 'completed'): ?>
            <span class="badge badge-success">Submitted</span>
          <?php elseif ($pa['status'] === 'conflict_declared'): ?>
            <span class="muted">Conflict declared</span>
          <?php elseif ($pa['status'] === 'declined'): ?>
            <span class="muted">Declined</span>
          <?php endif; ?>
          <a class="btn btn-outline btn-sm" href="<?= e(url('action=final_report_view&id=' . (int) $pa['final_report_id'])) ?>">View report</a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php include APP_PATH . '/views/partials/footer.php'; ?>
