<?php
use App\Core\Gate;
$title = 'Research projects';
include APP_PATH . '/views/partials/header.php';

$byStatus = [];
foreach ($counts as $c) { $byStatus[$c['status_code']] = (int) $c['cnt']; }
?>
<div class="page-head">
  <div><h1>Research projects (§35, §39)</h1><p>Approved and funded research under implementation</p></div>
</div>

<div class="grid grid-4">
  <div class="stat"><div class="num"><?= $byStatus['APPROVED'] ?? 0 ?></div><div class="lbl">Awaiting activation</div></div>
  <div class="stat"><div class="num"><?= $byStatus['ACTIVE'] ?? 0 ?></div><div class="lbl">Active</div></div>
  <div class="stat"><div class="num"><?= $byStatus['PROGRESS_MONITORING'] ?? 0 ?></div><div class="lbl">In progress reporting</div></div>
  <div class="stat"><div class="num"><?= $byStatus['COMPLETED'] ?? 0 ?></div><div class="lbl">Completed</div></div>
  <div class="stat"><div class="num"><?= $byStatus['SUSPENDED'] ?? 0 ?></div><div class="lbl">Suspended</div></div>
  <div class="stat"><div class="num"><?= ($byStatus['TERMINATED'] ?? 0) + ($byStatus['CANCELLED'] ?? 0) ?></div><div class="lbl">Terminated/cancelled</div></div>
</div>

<form class="filters" method="get" action="<?= e(base_url('index.php')) ?>">
  <input type="hidden" name="action" value="projects">
  <div><label>Search</label><input type="text" name="q" value="<?= e($filters['q']) ?>" placeholder="Number or title"></div>
  <div><label>Status</label>
    <select name="status">
      <option value="">All</option>
      <?php foreach (['APPROVED','ACTIVE','PROGRESS_MONITORING','FINAL_REPORT_SUBMITTED','COMPLETED','SUSPENDED','TERMINATED','CANCELLED'] as $st): ?>
      <option value="<?= e($st) ?>" <?= $filters['status'] === $st ? 'selected' : '' ?>><?= e(status_label($st)) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <button class="btn btn-outline" type="submit">Filter</button>
</form>

<?php if (!$rows): ?><p class="muted">No projects yet. Projects are created when funded applications are activated.</p><?php else: ?>
<table class="tbl">
  <tr><th>Project number</th><th>Title</th><th>PI</th><th>College</th><th>Ends</th><th>Budget</th><th>Status</th><th></th></tr>
  <?php foreach ($rows as $pr): ?>
  <tr>
    <td><?= e($pr['project_number']) ?></td>
    <td><?= e(mb_strimwidth($pr['title'], 0, 55, '…')) ?></td>
    <td><?= e($pr['pi_name']) ?></td>
    <td><?= e($pr['college_name'] ?? '—') ?></td>
    <td><?= e(format_date($pr['planned_end'] ?? null)) ?></td>
    <td><?= e(format_money($pr['approved_budget'] ?? null)) ?></td>
    <td><span class="badge <?= status_badge($pr['status_code']) ?>"><?= e(status_label($pr['status_code'])) ?></span></td>
    <td class="actions"><a class="btn btn-outline btn-sm" href="<?= e(url('action=project_view&id=' . $pr['id'])) ?>">Open</a></td>
  </tr>
  <?php endforeach; ?>
</table>
<?= pagination_links($p, $qs) ?>
<?php endif; ?>

<?php include APP_PATH . '/views/partials/footer.php'; ?>

