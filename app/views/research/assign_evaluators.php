<?php
use App\Core\Database;
$title = 'Assign evaluators';
include APP_PATH . '/views/partials/header.php';
?>
<div class="page-head">
  <div><h1>Assign evaluators</h1>
  <p><?= e($app['application_number']) ?> — <?= e($app['title']) ?> ·
     Required evaluators: <strong><?= (int) ($app['required_evaluators'] ?? 2) ?></strong></p></div>
</div>

<?php if ($assignments): ?>
<div class="card">
  <h2 class="mt0">Currently assigned</h2>
  <table class="tbl">
    <tr><th>Evaluator</th><th>Status</th><th>Deadline</th><th></th></tr>
    <?php foreach ($assignments as $as): ?>
    <tr>
      <td><?= e($as['evaluator_name']) ?><div class="muted" style="font-size:12px;"><?= e($as['evaluator_department'] ?? '') ?></div></td>
      <td><span class="badge badge-info"><?= e(ucfirst($as['status'])) ?></span></td>
      <td><?= e(format_date($as['deadline'])) ?></td>
      <td class="actions">
        <form class="inline-form" method="post" action="<?= e(url('action=conflict_resolve')) ?>">
          <?= \App\Core\Csrf::field() ?>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php $openConflicts = \App\Models\EvaluationModel::openConflictsForApplication((int) $app['id']); ?>
  <?php if ($openConflicts): ?>
    <h3>Open conflicts of interest</h3>
    <?php foreach ($openConflicts as $c): ?>
    <div class="alert alert-warning">
      <strong><?= e($c['evaluator_name'] ?? 'Evaluator') ?></strong>: <?= e($c['description'] ?? 'Conflict declared') ?>
      <form method="post" action="<?= e(url('action=conflict_resolve')) ?>" style="margin-top:8px;display:flex;gap:8px;">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="conflict_id" value="<?= (int) $c['id'] ?>">
        <select name="resolution">
          <option value="reassign">Resolved — new evaluator will be assigned</option>
          <option value="override">Resolved — proceed anyway (coordinator decision, §75)</option>
        </select>
        <button class="btn btn-outline btn-sm" type="submit">Mark resolved</button>
      </form>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="card">
  <h2 class="mt0">Assign new evaluators</h2>
  <p class="muted" style="margin-bottom: 12px;">
    Eligibility rules: Must be from the same college, active, not a research team member, not already assigned,
    and must have capacity. Only eligible evaluators are shown below.
  </p>
  <form method="post" action="<?= e(url('action=assign_evaluator')) ?>">
    <?= \App\Core\Csrf::field() ?>
    <input type="hidden" name="id" value="<?= (int) $app['id'] ?>">
    <table class="tbl">
      <tr><th></th><th>Evaluator</th><th>Department</th><th>Expertise</th><th>Current Load</th><th>Status</th></tr>
      <?php if (isset($eligibleEvaluators) && $eligibleEvaluators): ?>
        <?php foreach ($eligibleEvaluators as $ev): ?>
        <tr>
          <td><input type="checkbox" name="evaluator_ids[]" value="<?= (int) $ev['id'] ?>"></td>
          <td><?= e($ev['full_name']) ?><div class="muted" style="font-size:12px;"><?= e($ev['college_name'] ?? '') ?></div></td>
          <td><?= e($ev['department_name'] ?? '—') ?></td>
          <td><?= e(mb_strimwidth($ev['expertise'] ?? '—', 0, 40, '…')) ?></td>
          <td><?= (int) $ev['current_load'] ?> / <?= (int) ($ev['max_concurrent_evaluations'] ?? 5) ?></td>
          <td>
            <span class="badge badge-success">Available</span>
            <?php if (!empty($ev['conflict_warning'])): ?>
              <div class="muted" style="font-size:11px;margin-top:4px;">⚠ <?= e($ev['conflict_warning']) ?></div>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="6" class="text-center muted">No eligible evaluators available in your college.</td>
        </tr>
      <?php endif; ?>
    </table>
    <div class="form-row" style="margin-top:12px;">
      <div><label>Evaluation deadline</label><input type="date" name="deadline" value="<?= e(date('Y-m-d', strtotime('+' . \App\Core\Settings::int('default_evaluation_days', 14) . ' days'))) ?>"></div>
    </div>
    <div class="form-actions">
      <button class="btn" type="submit" <?= !isset($eligibleEvaluators) || !$eligibleEvaluators ? 'disabled' : '' ?>>Assign & notify</button>
      <a class="btn btn-outline" href="<?= e(url('action=application_view&id=' . $app['id'])) ?>">Back</a>
    </div>
  </form>
</div>

<?php if (isset($ineligibleReasons) && $ineligibleReasons): ?>
<div class="card" style="margin-top: 20px;">
  <h2 class="mt0">Ineligible evaluators (from your college)</h2>
  <p class="muted">These evaluators are not eligible for assignment based on the rules above.</p>
  <table class="tbl">
    <tr><th>Evaluator</th><th>Department</th><th>Reason for ineligibility</th></tr>
    <?php foreach ($evaluators as $ev): ?>
      <?php if (isset($ineligibleReasons[$ev['id']])): ?>
      <tr>
        <td><?= e($ev['full_name']) ?></td>
        <td><?= e($ev['department_name'] ?? '—') ?></td>
        <td><span class="muted"><?= e($ineligibleReasons[$ev['id']]) ?></span></td>
      </tr>
      <?php endif; ?>
    <?php endforeach; ?>
  </table>
</div>
<?php endif; ?>

<?php if (\App\Core\Gate::allows('research.assign')): ?>
<div class="card">
  <h2 class="mt0">Create evaluator account <span class="muted" style="font-size:13px;font-weight:400;">(§5 — scoped to your college)</span></h2>
  <form method="post" action="<?= e(url('action=evaluator_create')) ?>">
    <?= \App\Core\Csrf::field() ?>
    <input type="hidden" name="application_id" value="<?= (int) $app['id'] ?>">
    <div class="form-row">
      <div><label>Full name <span class="req">*</span></label><input type="text" name="full_name" required></div>
      <div><label>Email <span class="req">*</span></label><input type="email" name="email" required></div>
    </div>
    <div class="form-row">
      <div><label>Phone</label><input type="text" name="phone"></div>
      <div><label>Temporary password (min 8) <span class="req">*</span></label><input type="text" name="password" required minlength="8" value="Password123!"></div>
    </div>
    <div class="form-row">
      <div>
        <label>Department</label>
        <select name="department_id">
          <option value="">—</option>
          <?php foreach (\App\Models\OrgModel::departments() as $d): ?>
            <option value="<?= (int) $d['id'] ?>"><?= e($d['name']) ?> (<?= e($d['college_name']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div><label>Expertise</label><input type="text" name="expertise" placeholder="e.g. Methodology, agronomy"></div>
    </div>
    <button class="btn btn-outline" type="submit">Create evaluator account</button>
    <p class="muted" style="font-size:12px;">The account gets the <strong>evaluator</strong> role only and must change its password at first login.</p>
  </form>
</div>
<?php endif; ?>

<?php include APP_PATH . '/views/partials/footer.php'; ?>

