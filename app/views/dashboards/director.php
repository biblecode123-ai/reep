<?php $title = 'Research Director Dashboard'; include APP_PATH . '/views/partials/header.php'; ?>

<div class="page-head director-page-head">
    <div>
        <p class="eyebrow">RESEARCH, PUBLICATION, ETHICS &amp; EXTENSION</p>
        <h1>Research office overview</h1>
        <p>Priority decisions, active calls, and project follow-through in one place.</p>
    </div>
    <div class="director-date" aria-label="Current date"><?= e(date('F j, Y')) ?></div>
</div>

<section class="grid grid-4 director-summary-grid" aria-label="Research office priorities">
<?php foreach ([
  ['Active calls', $stats['activeCalls'], 'calls_public'],
  ['Awaiting evaluation assignment', $stats['awaitingCommitteeAssignment'], 'applications'],
  ['Evaluations in progress', $stats['univEvaluationsInProgress'], 'applications'],
  ['Decisions to record', $stats['completedAwaitingDecision'], 'applications'],
  ['Funding to authorize', $stats['fundingDecisionsPending'], 'applications'],
  ['Overdue project reports', $stats['overdueReports'], 'projects'],
] as $index => $c): ?>
    <a class="stat director-stat director-stat-<?= e((string) ($index + 1)) ?>" href="<?= e(url('action=' . $c[2])) ?>">
        <span class="director-stat-label"><?= e($c[0]) ?></span>
        <span class="director-stat-value"><?= e((string) $c[1]) ?></span>
        <span class="director-stat-link">View queue <span aria-hidden="true">→</span></span>
    </a>
<?php endforeach; ?>
</section>

