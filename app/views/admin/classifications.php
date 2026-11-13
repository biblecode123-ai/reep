<?php $title = 'Classifications'; include APP_PATH . '/views/partials/header.php'; ?>

<div class="page-head"><div><h1>Classifications</h1>
<p>Research themes, research types and document types — all database-driven (Rule 2, §102)</p></div></div>

<div class="grid grid-3">
  <div class="card">
    <h2 class="mt0">Research themes</h2>
    <table class="tbl">
      <tr><th>Code</th><th>Name</th><th>Status</th></tr>
      <?php foreach ($themes as $t): ?>
      <tr><td><?= e($t['code']) ?></td><td><?= e($t['name']) ?></td>
      <td><span class="badge <?= $t['status'] === 'active' ? 'badge-success' : 'badge-secondary' ?>"><?= e($t['status']) ?></span></td></tr>
      <?php endforeach; ?>
    </table>
  </div>

  <div class="card">
    <h2 class="mt0">Research types</h2>
    <table class="tbl">
      <tr><th>Code</th><th>Name</th><th>Status</th></tr>
      <?php foreach ($types as $t): ?>
      <tr><td><?= e($t['code']) ?></td><td><?= e($t['name']) ?></td>
      <td><span class="badge <?= $t['status'] === 'active' ? 'badge-success' : 'badge-secondary' ?>"><?= e($t['status']) ?></span></td></tr>
      <?php endforeach; ?>
    </table>
  </div>

  <div class="card">
    <h2 class="mt0">Document types</h2>
    <table class="tbl">
      <tr><th>Code</th><th>Name</th><th>Status</th></tr>
      <?php foreach ($docTypes as $t): ?>
      <tr><td><?= e($t['type_code']) ?></td><td><?= e($t['name']) ?></td>
      <td><span class="badge <?= $t['status'] === 'active' ? 'badge-success' : 'badge-secondary' ?>"><?= e($t['status']) ?></span></td></tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>

<div class="card">
  <h2 class="mt0">Add classification</h2>
  <form method="post" action="<?= e(url('action=classification_save')) ?>">
    <?= \App\Core\Csrf::field() ?>
    <input type="hidden" name="id" value="">
    <div class="form-row">
      <div><label>Kind</label>
        <select name="kind">
          <option value="theme">Research theme</option>
          <option value="type">Research type</option>
          <option value="doc_type">Document type</option>
        </select></div>
      <div><label>Name <span class="req">*</span></label><input type="text" name="name" required maxlength="150"></div>
      <div><label>Code <span class="req">*</span></label><input type="text" name="code" required maxlength="30"></div>
      <div><label>Status</label><select name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
    </div>
    <label>Description</label><input type="text" name="description" maxlength="255">
    <div class="form-actions"><button class="btn" type="submit">Save</button></div>
  </form>
</div>

<?php include APP_PATH . '/views/partials/footer.php'; ?>
