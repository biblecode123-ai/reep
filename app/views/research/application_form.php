<?php $title = ($app === null ? 'New application' : 'Edit application'); include APP_PATH . '/views/partials/header.php'; ?>

<div class="page-head">
  <div>
    <h1><?= $app === null ? 'New research application' : 'Edit: ' . e($app['title']) ?></h1>
    <p>Your college and department are taken from your profile automatically (spec §17).</p>
  </div>
</div>

<form method="post" action="<?= e(url('action=application_save')) ?>" enctype="multipart/form-data">
  <?= \App\Core\Csrf::field() ?>
  <input type="hidden" name="id" value="<?= (int) ($app['id'] ?? 0) ?>">

  <div class="card">
    <h2 class="mt0">Basic information</h2>
    <div class="form-row">
      <div>
        <label>Call <span class="req">*</span></label>
        <select name="call_id" required <?= $app !== null ? 'disabled' : '' ?>>
          <option value="">— select call —</option>
          <?php foreach ($openCalls as $c): ?>
          <option value="<?= (int) $c['id'] ?>" <?= (int) ($app['call_id'] ?? ($_GET['call_id'] ?? 0)) === (int) $c['id'] ? 'selected' : '' ?>>
            <?= e($c['title']) ?>
          </option>
          <?php endforeach; ?>
        </select>
        <?php if ($app !== null): ?><input type="hidden" name="call_id" value="<?= (int) $app['call_id'] ?>"><?php endif; ?>
      </div>
      <div><label>Research title <span class="req">*</span></label><input type="text" name="title" required maxlength="250" value="<?= e($app['title'] ?? '') ?>"></div>
    </div>
    <div class="form-row">
      <div><label>Research type</label>
        <select name="research_type_id">
          <option value="">— select research type —</option>
          <?php foreach ($types as $type): ?>
            <option value="<?= (int) $type['id'] ?>" <?= (int) ($app['research_type_id'] ?? 0) === (int) $type['id'] ? 'selected' : '' ?>><?= e($type['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div><label>Research area / discipline</label>
        <input type="text" name="research_area" maxlength="150" value="<?= e($app['research_area'] ?? '') ?>">
      </div>
    </div>
    <div class="form-row">
      <div><label>Research theme</label>
        <select name="research_theme_id">
          <option value="">—</option>
          <?php foreach ($themes as $t): ?>
          <option value="<?= (int) $t['id'] ?>" <?= (int) ($app['research_theme_id'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div><label>Requires ethical review? (§30)</label>
        <select name="requires_ethics">
          <option value="no" <?= ($app['requires_ethics'] ?? 'no') === 'no' ? 'selected' : '' ?>>No</option>
          <option value="yes" <?= ($app['requires_ethics'] ?? '') === 'yes' ? 'selected' : '' ?>>Yes</option>
          <option value="unsure" <?= ($app['requires_ethics'] ?? '') === 'unsure' ? 'selected' : '' ?>>Unsure (route to ethics screening)</option>
        </select>
      </div>
    </div>
  </div>

  <div class="card">
    <h2 class="mt0">Structured research proposal</h2>
    <p class="muted">Complete the proposal sections below. The system stores these fields separately for screening and evaluation; the uploaded document remains the official full proposal attachment.</p>
    <label>Abstract</label>
    <textarea name="abstract" rows="4"><?= e($app['abstract'] ?? '') ?></textarea>
    <label>Background</label>
    <textarea name="background" rows="5"><?= e($app['background'] ?? '') ?></textarea>
    <label>Problem statement</label>
    <textarea name="problem_statement" rows="5"><?= e($app['problem_statement'] ?? '') ?></textarea>
    <label>Research objectives</label>
    <textarea name="objectives" rows="4"><?= e($app['objectives'] ?? '') ?></textarea>
    <label>Research questions / hypotheses</label>
    <textarea name="research_questions" rows="4"><?= e($app['research_questions'] ?? '') ?></textarea>
    <label>Literature review and theoretical framework</label>
    <textarea name="literature_review" rows="5"><?= e($app['literature_review'] ?? '') ?></textarea>
    <label>Research design and methodology</label>
    <textarea name="methodology" rows="5"><?= e($app['methodology'] ?? '') ?></textarea>
    <label>Expected results</label>
    <textarea name="expected_results" rows="4"><?= e($app['expected_results'] ?? '') ?></textarea>
    <label>Significance and contribution</label>
    <textarea name="significance" rows="4"><?= e($app['significance'] ?? '') ?></textarea>
    <label>Innovation / originality</label>
    <textarea name="innovation" rows="4"><?= e($app['innovation'] ?? '') ?></textarea>
    <label>References and citations</label>
    <textarea name="references_text" rows="5"><?= e($app['references_text'] ?? '') ?></textarea>
  </div>

  <div class="card">
    <h2 class="mt0">Budget and duration</h2>
    <div class="form-row">
      <div><label>Requested budget (ETB) <span class="req">*</span></label>
        <input type="number" name="budget" min="0" step="0.01" required value="<?= e((string) ($app['budget_requested'] ?? '0')) ?>">
      </div>
      <div><label>Proposed duration (months)</label>
        <input type="number" name="duration_months" min="1" max="<?= (int) ($call['max_duration_months'] ?? 120) ?>" value="<?= e((string) ($app['duration_months'] ?? '')) ?>">
      </div>
    </div>
    <div class="form-row">
      <div><label>Proposed start date</label><input type="date" name="proposed_start_date" value="<?= e($app['proposed_start_date'] ?? '') ?>"></div>
      <div><label>Proposed end date</label><input type="date" name="proposed_end_date" value="<?= e($app['proposed_end_date'] ?? '') ?>"></div>
    </div>
    <label>Budget justification</label>
    <textarea name="budget_justification" rows="4"><?= e($app['budget_justification'] ?? '') ?></textarea>
  </div>

  <div class="card">
    <h2 class="mt0">Work plan and timeline</h2>
    <p class="muted">List the planned activities, responsible team members, and expected outputs. At least one activity is required before submission.</p>
    <?php
      $workPlanRows = $workPlan ?: [
        ['activity' => '', 'start_date' => '', 'end_date' => '', 'responsible' => '', 'expected_output' => ''],
        ['activity' => '', 'start_date' => '', 'end_date' => '', 'responsible' => '', 'expected_output' => ''],
        ['activity' => '', 'start_date' => '', 'end_date' => '', 'responsible' => '', 'expected_output' => ''],
      ];
    ?>
    <?php foreach ($workPlanRows as $wp): ?>
      <div class="card" style="margin:12px 0;padding:12px;">
        <label>Activity</label>
        <input type="text" name="wp_activity[]" maxlength="200" value="<?= e($wp['activity'] ?? '') ?>">
        <div class="form-row">
          <div><label>Start date</label><input type="date" name="wp_start[]" value="<?= e($wp['start_date'] ?? '') ?>"></div>
          <div><label>End date</label><input type="date" name="wp_end[]" value="<?= e($wp['end_date'] ?? '') ?>"></div>
        </div>
        <div class="form-row">
          <div><label>Responsible person / role</label><input type="text" name="wp_resp[]" maxlength="150" value="<?= e($wp['responsible'] ?? '') ?>"></div>
          <div><label>Expected output</label><input type="text" name="wp_out[]" maxlength="250" value="<?= e($wp['expected_output'] ?? '') ?>"></div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="card">
    <h2 class="mt0">Proposal document</h2>
    <p>Upload the full proposal as a <strong>single PDF or Word document</strong> (`.pdf`, `.docx`).</p>
    <p>The proposal document must contain the following sections in this order: Abstract, Background, Problem statement, Objectives, Research questions, Literature review, Methodology, Expected results, Significance, Innovation, References.</p>
    <label>Upload Proposal <span class="req">*</span></label>
    <input type="file" name="proposal_document" accept=".pdf,.docx" <?= $app === null ? 'required' : '' ?>>
  </div>




  <div class="form-actions">
    <button class="btn" type="submit">Save application</button>
    <a class="btn btn-outline" href="<?= e(url('action=applications')) ?>">Cancel</a>
  </div>
</form>

<?php include APP_PATH . '/views/partials/footer.php'; ?>