<!-- 2. Priority Action Center -->
<section>
    <h2 class="section-title">Priority Action Center</h2>

    <!-- Proposals Awaiting Committee Assignment -->
    <div class="card">
        <h3 class="mt0">Proposals Awaiting University Evaluation Committee Assignment</h3>
        <?php if (empty($pendingCommitteeAssignment)): ?>
            <p class="muted">No proposals awaiting committee assignment.</p>
        <?php else: ?>
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Title</th>
                        <th>PI</th>
                        <th>College</th>
                        <th>Submitted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingCommitteeAssignment as $a): ?>
                    <tr>
                        <td><?= e($a['application_number']) ?></td>
                        <td><?= e($a['title']) ?></td>
                        <td><?= e($a['pi_name']) ?></td>
                        <td><?= e($a['college_name'] ?? '') ?></td>
                        <td><?= e(format_date($a['submitted_at'])) ?></td>
                        <td>
                            <a class="btn-sm" href="<?= e(url('action=app_committee_assign&id=' . $a['id'])) ?>">Assign Committee</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Overdue Evaluations -->
    <div class="card">
        <h3 class="mt0">Overdue Evaluations</h3>
        <?php if (empty($overdueEvaluations)): ?>
            <p class="muted">No overdue evaluations.</p>
        <?php else: ?>
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Title</th>
                        <th>Evaluator</th>
                        <th>Deadline</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($overdueEvaluations as $e): ?>
                    <tr class="danger">
                        <td><?= e($e['application_number']) ?></td>
                        <td><?= e($e['title']) ?></td>
                        <td><?= e($e['evaluator_name']) ?></td>
                        <td><?= e(format_date($e['deadline'])) ?></td>
                        <td><?= e($e['status']) ?></td>
                        <td>
                            <a class="btn-sm" href="<?= e(url('action=conflict_resolve&id=' . $e['id'])) ?>">Resolve</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Completed Evaluations Awaiting Decision -->
    <div class="card">
        <h3 class="mt0">Completed Evaluations Awaiting Official Decision</h3>
        <?php if (empty($completedAwaitingDecision)): ?>
            <p class="muted">No completed evaluations awaiting decision.</p>
        <?php else: ?>
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Title</th>
                        <th>PI</th>
                        <th>Completed</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($completedAwaitingDecision as $a): ?>
                    <tr>
                        <td><?= e($a['application_number']) ?></td>
                        <td><?= e($a['title']) ?></td>
                        <td><?= e($a['pi_name']) ?></td>
                        <td><?= e(format_date($a['evaluation_completed_at'])) ?></td>
                        <td>
                            <a class="btn-sm" href="<?= e(url('action=app_committee_decide&id=' . $a['id'])) ?>">Record Decision</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Funding Decisions Pending -->
    <div class="card">
        <h3 class="mt0">Funding Decisions Awaiting Authorization</h3>
        <?php if (empty($fundingDecisionsPending)): ?>
            <p class="muted">No funding decisions pending.</p>
        <?php else: ?>
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Title</th>
                        <th>PI</th>
                        <th>Requested</th>
                        <th>Approved</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fundingDecisionsPending as $f): ?>
                    <tr>
                        <td><?= e($f['application_number']) ?></td>
                        <td><?= e($f['title']) ?></td>
                        <td><?= e($f['pi_name']) ?></td>
                        <td><?= e(number_format($f['budget_requested'], 2)) ?></td>
                        <td><?= e($f['budget_approved_amount'] ? number_format($f['budget_approved_amount'], 2) : 'Pending') ?></td>
                        <td>
                            <a class="btn-sm" href="<?= e(url('action=application_budget&id=' . $f['id'])) ?>">Authorize</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Appeals Awaiting Review -->
    <div class="card">
        <h3 class="mt0">Appeals Awaiting Review or Committee Appointment</h3>
        <?php if (empty($appealsAwaitingReview)): ?>
            <p class="muted">No appeals awaiting review.</p>
        <?php else: ?>
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Reference</th>
                        <th>Title</th>
                        <th>PI</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($appealsAwaitingReview as $ap): ?>
                    <tr>
                        <td><?= e(ucfirst($ap['appealable_type'])) ?></td>
                        <td><?= e($ap['application_number']) ?></td>
                        <td><?= e($ap['title']) ?></td>
                        <td><?= e($ap['pi_name']) ?></td>
                        <td><?= e(ucfirst($ap['status'])) ?></td>
                        <td><?= e(format_date($ap['submitted_at'])) ?></td>
                        <td>
                            <a class="btn-sm" href="<?= e(url('action=appeals&id=' . $ap['id'])) ?>">Review</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Agreements Awaiting Signature -->
    <div class="card">
        <h3 class="mt0">Agreements Awaiting Signature</h3>
        <?php if (empty($agreementsAwaitingSignature)): ?>
            <p class="muted">No agreements awaiting signature.</p>
        <?php else: ?>
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Title</th>
                        <th>PI</th>
                        <th>Version</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($agreementsAwaitingSignature as $ag): ?>
                    <tr>
                        <td><?= e($ag['application_number']) ?></td>
                        <td><?= e($ag['title']) ?></td>
                        <td><?= e($ag['pi_name']) ?></td>
                        <td><?= e($ag['version']) ?></td>
                        <td><?= e(ucfirst($ag['status'])) ?></td>
                        <td>
                            <a class="btn-sm" href="<?= e(url('action=agreement_sign&id=' . $ag['id'])) ?>">Sign</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Projects with Overdue Reports -->
    <div class="card">
        <h3 class="mt0">Active Projects with Overdue Reports</h3>
        <?php if (empty($projectsWithOverdueReports)): ?>
            <p class="muted">No projects with overdue reports.</p>
        <?php else: ?>
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Project</th>
                        <th>Title</th>
                        <th>Due Date</th>
                        <th>Report Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($projectsWithOverdueReports as $p): ?>
                    <tr class="danger">
                        <td><?= e($p['project_number']) ?></td>
                        <td><?= e($p['title']) ?></td>
                        <td><?= e(format_date($p['due_date'])) ?></td>
                        <td><?= e(ucfirst($p['report_status'])) ?></td>
                        <td>
                            <a class="btn-sm" href="<?= e(url('action=project_view&id=' . $p['id'])) ?>">View Project</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</section>

