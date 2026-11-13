<?php
use App\Core\Auth;
use App\Core\Gate;
$title = 'Application: ' . $app['title'];
include APP_PATH . '/views/partials/header.php';

$isPi = (int) $app['pi_user_id'] === Auth::id();
?>
<div class="page-head">
  <div>
    <h1><?= e($app['title']) ?></h1>
    <p>
      <strong><?= e($app['application_number']) ?></strong> ·
      <span class="badge <?= status_badge($app['status_code']) ?>"><?= e($app['stage_label'] ?? status_label($app['status_code'])) ?></span>
      · v<?= (int) $app['version'] ?>
      · Call: <?= e($app['call_title'] ?? '—') ?>
    </p>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap;">
    <?php if ($isPi && $app['status_code'] === 'DRAFT'): ?>
      <a class="btn btn-outline" href="<?= e(url('action=application_form&id=' . $app['id'])) ?>">Edit</a>
      <form method="post" action="<?= e(url('action=application_submit')) ?>" data-confirm="Submit this application? Locked documents become part of the official record.">
        <?= \App\Core\Csrf::field() ?><input type="hidden" name="id" value="<?= (int) $app['id'] ?>">
        <button class="btn" type="submit">Submit application</button>
      </form>
    <?php endif; ?>
    <?php if ($isPi && in_array($app['status_code'], ['SUBMITTED', 'COLLEGE_RECEIVED', 'UNDER_COLLEGE_SCREENING', 'EVALUATOR_ASSIGNMENT_PENDING', 'EVALUATOR_ASSIGNED', 'EVALUATOR_ACCEPTED', 'UNDER_EVALUATION'], true)): ?>
      <form method="post" action="<?= e(url('action=application_withdraw')) ?>" data-confirm="Withdraw this application?">
        <?= \App\Core\Csrf::field() ?><input type="hidden" name="id" value="<?= (int) $app['id'] ?>">
        <button class="btn btn-danger" type="submit">Withdraw</button>
      </form>
    <?php endif; ?>
    <?php if (Gate::allows('research.assign') && $app['status_code'] === 'EVALUATOR_ASSIGNMENT_PENDING'): ?>
      <a class="btn" href="<?= e(url('action=assign_evaluators&application_id=' . $app['id'])) ?>">Assign evaluators</a>
    <?php endif; ?>
    <?php if (Gate::allows('research.assign') && $app['status_code'] === 'UNIV_EVAL_ASSIGNMENT_PENDING'): ?>
      <a class="btn" href="<?= e(url('action=univ_eval_assign_form&application_id=' . $app['id'])) ?>">Assign university evaluators</a>
    <?php endif; ?>

  </div>
</div>

