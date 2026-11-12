<?php $title = 'System Administrator Dashboard'; include APP_PATH . '/views/partials/header.php'; ?>

<div class="page-head">
    <div>
        <h1>System Administration</h1>
        <p>MWU-RMS Operational Overview</p>
    </div>
</div>

<div class="grid grid-4">
    <div class="stat"><div class="num"><?= (int) ($stats['total_users'] ?? 0) ?></div><div class="lbl">Total Users</div></div>
    <div class="stat"><div class="num"><?= (int) ($stats['active_users'] ?? 0) ?></div><div class="lbl">Active Users</div></div>
    <div class="stat"><div class="num"><?= (int) ($stats['colleges'] ?? 0) ?></div><div class="lbl">Colleges</div></div>
    <div class="stat"><div class="num"><?= (int) ($stats['depts'] ?? 0) ?></div><div class="lbl">Departments</div></div>
</div>

<div class="grid grid-2">
    <div class="card">
        <h2 class="mt0">Users by Role</h2>
        <table class="tbl">
            <tr><th>Role</th><th>Count</th></tr>
            <?php foreach ($roles as $r): ?>
            <tr>
                <td><?= e($r['name']) ?></td>
                <td><?= (int) $r['cnt'] ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <div class="card">
        <h2 class="mt0">Recent Activity</h2>
        <table class="tbl">
            <tr><th>Action</th><th>Actor</th><th>Time</th></tr>
            <?php foreach ($recentLogs as $log): ?>
            <tr>
                <td><?= e($log['action']) ?></td>
                <td><?= e($log['actor_name'] ?? 'System') ?></td>
                <td><?= e(format_datetime($log['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <div class="form-actions"><a class="btn btn-outline btn-sm" href="<?= e(url('action=audit')) ?>">View All Logs</a></div>
    </div>
</div>

<?php include APP_PATH . '/views/partials/footer.php'; ?>

