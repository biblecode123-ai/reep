<?php $title = 'Audit log'; include APP_PATH . '/views/partials/header.php'; ?>

<div class="page-head"><div><h1>Audit log</h1>
<p>Append-only institutional trail (§65). Entries cannot be edited or deleted from the application.</p></div></div>

<form class="filters" method="get" action="<?= e(base_url('index.php')) ?>">
  <input type="hidden" name="action" value="audit">
  <div><label>Action contains</label><input type="text" name="action_filter" value="<?= e($filters['action'] ?? '') ?>"></div>
  <div><label>Entity</label>
    <select name="entity"><option value="">All</option>
      <?php foreach (['research_call','research_application','research_project','ethics_application','user','college','department','committee','document'] as $ent): ?>
      <option value="<?= e($ent) ?>" <?= ($filters['entity'] ?? '') === $ent ? 'selected' : '' ?>><?= e(str_replace('_', ' ', $ent)) ?></option>
      <?php endforeach; ?>
    </select></div>
  <div><label>From</label><input type="date" name="date_from" value="<?= e($filters['date_from'] ?? '') ?>"></div>
  <div><label>To</label><input type="date" name="date_to" value="<?= e($filters['date_to'] ?? '') ?>"></div>
  <button class="btn btn-outline" type="submit">Filter</button>
</form>

<?php if (!$logs): ?><p class="muted">No audit entries.</p><?php else: ?>
<table class="tbl">
  <tr><th>#</th><th>When</th><th>User</th><th>Action</th><th>Entity</th><th>Details</th><th>IP</th></tr>
  <?php foreach ($logs as $l): ?>
  <tr>
    <td><?= (int) $l['id'] ?></td>
    <td style="white-space:nowrap;"><?= e(format_datetime($l['created_at'])) ?></td>
    <td><?= e($l['user_name'] ?? 'system') ?></td>
    <td><code style="font-size:12px;"><?= e($l['action']) ?></code></td>
    <td><?= e($l['entity_type'] ?? '—') ?><?= $l['entity_id'] ? ' #' . (int) $l['entity_id'] : '' ?></td>
    <td style="max-width:380px;font-size:12px;word-break:break-word;">
      <?php
        $nv = json_decode((string) $l['new_values'], true);
        $ov = json_decode((string) $l['old_values'], true);
        if (is_array($ov)) { echo '<span class="muted">old: ' . e(json_encode($ov)) . '</span><br>'; }
        if (is_array($nv)) {
            if (isset($nv['comment']) && is_string($nv['comment'])) { echo e($nv['comment']) . '<br>'; }
            if (isset($nv['values'])) { echo e(json_encode($nv['values'])); }
            elseif (isset($nv['uri'])) { echo e(json_encode($nv)); }
        }
      ?>
    </td>
    <td><?= e($l['ip_address'] ?? '') ?></td>
  </tr>
  <?php endforeach; ?>
</table>
<?= pagination_links($p, $qs ?? '') ?>
<?php endif; ?>

<?php include APP_PATH . '/views/partials/footer.php'; ?>

