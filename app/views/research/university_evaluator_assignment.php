<?php
$title = 'Assign University Evaluators';
include APP_PATH . '/views/partials/header.php';
?>
<div class="page-head">
    <h1>Assign University Evaluators</h1>
    <p>Application: <strong><?= e($app['application_number']) ?></strong> · <?= e($app['title']) ?></p>
</div>

<div class="card">
    <form method="post" action="<?= e(url('action=univ_eval_assign_save')) ?>">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="application_id" value="<?= (int) $app['id'] ?>">
        
        <table class="tbl">
            <tr><th>Select</th><th>Evaluator Name</th></tr>
            <?php foreach ($evaluators as $eval): ?>
            <tr>
                <td><input type="checkbox" name="evaluators[]" value="<?= (int) $eval['id'] ?>" <?= in_array($eval['id'], $assignedIds) ? 'checked' : '' ?>></td>
                <td><?= e($eval['full_name']) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        
        <button class="btn" type="submit">Assign selected evaluators</button>
    </form>
</div>

<?php include APP_PATH . '/views/partials/footer.php'; ?>
