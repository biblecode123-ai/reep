<?php
use App\Core\Auth;
use App\Core\Gate;
use App\Core\Csrf;
$title = 'Project: ' . $project['project_number'];
include APP_PATH . '/views/partials/header.php';
$isPi = (int) $project['pi_user_id'] === Auth::id();
$canReport = $isPi || Gate::allows('project.report');
$currency = $budget['currency'] ?? 'ETB';
$remaining = $budget ? (float) $budget['approved_amount'] - $released : 0;
?>
<div class="page-head">
  <div>
    <h1><?= e($project['title']) ?></h1>
    <p><strong><?= e($project['project_number']) ?></strong> ·
      <span class="badge <?= status_badge($project['status_code']) ?>"><?= e($project['stage_label'] ?? status_label($project['status_code'])) ?></span>
      · <a href="<?= e(url('action=application_view&id=' . $project['application_id'])) ?>">Source application <?= e($project['application_number']) ?></a></p>
  </div>
  <?php if (!empty(array_filter($transitions, fn($t) => $t['permitted']))): ?>
  <div style="display:flex;gap:8px;flex-wrap:wrap;">
    <?php foreach ($transitions as $t): ?>
      <?php if (!$t['permitted']) continue; ?>
      <?php if (in_array($t['name'], ['Suspend project', 'Resume project', 'Cancel project', 'Terminate project'], true)) continue; ?>
      <form method="post" action="<?= e(url('action=workflow_fire_project')) ?>">
        <?= Csrf::field() ?>
        <input type="hidden" name="entity_type" value="research_project">
        <input type="hidden" name="entity_id" value="<?= (int) $project['id'] ?>">
        <input type="hidden" name="transition" value="<?= e($t['name']) ?>">
        <button class="btn btn-outline btn-sm" type="submit"><?= e($t['name']) ?></button>
      </form>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<div class="grid grid-4">
  <div class="stat"><div class="num"><?= e(format_date($project['planned_start'])) ?></div><div class="lbl">Planned start</div></div>
  <div class="stat"><div class="num"><?= e(format_date($project['planned_end'])) ?></div><div class="lbl">Planned end</div></div>
  <div class="stat"><div class="num"><?= e(format_money($budget['approved_amount'] ?? $project['approved_budget'], $currency)) ?></div><div class="lbl">Approved budget</div></div>
  <div class="stat"><div class="num"><?= e(format_money($released, $currency)) ?></div><div class="lbl">Released</div></div>
</div>

