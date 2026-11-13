<?php 
$title = 'Research Director Executive Dashboard'; 
include APP_PATH . '/views/partials/header.php'; 
?>

<div class="page-head">
    <div class="page-head-title">
        <h1>Research Director Executive Dashboard</h1>
        <p>University-wide research governance, calls management, and permanent evaluation committee oversight.</p>
    </div>
    <div class="page-head-actions">
        <a class="btn btn-primary" href="<?= e(url('action=call_form')) ?>">➕ Create Research Call</a>
        <a class="btn btn-secondary" href="<?= e(url('action=themes')) ?>">🏷️ Theme Catalogue</a>
        <a class="btn btn-secondary" href="<?= e(url('action=rubrics')) ?>">📋 Global Rubrics</a>
        <a class="btn btn-secondary" href="<?= e(url('action=committees')) ?>">🏛️ Committee</a>
    </div>
</div>

<!-- Executive Summary Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Research Calls</span>
            <span class="stat-icon">📢</span>
        </div>
        <div class="stat-value"><?= (int) ($stats['totalCalls'] ?? 0) ?></div>
        <div class="stat-desc">
            <span class="badge badge-ok"><?= (int) ($stats['publishedCalls'] ?? 0) ?> Active</span>
            <span class="badge badge-danger"><?= (int) ($stats['closedCalls'] ?? 0) ?> Closed</span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Total Proposals</span>
            <span class="stat-icon">📑</span>
        </div>
        <div class="stat-value"><?= (int) ($stats['totalProposals'] ?? 0) ?></div>
        <div class="stat-desc">Submissions across all academic units</div>
    </div>

    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">College Stage</span>
            <span class="stat-icon">🏫</span>
        </div>
        <div class="stat-value"><?= (int) ($stats['collegeEvaluationInProgress'] ?? 0) ?></div>
        <div class="stat-desc">
            <span class="badge badge-warning"><?= (int) ($stats['collegeAwaitingAssignment'] ?? 0) ?> Screening</span>
            <span class="badge badge-ok"><?= (int) ($stats['collegeApproved'] ?? 0) ?> Approved</span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">University Evaluation</span>
            <span class="stat-icon">⚖️</span>
        </div>
        <div class="stat-value"><?= (int) ($stats['univEvaluationsInProgress'] ?? 0) ?></div>
        <div class="stat-desc">
            <span class="badge badge-info"><?= (int) ($stats['awaitingUniversityEvaluation'] ?? 0) ?> Awaiting</span>
            <span class="badge badge-ok"><?= (int) ($stats['finalDecisions'] ?? 0) ?> Decided</span>
        </div>
    </div>
</div>

<!-- Priority Action Items Banner -->
<?php if (!empty($pendingCommitteeAssignment) || !empty($completedAwaitingDecision)): ?>
<div class="action-banner">
    <div class="action-banner-content">
        <h3>⚡ Actions Awaiting Director Oversight</h3>
        <p>You have <strong><?= count($pendingCommitteeAssignment) ?></strong> college-approved proposals awaiting university committee assignment, and <strong><?= count($completedAwaitingDecision) ?></strong> proposals awaiting final decision recording.</p>
    </div>
    <div style="display:flex; gap:10px;">
        <?php if (!empty($pendingCommitteeAssignment)): ?>
            <a class="btn btn-primary btn-sm" href="#pendingUniversitySection">Assign Committee</a>
        <?php endif; ?>
        <?php if (!empty($completedAwaitingDecision)): ?>
            <a class="btn btn-secondary btn-sm" href="#completedDecisionSection">Review Decisions</a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Section 1: Proposals Awaiting University Committee Review -->
