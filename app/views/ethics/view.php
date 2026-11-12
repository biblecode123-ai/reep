<?php
use App\Core\Auth;
use App\Core\Gate;
use App\Core\Session;
use App\Core\Csrf;
$title = 'Ethics: ' . $eth['application_number'];
include APP_PATH . '/views/partials/header.php';
$isResearcher = (int) $eth['researcher_user_id'] === Auth::id();
?>
<div class="page-head">
  <div>
    <h1><?= e($eth['application_number']) ?></h1>
    <p><span class="badge <?= in_array($eth['status'], ['approved','approved_with_conditions','exempt'], true) ? 'badge-success' : (in_array($eth['status'], ['rejected','expired'], true) ? 'badge-danger' : 'badge-info') ?>"><?= e(ucfirst(str_replace('_', ' ', $eth['status']))) ?></span>
      · Research: <a href="<?= e(url('action=application_view&id=' . $eth['research_application_id'])) ?>"><?= e($eth['research_title']) ?></a>
      · Researcher: <?= e($eth['researcher_name']) ?></p>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap;">
    <?php if ($isResearcher && in_array($eth['status'], ['draft', 'revision_required'], true)): ?>
      <a class="btn btn-outline" href="<?= e(url('action=ethics_edit&id=' . $eth['id'])) ?>">Edit</a>
      <?php if ($eth['status'] === 'draft'): ?>
      <form method="post" action="<?= e(url('action=ethics_submit')) ?>">
        <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int) $eth['id'] ?>">
        <button class="btn" type="submit">Submit for screening</button>
      </form>
      <?php endif; ?>
    <?php endif; ?>
    <?php if ($isResearcher && in_array($eth['status'], ['approved', 'approved_with_conditions'], true)): ?>
      <a class="btn btn-outline" href="#amendments">Request amendment</a>
    <?php endif; ?>
    <?php if (Gate::allows('ethics.manage') && in_array($eth['status'], ['under_screening', 'under_review', 'revision_required'], true)): ?>
      <a class="btn" href="<?= e(url('action=ethics_assign&id=' . $eth['id'])) ?>">Assign reviewers</a>
    <?php endif; ?>
  </div>
</div>

