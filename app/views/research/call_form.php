<?php $title = ($call === null ? 'New research call' : 'Edit call'); include APP_PATH . '/views/partials/header.php'; ?>

<div class="page-head">
  <div><h1><?= $call === null ? 'New research call' : 'Edit: ' . e($call['title']) ?></h1>
  <p>Basic call information. Associate one or more university-wide research themes with this call.</p></div>
</div>

<form method="post" action="<?= e(url('action=call_save')) ?>">
  <?= \App\Core\Csrf::field() ?>
  <input type="hidden" name="id" value="<?= (int) ($call['id'] ?? 0) ?>">

  <div class="card">
    <h2 class="mt0">Call basics</h2>
    <div class="form-row">
      <div>
        <label>Research themes <span class="req">*</span></label>
        <select name="themes[]" multiple required size="6" style="min-height:150px;">
          <?php $selectedThemeIds = array_map(static fn($t) => (int) $t['id'], $selectedThemes ?? []); ?>
          <?php if (empty($selectedThemeIds) && !empty($call['research_theme_id'])) $selectedThemeIds = [(int) $call['research_theme_id']]; ?>
          <?php foreach ($themes as $t): ?>
          <?php if (($t['status'] ?? 'active') === 'active' || in_array((int) $t['id'], $selectedThemeIds, true)): ?>
          <option value="<?= (int) $t['id'] ?>" <?= in_array((int) $t['id'], $selectedThemeIds, true) ? 'selected' : '' ?>><?= e($t['name']) ?></option>
          <?php endif; ?>
          <?php endforeach; ?>
        </select>
        <small class="muted">Hold Ctrl (Windows) or Command (Mac) to select multiple themes.</small>
      </div>
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
