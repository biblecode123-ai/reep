<?php
$title = 'Researcher Dashboard';
include APP_PATH . '/views/partials/header.php';
?>

<div class="page-head">
  <div><h1>Researcher Dashboard</h1></div>
</div>

<!-- Summary Cards -->
<section>
  <h2 class="section-title">Summary</h2>
  <div class="grid grid-4">
    <div class="stat">
      <div class="num"><?= $stats['draftProposals'] ?></div>
      <div class="lbl">Draft Proposals</div>
    </div>
    <div class="stat">
      <div class="num"><?= $stats['submittedProposals'] ?></div>
      <div class="lbl">Submitted Proposals</div>
    </div>
    <div class="stat">
      <div class="num"><?= $stats['activeProjects'] ?></div>
      <div class="lbl">Active Projects</div>
    </div>
    <div class="stat">
      <div class="num"><?= $stats['pendingEvaluations'] ?></div>
      <div class="lbl">Pending Evaluations</div>
    </div>
  </div>
</section>

<!-- Profile Completion -->
<section>
  <h2 class="section-title">Profile Completion</h2>
  <div class="card">
    <h3>Profile Completion: <?= $profileCompletion ?>%</h3>
    <div class="completion-bar"><div class="completion-fill" style="width: <?= $profileCompletion ?>%;"></div></div>
    <a class="btn btn-sm" href="<?= e(url('action=profile')) ?>">Complete your profile</a>
  </div>
</section>

<!-- Draft Proposals -->
<section>
  <h2 class="section-title">Draft Proposals</h2>
  <?php if ($draftProposals): ?>
  <table class="tbl">
    <thead>
      <tr>
        <th>Title</th>
        <th>Call</th>
        <th>Created</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($draftProposals as $app): ?>
      <tr>
        <td><?= e($app['title']) ?></td>
        <td><?= e($app['call_title'] ?? '—') ?></td>
        <td><?= e(format_date($app['created_at'])) ?></td>
        <td>
          <a class="btn-sm" href="<?= e(url('action=application_form&id=' . $app['id'])) ?>">Edit</a>
          <a class="btn-sm" href="<?= e(url('action=application_submit&id=' . $app['id'])) ?>">Submit</a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?>
  <p class="muted">No draft proposals.</p>
  <?php endif; ?>
</section>

<!-- Submitted Proposals -->
<section>
  <h2 class="section-title">Submitted Proposals</h2>
  <?php if ($submittedProposals): ?>
  <table class="tbl">
    <thead>
      <tr>
        <th>Application Number</th>
        <th>Title</th>
        <th>Call</th>
        <th>Status</th>
        <th>Submitted</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($submittedProposals as $app): ?>
      <tr>
        <td><?= e($app['application_number']) ?></td>
        <td><?= e($app['title']) ?></td>
        <td><?= e($app['call_title'] ?? '—') ?></td>
        <td><span class="badge <?= status_badge($app['status_code']) ?>"><?= e($app['status_code']) ?></span></td>
        <td><?= e(format_date($app['submitted_at'])) ?></td>
        <td>
          <a class="btn-sm" href="<?= e(url('action=application_view&id=' . $app['id'])) ?>">View</a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?>
  <p class="muted">No submitted proposals.</p>
  <?php endif; ?>
</section>

<!-- Under Revision -->
<?php if ($underRevision): ?>
<section>
  <h2 class="section-title">Proposals Under Revision</h2>
  <table class="tbl">
    <thead>
      <tr>
        <th>Application Number</th>
        <th>Title</th>
        <th>Revision Reason</th>
        <th>Requested</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($underRevision as $app): ?>
      <tr class="warning">
        <td><?= e($app['application_number']) ?></td>
        <td><?= e($app['title']) ?></td>
        <td><?= e($app['request_reason']) ?></td>
        <td><?= e(format_date($app['requested_at'])) ?></td>
        <td>
          <a class="btn-sm" href="<?= e(url('action=application_view&id=' . $app['id'])) ?>">Revise</a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php endif; ?>

<!-- Active Projects -->
<section>
  <h2 class="section-title">Active Projects</h2>
  <?php if ($activeProjects): ?>
  <table class="tbl">
    <thead>
      <tr>
        <th>Project Number</th>
        <th>Title</th>
        <th>Started</th>
        <th>Planned End</th>
        <th>Pending Reports</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($activeProjects as $proj): ?>
      <tr>
        <td><?= e($proj['project_number']) ?></td>
        <td><?= e($proj['title']) ?></td>
        <td><?= e(format_date($proj['actual_start'])) ?></td>
        <td><?= e(format_date($proj['planned_end'])) ?></td>
        <td><?= (int)$proj['pending_reports'] ?></td>
        <td>
          <a class="btn-sm" href="<?= e(url('action=project_view&id=' . $proj['id'])) ?>">View</a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?>
  <p class="muted">No active projects.</p>
  <?php endif; ?>
</section>

