<?php $title = 'Committees'; include APP_PATH . '/views/partials/header.php'; ?>

<div class="page-head"><div><h1>Committees</h1><p>University, college, ethics and other committees (§29, §68)</p></div></div>

<div class="grid grid-2">
  <div class="card">
    <h2 class="mt0">Committees</h2>
    <table class="tbl">
      <tr><th>Code</th><th>Name</th><th>Type</th><th>Members</th><th></th></tr>
      <?php foreach ($committees as $cm): ?>
      <tr>
        <td><?= e($cm['code']) ?></td>
        <td><?= e($cm['name']) ?><div class="muted" style="font-size:12px;"><?= e($cm['college_name'] ?? 'University-wide') ?></div></td>
        <td><span class="badge badge-info"><?= e(str_replace('_', ' ', $cm['committee_type'])) ?></span></td>
        <td><?= (int) $cm['member_count'] ?></td>
        <td class="actions" style="white-space: nowrap;">
          <a class="btn btn-outline btn-sm" href="<?= e(url('action=committee_members&id=' . $cm['id'])) ?>">Members</a>
          <a class="btn btn-primary btn-sm" href="<?= e(url('action=committee_assign')) ?>">Assign</a>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <div class="card">
    <h2 class="mt0">Add committee</h2>
    <form method="post" action="<?= e(url('action=committee_save')) ?>">
      <?= \App\Core\Csrf::field() ?>
      <input type="hidden" name="id" value="">
      <label>Name <span class="req">*</span></label><input type="text" name="name" required maxlength="150">
      <label>Code <span class="req">*</span></label><input type="text" name="code" required maxlength="30">
      <label>Type</label>
      <select name="committee_type">
        <?php foreach (['university_research','college_research','ethics','publication','extension','presentation_panel','integrity','other'] as $t): ?>
        <option value="<?= e($t) ?>"><?= e(ucwords(str_replace('_', ' ', $t))) ?></option>
        <?php endforeach; ?>
      </select>
      <label>College (optional)</label>
      <select name="college_id"><option value="">University-wide</option>
        <?php foreach ($colleges as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
      </select>
      <label>Quorum size (optional — POLICY DECISION REP-1)</label>
      <input type="number" name="quorum_size" min="1">
      <label>Description</label><input type="text" name="description" maxlength="255">
      <div class="form-actions"><button class="btn" type="submit">Save committee</button></div>
    </form>
  </div>
</div>

<?php include APP_PATH . '/views/partials/footer.php'; ?>
