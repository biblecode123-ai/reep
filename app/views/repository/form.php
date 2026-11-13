<?php
$title = $record === null ? 'Add research to repository' : 'Edit repository record';
include APP_PATH . '/views/partials/header.php';

$visibilities = ['public' => 'Public', 'university_only' => 'University only', 'college_only' => 'College only', 'restricted' => 'Restricted', 'confidential' => 'Confidential', 'embargoed' => 'Embargoed'];
$statuses = ['draft' => 'Draft', 'published' => 'Published', 'embargoed' => 'Embargoed', 'archived' => 'Archived', 'withdrawn' => 'Withdrawn'];
?>
<div class="page-head">
  <div>
    <h1><?= $record === null ? 'Add research to repository' : 'Edit: ' . e($record['repository_id_code']) ?></h1>
    <p>Only finished research (final report submitted or completed) can be uploaded. New records enter as <strong>draft</strong> and go live after the administrator approves them.</p>
  </div>
</div>

<form method="post" action="<?= e(url('action=repository_save')) ?>">
  <?= \App\Core\Csrf::field() ?>
  <input type="hidden" name="id" value="<?= (int) ($record['id'] ?? 0) ?>">

  <div class="card">
    <h2 class="mt0">Source research</h2>
    <?php if ($record === null): ?>
      <?php if (!$projects): ?>
        <p class="muted">No eligible projects. A research project must be active or completed before it can be added to the repository.</p>
      <?php else: ?>
      <div class="form-row">
        <div><label>Research project <span class="req">*</span></label>
          <select name="project_id" required>
            <option value="">— select project —</option>
            <?php foreach ($projects as $p): ?>
            <option value="<?= (int) $p['id'] ?>"><?= e($p['project_number'] . ' · ' . mb_strimwidth($p['title'], 0, 70, '…')) ?></option>
            <?php endforeach; ?>
          </select></div>
      </div>
      <?php endif; ?>
    <?php else: ?>
      <div class="form-row">
        <div><label>Research project</label><input type="text" value="<?= e($record['project_number'] . ' · ' . $record['project_title']) ?>" disabled></div>
        <div><label>Repository code</label><input type="text" value="<?= e($record['repository_id_code']) ?>" disabled></div>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2 class="mt0">Record details</h2>
    <div class="form-row">
      <div style="flex:2;"><label>Title <span class="req">*</span></label><input type="text" name="title" required maxlength="255" value="<?= e($record['title'] ?? '') ?>"></div>
      <div><label>Year</label><input type="number" name="year" min="1990" max="2100" value="<?= e((string) ($record['year'] ?? date('Y'))) ?>"></div>
    </div>
    <div class="form-row">
      <div style="flex:2;"><label>Abstract</label><textarea name="abstract" rows="5" maxlength="5000"><?= e($record['abstract'] ?? '') ?></textarea></div>
    </div>
    <div class="form-row">
      <div><label>Keywords (many)</label><input type="text" name="keywords" maxlength="500" value="<?= e($record['keywords'] ?? '') ?>" placeholder="comma separated (e.g., kw1, kw2, kw3)"></div>
      <div><label>Theme</label>
        <select name="research_theme_id"><option value="">—</option>
        <?php foreach ($themes as $t): ?>
          <option value="<?= (int) $t['id'] ?>" <?= (int) ($record['research_theme_id'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option>
        <?php endforeach; ?>
        </select></div>
    </div>
    <div class="form-row">
      <div><label>College</label>
        <select name="college_id"><option value="">—</option>
        <?php foreach ($colleges as $c): ?>
          <option value="<?= (int) $c['id'] ?>" <?= (int) ($record['college_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
        </select></div>
      <div><label>Department</label>
        <select name="department_id"><option value="">—</option>
        <?php foreach ($departments as $d): ?>
          <option value="<?= (int) $d['id'] ?>" <?= (int) ($record['department_id'] ?? 0) === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
        <?php endforeach; ?>
        </select></div>
    </div>
    <div class="form-row">
      <div style="flex:2;"><label>Citation</label><textarea name="citation_text" rows="2" maxlength="2000" placeholder="How this work should be cited"><?= e($record['citation_text'] ?? '') ?></textarea></div>
    </div>
  </div>

  <div class="card">
    <h2 class="mt0">Access &amp; status</h2>
    <div class="form-row">
      <div><label>Visibility</label>
        <select name="visibility">
        <?php foreach ($visibilities as $v => $lbl): ?>
          <option value="<?= e($v) ?>" <?= ($record['visibility'] ?? 'university_only') === $v ? 'selected' : '' ?>><?= e($lbl) ?></option>
        <?php endforeach; ?>
        </select></div>
      <?php if ($record !== null): ?>
      <div><label>Status</label>
        <select name="status">
        <?php foreach ($statuses as $s => $lbl): ?>
          <option value="<?= e($s) ?>" <?= ($record['status'] ?? 'draft') === $s ? 'selected' : '' ?>><?= e($lbl) ?></option>
        <?php endforeach; ?>
        </select></div>
      <?php else: ?>
      <div><label>Status</label><input type="text" value="Draft — awaiting admin approval" disabled></div>
      <?php endif; ?>
    </div>
  </div>

  <div class="form-actions">
    <button class="btn" type="submit"><?= $record === null ? 'Add to repository' : 'Save changes' ?></button>
    <a class="btn btn-outline" href="<?= e(url($record === null ? 'action=repository' : 'action=repository_view&id=' . (int) $record['id'])) ?>">Cancel</a>
  </div>
</form>

<?php include APP_PATH . '/views/partials/footer.php'; ?>
