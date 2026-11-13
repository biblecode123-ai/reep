<?php
$title = 'Global Evaluation Rubrics';
include APP_PATH . '/views/partials/header.php';
?>

<div class="page-head mb-4">
  <div>
    <div class="badge badge-purple mb-1">Institutional Governance</div>
    <h1 class="page-title">Global Evaluation Framework & Rubrics</h1>
    <p class="text-muted">Centrally managed, versioned 100-point evaluation rubrics across Madda Walabu University. Publishing creates an immutable version that locks to existing evaluations.</p>
  </div>
  <div>
    <button class="btn btn-primary" onclick="showRubricBuilder()">
      <i class="fas fa-plus mr-1"></i> Author New Rubric Version
    </button>
  </div>
</div>

<!-- Active & Archived Versions -->
<div class="card mb-4">
  <div class="card-header border-bottom pb-3 mb-3">
    <h2 class="card-title text-purple h5 m-0"><i class="fas fa-layer-group mr-2"></i> Published Framework Versions</h2>
  </div>
  <?php if (empty($templates)): ?>
    <div class="p-4 text-center text-muted">No rubric versions published yet.</div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-bordered table-hover mb-0">
        <thead class="bg-light">
          <tr>
            <th>Rubric Target</th>
            <th>Type Code</th>
            <th>Version</th>
            <th>Status</th>
            <th>Published By</th>
            <th>Effective Date</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($templates as $tmpl): ?>
            <tr>
              <td><strong><?= e($tmpl['name'] ?? $tmpl['type'] ?? 'Rubric') ?></strong></td>
              <td><code><?= e($tmpl['type'] ?? '—') ?></code></td>
              <td><span class="badge badge-purple">v<?= (int) ($tmpl['version'] ?? 1) ?></span></td>
              <td>
                <?php if ((int) ($tmpl['is_active'] ?? 0) === 1): ?>
                  <span class="badge badge-green">Active (Current Standard)</span>
                <?php else: ?>
                  <span class="badge badge-outline">Archived Version</span>
                <?php endif; ?>
              </td>
              <td><?= e($tmpl['approved_by_name'] ?? 'Research Directorate') ?></td>
              <td><?= e(format_datetime($tmpl['approved_at'] ?? $tmpl['created_at'])) ?></td>
              <td>
                <button type="button" class="btn btn-sm btn-outline" onclick="inspectRubric('<?= e($tmpl['type'] ?? '') ?>', <?= (int) ($tmpl['version'] ?? 1) ?>)">Inspect</button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- Visual Rubric Builder (Interactive 100-point dynamic editor) -->
<div class="card mb-4" id="rubricBuilderCard">
  <div class="card-header border-bottom pb-3 mb-3 d-flex justify-content-between align-items-center">
    <div>
      <h2 class="card-title text-purple h5 m-0"><i class="fas fa-sliders-h mr-2"></i> Visual Evaluation Rubric Builder</h2>
      <p class="text-muted small m-0">Sum of all criteria must equal exactly 100.0 points. Indicators within each criterion must sum to their criterion's maximum.</p>
    </div>
    <div class="total-score-pill px-3 py-1 rounded bg-light border">
      Allocated Total: <strong id="visualTotalScore" class="text-purple h5 m-0">0.0</strong> / 100.0
    </div>
  </div>

  <form method="post" action="<?= e(url('action=rubric_save')) ?>" id="rubricPublishForm">
    <?= \App\Core\Csrf::field() ?>
    <input type="hidden" name="criteria_json" id="criteria_json">

    <div class="row g-3 mb-4">
      <div class="col-md-6">
        <label class="form-label font-weight-bold">Evaluation Level & Scope <span class="req text-danger">*</span></label>
        <select name="rubric_type" id="rubric_type_select" class="form-control" required onchange="loadPresetForType(this.value)">
          <option value="">— Select institutional rubric to version —</option>
          <?php foreach ($rubricTypes as $key => $lbl): ?>
            <option value="<?= e($key) ?>"><?= e($lbl) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6 d-flex align-items-end">
        <button type="button" class="btn btn-outline" onclick="addCriterionBlock()">
          <i class="fas fa-plus mr-1"></i> Add Evaluation Criterion
        </button>
      </div>
    </div>

    <!-- Criteria Container -->
    <div id="criteriaBlocksContainer" class="d-flex flex-column gap-3 mb-4">
      <!-- Dynamic criteria blocks injected by JS -->
    </div>

    <div class="d-flex justify-content-between align-items-center border-top pt-3">
      <div id="rubricValidationAlert" class="small text-danger font-weight-bold">
        Total allocated points must equal exactly 100.
      </div>
      <button type="button" id="publishRubricBtn" class="btn btn-primary" disabled onclick="submitRubricForm()">
        <i class="fas fa-check-circle mr-1"></i> Validate & Publish Immutable Version
      </button>
    </div>
  </form>
