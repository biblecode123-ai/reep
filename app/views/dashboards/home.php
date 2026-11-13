<?php $title = 'Dashboard'; include APP_PATH . '/views/partials/header.php'; ?>

<div class="page-head">
  <div>
    <h1>Welcome, <?= e(Auth::user()['full_name'] ?? '') ?></h1>
    <p>Your research management overview</p>
  </div>
</div>

<?php if (Gate::allows('research.manage')): ?>
<div class="grid grid-4">
  <div class="stat"><div class="num"><?= (int) ($stats['calls'] ?? 0) ?></div><div class="lbl">Research calls</div></div>
  <div class="stat"><div class="num"><?= (int) ($stats['applications'] ?? 0) ?></div><div class="lbl">Applications</div></div>
  <div class="stat"><div class="num"><?= (int) ($stats['projects'] ?? 0) ?></div><div class="lbl">Projects</div></div>
  <div class="stat"><div class="num"><?= (int) ($stats['active'] ?? 0) ?></div><div class="lbl">Active projects</div></div>
  <div class="stat"><div class="num"><?= (int) ($stats['pendingEval'] ?? 0) ?></div><div class="lbl">Pending evaluations</div></div>
  <div class="stat"><div class="num"><?= (int) ($stats['ethicsPending'] ?? 0) ?></div><div class="lbl">Pending ethics</div></div>
  <div class="stat"><div class="num"><?= (int) ($stats['published'] ?? 0) ?></div><div class="lbl">Publications</div></div>
  <div class="stat"><div class="num"><?= (int) ($stats['repoRecords'] ?? 0) ?></div><div class="lbl">Repository records</div></div>
</div>

<h2>Application pipeline</h2>
<?php if (!$statuses): ?><p class="muted">No applications yet.</p><?php else: ?>
<div class="card card-chart">
    <canvas id="pipelineChart" style="max-height: 300px;"></canvas>
    <script>
        const pipelineData = <?= json_encode(array_map(function($s) { return ['label' => status_label($s['status_code']), 'count' => (int)$s['cnt']]; }, $statuses)) ?>;
    </script>
</div>
<table class="tbl">
    <tr><th>Status</th><th>Count</th></tr>
  <?php foreach ($statuses as $s): ?>
  <tr>
    <td><a href="<?= e(url('action=applications&status=' . urlencode($s['status_code']))) ?>">
      <span class="badge <?= status_badge($s['status_code']) ?>"><?= e(status_label($s['status_code'])) ?></span></a></td>
    <td><?= (int) $s['cnt'] ?></td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>
<?php endif; ?>

<?php if (!empty($collegeQueue)): ?>
<div class="card">
  <h2 class="mt0">College screening queue</h2>
  <table class="tbl">
    <tr><th>Number</th><th>Title</th><th>PI</th><th>Status</th><th></th></tr>
    <?php foreach ($collegeQueue as $a): ?>
    <tr>
      <td><?= e($a['application_number']) ?></td>
      <td><?= e($a['title']) ?></td>
      <td><?= e($a['pi_name']) ?></td>
      <td><span class="badge <?= status_badge($a['status_code']) ?>"><?= e(status_label($a['status_code'])) ?></span></td>
      <td class="actions"><a class="btn btn-outline btn-sm" href="<?= e(url('action=application_view&id=' . $a['id'])) ?>">Open</a></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php endif; ?>

<?php if (Gate::allows('research.evaluate')): ?>
<div class="card">
  <h2 class="mt0">My evaluations</h2>
  <div class="grid grid-4">
    <div class="stat"><div class="num"><?= (int) ($evalStats['assigned'] ?? 0) ?></div><div class="lbl">Awaiting response</div></div>
    <div class="stat"><div class="num"><?= (int) ($evalStats['accepted'] ?? 0) ?></div><div class="lbl">Accepted</div></div>
    <div class="stat"><div class="num"><?= (int) ($evalStats['completed'] ?? 0) ?></div><div class="lbl">Completed</div></div>
    <div class="stat"><div class="num"><?= (int) (($evalStats['assigned'] ?? 0) + ($evalStats['accepted'] ?? 0)) ?></div><div class="lbl">Open</div></div>
  </div>
  <div class="form-actions"><a class="btn btn-outline" href="<?= e(url('action=my_evaluations')) ?>">Go to my evaluations</a></div>
</div>
<?php endif; ?>

<?php if (Gate::allows('research.create')): ?>
<div class="grid grid-2">
  <div class="card">
    <h2 class="mt0">Open calls</h2>
    <?php if (!$openCalls): ?><p class="muted">No open calls right now.</p><?php else: ?>
    <table class="tbl">
      <tr><th>Call</th><th>Closes</th><th></th></tr>
      <?php foreach ($openCalls as $c): ?>
      <tr>
        <td><?= e($c['title']) ?><div class="muted" style="font-size:12px;"><?= e($c['reference_number']) ?></div></td>
        <td><?= e(format_date($c['closes_at'])) ?></td>
        <td class="actions"><a class="btn btn-sm" href="<?= e(url('action=application_form&call_id=' . $c['id'])) ?>">Apply</a></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>
  </div>
  <div class="card">
    <h2 class="mt0">My applications</h2>
    <?php if (!$myApps): ?><p class="muted">You have not applied yet.</p><?php else: ?>
    <table class="tbl">
      <tr><th>Number</th><th>Title</th><th>Status</th></tr>
      <?php foreach ($myApps as $a): ?>
      <tr>
        <td><a href="<?= e(url('action=application_view&id=' . $a['id'])) ?>"><?= e($a['application_number']) ?></a></td>
        <td><?= e(mb_strimwidth($a['title'], 0, 48, '…')) ?></td>
        <td><span class="badge <?= status_badge($a['status_code']) ?>"><?= e(status_label($a['status_code'])) ?></span></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php if (isset($pubStats)): ?>