<!-- 3. University Evaluation Management -->
<section>
    <h2 class="section-title">University Evaluation Management</h2>
    <div class="grid grid-4">
        <div class="stat">
            <div class="num"><?= e($evaluationSummary['proposalsReceived']) ?></div>
            <div class="lbl">Proposals Received</div>
        </div>
        <div class="stat">
            <div class="num"><?= e($evaluationSummary['activeCommittees']) ?></div>
            <div class="lbl">Active Committees</div>
        </div>
        <div class="stat">
            <div class="num"><?= e($evaluationSummary['assignedEvaluators']) ?></div>
            <div class="lbl">Assigned Evaluators</div>
        </div>
        <div class="stat">
            <div class="num"><?= e($evaluationSummary['completedEvaluations']) ?></div>
            <div class="lbl">Completed Evaluations</div>
        </div>
    </div>

    <div class="card">
        <h3 class="mt0">Recent Committee Assignments</h3>
        <?php if (empty($recentCommitteeAssignments)): ?>
            <p class="muted">No recent committee assignments.</p>
        <?php else: ?>
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Committee</th>
                        <th>Reference</th>
                        <th>Title</th>
                        <th>Assigned</th>
                        <th>Meeting Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentCommitteeAssignments as $a): ?>
                    <tr>
                        <td><?= e($a['committee_name']) ?></td>
                        <td><?= e($a['application_number']) ?></td>
                        <td><?= e($a['title']) ?></td>
                        <td><?= e(format_date($a['assigned_at'])) ?></td>
                        <td><?= e(format_date($a['meeting_date'] ?? '')) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</section>

<!-- 4. Funding Overview -->
<section>
    <h2 class="section-title">Funding Overview</h2>
    <div class="grid grid-4">
        <div class="stat">
            <div class="num"><?= e(number_format($fundingOverview['requestedBudget'], 2)) ?> ETB</div>
            <div class="lbl">Requested Budget</div>
        </div>
        <div class="stat">
            <div class="num"><?= e(number_format($fundingOverview['committeeRecommended'], 2)) ?> ETB</div>
            <div class="lbl">Committee Recommended</div>
        </div>
        <div class="stat">
            <div class="num"><?= e(number_format($fundingOverview['officiallyApproved'], 2)) ?> ETB</div>
            <div class="lbl">Officially Approved</div>
        </div>
        <div class="stat">
            <div class="num"><?= e(number_format($fundingOverview['allocatedFunds'], 2)) ?> ETB</div>
            <div class="lbl">Allocated Funds</div>
        </div>
        <div class="stat">
            <div class="num"><?= e(number_format($fundingOverview['disbursedFunds'], 2)) ?> ETB</div>
            <div class="lbl">Disbursed Funds</div>
        </div>
        <div class="stat">
            <div class="num"><?= e(number_format($fundingOverview['reportedExpenditure'], 2)) ?> ETB</div>
            <div class="lbl">Reported Expenditure</div>
        </div>
    </div>
</section>

<!-- 5. Appeals Overview -->
<section>
    <h2 class="section-title">Appeals Overview</h2>
    <div class="grid grid-4">
        <div class="stat">
            <div class="num"><?= e($appealsSummary['budgetAppeals']) ?></div>
            <div class="lbl">Budget Appeals</div>
        </div>
        <div class="stat">
            <div class="num"><?= e($appealsSummary['rejectedAppeals']) ?></div>
            <div class="lbl">Rejected Proposal Appeals</div>
        </div>
        <div class="stat">
            <div class="num"><?= e($appealsSummary['pendingReview']) ?></div>
            <div class="lbl">Pending Review</div>
        </div>
        <div class="stat">
            <div class="num"><?= e($appealsSummary['underReview']) ?></div>
            <div class="lbl">Under Review</div>
        </div>
        <div class="stat">
            <div class="num"><?= e($appealsSummary['awaitingImplementation']) ?></div>
            <div class="lbl">Awaiting Implementation</div>
        </div>
        <div class="stat">
            <div class="num"><?= e($appealsSummary['closedAppeals']) ?></div>
            <div class="lbl">Closed Appeals</div>
        </div>
    </div>
