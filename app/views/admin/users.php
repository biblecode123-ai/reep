<?php $title = 'Users'; include APP_PATH . '/views/partials/header.php'; ?>

<div class="page-head">
  <div><h1>Users</h1><p>Manage institutional users and their roles (§10)</p></div>
  <?php if (Gate::allows('user.manage')): ?><a class="btn" href="<?= e(url('action=user_form')) ?>">New user</a><?php endif; ?>
</div>

<form class="filters" method="get" action="<?= e(base_url('index.php')) ?>">
  <input type="hidden" name="action" value="users">
  <?php if (!empty($filters['college'])): ?>
    <input type="hidden" name="college_id" value="<?= (int)$filters['college'] ?>">
  <?php endif; ?>
  <div><label>Search</label><input type="text" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Name, email, employee ID"></div>
  <div><label>College</label>
    <select name="college_id">
      <option value="">All</option>
      <?php foreach ($colleges as $c): ?>
        <option value="<?= (int) $c['id'] ?>" <?= ((int) ($filters['college'] ?? 0) === (int) $c['id']) ? 'selected' : '' ?>><?= e($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div><label>Role</label>
    <select name="role"><option value="">All</option>
      <?php foreach ($roles as $r): ?><option value="<?= e($r['name']) ?>" <?= ($filters['role'] ?? '') === $r['name'] ? 'selected' : '' ?>><?= e($r['display_name']) ?></option><?php endforeach; ?>
    </select></div>
  <div><label>Status</label>
    <select name="status"><option value="">All</option>
      <?php foreach (['active','inactive','suspended','pending'] as $st): ?>
      <option value="<?= e($st) ?>" <?= ($filters['status'] ?? '') === $st ? 'selected' : '' ?>><?= e(ucfirst($st)) ?></option>
      <?php endforeach; ?>
    </select></div>
  <button class="btn btn-outline" type="submit">Filter</button>
</form>

<table class="tbl">
  <tr><th>Name</th><th>Employee ID</th><th>Email</th><th>College / Dept</th><th>Roles</th><th>Status</th><th class="actions">Actions</th></tr>
  <?php foreach ($rows as $u): ?>
  <tr>
    <td><?= e($u['full_name']) ?></td>
    <td><?= e($u['employee_id'] ?? '—') ?></td>
    <td><?= e($u['email']) ?></td>
    <td><?= e($u['college_name'] ?? '—') ?> / <?= e($u['department_name'] ?? '—') ?></td>
    <td style="max-width:260px;font-size:13px;"><?= e($u['role_names'] ?? '—') ?></td>
    <td><span class="badge <?= $u['status'] === 'active' ? 'badge-success' : 'badge-secondary' ?>"><?= e(ucfirst($u['status'])) ?></span></td>
    <td class="actions">
      <?php if (Gate::allows('user.manage')): ?>
        <a class="btn btn-outline btn-sm" href="<?= e(url('action=user_form&id=' . $u['id'])) ?>">Edit</a>
        <form method="post" action="<?= e(url('action=user_delete')) ?>" style="display:inline;" data-confirm="Are you sure you want to toggle this user's status?">
          <?= \App\Core\Csrf::field() ?>
          <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
          <button class="btn btn-secondary btn-sm" type="submit"><?= $u['status'] === 'active' ? 'Deactivate' : 'Activate' ?></button>
        </form>
        <form method="post" action="<?= e(url('action=user_destroy')) ?>" style="display:inline;" data-confirm="Are you sure you want to PERMANENTLY DELETE this user? This cannot be undone.">
          <?= \App\Core\Csrf::field() ?>
          <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
          <button class="btn btn-danger btn-sm" type="submit">Delete</button>
        </form>
      <?php endif; ?>
      <?php if (Gate::allows('user.roles')): ?>
        <a class="btn btn-outline btn-sm" href="<?= e(url('action=user_roles&id=' . $u['id'])) ?>">Roles</a>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<?= pagination_links($p, $qs ?? '') ?>

<?php include APP_PATH . '/views/partials/footer.php'; ?>

