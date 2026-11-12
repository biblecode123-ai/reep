<?php $title = 'Set new password'; $bodyClass = 'auth-solid'; include __DIR__ . '/../partials/header.php'; ?>
<div class="login-wrap" style="margin:-24px auto 0;">
  <div class="login-card">
    <h1>Set new password</h1>
    <?= flash_html() ?>
    <form method="post" action="<?= e(url('action=do_reset')) ?>">
      <?= \App\Core\Csrf::field() ?>
      <input type="hidden" name="token" value="<?= e($token ?? '') ?>">
      <label>New password <span class="req">*</span></label>
      <input type="password" name="password" required minlength="8">
      <label>Confirm password <span class="req">*</span></label>
      <input type="password" name="password_confirm" required minlength="8">
      <div class="form-actions">
        <button class="btn" type="submit">Update password</button>
      </div>
    </form>
  </div>
</div>
<?php include __DIR__ . '/../partials/footer.php'; ?>

