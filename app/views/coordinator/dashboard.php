<?php
$title = 'College Research Coordinator Dashboard';
include APP_PATH . '/views/partials/header.php';
?>

<!-- Enhanced Page Header -->
<div class="page-head mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <span class="badge badge-purple font-weight-bold px-2 py-1"><i class="fas fa-university mr-1"></i> College Operations</span>
            <span class="text-muted small">Madda Walabu University</span>
        </div>
        <h1 class="page-title h3 font-weight-bold m-0 text-dark">College Research Coordination Dashboard</h1>
        <p class="text-muted small m-0 mt-1">Operational proposal screening, multi-evaluator assignment, and automated college evaluation scoring.</p>
    </div>
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <a class="btn btn-primary shadow-sm" href="<?= e(url('action=coordinator_proposals')) ?>">
            <i class="fas fa-folder-open mr-1"></i> View All College Proposals
        </a>
        <a class="btn btn-outline shadow-sm" href="<?= e(url('action=coordinator_proposals&filter=needs_evaluators')) ?>">
            <i class="fas fa-user-edit mr-1"></i> Evaluator Assignments
        </a>
        <a class="btn btn-outline shadow-sm" href="<?= e(url('action=reports')) ?>">
            <i class="fas fa-chart-line mr-1"></i> College Reports
        </a>
    </div>
</div>

<!-- Primary Stats Grid -->
<div class="row g-3 mb-4">
    <!-- College Submissions Card -->
    <div class="col-xl-3 col-md-6">
        <div class="card h-100 border-0 shadow-sm rounded-lg p-3 position-relative overflow-hidden" style="border-left: 4px solid #594FB6 !important; background: #ffffff;">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <span class="text-uppercase text-muted font-weight-bold" style="font-size: 11px; letter-spacing: 0.5px;">College Submissions</span>
                    <div class="h2 font-weight-bold m-0 text-dark mt-1"><?= (int) ($stats['totalProposals'] ?? 0) ?></div>
                </div>
                <div class="p-2 rounded-circle" style="background: rgba(89, 79, 182, 0.1); color: #594FB6;">
                    <i class="fas fa-file-alt fa-lg"></i>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 mt-2 pt-2 border-top">
                <span class="badge badge-amber font-weight-bold"><i class="fas fa-inbox mr-1"></i> <?= (int) ($stats['newSubmissions'] ?? 0) ?> New Submissions</span>
            </div>
        </div>
    </div>

    <!-- Evaluation Pipeline Card -->
    <div class="col-xl-3 col-md-6">
        <div class="card h-100 border-0 shadow-sm rounded-lg p-3 position-relative overflow-hidden" style="border-left: 4px solid #3B82F6 !important; background: #ffffff;">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <span class="text-uppercase text-muted font-weight-bold" style="font-size: 11px; letter-spacing: 0.5px;">Evaluation Pipeline</span>
                    <div class="h2 font-weight-bold m-0 text-dark mt-1"><?= (int) ($stats['underEvaluation'] ?? 0) ?></div>
                </div>
                <div class="p-2 rounded-circle" style="background: rgba(59, 130, 246, 0.1); color: #3B82F6;">
                    <i class="fas fa-clock fa-lg"></i>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 mt-2 pt-2 border-top">
                <span class="badge badge-purple font-weight-bold"><?= (int) ($stats['awaitingAssignment'] ?? 0) ?> Needs Evaluators</span>
                <span class="badge badge-info font-weight-bold"><?= (int) ($stats['pendingAssessments'] ?? 0) ?> Pending</span>
            </div>
        </div>
    </div>

    <!-- Decision Ready Card -->
    <div class="col-xl-3 col-md-6">
        <div class="card h-100 border-0 shadow-sm rounded-lg p-3 position-relative overflow-hidden" style="border-left: 4px solid #10B981 !important; background: #ffffff;">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <span class="text-uppercase text-muted font-weight-bold" style="font-size: 11px; letter-spacing: 0.5px;">Decision Ready</span>
                    <div class="h2 font-weight-bold m-0 text-dark mt-1"><?= count($priorityActions['readyForDecision'] ?? []) ?></div>
                </div>
                <div class="p-2 rounded-circle" style="background: rgba(16, 185, 129, 0.1); color: #10B981;">
                    <i class="fas fa-balance-scale fa-lg"></i>
                </div>
            </div>
            <div class="text-muted small mt-2 pt-2 border-top">
                <span class="badge badge-green font-weight-bold"><i class="fas fa-check-double mr-1"></i> ≥ 2 Submissions Met</span>
            </div>
        </div>
    </div>

    <!-- Forwarded to University Card -->
    <div class="col-xl-3 col-md-6">
        <div class="card h-100 border-0 shadow-sm rounded-lg p-3 position-relative overflow-hidden" style="border-left: 4px solid #8B5CF6 !important; background: #ffffff;">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <span class="text-uppercase text-muted font-weight-bold" style="font-size: 11px; letter-spacing: 0.5px;">Forwarded to University</span>
                    <div class="h2 font-weight-bold m-0 text-dark mt-1"><?= (int) ($stats['forwardedToUniversity'] ?? 0) ?></div>
                </div>
                <div class="p-2 rounded-circle" style="background: rgba(139, 92, 246, 0.1); color: #8B5CF6;">
                    <i class="fas fa-paper-plane fa-lg"></i>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 mt-2 pt-2 border-top">
                <span class="badge badge-green font-weight-bold"><?= (int) ($stats['collegeApproved'] ?? 0) ?> Approved (≥ 50%)</span>
                <span class="badge badge-danger font-weight-bold"><?= (int) ($stats['collegeRejected'] ?? 0) ?> Rejected</span>
            </div>
        </div>
    </div>
