<?php
$title = 'University Proposal Evaluation';
include APP_PATH . '/views/partials/header.php';
?>
<div class="page-head">
  <div>
    <h1>University Proposal Evaluation</h1>
    <p><?= e($app['application_number']) ?> · <?= e($app['title']) ?></p>
    <p class="muted"><?= e($rubric['name']) ?> — Version <?= (int) $rubric['version'] ?> · Total 100 points</p>
  </div>
</div>

<form method="post" action="<?= e(url('action=submit_university_evaluation')) ?>">
  <?= \App\Core\Csrf::field() ?>
  <input type="hidden" name="application_id" value="<?= (int) $app['id'] ?>">

  <div class="card">
    <h2 class="mt0">University Proposal Rubric</h2>
    <p class="muted">Score every required indicator from zero up to its maximum. Scores are raw points, not percentages.</p>
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
    <h2 class="mt0">Academic Recommendation</h2>
    <label>Recommendation</label>
    <select name="recommendation" required>
      <option value="">Select recommendation</option>
      <option value="accept">Accept</option>
      <option value="accept_with_revision">Accept with revision</option>
      <option value="major_revision">Major revision</option>
      <option value="reject">Reject</option>
    </select>
    <label>Strengths</label>
    <textarea name="strengths" rows="3" required></textarea>
    <label>Weaknesses and concerns</label>
    <textarea name="weaknesses" rows="3" required></textarea>
    <label>Overall evaluation summary</label>
    <textarea name="overall_comment" rows="4" required></textarea>
  </div>

  <div class="card">
    <h2 class="mt0">Budget Evaluation</h2>
    <label>Recommended Budget (ETB)</label>
    <input type="number" name="recommended_budget" min="0" step="0.01" required>
    <label>Budget justification</label>
    <textarea name="budget_justification" rows="4" required></textarea>
  </div>

  <button class="btn btn-primary" type="submit">Submit University Evaluation</button>
</form>
<?php include APP_PATH . '/views/partials/footer.php'; ?>

