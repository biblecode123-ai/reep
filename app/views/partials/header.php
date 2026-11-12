<?php
/** Shared page header. Expects: $title (string), optional $subtitle.
 * Layout: logged-in pages use a left sidebar; guests (login/forgot/reset) get a
 * full-page layout with no navigation at all. */
use App\Core\Auth;
use App\Core\Gate;
use App\Services\NotificationService;
$unreadCount = Auth::check() ? NotificationService::unreadCount(Auth::id()) : 0;
$authUser = Auth::user();
$currentAction = (string) ($_GET['action'] ?? '');
$navMap = [
    'director_dashboard'   => ['Director Dashboard', 'research.manage'],
    'applications'   => ['Research', 'research.view'],
    'themes'         => ['Themes', 'research.manage'],
    'ethics'         => ['Ethics', 'ethics.view'],
    'projects'       => ['Projects', 'project.view'],
    'calls_public'   => ['Calls', 'research.create'],
    'my_evaluations' => ['My Evaluations', 'research.evaluate'],
    'researchers'   => ['Researchers', 'researcher.view'],
    'committees'     => ['Committees', 'committee.manage'],
    'repository'     => ['Repository', 'repository.view'],
    'users'          => ['Users', 'user.view'],
    'colleges'       => ['College', 'org.view'],
    'settings'       => ['Settings', 'settings.manage'],
    'audit'          => ['Audit', 'audit.view'],
];
// The system administrator's workspace is deliberately minimal:
// manage users and manage the institutional research repository.
if (Gate::isAdmin()) {
    $navMap = [
        'users'      => ['Users', 'user.view'],
        'repository' => ['Repository', 'repository.view'],
    ];
}
// Show ONLY dashboard to researchers who don't have other roles.
if (Gate::hasRole('researcher') && Gate::denies('research.manage') && !Gate::hasRole('college_coordinator')) {
    $navMap = [
        'researcher_dashboard' => ['Dashboard', 'researcher.view'],
        'applications'   => ['My Proposals', 'research.view'],
        'calls_public'   => ['Research Calls', 'research.create'],
        'my_evaluations' => ['My Evaluations', 'research.evaluate'],
        'repository'     => ['Repository', 'repository.view'],
    ];
} elseif (Gate::hasRole('rpee_director')) {
    $navMap = [
        'director_dashboard' => ['Director Dashboard', 'research.manage'],
        'applications'   => ['Research', 'research.view'],
        'themes'         => ['Themes', 'research.manage'],
        'ethics'         => ['Ethics', 'ethics.view'],
        'projects'       => ['Projects', 'project.view'],
        'calls_public'   => ['Calls', 'research.create'],
        'researchers'   => ['Researchers', 'researcher.view'],
        'committees'     => ['Committees', 'committee.manage'],
        'repository'     => ['Repository', 'repository.view'],
        'users'          => ['Users', 'user.view'],
        'colleges'       => ['College', 'org.view'],
        'settings'       => ['Settings', 'settings.manage'],
        'audit'          => ['Audit', 'audit.view'],
    ];
} elseif (Gate::hasRole('college_coordinator')) {
    $navMap = [
        'coordinator_dashboard' => ['Dashboard', 'research.view'],
        'applications' => ['Proposals', 'research.view'],
        'projects'     => ['Projects', 'project.view'],
        'calls_public' => ['Research Calls', 'research.view'],
        'my_evaluations' => ['My Evaluations', 'research.evaluate'],
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?= csrf_meta() ?>
<title><?= e($title ?? 'MWU RPEE') ?> · <?= e(\App\Core\Settings::get('university_short_name', 'MWU')) ?> <?= e(\App\Core\Settings::get('office_abbreviation', 'RPEE')) ?></title>
<link rel="stylesheet" href="<?= e(base_url('assets/css/app.css')) ?>">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="<?= Auth::check() ? 'app-shell' : e($bodyClass ?? '') ?>">
<?php if (Auth::check()): ?>
<button type="button" class="menu-fab" id="navToggle" aria-label="Toggle navigation" aria-expanded="false">☰</button>
<aside class="sidebar" id="sidebar">
  <a class="side-brand" href="<?= e(url('')) ?>">
    <span class="brand-mark">RW</span>
    <span class="side-brand-text">
      <strong><?= e(\App\Core\Settings::get('university_name', 'Madda Walabu University')) ?></strong>
      <small><?= e(\App\Core\Settings::get('office_name', 'Research, Publication, Ethics and Extension Office')) ?></small>
    </span>
  </a>
  <nav class="side-nav">
    <?php foreach ($navMap as $navAction => [$label, $perm]): ?>
      <?php if (Gate::allows($perm)): ?>
        <a href="<?= e(url('action=' . $navAction)) ?>" class="<?= $currentAction === $navAction
          || ($navAction === 'applications' && in_array($currentAction, ['application_view', 'application_form', 'calls'], true))
          || ($navAction === 'my_evaluations' && in_array($currentAction, ['evaluation_form', 'evaluation_view', 'univ_evaluation_form'], true))
          || ($navAction === 'ethics' && str_starts_with($currentAction, 'ethics'))
          || ($navAction === 'repository' && str_starts_with($currentAction, 'repository'))
          || ($navAction === 'users' && str_starts_with($currentAction, 'user'))
          || ($navAction === 'researchers' && in_array($currentAction, ['researchers', 'researcher_view'], true))
          ? 'active' : '' ?>"><?= e($label) ?></a>
      <?php endif; ?>
    <?php endforeach; ?>
  </nav>
  <div class="side-user">
    <a class="bell" href="<?= e(url('action=notifications')) ?>" title="Notifications">
      🔔<?php if ($unreadCount > 0): ?><span class="bell-count"><?= e((string) $unreadCount) ?></span><?php endif; ?>
    </a>
    <span class="user-chip" title="<?= e($authUser['email'] ?? '') ?>"><?= e($authUser['full_name'] ?? '') ?></span>
    <div class="side-user-actions">
      <a class="btn btn-ghost btn-sm" href="<?= e(url('action=profile')) ?>">Profile</a>
      <a class="btn btn-ghost btn-sm" href="<?= e(base_url('logout.php')) ?>">Logout</a>
    </div>
  </div>
</aside>
<div class="nav-backdrop" id="navBackdrop"></div>
<div class="app-content">
<?php endif; ?>
<main class="container">
<?= flash_html() ?>
<?php if (!empty($mustChangePassword) || (Auth::check() && (int) Auth::user()['must_change_password'] === 1)): ?>
  <div class="alert alert-warning">You are using an initial password. Please change it on your <a href="<?= e(url('action=profile')) ?>">profile page</a>.</div>
<?php endif; ?>

