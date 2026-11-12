<?php
$title = 'Completed Research Report — ' . $report['project_number'];
include APP_PATH . '/views/partials/header.php';
?>
<div class="page-head">
  <div>
    <h1>Completed Research Report</h1>
    <p><strong><?= e($report['project_number']) ?></strong> · <?= e($report['project_title']) ?></p>
    <p class="muted">Version <?= (int) $report['version'] ?> · Submitted by <?= e($report['submitter_name']) ?> · <?= e(format_datetime($report['submitted_at'])) ?></p>
  </div>
  <span class="badge <?= in_array($report['status'], ['approved','college_passed','university_passed'], true) ? 'badge-success' : (in_array($report['status'], ['rejected'], true) ? 'badge-danger' : 'badge-warning') ?>"><?= e(str_replace('_', ' ', $report['status'])) ?></span>
</div>

<div class="grid grid-2">
  <div class="card">
    <h2 class="mt0">Submitted report</h2>
    <p><?= e($report['document_title']) ?> · Document version <?= (int) $report['document_version'] ?></p>
    <a class="btn btn-outline" href="<?= e($documentUrl) ?>">Download completed report</a>
    <dl class="kv" style="margin-top:14px;">
      <dt>College average</dt><dd><?= $report['college_average_score'] !== null ? e(number_format((float) $report['college_average_score'], 2) . ' / 100') : '—' ?></dd>
      <dt>University average</dt><dd><?= $report['university_average_score'] !== null ? e(number_format((float) $report['university_average_score'], 2) . ' / 100') : '—' ?></dd>
      <dt>Director decision</dt><dd><?= e($report['director_decision'] ? str_replace('_', ' ', $report['director_decision']) : 'Pending') ?></dd>
      <?php if ($report['director_comments']): ?><dt>Director comments</dt><dd><?= e($report['director_comments']) ?></dd><?php endif; ?>
    </dl>
  </div>

  <div class="card">
    <h2 class="mt0">Evaluation assignments</h2>
    <?php if (!$assignments): ?><p class="muted">No evaluators have been assigned yet.</p><?php else: ?>
      <table class="tbl">
        <tr><th>Stage</th><th>Evaluator</th><th>Status</th><th>Deadline</th></tr>
        <?php foreach ($assignments as $a): ?>
        <tr>
          <td><?= e(ucfirst($a['assignment_level'])) ?></td>
          <td><?= e($a['evaluator_name']) ?></td>
          <td><?= e(str_replace('_', ' ', $a['status'])) ?></td>
          <td><?= e(format_datetime($a['deadline'])) ?></td>
        </tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>
  </div>
</div>

<?php if ($isPi && $canSubmitRevision): ?>
<div class="card">
  <h2 class="mt0">Submit revised completed report</h2>
  <p class="muted">Upload a revised report version. The previous version and its evaluations remain in the audit history.</p>
  <form method="post" action="<?= e(url('action=final_report_submit')) ?>" enctype="multipart/form-data">
    <?= \App\Core\Csrf::field() ?>
    <input type="hidden" name="project_id" value="<?= (int) $report['project_id'] ?>">
    <label>Revised final report (PDF or DOCX)</label>
    <input type="file" name="final_report" accept=".pdf,.docx" required>
    <button class="btn btn-primary" type="submit">Submit revised version</button>
  </form>
</div>
<?php endif; ?>

