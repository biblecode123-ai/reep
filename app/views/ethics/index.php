<?php
use App\Core\Gate;
$title = 'Ethics review';
include APP_PATH . '/views/partials/header.php';

$countsByStatus = [];
foreach ($counts as $c) { $countsByStatus[$c['status']] = (int) $c['cnt']; }
?>
<div class="page-head">
  <div><h1>Ethics review (§30–§34)</h1><p>Screening, review, decisions, certificates, expiry and amendments</p></div>
</div>

<div class="grid grid-4">
  <div class="stat"><div class="num"><?= $countsByStatus['draft'] ?? 0 ?></div><div class="lbl">Draft</div></div>
  <div class="stat"><div class="num"><?= ($countsByStatus['submitted'] ?? 0) + ($countsByStatus['under_screening'] ?? 0) ?></div><div class="lbl">Pending screening</div></div>
  <div class="stat"><div class="num"><?= $countsByStatus['under_review'] ?? 0 ?></div><div class="lbl">Under review</div></div>
  <div class="stat"><div class="num"><?= ($countsByStatus['approved'] ?? 0) + ($countsByStatus['approved_with_conditions'] ?? 0) ?></div><div class="lbl">Approved</div></div>
  <div class="stat"><div class="num"><?= $countsByStatus['exempt'] ?? 0 ?></div><div class="lbl">Exempt</div></div>
  <div class="stat"><div class="num"><?= $countsByStatus['rejected'] ?? 0 ?></div><div class="lbl">Rejected</div></div>
  <div class="stat"><div class="num"><?= $countsByStatus['expired'] ?? 0 ?></div><div class="lbl">Expired</div></div>
</div>

<?php if (!empty($expiring)): ?>
<div class="alert alert-warning">
  <strong>Expiring within 30 days (§33):</strong>
  <ul style="margin:6px 0 0 18px;">
    <?php foreach ($expiring as $e): ?>
      <li><a href="<?= e(url('action=ethics_view&id=' . $e['id'])) ?>"><?= e($e['application_number']) ?></a>
        — <?= e($e['research_title']) ?> · expires <strong><?= e(format_date($e['approved_until'])) ?></strong></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<form class="filters" method="get" action="<?= e(base_url('index.php')) ?>">
  <input type="hidden" name="action" value="ethics">
  <div><label>Search</label><input type="text" name="q" value="<?= e($filters['q']) ?>" placeholder="Number or title"></div>
  <div><label>Status</label>
    <select name="status">
      <option value="">All</option>
      <?php foreach (['draft','submitted','under_screening','under_review','revision_required','approved','approved_with_conditions','rejected','exempt','expired'] as $st): ?>
      <option value="<?= e($st) ?>" <?= $filters['status'] === $st ? 'selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $st))) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <button class="btn btn-outline" type="submit">Filter</button>
</form>

<?php if (!$rows): ?><p class="muted">No ethics applications.</p><?php else: ?>
<table class="tbl">
  <tr><th>Number</th><th>Research</th><th>Researcher</th><th>Risk</th><th>Expires</th><th>Status</th><th></th></tr>
  <?php foreach ($rows as $r): ?>
  <tr>
    <td><?= e($r['application_number']) ?></td>
    <td><?= e(mb_strimwidth($r['research_title'], 0, 50, '…')) ?><div class="muted" style="font-size:12px;"><?= e($r['research_app_number']) ?></div></td>
    <td><?= e($r['researcher_name']) ?></td>
    <td><?= e($r['risk_level'] ? ucfirst($r['risk_level']) : '—') ?></td>
    <td><?= e(format_date($r['approved_until'])) ?></td>
    <td><span class="badge <?= in_array($r['status'], ['approved','approved_with_conditions','exempt'], true) ? 'badge-success' : (in_array($r['status'], ['rejected','expired'], true) ? 'badge-danger' : 'badge-info') ?>"><?= e(ucfirst(str_replace('_', ' ', $r['status']))) ?></span></td>
    <td class="actions"><a class="btn btn-outline btn-sm" href="<?= e(url('action=ethics_view&id=' . $r['id'])) ?>">Open</a></td>
  </tr>
  <?php endforeach; ?>
</table>
<?= pagination_links($p, $qs) ?>
<?php endif; ?>

<?php include APP_PATH . '/views/partials/footer.php'; ?>
