<?php $title = 'Research Themes'; include APP_PATH . '/views/partials/header.php'; ?>

<div class="page-head">
    <div>
        <h1>Research Themes</h1>
        <p>Manage university-level research themes (§6).</p>
    </div>
    <div class="actions">
        <a href="<?= e(url('action=theme_form')) ?>" class="btn">New Theme</a>
    </div>
</div>

<div class="card">
    <table class="tbl">
        <thead>
            <tr>
                <th>Code</th>
                <th>Name</th>
                <th>Description</th>
                <th>Calls</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($themes as $t): ?>
            <tr>
                <td><?= e($t['code']) ?></td>
                <td><?= e($t['name']) ?></td>
                <td><?= e($t['description'] ?? '') ?></td>
                <td><?= (int) \App\Models\ThemeModel::countCalls((int)$t['id']) ?></td>
                <td>
                    <span class="badge <?= $t['status'] === 'active' ? 'badge-success' : 'badge-secondary' ?>">
                        <?= e($t['status']) ?>
                    </span>
                </td>
                <td>
                    <a href="<?= e(url('action=theme_form&id=' . $t['id'])) ?>" class="btn-sm">Edit</a>
                    <?php if ($t['status'] === 'active'): ?>
                        <form method="post" action="<?= e(url('action=theme_status')) ?>" style="display:inline;">
                            <?= \App\Core\Csrf::field() ?>
                            <input type="hidden" name="id" value="<?= e($t['id']) ?>">
                            <input type="hidden" name="status" value="inactive">
                            <button type="submit" class="btn-sm btn-secondary">Deactivate</button>
                        </form>
                    <?php else: ?>
                        <form method="post" action="<?= e(url('action=theme_status')) ?>" style="display:inline;">
                            <?= \App\Core\Csrf::field() ?>
                            <input type="hidden" name="id" value="<?= e($t['id']) ?>">
                            <input type="hidden" name="status" value="active">
                            <button type="submit" class="btn-sm btn-success">Activate</button>
                        </form>
                    <?php endif; ?>
                    <form method="post" action="<?= e(url('action=theme_delete')) ?>" style="display:inline;" onsubmit="return confirm('Delete this theme?')">
                        <?= \App\Core\Csrf::field() ?>
                        <input type="hidden" name="id" value="<?= e($t['id']) ?>">
                        <button type="submit" class="btn-sm btn-danger">Delete</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include APP_PATH . '/views/partials/footer.php'; ?>
