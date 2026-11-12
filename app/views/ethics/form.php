<?php $title = 'Ethics application'; include APP_PATH . '/views/partials/header.php'; ?>

<div class="page-head">
  <div><h1>Ethics application <?= e($eth['application_number']) ?></h1>
  <p>Research: <?= e($eth['research_title']) ?></p></div>
</div>

<form method="post" action="<?= e(url('action=ethics_save')) ?>">
  <?= \App\Core\Csrf::field() ?>
  <input type="hidden" name="id" value="<?= (int) $eth['id'] ?>">

  <div class="card">
    <h2 class="mt0">Participants & risk (§32)</h2>
    <div class="form-row">
      <div><label>Participant count</label><input type="number" name="participant_count" min="0" value="<?= e((string) ($eth['participant_count'] ?? '')) ?>"></div>
      <div><label>Risk level</label>
        <select name="risk_level">
          <option value="">—</option>
          <?php foreach (['minimal','low','moderate','high'] as $rl): ?>
          <option value="<?= e($rl) ?>" <?= ($eth['risk_level'] ?? '') === $rl ? 'selected' : '' ?>><?= e(ucfirst($rl)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <label>Participant description</label>
    <textarea name="participant_description" style="min-height:70px;"><?= e($eth['participant_description'] ?? '') ?></textarea>
    <label>Research type summary</label>
    <input type="text" name="research_type_summary" maxlength="255" value="<?= e($eth['research_type_summary'] ?? '') ?>">
  </div>

  <div class="card">
    <h2 class="mt0">Ethics safeguards</h2>
    <label>Consent process</label>
    <textarea name="consent_process"><?= e($eth['consent_process'] ?? '') ?></textarea>
    <label>Confidentiality measures</label>
    <textarea name="confidentiality_measures"><?= e($eth['confidentiality_measures'] ?? '') ?></textarea>
    <label>Data collection summary</label>
    <textarea name="data_collection_summary"><?= e($eth['data_collection_summary'] ?? '') ?></textarea>
  </div>

  <div class="form-actions">
    <button class="btn" type="submit">Save ethics application</button>
    <a class="btn btn-outline" href="<?= e(url('action=ethics_view&id=' . $eth['id'])) ?>">Back</a>
  </div>
</form>

<?php include APP_PATH . '/views/partials/footer.php'; ?>

