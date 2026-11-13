<?php
use App\Core\Csrf;
use App\Core\Gate;

$title = 'Repository record';
include APP_PATH . '/views/partials/header.php';

$badge = ['draft' => 'badge-secondary', 'published' => 'badge-success', 'embargoed' => 'badge-warning', 'archived' => 'badge-secondary', 'withdrawn' => 'badge-danger'];
$visLabel = ['public' => 'Public', 'university_only' => 'University only', 'college_only' => 'College only', 'restricted' => 'Restricted', 'confidential' => 'Confidential', 'embargoed' => 'Embargoed'];
$canManage = Gate::allows('repository.manage');
$canPublish = Gate::allows('repository.publish');
?>
<div class="page-head">
  <div>
    <h1><?= e($record['title']) ?></h1>
    <p><code><?= e($record['repository_id_code']) ?></code> · <span class="badge <?= e($badge[$record['status']] ?? 'badge-secondary') ?>"><?= e(ucfirst($record['status'])) ?></span></p>
  </div>
  <?php if ($canManage): ?>
  <div>
    <a class="btn" href="<?= e(url('action=repository_form&id=' . (int) $record['id'])) ?>">Edit</a>
    <?php if ($canPublish && $record['status'] !== 'published' && $documents): ?>
    <form method="post" action="<?= e(url('action=repository_approve')) ?>" style="display:inline;">
      <?= Csrf::field() ?>
      <input type="hidden" name="id" value="<?= (int) $record['id'] ?>">
      <button class="btn" type="submit">Approve &amp; publish</button>
    </form>
    <?php endif; ?>
    <?php if ($canPublish && $record['status'] === 'published'): ?>
    <form method="post" action="<?= e(url('action=repository_unpublish')) ?>" style="display:inline;">
      <?= Csrf::field() ?>
      <input type="hidden" name="id" value="<?= (int) $record['id'] ?>">
      <button class="btn btn-outline" type="submit">Unpublish</button>
    </form>
    <?php endif; ?>
    <form method="post" action="<?= e(url('action=repository_delete')) ?>" style="display:inline;" onsubmit="return confirm('Delete this repository record permanently?');">
      <?= Csrf::field() ?>
      <input type="hidden" name="id" value="<?= (int) $record['id'] ?>">
      <button class="btn btn-danger" type="submit">Delete</button>
    </form>
  </div>
  <?php endif; ?>
</div>

<div class="grid grid-2">
  <div>
    <div class="card">
      <h2 class="mt0">Record</h2>
      <table class="tbl">
        <tr><th>Project</th><td><?= e($record['project_number']) ?> · <?= e($record['project_title']) ?></td></tr>
        <tr><th>Year</th><td><?= e((string) ($record['year'] ?? '—')) ?></td></tr>
        <tr><th>Type / theme</th><td><?= e($record['type_name'] ?? '—') ?> / <?= e($record['theme_name'] ?? '—') ?></td></tr>
        <tr><th>College / dept</th><td><?= e($record['college_name'] ?? '—') ?> / <?= e($record['department_name'] ?? '—') ?></td></tr>
        <tr><th>Visibility</th><td><?= e($visLabel[$record['visibility']] ?? $record['visibility']) ?></td></tr>
        <tr><th>Keywords</th><td><?= e($record['keywords'] ?? '—') ?></td></tr>
        <?php if (!empty($record['published_at'])): ?><tr><th>Published</th><td><?= e(format_datetime($record['published_at'])) ?></td></tr><?php endif; ?>
        <?php if (!empty($record['archived_at'])): ?><tr><th>Archived</th><td><?= e(format_datetime($record['archived_at'])) ?></td></tr><?php endif; ?>
        <tr><th>Added by</th><td><?= e($record['created_by_name'] ?? '—') ?> · <?= e(format_datetime($record['created_at'])) ?></td></tr>
      </table>
    </div>

    <div class="card">
      <h2 class="mt0">Abstract</h2>
      <p><?= nl2br(e($record['abstract'] ?? 'No abstract provided.')) ?></p>
      <?php if (!empty($record['citation_text'])): ?>
      <h3>Citation</h3>
      <p><?= nl2br(e($record['citation_text'])) ?></p>
      <?php endif; ?>
    </div>
  </div>

  <div>
    <!-- Research document (§64) -->
    <div class="card">
      <h2 class="mt0">Research document</h2>
      <?php if ($canPublish && $record['status'] !== 'published' && !$documents): ?>
      <div class="alert alert-warning" style="padding:10px 14px;">Attach the finished research document (PDF/DOC) below — approval requires it.</div>
      <?php endif; ?>
      <?php if (!$documents): ?><p class="muted">No document attached yet.</p><?php endif; ?>
      <?php foreach ($documents as $d): ?>
      <div class="doc-row">
        <div><?= e($d['type_name']) ?> v<?= (int) $d['version'] ?>
          <span class="muted" style="font-size:12px;"><?= e($d['original_filename']) ?> · <?= e(format_datetime($d['uploaded_at'])) ?> · <?= e($d['uploader_name'] ?? '') ?></span>
          <?= $d['status'] === 'superseded' ? '<span class="badge badge-secondary">superseded</span>' : '' ?></div>
        <a class="btn btn-outline btn-sm" href="<?= e(base_url('download.php?id=' . $d['id'])) ?>">Download</a>
      </div>
      <?php endforeach; ?>
      <?php if ($canManage): ?>
      <form method="post" action="<?= e(url('action=document_upload')) ?>" enctype="multipart/form-data" style="margin-top:12px;">
        <?= Csrf::field() ?>
        <input type="hidden" name="entity_type" value="repository_record">
        <input type="hidden" name="entity_id" value="<?= (int) $record['id'] ?>">
        <input type="hidden" name="visibility" value="university">
        <div class="form-row">
          <div><label>Document type</label>
            <select name="document_type_id">
              <?php foreach ($docTypes as $dt): ?>
              <option value="<?= (int) $dt['id'] ?>"><?= e($dt['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div><label>File</label><input type="file" name="document" required></div>
        </div>
        <button class="btn btn-outline btn-sm" type="submit">Upload document</button>
      </form>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2 class="mt0">Actions</h2>
      <p class="muted">A record linked to a project can be published, edited or deleted here. Deletion removes the record and its attached documents.</p>
      <a class="btn btn-outline btn-sm" href="<?= e(url('action=repository')) ?>">Back to repository</a>
    </div>
  </div>
</div>

<?php include APP_PATH . '/views/partials/footer.php'; ?>
