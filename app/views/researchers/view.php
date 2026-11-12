<?php
use App\Core\Csrf;

$title = 'Researcher profile';
include APP_PATH . '/views/partials/header.php';

$interests = $profile['research_interests'] ? array_filter(array_map('trim', explode(',', (string) $profile['research_interests']))) : [];
$areas = $profile['research_areas'] ? array_filter(array_map('trim', explode(',', (string) $profile['research_areas']))) : [];
$statusBadge = ['ACTIVE' => 'badge-success', 'PROGRESS_MONITORING' => 'badge-primary', 'COMPLETED' => 'badge-success', 'FINAL_REPORT_SUBMITTED' => 'badge-info'];
?>
<div class="page-head">
  <div>
    <h1><?= e($profile['full_name']) ?></h1>
    <p>Researcher profile · <?= e($profile['college_name'] ?? '—') ?> · <?= e($profile['department_name'] ?? '—') ?></p>
  </div>
  <div>
    <?php if ($isSelf): ?><a class="btn btn-outline" href="<?= e(url('action=profile')) ?>">Edit my profile</a><?php endif; ?>
    <a class="btn btn-outline" href="<?= e(url('action=researchers')) ?>">Back to directory</a>
  </div>
</div>

<div class="grid grid-2">
  <div>
    <div class="card">
      <h2 class="mt0">Profile</h2>
      <?php if ($isSelf): ?>
      <p class="muted" style="font-size:13px;">Profile completion: <strong><?= (int) $completion ?>%</strong></p>
      <?php endif; ?>
      <table class="tbl">
        <tr><th>Photo</th><td>
          <?php if ($photo): ?>
          <img src="<?= e(url('action=researcher_photo&user_id=' . (int) $profile['id'])) ?>" alt="Profile photo" style="width:96px;height:96px;object-fit:cover;border-radius:8px;">
          <?php else: ?><span class="muted">No photo yet</span><?php endif; ?>
        </td></tr>
        <tr><th>Staff ID</th><td><?= e($profile['employee_id'] ?? '—') ?></td></tr>
        <tr><th>Academic rank</th><td><?= e($profile['academic_position'] ?? '—') ?></td></tr>
        <tr><th>Qualification</th><td><?= e($profile['qualification'] ?? '—') ?></td></tr>
        <tr><th>Specialization</th><td><?= e($profile['specialization'] ?? '—') ?></td></tr>
        <?php if ($canSeeContact): ?><tr><th>Email</th><td><?= e($profile['email']) ?></td></tr><?php endif; ?>
        <?php if ($canSeeContact): ?><tr><th>Phone</th><td><?= e($profile['phone'] ?? '—') ?></td></tr><?php endif; ?>
        <?php if ($canSeeContact): ?><tr><th>Gender</th><td><?= e($profile['gender'] ?? '—') ?></td></tr><?php endif; ?>
        <tr><th>ORCID</th><td><?= $profile['orcid'] ? '<a href="https://orcid.org/' . e($profile['orcid']) . '" target="_blank" rel="noopener">' . e($profile['orcid']) . '</a>' : '—' ?></td></tr>
        <tr><th>Google Scholar</th><td><?= $profile['google_scholar'] ? '<a href="' . e($profile['google_scholar']) . '" target="_blank" rel="noopener">' . e($profile['google_scholar']) . '</a>' : '—' ?></td></tr>
        <tr><th>Member since</th><td><?= e(format_date($profile['created_at'] ?? null)) ?></td></tr>
      </table>
    </div>

    <div class="card">
      <h2 class="mt0">Research profile</h2>
      <h3>Research interests</h3>
      <?php if ($interests): ?><p><?= e(implode(' · ', $interests)) ?></p><?php else: ?><p class="muted">Not provided yet.</p><?php endif; ?>
      <h3>Research areas</h3>
      <?php if ($areas): ?><p><?= e(implode(' · ', $areas)) ?></p><?php else: ?><p class="muted">Not provided yet.</p><?php endif; ?>
      <h3>Biography</h3>
      <p><?= $profile['biography'] ? nl2br(e($profile['biography'])) : '<span class="muted">Not provided yet.</span>' ?></p>
    </div>
  </div>

  <div>
    <div class="card">
      <h2 class="mt0">Research history</h2>
      <p class="muted" style="font-size:13px;"><?= (int) $stats['projects'] ?> project(s) · <?= (int) $stats['active'] ?> active · <?= (int) $stats['completed'] ?> completed</p>
      <?php if (!$projects): ?><p class="muted">No project memberships.</p><?php endif; ?>
      <table class="tbl">
        <?php foreach ($projects as $pr): ?>
        <tr>
          <td><strong><?= e($pr['project_number']) ?></strong><br><span class="muted"><?= e(mb_strimwidth($pr['title'], 0, 55, '…')) ?></span></td>
          <td><?= e(ucfirst(str_replace('_', ' ', $pr['member_role']))) ?></td>
          <td><span class="badge <?= e($statusBadge[$pr['status_code']] ?? 'badge-secondary') ?>"><?= e(status_label($pr['status_code'])) ?></span></td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>

    <div class="card">
      <h2 class="mt0">Publications</h2>
      <?php if (!$publications): ?><p class="muted">No publications recorded.</p><?php endif; ?>
      <table class="tbl">
        <?php foreach ($publications as $pub): ?>
        <tr><td><?= e(mb_strimwidth($pub['title'], 0, 60, '…')) ?><br><span class="muted"><?= e($pub['journal_name'] ?? '') ?> <?= e((string) ($pub['publication_year'] ?? '')) ?></span></td>
        <td><span class="badge badge-secondary"><?= e(ucfirst(str_replace('_', ' ', $pub['status']))) ?></span></td></tr>
        <?php endforeach; ?>
      </table>
    </div>

    <div class="card">
      <h2 class="mt0">Outputs & repository</h2>
      <?php if (!$outputs && !$repoRecords): ?><p class="muted">No outputs or repository records.</p><?php endif; ?>
      <table class="tbl">
        <?php foreach ($outputs as $o): ?>
        <tr><td><?= e(mb_strimwidth($o['title'], 0, 55, '…')) ?><br><span class="muted"><?= e(ucfirst(str_replace('_', ' ', $o['output_type']))) ?> · <?= e($o['project_number']) ?></span></td>
        <td><span class="badge badge-secondary"><?= e(ucfirst($o['status'])) ?></span></td></tr>
        <?php endforeach; ?>
        <?php foreach ($repoRecords as $rr): ?>
        <tr><td><code><?= e($rr['repository_id_code']) ?></code> <?= e(mb_strimwidth($rr['title'], 0, 45, '…')) ?></td>
        <td><span class="badge badge-secondary"><?= e(ucfirst($rr['status'])) ?></span></td></tr>
        <?php endforeach; ?>
      </table>
      <p class="muted" style="font-size:12px;margin-top:8px;">Research documents and datasets follow their own access rules — profile visibility does not grant document access.</p>
    </div>
  </div>
</div>

<?php include APP_PATH . '/views/partials/footer.php'; ?>
