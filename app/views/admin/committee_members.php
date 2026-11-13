<?php $title = 'Committee members'; include APP_PATH . '/views/partials/header.php'; ?>

<div class="page-head"><div><h1><?= e($committee['name']) ?></h1>
<p>Members of this committee (§68). Chair/secretary roles are recorded.</p></div>
<a class="btn btn-outline" href="<?= e(url('action=committees')) ?>">Back to committees</a></div>

<div class="grid grid-2">
  <div class="card">
    <h2 class="mt0">Members</h2>
    <table class="tbl">
      <tr><th>Name</th><th>Role</th><th>Period</th><th>Status</th><th class="actions"></th></tr>
      <?php foreach ($members as $m): ?>
      <tr>
        <td><?= e($m['full_name']) ?><div class="muted" style="font-size:12px;"><?= e($m['email']) ?></div></td>
        <td><span class="badge badge-info"><?= e($m['member_role']) ?></span></td>
        <td><?= e(format_date($m['appointed_from'])) ?> → <?= e(format_date($m['appointed_to'])) ?></td>
        <td><span class="badge <?= $m['status'] === 'active' ? 'badge-success' : 'badge-secondary' ?>"><?= e($m['status']) ?></span></td>
        <td class="actions">
          <?php if ($m['status'] === 'active'): ?>
          <form class="inline-form" method="post" action="<?= e(url('action=committee_member_remove')) ?>" data-confirm="Retire this member?">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
            <input type="hidden" name="committee_id" value="<?= (int) $committee['id'] ?>">
            <button class="btn btn-outline btn-sm" type="submit">Retire</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <div class="card">
    <h2 class="mt0">Add member</h2>
    <form method="get" action="index.php" class="mb20">
      <input type="hidden" name="action" value="committee_members">
      <input type="hidden" name="id" value="<?= (int) $committee['id'] ?>">
      <label>Filter users by college</label>
      <select name="college_id" onchange="this.form.submit()">
        <option value="0">All colleges</option>
        <?php foreach ($colleges as $c): ?>
        <option value="<?= (int) $c['id'] ?>" <?= $collegeId === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
    <form method="post" action="<?= e(url('action=committee_member_save')) ?>">
      <?= \App\Core\Csrf::field() ?>
      <input type="hidden" name="committee_id" value="<?= (int) $committee['id'] ?>">
      <label>User <span class="req">*</span></label>
      <select name="user_id" required>
        <?php foreach ($users as $u): ?>
        <option value="<?= (int) $u['id'] ?>"><?= e($u['full_name']) ?> (<?= e($u['employee_id'] ?? '') ?>)</option>
        <?php endforeach; ?>
      </select>
      <label>Committee role</label>
      <select name="member_role">
        <option value="member">Member</option>
        <option value="chair">Chair</option>
        <option value="secretary">Secretary</option>
      </select>
      <div class="form-row">
        <div><label>Appointed from</label><input type="date" name="appointed_from"></div>
        <div><label>Appointed to</label><input type="date" name="appointed_to"></div>
      </div>
      <div class="form-actions"><button class="btn" type="submit">Add member</button></div>
    </form>
  </div>
</div>

<?php include APP_PATH . '/views/partials/footer.php'; ?>
