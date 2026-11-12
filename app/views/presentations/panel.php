<?php
$title = 'Manage presentation panel';
include APP_PATH . '/views/partials/header.php';
?>
<div class="page-head">
  <div><h1>Manage panel</h1></div>
</div>

<h3>Existing members</h3>
<table class="tbl">
  <tr><th>Name</th><th>Role</th><th>Affiliation</th></tr>
  <?php foreach ($panel as $m): ?>
  <tr>
    <td><?= e($m['member_name']) ?></td>
    <td><?= e($m['member_role']) ?></td>
    <td><?= e($m['affiliation'] ?? '—') ?></td>
  </tr>
  <?php endforeach; ?>
</table>

<h3>Add member</h3>
<form method="post" action="<?= e(url('action=presentation_panel&id=' . $presentationId)) ?>">
  <?= \App\Core\Csrf::field() ?>
  <div class="form-group">
    <label>User (leave blank for external)</label>
    <select name="user_id"><option value="">-- External --</option></select>
  </div>
  <div class="form-group">
    <label>Name</label>
    <input type="text" name="name" required>
  </div>
  <div class="form-group">
    <label>Role</label>
    <select name="role">
      <option value="chair">Chair</option>
      <option value="member">Member</option>
      <option value="external_expert">External Expert</option>
      <option value="secretary">Secretary</option>
    </select>
  </div>
  <div class="form-group">
    <label>Affiliation</label>
    <input type="text" name="affiliation">
  </div>
  <button class="btn btn-primary" type="submit">Add</button>
</form>

<?php include APP_PATH . '/views/partials/footer.php'; ?>