</section>

<!-- 6. Agreements and Project Monitoring -->
<section>
    <h2 class="section-title">Agreements and Project Monitoring</h2>
    <div class="grid grid-4">
        <div class="stat">
            <div class="num"><?= e($agreementsSummary['awaitingPreparation']) ?></div>
            <div class="lbl">Awaiting Agreement Preparation</div>
        </div>
        <div class="stat">
            <div class="num"><?= e($agreementsSummary['awaitingSignature']) ?></div>
            <div class="lbl">Awaiting Signature</div>
        </div>
        <div class="stat">
            <div class="num"><?= e($agreementsSummary['awaitingActivation']) ?></div>
            <div class="lbl">Awaiting Activation</div>
        </div>
        <div class="stat">
            <div class="num"><?= e($agreementsSummary['activeProjects']) ?></div>
            <div class="lbl">Active Projects</div>
        </div>
        <div class="stat">
            <div class="num"><?= e($agreementsSummary['extensionRequests']) ?></div>
            <div class="lbl">Extension Requests</div>
        </div>
        <div class="stat">
            <div class="num"><?= e($agreementsSummary['amendmentRequests']) ?></div>
            <div class="lbl">Amendment Requests</div>
        </div>
        <div class="stat">
            <div class="num"><?= e($agreementsSummary['awaitingFinalReport']) ?></div>
            <div class="lbl">Awaiting Final Report</div>
        </div>
        <div class="stat">
            <div class="num"><?= e($agreementsSummary['awaitingRepository']) ?></div>
            <div class="lbl">Awaiting Repository</div>
        </div>
    </div>
</section>

<!-- 7. Research Calls and Themes -->
<section>
    <h2 class="section-title">Research Calls and Themes</h2>
    <div class="grid grid-4">
        <div class="stat">
            <div class="num"><?= e($callsSummary['activeCalls']) ?></div>
            <div class="lbl">Active Calls</div>
        </div>
        <div class="stat">
            <div class="num"><?= e($callsSummary['upcomingCalls']) ?></div>
            <div class="lbl">Upcoming Calls</div>
        </div>
        <div class="stat">
            <div class="num"><?= e($callsSummary['closedCalls']) ?></div>
            <div class="lbl">Closed Calls</div>
        </div>
        <div class="stat">
            <div class="num"><?= e($callsSummary['archivedCalls']) ?></div>
            <div class="lbl">Archived Calls</div>
        </div>
    </div>

    <div class="card">
        <h3 class="mt0">Active and Upcoming Calls</h3>
        <?php if (empty($activeCalls)): ?>
            <p class="muted">No active or upcoming calls.</p>
        <?php else: ?>
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Title</th>
                        <th>Opens</th>
                        <th>Closes</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activeCalls as $c): ?>
                    <tr>
                        <td><?= e($c['reference_number']) ?></td>
                        <td><?= e($c['title']) ?></td>
                        <td><?= e(format_date($c['opens_at'])) ?></td>
                        <td><?= e(format_date($c['closes_at'])) ?></td>
                        <td><?= e(ucfirst($c['status'])) ?></td>
                        <td>
                            <a class="btn-sm" href="<?= e(url('action=call_view&id=' . $c['id'])) ?>">View</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</section>

<!-- 8. Recent Activity and Alerts -->
<section>
    <h2 class="section-title">Recent Activity and Alerts</h2>
    <div class="card">
        <?php if (empty($recentActivity)): ?>
            <p class="muted">No recent activity.</p>
        <?php else: ?>
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Action</th>
                        <th>Entity</th>
                        <th>Actor</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentActivity as $a): ?>
                    <tr>
                        <td><?= e(str_replace('.', ' ', $a['action'])) ?></td>
                        <td><?= e($a['entity_type']) ?> #<?= e($a['entity_id']) ?></td>
                        <td><?= e($a['actor_name'] ?? 'System') ?></td>
                        <td><?= e(format_date($a['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</section>

<?php include APP_PATH . '/views/partials/footer.php'; ?>