</div>

<script>
let criteriaData = [
  {
    name: "Research Problem & Institutional Relevance",
    max_points: 15,
    indicators: [
      { name: "Clarity of problem formulation", max_points: 7, description: "Clearly stated research questions aligned with regional development." },
      { name: "Significance & practical value", max_points: 8, description: "Direct relevance to community, industry, or academic priorities." }
    ]
  },
  {
    name: "Literature Review & Theoretical Framing",
    max_points: 10,
    indicators: [
      { name: "Currency and depth of literature", max_points: 5, description: "Adequate coverage of recent peer-reviewed sources." },
      { name: "Identification of knowledge gaps", max_points: 5, description: "Critical synthesis justifying the current inquiry." }
    ]
  },
  {
    name: "Research Methodology & Rigour",
    max_points: 30,
    indicators: [
      { name: "Research design and sampling frame", max_points: 10, description: "Sound statistical or qualitative sampling rationale." },
      { name: "Data collection protocols & instruments", max_points: 10, description: "Validity, reliability and ethics adherence." },
      { name: "Analytical methods & data processing", max_points: 10, description: "Appropriateness of analytical models." }
    ]
  },
  {
    name: "Originality, Innovation & Ethics",
    max_points: 15,
    indicators: [
      { name: "Novelty of approach or findings", max_points: 10, description: "Original contribution to knowledge or application." },
      { name: "Ethical compliance & risk mitigation", max_points: 5, description: "Protection of subjects and safety." }
    ]
  },
  {
    name: "Expected Outputs & Dissemination Plan",
    max_points: 15,
    indicators: [
      { name: "Tangible deliverables & publications", max_points: 8, description: "Articles, policy briefs, technology prototypes." },
      { name: "Stakeholder engagement & extension", max_points: 7, description: "Transfer of knowledge to beneficiaries." }
    ]
  },
  {
    name: "Budget Realism & Feasibility Timeline",
    max_points: 15,
    indicators: [
      { name: "Cost breakdown justification", max_points: 8, description: "Realistic cost estimates conforming to MWU limits." },
      { name: "Work plan and schedule feasibility", max_points: 7, description: "Achievable milestones within proposed duration." }
    ]
  }
];

