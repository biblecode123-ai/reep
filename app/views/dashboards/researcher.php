<?php
$title = 'Researcher Workspace';
include APP_PATH . '/views/partials/header.php';
?>

<div class="page-head">
    <div class="page-head-title">
        <h1>Researcher Workspace</h1>
        <p>Manage your research proposals, collaborations, revisions, and assigned peer evaluations.</p>
    </div>
    <div class="page-head-actions">
        <a class="btn btn-primary" href="<?= e(url('action=application_form')) ?>">➕ Submit New Proposal</a>
        <a class="btn btn-secondary" href="<?= e(url('action=calls_public')) ?>">📢 Browse Open Calls</a>
        <a class="btn btn-secondary" href="<?= e(url('action=profile')) ?>">👤 Edit Profile (<?= (int)$profileCompletion ?>% Complete)</a>
    </div>
</div>

<!-- Primary Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">My Applications</span>
            <span class="stat-icon">📑</span>
        </div>
        <div class="stat-value"><?= (int) ($stats['submittedProposals'] ?? 0) ?></div>
        <div class="stat-desc">
            <span class="badge badge-ok"><?= (int) ($stats['approvedProposals'] ?? 0) ?> Approved</span>
            <?php if ((int)($stats['underRevision'] ?? 0) > 0): ?>
                <span class="badge badge-warning"><?= (int) $stats['underRevision'] ?> Revision Required</span>
            <?php endif; ?>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Evaluation Progress</span>
            <span class="stat-icon">⏳</span>
        </div>
        <div class="stat-value"><?= (int) (($stats['collegeEvaluating'] ?? 0) + ($stats['universityReview'] ?? 0)) ?></div>
        <div class="stat-desc">
            <span class="badge badge-purple"><?= (int) ($stats['collegeEvaluating'] ?? 0) ?> College Stage</span>
            <span class="badge badge-info"><?= (int) ($stats['universityReview'] ?? 0) ?> University Stage</span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Peer Evaluation Duties</span>
            <span class="stat-icon">📝</span>
        </div>
        <div class="stat-value"><?= (int) ($stats['pendingEvaluations'] ?? 0) ?></div>
        <div class="stat-desc">
            <span class="badge badge-warning">Assignments Pending</span>
            <span class="badge badge-ok"><?= (int) ($stats['completedEvaluations'] ?? 0) ?> Completed</span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Collaborations</span>
            <span class="stat-icon">🤝</span>
        </div>
        <div class="stat-value"><?= (int) ($stats['acceptedCollaborations'] ?? 0) ?></div>
        <div class="stat-desc">
            <?php if ((int)($stats['pendingInvitations'] ?? 0) > 0): ?>
                <span class="badge badge-danger"><?= (int) $stats['pendingInvitations'] ?> Invitations Pending</span>
            <?php else: ?>
                <span class="badge badge-ok">All Invitations Answered</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Pending Co-investigator Invitations Banner -->
<?php if (!empty($coiInvitations)): ?>
<div class="action-banner">
    <div class="action-banner-content">
        <h3>🤝 You have pending co-investigator invitations</h3>
        <p>Other principal researchers have invited you to collaborate on their research proposals.</p>
    </div>
    <div style="display:flex; gap:10px;">
        <a class="btn btn-primary btn-sm" href="#collaborationsSection">Respond to Invitations</a>
    </div>
</div>
<?php endif; ?>

