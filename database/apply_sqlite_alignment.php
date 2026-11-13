<?php
declare(strict_types=1);

require 'C:/xampp/htdocs/REEP/app/bootstrap.php';

$dbs = [
    'database/mwu_rpee.sqlite',
    'database/mwu_rpee_test.sqlite',
];

$sql = file_get_contents('database/migrations/20261113_sqlite_schema_alignment.sql');

foreach ($dbs as $dbFile) {
    if (!file_exists($dbFile)) continue;
    echo "Applying migration to $dbFile...\n";
    $pdo = new PDO("sqlite:$dbFile");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec($sql);

    // Check evaluations columns
    $cols = $pdo->query("PRAGMA table_info(evaluations)")->fetchAll(PDO::FETCH_ASSOC);
    $colNames = array_column($cols, 'name');
    if (!in_array('rubric_template_id', $colNames, true)) {
        echo "Adding rubric_template_id to evaluations in $dbFile...\n";
        $pdo->exec("ALTER TABLE evaluations ADD COLUMN rubric_template_id INTEGER NULL");
    }
    if (!in_array('rubric_snapshot', $colNames, true)) {
        echo "Adding rubric_snapshot to evaluations in $dbFile...\n";
        $pdo->exec("ALTER TABLE evaluations ADD COLUMN rubric_snapshot TEXT NULL");
    }

    // Populate research_call_themes from research_calls
    $calls = $pdo->query("SELECT id, research_theme_id FROM research_calls WHERE research_theme_id IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO research_call_themes (call_id, theme_id) VALUES (?, ?)");
    foreach ($calls as $call) {
        $stmt->execute([(int)$call['id'], (int)$call['research_theme_id']]);
    }
    echo "Done for $dbFile.\n";
}
