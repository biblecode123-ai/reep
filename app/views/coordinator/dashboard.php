<?php
$title = 'College Coordinator Dashboard';
include APP_PATH . '/views/partials/header.php';
?>

<div class="page-head">
  <div><h1>College Coordinator Dashboard</h1></div>
</div>

<!-- Summary Cards -->
<section>
  <h2 class="section-title">Summary</h2>
  <div class="grid grid-4">
    <div class="stat">
      <div class="num"><?= $stats['newSubmissions'] ?></div>
      <div class="lbl">New Submissions</div>
    </div>
    <div class="stat">
      <div class="num"><?= $stats['underEvaluation'] ?></div>
      <div class="lbl">Under Evaluation</div>
    </div>
    <div class="stat">
      <div class="num"><?= $stats['awaitingDecision'] ?></div>
      <div class="lbl">Awaiting Decision</div>
    </div>
    <div class="stat">
      <div class="num"><?= $stats['forwardedToUniversity'] ?></div>
      <div class="lbl">Forwarded to University</div>
    </div>
    <div class="stat">
      <div class="num"><?= $stats['collegeRejected'] ?></div>
      <div class="lbl">College Rejected</div>
    </div>
    <div class="stat">
      <div class="num"><?= $stats['activeProjects'] ?></div>
      <div class="lbl">Active Projects</div>
    </div>
    <div class="stat">
      <div class="num"><?= $stats['reportsSubmitted'] ?></div>
      <div class="lbl">Reports Submitted</div>
    </div>
    <div class="stat">
      <div class="num danger"><?= $stats['reportsOverdue'] ?></div>
      <div class="lbl">Reports Overdue</div>
    </div>
  </div>
</section>

<!-- Priority Action List -->
<section>
  <h2 class="section-title">Priority Actions</h2>

  <?php if ($priorityActions['screeningNeeded']): ?>
  <div class="card" style="margin-bottom: 20px;">
    <h3 style="color: var(--danger);">Needs Screening (<?= count($priorityActions['screeningNeeded']) ?>)</h3>
    <table class="tbl">
      <thead>
        <tr>
          <th>Application Number</th>
          <th>Title</th>
          <th>PI</th>
          <th>Status</th>
          <th>Submitted</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($priorityActions['screeningNeeded'] as $app): ?>
        <tr>
          <td><?= e($app['application_number']) ?></td>
          <td><?= e($app['title']) ?></td>
          <td><?= e($app['pi_name']) ?></td>
          <td><span class="badge <?= status_badge($app['status_code']) ?>"><?= e($app['status_code']) ?></span></td>
          <td><?= e(format_date($app['submitted_at'])) ?></td>
          <td>
            <a class="btn-sm" href="<?= e(url('action=application_view&id=' . $app['id'])) ?>">Screen</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <?php if ($priorityActions['needsEvaluators']): ?>
  <div class="card" style="margin-bottom: 20px;">
    <h3 style="color: var(--warning);">Needs Evaluator Assignment (<?= count($priorityActions['needsEvaluators']) ?>)</h3>
    <table class="tbl">
      <thead>
        <tr>
          <th>Application Number</th>
          <th>Title</th>
          <th>Status</th>
          <th>Evaluators Assigned</th>
          <th>Required</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($priorityActions['needsEvaluators'] as $app): ?>
        <tr>
          <td><?= e($app['application_number']) ?></td>
          <td><?= e($app['title']) ?></td>
          <td><span class="badge <?= status_badge($app['status_code']) ?>"><?= e($app['status_code']) ?></span></td>
          <td><?= (int)$app['assigned_count'] ?></td>
          <td><?= (int)($app['required_evaluators'] ?? 2) ?></td>
          <td>
            <a class="btn-sm" href="<?= e(url('action=assign_evaluators&id=' . $app['id'])) ?>">Assign</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <?php if ($priorityActions['overdueEvaluations']): ?>
  <div class="card" style="margin-bottom: 20px; border: 2px solid var(--danger);">
    <h3 style="color: var(--danger);">Overdue Evaluations (<?= count($priorityActions['overdueEvaluations']) ?>)</h3>
    <table class="tbl">
      <thead>
        <tr>
          <th>Application Number</th>
          <th>Title</th>
          <th>Evaluator</th>
          <th>Deadline</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($priorityActions['overdueEvaluations'] as $ea): ?>
        <tr class="danger">
          <td><?= e($ea['application_number']) ?></td>
          <td><?= e($ea['title']) ?></td>
          <td><?= e($ea['evaluator_name']) ?></td>
          <td style="color: var(--danger);"><?= e(format_date($ea['deadline'])) ?></td>
          <td><span class="badge"><?= e($ea['status']) ?></span></td>
          <td>
            <a class="btn-sm" href="<?= e(url('action=application_view&id=' . $ea['id'])) ?>">Manage</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <?php if ($priorityActions['approachingDeadlines']): ?>
  <div class="card" style="margin-bottom: 20px;">
    <h3 style="color: var(--warning);">Evaluations Approaching Deadline (<?= count($priorityActions['approachingDeadlines']) ?>)</h3>
    <table class="tbl">
      <thead>
        <tr>
          <th>Application Number</th>
          <th>Title</th>
          <th>Evaluator</th>
          <th>Deadline</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($priorityActions['approachingDeadlines'] as $ea): ?>
        <tr>
          <td><?= e($ea['application_number']) ?></td>
          <td><?= e($ea['title']) ?></td>
          <td><?= e($ea['evaluator_name']) ?></td>
          <td style="color: var(--warning);"><?= e(format_date($ea['deadline'])) ?></td>
          <td><span class="badge"><?= e($ea['status']) ?></span></td>
          <td>
            <a class="btn-sm" href="<?= e(url('action=application_view&id=' . $ea['id'])) ?>">Manage</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <?php if ($priorityActions['awaitingReview']): ?>
  <div class="card" style="margin-bottom: 20px;">
    <h3>Awaiting College Decision (<?= count($priorityActions['awaitingReview']) ?>)</h3>
    <table class="tbl">
      <thead>
        <tr>
          <th>Application Number</th>
          <th>Title</th>
          <th>Status</th>
          <th>Evaluations Complete</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($priorityActions['awaitingReview'] as $app): ?>
        <tr>
          <td><?= e($app['application_number']) ?></td>
          <td><?= e($app['title']) ?></td>
          <td><span class="badge <?= status_badge($app['status_code']) ?>"><?= e($app['status_code']) ?></span></td>
          <td><?= (int)$app['completed_count'] ?> / 2 minimum</td>
          <td>
            <a class="btn-sm" href="<?= e(url('action=application_view&id=' . $app['id'])) ?>">Decide</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <?php if ($priorityActions['readyToForward']): ?>
  <div class="card" style="margin-bottom: 20px;">
    <h3 style="color: var(--brand);">Ready to Forward to University (<?= count($priorityActions['readyToForward']) ?>)</h3>
    <table class="tbl">
      <thead>
        <tr>
          <th>Application Number</th>
          <th>Title</th>
          <th>Status</th>
          <th>Evaluations Complete</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($priorityActions['readyToForward'] as $app): ?>
        <tr>
          <td><?= e($app['application_number']) ?></td>
          <td><?= e($app['title']) ?></td>
          <td><span class="badge <?= status_badge($app['status_code']) ?>"><?= e($app['status_code']) ?></span></td>
          <td><?= (int)$app['completed_count'] ?> / 2 minimum</td>
          <td>
            <a class="btn-sm" href="<?= e(url('action=application_view&id=' . $app['id'])) ?>">Forward</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <?php if (!$priorityActions['screeningNeeded'] && !$priorityActions['needsEvaluators'] && !$priorityActions['overdueEvaluations'] && !$priorityActions['approachingDeadlines'] && !$priorityActions['awaitingReview'] && !$priorityActions['readyToForward']): ?>
  <div class="card">
    <p class="muted">No priority actions at this time. All tasks are up to date.</p>
  </div>
  <?php endif; ?>
