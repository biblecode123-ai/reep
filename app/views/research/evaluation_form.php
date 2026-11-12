<?php
$title = 'Evaluate proposal';
include APP_PATH . '/views/partials/header.php';
?>

<div class="page-head">
  <div>
    <h1>Evaluate: <?= e($app['title']) ?></h1>
    <p>
      <?= e($app['application_number']) ?> · Call: <?= e($app['call_title'] ?? '') ?>
      · Deadline: <?= e(format_date($assignment['deadline'])) ?>
      <?php if ($assignment['deadline'] && strtotime($assignment['deadline']) < time()): ?>
        <span class="badge badge-danger">OVERDUE</span>
      <?php endif; ?>
    </p>
  </div>
</div>

<!-- Progress Indicator -->
<div class="card" style="margin-bottom: 20px;">
  <div style="display:flex;gap:20px;align-items:center;">
    <div style="flex:1;">
      <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
        <span style="font-weight:600;">Evaluation Progress</span>
        <span id="progressText">0% complete</span>
      </div>
      <div style="height:8px;background:var(--line);border-radius:4px;overflow:hidden;">
        <div id="progressBar" style="height:100%;background:var(--brand);width:0%;transition:width 0.3s;"></div>
      </div>
    </div>
    <div id="lastSaved" style="color:var(--muted);font-size:13px;">Not saved yet</div>
  </div>
</div>

<!-- Proposal Review Section -->
<div class="card" style="margin-bottom: 20px;">
  <h2 class="mt0">Proposal Review</h2>

  <?php if ($isBlind): ?>
  <div class="alert alert-info" style="margin-bottom:16px;">
    <strong>Blind Review Mode:</strong> Evaluator identities are anonymized. Your feedback will be shared without attribution to the PI.
  </div>
  <?php endif; ?>

  <dl class="kv">
    <dt>Call reference</dt><dd><?= e($app['call_title'] ?? '—') ?> (<?= e($app['call_reference'] ?? '') ?>)</dd>
    <dt>Principal investigator</dt><dd><?= e($app['pi_name']) ?></dd>
    <?php if ($teamMembers): ?>
    <dt>Co-investigators</dt><dd>
      <?php foreach ($teamMembers as $tm): ?>
        <div><?= e($tm['full_name']) ?> <span class="muted" style="font-size:12px;">(<?= e(ucfirst(str_replace('_', ' ', (string) ($tm['role'] ?? 'co_investigator')))) ?>)</span></div>
      <?php endforeach; ?>
    </dd>
    <?php endif; ?>
    <dt>College / Department</dt><dd><?= e($app['college_name'] ?? '—') ?> / <?= e($app['department_name'] ?? '—') ?></dd>
    <dt>Submitted</dt><dd><?= e(format_datetime($app['submitted_at'] ?? '')) ?></dd>
    <dt>Evaluation level</dt><dd><?= ($assignment['assignment_level'] ?? 'college') === 'university' ? 'University level' : 'College level' ?></dd>
    <dt>Requested budget</dt><dd><?= e(format_money($app['budget_requested'])) ?></dd>
    <dt>Duration</dt><dd><?= e((string) ($app['duration_months'] ?? '—')) ?> months</dd>
    <dt>Ethics review required</dt><dd><?= e(ucfirst((string) ($app['requires_ethics'] ?? 'no'))) ?></dd>
  </dl>

  <?php if (!empty($app['abstract'])): ?>
  <h3>Abstract</h3>
  <p style="white-space:pre-wrap;"><?= e($app['abstract']) ?></p>
  <?php endif; ?>

  <?php if (!empty($app['background'])): ?>
  <h3>Background</h3>
  <p style="white-space:pre-wrap;"><?= e($app['background']) ?></p>
  <?php endif; ?>

  <?php if (!empty($app['problem_statement'])): ?>
  <h3>Problem Statement</h3>
  <p style="white-space:pre-wrap;"><?= e($app['problem_statement']) ?></p>
  <?php endif; ?>

  <?php if (!empty($app['objectives'])): ?>
  <h3>Research Objectives</h3>
  <p style="white-space:pre-wrap;"><?= e($app['objectives']) ?></p>
  <?php endif; ?>

  <?php if (!empty($app['research_questions'])): ?>
  <h3>Research Questions</h3>
  <p style="white-space:pre-wrap;"><?= e($app['research_questions']) ?></p>
  <?php endif; ?>

  <?php if (!empty($app['literature_review'])): ?>
  <h3>Literature Review</h3>
  <p style="white-space:pre-wrap;"><?= e($app['literature_review']) ?></p>
  <?php endif; ?>

  <?php if (!empty($app['methodology'])): ?>
  <h3>Methodology</h3>
  <p style="white-space:pre-wrap;"><?= e($app['methodology']) ?></p>
  <?php endif; ?>

  <?php if (!empty($app['expected_results'])): ?>
  <h3>Expected Results</h3>
  <p style="white-space:pre-wrap;"><?= e($app['expected_results']) ?></p>
  <?php endif; ?>

  <?php if (!empty($app['significance'])): ?>
  <h3>Significance</h3>
  <p style="white-space:pre-wrap;"><?= e($app['significance']) ?></p>
  <?php endif; ?>

  <?php if (!empty($app['innovation'])): ?>
  <h3>Innovation</h3>
  <p style="white-space:pre-wrap;"><?= e($app['innovation']) ?></p>
  <?php endif; ?>

  <?php if ($workPlan): ?>
  <h3>Work Plan and Timeline</h3>
  <table class="tbl">
    <tr><th>Activity</th><th>Start Date</th><th>End Date</th><th>Responsible</th><th>Expected Output</th></tr>
    <?php foreach ($workPlan as $wp): ?>
    <tr>
      <td><?= e($wp['activity']) ?></td>
      <td><?= e(format_date($wp['start_date'])) ?></td>
      <td><?= e(format_date($wp['end_date'])) ?></td>
      <td><?= e($wp['responsible'] ?? '—') ?></td>
      <td><?= e($wp['expected_output'] ?? '—') ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>

  <?php if (!empty($app['budget_justification'])): ?>
  <h3>Budget Justification</h3>
  <p style="white-space:pre-wrap;"><?= e($app['budget_justification']) ?></p>
  <?php endif; ?>

  <?php if ($docHead): ?>
  <h3>Supporting Documents</h3>
  <?php foreach ($docHead as $d): ?>
    <div class="doc-row">
      <div>
        <strong><?= e($d['type_name']) ?></strong>
        <span class="muted" style="font-size:12px;"> — v<?= (int) $d['version'] ?> · <?= e(format_datetime($d['uploaded_at'])) ?></span>
      </div>
      <a class="btn btn-outline btn-sm" href="<?= e(base_url('download.php?id=' . $d['id'])) ?>" target="_blank">Download</a>
    </div>
  <?php endforeach; ?>
  <?php else: ?>
  <p class="muted">No supporting documents attached.</p>
  <?php endif; ?>