<div class="card">
  <h2 class="mt0">Publications overview</h2>
  <div class="grid grid-4">
    <div class="stat"><div class="num"><?= (int) ($pubStats['published'] ?? 0) ?></div><div class="lbl">Published</div></div>
    <div class="stat"><div class="num"><?= (int) ($pubStats['pipeline'] ?? 0) ?></div><div class="lbl">In review pipeline</div></div>
  </div>
  <?php if (!empty($recentPubs)): ?>
  <table class="tbl">
    <tr><th>Title</th><th>Journal</th><th>Year</th><th>Status</th></tr>
    <?php foreach ($recentPubs as $p): ?>
    <tr>
      <td><?= e(mb_strimwidth((string) $p['title'], 0, 60, '…')) ?></td>
      <td><?= e($p['journal_name'] ?? '—') ?></td>
      <td><?= e((string) ($p['publication_year'] ?? '—')) ?></td>
      <td><span class="badge badge-secondary"><?= e(status_label((string) $p['status'])) ?></span></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if (isset($extStats)): ?>
<div class="card">
  <h2 class="mt0">Extension overview</h2>
  <div class="grid grid-4">
    <div class="stat"><div class="num"><?= (int) ($extStats['active'] ?? 0) ?></div><div class="lbl">Active extension projects</div></div>
    <div class="stat"><div class="num"><?= (int) ($extStats['planned'] ?? 0) ?></div><div class="lbl">Planned activities</div></div>
    <div class="stat"><div class="num"><?= (int) ($extStats['completed'] ?? 0) ?></div><div class="lbl">Completed activities</div></div>
    <div class="stat"><div class="num"><?= number_format((float) ($extStats['beneficiaries'] ?? 0)) ?></div><div class="lbl">Beneficiaries reached</div></div>
  </div>
  <?php if (!empty($recentExt)): ?>
  <table class="tbl">
    <tr><th>Number</th><th>Title</th><th>Community</th><th>Ends</th><th>Status</th></tr>
    <?php foreach ($recentExt as $x): ?>
    <tr>
      <td><?= e($x['project_number']) ?></td>
      <td><?= e(mb_strimwidth((string) $x['title'], 0, 55, '…')) ?></td>
      <td><?= e($x['community'] ?? '—') ?></td>
      <td><?= e(format_date($x['planned_end'] ?? null)) ?></td>
      <td><span class="badge badge-secondary"><?= e(status_label((string) $x['status'])) ?></span></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if (isset($panels)): ?>
<div class="card">
  <h2 class="mt0">My panel assignments</h2>
  <?php if (!$panels): ?><p class="muted">No panel assignments yet.</p><?php else: ?>
  <table class="tbl">
    <tr><th>Project</th><th>My role</th><th>Scheduled</th><th>Venue</th><th>Status</th></tr>
    <?php foreach ($panels as $pa): ?>
    <tr>
      <td><?= e(mb_strimwidth((string) $pa['title'], 0, 55, '…')) ?></td>
      <td><?= e(status_label((string) $pa['member_role'])) ?></td>
      <td><?= e(format_date($pa['scheduled_at'] ?? null)) ?></td>
      <td><?= e($pa['venue'] ?? '—') ?></td>
      <td><span class="badge badge-secondary"><?= e(status_label((string) $pa['presentation_status'])) ?></span></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
  <?php if (!empty($upcomingPanels)): ?>
  <h2>All scheduled presentations</h2>
  <table class="tbl">
    <tr><th>Project</th><th>Scheduled</th><th>Venue</th><th>Status</th></tr>
    <?php foreach ($upcomingPanels as $pr): ?>
    <tr>
      <td><?= e(mb_strimwidth((string) $pr['title'], 0, 55, '…')) ?></td>
      <td><?= e(format_date($pr['scheduled_at'] ?? null)) ?></td>
      <td><?= e($pr['venue'] ?? '—') ?></td>
      <td><span class="badge badge-secondary"><?= e(status_label((string) $pr['status'])) ?></span></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if (isset($repoStats)): ?>
<div class="card">
  <h2 class="mt0">Repository overview</h2>
  <div class="grid grid-4">
    <div class="stat"><div class="num"><?= (int) ($repoStats['published'] ?? 0) ?></div><div class="lbl">Published records</div></div>
    <div class="stat"><div class="num"><?= (int) ($repoStats['draft'] ?? 0) ?></div><div class="lbl">Draft records</div></div>
    <div class="stat"><div class="num"><?= (int) ($repoStats['embargoed'] ?? 0) ?></div><div class="lbl">Embargoed</div></div>
  </div>
  <?php if (!empty($recentRepo)): ?>
  <table class="tbl">
    <tr><th>ID</th><th>Title</th><th>Year</th><th>Visibility</th><th>Status</th></tr>
    <?php foreach ($recentRepo as $r): ?>
    <tr>
      <td><?= e($r['repository_id_code']) ?></td>
      <td><?= e(mb_strimwidth((string) $r['title'], 0, 55, '…')) ?></td>
      <td><?= e((string) ($r['year'] ?? '—')) ?></td>
      <td><?= e(status_label((string) $r['visibility'])) ?></td>
      <td><span class="badge badge-secondary"><?= e(status_label((string) $r['status'])) ?></span></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php include APP_PATH . '/views/partials/footer.php'; ?>
