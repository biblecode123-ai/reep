<?php
use App\Core\Gate;

$title = 'Research repository';
include APP_PATH . '/views/partials/header.php';

$badge = ['draft' => 'badge-secondary', 'published' => 'badge-success', 'embargoed' => 'badge-warning', 'archived' => 'badge-secondary', 'withdrawn' => 'badge-danger'];
$visLabel = ['public' => 'Public', 'university_only' => 'University only', 'college_only' => 'College only', 'restricted' => 'Restricted', 'confidential' => 'Confidential', 'embargoed' => 'Embargoed'];
$canManage = Gate::allows('repository.manage');
?>
<div class="page-head">
  <div>
    <h1>Research repository (§69)</h1>
    <p><?= $canManage ? 'Upload, edit and manage finished research' : 'Search and read published university research' ?></p>
  </div>
  <?php if ($canManage): ?>
  <div><a class="btn" href="<?= e(url('action=repository_form')) ?>">+ Upload finished research</a></div>
  <?php endif; ?>
</div>

<div class="grid grid-4">
  <div class="stat"><div class="num"><?= (int) $counts['total'] ?></div><div class="lbl">All records</div></div>
  <div class="stat"><div class="num"><?= (int) $counts['published'] ?></div><div class="lbl">Published</div></div>
  <div class="stat"><div class="num"><?= (int) $counts['draft'] ?></div><div class="lbl">Draft</div></div>
  <div class="stat"><div class="num"><?= (int) $counts['archived'] ?></div><div class="lbl">Archived</div></div>
</div>

<form class="filters" method="get" action="<?= e(base_url('index.php')) ?>">
  <input type="hidden" name="action" value="repository">
  <div><label>Search</label><input type="text" name="q" value="<?= e($filters['q']) ?>" placeholder="Search published research — title, keywords, code"></div>
  <?php if ($canManage): ?>
  <div><label>Status</label>
    <select name="status">
      <option value="">All</option>
      <?php foreach ($badge as $st => $unused): ?>
      <option value="<?= e($st) ?>" <?= $filters['status'] === $st ? 'selected' : '' ?>><?= e(ucfirst($st)) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div><label>Visibility</label>
    <select name="visibility">
      <option value="">All</option>
      <?php foreach ($visLabel as $v => $lbl): ?>
      <option value="<?= e($v) ?>" <?= $filters['visibility'] === $v ? 'selected' : '' ?>><?= e($lbl) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <?php else: ?>
  <input type="hidden" name="status" value="published">
  <?php endif; ?>
  <button class="btn btn-outline" type="submit">Search</button>
</form>

<?php if (!$rows): ?>
<p class="muted">No repository records found.</p>
<?php else: ?>
<table class="tbl">
  <tr><th>Code</th><th>Title</th><th>Project</th><th>Year</th><th>Visibility</th><th>Status</th><th></th></tr>
  <?php foreach ($rows as $r): ?>
  <tr>
    <td><code><?= e($r['repository_id_code']) ?></code></td>
    <td><?= e(mb_strimwidth($r['title'], 0, 60, '…')) ?></td>
    <td><?= e($r['project_number'] ?? '—') ?></td>
    <td><?= e((string) ($r['year'] ?? '—')) ?></td>
    <td><?= e($visLabel[$r['visibility']] ?? $r['visibility']) ?></td>
    <td><span class="badge <?= e($badge[$r['status']] ?? 'badge-secondary') ?>"><?= e(ucfirst($r['status'])) ?></span></td>
    <td class="actions">
      <a class="btn btn-outline btn-sm" href="<?= e(url('action=repository_view&id=' . $r['id'])) ?>">Open</a>
      <?php if ($canManage): ?>
      <a class="btn btn-outline btn-sm" href="<?= e(url('action=repository_form&id=' . $r['id'])) ?>">Edit</a>
      <form method="post" action="<?= e(url('action=repository_delete')) ?>" style="display:inline;" onsubmit="return confirm('Delete this repository record permanently?');">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
        <button class="btn btn-danger btn-sm" type="submit">Delete</button>
      </form>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<?= pagination_links($p, $qs) ?>
<?php endif; ?>

<?php include APP_PATH . '/views/partials/footer.php'; ?>
