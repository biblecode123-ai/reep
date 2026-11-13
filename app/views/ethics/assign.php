<?php $title = 'Assign ethics reviewers'; include APP_PATH . '/views/partials/header.php'; ?>

<div class="page-head">
  <div><h1>Assign ethics reviewers</h1>
  <p><?= e($eth['application_number']) ?> — <?= e($eth['research_title']) ?> · Risk: <?= e($eth['risk_level'] ? ucfirst($eth['risk_level']) : '—') ?></p></div>
</div>

<?php if ($reviews): ?>
<div class="card">
  <h2 class="mt0">Currently assigned</h2>
  <table class="tbl">
    <tr><th>Reviewer</th><th>Status</th><th>Deadline</th></tr>
    <?php foreach ($reviews as $r): ?>
    <tr><td><?= e($r['reviewer_name']) ?></td>
        <td><span class="badge badge-info"><?= e($r['status']) ?></span></td>
        <td><?= e(format_date($r['review_deadline'])) ?></td></tr>
    <?php endforeach; ?>
  </table>
</div>
<?php endif; ?>

<div class="card">
  <h2 class="mt0">Assign reviewers (§32)</h2>
  <form method="post" action="<?= e(url('action=ethics_assign')) ?>">
    <?= \App\Core\Csrf::field() ?>
    <input type="hidden" name="id" value="<?= (int) $eth['id'] ?>">
    <table class="tbl">
      <tr><th></th><th>Reviewer</th><th>Department</th><th>Email</th></tr>
      <?php foreach ($reviewers as $rv): ?>
      <tr>
        <td><input type="checkbox" name="reviewer_ids[]" value="<?= (int) $rv['id'] ?>"></td>
        <td><?= e($rv['full_name']) ?></td>
        <td><?= e($rv['department_name'] ?? '—') ?></td>
        <td><?= e($rv['email']) ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <div class="form-row" style="margin-top:12px;">
      <div><label>Review deadline</label><input type="date" name="deadline" value="<?= e(date('Y-m-d', strtotime('+14 days'))) ?>"></div>
    </div>
    <div class="form-actions">
      <button class="btn" type="submit">Assign & notify</button>
      <a class="btn btn-outline" href="<?= e(url('action=ethics_view&id=' . $eth['id'])) ?>">Back</a>
    </div>
  </form>
</div>

<?php include APP_PATH . '/views/partials/footer.php'; ?>