</div>

<!-- Priority Action 1: Ready for Decision (Minimum 2 Submissions Rule) -->
<?php if (!empty($priorityActions['readyForDecision'])): ?>
<div class="card border-0 shadow-sm rounded-lg mb-4" style="border-left: 4px solid #10B981 !important;">
    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
        <div>
            <h2 class="h5 font-weight-bold text-dark m-0"><i class="fas fa-bolt text-warning mr-2"></i> Minimum Submission Quorum Met (Ready for Calculation)</h2>
            <div class="text-muted small">These proposals have reached at least 2 submitted evaluations. The system automatically computes the average of submitted assessments and applies the 50% pass threshold.</div>
        </div>
        <span class="badge badge-green px-3 py-1 font-weight-bold"><?= count($priorityActions['readyForDecision']) ?> Ready</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light">
                <tr>
                    <th class="py-2">Proposal</th>
                    <th class="py-2">Principal Researcher</th>
                    <th class="py-2 text-center">Submitted / Pending</th>
                    <th class="py-2 text-center">Calculated Average</th>
                    <th class="py-2 text-center">Result (50% Threshold)</th>
                    <th class="py-2 text-right">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($priorityActions['readyForDecision'] as $app): ?>
                <?php $isPass = ((float)$app['avg_score'] >= 50.0); ?>
                <tr>
                    <td class="py-2">
                        <strong class="text-purple"><?= e($app['application_number']) ?></strong><br>
                        <a href="<?= e(url('action=application_view&id=' . $app['id'])) ?>" class="font-weight-bold text-dark">
                            <?= e($app['title']) ?>
                        </a>
                    </td>
                    <td class="py-2 font-weight-bold"><?= e($app['pi_name']) ?></td>
                    <td class="py-2 text-center">
                        <span class="badge badge-green"><?= (int) $app['completed_count'] ?> submitted</span>
                        <?php if ((int)$app['pending_count'] > 0): ?>
                            <span class="badge badge-amber"><?= (int) $app['pending_count'] ?> pending</span>
                        <?php endif; ?>
                    </td>
                    <td class="py-2 text-center">
                        <strong class="h5 font-weight-bold m-0" style="color: <?= $isPass ? '#10B981' : '#EF4444' ?>;">
                            <?= number_format((float) $app['avg_score'], 2) ?>%
                        </strong>
                    </td>
                    <td class="py-2 text-center">
                        <?php if ($isPass): ?>
                            <span class="badge badge-green font-weight-bold">PASS (≥ 50%)</span>
                        <?php else: ?>
                            <span class="badge badge-danger font-weight-bold">REJECTED (&lt; 50%)</span>
                        <?php endif; ?>
                    </td>
                    <td class="py-2 text-right">
                        <a class="btn btn-primary btn-sm font-weight-bold" href="<?= e(url('action=application_view&id=' . $app['id'])) ?>">
                            Review & Forward &rarr;
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Priority Action 2: Screening Needed & Evaluator Assignment Grids -->
<div class="row g-4 mb-4">
    <!-- Needs Screening Column -->
    <div class="col-lg-6">
        <div class="card h-100 border-0 shadow-sm rounded-lg">
            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="h5 font-weight-bold text-dark m-0"><i class="fas fa-search text-purple mr-2"></i> Initial Screening Queue</h2>
                    <p class="text-muted small m-0">New submissions awaiting eligibility verification</p>
                </div>
                <span class="badge badge-amber"><?= count($priorityActions['screeningNeeded'] ?? []) ?></span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="py-2">Proposal</th>
                            <th class="py-2">PI</th>
                            <th class="py-2">Submitted</th>
                            <th class="py-2 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($priorityActions['screeningNeeded'])): ?>
                            <tr><td colspan="4" class="text-center py-4 text-muted">Screening queue is clear.</td></tr>
                        <?php else: ?>
                            <?php foreach ($priorityActions['screeningNeeded'] as $app): ?>
                            <tr>
                                <td class="py-2">
                                    <strong class="text-purple"><?= e($app['application_number']) ?></strong><br>
                                    <small class="text-muted"><?= e(mb_strimwidth($app['title'], 0, 45, '…')) ?></small>
                                </td>
                                <td class="py-2 font-weight-bold"><?= e($app['pi_name']) ?></td>
                                <td class="py-2 small text-muted" style="white-space:nowrap;"><?= e(format_date($app['submitted_at'])) ?></td>
                                <td class="py-2 text-right">
                                    <a class="btn btn-outline btn-sm font-weight-bold" href="<?= e(url('action=application_view&id=' . $app['id'])) ?>">Screen</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Needs Evaluators Column -->
    <div class="col-lg-6">
        <div class="card h-100 border-0 shadow-sm rounded-lg">
            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="h5 font-weight-bold text-dark m-0"><i class="fas fa-user-check text-purple mr-2"></i> Evaluator Assignment Needed</h2>
                    <p class="text-muted small m-0">Screened proposals requiring college faculty evaluators</p>
                </div>
                <span class="badge badge-purple"><?= count($priorityActions['needsEvaluators'] ?? []) ?></span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="py-2">Proposal</th>
                            <th class="py-2">Assigned</th>
                            <th class="py-2 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($priorityActions['needsEvaluators'])): ?>
                            <tr><td colspan="3" class="text-center py-4 text-muted">No proposals currently pending evaluator assignment.</td></tr>
                        <?php else: ?>
                            <?php foreach ($priorityActions['needsEvaluators'] as $app): ?>
                            <tr>
                                <td class="py-2">
                                    <strong class="text-purple"><?= e($app['application_number']) ?></strong><br>
                                    <small class="text-muted"><?= e(mb_strimwidth($app['title'], 0, 45, '…')) ?></small>
                                </td>
                                <td class="py-2"><span class="badge badge-amber"><?= (int) $app['assigned_count'] ?> evaluators</span></td>
                                <td class="py-2 text-right">
                                    <a class="btn btn-primary btn-sm font-weight-bold" href="<?= e(url('action=assign_evaluators&id=' . $app['id'])) ?>">Assign Evaluators</a>
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