</div>

<!-- Evaluation Form -->
<form id="evaluationForm" method="post" action="<?= e(url('action=evaluation_submit')) ?>">
  <?= \App\Core\Csrf::field() ?>
  <input type="hidden" name="application_id" value="<?= (int) $app['id'] ?>">

  <!-- Scoring Section -->
  <div class="card" style="margin-bottom: 20px;">
    <h2 class="mt0">Scoring Criteria</h2>
    <p class="muted">Score each criterion according to the rubric. Scores range from 0 to 100.</p>

    <table class="tbl">
      <thead>
        <tr>
          <th style="width:30%;">Criterion</th>
          <th style="width:10%;">Weight</th>
          <th style="width:15%;">Score (0–100)</th>
          <th style="width:45%;">Criterion-specific comments</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($criteria as $c): ?>
        <tr>
          <td>
            <strong><?= e($c['criterion_name']) ?></strong>
            <?php if (!empty($c['description'])): ?>
            <div class="muted" style="font-size:12px;margin-top:4px;"><?= e($c['description']) ?></div>
            <?php endif; ?>
          </td>
          <td><?= e((string) $c['weight_pct']) ?>%</td>
          <td>
            <input type="number" name="score_<?= (int) $c['id'] ?>"
                   class="score-input"
                   data-weight="<?= (float) $c['weight_pct'] ?>"
                   min="0" max="100" step="0.5"
                   value="<?= isset($draftData['scores'][$c['id']]) ? e($draftData['scores'][$c['id']]) : '' ?>"
                   required>
          </td>
          <td>
            <input type="text" name="score_comment_<?= (int) $c['id'] ?>"
                   maxlength="500"
                   placeholder="Optional feedback on this criterion"
                   value="<?= isset($draftData['score_comments'][$c['id']]) ? e($draftData['score_comments'][$c['id']]) : '' ?>">
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <!-- Score Summary -->
    <div style="margin-top:20px;padding:16px;background:var(--bg-soft);border-radius:4px;">
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <div>
          <strong>Current Weighted Score:</strong>
          <span id="currentScore" style="font-size:24px;font-weight:600;margin-left:12px;">0.0</span>
          <span style="color:var(--muted);">/ 100</span>
        </div>
        <div id="scorePercentage" style="color:var(--muted);">0%</div>
      </div>
    </div>
  </div>

  <!-- Structured Feedback Section -->
  <div class="card" style="margin-bottom: 20px;">
    <h2 class="mt0">Written Feedback</h2>
    <p class="muted">Provide constructive feedback to help the researcher improve their proposal.</p>

    <div class="form-row">
      <div>
        <label>Strengths <span class="req">*</span></label>
        <textarea name="strengths" rows="4" required placeholder="What is scientifically or practically strong about this proposal?"><?= e($draftData['strengths'] ?? '') ?></textarea>
      </div>
      <div>
        <label>Weaknesses and Concerns <span class="req">*</span></label>
        <textarea name="weaknesses" rows="4" required placeholder="What needs improvement, clarification, or additional evidence?"><?= e($draftData['weaknesses'] ?? '') ?></textarea>
      </div>
    </div>

    <label>Methodological Feedback</label>
    <textarea name="methodology_feedback" rows="3" placeholder="Are the methods appropriate and feasible? Any concerns about the research design?"><?= e($draftData['methodology_feedback'] ?? '') ?></textarea>

    <label>Recommendations for Improvement</label>
    <textarea name="improvement_recommendations" rows="3" placeholder="What specific changes would improve this proposal?"><?= e($draftData['improvement_recommendations'] ?? '') ?></textarea>

    <label>Ethics and Feasibility Concerns</label>
    <textarea name="ethics_feasibility" rows="3" placeholder="Any ethical considerations or feasibility issues requiring clarification?"><?= e($draftData['ethics_feasibility'] ?? '') ?></textarea>

    <label>Overall Evaluation Summary <span class="req">*</span></label>
    <textarea name="overall_comment" rows="4" required placeholder="Provide a concise professional assessment of the proposal"><?= e($draftData['overall_comment'] ?? '') ?></textarea>
  </div>

  <!-- Additional Comments Section -->
  <div class="card" style="margin-bottom: 20px;">
    <h2 class="mt0">Additional Comments</h2>

    <label>Comments for the Researcher (visible to them)</label>
    <textarea name="comments[]" rows="2" placeholder="Additional feedback or clarification for the PI"></textarea>
    <textarea name="comments[]" rows="2" placeholder="Additional comment (optional)" style="margin-top:8px;"></textarea>

    <label style="margin-top:16px;">Confidential Comments (visible only to authorized committee/coordinators)</label>
    <textarea name="committee_comments[]" rows="2" placeholder="Internal notes for the evaluation committee"></textarea>
    <p class="muted" style="font-size:12px;">These comments will NOT be shared with the principal investigator.</p>
  </div>

  <!-- Recommendation Section -->
  <div class="card" style="margin-bottom: 20px;">
    <h2 class="mt0">Overall Recommendation</h2>
    <p class="muted">This is a recommendation, not a final institutional decision.</p>

    <label>Recommendation <span class="req">*</span></label>
    <select name="recommendation" required>
      <option value="">— select recommendation —</option>
      <option value="accept" <?= isset($draftData['recommendation']) && $draftData['recommendation'] === 'accept' ? 'selected' : '' ?>>Accept</option>
      <option value="accept_with_revision" <?= isset($draftData['recommendation']) && $draftData['recommendation'] === 'accept_with_revision' ? 'selected' : '' ?>>Accept with revision</option>
      <option value="major_revision" <?= isset($draftData['recommendation']) && $draftData['recommendation'] === 'major_revision' ? 'selected' : '' ?>>Major revision required</option>
      <option value="reject" <?= isset($draftData['recommendation']) && $draftData['recommendation'] === 'reject' ? 'selected' : '' ?>>Reject</option>
    </select>
  </div>

  <!-- Actions -->
  <div class="form-actions">
    <button type="button" class="btn btn-outline" onclick="saveDraft()">💾 Save Draft</button>
    <button class="btn" type="submit" onclick="return confirmSubmission()">Submit Evaluation</button>
    <a class="btn btn-outline" href="<?= e(url('action=my_evaluations')) ?>">Cancel</a>
  </div>
