<?php $title = 'My profile'; include APP_PATH . '/views/partials/header.php'; ?>

<div class="page-head"><div><h1>My profile</h1><p>Account and researcher information (§15)</p></div></div>

<div class="grid grid-2">
  <div class="card">
    <h2 class="mt0">Account</h2>
    <dl class="kv">
      <dt>Name</dt><dd><?= e($user['full_name']) ?></dd>
      <dt>Email</dt><dd><?= e($user['email']) ?></dd>
      <dt>Employee ID</dt><dd><?= e($user['employee_id'] ?? '—') ?></dd>
      <dt>College</dt><dd><?= e($user['college_name'] ?? '—') ?></dd>
      <dt>Department</dt><dd><?= e($user['department_name'] ?? '—') ?></dd>
      <dt>Roles</dt><dd><?= e(implode(', ', array_column($roles, 'display_name'))) ?></dd>
      <dt>Last login</dt><dd><?= e(format_datetime($user['last_login_at'])) ?></dd>
    </dl>
    <?php if (\App\Core\Gate::allows('researcher.view')): ?>
    <p><a class="btn btn-outline btn-sm" href="<?= e(url('action=researcher_view&id=' . (int) $user['id'])) ?>">View my researcher profile</a></p>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2 class="mt0">Change password</h2>
    <form method="post" action="<?= e(url('action=profile_password')) ?>">
      <?= \App\Core\Csrf::field() ?>
      <label>Current password <span class="req">*</span></label>
      <input type="password" name="current_password" required autocomplete="current-password">
      <label>New password <span class="req">*</span></label>
      <input type="password" name="new_password" required minlength="8" autocomplete="new-password">
      <label>Confirm new password <span class="req">*</span></label>
      <input type="password" name="confirm_password" required minlength="8" autocomplete="new-password">
      <div class="form-actions"><button class="btn" type="submit">Change password</button></div>
    </form>
  </div>
</div>

<div class="card">
  <h2 class="mt0">Researcher profile (§15)</h2>

  <div style="display:flex;gap:16px;align-items:center;margin-bottom:12px;">
    <?php if (!empty($researcher['photo_ok'])): ?>
    <img src="<?= e(url('action=researcher_photo&user_id=' . (int) $user['id'])) ?>" alt="Profile photo" style="width:80px;height:80px;object-fit:cover;border-radius:8px;">
    <?php else: ?>
    <div style="width:80px;height:80px;border-radius:8px;background:#eee;display:flex;align-items:center;justify-content:center;color:#999;">—</div>
    <?php endif; ?>
    <form method="post" action="<?= e(url('action=researcher_photo_upload')) ?>" enctype="multipart/form-data">
      <?= \App\Core\Csrf::field() ?>
      <label>Profile photo (jpg/png, max <?= (int) \App\Core\Settings::get('max_upload_size_mb', 25) ?> MB)</label>
      <input type="file" name="photo" accept="image/jpeg,image/png" required>
      <button class="btn btn-outline btn-sm" type="submit">Upload photo</button>
    </form>
  </div>

  <?php
  $fieldsCheck = [
      'photo' => !empty($researcher['photo_ok']),
      'rank' => (string) ($researcher['academic_position'] ?? '') !== '',
      'qualification' => (string) ($researcher['qualification'] ?? '') !== '',
      'specialization' => (string) ($researcher['specialization'] ?? '') !== '',
      'interests' => (string) ($researcher['research_interests'] ?? '') !== '',
      'areas' => (string) ($researcher['research_areas'] ?? '') !== '',
      'biography' => (string) ($researcher['biography'] ?? '') !== '',
      'orcid' => (string) ($researcher['orcid'] ?? '') !== '',
      'scholar' => (string) ($researcher['google_scholar'] ?? '') !== '',
  ];
  $completion = (int) round(100 * count(array_filter($fieldsCheck)) / count($fieldsCheck));
  ?>
  <p class="muted" style="font-size:13px;">Profile completion: <strong><?= $completion ?>%</strong> — a complete profile helps evaluators and the directorate assess expertise.</p>

  <form method="post" action="<?= e(url('action=profile_researcher')) ?>">
    <?= \App\Core\Csrf::field() ?>
    <div class="form-row">
      <div><label>Academic position</label><input type="text" name="position" maxlength="100" value="<?= e($researcher['academic_position'] ?? '') ?>"></div>
      <div><label>Qualification</label><input type="text" name="qualification" maxlength="150" value="<?= e($researcher['qualification'] ?? '') ?>"></div>
      <div><label>Specialization</label><input type="text" name="specialization" maxlength="200" value="<?= e($researcher['specialization'] ?? '') ?>"></div>
    </div>
    <div class="form-row">
      <div><label>Gender</label>
        <select name="gender">
          <?php foreach (['' => 'Prefer not to say', 'female' => 'Female', 'male' => 'Male', 'other' => 'Other'] as $gv => $gl): ?>
          <option value="<?= $gv ?>" <?= ($researcher['gender'] ?? '') === $gv ? 'selected' : '' ?>><?= $gl ?></option>
          <?php endforeach; ?>
        </select></div>
      <div><label>ORCID</label><input type="text" name="orcid" maxlength="50" value="<?= e($researcher['orcid'] ?? '') ?>" placeholder="0000-0000-0000-0000"></div>
      <div><label>Google Scholar profile URL</label><input type="url" name="scholar" maxlength="255" value="<?= e($researcher['google_scholar'] ?? '') ?>" placeholder="https://scholar.google.com/…"></div>
    </div>
    <label>Research interests</label>
    <textarea name="interests"><?= e($researcher['research_interests'] ?? '') ?></textarea>
    <label>Research areas</label>
    <textarea name="areas"><?= e($researcher['research_areas'] ?? '') ?></textarea>
    <label>Biography</label>
    <textarea name="biography" rows="4"><?= e($researcher['biography'] ?? '') ?></textarea>
    <div class="form-actions"><button class="btn" type="submit">Save researcher profile</button></div>
  </form>
</div>

<?php include APP_PATH . '/views/partials/footer.php'; ?>
