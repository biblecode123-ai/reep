<?php $title = 'User roles'; include APP_PATH . '/views/partials/header.php'; ?>

<div class="page-head"><div><h1>Roles: <?= e($user['full_name']) ?></h1>
<p>Users may hold multiple roles (§7). Effective permissions are the union of role grants.</p></div></div>

<form method="post" action="<?= e(url('action=user_roles_save')) ?>">
  <?= \App\Core\Csrf::field() ?>
  <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
  <div class="card">
    <div style="columns:2;">
      <?php foreach ($roles as $r): ?>
      <label style="font-weight:400;margin:8px 0;">
        <input type="checkbox" name="roles[]" value="<?= (int) $r['id'] ?>"
          <?= in_array((int) $r['id'], $userRoleIds, true) ? 'checked' : '' ?>>
        <strong><?= e($r['display_name']) ?></strong>
        <div class="muted" style="font-size:12px;margin-left:22px;"><?= e($r['description'] ?? '') ?></div>
      </label>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="form-actions">
    <button class="btn" type="submit">Save roles</button>
    <a class="btn btn-outline" href="<?= e(url('action=users')) ?>">Cancel</a>
  </div>
</form>

<?php include APP_PATH . '/views/partials/footer.php'; ?>
