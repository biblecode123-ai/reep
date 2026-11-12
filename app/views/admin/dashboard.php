<?php
$title = 'System Administration Dashboard';
include APP_PATH . '/views/partials/header.php';
?>
<h1>System Administration Dashboard</h1>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <h3>Institutional Overview</h3>
            <ul>
                <li>Total Users: <?= $stats['users']['total'] ?></li>
                <li>Active Users: <?= $stats['users']['active'] ?></li>
                <li>Colleges (Active/Total): <?= $stats['org']['active_colleges'] ?> / <?= $stats['org']['colleges'] ?></li>
                <li>Departments (Active/Total): <?= $stats['org']['active_departments'] ?> / <?= $stats['org']['departments'] ?></li>
            </ul>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <h3>User Distribution</h3>
            <ul>
                <?php foreach ($stats['roles'] as $role => $count): ?>
                <li><?= ucfirst(str_replace('_', ' ', $role)) ?>: <?= $count ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <h3>Quick Links</h3>
            <a href="index.php?action=users" class="btn">Manage Users</a>
            <a href="index.php?action=colleges" class="btn">Manage Colleges</a>
            <a href="index.php?action=settings" class="btn">System Settings</a>
            <a href="index.php?action=audit" class="btn">Audit Logs</a>
        </div>
    </div>
</div>

<?php include APP_PATH . '/views/partials/footer.php'; ?>
