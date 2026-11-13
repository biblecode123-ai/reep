<?php $title = 'User roles'; include APP_PATH . '/views/partials/header.php'; ?>

<div class="page-head"><div><h1>Access profile: <?= e($user['full_name']) ?></h1>
<p>Choose one primary account category. Assign operational responsibilities separately; existing committee and evaluator assignments remain the source of record for those workflows.</p></div></div>

<form method="post" action="<?= e(url('action=user_roles_save')) ?>">
  <?= \App\Core\Csrf::field() ?>
  <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
  <section class="card" aria-labelledby="primary-role-heading">
    <h2 id="primary-role-heading">Primary account category</h2>
    <p class="muted">Select exactly one of the four institutional account categories.</p>
    <div class="role-choice-grid">
      <?php foreach ($roles as $r): ?>
      <label class="role-choice">
        <input type="radio" name="primary_role" value="<?= (int) $r['id'] ?>" required
          <?= in_array((int) $r['id'], $userRoleIds, true) ? 'checked' : '' ?>>
        <span><strong><?= e($r['display_name']) ?></strong>
          <span class="muted"><?= e($r['description'] ?? '') ?></span></span>
      </label>
      <?php endforeach; ?>
    </div>
  </section>
  <section class="card" aria-labelledby="responsibility-heading">
    <h2 id="responsibility-heading">Responsibilities and capabilities</h2>
    <p class="muted">These assignments do not replace committee membership or item-level evaluator assignments.</p>
    <div class="role-choice-grid">
      <?php foreach ($assignments as $assignment): ?>
      <label class="role-choice">
        <input type="checkbox" name="responsibilities[]" value="<?= (int) $assignment['id'] ?>"
          <?= in_array((int) $assignment['id'], $userResponsibilityIds, true) ? 'checked' : '' ?>>
        <span><strong><?= e($assignment['display_name']) ?></strong>
          <span class="muted"><?= e($assignment['description'] ?? '') ?></span></span>
      </label>
      <?php endforeach; ?>
    </div>
  </section>
  <div class="form-actions">
    <button class="btn" type="submit">Save access profile</button>
    <a class="btn btn-outline" href="<?= e(url('action=users')) ?>">Cancel</a>
  </div>
</form>

<?php include APP_PATH . '/views/partials/footer.php'; ?>