</form>

<!-- Submission Confirmation Modal -->
<div id="submitModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:1000;">
  <div style="background:white;padding:24px;max-width:500px;margin:100px auto;border-radius:8px;">
    <h3>Confirm Evaluation Submission</h3>
    <p>Are you ready to submit your evaluation? Once submitted:</p>
    <ul>
      <li>Your evaluation will be locked and cannot be edited</li>
      <li>The principal investigator will be able to see your feedback</li>
      <li>Your recommendation will be forwarded to the coordinator</li>
    </ul>
    <div style="margin-top:16px;display:flex;gap:12px;">
      <button class="btn" onclick="confirmSubmit()">Yes, Submit</button>
      <button class="btn btn-outline" onclick="closeSubmitModal()">Cancel</button>
    </div>
  </div>
</div>

<script>
// Auto-save draft every 60 seconds
let autoSaveInterval;

function calculateScore() {
  const inputs = document.querySelectorAll('.score-input');
  let total = 0;
  let weightSum = 0;
  let filled = 0;

  inputs.forEach(input => {
    const score = parseFloat(input.value);
    if (!isNaN(score)) {
      const weight = parseFloat(input.dataset.weight) || 0;
      total += score * (weight / 100);
      weightSum += weight;
      filled++;
    }
  });

  const weighted = weightSum > 0 ? (total * 100 / weightSum) : total;
  const percentage = weightSum > 0 ? weighted : 0;

  document.getElementById('currentScore').textContent = weighted.toFixed(1);
  document.getElementById('scorePercentage').textContent = percentage.toFixed(1) + '%';

  // Update progress bar
  const totalFields = inputs.length + 6; // scores + 6 required text fields
  const filledFields = filled + (document.querySelector('textarea[name="strengths"]').value ? 1 : 0)
                          + (document.querySelector('textarea[name="weaknesses"]').value ? 1 : 0)
                          + (document.querySelector('textarea[name="overall_comment"]').value ? 1 : 0)
                          + (document.querySelector('select[name="recommendation"]').value ? 1 : 0)
                          + (document.querySelector('textarea[name="methodology_feedback"]').value ? 1 : 0)
                          + (document.querySelector('textarea[name="improvement_recommendations"]').value ? 1 : 0);
  const progress = Math.min(100, Math.round((filledFields / totalFields) * 100));

  document.getElementById('progressBar').style.width = progress + '%';
  document.getElementById('progressText').textContent = progress + '% complete';
}

