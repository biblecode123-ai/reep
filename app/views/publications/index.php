<?php
$title = 'Publications';
include APP_PATH . '/views/partials/header.php';
?>
<div class="page-head">
  <div><h1>Publications</h1></div>
</div>

<table class="tbl">
  <tr><th>Title</th><th>Journal</th><th>Year</th><th>Status</th><th></th></tr>
  <?php foreach ($publications as $p): ?>
  <tr>
    <td><?= e($p['title']) ?></td>
    <td><?= e($p['journal_name'] ?? '—') ?></td>
    <td><?= e($p['publication_year'] ?? '—') ?></td>
    <td><?= e($p['status']) ?></td>
    <td><a href="<?= e(url('action=publication_view&id=' . $p['id'])) ?>">View</a></td>
  </tr>
  <?php endforeach; ?>
</table>

<?php include APP_PATH . '/views/partials/footer.php'; ?>
