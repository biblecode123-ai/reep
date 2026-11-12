<?php $title = 'Workflows'; include APP_PATH . '/views/partials/header.php'; ?>

<div class="page-head"><div><h1>Workflow configuration</h1>
<p>State machines are stored in the database (§92–§96). Toggle transitions to enable/disable paths.</p></div></div>

<div class="grid grid-2">
  <div class="card">
    <h2 class="mt0">Workflows</h2>
    <table class="tbl">
      <tr><th>Name</th><th>Entity</th><th>Stages</th><th>Transitions</th><th></th></tr>
      <?php foreach ($workflows as $w): ?>
      <tr>
        <td><?= e($w['name']) ?></td>
        <td><code style="font-size:12px;"><?= e($w['entity_type']) ?></code></td>
        <td><?= (int) $w['stage_count'] ?></td>
        <td><?= (int) $w['transition_count'] ?></td>
        <td class="actions"><a class="btn btn-outline btn-sm" href="<?= e(url('action=workflows&id=' . $w['id'])) ?>">View</a></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <?php if ($selected !== null): ?>
  <div class="card">
    <h2 class="mt0"><?= e($selected['name']) ?></h2>
    <h3>Stages</h3>
    <table class="tbl">
      <tr><th>Code</th><th>Name</th><th>Type</th></tr>
      <?php foreach ($stages as $s): ?>
      <tr>
        <td><code style="font-size:12px;"><?= e($s['stage_code']) ?></code></td>
        <td><?= e($s['stage_name']) ?></td>
        <td><span class="badge <?= $s['stage_type'] === 'start' ? 'badge-primary' : ($s['stage_type'] === 'terminal' ? 'badge-secondary' : 'badge-info') ?>"><?= e($s['stage_type']) ?></span>
          <?= $s['is_terminal_exception'] ? '<span class="badge badge-danger">exception</span>' : '' ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php if ($selected !== null && $transitions): ?>
<div class="card">
  <h2 class="mt0">Transitions</h2>
  <table class="tbl">
    <tr><th>From</th><th>Action</th><th>To</th><th>Required permission</th><th>Condition</th><th>Active</th></tr>
    <?php foreach ($transitions as $t): ?>
    <tr>
      <td><?= e($t['from_name']) ?></td>
      <td><strong><?= e($t['transition_name']) ?></strong></td>
      <td><?= e($t['to_name']) ?></td>
      <td><code style="font-size:12px;"><?= e($t['required_permission'] ?? 'any participant') ?></code></td>
      <td><?= e($t['condition_key'] ?? '—') ?></td>
      <td>
        <form class="inline-form" method="post" action="<?= e(url('action=workflow_toggle')) ?>">
          <?= \App\Core\Csrf::field() ?>
          <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
          <input type="hidden" name="active" value="<?= (int) !$t['is_active'] ?>">
          <button class="btn btn-sm <?= $t['is_active'] ? 'btn-outline' : '' ?>" type="submit"><?= $t['is_active'] ? 'Active' : 'Disabled' ?></button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php endif; ?>

<?php include APP_PATH . '/views/partials/footer.php'; ?>

