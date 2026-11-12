<?php $title = ($id > 0 ? 'Edit' : 'New') . ' Theme'; include APP_PATH . '/views/partials/header.php'; ?>

<div class="page-head">
    <div>
        <h1><?= $id > 0 ? 'Edit Theme' : 'New Theme' ?></h1>
    </div>
</div>

<div class="card">
    <form method="post" action="<?= e(url('action=theme_save')) ?>">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="id" value="<?= e($id) ?>">
        
        <label>Name <span class="req">*</span></label>
        <input type="text" name="name" value="<?= e($theme['name'] ?? '') ?>" required maxlength="150">
        
        <label>Code <span class="req">*</span></label>
        <input type="text" name="code" value="<?= e($theme['code'] ?? '') ?>" required maxlength="30" placeholder="e.g. AGRI">
        
        <label>Description</label>
        <textarea name="description" rows="3"><?= e($theme['description'] ?? '') ?></textarea>
        
        <label>Status</label>
        <select name="status">
            <option value="active" <?= ($theme['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= ($theme['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
        
        <div class="form-actions">
            <button class="btn" type="submit">Save Theme</button>
            <a href="<?= e(url('action=themes')) ?>" class="btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php include APP_PATH . '/views/partials/footer.php'; ?>

