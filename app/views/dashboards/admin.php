<?php 
$title = 'Administrator Dashboard'; 
include APP_PATH . '/views/partials/header.php'; 
?>

<!-- Enhanced Page Header -->
<div class="page-head mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <span class="badge badge-purple font-weight-bold px-2 py-1"><i class="fas fa-shield-alt mr-1"></i> Admin Portal</span>
            <span class="text-muted small">Madda Walabu University</span>
        </div>
        <h1 class="page-title h3 font-weight-bold m-0 text-dark">Institutional System Administration</h1>
        <p class="text-muted small m-0 mt-1">High-level institutional governance, college infrastructure, and security audit telemetry.</p>
    </div>
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <a class="btn btn-primary shadow-sm" href="<?= e(url('action=user_form')) ?>">
            <i class="fas fa-user-plus mr-1"></i> Create User Account
        </a>
        <a class="btn btn-outline shadow-sm" href="<?= e(url('action=colleges')) ?>">
            <i class="fas fa-university mr-1"></i> Manage Colleges
        </a>
        <a class="btn btn-outline shadow-sm" href="<?= e(url('action=audit')) ?>">
            <i class="fas fa-history mr-1"></i> Audit Logs
        </a>
    </div>
</div>

<!-- Primary Stats Grid with Enhanced CSS & Tokens -->
<div class="row g-3 mb-4">
    <!-- Total Users Card -->
    <div class="col-xl-3 col-md-6">
        <div class="card h-100 border-0 shadow-sm rounded-lg p-3 position-relative overflow-hidden" style="border-left: 4px solid #594FB6 !important; background: #ffffff;">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <span class="text-uppercase text-muted font-weight-bold" style="font-size: 11px; letter-spacing: 0.5px;">Total Users</span>
                    <div class="h2 font-weight-bold m-0 text-dark mt-1"><?= (int) ($stats['total_users'] ?? 0) ?></div>
                </div>
                <div class="p-2 rounded-circle" style="background: rgba(89, 79, 182, 0.1); color: #594FB6;">
                    <i class="fas fa-users fa-lg"></i>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 mt-2 pt-2 border-top">
                <span class="badge badge-green font-weight-bold"><i class="fas fa-check-circle mr-1"></i> <?= (int) ($stats['active_users'] ?? 0) ?> Active</span>
                <span class="badge badge-danger font-weight-bold"><?= (int) ($stats['inactive_users'] ?? 0) ?> Inactive</span>
            </div>
        </div>
    </div>

    <!-- Institutional Structure Card -->
    <div class="col-xl-3 col-md-6">
        <div class="card h-100 border-0 shadow-sm rounded-lg p-3 position-relative overflow-hidden" style="border-left: 4px solid #3B82F6 !important; background: #ffffff;">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <span class="text-uppercase text-muted font-weight-bold" style="font-size: 11px; letter-spacing: 0.5px;">Institutional Structure</span>
                    <div class="h2 font-weight-bold m-0 text-dark mt-1"><?= (int) ($stats['colleges'] ?? 0) ?></div>
                </div>
                <div class="p-2 rounded-circle" style="background: rgba(59, 130, 246, 0.1); color: #3B82F6;">
                    <i class="fas fa-landmark fa-lg"></i>
                </div>
            </div>
            <div class="text-muted small mt-2 pt-2 border-top">
                Colleges across <strong><?= (int) ($stats['depts'] ?? 0) ?></strong> Academic Departments
            </div>
        </div>
    </div>

    <!-- Faculty & Researchers Card -->
    <div class="col-xl-3 col-md-6">
        <div class="card h-100 border-0 shadow-sm rounded-lg p-3 position-relative overflow-hidden" style="border-left: 4px solid #10B981 !important; background: #ffffff;">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <span class="text-uppercase text-muted font-weight-bold" style="font-size: 11px; letter-spacing: 0.5px;">Faculty & Researchers</span>
                    <div class="h2 font-weight-bold m-0 text-dark mt-1"><?= (int) ($stats['total_researchers'] ?? 0) ?></div>
                </div>
                <div class="p-2 rounded-circle" style="background: rgba(16, 185, 129, 0.1); color: #10B981;">
                    <i class="fas fa-graduation-cap fa-lg"></i>
                </div>
            </div>
            <div class="text-muted small mt-2 pt-2 border-top">
                Registered Principal Investigators & Evaluators
            </div>
        </div>
    </div>

    <!-- Research Pipeline Card -->
    <div class="col-xl-3 col-md-6">
        <div class="card h-100 border-0 shadow-sm rounded-lg p-3 position-relative overflow-hidden" style="border-left: 4px solid #F59E0B !important; background: #ffffff;">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <span class="text-uppercase text-muted font-weight-bold" style="font-size: 11px; letter-spacing: 0.5px;">Research Pipeline</span>
                    <div class="h2 font-weight-bold m-0 text-dark mt-1"><?= (int) ($stats['total_proposals'] ?? 0) ?></div>
                </div>
                <div class="p-2 rounded-circle" style="background: rgba(245, 158, 11, 0.1); color: #F59E0B;">
                    <i class="fas fa-microscope fa-lg"></i>
                </div>
            </div>
            <div class="text-muted small mt-2 pt-2 border-top">
                Proposals in <strong><?= (int) ($stats['total_calls'] ?? 0) ?></strong> Calls (<strong><?= (int) ($stats['active_projects'] ?? 0) ?></strong> Active Projects)
            </div>
        </div>
    </div>
