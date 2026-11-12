<?php
$title = 'Schedule presentation: ' . e($project['title']);
include APP_PATH . '/views/partials/header.php';
?>
<div class="page-head">
  <div><h1>Schedule presentation</h1><p><?= e($project['title']) ?></p></div>
</div>

<form method="post" action="<?= e(url('action=presentation_schedule&project_id=' . $project['id'])) ?>">
  <?= \App\Core\Csrf::field() ?>
  <div class="form-group">
    <label>Scheduled at</label>
    <input type="datetime-local" name="scheduled_at" required>
  </div>
  <div class="form-group">
    <label>Venue</label>
    <input type="text" name="venue" required>
  </div>
  <button class="btn btn-primary" type="submit">Schedule</button>
  <a class="btn btn-outline" href="<?= e(url('action=project_view&id=' . $project['id'])) ?>">Cancel</a>
</form>

<?php include APP_PATH . '/views/partials/footer.php'; ?>