function saveDraft() {
  const form = document.getElementById('evaluationForm');
  const formData = new FormData(form);

  fetch('index.php?action=evaluation_save_draft', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.ok) {
      document.getElementById('lastSaved').textContent = 'Last saved: ' + data.message;
      document.getElementById('lastSaved').style.color = 'var(--ok)';
    } else {
      alert('Failed to save draft: ' + data.message);
    }
  })
  .catch(error => {
    console.error('Error saving draft:', error);
  });
}

function confirmSubmission() {
  document.getElementById('submitModal').style.display = 'block';
  return false;
}

function closeSubmitModal() {
  document.getElementById('submitModal').style.display = 'none';
}

function confirmSubmit() {
  document.getElementById('submitModal').style.display = 'none';
  document.getElementById('evaluationForm').submit();
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
  // Calculate initial score
  calculateScore();

  // Recalculate on score changes
  document.querySelectorAll('.score-input').forEach(input => {
    input.addEventListener('input', calculateScore);
  });

  // Auto-save every 60 seconds
  autoSaveInterval = setInterval(saveDraft, 60000);

  // Save on field blur
  document.querySelectorAll('textarea, select').forEach(field => {
    field.addEventListener('blur', saveDraft);
  });
});

// Clear auto-save on page unload
window.addEventListener('beforeunload', function() {
  clearInterval(autoSaveInterval);
});
</script>

<?php include APP_PATH . '/views/partials/footer.php'; ?>

