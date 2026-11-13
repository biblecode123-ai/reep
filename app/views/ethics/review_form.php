<?php $title = 'Ethics review'; include APP_PATH . '/views/partials/header.php'; ?>

<div class="page-head">
  <div><h1>Ethics review: <?= e($review['application_number']) ?></h1>
  <p><?= e($review['research_title']) ?> · Deadline: <?= e(format_date($review['review_deadline'])) ?></p></div>
</div>

<div class="card">
  <h2 class="mt0">Application summary</h2>
  <dl class="kv">
    <dt>Risk level</dt><dd><?= e($eth['risk_level'] ? ucfirst($eth['risk_level']) : '—') ?></dd>
    <dt>Participants</dt><dd><?= e((string) ($eth['participant_count'] ?? '—')) ?><?= $eth['participant_description'] ? ' — ' . e($eth['participant_description']) : '' ?></dd>
  </dl>
  <h3>Consent process</h3><p style="white-space:pre-wrap;"><?= e($eth['consent_process'] ?? '—') ?></p>
  <h3>Confidentiality measures</h3><p style="white-space:pre-wrap;"><?= e($eth['confidentiality_measures'] ?? '—') ?></p>
  <h3>Data collection summary</h3><p style="white-space:pre-wrap;"><?= e($eth['data_collection_summary'] ?? '—') ?></p>
</div>

<?php if ($review['status'] === 'assigned'): ?>
<div class="card">
  <h2 class="mt0">Accept this review?</h2>
  <form method="post" action="<?= e(url('action=ethics_review_respond')) ?>" style="display:flex;gap:8px;">
    <?= \App\Core\Csrf::field() ?>
    <input type="hidden" name="id" value="<?= (int) $review['id'] ?>">
    <button class="btn" name="action_type" value="accept" type="submit">Accept</button>
    <button class="btn btn-danger" name="action_type" value="decline" type="submit">Decline</button>
  </form>
</div>
<?php else: ?>
<form method="post" action="<?= e(url('action=ethics_review_respond')) ?>">
  <?= \App\Core\Csrf::field() ?>
  <input type="hidden" name="id" value="<?= (int) $review['id'] ?>">
  <input type="hidden" name="action_type" value="submit">

  <div class="card">
    <h2 class="mt0">Review (§32)</h2>
    <label>Comments</label>
    <textarea name="comments" placeholder="Ethical considerations, required safeguards, concerns…"></textarea>
    <label>Recommendation <span class="req">*</span></label>
    <select name="recommendation" required>
      <option value="">— select —</option>
      <option value="approve">Approve</option>
      <option value="approve_with_conditions">Approve with conditions</option>
      <option value="revision">Revision required</option>
      <option value="reject">Reject</option>
      <option value="exempt">Exempt</option>
    </select>
  </div>
  <div class="form-actions">
    <button class="btn" type="submit" data-confirm="Submit review? It will be locked.">Submit review</button>
    <a class="btn btn-outline" href="<?= e(url('action=ethics_my')) ?>">Cancel</a>
  </div>
</form>
<?php endif; ?>

<?php include APP_PATH . '/views/partials/footer.php'; ?>
