<?php $title = 'Departments'; include APP_PATH . '/views/partials/header.php'; ?>

<div class="page-head"><div><h1>Departments</h1><p>Departments belong to colleges and drive automatic routing (§17)</p></div></div>

<div class="grid grid-2">
  <div class="card">
    <h2 class="mt0">All departments</h2>
    <table class="tbl">
      <tr><th>Code</th><th>Department</th><th>College</th><th>Status</th></tr>
      <?php foreach ($departments as $d): ?>
      <tr>
        <td><?= e($d['code']) ?></td>
        <td><?= e($d['name']) ?></td>
        <td><?= e($d['college_name']) ?></td>
        <td><span class="badge <?= $d['status'] === 'active' ? 'badge-success' : 'badge-secondary' ?>"><?= e($d['status']) ?></span></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <?php if (Gate::allows('org.manage')): ?>
  <div class="card">
    <h2 class="mt0">Add department</h2>
    <form method="post" action="<?= e(url('action=department_save')) ?>">
      <?= \App\Core\Csrf::field() ?>
      <input type="hidden" name="id" value="">
      <label>Department name <span class="req">*</span></label>
      <input type="text" name="name" required maxlength="150">
      <label>Code <span class="req">*</span></label>
      <input type="text" name="code" required maxlength="20">
      <label>College <span class="req">*</span></label>
      <select name="college_id" required>
        <?php foreach ($colleges as $c): ?>
        <option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <label>Status</label>
      <select name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select>
      <div class="form-actions"><button class="btn" type="submit">Save department</button></div>
    </form>
  </div>
  <?php endif; ?>
</div>

<?php include APP_PATH . '/views/partials/footer.php'; ?>