<div class="grid grid-2">
  <div>
    <div class="card">
      <h2 class="mt0">Project overview (§39)</h2>
      <dl class="kv">
        <dt>Principal investigator</dt><dd><?= e($project['pi_name']) ?></dd>
        <dt>College / Dept</dt><dd><?= e($project['college_name'] ?? '—') ?> / <?= e($project['department_name'] ?? '—') ?></dd>
        <dt>Type / Theme</dt><dd><?= e($project['research_type_name'] ?? '—') ?> / <?= e($project['research_theme_name'] ?? '—') ?></dd>
        <dt>Duration</dt><dd><?= e((string) ($project['duration_months'] ?? '—')) ?> months</dd>
        <dt>Call</dt><dd><?= e($project['call_title'] ?? '—') ?></dd>
        <dt>Actual start</dt><dd><?= e(format_date($project['actual_start'])) ?></dd>
        <dt>Actual end</dt><dd><?= e(format_date($project['actual_end'])) ?></dd>
      </dl>
    </div>

    <!-- Milestones (§40) -->
    <div class="card" id="milestones">
      <h2 class="mt0">Milestones</h2>
      <?php if (!$milestones): ?><p class="muted">No milestones. Seeded from the application work plan at activation.</p><?php else: ?>
      <table class="tbl">
        <tr><th>Milestone</th><th>Due</th><th>Progress</th><th>Status</th></tr>
        <?php foreach ($milestones as $m): ?>
        <tr>
          <td><?= e($m['milestone_name']) ?><div class="muted" style="font-size:12px;"><?= e($m['responsible_name'] ?? '') ?></div></td>
          <td><?= e(format_date($m['due_date'])) ?></td>
          <td><?= e((string) $m['progress_pct']) ?>%</td>
          <td><span class="badge <?= $m['status'] === 'completed' ? 'badge-success' : (in_array($m['status'], ['delayed', 'cancelled'], true) ? 'badge-danger' : 'badge-info') ?>"><?= e($m['status']) ?></span></td>
        </tr>
        <?php endforeach; ?>
      </table>
      <?php endif; ?>

      <?php if ($canReport && in_array($project['status_code'], ['ACTIVE', 'PROGRESS_MONITORING', 'SUSPENDED'], true)): ?>
      <details style="margin-top:12px;">
        <summary style="cursor:pointer;font-weight:600;">Update a milestone</summary>
        <form method="post" action="<?= e(url('action=project_milestone_update')) ?>" style="margin-top:8px;">
          <?= Csrf::field() ?>
          <label>Milestone</label>
          <select name="id" required>
            <?php foreach ($milestones as $m): ?>
            <option value="<?= (int) $m['id'] ?>"><?= e($m['milestone_name']) ?> (<?= e($m['status']) ?>)</option>
            <?php endforeach; ?>
          </select>
          <div class="form-row">
            <div><label>Status</label>
              <select name="status">
                <?php foreach (['pending', 'in_progress', 'completed', 'delayed'] as $st): ?><option value="<?= e($st) ?>"><?= e($st) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div><label>Progress %</label><input type="number" name="progress_pct" min="0" max="100" step="1"></div>
          </div>
          <button class="btn btn-outline btn-sm" type="submit">Update milestone</button>
        </form>
      </details>
      <?php endif; ?>

      <?php if (Gate::allows('project.activate')): ?>
      <details style="margin-top:8px;">
        <summary style="cursor:pointer;font-weight:600;">Add milestone</summary>
        <form method="post" action="<?= e(url('action=project_milestone_add')) ?>" style="margin-top:8px;">
          <?= Csrf::field() ?>
          <input type="hidden" name="project_id" value="<?= (int) $project['id'] ?>">
          <label>Name</label><input type="text" name="milestone_name" required maxlength="200">
          <div class="form-row">
            <div><label>Due date</label><input type="date" name="due_date"></div>
          </div>
          <button class="btn btn-outline btn-sm" type="submit">Add</button>
        </form>
      </details>
      <?php endif; ?>
    </div>

    <!-- Team (§73) -->
    <div class="card">
      <h2 class="mt0">Research team</h2>
      <table class="tbl">
        <tr><th>Member</th><th>Role</th><th>Joined</th></tr>
        <?php foreach ($members as $m): ?>
        <tr><td><?= e($m['full_name']) ?></td><td><?= e(ucfirst(str_replace('_', ' ', $m['member_role']))) ?></td><td><?= e(format_date($m['joined_at'])) ?></td></tr>
        <?php endforeach; ?>
      </table>
    </div>

    <!-- Documents -->
    <div class="card">
      <h2 class="mt0">Documents</h2>
      <?php if (!$documents): ?><p class="muted">No documents.</p><?php endif; ?>
      <?php foreach ($documents as $d): ?>
      <div class="doc-row">
        <div><?= e($d['type_name']) ?> v<?= (int) $d['version'] ?>
          <span class="muted" style="font-size:12px;"><?= e(format_datetime($d['uploaded_at'])) ?> · <?= e($d['uploader_name'] ?? '') ?></span>
          <?= $d['status'] === 'superseded' ? '<span class="badge badge-secondary">superseded</span>' : '' ?></div>
        <a class="btn btn-outline btn-sm" href="<?= e(base_url('download.php?id=' . $d['id'])) ?>">Download</a>
      </div>
      <?php endforeach; ?>
      <?php if ($isPi || $canManage = Gate::allows('research.manage')): ?>
      <form method="post" action="<?= e(url('action=document_upload')) ?>" enctype="multipart/form-data" style="margin-top:12px;">
        <?= Csrf::field() ?>
        <input type="hidden" name="entity_type" value="research_project">
        <input type="hidden" name="entity_id" value="<?= (int) $project['id'] ?>">
        <div class="form-row">
          <div><label>Document type</label>
            <select name="document_type_id">
              <?php foreach (\App\Models\OrgModel::documentTypes() as $dt): ?>
              <option value="<?= (int) $dt['id'] ?>"><?= e($dt['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div><label>File</label><input type="file" name="document" required></div>
        </div>
        <button class="btn btn-outline btn-sm" type="submit">Upload</button>
      </form>
      <?php endif; ?>
    </div>
  </div>

  <div>
    <!-- Budget & funding (§36–§38) -->
    <div class="card" id="budget">
      <h2 class="mt0">Budget & funding</h2>
      <?php if ($budget === null): ?>
        <p class="muted">No budget record.</p>
      <?php else: ?>
      <dl class="kv">
        <dt>Requested</dt><dd><?= e(format_money($budget['requested_amount'], $currency)) ?></dd>
        <dt>Approved</dt><dd><strong><?= e(format_money($budget['approved_amount'], $currency)) ?></strong></dd>
        <dt>Released</dt><dd><?= e(format_money($released, $currency)) ?></dd>
        <dt>Remaining releasable</dt><dd><?= e(format_money($remaining, $currency)) ?></dd>
        <dt>Budget status</dt><dd><span class="badge <?= $budget['status'] === 'approved' ? 'badge-success' : 'badge-warning' ?>"><?= e(ucfirst(str_replace('_', ' ', $budget['status']))) ?></span></dd>
        <?php if ($budget['approved_by_name']): ?><dt>Approved by</dt><dd><?= e($budget['approved_by_name']) ?></dd><?php endif; ?>
      </dl>
      <?php if ($budgetItems): ?>
      <table class="tbl" style="margin-top:10px;">
        <tr><th>Item</th><th>Requested</th><th>Approved</th></tr>
        <?php foreach ($budgetItems as $bi): ?>
        <tr><td><?= e($bi['description']) ?><div class="muted" style="font-size:12px;"><?= e(ucfirst(str_replace('_', ' ', $bi['item_category']))) ?></div></td>
            <td><?= e(format_money($bi['requested_amount'], $currency)) ?></td>
            <td><?= e(format_money($bi['approved_amount'], $currency)) ?></td></tr>
        <?php endforeach; ?>
      </table>
      <?php endif; ?>
      <?php endif; ?>

      <?php if (Gate::allows('budget.release') && $budget !== null && $budget['status'] === 'approved' && $remaining > 0): ?>
      <form method="post" action="<?= e(url('action=project_release_funds')) ?>" style="margin-top:12px;">
        <?= Csrf::field() ?>
        <input type="hidden" name="project_id" value="<?= (int) $project['id'] ?>">
        <div class="form-row">
          <div><label>Amount to release</label><input type="number" name="amount" step="0.01" min="0.01" max="<?= e((string) $remaining) ?>" required></div>
          <div><label>Reference</label><input type="text" name="reference" placeholder="Voucher / letter ref"></div>
        </div>
        <label>Note</label><input type="text" name="note" maxlength="255">
        <button class="btn" type="submit">Release funds</button>
      </form>
      <?php endif; ?>

      <?php if ($releases): ?>
      <h3>Release history</h3>
      <table class="tbl">
        <tr><th>Date</th><th>Amount</th><th>By</th><th>Reference</th></tr>
        <?php foreach ($releases as $f): ?>
        <tr><td><?= e(format_datetime($f['released_at'])) ?></td>
            <td><?= e(format_money($f['amount'], $currency)) ?></td>
            <td><?= e($f['released_by_name'] ?? '') ?></td>
            <td><?= e($f['release_reference'] ?? '—') ?></td></tr>
        <?php endforeach; ?>
      </table>
      <?php endif; ?>
    </div>

    <!-- Progress reports (§41) -->
    <div class="card" id="reports">
      <h2 class="mt0">Progress reports</h2>
      <?php if (!$reports): ?><p class="muted">No reports submitted.</p><?php else: ?>
      <?php foreach ($reports as $r): ?>
      <div class="doc-row">
        <div>
          <strong><?= e($r['report_number']) ?></strong>
          <span class="badge <?= $r['status'] === 'approved' ? 'badge-success' : (in_array($r['status'], ['rejected'], true) ? 'badge-danger' : 'badge-warning') ?>"><?= e(str_replace('_', ' ', $r['status'])) ?></span>
          <div class="muted" style="font-size:12px;">progress <?= e((string) $r['progress_pct']) ?>% · submitted <?= e(format_datetime($r['submitted_at'])) ?><?= $r['reviewer_name'] ? ' · reviewed by ' . e($r['reviewer_name']) : '' ?></div>
          <?php if ($r['review_comments']): ?><div style="font-size:12.5px;"><?= e($r['review_comments']) ?></div><?php endif; ?>
        </div>
        <?php if (Gate::allows('research.screen') && $r['status'] === 'submitted'): ?>
        <form method="post" action="<?= e(url('action=project_report_review')) ?>" style="display:flex;gap:6px;">
          <?= Csrf::field() ?>
          <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
          <select name="decision">
            <option value="approved">Approve</option>
            <option value="revision_required">Revision</option>
            <option value="rejected">Reject</option>
          </select>
          <button class="btn btn-outline btn-sm" type="submit">Decide</button>
        </form>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>

      <?php if ($canReport && in_array($project['status_code'], ['ACTIVE', 'PROGRESS_MONITORING', 'SUSPENDED'], true)): ?>
      <details style="margin-top:12px;">
        <summary style="cursor:pointer;font-weight:600;">Submit progress report</summary>
        <form method="post" action="<?= e(url('action=project_report_submit')) ?>" enctype="multipart/form-data" style="margin-top:8px;">
          <?= Csrf::field() ?>
          <input type="hidden" name="project_id" value="<?= (int) $project['id'] ?>">
          <div class="form-row">
            <div><label>Reporting period</label><input type="text" name="reporting_period" placeholder="e.g. Jan–Mar 2027"></div>
            <div><label>Progress %</label><input type="number" name="progress_pct" min="0" max="100" required></div>
          </div>
          <div class="form-row">
            <div><label>Period start</label><input type="date" name="period_start"></div>
            <div><label>Period end</label><input type="date" name="period_end"></div>
          </div>
          <label>Completed activities</label><textarea name="completed_activities" style="min-height:60px;"></textarea>
          <label>Pending activities</label><textarea name="pending_activities" style="min-height:60px;"></textarea>
          <label>Challenges</label><textarea name="challenges" style="min-height:60px;"></textarea>
          <label>Achievements</label><textarea name="achievements" style="min-height:60px;"></textarea>
          <div class="form-row">
            <div><label>Financial progress %</label><input type="number" name="financial_progress_pct" min="0" max="100" step="0.1"></div>
            <div><label>Amount spent to date</label><input type="number" name="amount_spent" step="0.01" min="0"></div>
          </div>
          <label>Supporting document (optional)</label><input type="file" name="report_document">
          <button class="btn" type="submit">Submit report</button>
        </form>
      </details>
      <?php endif; ?>
    </div>

    <!-- Extensions (§43) -->
    <div class="card" id="extensions">
      <h2 class="mt0">Deadline extensions</h2>
      <?php if (!$extensions): ?><p class="muted">No extension requests.</p><?php endif; ?>
      <?php foreach ($extensions as $x): ?>
      <div class="doc-row">
        <div>
          <span class="badge <?= $x['status'] === 'approved' ? 'badge-success' : (in_array($x['status'], ['rejected'], true) ? 'badge-danger' : 'badge-warning') ?>"><?= e($x['status']) ?></span>
          <?= e(format_date($x['original_deadline'])) ?> → <?= e(format_date($x['requested_deadline'])) ?>
          <div class="muted" style="font-size:12px;"><?= e($x['reason']) ?></div>
          <?php if ($x['decided_by_name']): ?><div class="muted" style="font-size:12px;">by <?= e($x['decided_by_name']) ?><?= $x['approved_deadline'] ? ' · approved to ' . e(format_date($x['approved_deadline'])) : '' ?></div><?php endif; ?>
        </div>
        <?php if (Gate::allows('research.screen') && $x['status'] === 'pending'): ?>
        <form method="post" action="<?= e(url('action=project_extension_decide')) ?>" style="display:flex;gap:6px;">
          <?= Csrf::field() ?>
          <input type="hidden" name="id" value="<?= (int) $x['id'] ?>">
          <input type="hidden" name="decision" value="approved">
          <input type="date" name="approved_deadline" value="<?= e($x['requested_deadline']) ?>">
          <button class="btn btn-outline btn-sm" type="submit">Approve</button>
        </form>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>

      <?php if ($isPi && in_array($project['status_code'], ['ACTIVE', 'PROGRESS_MONITORING', 'SUSPENDED'], true)): ?>
      <details style="margin-top:12px;">
        <summary style="cursor:pointer;font-weight:600;">Request extension</summary>
        <form method="post" action="<?= e(url('action=project_extension_request')) ?>" style="margin-top:8px;">
          <?= Csrf::field() ?>
          <input type="hidden" name="project_id" value="<?= (int) $project['id'] ?>">
          <div class="form-row">
            <div><label>Current deadline</label><input type="date" value="<?= e($project['planned_end']) ?>" disabled></div>
            <div><label>Requested new deadline</label><input type="date" name="requested_deadline" required></div>
          </div>
          <label>Reason</label><textarea name="reason" required style="min-height:60px;"></textarea>
          <button class="btn btn-outline btn-sm" type="submit">Request extension</button>
        </form>
      </details>
      <?php endif; ?>
    </div>

    <!-- Administrative actions (§44) -->
    <?php if (Gate::allows('research.manage') && !in_array($project['status_code'], ['COMPLETED', 'TERMINATED', 'CANCELLED'], true)): ?>
    <div class="card">
      <h2 class="mt0">Administrative actions (§44)</h2>
      <form method="post" action="<?= e(url('action=project_action')) ?>">
        <?= Csrf::field() ?>
        <input type="hidden" name="project_id" value="<?= (int) $project['id'] ?>">
        <div class="form-row">
          <div><label>Action</label>
            <select name="action_type">
              <?php if ($project['status_code'] !== 'SUSPENDED'): ?><option value="suspend">Suspend</option><?php else: ?><option value="resume">Resume</option><?php endif; ?>
              <option value="cancel">Cancel</option>
              <option value="terminate">Terminate</option>
            </select>
          </div>
        </div>
        <label>Reason <span class="req">*</span></label>
        <textarea name="reason" required style="min-height:60px;"></textarea>
        <label>Comments</label><input type="text" name="comments" maxlength="255">
        <button class="btn btn-danger" type="submit" data-confirm="Record this administrative action? It will be audited.">Record action</button>
      </form>
      <?php if ($actions): ?>
      <h3>Action history</h3>
      <table class="tbl">
        <tr><th>Action</th><th>Reason</th><th>By</th><th>When</th></tr>
        <?php foreach ($actions as $a): ?>
        <tr><td><span class="badge badge-danger"><?= e($a['action_type']) ?></span></td>
            <td><?= e($a['reason']) ?></td>
            <td><?= e($a['approved_by_name'] ?? '') ?></td>
            <td><?= e(format_datetime($a['action_at'])) ?></td></tr>
        <?php endforeach; ?>
      </table>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- History -->
    <div class="card">
      <h2 class="mt0">Workflow history</h2>
      <ul class="timeline">
        <?php foreach ($history as $h): ?>
        <li class="done">
          <div class="tl-head"><?= e($h['from_status'] ?? '—') ?> → <?= e($h['to_status'] ?? '—') ?></div>
          <div class="tl-meta"><?= e(format_datetime($h['created_at'])) ?> · <?= e($h['user_name'] ?? 'system') ?><?= $h['comment'] ? ' · ' . e($h['comment']) : '' ?></div>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
</div>

<?php include APP_PATH . '/views/partials/footer.php'; ?>
