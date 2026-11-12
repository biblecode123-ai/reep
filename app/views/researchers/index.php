<?php
use App\Core\Gate;

$title = 'Researchers';
include APP_PATH . '/views/partials/header.php';

$canFilterCollege = \App\Services\ResearcherPolicy::isDirectorate();
?>
<div class="page-head">
  <div>
    <h1><?= $coordinatorCollege ? 'Researchers — ' . e($coordinatorCollege['name']) : 'University researchers' ?></h1>
    <p><?= $coordinatorCollege
        ? 'Researcher directory for your college (scoped automatically to your assignment)'
        : 'University-wide researcher directory' ?></p>
  </div>
</div>

<div class="grid grid-3">
<div class="stat"><div class="num"><?= (int) ($p['total'] ?? 0) ?></div><div class="lbl"><?= $coordinatorCollege ? 'Researchers in your college' : 'Researchers' ?></div></div>
</div>

<form class="filters" method="get" action="<?= e(base_url('index.php')) ?>">
  <input type="hidden" name="action" value="researchers">
  <div><label>Search</label><input type="text" name="q" value="<?= e($filters['q']) ?>" placeholder="Name, staff ID, specialization, interests"></div>
  <?php if ($canFilterCollege): ?>
  <div><label>College</label>
    <select name="college_id">
      <option value="">All</option>
      <?php foreach ($colleges as $c): ?>
      <option value="<?= (int) $c['id'] ?>" <?= (int) $filters['college'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
      <?php endforeach; ?>
    </select></div>
  <?php endif; ?>
  <div><label>Department</label>
    <select name="department_id">
      <option value="">All</option>
      <?php foreach ($departments as $d): ?>
      <option value="<?= (int) $d['id'] ?>" <?= (int) $filters['dept'] === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
      <?php endforeach; ?>
    </select></div>
  <div><label>Academic rank</label>
    <select name="rank">
      <option value="">All</option>
      <?php foreach ($ranks as $r): ?>
      <option value="<?= e($r['v']) ?>" <?= $filters['rank'] === $r['v'] ? 'selected' : '' ?>><?= e($r['v']) ?></option>
      <?php endforeach; ?>
    </select></div>
  <?php if ($canFilterCollege): ?>
  <div><label>Qualification</label>
    <select name="qual">
      <option value="">All</option>
      <?php foreach ($quals as $q): ?>
      <option value="<?= e($q['v']) ?>" <?= ($filters['qual'] ?? '') === $q['v'] ? 'selected' : '' ?>><?= e($q['v']) ?></option>
      <?php endforeach; ?>
    </select></div>
  <div><label>Account status</label>
    <select name="status">
      <option value="">All</option>
      <?php foreach (['active', 'inactive', 'suspended'] as $st): ?>
      <option value="<?= $st ?>" <?= ($filters['status'] ?? '') === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
      <?php endforeach; ?>
    </select></div>
  <?php endif; ?>
  <button class="btn btn-outline" type="submit">Filter</button>
</form>

<?php if (!$rows): ?>
<p class="muted">No researchers found<?= $coordinatorCollege ? ' in your college' : '' ?>.</p>
<?php else: ?>
<table class="tbl">
  <tr><th>Name</th><th>Staff ID</th><th>College</th><th>Department</th><th>Academic rank</th><th>Projects</th><th></th></tr>
  <?php foreach ($rows as $r): ?>
  <tr>
    <td><a href="<?= e(url('action=researcher_view&id=' . (int) $r['id'])) ?>"><?= e($r['full_name']) ?></a></td>
    <td><?= e($r['employee_id'] ?? '—') ?></td>
    <td><?= e($r['college_name'] ?? '—') ?></td>
    <td><?= e($r['department_name'] ?? '—') ?></td>
    <td><?= e($r['academic_position'] ?? '—') ?></td>
    <td><?= (int) $r['project_count'] ?></td>
    <td class="actions"><a class="btn btn-outline btn-sm" href="<?= e(url('action=researcher_view&id=' . (int) $r['id'])) ?>">Profile</a></td>
  </tr>
  <?php endforeach; ?>
</table>
<?= pagination_links($p, $qs) ?>
<?php endif; ?>

<?php include APP_PATH . '/views/partials/footer.php'; ?>

