<?php $title = 'System settings'; include APP_PATH . '/views/partials/header.php'; ?>

<div class="page-head"><div><h1>System settings</h1>
<p>Database-driven configuration (§103). Changes are audited.</p></div></div>

<form method="post" action="<?= e(url('action=settings_save')) ?>">
  <?= \App\Core\Csrf::field() ?>
  <?php
    $grouped = [];
    foreach ($settings as $s) { $grouped[$s['setting_group']][] = $s; }
    foreach ($grouped as $group => $items): ?>
    <div class="card">
      <h2 class="mt0"><?= e(ucfirst($group)) ?></h2>
      <table class="tbl">
        <tr><th style="width:280px;">Key</th><th>Value</th><th>Description</th></tr>
        <?php foreach ($items as $s): ?>
        <tr>
          <td><code><?= e($s['setting_key']) ?></code></td>
          <td>
            <?php if ($s['value_type'] === 'boolean'): ?>
              <select name="settings[<?= e($s['setting_key']) ?>]">
                <option value="1" <?= $s['setting_value'] === '1' ? 'selected' : '' ?>>Enabled</option>
                <option value="0" <?= $s['setting_value'] !== '1' ? 'selected' : '' ?>>Disabled</option>
              </select>
            <?php else: ?>
              <input type="text" name="settings[<?= e($s['setting_key']) ?>]" value="<?= e($s['setting_value']) ?>" style="min-width:260px;">
            <?php endif; ?>
          </td>
          <td class="muted" style="font-size:12.5px;"><?= e($s['description'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>
  <?php endforeach; ?>
  <div class="form-actions"><button class="btn" type="submit">Save all settings</button></div>
</form>

<?php include APP_PATH . '/views/partials/footer.php'; ?>

