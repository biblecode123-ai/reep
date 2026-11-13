<?php $title = ($app === null ? 'New Research Application' : 'Edit Research Application'); include APP_PATH . '/views/partials/header.php'; ?>

<div class="page-head mb-4">
  <div>
    <div class="badge badge-purple mb-1">Researcher Portal</div>
    <h1 class="page-title"><?= $app === null ? 'New Research Proposal Submission' : 'Edit Proposal: ' . e($app['title']) ?></h1>
    <p class="text-muted">Proposals are routed to your designated college (<strong><?= e(\App\Core\Auth::user()['college_name'] ?? 'Affiliated College') ?></strong>) and department automatically.</p>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline" href="<?= e(url('action=dashboard')) ?>">Cancel & Return</a>
  </div>
</div>

<!-- 5-Step Process Indicator -->
<div class="wizard-steps mb-4">
  <div class="wizard-step active" id="step-nav-1">
    <div class="step-num">1</div>
    <div class="step-label">Call & Theme</div>
  </div>
  <div class="wizard-step" id="step-nav-2">
    <div class="step-num">2</div>
    <div class="step-label">Project Details</div>
  </div>
  <div class="wizard-step" id="step-nav-3">
    <div class="step-num">3</div>
    <div class="step-label">Proposal Document</div>
  </div>
  <div class="wizard-step" id="step-nav-4">
    <div class="step-num">4</div>
    <div class="step-label">Co-Investigators</div>
  </div>
  <div class="wizard-step" id="step-nav-5">
    <div class="step-num">5</div>
    <div class="step-label">Review & Submit</div>
  </div>
</div>

