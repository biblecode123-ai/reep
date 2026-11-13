<?php
$title = 'Research presentations';
include APP_PATH . '/views/partials/header.php';
?>
<div class="page-head">
  <div><h1>Research presentations</h1></div>
</div>

<?php if (!$presentations): ?><p class="muted">No scheduled presentations found.</p><?php else: ?>
<table class="tbl">
  <tr><th>Project number</th><th>Title</th><th>Scheduled at</th><th>Venue</th><th>Status</th><th></th></tr>
  <?php foreach ($presentations as $p): ?>
  <tr>
    <td><?= e($p['project_number']) ?></td>
    <td><?= e(mb_strimwidth($p['project_title'], 0, 55, '…')) ?></td>
    <td><?= e(format_datetime($p['scheduled_at'])) ?></td>
    <td><?= e($p['venue'] ?? '—') ?></td>
    <td><span class="badge"><?= e($p['status']) ?></span></td>
    <td class="actions"><a class="btn btn-outline btn-sm" href="<?= e(url('action=project_view&id=' . $p['project_id'])) ?>">Project</a></td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<?php include APP_PATH . '/views/partials/footer.php'; ?>
