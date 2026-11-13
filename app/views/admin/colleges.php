<?php $title = 'Colleges'; include APP_PATH . '/views/partials/header.php'; ?>

<div class="page-head"><div><h1>Colleges</h1><p>Institutional structure (§10) — data-driven, never hardcoded</p></div></div>

<div class="grid grid-2">
  <div class="card">
    <h2 class="mt0">Colleges</h2>
    <table class="tbl">
      <tr><th>Code</th><th>Name</th><th>Depts</th><th>Status</th></tr>
      <?php foreach ($colleges as $c): ?>
      <tr>
        <td><?= e($c['code']) ?></td>
        <td><a href="<?= e(url('action=departments&college_id=' . $c['id'])) ?>"><?= e($c['name']) ?></a></td>
        <td><?= (int) $c['dept_count'] ?></td>
        <td><span class="badge <?= $c['status'] === 'active' ? 'badge-success' : 'badge-secondary' ?>"><?= e($c['status']) ?></span></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <?php if (Gate::allows('org.manage')): ?>
  <div class="card">
    <h2 class="mt0">Add / update college</h2>
    <form method="post" action="<?= e(url('action=college_save')) ?>">
      <?= \App\Core\Csrf::field() ?>
      <input type="hidden" name="id" value="">
      <label>College name <span class="req">*</span></label>
      <input type="text" name="name" required maxlength="150">
      <label>Code <span class="req">*</span></label>
      <input type="text" name="code" required maxlength="20" placeholder="e.g. CNCS">
      <label>Description</label>
      <input type="text" name="description" maxlength="255">
      <label>Status</label>
      <select name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select>
      <div class="form-actions"><button class="btn" type="submit">Save college</button></div>
    </form>
    <p class="muted" style="font-size:12px;">To edit an existing college, use the inline table once editing support is enabled, or update via this form after selecting it.</p>
  </div>
  <?php endif; ?>
</div>

<?php include APP_PATH . '/views/partials/footer.php'; ?>