<form method="post" action="<?= e(url('action=application_save')) ?>" enctype="multipart/form-data" id="applicationForm">
  <?= \App\Core\Csrf::field() ?>
  <input type="hidden" name="id" value="<?= (int) ($app['id'] ?? 0) ?>">

  <!-- STEP 1: Call & Theme Selection -->
  <div class="card wizard-card mb-4" id="step-card-1">
    <div class="card-header border-bottom pb-3 mb-3">
      <h2 class="card-title text-purple h4 m-0">Step 1: Select Open Research Call & Research Theme</h2>
      <p class="text-muted small m-0">Researchers must choose one open research call and select exactly one theme associated with that call.</p>
    </div>

    <div class="row g-3">
      <div class="col-md-7">
        <label class="form-label font-weight-bold">Select Open Research Call <span class="req text-danger">*</span></label>
        <select name="call_id" id="callSelect" class="form-control" required <?= $app !== null ? 'disabled' : '' ?> onchange="onCallChange(this.value)">
          <option value="">— Select an open research call —</option>
          <?php foreach ($openCalls as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= (int) ($app['call_id'] ?? ($_GET['call_id'] ?? 0)) === (int) $c['id'] ? 'selected' : '' ?>>
              <?= e($c['call_number'] ?? ('CALL-' . $c['id'])) ?>: <?= e($c['title']) ?> (Closes: <?= e($c['deadline'] ?? 'Open') ?>)
            </option>
          <?php endforeach; ?>
        </select>
        <?php if ($app !== null): ?>
          <input type="hidden" name="call_id" value="<?= (int) $app['call_id'] ?>">
        <?php endif; ?>
      </div>

      <div class="col-md-5">
        <label class="form-label font-weight-bold">Select Call Theme <span class="req text-danger">*</span></label>
        <select name="research_theme_id" id="themeSelect" class="form-control" required>
          <option value="">— Select research theme —</option>
          <?php foreach ($themes as $t): ?>
            <option value="<?= (int) $t['id'] ?>" <?= (int) ($app['research_theme_id'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>>
              <?= e($t['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <small class="form-text text-muted">Choose exactly one theme linked to this call. Researchers cannot add themes.</small>
        <?php if (empty($themes)): ?>
          <p class="text-muted">This call has no themes yet. It cannot accept an application until the Research Director associates themes.</p>
        <?php endif; ?>
      </div>

      <div class="col-12">
        <label class="form-label font-weight-bold">Proposal Title <span class="req text-danger">*</span></label>
        <input type="text" name="title" class="form-control" required maxlength="250" placeholder="e.g. Investigation of Climate-Resilient Agricultural Practices in Bale Zone" value="<?= e($app['title'] ?? '') ?>">
      </div>
    </div>

    <div class="d-flex justify-content-end mt-4">
      <button type="button" class="btn btn-primary" onclick="goToStep(2)">Next: Project Details &rarr;</button>
    </div>
  </div>

  <!-- STEP 2: Project Details, Budget & Ethics -->
  <div class="card wizard-card mb-4" id="step-card-2" style="display:none;">
    <div class="card-header border-bottom pb-3 mb-3">
      <h2 class="card-title text-purple h4 m-0">Step 2: Project Specifications, Duration & Ethics</h2>
      <p class="text-muted small m-0">Provide project duration, budget figures and ethical clearance requirement.</p>
    </div>

    <div class="row g-3">
      <div class="col-md-4">
        <label class="form-label font-weight-bold">Estimated Budget (ETB) <span class="req text-danger">*</span></label>
        <input type="number" step="0.01" name="budget" class="form-control" placeholder="e.g. 150000.00" value="<?= e($app['budget_requested'] ?? '') ?>" required>
      </div>
      <div class="col-md-4">
        <label class="form-label font-weight-bold">Duration (Months)</label>
        <input type="number" min="1" max="60" name="duration_months" class="form-control" placeholder="e.g. 12" value="<?= e($app['duration_months'] ?? 12) ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label font-weight-bold">Requires Ethical Clearance? <span class="req text-danger">*</span></label>
        <select name="requires_ethics" class="form-control" required>
          <option value="no" <?= ($app['requires_ethics'] ?? 'no') === 'no' ? 'selected' : '' ?>>No (Standard research)</option>
          <option value="yes" <?= ($app['requires_ethics'] ?? '') === 'yes' ? 'selected' : '' ?>>Yes (Involves human/animal subjects)</option>
          <option value="unsure" <?= ($app['requires_ethics'] ?? '') === 'unsure' ? 'selected' : '' ?>>Unsure (Route to Ethics Committee)</option>
        </select>
      </div>

      <div class="col-12">
        <label class="form-label font-weight-bold">Executive Abstract / Summary</label>
        <textarea name="abstract" rows="4" class="form-control" placeholder="Provide a concise summary of the research problem, objectives, methodology and expected contributions (max 300 words)..."><?= e($app['abstract'] ?? '') ?></textarea>
      </div>
    </div>

    <div class="d-flex justify-content-between mt-4">
      <button type="button" class="btn btn-outline" onclick="goToStep(1)">&larr; Back: Call & Theme</button>
      <button type="button" class="btn btn-primary" onclick="goToStep(3)">Next: Proposal Document &rarr;</button>
    </div>
  </div>

  <!-- STEP 3: Proposal Document Upload -->
  <div class="card wizard-card mb-4" id="step-card-3" style="display:none;">
    <div class="card-header border-bottom pb-3 mb-3">
      <h2 class="card-title text-purple h4 m-0">Step 3: Upload Full Proposal Document</h2>
      <p class="text-muted small m-0">Institutional requirement: upload the complete proposal in PDF or Word format.</p>
    </div>

    <div class="alert alert-info border-left-purple bg-light p-3 rounded mb-3">
      <h6 class="font-weight-bold mb-1"><i class="fas fa-file-pdf text-purple mr-1"></i> Proposal Document Structure Guidelines</h6>
      <p class="small text-muted mb-0">The proposal must systematically cover: Abstract, Background, Problem Statement, Objectives, Research Questions, Literature Review, Methodology, Expected Results, Significance, Innovation, and References.</p>
    </div>

    <div class="p-4 border rounded bg-light text-center mb-3">
      <i class="fas fa-cloud-upload-alt text-purple fa-3x mb-2"></i>
      <div class="font-weight-bold mb-1">Upload Proposal Document (.pdf or .docx)</div>
      <p class="small text-muted mb-3">Maximum file size: 20MB. Document will be versioned and watermarked during review.</p>
      <input type="file" name="proposal_document" class="form-control w-50 mx-auto" accept=".pdf,.docx" <?= $app === null ? 'required' : '' ?>>
    </div>

    <div class="d-flex justify-content-between mt-4">
      <button type="button" class="btn btn-outline" onclick="goToStep(2)">&larr; Back: Project Details</button>
      <button type="button" class="btn btn-primary" onclick="goToStep(4)">Next: Co-Investigators &rarr;</button>
    </div>
  </div>

  <!-- STEP 4: Co-Investigators & Research Team -->
  <div class="card wizard-card mb-4" id="step-card-4" style="display:none;">
    <div class="card-header border-bottom pb-3 mb-3">
      <h2 class="card-title text-purple h4 m-0">Step 4: Research Team & Co-Investigators (Optional)</h2>
      <p class="text-muted small m-0">Invite registered Madda Walabu University academic staff as Co-Investigators. They will receive invitation notifications to accept or decline.</p>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-md-7">
        <label class="form-label font-weight-bold">Select Registered Faculty / Researcher</label>
        <select id="coInvestigatorSelect" class="form-control">
          <option value="">— Choose a faculty member to invite —</option>
          <?php foreach ($potentialCoInvestigators ?? [] as $pi): ?>
            <option value="<?= (int) $pi['id'] ?>" data-name="<?= e($pi['full_name']) ?>" data-email="<?= e($pi['email']) ?>">
              <?= e($pi['full_name']) ?> (<?= e($pi['email']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label font-weight-bold">Project Role</label>
        <select id="coInvestigatorRole" class="form-control">
          <option value="co_investigator">Co-Investigator</option>
          <option value="methodologist">Methodologist / Statistician</option>
          <option value="domain_specialist">Domain Specialist</option>
        </select>
      </div>
      <div class="col-md-2 d-flex align-items-end">
        <button type="button" class="btn btn-purple btn-block" onclick="addCoInvestigator()">Add Member</button>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-bordered table-sm bg-white" id="coInvestigatorsTable">
        <thead class="bg-light">
          <tr>
            <th>Member Name & Email</th>
            <th>Proposed Role</th>
            <th>Invitation Status</th>
            <th style="width: 80px;">Action</th>
          </tr>
        </thead>
        <tbody id="coInvestigatorsBody">
          <?php if (!empty($appTeamMembers)): ?>
            <?php foreach ($appTeamMembers as $tm): ?>
              <tr>
                <td><?= e($tm['full_name'] ?? 'Faculty') ?> <br><small class="text-muted"><?= e($tm['email'] ?? '') ?></small></td>
                <td><span class="badge badge-outline"><?= e(ucfirst(str_replace('_', ' ', $tm['role'] ?? 'co_investigator'))) ?></span></td>
                <td><span class="badge badge-<?= ($tm['invitation_status'] ?? 'pending') === 'accepted' ? 'green' : 'amber' ?>"><?= e(ucfirst($tm['invitation_status'] ?? 'pending')) ?></span></td>
                <td>
                  <button type="button" class="btn btn-sm btn-outline text-danger" onclick="this.closest('tr').remove()">&times;</button>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr id="emptyTeamRow">
              <td colspan="4" class="text-center text-muted py-3">No co-investigators added yet. You will be listed as Principal Investigator (PI).</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="d-flex justify-content-between mt-4">
      <button type="button" class="btn btn-outline" onclick="goToStep(3)">&larr; Back: Proposal Document</button>
      <button type="button" class="btn btn-primary" onclick="goToStep(5)">Next: Review & Submit &rarr;</button>
    </div>
  </div>

  <!-- STEP 5: Final Review & Submit -->
  <div class="card wizard-card mb-4" id="step-card-5" style="display:none;">
    <div class="card-header border-bottom pb-3 mb-3">
      <h2 class="card-title text-purple h4 m-0">Step 5: Final Review & Submission Confirmation</h2>
      <p class="text-muted small m-0">Please verify all entered research information before saving or submitting your application.</p>
    </div>

    <div class="review-box p-3 bg-light rounded border mb-4">
      <div class="row">
        <div class="col-md-6 mb-2">
          <small class="text-muted d-block">Submitting Principal Investigator:</small>
          <strong><?= e(\App\Core\Auth::user()['full_name'] ?? 'Current Researcher') ?></strong>
        </div>
        <div class="col-md-6 mb-2">
          <small class="text-muted d-block">Institutional Routing:</small>
          <strong><?= e(\App\Core\Auth::user()['college_name'] ?? 'College') ?> / <?= e(\App\Core\Auth::user()['department_name'] ?? 'Department') ?></strong>
        </div>
      </div>
    </div>

    <div class="form-check mb-4">
      <input class="form-check-input" type="checkbox" id="declarationCheck" required>
      <label class="form-check-label small" for="declarationCheck">
        I hereby confirm that this proposal is original, adheres to Madda Walabu University Research and Publication ethics, has not been simultaneously submitted elsewhere, and complies with all call guidelines.
      </label>
    </div>

    <div class="d-flex justify-content-between">
      <button type="button" class="btn btn-outline" onclick="goToStep(4)">&larr; Back: Co-Investigators</button>
      <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-paper-plane mr-2"></i> Save & Proceed to Application Workspace</button>
    </div>
  </div>
</form>

<script>
function goToStep(stepNumber) {
  for (let i = 1; i <= 5; i++) {
    const card = document.getElementById('step-card-' + i);
    const nav = document.getElementById('step-nav-' + i);
    if (card) card.style.display = (i === stepNumber) ? 'block' : 'none';
    if (nav) {
      if (i === stepNumber) {
        nav.classList.add('active');
        nav.classList.remove('completed');
      } else if (i < stepNumber) {
        nav.classList.remove('active');
        nav.classList.add('completed');
      } else {
        nav.classList.remove('active', 'completed');
      }
    }
  }
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function onCallChange(callId) {
  if (!callId) return;
  fetch('index.php?action=api_call_themes&call_id=' + callId)
    .then(r => r.json())
    .then(data => {
      const select = document.getElementById('themeSelect');
      if (!select || !data.themes) return;
      select.innerHTML = '<option value="">— Select research theme —</option>';
      data.themes.forEach(t => {
        const opt = document.createElement('option');
        opt.value = t.id;
        opt.textContent = t.name;
        select.appendChild(opt);
      });
    })
    .catch(err => {
      console.warn('Could not refresh themes for call', err);
    });
}

function addCoInvestigator() {
  const sel = document.getElementById('coInvestigatorSelect');
  const roleSel = document.getElementById('coInvestigatorRole');
  if (!sel || !sel.value) {
    alert('Please choose a faculty member first.');
    return;
  }
  const userId = sel.value;
  const opt = sel.options[sel.selectedIndex];
  const name = opt.getAttribute('data-name');
  const email = opt.getAttribute('data-email');
  const role = roleSel.value;
  const roleLabel = roleSel.options[roleSel.selectedIndex].text;

  const emptyRow = document.getElementById('emptyTeamRow');
  if (emptyRow) emptyRow.remove();

  const tbody = document.getElementById('coInvestigatorsBody');
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td><strong>${name}</strong><br><small class="text-muted">${email}</small>
      <input type="hidden" name="co_investigators[]" value="${userId}">
      <input type="hidden" name="co_investigator_roles[]" value="${role}">
    </td>
    <td><span class="badge badge-outline">${roleLabel}</span></td>
    <td><span class="badge badge-amber">Invitation Will Be Sent</span></td>
    <td><button type="button" class="btn btn-sm btn-outline text-danger" onclick="this.closest('tr').remove()">&times;</button></td>
  `;
  tbody.appendChild(tr);
  sel.value = '';
}
</script>

<?php include APP_PATH . '/views/partials/footer.php'; ?>
