<?php
$title = 'Create Publication';
include APP_PATH . '/views/partials/header.php';
?>
<h1>Create Publication</h1>
<form method="post" action="<?= e(url('action=publication_create')) ?>">
    <?= \App\Core\Csrf::field() ?>
    <label>Title <span class="req">*</span></label>
    <input type="text" name="title" required>
    <label>Journal Name</label>
    <input type="text" name="journal_name">
    <label>Publication Type</label>
    <select name="publication_type">
        <option value="journal_article">Journal Article</option>
        <option value="conference_paper">Conference Paper</option>
        <option value="book_chapter">Book Chapter</option>
    </select>
    <button type="submit" class="btn btn-primary">Create Draft</button>
</form>
<?php include APP_PATH . '/views/partials/footer.php'; ?>

