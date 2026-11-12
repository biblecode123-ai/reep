<?php
$title = 'Institutional Evaluation Rubrics';
include APP_PATH . '/views/partials/header.php';
?>
<div class="page-head">
  <div>
    <h1>Institutional Evaluation Rubrics</h1>
    <p class="muted">Four separate 100-point rubrics. Publishing creates a new immutable version; previous versions remain attached to past evaluations.</p>
  </div>
</div>

<div class="card">
  <h2 class="mt0">Published rubric versions</h2>
  <?php if (!$templates): ?>
    <p class="muted">No rubric versions have been published yet. Evaluator submission must remain disabled for a stage until its complete rubric is configured.</p>
  <?php else: ?>
    <table class="tbl">
      <thead><tr><th>Rubric</th><th>Version</th><th>Status</th><th>Published by</th><th>Published at</th></tr></thead>
      <tbody>
      <?php foreach ($templates as $template): ?>
        <tr>
          <td><?= e($template['name']) ?></td>
          <td>v<?= (int) $template['version'] ?></td>
          <td><?= (int) $template['is_active'] === 1 ? 'Active' : 'Archived' ?></td>
          <td><?= e($template['approved_by_name'] ?? '—') ?></td>
          <td><?= e(format_datetime($template['approved_at'] ?? $template['created_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<div class="card">
  <h2 class="mt0">Publish a new rubric version</h2>
  <p>Submit the complete criterion and indicator structure as JSON. The system rejects incomplete rubrics, any total other than 100 points, and indicator maxima that do not add up to their criterion maximum. Existing versions are never edited or deleted.</p>
  <form method="post" action="<?= e(url('action=rubric_save')) ?>">
    <?= \App\Core\Csrf::field() ?>
    <label for="rubric_type">Rubric type</label>
    <select name="rubric_type" id="rubric_type" required>
      <option value="">Select rubric</option>
      <?php foreach ($rubricTypes as $key => $label): ?>
        <option value="<?= e($key) ?>"><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <label for="criteria_json">Criteria and indicators JSON</label>
    <textarea name="criteria_json" id="criteria_json" rows="18" required spellcheck="false" placeholder='[
  {
    "name": "Criterion name",
    "max_points": 10,
    "indicators": [
      {"name": "Indicator name", "max_points": 4, "description": "What the evaluator assesses"},
      {"name": "Second indicator", "max_points": 6}
    ]
  }
]'></textarea>
    <p class="muted">Every criterion must contain at least one indicator. Indicator maxima must sum to the criterion maximum. All criterion maxima must sum to exactly 100.</p>
    <button class="btn btn-primary" type="submit">Validate and publish new version</button>
  </form>
</div>
<?php include APP_PATH . '/views/partials/footer.php'; ?>
