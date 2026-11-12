<?php $title = 'System Health'; include APP_PATH . '/views/partials/header.php'; ?>

<div class="page-head">
    <div>
        <h1>System Health</h1>
    </div>
</div>

<div class="card">
    <dl class="kv">
        <dt>PHP Version</dt><dd><?= e($php_version) ?></dd>
        <dt>Database Connection</dt><dd><span class="badge <?= $db_connected ? 'badge-success' : 'badge-danger' ?>"><?= $db_connected ? 'Connected' : 'Failed' ?></span></dd>
        <dt>Database Integrity</dt><dd><?= e($integrity) ?></dd>
        <dt>Storage Status</dt><dd><span class="badge <?= $storage_writable ? 'badge-success' : 'badge-danger' ?>"><?= $storage_writable ? 'Writable' : 'Not Writable' ?></span></dd>
        <dt>Storage Free Space</dt><dd><?= number_format($disk_free / 1024 / 1024 / 1024, 2) ?> GB</dd>
    </dl>
</div>

<?php include APP_PATH . '/views/partials/footer.php'; ?>

