<?php
/** Shared page header. Expects: $title (string), optional $subtitle. */
use App\Core\Auth;
use App\Core\Gate;
use App\Services\NotificationService;

$unreadCount = Auth::check() ? NotificationService::unreadCount(Auth::id()) : 0;
$authUser = Auth::user();
$currentAction = (string) ($_GET['action'] ?? '');
$currentFilter = (string) ($_GET['filter'] ?? '');

// Build actor-specific navigation adhering to finalized requirements
$navSections = [];

if (Auth::check()) {
    if (Gate::isAdmin()) {
        $navSections = [
            'General' => [
                ['index.php', 'Dashboard', '📊', true],
                ['index.php?action=users', 'User Management', '👥', Gate::allows('user.view')],
                ['index.php?action=colleges', 'Institutional Structure', '🏛️', Gate::allows('org.view')],
                ['index.php?action=applications', 'Research Overview', '🔬', Gate::allows('research.view')],
            ],
            'System' => [
                ['index.php?action=reports', 'Reports & Analytics', '📈', Gate::allows('report.view')],
                ['index.php?action=settings', 'System Configuration', '⚙️', Gate::allows('settings.manage')],
                ['index.php?action=audit', 'Audit Logs', '🛡️', Gate::allows('audit.view')],
                ['index.php?action=profile', 'My Profile', '👤', true],
            ]
        ];
    } elseif (Gate::hasRole('rpee_director') || Gate::allows('research.manage')) {
        $navSections = [
            'Executive' => [
                ['index.php?action=director_dashboard', 'Dashboard', '📊', true],
                ['index.php?action=themes', 'Research Themes', '🏷️', true],
                ['index.php?action=calls', 'Research Calls', '📢', true],
                ['index.php?action=rubrics', 'Evaluation Framework', '📋', true],
            ],
            'Evaluation & Committee' => [
                ['index.php?action=committees', 'Permanent Committee', '🏛️', true],
                ['index.php?action=applications', 'Proposal Monitoring', '🔬', true],
                ['index.php?action=committee_queue', 'University Evaluations', '⚖️', true],
            ],
            'Overview' => [
                ['index.php?action=reports', 'Reports & Analytics', '📈', Gate::allows('report.view')],
                ['index.php?action=notifications', 'Notifications', '🔔', true, $unreadCount],
                ['index.php?action=profile', 'My Profile', '👤', true],
            ]
        ];
    } elseif (Gate::hasRole('college_coordinator')) {
        $navSections = [
            'Operations' => [
                ['index.php?action=coordinator_dashboard', 'Dashboard', '📊', true],
                ['index.php?action=coordinator_proposals', 'College Proposals', '📑', true],
                ['index.php?action=coordinator_proposals&filter=screening_needed', 'Screening Queue', '🔍', true],
                ['index.php?action=coordinator_proposals&filter=needs_evaluators', 'Evaluator Assignment', '✍️', true],
                ['index.php?action=coordinator_proposals&filter=under_evaluation', 'Evaluation Monitoring', '⏳', true],
            ],
            'Outcomes' => [
                ['index.php?action=coordinator_proposals&filter=approved', 'College Approved', '✅', true],
                ['index.php?action=coordinator_proposals&filter=rejected', 'College Rejected', '❌', true],
                ['index.php?action=coordinator_proposals&filter=forwarded', 'University Tracking', '🚀', true],
                ['index.php?action=reports', 'Reports', '📈', Gate::allows('report.view')],
                ['index.php?action=notifications', 'Notifications', '🔔', true, $unreadCount],
                ['index.php?action=profile', 'My Profile', '👤', true],
            ]
        ];
    } else {
        // Researcher
        $navSections = [
            'My Workspace' => [
                ['index.php?action=researcher_dashboard', 'Dashboard', '📊', true],
                ['index.php?action=calls_public', 'Research Calls', '📢', true],
                ['index.php?action=application_form', 'Apply for Call', '➕', Gate::allows('research.create')],
                ['index.php?action=applications', 'My Applications', '📁', true],
            ],
            'Evaluations & Teams' => [
                ['index.php?action=my_evaluations', 'My Evaluation Assignments', '📝', Gate::allows('research.evaluate')],
                ['index.php?action=applications&view=collaborations', 'Co-investigators', '🤝', true],
                ['index.php?action=notifications', 'Notifications', '🔔', true, $unreadCount],
                ['index.php?action=profile', 'My Profile', '👤', true],
            ]
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?= csrf_meta() ?>
<title><?= e($title ?? 'MWU REEP') ?> · <?= e(\App\Core\Settings::get('university_short_name', 'MWU')) ?> <?= e(\App\Core\Settings::get('office_abbreviation', 'RPEE')) ?></title>
<link rel="stylesheet" href="<?= e(base_url('assets/css/app.css')) ?>">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="<?= Auth::check() ? 'app-shell' : e($bodyClass ?? '') ?>">
<?php if (Auth::check()): ?>
<aside class="sidebar" id="sidebar">
  <a class="side-brand" href="<?= e(url('')) ?>">
    <span class="brand-mark">MWU</span>
    <span class="side-brand-text">
      <strong><?= e(\App\Core\Settings::get('university_name', 'Madda Walabu University')) ?></strong>
      <small><?= e(\App\Core\Settings::get('office_name', 'Research & Extension Office')) ?></small>
    </span>
  </a>

  <nav class="side-nav">
    <?php foreach ($navSections as $sectionTitle => $items): ?>
      <div class="nav-section-title"><?= e($sectionTitle) ?></div>
      <?php foreach ($items as $item): ?>
        <?php
          [$itemUrl, $itemLabel, $itemIcon, $itemPerm, $itemBadge] = array_pad($item, 5, null);
          if (!$itemPerm) continue;
          
          // Check active state
          $parsedQuery = parse_url($itemUrl, PHP_URL_QUERY) ?? '';
          parse_str($parsedQuery, $params);
          $targetAction = $params['action'] ?? '';
          $targetFilter = $params['filter'] ?? '';
          
          $isActive = ($currentAction === $targetAction) && ($targetFilter === '' || $currentFilter === $targetFilter);
          if ($itemUrl === 'index.php' && $currentAction === '') {
            $isActive = true;
          }
        ?>
        <a href="<?= e(base_url($itemUrl)) ?>" class="<?= $isActive ? 'active' : '' ?>">
          <span class="nav-icon"><?= $itemIcon ?></span>
          <span><?= e($itemLabel) ?></span>
          <?php if (!empty($itemBadge) && $itemBadge > 0): ?>
            <span class="nav-badge"><?= (int) $itemBadge ?></span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </nav>

  <div class="side-user">
    <div class="user-card-mini">
      <div class="user-avatar-circle">
        <?= strtoupper(substr(e($authUser['full_name'] ?? 'U'), 0, 1)) ?>
      </div>
      <div class="user-info-text">
        <span class="user-info-name" title="<?= e($authUser['full_name'] ?? '') ?>"><?= e($authUser['full_name'] ?? '') ?></span>
        <span class="user-info-role"><?= e($authUser['email'] ?? '') ?></span>
      </div>
    </div>
    <div class="side-user-actions">
      <a class="btn btn-secondary btn-sm" style="flex:1;" href="<?= e(url('action=profile')) ?>">Profile</a>
      <a class="btn btn-secondary btn-sm" href="<?= e(base_url('logout.php')) ?>" title="Sign Out">Logout</a>
    </div>
  </div>
</aside>

<div class="nav-backdrop" id="navBackdrop"></div>

<div class="app-content">
  <header class="topbar">
    <div class="topbar-left">
      <button type="button" class="menu-fab" id="navToggle" aria-label="Toggle navigation" aria-expanded="false">☰</button>
      <div class="breadcrumb-trail">
        <a href="<?= e(url('')) ?>">RMS</a>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-current"><?= e($title ?? 'Dashboard') ?></span>
      </div>
    </div>
    <div class="topbar-right">
      <a class="bell-btn" href="<?= e(url('action=notifications')) ?>" title="Notifications">
        🔔
        <?php if ($unreadCount > 0): ?>
          <span class="bell-count"><?= (int) $unreadCount ?></span>
        <?php endif; ?>
      </a>
    </div>
  </header>
<?php endif; ?>

<main class="container">
<?= flash_html() ?>
<?php if (!empty($mustChangePassword) || (Auth::check() && (int) Auth::user()['must_change_password'] === 1)): ?>
  <div class="alert alert-warning">
    <span>⚠️ You are using a temporary initial password. Please update your security credentials on your <a href="<?= e(url('action=profile')) ?>">profile page</a>.</span>
  </div>
<?php endif; ?>
