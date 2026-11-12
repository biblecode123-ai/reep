<?php $title = 'Sign in'; $bodyClass = 'auth-solid'; include __DIR__ . '/../partials/header.php'; ?>
<div class="login-wrap" style="margin:-24px auto 0;">
  <div class="login-card">
    <h1>MWU RPEE</h1>
    <p class="sub">Research, Publication, Ethics and Extension Office</p>
    <?= flash_html() ?>
    <?php if (!empty($error)): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <?php if (!empty($warning)): ?><div class="alert alert-warning"><?= e($warning) ?></div><?php endif; ?>
    <form method="post" action="<?= e(base_url('login.php?action=do_login')) ?>">
      <?= \App\Core\Csrf::field() ?>
      <label>Employee ID or Email <span class="req">*</span></label>
      <input type="text" name="identifier" required autofocus autocomplete="username">
      <label>Password <span class="req">*</span></label>
      <input type="password" name="password" required autocomplete="current-password">
      <div class="form-actions" style="justify-content:space-between;">
        <button class="btn" type="submit">Sign in</button>
        <a href="<?= e(url('action=forgot')) ?>" class="muted" style="font-size:13px;">Forgot password?</a>
      </div>
    </form>
  </div>
</div>
<?php include __DIR__ . '/../partials/footer.php'; ?>

