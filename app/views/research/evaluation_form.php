<?php
$title = 'Evaluate Proposal';
include APP_PATH . '/views/partials/header.php';
?>

<div class="page-head mb-4">
  <div>
    <div class="badge badge-purple mb-1">Evaluator Workspace</div>
    <h1 class="page-title">Proposal Assessment: <?= e($app['title']) ?></h1>
    <p class="text-muted">
      <strong><?= e($app['application_number']) ?></strong> · Call: <?= e($app['call_title'] ?? '') ?> ·
      Review Level: <span class="badge badge-outline"><?= ($assignment['assignment_level'] ?? 'college') === 'university' ? 'University Committee Review' : 'College Evaluation' ?></span> ·
      Deadline: <strong><?= e(format_date($assignment['deadline'])) ?></strong>
      <?php if ($assignment['deadline'] && strtotime($assignment['deadline']) < time()): ?>
        <span class="badge badge-danger ml-2">OVERDUE</span>
      <?php endif; ?>
    </p>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline" href="<?= e(url('action=researcher_dashboard')) ?>">&larr; Back to Dashboard</a>
  </div>
</div>

<!-- Split Screen Layout: Left Pane Proposal Details / Docs, Right Pane Live 100-pt Scoring -->
<div class="row g-4">
  <!-- LEFT COLUMN: Proposal Dossier & Downloaded Documents -->
  <div class="col-lg-5">
    <div class="card mb-4">
      <div class="card-header border-bottom pb-2 mb-3">
        <h2 class="card-title text-purple h5 m-0"><i class="fas fa-file-alt mr-2"></i> Proposal Dossier</h2>
      </div>

      <?php if ($isBlind): ?>
        <div class="alert alert-info py-2 small mb-3">
          <strong><i class="fas fa-user-secret mr-1"></i> Blind Evaluation Mode:</strong> Reviewing in strict blind mode. Principal investigator and institution identities are confidential.
        </div>
      <?php endif; ?>

      <dl class="kv mb-3">
        <dt>Research Title</dt><dd><strong><?= e($app['title']) ?></strong></dd>
        <?php if (!$isBlind): ?>
          <dt>Principal Investigator</dt><dd><?= e($app['pi_name']) ?></dd>
          <dt>College / Department</dt><dd><?= e($app['college_name'] ?? '—') ?> / <?= e($app['department_name'] ?? '—') ?></dd>
        <?php endif; ?>
        <dt>Requested Budget</dt><dd><?= e(format_money($app['budget_requested'])) ?></dd>
        <dt>Duration</dt><dd><?= e((string) ($app['duration_months'] ?? '—')) ?> Months</dd>
        <dt>Ethics Review Required</dt><dd><span class="badge badge-<?= ($app['requires_ethics'] ?? 'no') === 'yes' ? 'amber' : 'outline' ?>"><?= strtoupper(e($app['requires_ethics'] ?? 'no')) ?></span></dd>
      </dl>

      <div class="border-top pt-3 mb-3">
        <h6 class="font-weight-bold text-purple mb-2"><i class="fas fa-paperclip mr-1"></i> Attached Review Documents</h6>
        <?php if (!empty($docHead)): ?>
          <div class="d-flex flex-column gap-2">
            <?php foreach ($docHead as $d): ?>
              <div class="p-2 border rounded bg-light d-flex justify-content-between align-items-center">
                <div>
                  <strong><?= e($d['type_name'] ?? 'Proposal Document') ?></strong>
                  <div class="text-muted small">v<?= (int) ($d['version'] ?? 1) ?> · <?= e(format_datetime($d['uploaded_at'])) ?></div>
                </div>
                <a class="btn btn-sm btn-purple" href="<?= e(base_url('download.php?id=' . $d['id'])) ?>" target="_blank">
                  <i class="fas fa-download mr-1"></i> Download
                </a>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p class="text-muted small m-0">No separate files attached.</p>
        <?php endif; ?>
      </div>

      <?php if (!empty($app['abstract'])): ?>
        <div class="border-top pt-3">
          <h6 class="font-weight-bold text-purple mb-1">Abstract Summary</h6>
          <p class="small text-muted" style="white-space: pre-wrap;"><?= e($app['abstract']) ?></p>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- RIGHT COLUMN: Interactive Scoring Rubric & Written Feedback -->
  <div class="col-lg-7">
    <form id="evaluationForm" method="post" action="<?= e(url('action=evaluation_submit')) ?>">
      <?= \App\Core\Csrf::field() ?>
      <input type="hidden" name="application_id" value="<?= (int) $app['id'] ?>">

      <!-- Live Floating Score Header -->
      <div class="card mb-4 border-purple shadow-sm">
        <div class="p-3 d-flex justify-content-between align-items-center bg-light rounded">
          <div>
            <span class="small text-muted d-block text-uppercase font-weight-bold">Total Weighted Score</span>
            <div class="d-flex align-items-baseline gap-1">
              <span id="currentScore" class="h2 text-purple font-weight-bold m-0">0.0</span>
              <span class="text-muted font-weight-bold">/ 100.0</span>
            </div>
          </div>
          <div class="text-right">
            <span id="scoreBadge" class="badge badge-amber px-3 py-2 h6 mb-1">Evaluating</span>
            <div id="scorePercentage" class="small text-muted">Pass Mark: 50%</div>
          </div>
        </div>
      </div>

      <!-- Rubric Criteria & Inputs -->
      <div class="card mb-4">
        <div class="card-header border-bottom pb-2 mb-3">
          <h2 class="card-title text-purple h5 m-0"><i class="fas fa-tasks mr-2"></i> Rubric Evaluation Criteria</h2>
          <p class="text-muted small m-0">Score each criterion independently according to the 100-point framework.</p>
        </div>

        <div class="table-responsive">
          <table class="table table-bordered mb-0">
            <thead class="bg-light">
              <tr>
                <th>Criterion & Guidance</th>
                <th style="width: 110px;">Max Weight</th>
                <th style="width: 140px;">Score Input</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($criteria as $c): ?>
                <tr>
                  <td>
                    <strong><?= e($c['criterion_name']) ?></strong>
                    <?php if (!empty($c['description'])): ?>
                      <div class="text-muted small mt-1"><?= e($c['description']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td class="font-weight-bold text-center align-middle">
                    <?= e((string) $c['weight_pct']) ?> pts
                  </td>
                  <td class="align-middle">
                    <div class="input-group input-group-sm">
                      <input type="number" name="score_<?= (int) $c['id'] ?>"
                             class="form-control score-input text-right font-weight-bold"
                             data-weight="<?= (float) $c['weight_pct'] ?>"
                             min="0" max="<?= (float) $c['weight_pct'] ?>" step="0.5"
                             value="<?= isset($draftData['scores'][$c['id']]) ? e($draftData['scores'][$c['id']]) : '' ?>"
                             oninput="calculateLiveScore()"
                             required>
                      <div class="input-group-append">
                        <span class="input-group-text">/ <?= (float) $c['weight_pct'] ?></span>
                      </div>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Written Feedback -->
      <div class="card mb-4">
        <div class="card-header border-bottom pb-2 mb-3">
          <h2 class="card-title text-purple h5 m-0"><i class="fas fa-comment-dots mr-2"></i> Qualitative Feedback & Recommendations</h2>
        </div>

        <div class="row g-3">
          <div class="col-12">
            <label class="form-label font-weight-bold">Strengths of the Proposal <span class="req text-danger">*</span></label>
            <textarea name="strengths" rows="3" class="form-control" required placeholder="Highlight original elements, clear problem definition, or strong methodology..."><?= e($draftData['strengths'] ?? '') ?></textarea>
          </div>
          <div class="col-12">
            <label class="form-label font-weight-bold">Weaknesses & Required Improvements <span class="req text-danger">*</span></label>
            <textarea name="weaknesses" rows="3" class="form-control" required placeholder="Detail specific shortcomings, methodological concerns, or feasibility issues..."><?= e($draftData['weaknesses'] ?? '') ?></textarea>
          </div>
          <div class="col-12">
            <label class="form-label font-weight-bold">Overall Evaluation Summary <span class="req text-danger">*</span></label>
            <textarea name="overall_comment" rows="3" class="form-control" required placeholder="Provide an executive summary of your evaluation..."><?= e($draftData['overall_comment'] ?? '') ?></textarea>
          </div>
          <div class="col-12">
            <label class="form-label font-weight-bold">Overall Evaluator Recommendation <span class="req text-danger">*</span></label>
            <select name="recommendation" class="form-control" required>
              <option value="">— Select recommendation —</option>
              <option value="accept" <?= isset($draftData['recommendation']) && $draftData['recommendation'] === 'accept' ? 'selected' : '' ?>>Accept (Meets university standards)</option>
              <option value="accept_with_revision" <?= isset($draftData['recommendation']) && $draftData['recommendation'] === 'accept_with_revision' ? 'selected' : '' ?>>Accept with Minor Revision</option>
              <option value="major_revision" <?= isset($draftData['recommendation']) && $draftData['recommendation'] === 'major_revision' ? 'selected' : '' ?>>Major Revision Required</option>
              <option value="reject" <?= isset($draftData['recommendation']) && $draftData['recommendation'] === 'reject' ? 'selected' : '' ?>>Reject</option>
            </select>
          </div>
        </div>
      </div>

      <div class="d-flex justify-content-between align-items-center mb-5">
        <button type="button" class="btn btn-outline" onclick="saveDraft()">💾 Save Draft</button>
        <button type="submit" class="btn btn-primary btn-lg" onclick="return confirm('Are you sure you want to submit this evaluation? Submitted evaluations are locked and cannot be edited.')">
          <i class="fas fa-check-circle mr-1"></i> Submit Official Assessment
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function calculateLiveScore() {
  const inputs = document.querySelectorAll('.score-input');
  let total = 0;

  inputs.forEach(input => {
    const val = parseFloat(input.value);
    if (!isNaN(val)) {
      total += val;
    }
  });

  const totalEl = document.getElementById('currentScore');
  const badgeEl = document.getElementById('scoreBadge');
  if (totalEl) totalEl.textContent = total.toFixed(1);

  if (badgeEl) {
    if (total >= 50.0) {
      badgeEl.className = 'badge badge-green px-3 py-2 h6 mb-1';
      badgeEl.textContent = 'PASS (>= 50%)';
    } else {
      badgeEl.className = 'badge badge-danger px-3 py-2 h6 mb-1';
      badgeEl.textContent = 'REJECT (< 50%)';
    }
  }
}

document.addEventListener('DOMContentLoaded', () => {
  calculateLiveScore();
});
</script>

<?php include APP_PATH . '/views/partials/footer.php'; ?>
