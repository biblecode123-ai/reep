<?php $title = 'Applications'; include APP_PATH . '/views/partials/header.php'; ?>

<div class="page-head">
  <div><h1>Research applications</h1><p>Filtered list — server-side pagination (spec §86)</p></div>
  <?php if (Gate::allows('research.create')): ?>
    <a class="btn" href="<?= e(url('action=calls_public')) ?>">New application</a>
  <?php endif; ?>
</div>

<form class="filters" method="get" action="<?= e(base_url('index.php')) ?>">
  <input type="hidden" name="action" value="applications">
  <div><label>Search</label><input type="text" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Title or number"></div>
  <div><label>Status</label>
    <select name="status">
      <option value="">All</option>
      <?php foreach (['DRAFT','SUBMITTED','COLLEGE_RECEIVED','UNDER_COLLEGE_SCREENING','EVALUATOR_ASSIGNMENT_PENDING','EVALUATOR_ASSIGNED','UNDER_EVALUATION','REVISION_REQUIRED','COLLEGE_APPROVED','SUBMITTED_TO_UNIVERSITY','APPROVED','FUNDED','ACTIVE','COMPLETED','REJECTED'] as $st): ?>
      <option value="<?= e($st) ?>" <?= ($filters['status'] ?? '') === $st ? 'selected' : '' ?>><?= e(status_label($st)) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <button class="btn btn-outline" type="submit">Filter</button>
</form>

<?php if (!$rows): ?><p class="muted">No applications found.</p><?php else: ?>
<table class="tbl">
  <tr><th>Number</th><th>Title</th><th>PI</th><th>College</th><th>Call</th><th>Budget</th><th>Status</th><th class="actions"></th></tr>
  <?php foreach ($rows as $a): ?>
  <tr>
    <td><?= e($a['application_number']) ?></td>
    <td><?= e(mb_strimwidth($a['title'], 0, 60, '…')) ?></td>
    <td><?= e($a['pi_name']) ?></td>
    <td><?= e($a['college_name'] ?? '—') ?></td>
    <td><a href="<?= e(url('action=call_view&id=' . ($a['call_id'] ?? 0))) ?>"><?= e(mb_strimwidth($a['call_title'] ?? '', 0, 30, '…')) ?></a></td>
    <td><?= e(format_money($a['budget_requested'])) ?></td>
    <td><span class="badge <?= status_badge($a['status_code']) ?>"><?= e(status_label($a['status_code'])) ?></span></td>
    <td class="actions"><a class="btn btn-outline btn-sm" href="<?= e(url('action=application_view&id=' . $a['id'])) ?>">Open</a></td>
  </tr>
  <?php endforeach; ?>
</table>
<?= pagination_links($p, $qs ?? '') ?>
<?php endif; ?>

<?php include APP_PATH . '/views/partials/footer.php'; ?>
