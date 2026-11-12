<?php
$title = 'Reports';
include APP_PATH . '/views/partials/header.php';
?>
<div class="page-head">
  <div><h1>Institutional Reports</h1></div>
</div>

<ul>
  <li><a href="<?= e(url('action=reporting_export&type=proposals')) ?>">Export Proposals (CSV)</a></li>
  <li><a href="<?= e(url('action=reporting_export&type=projects')) ?>">Export Projects (CSV)</a></li>
</ul>

<?php include APP_PATH . '/views/partials/footer.php'; ?>

