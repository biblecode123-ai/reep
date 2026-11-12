<?php $title = 'Research calls'; include APP_PATH . '/views/partials/header.php'; ?>

<div class="page-head">
  <div>
    <h1>Research calls</h1>
    <p>Announcements from the RPEE office</p>
  </div>
  <?php if (Gate::allows('research.manage')): ?>
  <a class="btn" href="<?= e(url('action=call_form')) ?>">New call</a>
  <?php endif; ?>
</div>

<form class="filters" method="get" action="<?= e(base_url('index.php')) ?>">
  <input type="hidden" name="action" value="calls">
  <div><label>Search</label><input type="text" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Title or reference"></div>
  <div><label>Status</label>
    <select name="status">
      <option value="">All</option>
      <?php foreach (['draft','published','open','closed','under_evaluation','completed','archived'] as $st): ?>
      <option value="<?= e($st) ?>" <?= ($filters['status'] ?? '') === $st ? 'selected' : '' ?>><?= e(ucfirst($st)) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <button class="btn btn-outline" type="submit">Filter</button>
</form>

<?php if (!$rows): ?><p class="muted">No calls found.</p><?php else: ?>
<table class="tbl">
  <tr><th>Call number</th><th>Title</th><th>Research theme</th><th>Closes</th><th>Applications</th><th>Status</th><th class="actions">Actions</th></tr>
  <?php foreach ($rows as $c): ?>
  <tr>
    <td><?= e($c['call_number']) ?><div class="muted" style="font-size:12px;"><?= e($c['reference_number']) ?></div></td>
    <td><?= e($c['title']) ?></td>
    <td><?= e($c['theme_name'] ?? '—') ?></td>
    <td><?= e(format_date($c['closes_at'])) ?></td>
    <td><?= (int) $c['application_count'] ?></td>
    <td><span class="badge <?= $c['status'] === 'open' || $c['status'] === 'published' ? 'badge-success' : 'badge-secondary' ?>"><?= e(ucfirst($c['status'])) ?></span></td>
    <td class="actions">
      <a class="btn btn-outline btn-sm" href="<?= e(url('action=call_view&id=' . $c['id'])) ?>">View</a>
      <?php if (Gate::allows('research.manage')): ?>
      <a class="btn btn-outline btn-sm" href="<?= e(url('action=call_form&id=' . $c['id'])) ?>">Edit</a>
      <?php endif; ?>
      <?php if (in_array((int) $c['id'], $appliedCallIds ?? [], true)): ?>
      <button class="btn btn-sm" disabled>Already Applied</button>
      <?php elseif (Gate::allows('research.create') && in_array($c['status'], ['published', 'open'], true)): ?>
      <a class="btn btn-sm" href="<?= e(url('action=application_form&call_id=' . $c['id'])) ?>">Apply</a>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<?= pagination_links($p, $qs ?? '') ?>
<?php endif; ?>

<?php include APP_PATH . '/views/partials/footer.php'; ?>
