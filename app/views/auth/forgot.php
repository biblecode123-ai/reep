<?php $title = 'Forgot password'; $bodyClass = 'auth-solid'; include __DIR__ . '/../partials/header.php'; ?>
<div class="login-wrap" style="margin:-24px auto 0;">
  <div class="login-card">
    <h1>Reset password</h1>
    <p class="sub">Request a password reset link</p>
    <?= flash_html() ?>
    <?php if (!empty($message)): ?><div class="alert alert-info"><?= e($message) ?></div><?php endif; ?>
    <form method="post" action="<?= e(url('action=send_reset')) ?>">
      <?= \App\Core\Csrf::field() ?>
      <label>Email address <span class="req">*</span></label>
      <input type="email" name="email" required>
      <div class="form-actions">
        <button class="btn" type="submit">Generate reset link</button>
        <a class="btn btn-outline" href="<?= e(base_url('login.php')) ?>">Back to login</a>
      </div>
    </form>
  </div>
</div>
<?php include __DIR__ . '/../partials/footer.php'; ?>

