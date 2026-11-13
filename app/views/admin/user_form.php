<?php $title = ($user === null ? 'New user' : 'Edit user'); include APP_PATH . '/views/partials/header.php'; ?>

<div class="page-head"><div><h1><?= $user === null ? 'New user' : 'Edit: ' . e($user['full_name']) ?></h1>
<p>User accounts and role assignment (§10, §102)</p></div></div>

<form method="post" action="<?= e(url('action=user_save')) ?>">
  <?= \App\Core\Csrf::field() ?>
  <input type="hidden" name="id" value="<?= (int) ($user['id'] ?? 0) ?>">

  <div class="card">
    <h2 class="mt0">Identity</h2>
    <div class="form-row">
      <div><label>Full name <span class="req">*</span></label><input type="text" name="full_name" required maxlength="150" value="<?= e($user['full_name'] ?? '') ?>"></div>
      <div><label>Employee ID</label><input type="text" name="employee_id" maxlength="50" value="<?= e($user['employee_id'] ?? '') ?>"></div>
      <div><label>Email <span class="req">*</span></label><input type="email" name="email" required maxlength="190" value="<?= e($user['email'] ?? '') ?>"></div>
      <div><label>Phone</label><input type="text" name="phone" maxlength="30" value="<?= e($user['phone'] ?? '') ?>"></div>
    </div>
    <div class="form-row">
      <div><label>Position</label><input type="text" name="position" maxlength="100" value="<?= e($user['position_title'] ?? '') ?>"></div>
      <div><label>College</label>
        <select name="college_id"><option value="">—</option>
        <?php foreach ($colleges as $c): ?>
          <option value="<?= (int) $c['id'] ?>" <?= (int) ($user['college_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
        </select></div>
      <div><label>Department</label>
        <select name="department_id"><option value="">—</option>
        <?php foreach ($departments as $d): ?>
          <option value="<?= (int) $d['id'] ?>" <?= (int) ($user['department_id'] ?? 0) === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['college_name']) ?> · <?= e($d['name']) ?></option>
        <?php endforeach; ?>
        </select></div>
      <div><label>Status</label>
        <select name="status">
          <?php foreach (['active','inactive','suspended','pending'] as $st): ?>
          <option value="<?= e($st) ?>" <?= ($user['status'] ?? 'active') === $st ? 'selected' : '' ?>><?= e(ucfirst($st)) ?></option>
          <?php endforeach; ?>
        </select></div>
    </div>
    <div class="form-row">
      <div><label><?= $user === null ? 'Initial password <span class="req">*</span>' : 'New password (leave blank to keep)' ?></label>
        <input type="password" name="password" minlength="8" <?= $user === null ? 'required' : '' ?> autocomplete="new-password"></div>
      <?php if ($user !== null): ?>
      <div style="display:flex;align-items:flex-end;">
        <form method="post" action="<?= e(url('action=user_reset')) ?>" data-confirm="Force this user to change their password on next login?">
            <?= \App\Core\Csrf::field() ?><input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
            <button class="btn btn-danger btn-sm" type="submit">Reset Password</button>
        </form>
      </div>
      <?php endif; ?>
    </div>
    <?php if ($user === null): ?>
    <h2>Roles</h2>
    <div style="columns:2;">
      <?php foreach ($roles as $r): ?>
      <label style="font-weight:400;"><input type="checkbox" name="roles[]" value="<?= (int) $r['id'] ?>"
        <?= in_array((int) $r['id'], $userRoleIds, true) ? 'checked' : '' ?>> <?= e($r['display_name']) ?></label>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p class="muted">Roles are managed on the <a href="<?= e(url('action=user_roles&id=' . (int) $user['id'])) ?>">roles page</a>.</p>
    <?php endif; ?>
  </div>

  <div class="form-actions">
    <button class="btn" type="submit">Save user</button>
    <a class="btn btn-outline" href="<?= e(url('action=users')) ?>">Cancel</a>
  </div>
</form>

<script>
document.querySelector('select[name="college_id"]').addEventListener('change', function() {
    const collegeId = this.value;
    const deptSelect = document.querySelector('select[name="department_id"]');
    deptSelect.innerHTML = '<option value="">—</option>';
    
    if (!collegeId) return;

    fetch('<?= e(url('action=api_departments')) ?>&college_id=' + collegeId)
        .then(response => response.json())
        .then(data => {
            data.forEach(dept => {
                const option = document.createElement('option');
                option.value = dept.id;
                option.textContent = dept.name;
                deptSelect.appendChild(option);
            });
        });
});
</script>

<?php include APP_PATH . '/views/partials/footer.php'; ?>
