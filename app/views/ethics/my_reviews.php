<?php $title = 'My ethics reviews'; include APP_PATH . '/views/partials/header.php'; ?>

<div class="page-head"><div><h1>My ethics reviews</h1><p>Assigned ethics review tasks (§32)</p></div></div>

<?php if (!$reviews): ?><p class="muted">No assigned reviews.</p><?php else: ?>
<table class="tbl">
  <tr><th>Application</th><th>Research</th><th>Deadline</th><th>Status</th><th class="actions">Actions</th></tr>
  <?php foreach ($reviews as $r): ?>
  <tr>
    <td><?= e($r['application_number']) ?></td>
    <td><?= e(mb_strimwidth($r['research_title'], 0, 55, '…')) ?></td>
    <td><?= e(format_date($r['review_deadline'])) ?></td>
    <td><span class="badge <?= $r['status'] === 'completed' ? 'badge-success' : ($r['status'] === 'declined' ? 'badge-secondary' : 'badge-warning') ?>"><?= e($r['status']) ?></span></td>
    <td class="actions">
      <?php if (in_array($r['status'], ['assigned', 'accepted'], true)): ?>
        <a class="btn btn-sm" href="<?= e(url('action=ethics_review_form&id=' . $r['id'])) ?>">Review</a>
      <?php elseif ($r['status'] === 'completed'): ?>
        <span class="muted">done</span>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<?php include APP_PATH . '/views/partials/footer.php'; ?>

