<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>500 · Error</title>
<link rel="stylesheet" href="<?= e(base_url('assets/css/app.css')) ?>"></head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <h1>Something went wrong</h1>
    <p class="sub"><?= e($errorMessage ?? 'An unexpected error occurred.') ?></p>
    <div class="form-actions" style="justify-content:center;">
      <a class="btn" href="<?= e(url('')) ?>">Back to dashboard</a>
    </div>
  </div>
</div>
</body>
</html>

