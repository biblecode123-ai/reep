<?php use App\Core\Auth;

use App\Core\Gate;

$title = 'Assign Committee';

include APP_PATH . '/views/partials/header.php';

$applications = $applications ?? [];

?>

<div class="page-head">
    <h1>Assign to Research Committee</h1>
    <p>Assign applications ready for university committee review.</p>
    <a class="btn btn-outline" href="<?= e(url('action=applications')) ?>">Back to applications</a>
</div>

<div class="card">
    <?php if (!$applications): ?>
    <p class="muted">No applications available for committee assignment.</p>
    <?php else: ?>
    <form method="post" action="<?= e(url('action=committee_assign_save')) ?>" data-confirm="Assign the selected application to a committee?">
        <?= \App\Core\Csrf::field() ?>
        <table class="tbl">
            <tr><th></th><th>Reference</th><th>Title</th><th>Status</th><th>Committee</th><th>Meeting date</th><th>Notes</th></tr>
            <?php foreach ($applications as $a): ?>
            <tr>
                <td><input type="radio" name="application_id" value="<?= (int) $a['id'] ?>" required></td>
                <td><strong><?= e($a['application_number']) ?></strong></td>
                <td><?= e($a['title']) ?></td>
                <td><span class="badge badge-info"><?= e(status_label($a['status_code'])) ?></span></td>
                <td>
                    <select name="committee_id[<?= (int) $a['id'] ?>]">
                        <option value="">— select committee —</option>
                        <?php foreach (\App\Models\CommitteeModel::allActive() as $c): ?>
                        <option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?> (<?= e($c['committee_code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td><input type="date" name="meeting_date[<?= (int) $a['id'] ?>]"></td>
                <td><input type="text" name="notes[<?= (int) $a['id'] ?>]" placeholder="Optional"></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <div style="margin-top:10px;"><button class="btn" type="submit">Assign selected to committee</button></div>
    </form>
    <?php endif; ?>
</div>

<?php include APP_PATH . '/views/partials/footer.php'; ?>
