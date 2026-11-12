<?php
use App\Core\Gate;
$title = 'Committee review queue';
include APP_PATH . '/views/partials/header.php';
$rows = $rows ?? [];
?>
<div class="page-head">
  <div>
    <h1>University Research Committee queue</h1>
    <p>Research assigned to committees you are an active member of (§12). Record the committee decision from each research page.</p>
  </div>
</div>

<div class="card">
  <?php if (!$rows): ?>
    <p class="muted">No research has been assigned to your committees yet.</p>
  <?php else: ?>
    <table class="tbl">
      <tr><th>Application</th><th>Research</th><th>PI</th><th>Committee</th><th>Assigned</th><th>Status</th><th>Decision</th><th></th></tr>
      <?php foreach ($rows as $r): ?>
      <tr>
        <td><strong><?= e($r['application_number']) ?></strong><div class="muted" style="font-size:12px;">v<?= (int) $r['version'] ?></div></td>
        <td><?= e($r['title']) ?><div class="muted" style="font-size:12px;"><?= e($r['college_name'] ?? '') ?></div></td>
        <td><?= e($r['pi_name']) ?></td>
        <td><?= e($r['committee_name']) ?><?= $r['meeting_date'] ? '<div class="muted" style="font-size:12px;">meeting ' . e(format_date($r['meeting_date'])) . '</div>' : '' ?></td>
        <td><?= e(format_datetime($r['assigned_at'])) ?></td>
        <td><span class="badge <?= status_badge($r['status_code']) ?>"><?= e(status_label($r['status_code'])) ?></span></td>
        <td><?= $r['last_decision_id'] ? '<span class="badge badge-info">recorded</span>' : '<span class="muted">pending</span>' ?></td>
        <td><a class="btn btn-outline btn-sm" href="<?= e(url('action=application_view&id=' . (int) $r['id'])) ?>">Review</a></td>
      </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>
</div>

<?php include APP_PATH . '/views/partials/footer.php'; ?>

