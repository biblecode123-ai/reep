<?php
$title = 'Researcher Dashboard';
include APP_PATH . '/views/partials/header.php';

$draftCount = (int) ($stats['draftProposals'] ?? 0);
$totalProposalCount = (int) ($stats['totalProposals'] ?? ($draftCount + (int) ($stats['submittedProposals'] ?? 0)));
$approvedCount = (int) ($stats['approvedProposals'] ?? 0);
$revisionCount = (int) ($stats['underRevision'] ?? 0);
$evaluationCount = (int) ($stats['pendingEvaluations'] ?? 0);
$projectCount = (int) ($stats['activeProjects'] ?? 0);
$profilePercent = max(0, min(100, (int) ($profileCompletion ?? 0)));
$researcherName = trim((string) ($user['full_name'] ?? 'Researcher'));
$firstName = preg_split('/\s+/', $researcherName)[0] ?? 'Researcher';
?>

<div class="researcher-dashboard">
  <section class="rd-welcome">
    <div class="rd-welcome-copy">
      <span class="rd-eyebrow"><span class="rd-eyebrow-dot"></span> MWU REEP · RESEARCHER PORTAL</span>
      <h1>Welcome back, <?= e($firstName) ?>!</h1>
      <p>Manage your research proposals, discover funding opportunities, follow your projects, and complete evaluation assignments from one workspace.</p>
    </div>
    <div class="rd-welcome-actions">
      <a class="btn" href="<?= e(url('action=calls_public')) ?>">Explore Research Calls <span aria-hidden="true">→</span></a>
      <a class="btn btn-outline" href="<?= e(url('action=applications')) ?>">My Proposals</a>
    </div>
    <span class="rd-welcome-orb" aria-hidden="true"></span>
  </section>

  <section class="rd-stat-grid" aria-label="Your research summary">
    <a class="rd-stat rd-stat-purple" href="<?= e(url('action=applications')) ?>">
      <span class="rd-stat-icon" aria-hidden="true">▤</span>
      <span class="rd-stat-value"><?= $totalProposalCount ?></span>
      <span class="rd-stat-label">My Proposals</span>
      <span class="rd-stat-foot"><?= $draftCount ?> draft<?= $draftCount === 1 ? '' : 's' ?> · <?= (int) ($stats['submittedProposals'] ?? 0) ?> submitted</span>
    </a>
    <a class="rd-stat rd-stat-blue" href="<?= e(url('action=my_evaluations')) ?>">
      <span class="rd-stat-icon" aria-hidden="true">✓</span>
      <span class="rd-stat-value"><?= $evaluationCount ?></span>
      <span class="rd-stat-label">Evaluation Assignments</span>
      <span class="rd-stat-foot">Assignments awaiting your action</span>
    </a>
    <a class="rd-stat rd-stat-green" href="<?= e(url('action=applications')) ?>">
      <span class="rd-stat-icon" aria-hidden="true">★</span>
      <span class="rd-stat-value"><?= $approvedCount ?></span>
      <span class="rd-stat-label">Approved Proposals</span>
      <span class="rd-stat-foot">Proposals approved or funded</span>
    </a>
    <a class="rd-stat rd-stat-amber" href="<?= e(url('action=applications')) ?>">
      <span class="rd-stat-icon" aria-hidden="true">↻</span>
      <span class="rd-stat-value"><?= $revisionCount ?></span>
      <span class="rd-stat-label">Revision Requests</span>
      <span class="rd-stat-foot">Proposals requiring your response</span>
    </a>
  </section>

  <div class="rd-main-grid">
    <div class="rd-primary-column">
      <section class="rd-panel">
        <div class="rd-panel-heading">
          <div>
            <span class="rd-kicker">YOUR SUBMISSIONS</span>
            <h2>My Proposals</h2>
            <p>Track the progress and latest activity of your research applications.</p>
          </div>
          <a class="rd-text-link" href="<?= e(url('action=applications')) ?>">View all proposals <span aria-hidden="true">→</span></a>
        </div>

        <?php if (!empty($underRevision)): ?>
          <div class="rd-attention">
            <span class="rd-attention-icon" aria-hidden="true">!</span>
            <div>
              <strong><?= count($underRevision) ?> proposal<?= count($underRevision) === 1 ? '' : 's' ?> need<?= count($underRevision) === 1 ? 's' : '' ?> revision</strong>
              <span>Review the feedback and submit the requested changes before the deadline.</span>
            </div>
            <a class="btn btn-sm" href="<?= e(url('action=application_view&id=' . (int) $underRevision[0]['id'])) ?>">Review request</a>
          </div>
        <?php endif; ?>

        <?php if (!empty($submittedProposals) || !empty($draftProposals)): ?>
          <div class="rd-table-wrap">
            <table class="tbl rd-table">
              <thead><tr><th>Proposal</th><th>Research Call</th><th>Status</th><th>Last Activity</th><th></th></tr></thead>
              <tbody>
                <?php foreach ($submittedProposals as $app): ?>
                  <tr>
                    <td><strong><?= e($app['title']) ?></strong><small><?= e($app['application_number'] ?? 'Submitted proposal') ?></small></td>
                    <td><?= e($app['call_title'] ?? '—') ?></td>
                    <td><span class="badge <?= e(status_badge($app['status_code'])) ?>"><?= e(ucwords(strtolower(str_replace('_', ' ', $app['status_code'])))) ?></span></td>
                    <td><?= e(format_date($app['submitted_at'])) ?></td>
                    <td><a class="rd-row-action" href="<?= e(url('action=application_view&id=' . (int) $app['id'])) ?>">View <span aria-hidden="true">→</span></a></td>
                  </tr>
                <?php endforeach; ?>
                <?php foreach ($draftProposals as $app): ?>
                  <tr>
                    <td><strong><?= e($app['title']) ?></strong><small>Draft proposal</small></td>
                    <td><?= e($app['call_title'] ?? '—') ?></td>
                    <td><span class="badge badge-secondary">Draft</span></td>
                    <td><?= e(format_date($app['created_at'])) ?></td>
                    <td><a class="rd-row-action" href="<?= e(url('action=application_form&id=' . (int) $app['id'])) ?>">Continue <span aria-hidden="true">→</span></a></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="rd-empty">
            <span class="rd-empty-icon" aria-hidden="true">▤</span>
            <strong>No proposals yet</strong>
            <p>Browse published research calls and start a proposal when you find an opportunity that fits your research.</p>
            <a class="btn btn-sm" href="<?= e(url('action=calls_public')) ?>">Browse research calls</a>
          </div>
        <?php endif; ?>
      </section>

      <section class="rd-panel">
        <div class="rd-panel-heading">
          <div><span class="rd-kicker">YOUR REVIEW RESPONSIBILITIES</span><h2>Evaluation Assignments</h2><p>Assignments you have accepted or still need to complete.</p></div>
          <a class="rd-text-link" href="<?= e(url('action=my_evaluations')) ?>">View all evaluations <span aria-hidden="true">→</span></a>
        </div>
        <?php if (!empty($pendingEvaluations)): ?>
          <div class="rd-task-list">
            <?php foreach (array_slice($pendingEvaluations, 0, 5) as $eval): ?>
              <div class="rd-task">
                <span class="rd-task-icon" aria-hidden="true">✓</span>
                <div class="rd-task-copy">
                  <strong><?= e($eval['title']) ?></strong>
                  <span><?= e($eval['application_number']) ?> · Principal researcher: <?= e($eval['pi_name']) ?></span>
                </div>
                <div class="rd-task-meta">
                  <span class="badge badge-warning">Awaiting evaluation</span>
                  <small>Deadline: <?= e(format_date($eval['deadline'])) ?></small>
                </div>
                <a class="btn btn-sm" href="<?= e(url('action=evaluation_form&id=' . (int) $eval['id'])) ?>">Evaluate</a>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="rd-inline-empty">You have no pending evaluation assignments.</div>
        <?php endif; ?>
      </section>

      <section class="rd-panel">
        <div class="rd-panel-heading">
          <div><span class="rd-kicker">RESEARCH IMPLEMENTATION</span><h2>Active Projects</h2><p>Projects where you are currently an active team member.</p></div>
          <a class="rd-text-link" href="<?= e(url('action=projects')) ?>">View all projects <span aria-hidden="true">→</span></a>
        </div>
        <?php if (!empty($activeProjects)): ?>
          <div class="rd-table-wrap">
            <table class="tbl rd-table">
              <thead><tr><th>Project</th><th>Start Date</th><th>Planned End</th><th>Reports Due</th><th></th></tr></thead>
              <tbody>
                <?php foreach ($activeProjects as $proj): ?>
                  <tr>
                    <td><strong><?= e($proj['title']) ?></strong><small><?= e($proj['project_number']) ?></small></td>
                    <td><?= e(format_date($proj['actual_start'])) ?></td>
                    <td><?= e(format_date($proj['planned_end'])) ?></td>
                    <td><?= (int) $proj['pending_reports'] ?></td>
                    <td><a class="rd-row-action" href="<?= e(url('action=project_view&id=' . (int) $proj['id'])) ?>">Open <span aria-hidden="true">→</span></a></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="rd-inline-empty">You are not currently listed on an active research project.</div>
        <?php endif; ?>
      </section>

      <?php if (!empty($completedEvaluations)): ?>
        <section class="rd-panel">
          <div class="rd-panel-heading">
            <div><span class="rd-kicker">COMPLETED REVIEW WORK</span><h2>Recently Completed Evaluations</h2><p>Your submitted assessments are recorded here for reference.</p></div>
          </div>
          <div class="rd-simple-list">
            <?php foreach (array_slice($completedEvaluations, 0, 4) as $evaluation): ?>
              <div>
                <span class="rd-list-dot rd-dot-blue"></span>
                <div><strong><?= e($evaluation['title']) ?></strong><small><?= e($evaluation['application_number']) ?> · Submitted <?= e(format_date($evaluation['submitted_at'])) ?><?= $evaluation['weighted_score'] !== null ? ' · Score: ' . e((string) $evaluation['weighted_score']) : '' ?></small></div>
                <a class="rd-row-action" href="<?= e(url('action=evaluation_view&id=' . (int) $evaluation['id'])) ?>">View</a>
              </div>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endif; ?>

      <?php if (!empty($pendingAgreements) || !empty($pendingReports) || !empty($overdueReports)): ?>
        <section class="rd-panel">
          <div class="rd-panel-heading"><div><span class="rd-kicker">FOLLOW-UP REQUIRED</span><h2>Research Administration</h2><p>Agreements and project reporting items that may need your attention.</p></div></div>
          <?php if (!empty($pendingAgreements)): ?>
            <div class="rd-simple-list">
              <?php foreach ($pendingAgreements as $ag): ?>
                <div><span class="rd-list-dot"></span><div><strong><?= e($ag['title']) ?></strong><small>Agreement v<?= e((string) $ag['version']) ?> · <?= e(ucfirst($ag['status'])) ?></small></div><a class="rd-row-action" href="<?= e(url('action=application_view&id=' . (int) $ag['application_id'])) ?>">Open</a></div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <?php if (!empty($pendingReports)): ?>
            <div class="rd-simple-list">
              <?php foreach ($pendingReports as $report): ?>
                <div><span class="rd-list-dot rd-dot-blue"></span><div><strong><?= e($report['title']) ?></strong><small><?= e($report['report_number']) ?> · Due <?= e(format_date($report['period_end'])) ?></small></div><a class="rd-row-action" href="<?= e(url('action=project_view&id=' . (int) $report['project_id'])) ?>">Open</a></div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <?php if (!empty($overdueReports)): ?>
            <div class="rd-simple-list">
              <?php foreach ($overdueReports as $report): ?>
                <div><span class="rd-list-dot rd-dot-red"></span><div><strong><?= e($report['title']) ?></strong><small><?= e($report['report_number']) ?> · <?= (int) $report['days_overdue'] ?> days overdue</small></div><a class="rd-row-action" href="<?= e(url('action=project_view&id=' . (int) $report['project_id'])) ?>">Open</a></div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>
      <?php endif; ?>
    </div>

    <aside class="rd-side-column">
      <section class="rd-panel rd-profile-panel">
        <div class="rd-panel-heading rd-panel-heading-tight">
          <div><span class="rd-kicker">YOUR PROFILE</span><h2>Profile Completion</h2></div>
          <span class="rd-percent"><?= $profilePercent ?>%</span>
        </div>
        <div class="rd-progress-track" role="progressbar" aria-valuenow="<?= $profilePercent ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Profile completion">
          <span style="width: <?= $profilePercent ?>%"></span>
        </div>
        <p class="rd-muted-copy"><?= $profilePercent >= 100 ? 'Your researcher profile is complete.' : 'Complete your academic and research information to strengthen your researcher profile.' ?></p>
        <a class="btn btn-outline btn-block" href="<?= e(url('action=profile')) ?>">Update My Profile</a>
      </section>

      <section class="rd-panel">
        <div class="rd-panel-heading">
          <div><span class="rd-kicker">NEW OPPORTUNITIES</span><h2>Open Research Calls</h2><p>Discover calls that match your research interests.</p></div>
        </div>
        <?php if (!empty($openCalls)): ?>
          <div class="rd-call-list">
            <?php foreach (array_slice($openCalls, 0, 5) as $call): ?>
              <article class="rd-call">
                <span class="rd-call-mark" aria-hidden="true">✦</span>
                <div class="rd-call-copy"><strong><?= e($call['title']) ?></strong><small><?= e($call['call_number']) ?></small><small>Closes <?= e(format_datetime($call['closes_at'])) ?></small></div>
                <a class="rd-icon-link" aria-label="View research call" href="<?= e(url('action=call_view&id=' . (int) $call['id'])) ?>">↗</a>
              </article>
            <?php endforeach; ?>
          </div>
          <a class="rd-panel-footer-link" href="<?= e(url('action=calls_public')) ?>">Browse all research calls <span aria-hidden="true">→</span></a>
        <?php else: ?>
          <div class="rd-inline-empty">There are no open research calls at the moment.</div>
        <?php endif; ?>
      </section>

      <section class="rd-panel">
        <div class="rd-panel-heading">
          <div><span class="rd-kicker">LATEST UPDATES</span><h2>Recent Notifications</h2></div>
          <span class="rd-notification-count"><?= (int) $unread ?> unread</span>
        </div>
        <?php if (!empty($notifications)): ?>
          <div class="rd-notification-list">
            <?php foreach (array_slice($notifications, 0, 5) as $notif): ?>
              <a href="<?= e($notif['link_url'] ?: url('action=notifications')) ?>" class="rd-notification">
                <span class="rd-notification-dot"></span>
                <span><strong><?= e($notif['title']) ?></strong><small><?= e($notif['body'] ?? '') ?></small><small><?= e(format_datetime($notif['created_at'])) ?></small></span>
              </a>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="rd-inline-empty">You're all caught up. No unread notifications right now.</div>
        <?php endif; ?>
        <a class="rd-panel-footer-link" href="<?= e(url('action=notifications')) ?>">Open notification center <span aria-hidden="true">→</span></a>
      </section>

      <section class="rd-panel rd-mini-summary">
        <div class="rd-panel-heading"><div><span class="rd-kicker">YOUR RESEARCH AT A GLANCE</span><h2>Activity Summary</h2></div></div>
        <div class="rd-summary-row"><span>Active projects</span><strong><?= $projectCount ?></strong></div>
        <div class="rd-summary-row"><span>Pending agreements</span><strong><?= (int) ($stats['pendingAgreements'] ?? 0) ?></strong></div>
        <div class="rd-summary-row"><span>Project reports due</span><strong><?= (int) ($stats['pendingReports'] ?? 0) + (int) ($stats['overdueReports'] ?? 0) ?></strong></div>
      </section>
    </aside>
  </div>
</div>

<?php include APP_PATH . '/views/partials/footer.php'; ?>
