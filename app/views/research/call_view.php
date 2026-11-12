<?php
use App\Core\Database;
use App\Models\CallModel;
$title = 'Call: ' . ($call['title'] ?? '');
include APP_PATH . '/views/partials/header.php';
$appCount = (int) Database::scalar('SELECT COUNT(*) FROM research_applications WHERE call_id = ?', [(int) $call['id']]);
?>

<div class="page-head">
  <div>
    <h1><?= e($call['title'] ?? '') ?></h1>
    <p><?= e($call['call_number'] ?? '') ?> · Ref: <?= e($call['reference_number'] ?? '') ?> ·
       <span class="badge <?= ($call['status'] ?? '') === 'open' || ($call['status'] ?? '') === 'published' ? 'badge-success' : 'badge-secondary' ?>"><?= e(ucfirst($call['status'] ?? '')) ?></span></p>
  </div>
  <div style="display:flex;gap:8px;">
    <?php if (Gate::allows('research.manage')): ?>
      <a class="btn btn-outline" href="<?= e(url('action=call_form&id=' . $call['id'])) ?>">Edit basics</a>
      <?php if ($call['status'] === 'draft'): ?>
      <form method="post" action="<?= e(url('action=call_publish')) ?>" data-confirm="Publish this call and notify researchers?">
        <?= \App\Core\Csrf::field() ?><input type="hidden" name="id" value="<?= (int) $call['id'] ?>">
        <button class="btn" type="submit">Publish</button>
      </form>
      <?php else: ?>
      <form method="post" action="<?= e(url('action=call_status')) ?>">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="id" value="<?= (int) $call['id'] ?>">
        <select name="status">
          <?php foreach (['published','open','closed','under_evaluation','completed','archived'] as $st): ?>
          <option value="<?= e($st) ?>" <?= $call['status'] === $st ? 'selected' : '' ?>><?= e(ucfirst($st)) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-outline" type="submit">Update status</button>
      </form>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<div class="grid grid-2">
  <div class="card">
    <h2 class="mt0">Description</h2>
    <p style="white-space:pre-wrap;"><?= e($call['description'] ?? '—') ?></p>
    <h3>Priorities</h3>
    <p style="white-space:pre-wrap;"><?= e($call['research_priorities'] ?? '—') ?></p>
    <dl class="kv">
      <dt>Research theme</dt><dd><?= e($call['theme_name'] ?? '—') ?></dd>
      <dt>Opens</dt><dd><?= e(format_datetime($call['opens_at'] ?? '')) ?></dd>
      <dt>Closes</dt><dd><?= e(format_datetime($call['closes_at'] ?? '')) ?></dd>
      <dt>Research window</dt><dd><?= e(format_date($call['research_start_date'] ?? '')) ?> → <?= e(format_date($call['completion_deadline'] ?? '')) ?></dd>
      <dt>Max budget</dt><dd><?= e(format_money($call['max_budget'] ?? null, $call['budget_currency'] ?? 'ETB')) ?></dd>
      <dt>Max duration</dt><dd><?= e((string) ($call['max_duration_months'] ?? '—')) ?> months</dd>
      <dt>Evaluation</dt><dd><?= e(ucfirst(str_replace('_', ' ', $call['evaluation_mode'] ?? ''))) ?> · <?= (int) ($call['required_evaluators'] ?? 0) ?> evaluator(s)</dd>
      <dt>Applications</dt><dd><?= $appCount ?></dd>
    </dl>
  </div>

  <?php if (Gate::allows('research.manage')): ?>
  <div class="card">
    <h2 class="mt0">Eligibility, documents & criteria</h2>
    <form method="post" action="<?= e(url('action=call_config')) ?>">
      <?= \App\Core\Csrf::field() ?>
      <input type="hidden" name="id" value="<?= (int) $call['id'] ?>">

      <h3>Eligible colleges</h3>
      <label style="font-weight:600;"><input type="checkbox" id="selectAllColleges" onclick="toggleAllColleges()"> Select All Colleges</label>
      <div style="columns:2;">
        <?php foreach ($colleges as $col): ?>
        <label style="font-weight:400;"><input type="checkbox" class="college-checkbox" name="eligible_colleges[]" value="<?= (int) $col['id'] ?>"
          <?= in_array((int) $col['id'], array_map(fn($r) => (int) ($r['ref_id'] ?? 0), array_filter($eligibility, fn($r) => $r['eligibility_key'] === 'eligible_college'))) ? 'checked' : '' ?>>
          <?= e($col['name']) ?></label>
        <?php endforeach; ?>
      </div>
      <p class="muted" style="font-size:12px;">Leave empty = all colleges eligible (POLICY DECISION: confirm default).</p>

      <h3>Research themes</h3>
      <label style="font-weight:600;"><input type="checkbox" id="selectAllThemes" onclick="toggleAllThemes()"> Select All Themes</label>
      <div style="columns:2;">
        <?php foreach ($themes as $th): ?>
        <label style="font-weight:400;"><input type="checkbox" class="theme-checkbox" name="themes[]" value="<?= (int) $th['id'] ?>"
          <?= in_array((int) $th['id'], array_map(fn($r) => (int) ($r['ref_id'] ?? 0), array_filter($eligibility, fn($r) => $r['eligibility_key'] === 'research_theme'))) ? 'checked' : '' ?>>
          <?= e($th['name']) ?></label>
        <?php endforeach; ?>
      </div>

      <div class="form-actions">
        <button class="btn" type="submit">Save configuration</button>
      </div>
    </form>
  </div>
  <?php endif; ?>
</div>

<script>
function toggleAllColleges() {
  const selectAll = document.getElementById('selectAllColleges');
  const checkboxes = document.querySelectorAll('.college-checkbox');
  checkboxes.forEach(cb => cb.checked = selectAll.checked);
}

function toggleAllThemes() {
  const selectAll = document.getElementById('selectAllThemes');
  const checkboxes = document.querySelectorAll('.theme-checkbox');
  checkboxes.forEach(cb => cb.checked = selectAll.checked);
}
</script>

<?php include APP_PATH . '/views/partials/footer.php'; ?>

