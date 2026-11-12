<?php $title = ($app === null ? 'New application' : 'Edit application'); include APP_PATH . '/views/partials/header.php'; ?>

<div class="page-head">
  <div>
    <h1><?= $app === null ? 'New research application' : 'Edit: ' . e($app['title']) ?></h1>
    <p>Your college and department are taken from your profile automatically (spec §17).</p>
  </div>
</div>

<form method="post" action="<?= e(url('action=application_save')) ?>" enctype="multipart/form-data">
  <?= \App\Core\Csrf::field() ?>
  <input type="hidden" name="id" value="<?= (int) ($app['id'] ?? 0) ?>">

  <div class="card">
    <h2 class="mt0">Basic information</h2>
    <div class="form-row">
      <div>
        <label>Call <span class="req">*</span></label>
        <select name="call_id" required <?= $app !== null ? 'disabled' : '' ?>>
          <option value="">— select call —</option>
          <?php foreach ($openCalls as $c): ?>
          <option value="<?= (int) $c['id'] ?>" <?= (int) ($app['call_id'] ?? ($_GET['call_id'] ?? 0)) === (int) $c['id'] ? 'selected' : '' ?>>
            <?= e($c['title']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div><label>Research title <span class="req">*</span></label><input type="text" name="title" required maxlength="250" value="<?= e($app['title'] ?? '') ?>"></div>
    </div>
    <div class="form-row">
      <div><label>Research theme</label>
        <select name="research_theme_id">
          <option value="">—</option>
          <?php foreach ($themes as $t): ?>
          <option value="<?= (int) $t['id'] ?>" <?= (int) ($app['research_theme_id'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div><label>Requires ethical review?</label>
        <select name="requires_ethics">
          <option value="no" <?= ($app['requires_ethics'] ?? 'no') === 'no' ? 'selected' : '' ?>>No</option>
          <option value="yes" <?= ($app['requires_ethics'] ?? '') === 'yes' ? 'selected' : '' ?>>Yes</option>
          <option value="unsure" <?= ($app['requires_ethics'] ?? '') === 'unsure' ? 'selected' : '' ?>>Unsure (route to ethics screening)</option>
        </select>
      </div>
    </div>
  </div>

  <div class="card">
    <h2 class="mt0">Proposal document</h2>
    <p>Upload the full proposal as a <strong>single PDF or Word document</strong> (`.pdf`, `.docx`).</p>
    <p>The proposal document must contain the following sections in this order: Abstract, Background, Problem statement, Objectives, Research questions, Literature review, Methodology, Expected results, Significance, Innovation, References.</p>
    <label>Upload Proposal <span class="req">*</span></label>
    <input type="file" name="proposal_document" accept=".pdf,.docx" required>
  </div>




  <div class="form-actions">
    <button class="btn" type="submit">Save application</button>
    <a class="btn btn-outline" href="<?= e(url('action=applications')) ?>">Cancel</a>
  </div>
</form>

<?php include APP_PATH . '/views/partials/footer.php'; ?>