function renderCriteria() {
  const container = document.getElementById('criteriaBlocksContainer');
  if (!container) return;
  container.innerHTML = '';

  let grandTotal = 0;

  criteriaData.forEach((c, cIdx) => {
    grandTotal += (parseFloat(c.max_points) || 0);

    const block = document.createElement('div');
    block.className = 'border rounded p-3 bg-white shadow-sm';
    block.innerHTML = `
      <div class="d-flex justify-content-between align-items-center mb-2">
        <div class="d-flex align-items-center gap-2 flex-grow-1 mr-3">
          <span class="badge badge-purple">${cIdx + 1}</span>
          <input type="text" class="form-control form-control-sm font-weight-bold" value="${escapeHtml(c.name)}" oninput="updateCriterionName(${cIdx}, this.value)" placeholder="Criterion name">
        </div>
        <div class="d-flex align-items-center gap-2">
          <span class="small font-weight-bold">Max Points:</span>
          <input type="number" step="0.5" class="form-control form-control-sm" style="width: 80px;" value="${c.max_points}" oninput="updateCriterionMax(${cIdx}, this.value)">
          <button type="button" class="btn btn-sm btn-outline text-danger" onclick="removeCriterion(${cIdx})" title="Remove criterion">&times;</button>
        </div>
      </div>
      <div class="pl-3 border-left ml-2 mt-2">
        <div class="small text-muted font-weight-bold mb-1">Assessment Indicators:</div>
        <div id="indicators-for-${cIdx}" class="d-flex flex-column gap-2">
          ${c.indicators.map((ind, indIdx) => `
            <div class="d-flex align-items-center gap-2 bg-light p-2 rounded">
              <input type="text" class="form-control form-control-sm" value="${escapeHtml(ind.name)}" oninput="updateIndicatorName(${cIdx}, ${indIdx}, this.value)" placeholder="Indicator description">
              <span class="small text-muted">Max:</span>
              <input type="number" step="0.5" class="form-control form-control-sm" style="width: 70px;" value="${ind.max_points}" oninput="updateIndicatorMax(${cIdx}, ${indIdx}, this.value)">
              <button type="button" class="btn btn-sm text-danger" onclick="removeIndicator(${cIdx}, ${indIdx})">&times;</button>
            </div>
          `).join('')}
        </div>
        <button type="button" class="btn btn-xs btn-outline mt-2" onclick="addIndicator(${cIdx})">+ Add Indicator</button>
      </div>
    `;
    container.appendChild(block);
  });

  const totalEl = document.getElementById('visualTotalScore');
  const alertEl = document.getElementById('rubricValidationAlert');
  const publishBtn = document.getElementById('publishRubricBtn');

  if (totalEl) totalEl.textContent = grandTotal.toFixed(1);

  if (Math.abs(grandTotal - 100.0) < 0.01) {
    if (alertEl) {
      alertEl.className = 'small text-success font-weight-bold';
      alertEl.textContent = '✓ Total points allocate exactly to 100.0. Ready to publish.';
    }
    if (publishBtn) publishBtn.disabled = false;
  } else {
    if (alertEl) {
      alertEl.className = 'small text-danger font-weight-bold';
      alertEl.textContent = `Total allocated points must equal exactly 100.0 (Currently: ${grandTotal.toFixed(1)}).`;
    }
    if (publishBtn) publishBtn.disabled = true;
  }
}

function updateCriterionName(idx, val) { criteriaData[idx].name = val; }
function updateCriterionMax(idx, val) { criteriaData[idx].max_points = parseFloat(val) || 0; renderCriteria(); }
function updateIndicatorName(cIdx, indIdx, val) { criteriaData[cIdx].indicators[indIdx].name = val; }
function updateIndicatorMax(cIdx, indIdx, val) { criteriaData[cIdx].indicators[indIdx].max_points = parseFloat(val) || 0; }

function addCriterionBlock() {
  criteriaData.push({
    name: "New Evaluation Criterion",
    max_points: 10,
    indicators: [{ name: "Indicator 1", max_points: 10, description: "" }]
  });
  renderCriteria();
}

function removeCriterion(idx) {
  criteriaData.splice(idx, 1);
  renderCriteria();
}

function addIndicator(cIdx) {
  criteriaData[cIdx].indicators.push({
    name: "New Indicator",
    max_points: 5,
    description: ""
  });
  renderCriteria();
}

function removeIndicator(cIdx, indIdx) {
  criteriaData[cIdx].indicators.splice(indIdx, 1);
  renderCriteria();
}

function submitRubricForm() {
  const jsonInput = document.getElementById('criteria_json');
  const typeSelect = document.getElementById('rubric_type_select');
  if (!typeSelect.value) {
    alert('Please select a rubric type first.');
    return;
  }
  jsonInput.value = JSON.stringify(criteriaData);
  document.getElementById('rubricPublishForm').submit();
}

function escapeHtml(str) {
  return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

document.addEventListener('DOMContentLoaded', () => {
  renderCriteria();
});
</script>

<?php include APP_PATH . '/views/partials/footer.php'; ?>
