<?php $title = 'Notifications'; include APP_PATH . '/views/partials/header.php'; ?>

<div class="page-head">
  <div><h1>Notifications</h1><p>Internal alerts (§66)</p></div>
  <form method="post" action="<?= e(url('action=notifications_read_all')) ?>">
    <?= \App\Core\Csrf::field() ?>
    <button class="btn btn-outline" type="submit">Mark all as read</button>
  </form>
</div>

<?php if (!$rows): ?><p class="muted">No notifications.</p><?php else: ?>
<table class="tbl">
  <tr><th>When</th><th>Type</th><th>Notification</th><th></th></tr>
  <?php foreach ($rows as $n): ?>
  <tr style="<?= $n['read_at'] === null ? 'font-weight:600;' : '' ?>">
    <td style="white-space:nowrap;"><?= e(format_datetime($n['created_at'])) ?></td>
    <td><span class="badge badge-info" style="font-size:11px;"><?= e($n['notification_type']) ?></span>
      <?= $n['priority'] === 'high' || $n['priority'] === 'urgent' ? '<span class="badge badge-danger" style="font-size:11px;">' . e($n['priority']) . '</span>' : '' ?></td>
    <td>
      <?= e($n['title']) ?>
      <?php if ($n['body']): ?><div class="muted" style="font-weight:400;font-size:12.5px;"><?= e($n['body']) ?></div><?php endif; ?>
    </td>
    <td class="actions">
      <?php if ($n['link_url']): ?><a class="btn btn-outline btn-sm" href="<?= e(base_url($n['link_url'])) ?>">Open</a><?php endif; ?>
      <?php if ($n['read_at'] === null): ?>
      <form class="inline-form" method="post" action="<?= e(url('action=notification_read')) ?>">
        <?= \App\Core\Csrf::field() ?><input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
        <button class="btn btn-sm" type="submit">✓</button>
      </form>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<?= pagination_links($p, $qs ?? '') ?>
<?php endif; ?>

<?php include APP_PATH . '/views/partials/footer.php'; ?>
