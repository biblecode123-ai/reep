<?php
$title = 'University Evaluation Form';
include APP_PATH . '/views/partials/header.php';
?>
<div class="card">
    <h1>University Evaluation</h1>
    <form method="post" action="<?= e(url('action=submit_university_evaluation')) ?>">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="application_id" value="<?= (int) $app['id'] ?>">
        
        <h2>Academic Evaluation</h2>
        <!-- Criteria fields -->
        
        <h2>Budget Evaluation</h2>
        <label>Recommended Budget (ETB)</label>
        <input type="number" name="recommended_budget" required>
        <label>Justification</label>
        <textarea name="budget_justification" required></textarea>
        
        <button class="btn" type="submit">Submit Evaluation</button>
    </form>
</div>
<?php include APP_PATH . '/views/partials/footer.php'; ?>
