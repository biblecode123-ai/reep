<?php $title = 'Evaluation detail'; include APP_PATH . '/views/partials/header.php'; ?>

<div class="page-head">
  <div><h1>Evaluation #<?= (int) $eval['id'] ?></h1>
  <p><?= e($eval['application_title']) ?> · Evaluator: <?= e($eval['evaluator_name']) ?> ·
     Round <?= (int) $eval['evaluation_round'] ?> · <?= e(ucfirst($eval['status'])) ?></p></div>
  <a class="btn btn-outline" href="<?= e(url('action=application_view&id=' . $eval['application_id'])) ?>">Back to application</a>
</div>

<div class="card">
  <h2 class="mt0">Result</h2>
  <dl class="kv">
    <dt>Weighted score</dt><dd><strong><?= e((string) $eval['weighted_score']) ?></strong> / 100</dd>
    <dt>Recommendation</dt><dd><?= e(ucfirst(str_replace('_', ' ', (string) $eval['recommendation']))) ?></dd>
    <dt>Submitted</dt><dd><?= e(format_datetime($eval['submitted_at'])) ?></dd>
  </dl>
  <table class="tbl" style="margin-top:14px;">
    <tr><th>Criterion</th><th>Weight</th><th>Score</th><th>Weighted</th><th>Comment</th></tr>
    <?php $wsum = 0; foreach ($scores as $s): $w = (float) $s['weight_pct']; $wsum += (float) $s['score'] * $w / 100; ?>
    <tr>
      <td><?= e($s['criterion_name']) ?></td>
      <td><?= e((string) $w) ?>%</td>
      <td><?= e((string) $s['score']) ?></td>
      <td><?= e((string) round($s['score'] * $w / 100, 2)) ?></td>
      <td><?= e($s['comment'] ?? '—') ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>

<div class="grid grid-2">
  <div class="card">
    <h2 class="mt0">Assessment</h2>
    <p><strong>Strengths:</strong><br><?= e($eval['strengths'] ?? '—') ?></p>
    <p><strong>Weaknesses:</strong><br><?= e($eval['weaknesses'] ?? '—') ?></p>
    <p><strong>Recommendations:</strong><br><?= e($eval['recommendations'] ?? '—') ?></p>
    <p><strong>Overall comment:</strong><br><?= e($eval['overall_comment'] ?? '—') ?></p>
  </div>
  <div class="card">
    <h2 class="mt0">Comments</h2>
    <?php if (!$comments): ?><p class="muted">No comments.</p><?php endif; ?>
    <?php foreach ($comments as $c): ?>
      <div class="doc-row">
        <div><?= e($c['comment']) ?>
          <span class="muted" style="font-size:12px;"><?= e($c['author_name'] ?? '') ?> ·
            <span class="badge badge-secondary"><?= e($c['visibility'] === 'committee_only' ? 'committee only' : 'to researcher') ?></span></span>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<?php include APP_PATH . '/views/partials/footer.php'; ?>