<?php if ($myAssignments): ?>
<div class="card">
  <h2 class="mt0">Your evaluator assignment</h2>
  <?php foreach ($myAssignments as $mine): ?>
    <p><strong><?= e(ucfirst($mine['assignment_level'])) ?> evaluation:</strong> <?= e(str_replace('_', ' ', $mine['status'])) ?></p>
    <?php if ($mine['status'] === 'assigned'): ?>
      <form method="post" action="<?= e(url('action=final_report_assignment_respond')) ?>">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="assignment_id" value="<?= (int) $mine['id'] ?>">
        <label>Response</label>
        <select name="response" required>
          <option value="accepted">Accept assignment</option>
          <option value="declined">Decline assignment</option>
          <option value="conflict_declared">Declare conflict of interest</option>
        </select>
        <label>Reason (required for decline or conflict)</label>
        <textarea name="reason" rows="2"></textarea>
        <button class="btn btn-primary" type="submit">Submit response</button>
      </form>
    <?php elseif ($mine['status'] === 'accepted'): ?>
      <a class="btn btn-primary" href="<?= e(url('action=final_report_evaluation_form&final_report_id=' . (int) $report['id'] . '&assignment_id=' . (int) $mine['id'])) ?>">Open evaluation form</a>
    <?php endif; ?>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($canAssignCollege): ?>
<div class="card">
  <h2 class="mt0">Assign College Report Evaluators</h2>
  <p class="muted">Default evaluator count: <?= (int) \App\Core\Settings::int('college_evaluators_default', 1) ?>. Only active evaluators from this college who are not on the project team are eligible.</p>
  <form method="post" action="<?= e(url('action=final_report_assign_college')) ?>">
    <?= \App\Core\Csrf::field() ?>
    <input type="hidden" name="final_report_id" value="<?= (int) $report['id'] ?>">
    <label>Evaluators</label>
    <select name="evaluator_ids[]" multiple required size="6">
      <?php foreach ($eligibleCollegeEvaluators as $e): ?>
        <option value="<?= (int) $e['id'] ?>"><?= e($e['full_name']) ?> (<?= e($e['email']) ?>)</option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-primary" type="submit">Assign College Evaluators</button>
  </form>
</div>
<?php endif; ?>

<?php if ($canForward): ?>
<div class="card">
  <h2 class="mt0">Forward to University Research Directorate</h2>
  <form method="post" action="<?= e(url('action=final_report_forward')) ?>">
    <?= \App\Core\Csrf::field() ?>
    <input type="hidden" name="final_report_id" value="<?= (int) $report['id'] ?>">
    <label>College forwarding comments</label>
    <textarea name="comments" rows="3"></textarea>
    <button class="btn btn-primary" type="submit">Forward completed report</button>
  </form>
</div>
<?php endif; ?>

<?php if ($canAssignUniversity): ?>
<div class="card">
  <h2 class="mt0">Assign University Report Evaluators</h2>
  <p class="muted">Default evaluator count: <?= (int) \App\Core\Settings::int('university_evaluators_default', 1) ?>. The Research Director assigns independent university evaluators.</p>
  <form method="post" action="<?= e(url('action=final_report_assign_university')) ?>">
    <?= \App\Core\Csrf::field() ?>
    <input type="hidden" name="final_report_id" value="<?= (int) $report['id'] ?>">
    <label>Evaluators</label>
    <select name="evaluator_ids[]" multiple required size="6">
      <?php foreach ($eligibleUniversityEvaluators as $e): ?>
        <option value="<?= (int) $e['id'] ?>"><?= e($e['full_name']) ?> (<?= e($e['email']) ?>)</option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-primary" type="submit">Assign University Evaluators</button>
  </form>
</div>
<?php endif; ?>

<?php if ($canDecide): ?>
<div class="card">
  <h2 class="mt0">Research Director — Final Report Decision</h2>
  <form method="post" action="<?= e(url('action=final_report_decide')) ?>">
    <?= \App\Core\Csrf::field() ?>
    <input type="hidden" name="final_report_id" value="<?= (int) $report['id'] ?>">
    <label>Decision</label>
    <select name="decision" required>
      <option value="approve">Approve and close project</option>
      <option value="revision_required">Return for revision</option>
      <option value="reject">Reject completed report</option>
    </select>
    <label>Decision comments</label>
    <textarea name="comments" rows="4"></textarea>
    <button class="btn btn-primary" type="submit">Record Director decision</button>
  </form>
</div>
<?php endif; ?>

<?php include APP_PATH . '/views/partials/footer.php'; ?>
