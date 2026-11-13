<?php
$title = 'Researcher Dashboard';
include APP_PATH . '/views/partials/header.php';

$draftCount = (int) ($stats['draftProposals'] ?? 0);
$submittedCount = (int) ($stats['submittedProposals'] ?? 0);
$projectCount = (int) ($stats['activeProjects'] ?? 0);
$evaluationCount = (int) ($stats['pendingEvaluations'] ?? 0);
$profilePercent = max(0, min(100, (int) ($profileCompletion ?? 0)));
?>

<div class="researcher-dashboard">
  <section class="rd-welcome">
    <div class="rd-welcome-copy">
      <span class="rd-eyebrow"><span class="rd-eyebrow-dot"></span> RESEARCHER WORKSPACE</span>
      <h1>Welcome to your research workspace</h1>
      <p>Track your proposals, respond to evaluation assignments, and manage your research activities in one place.</p>
    </div>
    <div class="rd-welcome-actions">
      <a class="btn" href="<?= e(url('action=calls_public')) ?>">Explore Research Calls <span aria-hidden="true">→</span></a>
      <a class="btn btn-outline" href="<?= e(url('action=applications')) ?>">My Proposals</a>
    </div>
  </section>

  <section class="rd-stat-grid" aria-label="Research activity summary">
    <a class="rd-stat rd-stat-purple" href="<?= e(url('action=applications')) ?>">
      <span class="rd-stat-icon" aria-hidden="true">▤</span>
      <span class="rd-stat-value"><?= $draftCount ?></span>
      <span class="rd-stat-label">Draft proposals</span>
      <span class="rd-stat-foot">Continue your drafts <span aria-hidden="true">↗</span></span>
    </a>
    <a class="rd-stat rd-stat-blue" href="<?= e(url('action=applications')) ?>">
      <span class="rd-stat-icon" aria-hidden="true">↗</span>
      <span class="rd-stat-value"><?= $submittedCount ?></span>
      <span class="rd-stat-label">Submitted proposals</span>
      <span class="rd-stat-foot">Track proposal status <span aria-hidden="true">↗</span></span>
    </a>
    <a class="rd-stat rd-stat-green" href="<?= e(url('action=projects')) ?>">
      <span class="rd-stat-icon" aria-hidden="true">⌂</span>
      <span class="rd-stat-value"><?= $projectCount ?></span>
      <span class="rd-stat-label">Active projects</span>
      <span class="rd-stat-foot">View project activity <span aria-hidden="true">↗</span></span>
    </a>
    <a class="rd-stat rd-stat-amber" href="<?= e(url('action=my_evaluations')) ?>">
      <span class="rd-stat-icon" aria-hidden="true">✓</span>
      <span class="rd-stat-value"><?= $evaluationCount ?></span>
      <span class="rd-stat-label">Pending evaluations</span>
      <span class="rd-stat-foot">Assignments needing action <span aria-hidden="true">↗</span></span>
    </a>
  </section>

  <div class="rd-main-grid">
    <div class="rd-primary-column">
      <section class="rd-panel">
        <div class="rd-panel-heading">
          <div>
            <span class="rd-kicker">YOUR SUBMISSIONS</span>
            <h2>My proposals</h2>
            <p>Follow the progress of your research applications.</p>
          </div>
          <a class="rd-text-link" href="<?= e(url('action=applications')) ?>">View all proposals <span aria-hidden="true">→</span></a>
        </div>

        <?php if ($underRevision): ?>
          <div class="rd-attention">
            <span class="rd-attention-icon" aria-hidden="true">!</span>
            <div><strong><?= count($underRevision) ?> proposal<?= count($underRevision) === 1 ? '' : 's' ?> need revision</strong><span>Review the feedback and submit the requested changes.</span></div>
            <a class="btn btn-sm" href="<?= e(url('action=application_view&id=' . $underRevision[0]['id'])) ?>">Review request</a>
          </div>
        <?php endif; ?>

        <?php if ($submittedProposals || $draftProposals): ?>
          <div class="rd-table-wrap">
            <table class="tbl rd-table">
              <thead><tr><th>Proposal</th><th>Research call</th><th>Status</th><th>Last activity</th><th></th></tr></thead>
              <tbody>
                <?php foreach ($submittedProposals as $app): ?>
                  <tr>
                    <td><strong><?= e($app['title']) ?></strong><small><?= e($app['application_number'] ?? 'Submitted proposal') ?></small></td>
                    <td><?= e($app['call_title'] ?? '—') ?></td>
                    <td><span class="badge <?= e(status_badge($app['status_code'])) ?>"><?= e(ucwords(strtolower(str_replace('_', ' ', $app['status_code'])))) ?></span></td>
                    <td><?= e(format_date($app['submitted_at'])) ?></td>
                    <td><a class="rd-row-action" href="<?= e(url('action=application_view&id=' . $app['id'])) ?>">View <span aria-hidden="true">→</span></a></td>
                  </tr>
                <?php endforeach; ?>
                <?php foreach ($draftProposals as $app): ?>
                  <tr>
                    <td><strong><?= e($app['title']) ?></strong><small>Draft proposal</small></td>
                    <td><?= e($app['call_title'] ?? '—') ?></td>
                    <td><span class="badge badge-secondary">Draft</span></td>
                    <td><?= e(format_date($app['created_at'])) ?></td>
                    <td><a class="rd-row-action" href="<?= e(url('action=application_form&id=' . $app['id'])) ?>">Continue <span aria-hidden="true">→</span></a></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="rd-empty">
            <span class="rd-empty-icon" aria-hidden="true">▤</span>
            <strong>No proposals yet</strong>
            <p>Explore an open research call and start preparing your proposal.</p>
            <a class="btn btn-sm" href="<?= e(url('action=calls_public')) ?>">Browse research calls</a>
          </div>
        <?php endif; ?>
      </section>

      <?php if ($pendingEvaluations): ?>
      <section class="rd-panel">
        <div class="rd-panel-heading">
          <div><span class="rd-kicker">YOUR REVIEW RESPONSIBILITIES</span><h2>Evaluation assignments</h2><p>Research proposals assigned to you for assessment.</p></div>
          <a class="rd-text-link" href="<?= e(url('action=my_evaluations')) ?>">View all <span aria-hidden="true">→</span></a>
        </div>
        <div class="rd-task-list">
          <?php foreach ($pendingEvaluations as $eval): ?>
            <div class="rd-task">
              <span class="rd-task-icon" aria-hidden="true">✓</span>
              <div class="rd-task-copy"><strong><?= e($eval['title']) ?></strong><span><?= e($eval['application_number']) ?> · Principal researcher: <?= e($eval['pi_name']) ?></span></div>
              <div class="rd-task-meta"><span class="badge badge-warning">Awaiting evaluation</span><small>Deadline: <?= e(format_date($eval['deadline'])) ?></small></div>
              <a class="btn btn-sm" href="<?= e(url('action=evaluation_form&id=' . $eval['id'])) ?>">Evaluate</a>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>

      <section class="rd-panel">
        <div class="rd-panel-heading">
          <div><span class="rd-kicker">FUNDed RESEARCH</span><h2>Active projects</h2><p>Monitor projects where you are an active team member.</p></div>
          <a class="rd-text-link" href="<?= e(url('action=projects')) ?>">View projects <span aria-hidden="true">→</span></a>
        </div>
        <?php if ($activeProjects): ?>
          <div class="rd-table-wrap"><table class="tbl rd-table"><thead><tr><th>Project</th><th>Start date</th><th>Planned end</th><th>Reports due</th><th></th></tr></thead><tbody>
          <?php foreach ($activeProjects as $proj): ?>
            <tr><td><strong><?= e($proj['title']) ?></strong><small><?= e($proj['project_number']) ?></small></td><td><?= e(format_date($proj['actual_start'])) ?></td><td><?= e(format_date($proj['planned_end'])) ?></td><td><?= (int) $proj['pending_reports'] ?></td><td><a class="rd-row-action" href="<?= e(url('action=project_view&id=' . $proj['id'])) ?>">Open <span aria-hidden="true">→</span></a></td></tr>
          <?php endforeach; ?>
          </tbody></table></div>
        <?php else: ?>
          <div class="rd-inline-empty">You are not currently listed on an active research project.</div>
        <?php endif; ?>
      </section>

      <?php if ($pendingAgreements || $pendingReports || $overdueReports): ?>
      <section class="rd-panel">
        <div class="rd-panel-heading"><div><span class="rd-kicker">FOLLOW-UP REQUIRED</span><h2>Research administration</h2><p>Agreements and reporting items that may need your attention.</p></div></div>
        <?php if ($pendingAgreements): ?><div class="rd-simple-list"><?php foreach ($pendingAgreements as $ag): ?><div><span class="rd-list-dot"></span><div><strong><?= e($ag['title']) ?></strong><small>Agreement v<?= e((string) $ag['version']) ?> · <?= e(ucfirst($ag['status'])) ?></small></div><a class="rd-row-action" href="<?= e(url('action=application_view&id=' . $ag['application_id'])) ?>">Open</a></div><?php endforeach; ?></div><?php endif; ?>
        <?php if ($pendingReports): ?><div class="rd-simple-list"><?php foreach ($pendingReports as $report): ?><div><span class="rd-list-dot rd-dot-blue"></span><div><strong><?= e($report['title']) ?></strong><small><?= e($report['report_number']) ?> · Due <?= e(format_date($report['period_end'])) ?></small></div><a class="rd-row-action" href="<?= e(url('action=project_view&id=' . $report['project_id'])) ?>">Open</a></div><?php endforeach; ?></div><?php endif; ?>
        <?php if ($overdueReports): ?><div class="rd-simple-list"><?php foreach ($overdueReports as $report): ?><div><span class="rd-list-dot rd-dot-red"></span><div><strong><?= e($report['title']) ?></strong><small><?= e($report['report_number']) ?> · <?= (int) $report['days_overdue'] ?> days overdue</small></div><a class="rd-row-action" href="<?= e(url('action=project_view&id=' . $report['id'])) ?>">Open</a></div><?php endforeach; ?></div><?php endif; ?>
      </section>
      <?php endif; ?>
    </div>

    <aside class="rd-side-column">
      <section class="rd-panel rd-profile-panel">
        <div class="rd-panel-heading rd-panel-heading-tight"><div><span class="rd-kicker">YOUR PROFILE</span><h2>Profile strength</h2></div><span class="rd-percent"><?= $profilePercent ?>%</span></div>
        <div class="rd-progress-track"><span style="width: <?= $profilePercent ?>%"></span></div>
        <p class="rd-muted-copy"><?= $profilePercent >= 100 ? 'Your researcher profile is complete.' : 'Complete your academic and research information to strengthen your researcher profile.' ?></p>
        <a class="btn btn-outline btn-block" href="<?= e(url('action=profile')) ?>">Update my profile</a>
      </section>

      <section class="rd-panel">
        <div class="rd-panel-heading"><div><span class="rd-kicker">OPPORTUNITIES</span><h2>Open research calls</h2><p>Find opportunities that match your research interests.</p></div></div>
        <?php if ($openCalls): ?>
          <div class="rd-call-list">
            <?php foreach (array_slice($openCalls, 0, 5) as $call): ?>
              <article class="rd-call">
                <span class="rd-call-mark" aria-hidden="true">✦</span>
                <div class="rd-call-copy"><strong><?= e($call['title']) ?></strong><small><?= e($call['call_number']) ?></small><small>Closes <?= e(format_datetime($call['closes_at'])) ?></small></div>
                <a class="rd-icon-link" aria-label="View research call" href="<?= e(url('action=call_view&id=' . $call['id'])) ?>">↗</a>
              </article>
            <?php endforeach; ?>
          </div>
          <a class="rd-panel-footer-link" href="<?= e(url('action=calls_public')) ?>">Browse all research calls <span aria-hidden="true">→</span></a>
        <?php else: ?>
          <div class="rd-inline-empty">There are no open research calls at the moment.</div>
        <?php endif; ?>
      </section>

      <?php if ($notifications): ?>
      <section class="rd-panel">
        <div class="rd-panel-heading"><div><span class="rd-kicker">LATEST UPDATES</span><h2>Recent notifications</h2></div><span class="rd-notification-count"><?= (int) $unread ?> unread</span></div>
        <div class="rd-notification-list">
          <?php foreach (array_slice($notifications, 0, 5) as $notif): ?>
            <a href="<?= e($notif['link_url'] ?: url('action=notifications')) ?>" class="rd-notification">
              <span class="rd-notification-dot"></span><span><strong><?= e($notif['title']) ?></strong><small><?= e($notif['body'] ?? '') ?></small><small><?= e(format_datetime($notif['created_at'])) ?></small></span>
            </a>
          <?php endforeach; ?>
        </div>
        <a class="rd-panel-footer-link" href="<?= e(url('action=notifications')) ?>">View notifications <span aria-hidden="true">→</span></a>
      </section>
      <?php else: ?>
      <section class="rd-panel rd-notice-empty"><span class="rd-notice-icon" aria-hidden="true">♧</span><strong>You're all caught up</strong><p>No unread notifications right now.</p><a class="rd-panel-footer-link" href="<?= e(url('action=notifications')) ?>">Open notification center <span aria-hidden="true">→</span></a></section>
      <?php endif; ?>
    </aside>
  </div>
</div>

<?php include APP_PATH . '/views/partials/footer.php'; ?>