<div class="grid grid-2">
  <!-- LEFT: details -->
  <div>
    <div class="card">
      <h2 class="mt0">Research summary</h2>
      <dl class="kv">
        <dt>Principal investigator</dt><dd><?= e($app['pi_name']) ?></dd>
        <dt>College / Dept</dt><dd><?= e($app['college_name'] ?? '—') ?> / <?= e($app['department_name'] ?? '—') ?></dd>
        <dt>Type / Theme</dt><dd><?= e($app['research_type_name'] ?? '—') ?> / <?= e($app['research_theme_name'] ?? '—') ?></dd>
        <dt>Duration</dt><dd><?= e((string) ($app['duration_months'] ?? '—')) ?> months (<?= e(format_date($app['proposed_start_date'])) ?> → <?= e(format_date($app['proposed_end_date'])) ?>)</dd>
        <dt>Requested budget</dt><dd><?= e(format_money($app['budget_requested'])) ?></dd>
        <dt>Ethics review</dt><dd><?= e(ucfirst((string) ($app['requires_ethics'] ?? '—'))) ?></dd>
      </dl>
      <?php /* Sections now in uploaded proposal document (§16) */ ?>
      <?php if (!empty($app['abstract'])): ?><h3 class="muted">Abstract (deprecated)</h3><p style="white-space:pre-wrap;"><?= e($app['abstract']) ?></p><?php endif; ?>
      <?php foreach ([['Objectives', 'objectives'], ['Methodology', 'methodology'], ['Problem statement', 'problem_statement'], ['Significance', 'significance']] as [$lbl, $key]): ?>
        <?php if (!empty($app[$key])): ?><h3 class="muted"><?= e($lbl) ?> (deprecated)</h3><p style="white-space:pre-wrap;"><?= e($app[$key]) ?></p><?php endif; ?>
      <?php endforeach; ?>
    </div>

    <!-- Work plan -->
    <?php $wp = \App\Models\ApplicationModel::workPlan((int) $app['id']); if ($wp): ?>
    <div class="card">
      <h2 class="mt0">Work plan</h2>
      <table class="tbl">
        <tr><th>Activity</th><th>Dates</th><th>Responsible</th><th>Output</th></tr>
        <?php foreach ($wp as $w): ?>
        <tr><td><?= e($w['activity']) ?></td>
            <td><?= e(format_date($w['start_date'])) ?> → <?= e(format_date($w['end_date'])) ?></td>
            <td><?= e($w['responsible'] ?? '—') ?></td>
            <td><?= e($w['expected_output'] ?? '—') ?></td></tr>
        <?php endforeach; ?>
      </table>
    </div>
    <?php endif; ?>

    <!-- Research Team -->
    <div class="card" id="team">
      <h2 class="mt0">Research Team</h2>
      <table class="tbl">
        <tr><th>Name</th><th>Status</th><th>Invited</th><th>Responded</th></tr>
        <?php foreach ($appTeamMembers as $tm): ?>
        <tr>
          <td><?= e($tm['full_name']) ?></td>
          <td>
            <span class="badge"><?= e(ucfirst($tm['status'])) ?></span>
            <?php if ((int)$tm['user_id'] === Auth::id() && $tm['status'] === 'pending'): ?>
              <button class="btn btn-sm btn-success" onclick="respondInvitation(<?= $tm['id'] ?>, 'accept')">Accept</button>
              <button class="btn btn-sm btn-danger" onclick="respondInvitation(<?= $tm['id'] ?>, 'decline')">Decline</button>
            <?php endif; ?>
          </td>
          <td><?= e(format_date($tm['invited_at'])) ?></td>
          <td><?= $tm['responded_at'] ? e(format_date($tm['responded_at'])) : '—' ?></td>
        </tr>
        <?php endforeach; ?>
      </table>
      <?php if ($isPi && $app['status_code'] === 'DRAFT'): ?>
        <div style="margin-top:10px;">
            <input type="text" id="researcherSearch" placeholder="Search researchers by name, college, or department...">
            <div id="results"></div>
        </div>
      <?php endif; ?>
    </div>

  </div>

  <!-- RIGHT: workflow, evaluations, history -->
  <div>
    <div class="card">
      <h2 class="mt0">Status timeline (§107)</h2>
      <?php
        $stages = ['DRAFT','SUBMITTED','COLLEGE_RECEIVED','UNDER_COLLEGE_SCREENING','EVALUATOR_ASSIGNMENT_PENDING',
                   'EVALUATOR_ASSIGNED','EVALUATOR_ACCEPTED','UNDER_EVALUATION','EVALUATION_COMPLETED',
                   'REVISION_REQUIRED','REVISION_SUBMITTED','RE_EVALUATION','COLLEGE_REVIEW','COLLEGE_APPROVED',
                   'SUBMITTED_TO_UNIVERSITY','UNIVERSITY_REVIEW','UNIVERSITY_COMMITTEE_REVIEW','ETHICS_REVIEW','APPROVED','BUDGET_REVIEW',
                   'BUDGET_APPROVED','FUNDED','ACTIVE','PROGRESS_MONITORING','FINAL_REPORT_SUBMITTED',
                   'FINAL_EVALUATION','PRESENTATION_SCHEDULED','PRESENTED','PRESENTATION_EVALUATION',
                   'FINAL_APPROVAL','COMPLETED','REPOSITORY','ARCHIVED'];
        $currentIdx = array_search($app['status_code'], $stages, true);
      ?>
      <ul class="timeline">
        <?php foreach ($stages as $i => $st): ?>
          <?php
            $cls = '';
            if ($currentIdx !== false && $i < $currentIdx) { $cls = 'done'; }
            elseif ($currentIdx === $i) { $cls = 'current'; }
          ?>
          <li class="<?= $cls ?>">
            <div class="tl-head"><?= e(status_label($st)) ?></div>
            <?php if ($cls === 'current'): ?><div class="tl-meta">current stage</div><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php if (in_array($app['status_code'], ['REJECTED', 'WITHDRAWN', 'CANCELLED', 'SUSPENDED', 'TERMINATED', 'EXPIRED'], true)): ?>
        <div class="alert alert-error">Terminal status: <?= e(status_label($app['status_code'])) ?></div>
      <?php endif; ?>
    </div>

    <!-- Workflow actions available to this user (§95: server-side enforced) -->
    <?php if ($transitions): ?>
    <?php
      // Transitions driven by their own structured forms below (extra data is
      // required) — never fire them from the generic button.
      $formDriven = ['Request revision', 'Request revision (directorate)', 'Submit revision',
                     'Budget approved', 'Assign to research committee', 'Activate project'];
    ?>
    <div class="card">
      <h2 class="mt0">Available actions</h2>
      <?php foreach ($transitions as $t): ?>
        <?php if (!$t['permitted'] || in_array($t['name'], $formDriven, true)) { continue; } ?>
        <form method="post" action="<?= e(url('action=workflow_fire')) ?>" style="margin-bottom:8px;">
          <?= \App\Core\Csrf::field() ?>
          <input type="hidden" name="entity_type" value="research_application">
          <input type="hidden" name="entity_id" value="<?= (int) $app['id'] ?>">
          <input type="hidden" name="transition" value="<?= e($t['name']) ?>">
          <div style="display:flex;gap:8px;align-items:center;">
            <input type="text" name="comment" placeholder="Optional comment" style="flex:1;">
            <button class="btn btn-outline btn-sm" type="submit"><?= e($t['name']) ?> → <?= e($t['to_name']) ?></button>
          </div>
        </form>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Evaluations (§22–§24, blind rules §24) -->
    <div class="card">
      <h2 class="mt0">Evaluations</h2>
      <?php
        $minimumEvaluators = 2;
        $requiredEvaluators = max($minimumEvaluators, (int) ($app['required_evaluators'] ?? $minimumEvaluators));
        $assignedEvaluatorIds = [];
        $completedEvaluatorIds = [];
        foreach ($assignments as $as) {
            $evaluatorId = (int) ($as['evaluator_user_id'] ?? $as['id']);
            $assignedEvaluatorIds[$evaluatorId] = true;
            if ($as['status'] === 'completed') {
                $completedEvaluatorIds[$evaluatorId] = true;
            }
        }
        $assignedCount = count($assignedEvaluatorIds);
        $completedCount = count($completedEvaluatorIds);
        $progressPercent = min(100, round(($completedCount / $minimumEvaluators) * 100));
      ?>
      <div style="margin-bottom:16px;padding:12px;background:var(--bg-tint,#f6f8fa);border-radius:var(--r-md);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
          <span style="font-weight:600;">Evaluation Progress</span>
          <span><?= $completedCount ?> / <?= $minimumEvaluators ?> minimum completed</span>
        </div>
        <div style="height:8px;background:var(--line);border-radius:var(--r-pill);overflow:hidden;">
          <div style="height:100%;background:var(--brand);width:<?= $progressPercent ?>%;transition:width var(--t-med);"></div>
        </div>
        <?php if ($assignedCount < $requiredEvaluators): ?>
          <div style="margin-top:8px;font-size:13px;color:var(--muted);">
            <?= $requiredEvaluators - $assignedCount ?> evaluator(s) still need to be assigned.
          </div>
        <?php elseif ($completedCount < $minimumEvaluators): ?>
          <div style="margin-top:8px;font-size:13px;color:var(--muted);">
            <?= $minimumEvaluators - $completedCount ?> independent evaluation(s) still needed to advance.
          </div>
        <?php elseif ($completedCount < $assignedCount): ?>
          <div style="margin-top:8px;font-size:13px;color:var(--ok);">
            Minimum met. Additional assigned evaluations may remain pending, but do not block advancement.
          </div>
        <?php else: ?>
          <div style="margin-top:8px;font-size:13px;color:var(--ok);">
            Minimum independent evaluations completed.
          </div>
        <?php endif; ?>
      </div>

      <?php if (!$assignments): ?><p class="muted">No evaluators assigned yet.</p><?php else: ?>
      <table class="tbl">
        <tr><th>Evaluator</th><th>Status</th><th>Deadline</th><th>Actions</th></tr>
        <?php foreach ($assignments as $as): ?>
        <tr>
          <td><?= $canSeeEvaluatorNames ? e($as['evaluator_name']) : '<span class="muted">Evaluator ' . ((int) $as['id'] % 100) . '</span>' ?></td>
          <td><span class="badge badge-info"><?= e(ucfirst($as['status'])) ?></span></td>
          <td><?= e(format_date($as['deadline'])) ?></td>
          <td class="actions">
            <?php if (Gate::allows('research.assign') && in_array($as['status'], ['assigned', 'accepted'], true)): ?>
              <form method="post" action="<?= e(url('action=send_reminder')) ?>" data-confirm="Send reminder to evaluator?" style="display:inline;">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="assignment_id" value="<?= (int) $as['id'] ?>">
                <button class="btn btn-sm btn-outline" type="submit" title="Send reminder">🔔</button>
              </form>
              <button class="btn btn-sm btn-outline" onclick="showExtendDeadline(<?= (int) $as['id'] ?>, '<?= e(format_date($as['deadline'])) ?>')" title="Extend deadline">📅</button>
              <button class="btn btn-sm btn-outline" onclick="showReassign(<?= (int) $as['id'] ?>, '<?= e($canSeeEvaluatorNames ? $as['evaluator_name'] : 'Evaluator') ?>')" title="Reassign">🔄</button>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </table>
      <?php endif; ?>

      <!-- Extend Deadline Modal -->
      <div id="extendDeadlineModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:1000;">
        <div style="background:white;padding:20px;max-width:400px;margin:100px auto;border-radius:8px;">
          <h3>Extend Evaluation Deadline</h3>
          <form method="post" action="<?= e(url('action=extend_deadline')) ?>">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="assignment_id" id="extendAssignmentId">
            <div class="form-row">
              <div><label>New deadline</label><input type="date" name="new_deadline" id="newDeadline" required></div>
            </div>
            <div class="form-row">
              <div><label>Reason</label><input type="text" name="reason" placeholder="Optional reason"></div>
            </div>
            <div class="form-actions">
              <button class="btn" type="submit">Extend</button>
              <button class="btn btn-outline" type="button" onclick="closeExtendDeadline()">Cancel</button>
            </div>
          </form>
        </div>
      </div>

      <!-- Reassign Modal -->
      <div id="reassignModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:1000;">
        <div style="background:white;padding:20px;max-width:500px;margin:100px auto;border-radius:8px;">
          <h3>Reassign Evaluator</h3>
          <p>Current evaluator: <strong id="currentEvaluatorName"></strong></p>
          <form method="post" action="<?= e(url('action=reassign_evaluator')) ?>">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="assignment_id" id="reassignAssignmentId">
            <div class="form-row">
              <div>
                <label>New evaluator</label>
                <select name="new_evaluator_id" id="newEvaluatorSelect" required>
                  <option value="">Select evaluator...</option>
                  <?php
                  $collegeEvaluators = \App\Core\Database::all(
                    'SELECT u.id, u.full_name, d.name as department_name
                     FROM users u
                     JOIN research_evaluators re ON re.user_id = u.id
                     LEFT JOIN departments d ON d.id = u.department_id
                     WHERE u.status = "active" AND u.college_id = ?
                     ORDER BY u.full_name',
                    [(int) $app['college_id']]
                  );
                  foreach ($collegeEvaluators as $ev): ?>
                    <option value="<?= (int) $ev['id'] ?>"><?= e($ev['full_name']) ?> (<?= e($ev['department_name'] ?? '—') ?>)</option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="form-row">
              <div><label>Reason</label><input type="text" name="reason" required placeholder="Reason for reassignment"></div>
            </div>
            <div class="form-actions">
              <button class="btn" type="submit">Reassign</button>
              <button class="btn btn-outline" type="button" onclick="closeReassign()">Cancel</button>
            </div>
          </form>
        </div>
      </div>

      <script>
      function showExtendDeadline(assignmentId, currentDeadline) {
        document.getElementById('extendAssignmentId').value = assignmentId;
        document.getElementById('newDeadline').value = currentDeadline;
        document.getElementById('extendDeadlineModal').style.display = 'block';
      }
      function closeExtendDeadline() {
        document.getElementById('extendDeadlineModal').style.display = 'none';
      }
      function showReassign(assignmentId, evaluatorName) {
        document.getElementById('reassignAssignmentId').value = assignmentId;
        document.getElementById('currentEvaluatorName').textContent = evaluatorName;
        document.getElementById('reassignModal').style.display = 'block';
      }
      function closeReassign() {
        document.getElementById('reassignModal').style.display = 'none';
      }
      </script>

      <?php if ($evaluations): ?>
        <?php foreach ($evaluations as $ev): ?>
          <h3>Evaluation #<?= (int) $ev['id'] ?> (round <?= (int) $ev['evaluation_round'] ?>)
            <?php if ($canSeeEvaluatorNames): ?><span class="muted" style="font-weight:400;font-size:13px;">— <?= e($ev['evaluator_name']) ?></span><?php endif; ?></h3>
          <?php if ($ev['status'] === 'submitted'): ?>
          <dl class="kv">
            <dt>Weighted score</dt><dd><strong><?= e((string) $ev['weighted_score']) ?></strong> / 100</dd>
            <dt>Recommendation</dt><dd><?= e(ucfirst(str_replace('_', ' ', (string) $ev['recommendation']))) ?></dd>
          </dl>
          <?php if (trim((string) $ev['strengths'])): ?><p><strong>Strengths:</strong> <?= e($ev['strengths']) ?></p><?php endif; ?>
          <?php if (trim((string) $ev['weaknesses'])): ?><p><strong>Weaknesses:</strong> <?= e($ev['weaknesses']) ?></p><?php endif; ?>
          <?php if (!empty($researcherComments[(int) $ev['id']])): ?>
            <h4 style="margin-bottom:4px;">Comments for the researcher</h4>
            <?php foreach ($researcherComments[(int) $ev['id']] as $c): ?>
              <div style="border-left:3px solid var(--border,#d0d7de);padding:4px 10px;margin-bottom:6px;">
                <p style="margin:0;white-space:pre-wrap;"><?= e($c['comment']) ?></p>
                <span class="muted" style="font-size:12px;"><?= e($c['author_name'] ?? 'Evaluator') ?> · <?= e(format_datetime($c['created_at'])) ?></span>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
          <?php if ($canSeeEvaluatorNames): ?><a class="btn btn-outline btn-sm" href="<?= e(url('action=evaluation_view&id=' . $ev['id'])) ?>">Full evaluation</a><?php endif; ?>
          <?php else: ?><p class="muted">Draft — not yet submitted.</p><?php endif; ?>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- Revision cycle (§8, §9, §25) -->
    <?php
      $allResearcherComments = [];
      foreach ($researcherComments as $cs) { foreach ($cs as $c) { $allResearcherComments[] = $c; } }
      $canRequestRevision = Gate::allows('research.request_revision')
          && in_array($app['status_code'], ['EVALUATION_COMPLETED', 'COLLEGE_REVIEW', 'UNIVERSITY_REVIEW'], true);
    ?>
    <div class="card" id="revision">
      <h2 class="mt0">Revision cycle <span class="muted" style="font-size:13px;font-weight:400;">(§8 — versions preserved, §9)</span></h2>

      <?php if ($canRequestRevision): ?>
      <form method="post" action="<?= e(url('action=application_request_revision')) ?>" style="margin-bottom:14px;">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="id" value="<?= (int) $app['id'] ?>">
        <label>Request revision — required corrections <span class="req">*</span></label>
        <textarea name="reason" required style="min-height:60px;" placeholder="e.g. Improve the methodology section; clarify the sampling frame."></textarea>
        <div class="form-row">
          <div><label>Response deadline</label><input type="date" name="deadline"></div>
          <div style="display:flex;align-items:flex-end;"><button class="btn btn-outline" type="submit">Request revision</button></div>
        </div>
      </form>
      <?php endif; ?>

      <?php if (!$revisionRequests): ?>
        <p class="muted">No revision requests for this research yet.</p>
      <?php else: ?>
        <?php foreach ($revisionRequests as $rr): ?>
        <div style="border-top:1px solid #eee;padding-top:10px;margin-top:10px;">
          <h3 style="margin-bottom:4px;">
            <?= e(format_date($rr['requested_at'])) ?>
            <span class="badge <?= $rr['status'] === 'open' ? 'badge-warning' : ($rr['status'] === 'cancelled' ? 'badge-danger' : 'badge-secondary') ?>"><?= e($rr['status']) ?></span>
          </h3>
          <p style="white-space:pre-wrap;margin-top:4px;"><?= e($rr['request_reason'] ?? '') ?></p>
          <p class="muted" style="font-size:12px;">
            Requested by <?= e($rr['requested_by_name'] ?? '—') ?>
            <?= $rr['deadline'] ? ' · deadline ' . e(format_date($rr['deadline'])) : '' ?>
          </p>

          <?php $responses = $revisionResponses[(int) $rr['id']] ?? []; ?>
          <?php if ($responses): ?>
            <h4>Researcher responses</h4>
            <?php foreach ($responses as $resp): ?>
              <div style="border-left:3px solid var(--border,#d0d7de);padding:4px 10px;margin-bottom:6px;">
                <?php if (!empty($resp['commented_on'])): ?>
                  <p class="muted" style="margin:0;font-size:13px;"><strong>On comment:</strong> <?= e($resp['commented_on']) ?></p>
                <?php else: ?>
                  <p class="muted" style="margin:0;font-size:13px;"><strong>Overall response</strong></p>
                <?php endif; ?>
                <p style="margin:4px 0;white-space:pre-wrap;"><?= e($resp['response_text']) ?></p>
                <span class="muted" style="font-size:12px;"><?= e($resp['respondent_name'] ?? '') ?> · <?= e(format_datetime($resp['submitted_at'])) ?></span>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>

          <?php if ($isPi && in_array($rr['status'], ['open', 'responded'], true) && $app['status_code'] === 'REVISION_REQUIRED'): ?>
          <form method="post" action="<?= e(url('action=application_respond_revision')) ?>" style="margin-top:8px;">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="application_id" value="<?= (int) $app['id'] ?>">
            <input type="hidden" name="revision_request_id" value="<?= (int) $rr['id'] ?>">
            <label>In response to comment</label>
            <select name="comment_id">
              <option value="">— Overall response to this request —</option>
              <?php foreach ($allResearcherComments as $c): ?>
                <option value="<?= (int) $c['id'] ?>"><?= e(mb_substr((string) $c['comment'], 0, 90)) ?></option>
              <?php endforeach; ?>
            </select>
            <label>Your response <span class="req">*</span></label>
            <textarea name="response" required style="min-height:60px;" placeholder="The methodology section has been revised and additional sampling information has been added."></textarea>
            <button class="btn btn-outline btn-sm" type="submit">Submit response</button>
          </form>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <?php if ($isPi && $app['status_code'] === 'REVISION_REQUIRED'): ?>
        <form method="post" action="<?= e(url('action=application_submit_revision')) ?>" data-confirm="Submit the corrected version for re-evaluation? Earlier versions remain preserved." style="margin-top:12px;">
          <?= \App\Core\Csrf::field() ?>
          <input type="hidden" name="id" value="<?= (int) $app['id'] ?>">
          <p class="muted" style="font-size:13px;">Respond to every request and upload the revised document(s) above — then submit. This creates <strong>version <?= (int) $app['version'] + 1 ?></strong>; version <?= (int) $app['version'] ?> stays preserved.</p>
          <button class="btn" type="submit">Submit version <?= (int) $app['version'] + 1 ?> for re-evaluation</button>
        </form>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <!-- College-level decision (forwarding to university) -->
    <?php if (Gate::allows('research.screen') && in_array($app['status_code'], ['AWAITING_COLLEGE_DECISION', 'COLLEGE_REVIEW'], true)): ?>
    <div class="card" id="college-decision">
      <h2 class="mt0">College Decision</h2>
      <p class="muted" style="margin-bottom:12px;">Record the college-level outcome and forward eligible proposals to university evaluation.</p>

      <!-- Evaluation Summary -->
      <?php $scoreCount = 0; $avgScore = 0.0; if ($evaluations): ?>
      <div style="background:var(--bg-soft);padding:12px;border-radius:4px;margin-bottom:16px;">
        <h3 style="margin-top:0;">Consolidated Evaluation Summary</h3>
        <?php
        $submittedByEvaluator = [];
        foreach ($evaluations as $evaluation) {
          if ($evaluation['status'] !== 'submitted') {
            continue;
          }
          $evaluatorKey = (int) ($evaluation['evaluator_user_id'] ?? $evaluation['assignment_id'] ?? $evaluation['id']);
          $submittedByEvaluator[$evaluatorKey] = $evaluation;
        }
        $submittedEvals = array_values($submittedByEvaluator);
        $totalScore = 0.0;
        $scoreCount = 0;
        $recommendations = [];
        foreach ($submittedEvals as $ev) {
          if ($ev['weighted_score'] !== null) {
            $totalScore += (float) $ev['weighted_score'];
            $scoreCount++;
          }
          if ($ev['recommendation']) {
            $recommendations[] = $ev['recommendation'];
          }
        }
        $avgScore = $scoreCount > 0 ? $totalScore / $scoreCount : 0.0;
        ?>
        <dl class="kv">
          <dt>Independent evaluations submitted</dt><dd><?= count($submittedEvals) ?> / 2 minimum</dd>
          <dt>Average weighted score (advisory)</dt><dd><strong><?= number_format($avgScore, 1) ?></strong> / 100</dd>
          <dt>Recommendations</dt><dd>
            <?php if ($recommendations): ?>
              <?php
              $recCounts = array_count_values($recommendations);
              foreach ($recCounts as $rec => $count): ?>
                <?= e(ucfirst(str_replace('_', ' ', $rec))) ?>: <?= $count ?><?= $count < count($recommendations) ? ', ' : '' ?>
              <?php endforeach; ?>
            <?php else: ?>
              No recommendations recorded
            <?php endif; ?>
          </dd>
        </dl>
        <?php if ($scoreCount < 2): ?>
        <div class="alert alert-warning" style="margin-top:8px;">
          At least two independent evaluations must be completed before forwarding. Score thresholds and any additional assigned evaluations are advisory.
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <form method="post" action="<?= e(url('action=college_decision')) ?>">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="id" value="<?= (int) $app['id'] ?>">
        <label>Decision <span class="req">*</span></label>
        <select name="decision" required>
          <option value="">Select decision...</option>
          <option value="forward_to_university" <?= $scoreCount >= 2 ? '' : 'disabled' ?>>
            Forward to university evaluation
            <?php if ($scoreCount < 2): ?>
              (requires 2 independent evaluations)
            <?php endif; ?>
          </option>
          <option value="return_for_revision">Return for revision</option>
          <option value="reject_at_college">Reject at college level</option>
        </select>
        <label>Comments / Reason</label>
        <textarea name="comment" style="min-height:60px;" placeholder="Provide justification for this decision..."></textarea>
        <div class="form-actions">
          <button class="btn" type="submit">Record decision</button>
        </div>
      </form>
    </div>
    <?php endif; ?>

    <!-- University research committee (§11–§12; Rules 8 & 9) -->
    <div class="card" id="committee">
      <h2 class="mt0">University Research Committee <span class="muted" style="font-size:13px;font-weight:400;">(§12)</span></h2>
      <?php if ($assignableCommittees): ?>
        <form method="post" action="<?= e(url('action=app_committee_assign')) ?>" style="margin-bottom:12px;">
          <?= \App\Core\Csrf::field() ?>
          <input type="hidden" name="application_id" value="<?= (int) $app['id'] ?>">
          <label>Assign to committee <span class="req">*</span></label>
          <select name="committee_id" required>
            <?php foreach ($assignableCommittees as $cm): ?>
              <option value="<?= (int) $cm['id'] ?>"><?= e($cm['name']) ?> (<?= (int) $cm['member_count'] ?> members)</option>
            <?php endforeach; ?>
          </select>
          <div class="form-row">
            <div><label>Planned meeting date</label><input type="date" name="meeting_date"></div>
            <div><label>Notes</label><input type="text" name="notes" placeholder="Optional"></div>
          </div>
          <button class="btn btn-outline" type="submit">Assign to research committee</button>
        </form>
      <?php endif; ?>

      <?php if ($committeeAssignment): ?>
        <dl class="kv">
          <dt>Committee</dt><dd><strong><?= e($committeeAssignment['committee_name']) ?></strong> (<?= e($committeeAssignment['committee_code']) ?>)</dd>
          <dt>Assigned</dt><dd><?= e(format_datetime($committeeAssignment['assigned_at'])) ?> by <?= e($committeeAssignment['assigned_by_name'] ?? '—') ?></dd>
          <?php if ($committeeAssignment['meeting_date']): ?><dt>Meeting</dt><dd><?= e(format_date($committeeAssignment['meeting_date'])) ?></dd><?php endif; ?>
        </dl>
        <?php $members = \App\Models\CommitteeModel::members((int) $committeeAssignment['committee_id']); ?>
        <p class="muted" style="font-size:13px;">Members: <?= $members ? e(implode(', ', array_map(static fn ($m) => $m['full_name'] . ' (' . $m['member_role'] . ')', $members))) : '—' ?></p>

        <?php if ($committeeDecisions): ?>
          <h4>Committee decisions (Rule 8)</h4>
          <?php foreach ($committeeDecisions as $cd): ?>
            <div style="border-left:3px solid var(--border,#d0d7de);padding:4px 10px;margin-bottom:6px;">
              <strong><?= e(str_replace('_', ' ', $cd['decision'])) ?></strong>
              <span class="muted" style="font-size:12px;">— <?= e($cd['committee_name']) ?> · <?= e($cd['decided_by_name'] ?? '—') ?> · <?= e(format_datetime($cd['decided_at'])) ?></span>
              <?php if ($cd['recommended_amount'] !== null): ?>
                <p style="margin:4px 0;"><strong>Funding recommendation (Rule 9):</strong> <?= e(format_money($cd['recommended_amount'])) ?></p>
              <?php endif; ?>
              <?php if ($cd['recommendation_note']): ?><p class="muted" style="margin:2px 0;font-size:13px;"><?= e($cd['recommendation_note']) ?></p><?php endif; ?>
              <?php if ($cd['comments']): ?><p style="margin:4px 0;white-space:pre-wrap;"><?= e($cd['comments']) ?></p><?php endif; ?>
              <?php if ($cd['meeting_date']): ?><span class="muted" style="font-size:12px;">Meeting: <?= e(format_date($cd['meeting_date'])) ?></span><?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <p class="muted">No committee decision recorded yet.</p>
        <?php endif; ?>

        <?php if ($isCommitteeMember && $app['status_code'] === 'UNIVERSITY_COMMITTEE_REVIEW'): ?>
          <form method="post" action="<?= e(url('action=app_committee_decide')) ?>" style="margin-top:10px;border-top:1px solid #eee;padding-top:10px;">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="application_id" value="<?= (int) $app['id'] ?>">
            <label>Committee decision <span class="req">*</span></label>
            <select name="decision" required>
              <option value="approve">Approve</option>
              <option value="approve_with_conditions">Approve with conditions</option>
              <option value="revision_required">Revision required</option>
              <option value="reject">Reject</option>
            </select>
            <div class="form-row">
              <div><label>Funding recommendation (ETB)</label><input type="number" step="0.01" min="0" name="recommended_amount" placeholder="Optional"></div>
              <div><label>Meeting date</label><input type="date" name="meeting_date"></div>
            </div>
            <label>Recommendation note</label>
            <input type="text" name="recommendation_note" placeholder="e.g. Recommended subject to college-level revisions">
            <label>Decision comments</label>
            <textarea name="comments" style="min-height:60px;"></textarea>
            <label>Decision reference</label>
            <input type="text" name="decision_reference" placeholder="e.g. URC/2026/17">
            <button class="btn" type="submit">Record committee decision</button>
          </form>
        <?php elseif ($app['status_code'] === 'UNIVERSITY_COMMITTEE_REVIEW'): ?>
          <p class="muted" style="font-size:13px;">Awaiting the committee's decision — the Research Directorate approves after the committee recommendation is recorded.</p>
        <?php endif; ?>
      <?php elseif (!in_array($app['status_code'], ['UNIVERSITY_REVIEW', 'ETHICS_REVIEW'], true)): ?>
        <p class="muted">No committee assigned. The Research Directorate assigns committees during university review.</p>
      <?php endif; ?>
    </div>

    <!-- Budget allocation (§28 step 32; Rule 13) -->
    <div class="card" id="budget">
      <h2 class="mt0">Budget allocation <span class="muted" style="font-size:13px;font-weight:400;">(§28)</span></h2>
      <dl class="kv">
        <dt>Requested</dt><dd><?= e(format_money($app['budget_requested'])) ?></dd>
        <?php if ($app['budget_approved_amount'] !== null): ?>
          <dt>Approved allocation</dt><dd><strong><?= e(format_money($app['budget_approved_amount'])) ?></strong></dd>
          <dt>Approved by</dt><dd><?= e($budgetApproverName ?? '—') ?> <?= $app['budget_approved_at'] ? '· ' . e(format_datetime($app['budget_approved_at'])) : '' ?></dd>
        <?php else: ?>
          <dt>Approved allocation</dt><dd class="muted">not recorded yet</dd>
        <?php endif; ?>
        <?php $rec = $committeeDecisions ? $committeeDecisions[count($committeeDecisions) - 1] : null; ?>
        <?php if ($rec !== null && $rec['recommended_amount'] !== null): ?>
          <dt>Committee recommendation</dt><dd><?= e(format_money($rec['recommended_amount'])) ?> <span class="muted" style="font-size:12px;">(advisory — Rule 9)</span></dd>
        <?php endif; ?>
      </dl>
      <?php if ($canRecordBudget): ?>
        <form method="post" action="<?= e(url('action=application_budget')) ?>" style="margin-top:8px;">
          <?= \App\Core\Csrf::field() ?>
          <input type="hidden" name="id" value="<?= (int) $app['id'] ?>">
          <label>Approved amount (max <?= e(format_money($budgetCap)) ?>) <span class="req">*</span></label>
          <input type="number" step="0.01" min="0.01" max="<?= (float) $budgetCap ?>" name="approved_amount" required value="<?= e((string) (float) $app['budget_requested']) ?>">
          <label>Note</label>
          <input type="text" name="note" placeholder="Optional decision note">
          <button class="btn" type="submit">Record approval &amp; approve budget</button>
          <p class="muted" style="font-size:12px;">The system rejects allocations above the available budget (Rule 13).</p>
        </form>
      <?php endif; ?>
    </div>

    <!-- Research agreement (§13; Rules 10–12) -->
    <div class="card" id="agreement">
      <h2 class="mt0">Research agreement <span class="muted" style="font-size:13px;font-weight:400;">(§13 — versions preserved)</span></h2>
      <?php if (!$agreementVersions): ?>
        <p class="muted">No agreement yet. The Research Directorate issues it after budget approval.</p>
      <?php else: ?>
        <?php foreach ($agreementVersions as $agv): ?>
          <div style="border-left:3px solid <?= $agv['status'] === 'signed' ? '#2da44e' : 'var(--border,#d0d7de)' ?>;padding:6px 10px;margin-bottom:8px;">
            <strong>v<?= (int) $agv['version'] ?></strong> · <?= e($agv['agreement_number']) ?>
            <span class="badge <?= $agv['status'] === 'signed' ? 'badge-success' : ($agv['status'] === 'issued' ? 'badge-warning' : 'badge-secondary') ?>"><?= e($agv['status']) ?></span>
            <span class="muted" style="font-size:12px;"><?= e($agv['title']) ?></span>
            <dl class="kv" style="margin-top:4px;">
              <dt>Agreed amount</dt><dd><?= e(format_money($agv['agreed_amount'])) ?></dd>
              <dt>Issued</dt><dd><?= $agv['issued_at'] ? e(format_datetime($agv['issued_at'])) . ' by ' . e($agv['issued_by_name'] ?? '—') : '—' ?></dd>
              <?php if ($agv['status'] === 'signed'): ?>
                <dt>Signed</dt><dd><?= e(format_datetime($agv['signed_at'])) ?> by <?= e($agv['signature_name'] ?? $agv['signed_by_name'] ?? '—') ?></dd>
              <?php endif; ?>
            </dl>
            <?php if ($agv['terms']): ?><p style="white-space:pre-wrap;font-size:14px;"><?= e($agv['terms']) ?></p><?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>

      <?php if (Gate::allows('research.manage') && in_array($app['status_code'], ['BUDGET_APPROVED', 'FUNDED'], true) && ($agreement === null || $agreement['status'] !== 'signed')): ?>
        <form method="post" action="<?= e(url('action=agreement_issue')) ?>" style="margin-top:8px;border-top:1px solid #eee;padding-top:10px;">
          <?= \App\Core\Csrf::field() ?>
          <input type="hidden" name="application_id" value="<?= (int) $app['id'] ?>">
          <label>Agreement title</label>
          <input type="text" name="title" placeholder="Default: Research Agreement — <?= e(mb_substr((string) $app['title'], 0, 60)) ?>">
          <label>Terms <span class="req">*</span></label>
          <textarea name="terms" required style="min-height:80px;" placeholder="Terms and conditions of the research agreement…"></textarea>
          <label>Agreed amount (max <?= e(format_money($app['budget_approved_amount'] ?? $app['budget_requested'])) ?>)</label>
          <input type="number" step="0.01" min="0.01" name="agreed_amount" value="<?= e((string) (float) ($app['budget_approved_amount'] ?? $app['budget_requested'])) ?>">
          <button class="btn" type="submit"><?= $agreement !== null && $agreement['status'] === 'issued' ? 'Re-issue as version ' . ((int) $agreement['version'] + 1) : 'Issue agreement to researcher' ?></button>
        </form>
      <?php endif; ?>

      <?php if ($isPi && $agreement !== null && $agreement['status'] === 'issued' && in_array($app['status_code'], ['BUDGET_APPROVED', 'FUNDED'], true)): ?>
        <form method="post" action="<?= e(url('action=agreement_sign')) ?>" data-confirm="Sign this research agreement? The research becomes activatable afterwards." style="margin-top:8px;border-top:1px solid #eee;padding-top:10px;">
          <?= \App\Core\Csrf::field() ?>
          <input type="hidden" name="agreement_id" value="<?= (int) $agreement['id'] ?>">
          <label>Type your full name as signature <span class="req">*</span></label>
          <input type="text" name="signature_name" required value="<?= e($app['pi_name'] ?? '') ?>">
          <label>Note (optional)</label>
          <input type="text" name="signature_note">
          <button class="btn" type="submit">Sign agreement</button>
        </form>
      <?php elseif ($isPi && $agreement !== null && $agreement['status'] === 'signed'): ?>
        <div class="alert alert-success">Signed by <?= e($agreement['signature_name'] ?? '—') ?> on <?= e(format_datetime($agreement['signed_at'])) ?>. The research can now be activated (Rule 11).</div>
      <?php endif; ?>
    </div>

    <!-- Project activation (§35) -->
    <?php $proj = \App\Models\ProjectModel::findByApplication((int) $app['id']); ?>
    <?php if (Gate::allows('project.activate') && in_array($app['status_code'], ['FUNDED', 'ACTIVE', 'BUDGET_APPROVED'], true)): ?>
    <div class="card">
      <h2 class="mt0">Implementation (§35)</h2>
      <?php if ($proj === null): ?>
        <?php if ($app['status_code'] === 'FUNDED'): ?>
          <form method="post" action="<?= e(url('action=project_activate')) ?>" data-confirm="Activate the research project and generate its number?">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="application_id" value="<?= (int) $app['id'] ?>">
            <p class="muted" style="font-size:13px;">Creates project <strong>MWU-RPEE-YYYY-NNNN</strong>, seeds the team, milestones (from the work plan) and the initial budget.</p>
            <button class="btn" type="submit">Activate project</button>
          </form>
        <?php else: ?>
          <p class="muted">Project can be activated once funds are released (current: <?= e(status_label($app['status_code'])) ?>).</p>
        <?php endif; ?>
      <?php else: ?>
        <dl class="kv">
          <dt>Project number</dt><dd><a href="<?= e(url('action=project_view&id=' . $proj['id'])) ?>"><strong><?= e($proj['project_number']) ?></strong></a></dd>
          <dt>Status</dt><dd><span class="badge <?= status_badge($proj['status_code']) ?>"><?= e(status_label($proj['status_code'])) ?></span></dd>
        </dl>
      <?php endif; ?>
    </div>
    <?php elseif ($proj !== null): ?>
    <div class="card">
      <h2 class="mt0">Implementation (§35)</h2>
      <dl class="kv">
        <dt>Project number</dt><dd><a href="<?= e(url('action=project_view&id=' . $proj['id'])) ?>"><strong><?= e($proj['project_number']) ?></strong></a></dd>
        <dt>Status</dt><dd><span class="badge <?= status_badge($proj['status_code']) ?>"><?= e(status_label($proj['status_code'])) ?></span></dd>
      </dl>
    </div>
    <?php endif; ?>

    <!-- Ethics (§30) -->
    <?php
      $ethics = \App\Models\EthicsModel::findByResearchApplication((int) $app['id']);
    ?>
    <div class="card">
      <h2 class="mt0">Ethics (§30–§34)</h2>
      <p class="muted" style="font-size:13px;margin-bottom:8px;">Declared requirement: <strong><?= e(ucfirst((string) $app['requires_ethics'])) ?></strong></p>
      <?php if ($ethics === null): ?>
        <?php if ($isPi && Gate::allows('ethics.submit')): ?>
          <form method="post" action="<?= e(url('action=ethics_create')) ?>">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="research_application_id" value="<?= (int) $app['id'] ?>">
            <div class="form-row">
              <div><label>Participant count</label><input type="number" name="participant_count" min="0"></div>
              <div><label>Risk level</label>
                <select name="risk_level">
                  <option value="">—</option>
                  <?php foreach (['minimal','low','moderate','high'] as $rl): ?><option value="<?= e($rl) ?>"><?= e(ucfirst($rl)) ?></option><?php endforeach; ?>
                </select>
              </div>
            </div>
            <label>Consent process (brief)</label>
            <textarea name="consent_process" style="min-height:60px;"></textarea>
            <button class="btn btn-outline" type="submit">Create ethics application</button>
          </form>
        <?php else: ?>
          <p class="muted">No ethics application created.</p>
        <?php endif; ?>
      <?php else: ?>
        <dl class="kv">
          <dt>Ethics number</dt><dd><a href="<?= e(url('action=ethics_view&id=' . $ethics['id'])) ?>"><?= e($ethics['application_number']) ?></a></dd>
          <dt>Status</dt><dd><span class="badge <?= in_array($ethics['status'], ['approved','approved_with_conditions','exempt'], true) ? 'badge-success' : (in_array($ethics['status'], ['rejected','expired'], true) ? 'badge-danger' : 'badge-info') ?>"><?= e(ucfirst(str_replace('_', ' ', $ethics['status']))) ?></span></dd>
          <dt>Approved until</dt><dd><?= e(format_date($ethics['approved_until'])) ?></dd>
        </dl>
      <?php endif; ?>
    </div>

    <!-- Workflow history (§94) -->
    <div class="card">
      <h2 class="mt0">Workflow history</h2>
      <ul class="timeline">
        <?php foreach ($history as $h): ?>
        <li class="done">
          <div class="tl-head"><?= e($h['from_status'] ?? '—') ?> → <?= e($h['to_status'] ?? '—') ?></div>
          <div class="tl-meta"><?= e(format_datetime($h['created_at'])) ?> · <?= e($h['user_name'] ?? 'system') ?>
            <?= $h['comment'] ? '· ' . e($h['comment']) : '' ?></div>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
</div>

<script>
  window.applicationId = <?= (int) $app['id'] ?>;
</script>
<script src="<?= e(base_url('assets/js/team.js')) ?>"></script>

<?php include APP_PATH . '/views/partials/footer.php'; ?>