<!-- Pending Agreements -->
<?php if ($pendingAgreements): ?>
<section>
  <h2 class="section-title">Pending Agreements</h2>
  <table class="tbl">
    <thead>
      <tr>
        <th>Application Number</th>
        <th>Title</th>
        <th>Version</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($pendingAgreements as $ag): ?>
      <tr>
        <td><?= e($ag['application_number']) ?></td>
        <td><?= e($ag['title']) ?></td>
        <td><?= e($ag['version']) ?></td>
        <td><span class="badge <?= status_badge($ag['status']) ?>"><?= e($ag['status']) ?></span></td>
        <td>
          <a class="btn-sm" href="<?= e(url('action=application_view&id=' . $ag['id'])) ?>">Sign</a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php endif; ?>

<!-- Pending Progress Reports -->
<?php if ($pendingReports): ?>
<section>
  <h2 class="section-title">Progress Reports Due Soon</h2>
  <table class="tbl">
    <thead>
      <tr>
        <th>Report Number</th>
        <th>Project Number</th>
        <th>Title</th>
        <th>Due Date</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($pendingReports as $report): ?>
      <tr>
        <td><?= e($report['report_number']) ?></td>
        <td><?= e($report['project_number']) ?></td>
        <td><?= e($report['title']) ?></td>
        <td><?= e(format_date($report['period_end'])) ?></td>
        <td><span class="badge <?= status_badge($report['status']) ?>"><?= e($report['status']) ?></span></td>
        <td>
          <a class="btn-sm" href="<?= e(url('action=project_view&id=' . $report['id'])) ?>">Submit</a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php endif; ?>

<!-- Overdue Reports -->
<?php if ($overdueReports): ?>
<section>
  <h2 class="section-title">Overdue Reports</h2>
  <table class="tbl">
    <thead>
      <tr>
        <th>Report Number</th>
        <th>Project Number</th>
        <th>Title</th>
        <th>Due Date</th>
        <th>Days Overdue</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($overdueReports as $report): ?>
      <tr class="danger">
        <td><?= e($report['report_number']) ?></td>
        <td><?= e($report['project_number']) ?></td>
        <td><?= e($report['title']) ?></td>
        <td><?= e(format_date($report['period_end'])) ?></td>
        <td><?= (int)$report['days_overdue'] ?></td>
        <td>
          <a class="btn-sm" href="<?= e(url('action=project_view&id=' . $report['id'])) ?>">Submit</a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php endif; ?>

<!-- Pending Evaluations -->
<?php if ($pendingEvaluations): ?>
<section>
  <h2 class="section-title">Pending Evaluations</h2>
  <table class="tbl">
    <thead>
      <tr>
        <th>Application Number</th>
        <th>Title</th>
        <th>PI</th>
        <th>Deadline</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($pendingEvaluations as $eval): ?>
      <tr>
        <td><?= e($eval['application_number']) ?></td>
        <td><?= e($eval['title']) ?></td>
        <td><?= e($eval['pi_name']) ?></td>
        <td><?= e(format_date($eval['deadline'])) ?></td>
        <td>
          <a class="btn-sm" href="<?= e(url('action=evaluation_form&id=' . $eval['id'])) ?>">Evaluate</a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php endif; ?>

<!-- Open Calls -->
<section>
  <h2 class="section-title">Open Research Calls</h2>
  <?php if ($openCalls): ?>
  <table class="tbl">
    <thead>
      <tr>
        <th>Call Number</th>
        <th>Title</th>
        <th>Closing Date</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($openCalls as $call): ?>
      <tr>
        <td><?= e($call['call_number']) ?></td>
        <td><?= e($call['title']) ?></td>
        <td><?= e(format_datetime($call['closes_at'])) ?></td>
        <td>
          <a class="btn-sm" href="<?= e(url('action=call_view&id=' . $call['id'])) ?>">View</a>
          <a class="btn-sm" href="<?= e(url('action=application_form&call_id=' . $call['id'])) ?>">Apply</a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?>
  <p class="muted">No open calls at this time.</p>
  <?php endif; ?>
</section>

<!-- Notifications -->
<?php if ($notifications): ?>
<section>
  <h2 class="section-title">Recent Notifications</h2>
  <table class="tbl">
    <thead>
      <tr>
        <th>Type</th>
        <th>Title</th>
        <th>Message</th>
        <th>Date</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($notifications as $notif): ?>
      <tr>
        <td><?= e(ucfirst(str_replace('_', ' ', $notif['notification_type']))) ?></td>
        <td><?= e($notif['title']) ?></td>
        <td><?= e($notif['body'] ?? '—') ?></td>
        <td><?= e(format_datetime($notif['created_at'])) ?></td>
        <td>
          <?php if ($notif['link_url']): ?>
          <a class="btn-sm" href="<?= e($notif['link_url']) ?>">View</a>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <a class="btn btn-sm" href="<?= e(url('action=notifications')) ?>">View All Notifications</a>
</section>
<?php endif; ?>

<?php include APP_PATH . '/views/partials/footer.php'; ?>