</div>

<!-- Secondary Detail Grids (Colleges & 4 Main Roles) -->
<div class="row g-4 mb-4">
    <!-- Colleges Breakdown Table (Left) -->
    <div class="col-lg-8">
        <div class="card h-100 border-0 shadow-sm rounded-lg">
            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="h5 font-weight-bold text-dark m-0"><i class="fas fa-university text-purple mr-2"></i> College & Department Distribution</h2>
                    <p class="text-muted small m-0">Active colleges, department numbers, and proposal allocations.</p>
                </div>
                <a class="btn btn-outline btn-sm font-weight-bold" href="<?= e(url('action=colleges')) ?>">
                    View All Colleges &rarr;
                </a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="py-2">College / School</th>
                            <th class="py-2">Code</th>
                            <th class="py-2 text-center">Departments</th>
                            <th class="py-2 text-center">Affiliated Users</th>
                            <th class="py-2 text-center">Proposals</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($collegeBreakdown)): ?>
                            <tr><td colspan="5" class="text-center py-4 text-muted">No colleges registered.</td></tr>
                        <?php else: ?>
                            <?php foreach ($collegeBreakdown as $c): ?>
                            <tr>
                                <td class="py-2 font-weight-bold text-dark"><?= e($c['name']) ?></td>
                                <td class="py-2"><span class="badge badge-purple"><?= e($c['code']) ?></span></td>
                                <td class="py-2 text-center"><?= (int) $c['dept_count'] ?></td>
                                <td class="py-2 text-center"><?= (int) $c['user_count'] ?></td>
                                <td class="py-2 text-center">
                                    <span class="badge <?= ((int) $c['app_count']) > 0 ? 'badge-green' : 'badge-outline' ?>">
                                        <?= (int) $c['app_count'] ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- 4 Main Actors Role Breakdown Card (Right) -->
    <div class="col-lg-4">
        <div class="card h-100 border-0 shadow-sm rounded-lg">
            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="h5 font-weight-bold text-dark m-0"><i class="fas fa-user-tag text-purple mr-2"></i> Primary System Actors</h2>
                    <p class="text-muted small m-0">The 4 institutional RMS roles</p>
                </div>
                <a class="btn btn-outline btn-sm font-weight-bold" href="<?= e(url('action=users')) ?>">
                    Manage Users
                </a>
            </div>
            <div class="p-3">
                <div class="d-flex flex-column gap-3">
                    <?php 
                    $roleMeta = [
                        'system_admin' => ['icon' => 'fa-user-shield', 'color' => '#EF4444', 'title' => 'Admin', 'desc' => 'System administration & configuration'],
                        'rpee_director' => ['icon' => 'fa-crown', 'color' => '#594FB6', 'title' => 'Research Director', 'desc' => 'University research calls & themes'],
                        'college_coordinator' => ['icon' => 'fa-tasks', 'color' => '#3B82F6', 'title' => 'College Research Coordinator', 'desc' => 'College screening & evaluator assignments'],
                        'researcher' => ['icon' => 'fa-feather-alt', 'color' => '#10B981', 'title' => 'Researcher', 'desc' => 'Proposal submission & peer evaluation'],
                    ];
                    ?>
                    <?php foreach ($roles as $r): ?>
                        <?php 
                        $meta = $roleMeta[$r['name']] ?? ['icon' => 'fa-user', 'color' => '#6B7280', 'title' => $r['display_name'] ?? $r['name'], 'desc' => $r['name']]; 
                        ?>
                        <div class="p-3 rounded border bg-light d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-3">
                                <div class="p-2 rounded-circle text-white d-flex align-items-center justify-content-center" style="width: 38px; height: 38px; background: <?= e($meta['color']) ?>;">
                                    <i class="fas <?= e($meta['icon']) ?>"></i>
                                </div>
                                <div>
                                    <div class="font-weight-bold text-dark"><?= e($meta['title']) ?></div>
                                    <small class="text-muted d-block" style="font-size: 11px;"><?= e($meta['desc']) ?></small>
                                </div>
                            </div>
                            <div>
                                <span class="badge badge-purple px-2 py-1 font-weight-bold" style="font-size: 14px;">
                                    <?= (int) $r['cnt'] ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Administrative System Activities (Audit Trail) -->
