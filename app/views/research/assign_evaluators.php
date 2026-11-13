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
    <h3>Open conflicts of interest (§21)</h3>
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
      <tr><th></th><th>Evaluator</th><th>Department</th><th>Expertise</th><th>Current Load</th><th>Status</th><th>Action</th></tr>
      <?php if (isset($evaluators) && $evaluators): ?>
        <?php foreach ($evaluators as $ev): ?>
        <?php $isEligible = !isset($ineligibleReasons[$ev['id']]); ?>
        <tr>
          <td><?php if ($isEligible): ?><input type="checkbox" name="evaluator_ids[]" value="<?= (int) $ev['id'] ?>"><?php endif; ?></td>
          <td><?= e($ev['full_name']) ?><div class="muted" style="font-size:12px;"><?= e($ev['college_name'] ?? '') ?></div></td>
          <td><?= e($ev['department_name'] ?? '—') ?></td>
          <td><?= e(mb_strimwidth($ev['expertise'] ?? '—', 0, 40, '…')) ?></td>
          <td><?= (int) $ev['current_load'] ?> / <?= (int) ($ev['max_concurrent_evaluations'] ?? 5) ?></td>
          <td>
            <?php if ($isEligible): ?>
              <span class="badge badge-success">Available</span>
              <?php if (!empty($ev['conflict_warning'])): ?>
                <div class="muted" style="font-size:11px;margin-top:4px;">⚠ <?= e($ev['conflict_warning']) ?></div>
              <?php endif; ?>
            <?php else: ?>
              <span class="badge badge-warning">Ineligible</span>
              <div class="muted" style="font-size:11px;margin-top:4px;"><?= e($ineligibleReasons[$ev['id']]) ?></div>
            <?php endif; ?>
          </td>
          <td>
            <form method="post" action="<?= e(url('action=assign_evaluator')) ?>" class="inline-form">
              <?= \App\Core\Csrf::field() ?>
              <input type="hidden" name="id" value="<?= (int) $app['id'] ?>">
              <input type="hidden" name="evaluator_ids[]" value="<?= (int) $ev['id'] ?>">
              <button class="btn btn-sm <?= $isEligible ? 'btn-outline' : 'btn-outline' ?>" type="submit">Assign</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="7" class="text-center muted">No evaluators available in your college.</td>
        </tr>
      <?php endif; ?>
    </table>
    <div class="form-row" style="margin-top:12px;">
      <div><label>Evaluation deadline</label><input type="date" name="deadline" value="<?= e(date('Y-m-d', strtotime('+' . \App\Core\Settings::int('default_evaluation_days', 14) . ' days'))) ?>"></div>
    </div>
    <div class="form-actions">
      <button class="btn" type="submit">Assign & notify</button>
      <a class="btn btn-outline" href="<?= e(url('action=application_view&id=' . $app['id'])) ?>">Back</a>
    </div>
  </form>
</div>

<?php include APP_PATH . '/views/partials/footer.php'; ?>