</section>

<!-- New Submissions -->
<section>
  <h2 class="section-title">New Submissions</h2>
  <table class="tbl">
    <thead>
      <tr>
        <th>Application Number</th>
        <th>Title</th>
        <th>PI</th>
        <th>Status</th>
        <th>Submitted</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($newSubmissions): ?>
      <?php foreach ($newSubmissions as $app): ?>
      <tr>
        <td><?= e($app['application_number']) ?></td>
        <td><?= e($app['title']) ?></td>
        <td><?= e($app['pi_name']) ?></td>
        <td><span class="badge <?= status_badge($app['status_code']) ?>"><?= e($app['status_code']) ?></span></td>
        <td><?= e(format_date($app['submitted_at'])) ?></td>
        <td>
          <a class="btn-sm" href="<?= e(url('action=application_view&id=' . $app['id'])) ?>">Screen</a>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php else: ?>
      <tr>
        <td colspan="6" class="text-center muted">No new submissions.</td>
      </tr>
      <?php endif; ?>
    </tbody>
  </table>
</section>

<!-- Under Evaluation -->
<section>
  <h2 class="section-title">Under Evaluation</h2>
  <table class="tbl">
    <thead>
      <tr>
        <th>Application Number</th>
        <th>Title</th>
        <th>PI</th>
        <th>Pending Evaluators</th>
        <th>Completed Evaluators</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($underEvaluation): ?>
      <?php foreach ($underEvaluation as $app): ?>
      <tr>
        <td><?= e($app['application_number']) ?></td>
        <td><?= e($app['title']) ?></td>
        <td><?= e($app['pi_name']) ?></td>
        <td><?= (int)$app['pending_evaluators'] ?></td>
        <td><?= (int)$app['completed_evaluators'] ?></td>
        <td>
          <a class="btn-sm" href="<?= e(url('action=application_view&id=' . $app['id'])) ?>">View</a>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php else: ?>
      <tr>
        <td colspan="6" class="text-center muted">No proposals under evaluation.</td>
      </tr>
      <?php endif; ?>
    </tbody>
  </table>