<div class="card border-0 shadow-sm rounded-lg">
    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
        <div>
            <h2 class="h5 font-weight-bold text-dark m-0"><i class="fas fa-shield-alt text-purple mr-2"></i> Recent System Audit Trail</h2>
            <div class="text-muted small">Live trace of system actions, authentication events, and workflow transitions</div>
        </div>
        <a class="btn btn-outline btn-sm font-weight-bold" href="<?= e(url('action=audit')) ?>">
            Open Full Audit Logs &rarr;
        </a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light">
                <tr>
                    <th class="py-2">Timestamp</th>
                    <th class="py-2">Action</th>
                    <th class="py-2">Entity Type</th>
                    <th class="py-2">Actor</th>
                    <th class="py-2">Details</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentLogs)): ?>
                    <tr><td colspan="5" class="text-center py-4 text-muted">No recent audit activities logged.</td></tr>
                <?php else: ?>
                    <?php foreach ($recentLogs as $log): ?>
                    <tr>
                        <td class="py-2 small text-muted" style="white-space:nowrap;">
                            <i class="far fa-clock mr-1"></i> <?= e(format_datetime($log['created_at'])) ?>
                        </td>
                        <td class="py-2">
                            <?php 
                            $act = strtolower($log['action'] ?? '');
                            $badgeClass = 'badge-info';
                            if (str_contains($act, 'login')) $badgeClass = 'badge-green';
                            elseif (str_contains($act, 'logout')) $badgeClass = 'badge-outline';
                            elseif (str_contains($act, 'create') || str_contains($act, 'approve')) $badgeClass = 'badge-purple';
                            elseif (str_contains($act, 'reject') || str_contains($act, 'fail') || str_contains($act, 'delete')) $badgeClass = 'badge-danger';
                            ?>
                            <span class="badge <?= $badgeClass ?> font-weight-bold"><?= e($log['action']) ?></span>
                        </td>
                        <td class="py-2"><code><?= e($log['entity_type'] ?? 'system') ?></code></td>
                        <td class="py-2 font-weight-bold text-dark"><?= e($log['actor_name'] ?? 'System') ?></td>
                        <td class="py-2 small text-muted"><?= e($log['notes'] ?? '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include APP_PATH . '/views/partials/footer.php'; ?>