<div class="grid grid-2">
  <div>
    <div class="card">
      <h2 class="mt0">Application details (§32)</h2>
      <dl class="kv">
        <dt>Risk level</dt><dd><?= e($eth['risk_level'] ? ucfirst($eth['risk_level']) : '—') ?></dd>
        <dt>Participants</dt><dd><?= e((string) ($eth['participant_count'] ?? '—')) ?><?= $eth['participant_description'] ? ' — ' . e($eth['participant_description']) : '' ?></dd>
        <dt>Review track</dt><dd><?= e(ucfirst($eth['review_track'])) ?></dd>
        <dt>Submitted</dt><dd><?= e(format_datetime($eth['submitted_at'])) ?></dd>
        <dt>Approved until</dt><dd><?= e(format_date($eth['approved_until'])) ?></dd>
        <?php if ($eth['screening_note']): ?><dt>Screening note</dt><dd><?= e($eth['screening_note']) ?></dd><?php endif; ?>
      </dl>
      <h3>Consent process</h3><p style="white-space:pre-wrap;"><?= e($eth['consent_process'] ?? '—') ?></p>
      <h3>Confidentiality</h3><p style="white-space:pre-wrap;"><?= e($eth['confidentiality_measures'] ?? '—') ?></p>
      <h3>Data collection</h3><p style="white-space:pre-wrap;"><?= e($eth['data_collection_summary'] ?? '—') ?></p>
    </div>

    <div class="card">
      <h2 class="mt0">Documents</h2>
      <?php if (!$documents): ?><p class="muted">No documents.</p><?php endif; ?>
      <?php foreach ($documents as $d): ?>
      <div class="doc-row">
        <div><?= e($d['type_name']) ?> v<?= (int) $d['version'] ?>
          <span class="muted" style="font-size:12px;"><?= e(format_datetime($d['uploaded_at'])) ?></span>
          <?= $d['status'] === 'superseded' ? '<span class="badge badge-secondary">superseded</span>' : '' ?></div>
        <a class="btn btn-outline btn-sm" href="<?= e(base_url('download.php?id=' . $d['id'])) ?>">Download</a>
      </div>
      <?php endforeach; ?>
      <?php if ($isResearcher || Gate::allows('ethics.manage')): ?>
      <form method="post" action="<?= e(url('action=document_upload')) ?>" enctype="multipart/form-data" style="margin-top:12px;">
        <?= Csrf::field() ?>
        <input type="hidden" name="entity_type" value="ethics_application">
        <input type="hidden" name="entity_id" value="<?= (int) $eth['id'] ?>">
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

    <!-- Amendments (§34) -->
    <div class="card" id="amendments">
      <h2 class="mt0">Amendments</h2>
      <?php if (!$amendments): ?><p class="muted">No amendments.</p><?php endif; ?>
      <?php foreach ($amendments as $am): ?>
      <div class="doc-row">
        <div>
          <strong><?= e($am['amendment_number']) ?></strong> · <?= e(ucfirst(str_replace('_', ' ', $am['amendment_type']))) ?>
          <span class="badge badge-info"><?= e(ucfirst(str_replace('_', ' ', $am['status']))) ?></span>
          <div style="font-size:13px;"><?= e($am['description']) ?></div>
          <span class="muted" style="font-size:12px;">requested <?= e(format_date($am['requested_at'])) ?><?= $am['decided_by_name'] ? ' · decided by ' . e($am['decided_by_name']) : '' ?></span>
        </div>
        <?php if (Gate::allows('ethics.decide') && $am['status'] === 'submitted'): ?>
        <form method="post" action="<?= e(url('action=ethics_amendment_decide')) ?>" style="display:flex;gap:6px;">
          <?= Csrf::field() ?>
          <input type="hidden" name="amendment_id" value="<?= (int) $am['id'] ?>">
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

      <?php if ($isResearcher && in_array($eth['status'], ['approved', 'approved_with_conditions'], true)): ?>
      <form method="post" action="<?= e(url('action=ethics_amendment')) ?>" style="margin-top:12px;">
        <?= Csrf::field() ?>
        <input type="hidden" name="id" value="<?= (int) $eth['id'] ?>">
        <label>Amendment type</label>
        <select name="amendment_type">
          <?php foreach (['methodology','participants','location','data_collection','instruments','other'] as $t): ?>
          <option value="<?= e($t) ?>"><?= e(ucfirst(str_replace('_', ' ', $t))) ?></option>
          <?php endforeach; ?>
        </select>
        <label>Description of change</label>
        <textarea name="description" required></textarea>
        <button class="btn btn-outline btn-sm" type="submit">Submit amendment</button>
      </form>
      <?php endif; ?>
    </div>
  </div>

  <div>
    <!-- Workflow actions -->
    <?php
      $visible = array_filter($transitions, fn($t) => $t['permitted']);
    ?>
    <?php if ($visible): ?>
    <div class="card">
      <h2 class="mt0">Actions</h2>
      <?php foreach ($visible as $t): ?>
      <form method="post" action="<?= e(url('action=workflow_fire_ethics')) ?>" style="margin-bottom:8px;">
        <?= Csrf::field() ?>
        <input type="hidden" name="entity_type" value="ethics_application">
        <input type="hidden" name="entity_id" value="<?= (int) $eth['id'] ?>">
        <input type="hidden" name="transition" value="<?= e($t['name']) ?>">
        <div style="display:flex;gap:8px;">
          <input type="text" name="comment" placeholder="Optional comment" style="flex:1;">
          <button class="btn btn-outline btn-sm" type="submit"><?= e($t['name']) ?></button>
        </div>
      </form>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Screening (§31) -->
    <?php if (Gate::allows('ethics.screen') && in_array($eth['status'], ['submitted', 'under_screening'], true)): ?>
    <div class="card">
      <h2 class="mt0">Ethics screening</h2>
      <form method="post" action="<?= e(url('action=ethics_screen')) ?>">
        <?= Csrf::field() ?>
        <input type="hidden" name="id" value="<?= (int) $eth['id'] ?>">
        <label>Outcome</label>
        <select name="track">
          <option value="full">Full review required — route to committee</option>
          <option value="screening">Exempt after screening (§30)</option>
        </select>
        <label>Screening note</label>
        <textarea name="screening_note" style="min-height:70px;"></textarea>
        <button class="btn" type="submit">Record screening</button>
      </form>
    </div>
    <?php endif; ?>

    <!-- Decision (§31) -->
    <?php if (Gate::allows('ethics.decide') && in_array($eth['status'], ['under_review'], true)): ?>
    <div class="card">
      <h2 class="mt0">Committee decision</h2>
      <form method="post" action="<?= e(url('action=ethics_decide')) ?>">
        <?= Csrf::field() ?>
        <input type="hidden" name="id" value="<?= (int) $eth['id'] ?>">
        <label>Decision</label>
        <select name="decision" required>
          <option value="approved">Approved</option>
          <option value="approved_with_conditions">Approved with conditions</option>
          <option value="revision_required">Revision required</option>
          <option value="rejected">Rejected</option>
        </select>
        <label>Conditions (if any)</label>
        <textarea name="conditions" style="min-height:60px;"></textarea>
        <label>Comments</label>
        <textarea name="comments" style="min-height:60px;"></textarea>
        <div class="form-row">
          <div><label>Approval expires (§33)</label><input type="date" name="expires_at" value="<?= e(date('Y-m-d', strtotime('+1 year'))) ?>"></div>
          <div><label>Decision reference</label><input type="text" name="decision_reference"></div>
        </div>
        <button class="btn" type="submit">Record decision</button>
      </form>
    </div>
    <?php endif; ?>

    <!-- Renewal (§33) -->
    <?php if (Gate::allows('ethics.decide') && in_array($eth['status'], ['approved', 'approved_with_conditions', 'expired'], true)): ?>
    <div class="card">
      <h2 class="mt0">Renew approval (§33)</h2>
      <form method="post" action="<?= e(url('action=ethics_renew')) ?>" style="display:flex;gap:8px;align-items:end;">
        <?= Csrf::field() ?>
        <input type="hidden" name="id" value="<?= (int) $eth['id'] ?>">
        <div style="flex:1;"><label>New expiry date</label><input type="date" name="new_expiry" required value="<?= e(date('Y-m-d', strtotime('+1 year'))) ?>"></div>
        <button class="btn btn-outline" type="submit">Renew</button>
      </form>
    </div>
    <?php endif; ?>

    <!-- Certificate (§32) -->
    <?php if ($certificates): ?>
    <div class="card">
      <h2 class="mt0">Certificates</h2>
      <?php foreach ($certificates as $cert): ?>
      <div class="doc-row">
        <div><strong><?= e($cert['certificate_number']) ?></strong>
          <div class="muted" style="font-size:12px;">issued <?= e(format_date($cert['issued_date'])) ?> · expires <?= e(format_date($cert['expires_at'])) ?><?= $cert['issued_by_name'] ? ' · by ' . e($cert['issued_by_name']) : '' ?></div>
        </div>
        <?php if (Gate::allows('ethics.decide') && !in_array($eth['status'], ['approved', 'approved_with_conditions'], true)): ?>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
      <?php if (Gate::allows('ethics.decide') && in_array($eth['status'], ['approved', 'approved_with_conditions'], true) && count($certificates) === 0): ?>
      <form method="post" action="<?= e(url('action=ethics_certificate')) ?>" style="margin-top:10px;">
        <?= Csrf::field() ?>
        <input type="hidden" name="id" value="<?= (int) $eth['id'] ?>">
        <button class="btn" type="submit">Issue certificate</button>
      </form>
      <?php endif; ?>
    </div>
    <?php elseif (Gate::allows('ethics.decide') && in_array($eth['status'], ['approved', 'approved_with_conditions'], true)): ?>
    <div class="card">
      <h2 class="mt0">Certificate</h2>
      <form method="post" action="<?= e(url('action=ethics_certificate')) ?>">
        <?= Csrf::field() ?>
        <input type="hidden" name="id" value="<?= (int) $eth['id'] ?>">
        <p class="muted" style="font-size:13px;">Generate the official ethics certificate (<?= e(\App\Core\Settings::get('certificate_number_prefix', 'MWU-RPEE-CERT')) ?>-YYYY-NNNN).</p>
        <button class="btn" type="submit">Issue certificate</button>
      </form>
    </div>
    <?php endif; ?>

    <!-- Reviews -->
    <?php if ($reviews && ($canManage || $isReviewer || Gate::allows('ethics.decide'))): ?>
    <div class="card">
      <h2 class="mt0">Assigned reviewers</h2>
      <table class="tbl">
        <tr><th>Reviewer</th><th>Status</th><th>Recommendation</th></tr>
        <?php foreach ($reviews as $r): ?>
        <tr>
          <td><?= e($r['reviewer_name']) ?></td>
          <td><span class="badge <?= $r['status'] === 'completed' ? 'badge-success' : 'badge-warning' ?>"><?= e($r['status']) ?></span>
            <div class="muted" style="font-size:12px;">due <?= e(format_date($r['review_deadline'])) ?></div></td>
          <td><?= e($r['recommendation'] ? ucfirst(str_replace('_', ' ', $r['recommendation'])) : '—') ?></td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>
    <?php endif; ?>

    <!-- Decisions -->
    <?php if ($decisions): ?>
    <div class="card">
      <h2 class="mt0">Decision history</h2>
      <?php foreach ($decisions as $d): ?>
      <div class="doc-row">
        <div>
          <strong><?= e(ucfirst(str_replace('_', ' ', $d['decision']))) ?></strong>
          <span class="muted" style="font-size:12px;"><?= e(format_datetime($d['decided_at'])) ?> · <?= e($d['decided_by_name'] ?? '') ?></span>
          <?php if ($d['conditions']): ?><div style="font-size:13px;"><strong>Conditions:</strong> <?= e($d['conditions']) ?></div><?php endif; ?>
          <?php if ($d['comments']): ?><div style="font-size:13px;"><?= e($d['comments']) ?></div><?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
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