</section>

<!-- Awaiting Decision -->
<section>
  <h2 class="section-title">Awaiting College Decision</h2>
  <table class="tbl">
    <thead>
      <tr>
        <th>Application Number</th>
        <th>Title</th>
        <th>PI</th>
        <th>Average Score</th>
        <th>Evaluation Count</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($awaitingDecision): ?>
      <?php foreach ($awaitingDecision as $app): ?>
      <tr>
        <td><?= e($app['application_number']) ?></td>
        <td><?= e($app['title']) ?></td>
        <td><?= e($app['pi_name']) ?></td>
        <td><?= number_format((float)$app['avg_score'], 1) ?></td>
        <td><?= (int)$app['eval_count'] ?></td>
        <td>
          <a class="btn-sm" href="<?= e(url('action=application_view&id=' . $app['id'])) ?>">Decide</a>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php else: ?>
      <tr>
        <td colspan="6" class="text-center muted">No proposals awaiting decision.</td>
      </tr>
      <?php endif; ?>
    </tbody>
  </table>
</section>

<!-- Active Projects -->
<section>
  <h2 class="section-title">Active Projects</h2>
  <table class="tbl">
    <thead>
      <tr>
        <th>Project Number</th>
        <th>Title</th>
        <th>PI</th>
        <th>Started</th>
        <th>Pending Reports</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($activeProjects): ?>
      <?php foreach ($activeProjects as $proj): ?>
      <tr>
        <td><?= e($proj['project_number']) ?></td>
        <td><?= e($proj['title']) ?></td>
        <td><?= e($proj['pi_name']) ?></td>
        <td><?= e(format_date($proj['actual_start'])) ?></td>
        <td><?= (int)$proj['pending_reports'] ?></td>
        <td>
          <a class="btn-sm" href="<?= e(url('action=project_view&id=' . $proj['id'])) ?>">View</a>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php else: ?>
      <tr>
        <td colspan="6" class="text-center muted">No active projects.</td>
      </tr>
      <?php endif; ?>
    </tbody>
  </table>
</section>

<!-- Progress Reports Awaiting Review -->
<section>
  <h2 class="section-title">Progress Reports Awaiting Review</h2>
  <table class="tbl">
    <thead>
      <tr>
        <th>Report Number</th>
        <th>Project Number</th>
        <th>Title</th>
        <th>PI</th>
        <th>Period End</th>
        <th>Submitted</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($pendingReports): ?>
      <?php foreach ($pendingReports as $report): ?>
      <tr>
        <td><?= e($report['report_number']) ?></td>
        <td><?= e($report['project_number']) ?></td>
        <td><?= e($report['title']) ?></td>
        <td><?= e($report['pi_name']) ?></td>
        <td><?= e(format_date($report['period_end'])) ?></td>
        <td><?= e(format_date($report['submitted_at'])) ?></td>
        <td>
          <a class="btn-sm" href="<?= e(url('action=project_view&id=' . $report['id'])) ?>">Review</a>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php else: ?>
      <tr>
        <td colspan="7" class="text-center muted">No reports awaiting review.</td>
      </tr>
      <?php endif; ?>
    </tbody>
  </table>
</section>

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
        <th>PI</th>
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
        <td><?= e($report['pi_name']) ?></td>
        <td><?= e(format_date($report['period_end'])) ?></td>
        <td><?= (int)$report['days_overdue'] ?></td>
        <td>
          <a class="btn-sm" href="<?= e(url('action=project_view&id=' . $report['id'])) ?>">Follow Up</a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php endif; ?>

<!-- College Evaluators -->
<section>
  <h2 class="section-title">College Evaluators</h2>
  <table class="tbl">
    <thead>
      <tr>
        <th>Name</th>
        <th>Department</th>
        <th>Active Assignments</th>
        <th>Availability</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($collegeEvaluators): ?>
      <?php foreach ($collegeEvaluators as $eval): ?>
      <tr>
        <td><?= e($eval['full_name']) ?></td>
        <td><?= e($eval['department_name'] ?? '—') ?></td>
        <td><?= (int)$eval['active_assignments'] ?></td>
        <td><span class="badge <?= $eval['availability_status'] === 'available' ? 'badge-success' : 'badge-secondary' ?>"><?= e(ucfirst($eval['availability_status'])) ?></span></td>
        <td>
          <a class="btn-sm" href="<?= e(url('action=screening_assign&evaluator_id=' . $eval['id'])) ?>">Assign</a>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php else: ?>
      <tr>
        <td colspan="5" class="text-center muted">No evaluators registered for this college.</td>
      </tr>
      <?php endif; ?>
    </tbody>
  </table>
</section>

<?php include APP_PATH . '/views/partials/footer.php'; ?>
