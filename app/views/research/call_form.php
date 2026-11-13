<?php $title = ($call === null ? 'New research call' : 'Edit call'); include APP_PATH . '/views/partials/header.php'; ?>

<div class="page-head">
  <div><h1><?= $call === null ? 'New research call' : 'Edit: ' . e($call['title']) ?></h1>
  <p>Basic call information. Eligibility, documents and criteria are configured on the call page after saving.</p></div>
</div>

<form method="post" action="<?= e(url('action=call_save')) ?>">
  <?= \App\Core\Csrf::field() ?>
  <input type="hidden" name="id" value="<?= (int) ($call['id'] ?? 0) ?>">

  <div class="card">
    <h2 class="mt0">Call basics</h2>
    <div class="form-row">
      <div><label>Title <span class="req">*</span></label><input type="text" name="title" required maxlength="200" value="<?= e($call['title'] ?? '') ?>"></div>
      <div><label>Reference number <span class="req">*</span></label><input type="text" name="reference_number" required maxlength="60" value="<?= e($call['reference_number'] ?? '') ?>"></div>
    </div>
    <div class="form-row">
      <div><label></label>
        <select name="theme" required>
          <option value="">— select research theme —</option>
          <?php foreach ($themes as $t): ?>
          <option value="<?= (int) $t['id'] ?>" <?= (int) ($call['research_theme_id'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option>
          <?php endforeach; ?>
        </select></div>
    </div>
    <label>Description</label>
    <textarea name="description"><?= e($call['description'] ?? '') ?></textarea>
    <label>Research priorities</label>
    <textarea name="priorities" style="min-height:70px;"><?= e($call['research_priorities'] ?? '') ?></textarea>
    <div class="form-row">
      <div><label>Opening date</label><input type="datetime-local" name="opens_at" value="<?= e($call['opens_at'] ?? '') ?>"></div>
      <div><label>Closing date</label><input type="datetime-local" name="closes_at" value="<?= e($call['closes_at'] ?? '') ?>"></div>
    </div>
  </div>

  <div class="form-actions">
    <button class="btn" type="submit">Save call</button>
    <a class="btn btn-outline" href="<?= e(url('action=calls')) ?>">Cancel</a>
  </div>
</form>

<?php include APP_PATH . '/views/partials/footer.php'; ?>