<!-- Segmented Tabs for Applicant vs. Evaluator Separation -->
<div class="tab-container">
    <div class="nav-tabs">
        <a class="nav-tab-item active" href="#tab-applications" data-tab-target="#tab-applications">
            📁 My Research Applications (<?= count($submittedProposals) + count($draftProposals) ?>)
        </a>
        <a class="nav-tab-item" href="#tab-evaluations" data-tab-target="#tab-evaluations">
            📝 My Evaluation Assignments (<?= count($pendingEvaluations) ?>)
        </a>
        <a class="nav-tab-item" href="#tab-collaborations" data-tab-target="#tab-collaborations" id="collaborationsSection">
            🤝 Collaborations & Invitations (<?= count($coiInvitations) + count($collaborations) ?>)
        </a>
        <a class="nav-tab-item" href="#tab-calls" data-tab-target="#tab-calls">
            📢 Available Research Calls (<?= count($openCalls) ?>)
        </a>
    </div>

    <!-- TAB 1: MY RESEARCH APPLICATIONS -->
    <div class="tab-content-panel" id="tab-applications" style="display: block;">
        <!-- Revision Alerts -->
        <?php if (!empty($underRevision)): ?>
        <div class="card" style="border-left: 4px solid var(--warning);">
            <div class="card-header" style="background: var(--warning-soft);">
                <div>
                    <h3 class="card-title" style="color: var(--warning-text);">⚠️ Proposals Requiring Revision</h3>
                    <div class="card-subtitle">Review feedback from the evaluation committee and upload your revised PDF document before the deadline.</div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Application</th>
                            <th>Title</th>
                            <th>Revision Reason / Feedback</th>
                            <th>Requested Date</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($underRevision as $rev): ?>
                        <tr>
                            <td><strong><?= e($rev['application_number']) ?></strong></td>
                            <td><?= e($rev['title']) ?></td>
                            <td><?= e($rev['request_reason'] ?? 'Please revise proposal per evaluator feedback.') ?></td>
                            <td><?= e(format_date($rev['requested_at'])) ?></td>
                            <td style="text-align: right;">
                                <a class="btn btn-warning btn-sm" href="<?= e(url('action=application_view&id=' . $rev['id'])) ?>">
                                    📤 Upload Revised PDF
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Active Submitted Applications -->
        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Submitted Research Proposals</h2>
                    <div class="card-subtitle">Live tracking of your submissions across college and university review stages</div>
                </div>
                <a class="btn btn-primary btn-sm" href="<?= e(url('action=application_form')) ?>">➕ New Application</a>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Application Reference</th>
                            <th>Proposal Title</th>
                            <th>Research Call</th>
                            <th>Submitted Date</th>
                            <th>Current Stage</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($submittedProposals)): ?>
                            <tr><td colspan="6" style="text-align:center; padding:24px; color:var(--text-muted);">You have not submitted any proposals yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($submittedProposals as $app): ?>
                            <tr>
                                <td><strong><?= e($app['application_number']) ?></strong></td>
                                <td>
                                    <a href="<?= e(url('action=application_view&id=' . $app['id'])) ?>" style="font-weight:600;">
                                        <?= e($app['title']) ?>
                                    </a>
                                </td>
                                <td><?= e($app['call_title'] ?? 'Open Institutional Call') ?></td>
                                <td><?= e(format_date($app['submitted_at'])) ?></td>
                                <td>
                                    <span class="badge <?= status_badge($app['status_code']) ?>">
                                        <?= e(status_label($app['status_code'])) ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <a class="btn btn-secondary btn-sm" href="<?= e(url('action=application_view&id=' . $app['id'])) ?>">
                                        Track Progress
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Draft Applications -->
        <?php if (!empty($draftProposals)): ?>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Incomplete Draft Proposals</h3>
                <span class="badge badge-warning"><?= count($draftProposals) ?> Drafts</span>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Research Call</th>
                            <th>Created Date</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($draftProposals as $draft): ?>
                        <tr>
                            <td><strong><?= e($draft['title'] ?: 'Untitled Draft') ?></strong></td>
                            <td><?= e($draft['call_title'] ?? '-') ?></td>
                            <td><?= e(format_date($draft['created_at'])) ?></td>
                            <td style="text-align: right;">
                                <a class="btn btn-primary btn-sm" href="<?= e(url('action=application_form&id=' . $draft['id'])) ?>">Continue Editing</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- TAB 2: MY EVALUATION ASSIGNMENTS -->
    <div class="tab-content-panel" id="tab-evaluations" style="display: none;">
        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Pending Proposal Evaluations Assigned to You</h2>
                    <div class="card-subtitle">Conduct criterion-level independent scoring using the applicable global evaluation framework</div>
                </div>
                <a class="btn btn-secondary btn-sm" href="<?= e(url('action=my_evaluations')) ?>">View Evaluation Portal</a>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Application Reference</th>
                            <th>Proposal Title</th>
                            <th>Principal Investigator</th>
                            <th>Evaluation Deadline</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pendingEvaluations)): ?>
                            <tr><td colspan="5" style="text-align:center; padding:24px; color:var(--text-muted);">You currently have no pending evaluation assignments.</td></tr>
                        <?php else: ?>
                            <?php foreach ($pendingEvaluations as $ev): ?>
                            <tr>
                                <td><strong><?= e($ev['application_number']) ?></strong></td>
                                <td><strong><?= e($ev['title']) ?></strong></td>
                                <td><?= e($ev['pi_name']) ?></td>
                                <td>
                                    <?php if (!empty($ev['deadline'])): ?>
                                        <span class="badge badge-warning"><?= e(format_date($ev['deadline'])) ?></span>
                                    <?php else: ?>
                                        <span class="muted">No deadline specified</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right;">
                                    <a class="btn btn-primary btn-sm" href="<?= e(url('action=evaluation_form&application_id=' . $ev['id'])) ?>">
                                        Score Proposal
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Completed Evaluations History -->
        <?php if (!empty($completedEvaluations)): ?>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Your Submitted Assessments History</h3>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Application</th>
                            <th>Title</th>
                            <th>Your Total Score</th>
                            <th>Submitted Date</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($completedEvaluations as $comp): ?>
                        <tr>
                            <td><strong><?= e($comp['application_number']) ?></strong></td>
                            <td><?= e($comp['title']) ?></td>
                            <td><strong style="color:var(--brand);"><?= number_format((float)$comp['weighted_score'], 2) ?>%</strong></td>
                            <td><?= e(format_date($comp['submitted_at'])) ?></td>
                            <td style="text-align: right;">
                                <a class="btn btn-secondary btn-sm" href="<?= e(url('action=evaluation_view&id=' . $comp['id'])) ?>">View Assessment</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- TAB 3: COLLABORATIONS & INVITATIONS -->
    <div class="tab-content-panel" id="tab-collaborations" style="display: none;">
        <!-- Pending Invitations -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Co-Investigator Invitations</h3>
                <div class="card-subtitle">Invitations from other researchers to join their research proposals</div>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Proposal</th>
                            <th>Principal Investigator</th>
                            <th>Proposed Role</th>
                            <th>Invited Date</th>
                            <th style="text-align: right;">Response</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($coiInvitations)): ?>
                            <tr><td colspan="5" style="text-align:center; padding:20px; color:var(--text-muted);">No pending invitations.</td></tr>
                        <?php else: ?>
                            <?php foreach ($coiInvitations as $inv): ?>
                            <tr>
                                <td>
                                    <strong><?= e($inv['application_number']) ?></strong><br>
                                    <?= e($inv['proposal_title']) ?>
                                </td>
                                <td><strong><?= e($inv['pi_name']) ?></strong></td>
                                <td><span class="badge badge-purple"><?= e(ucfirst($inv['role'] ?? 'co_investigator')) ?></span></td>
                                <td><?= e(format_date($inv['invited_at'])) ?></td>
                                <td style="text-align: right;">
                                    <form method="POST" action="<?= e(url('action=application_respond_member')) ?>" style="display:inline;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="member_id" value="<?= (int) $inv['id'] ?>">
                                        <input type="hidden" name="response" value="accept">
                                        <button type="submit" class="btn btn-success btn-sm">Accept</button>
                                    </form>
                                    <form method="POST" action="<?= e(url('action=application_respond_member')) ?>" style="display:inline; margin-left:6px;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="member_id" value="<?= (int) $inv['id'] ?>">
                                        <input type="hidden" name="response" value="decline">
                                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Decline this invitation?')">Decline</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Accepted Collaborations -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Active Collaborations</h3>
                <div class="card-subtitle">Proposals where you are a confirmed team member</div>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Proposal</th>
                            <th>Principal Investigator</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Joined Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($collaborations)): ?>
                            <tr><td colspan="5" style="text-align:center; padding:20px; color:var(--text-muted);">No active collaborations.</td></tr>
                        <?php else: ?>
                            <?php foreach ($collaborations as $collab): ?>
                            <tr>
                                <td>
                                    <strong><?= e($collab['application_number']) ?></strong><br>
                                    <?= e($collab['proposal_title']) ?>
                                </td>
                                <td><?= e($collab['pi_name']) ?></td>
                                <td><span class="badge badge-purple"><?= e(ucfirst($collab['role'] ?? 'co_investigator')) ?></span></td>
                                <td><span class="badge <?= status_badge($collab['status_code']) ?>"><?= e(status_label($collab['status_code'])) ?></span></td>
                                <td><?= e(format_date($collab['joined_at'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 4: AVAILABLE RESEARCH CALLS -->
    <div class="tab-content-panel" id="tab-calls" style="display: none;">
        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Open Calls for Research Proposals</h2>
                    <div class="card-subtitle">Institutional calls currently accepting faculty applications</div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Call Title</th>
                            <th>Reference Number</th>
                            <th>Submission Deadline</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($openCalls)): ?>
                            <tr><td colspan="4" style="text-align:center; padding:24px; color:var(--text-muted);">No research calls are currently open.</td></tr>
                        <?php else: ?>
                            <?php foreach ($openCalls as $call): ?>
                            <tr>
                                <td>
                                    <strong><?= e($call['title']) ?></strong><br>
                                    <small class="muted"><?= e($call['theme_name'] ?? 'Multi-theme call') ?></small>
                                </td>
                                <td><code><?= e($call['reference_number'] ?? $call['call_number']) ?></code></td>
                                <td><span class="badge badge-warning"><?= e(format_date($call['closes_at'])) ?></span></td>
                                <td style="text-align: right;">
                                    <a class="btn btn-primary btn-sm" href="<?= e(url('action=application_form&call_id=' . $call['id'])) ?>">Apply Now</a>
                                    <a class="btn btn-secondary btn-sm" href="<?= e(url('action=call_view&id=' . $call['id'])) ?>">Guidelines</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include APP_PATH . '/views/partials/footer.php'; ?>
