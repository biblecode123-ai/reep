<?php $title = 'Research Theme Catalogue'; include APP_PATH . '/views/partials/header.php'; ?>

<div class="page-head mb-4">
  <div>
    <div class="badge badge-purple mb-1">Research Directorate</div>
    <h1 class="page-title">Global University Research Themes</h1>
    <p class="text-muted">Centralized research themes catalogued across Madda Walabu University. Themes are associated with research calls by the Director and chosen by researchers upon proposal submission.</p>
  </div>
  <div class="actions">
    <a href="<?= e(url('action=theme_form')) ?>" class="btn btn-primary">
      <i class="fas fa-plus mr-1"></i> Create Research Theme
    </a>
  </div>
</div>

<div class="card mb-4">
  <div class="card-header border-bottom pb-3 mb-3 d-flex justify-content-between align-items-center">
    <h2 class="card-title text-purple h5 m-0"><i class="fas fa-compass mr-2"></i> University Theme Directory</h2>
    <span class="badge badge-purple"><?= count($themes) ?> Active & Catalogued Themes</span>
  </div>

  <div class="table-responsive">
    <table class="table table-bordered table-hover mb-0">
      <thead class="bg-light">
        <tr>
          <th style="width: 100px;">Code</th>
          <th>Theme Title & Focus Area</th>
          <th>Description & Scope</th>
          <th style="width: 120px;" class="text-center">Associated Calls</th>
          <th style="width: 100px;">Status</th>
          <th style="width: 180px;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($themes as $t): ?>
          <tr>
            <td><code class="font-weight-bold text-purple"><?= e($t['code']) ?></code></td>
            <td><strong><?= e($t['name']) ?></strong></td>
            <td><small class="text-muted"><?= e(mb_strimwidth($t['description'] ?? 'No description provided.', 0, 90, '…')) ?></small></td>
            <td class="text-center">
              <span class="badge badge-outline">
                <?= (int) \App\Models\ThemeModel::countCalls((int)$t['id']) ?> calls
              </span>
            </td>
            <td>
              <span class="badge badge-<?= $t['status'] === 'active' ? 'green' : 'amber' ?>">
                <?= ucfirst(e($t['status'])) ?>
              </span>
            </td>
            <td>
              <div class="d-flex gap-1 align-items-center">
                <a href="<?= e(url('action=theme_form&id=' . $t['id'])) ?>" class="btn btn-sm btn-outline">Edit</a>
                <?php if ($t['status'] === 'active'): ?>
                  <form method="post" action="<?= e(url('action=theme_status')) ?>" class="m-0">
                    <?= \App\Core\Csrf::field() ?>
                    <input type="hidden" name="id" value="<?= e($t['id']) ?>">
                    <input type="hidden" name="status" value="inactive">
                    <button type="submit" class="btn btn-sm btn-outline text-warning">Deactivate</button>
                  </form>
                <?php else: ?>
                  <form method="post" action="<?= e(url('action=theme_status')) ?>" class="m-0">
                    <?= \App\Core\Csrf::field() ?>
                    <input type="hidden" name="id" value="<?= e($t['id']) ?>">
                    <input type="hidden" name="status" value="active">
                    <button type="submit" class="btn btn-sm btn-outline text-success">Activate</button>
                  </form>
                <?php endif; ?>
                <form method="post" action="<?= e(url('action=theme_delete')) ?>" class="m-0" onsubmit="return confirm('Archive/Delete this theme?')">
                  <?= \App\Core\Csrf::field() ?>
                  <input type="hidden" name="id" value="<?= e($t['id']) ?>">
                  <button type="submit" class="btn btn-sm btn-outline text-danger">&times;</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include APP_PATH . '/views/partials/footer.php'; ?>