<div class="card" id="pendingUniversitySection">
    <div class="card-header">
        <div>
            <h2 class="card-title">College-Approved Proposals Forwarded to University</h2>
            <div class="card-subtitle">Proposals that passed college-level evaluation (>= 50% threshold) and require committee routing</div>
        </div>
        <span class="badge badge-purple"><?= count($pendingCommitteeAssignment) ?> Pending</span>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Proposal Title</th>
                    <th>Principal Investigator</th>
                    <th>College</th>
                    <th>Submitted</th>
                    <th>Current Status</th>
                    <th style="text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pendingCommitteeAssignment)): ?>
                    <tr><td colspan="7" style="text-align:center; padding:24px; color:var(--text-muted);">No proposals currently awaiting university assignment.</td></tr>
                <?php else: ?>
                    <?php foreach ($pendingCommitteeAssignment as $a): ?>
                    <tr>
                        <td><strong><?= e($a['application_number']) ?></strong></td>
                        <td>
                            <a href="<?= e(url('action=application_view&id=' . $a['id'])) ?>" style="font-weight:600;">
                                <?= e($a['title']) ?>
                            </a>
                        </td>
                        <td><?= e($a['pi_name']) ?></td>
                        <td><span class="badge badge-purple"><?= e($a['college_name'] ?? 'College') ?></span></td>
                        <td><?= e(format_date($a['submitted_at'])) ?></td>
                        <td><span class="badge badge-info"><?= e(status_label($a['status_code'])) ?></span></td>
                        <td style="text-align: right;">
                            <a class="btn btn-primary btn-sm" href="<?= e(url('action=committee_assign&application_id=' . $a['id'])) ?>">Assign Committee</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Section 2: Permanent Committee & Meeting Overview -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">
    <!-- Active Research Calls -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <div>
                <h2 class="card-title">Active Research Calls</h2>
                <div class="card-subtitle">Calls currently published and open for researcher submissions</div>
            </div>
            <a class="btn btn-secondary btn-sm" href="<?= e(url('action=calls')) ?>">View All</a>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Call Title</th>
                        <th>Reference</th>
                        <th>Deadline</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($activeCalls)): ?>
                        <tr><td colspan="4" style="text-align:center; padding:20px; color:var(--text-muted);">No open research calls active.</td></tr>
                    <?php else: ?>
                        <?php foreach ($activeCalls as $call): ?>
                        <tr>
                            <td>
                                <a href="<?= e(url('action=call_view&id=' . $call['id'])) ?>" style="font-weight:600;">
                                    <?= e($call['title']) ?>
                                </a>
                            </td>
                            <td><small><?= e($call['reference_number'] ?? '-') ?></small></td>
                            <td style="white-space:nowrap;"><?= e(format_date($call['closes_at'])) ?></td>
                            <td><span class="badge badge-ok"><?= e($call['status']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Permanent Committee Status -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <div>
                <h2 class="card-title">Permanent Research Committee</h2>
                <div class="card-subtitle">Standing university evaluation body governance</div>
            </div>
            <a class="btn btn-secondary btn-sm" href="<?= e(url('action=committees')) ?>">Manage Committee</a>
        </div>
        <div class="card-body">
            <div style="display: flex; gap: 16px; margin-bottom: 20px;">
                <div style="flex: 1; padding: 14px; background: var(--bg-subtle); border-radius: var(--radius-md); text-align: center;">
                    <div style="font-size: 22px; font-weight: 700; color: var(--brand);"><?= (int) ($stats['activeCommitteeMembers'] ?? 0) ?></div>
                    <div style="font-size: 12px; color: var(--text-muted);">Active Members</div>
                </div>
                <div style="flex: 1; padding: 14px; background: var(--bg-subtle); border-radius: var(--radius-md); text-align: center;">
                    <div style="font-size: 22px; font-weight: 700; color: var(--ok);"><?= (int) ($stats['upcomingMeetings'] ?? 0) ?></div>
                    <div style="font-size: 12px; color: var(--text-muted);">Scheduled Meetings</div>
                </div>
            </div>
            <?php if (!empty($committeesNeedingMembers)): ?>
                <div class="alert alert-warning" style="margin-bottom: 0;">
                    <div>⚠️ <strong>Quorum Attention:</strong> Some evaluation committees have fewer than their required quorum size. Please appoint qualified faculty members.</div>
                </div>
            <?php else: ?>
                <div class="alert alert-success" style="margin-bottom: 0;">
                    <div>✅ <strong>Quorum Satisfied:</strong> The standing university committee has active members and quorum.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Section 3: Recent Activity Stream -->
<div class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">Recent Directorate & Evaluation Activity</h2>
            <div class="card-subtitle">Audit stream of recent assessments, committee assignments, and decisions</div>
        </div>
        <a class="btn btn-secondary btn-sm" href="<?= e(url('action=audit')) ?>">View All Logs</a>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Action</th>
                    <th>Entity</th>
                    <th>Actor</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentActivity)): ?>
                    <tr><td colspan="4" style="text-align:center; padding:20px; color:var(--text-muted);">No recent actions logged.</td></tr>
                <?php else: ?>
                    <?php foreach ($recentActivity as $act): ?>
                    <tr>
                        <td style="white-space:nowrap;"><?= e(format_datetime($act['created_at'])) ?></td>
                        <td><span class="badge badge-purple"><?= e($act['action']) ?></span></td>
                        <td><code><?= e($act['entity_type']) ?> #<?= (int) $act['entity_id'] ?></code></td>
                        <td><strong><?= e($act['actor_name'] ?? 'System') ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include APP_PATH . '/views/partials/footer.php'; ?>