<!-- College Evaluator Workload & Availability Directory -->
<div class="card border-0 shadow-sm rounded-lg">
    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
        <div>
            <h2 class="h5 font-weight-bold text-dark m-0"><i class="fas fa-users-cog text-purple mr-2"></i> College Evaluators Workload Directory</h2>
            <div class="text-muted small">Registered faculty evaluators within your college and their active review loads</div>
        </div>
        <span class="badge badge-purple px-2 py-1"><?= count($collegeEvaluators ?? []) ?> Registered</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light">
                <tr>
                    <th class="py-2">Faculty Evaluator</th>
                    <th class="py-2">Department</th>
                    <th class="py-2">Current Active Review Workload</th>
                    <th class="py-2 text-center">Availability Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($collegeEvaluators)): ?>
                    <tr><td colspan="4" class="text-center py-4 text-muted">No faculty registered as evaluators in this college.</td></tr>
                <?php else: ?>
                    <?php foreach ($collegeEvaluators as $ev): ?>
                    <tr>
                        <td class="py-2 font-weight-bold text-dark"><?= e($ev['full_name']) ?></td>
                        <td class="py-2 text-muted"><?= e($ev['department_name'] ?? 'General') ?></td>
                        <td class="py-2">
                            <?php $load = (int) ($ev['active_assignments'] ?? 0); ?>
                            <?php if ($load >= 4): ?>
                                <span class="badge badge-danger"><?= $load ?> active reviews (High)</span>
                            <?php elseif ($load >= 2): ?>
                                <span class="badge badge-amber"><?= $load ?> active reviews (Moderate)</span>
                            <?php else: ?>
                                <span class="badge badge-green"><?= $load ?> active reviews (Available)</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-2 text-center">
                            <?php if (($ev['availability_status'] ?? 'available') === 'available'): ?>
                                <span class="badge badge-green font-weight-bold">Available</span>
                            <?php else: ?>
                                <span class="badge badge-danger font-weight-bold">Unavailable</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include APP_PATH . '/views/partials/footer.php'; ?>
