<?php
$title = 'Completed Research Report Evaluation';
include APP_PATH . '/views/partials/header.php';
?>
<div class="page-head">
  <div>
    <h1><?= e($assignment['assignment_level'] === 'college' ? 'College' : 'University') ?> Completed Research Report Evaluation</h1>
    <p><?= e($report['project_number']) ?> · <?= e($report['project_title']) ?></p>
    <p class="muted"><?= e($rubric['name']) ?> — Version <?= (int) $rubric['version'] ?> · 100 points</p>
  </div>
</div>

<form id="finalReportEvaluationForm" method="post" action="<?= e(url('action=final_report_evaluation_submit')) ?>">
  <?= \App\Core\Csrf::field() ?>
  <input type="hidden" name="final_report_id" value="<?= (int) $report['id'] ?>">
  <input type="hidden" name="assignment_id" value="<?= (int) $assignment['id'] ?>">

  <div class="card">
    <h2 class="mt0">Indicator-level scoring</h2>
    <p class="muted">Score each required indicator from zero up to its maximum. All criterion maxima and indicator maxima total 100.</p>
    <?php foreach ($rubric['criteria'] as $criterion): ?>
      <h3><?= e($criterion['name']) ?> <span class="muted">(<?= e((string) $criterion['max_points']) ?> points)</span></h3>
      <table class="tbl">
        <thead><tr><th>Indicator</th><th>Maximum</th><th>Score</th><th>Comment</th></tr></thead>
        <tbody>
        <?php foreach ($criterion['indicators'] as $indicator): ?>
          <?php $saved = $draftScores[(int) $indicator['id']] ?? []; ?>
          <tr>
            <td>
              <strong><?= e($indicator['name']) ?></strong>
              <?php if (!empty($indicator['description'])): ?><div class="muted"><?= e($indicator['description']) ?></div><?php endif; ?>
              <?= (int) $indicator['is_required'] === 1 ? '<span class="badge badge-warning">Required</span>' : '<span class="muted">Optional</span>' ?>
            </td>
            <td><?= e((string) $indicator['max_points']) ?></td>
            <td><input type="number" name="score_<?= (int) $indicator['id'] ?>" min="0" max="<?= (float) $indicator['max_points'] ?>" step="0.01" value="<?= e((string) ($saved['score'] ?? '')) ?>" <?= (int) $indicator['is_required'] === 1 ? 'required' : '' ?>></td>
            <td><input type="text" name="score_comment_<?= (int) $indicator['id'] ?>" maxlength="1000" value="<?= e((string) ($saved['comment'] ?? '')) ?>" placeholder="Indicator feedback"></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endforeach; ?>
  </div>

  <div class="card">
    <h2 class="mt0">Evaluation summary</h2>
    <label>Recommendation</label>
    <select name="recommendation" required>
      <option value="">Select recommendation</option>
      <option value="accept">Accept</option>
      <option value="accept_with_revision">Accept with revision</option>
      <option value="major_revision">Major revision</option>
      <option value="reject">Reject</option>
    </select>
    <label>Strengths</label><textarea name="strengths" rows="3" required></textarea>
    <label>Weaknesses and concerns</label><textarea name="weaknesses" rows="3" required></textarea>
    <label>Overall evaluation</label><textarea name="overall_comment" rows="4" required></textarea>
  </div>

  <div style="display:flex;gap:8px;flex-wrap:wrap;">
    <button class="btn btn-outline" type="button" id="saveFinalReportDraft">Save draft</button>
    <button class="btn btn-primary" type="submit">Submit final report evaluation</button>
    <a class="btn btn-outline" href="<?= e(url('action=final_report_view&id=' . (int) $report['id'])) ?>">Cancel</a>
  </div>
  <p id="draftMessage" class="muted" role="status"></p>
</form>

<script>
document.getElementById('saveFinalReportDraft').addEventListener('click', async () => {
  const form = document.getElementById('finalReportEvaluationForm');
  const data = new FormData(form);
  const message = document.getElementById('draftMessage');
  try {
    const response = await fetch(<?= json_encode(url('action=final_report_evaluation_save')) ?>, {
      method: 'POST',
      body: data,
      headers: {'X-Requested-With': 'XMLHttpRequest'}
    });
    const result = await response.json();
    message.textContent = result.message || (result.ok ? 'Draft saved.' : 'Could not save draft.');
  } catch (error) {
    message.textContent = 'Could not save the draft. Please retry.';
  }
});
</script>
<?php include APP_PATH . '/views/partials/footer.php'; ?>
