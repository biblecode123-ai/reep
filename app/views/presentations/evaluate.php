<?php
$title = 'Evaluate presentation';
include APP_PATH . '/views/partials/header.php';
?>
<div class="page-head">
  <div><h1>Evaluate presentation</h1></div>
</div>

<form method="post" action="<?= e(url('action=presentation_eval&id=' . $presentationId)) ?>">
  <?= \App\Core\Csrf::field() ?>
  <div class="form-group">
    <label>Recommendation</label>
    <select name="recommendation">
      <option value="accept">Accept</option>
      <option value="accept_with_revision">Accept with revision</option>
      <option value="major_revision">Major revision</option>
      <option value="reject">Reject</option>
    </select>
  </div>
  <div class="form-group">
    <label>Comments</label>
    <textarea name="overall_comment" required></textarea>
  </div>
  <button class="btn btn-primary" type="submit">Submit</button>
</form>

<?php include APP_PATH . '/views/partials/footer.php'; ?>
