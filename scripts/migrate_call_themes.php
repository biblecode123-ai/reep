<?php
declare(strict_types=1);

/**
 * Add many-to-many research-call/theme associations without removing the
 * legacy research_calls.research_theme_id column.
 *
 * Run: php scripts/migrate_call_themes.php
 * Safe to re-run on supported MySQL and SQLite installations.
 */
require __DIR__ . '/../app/bootstrap.php';

use App\Core\Database;

$sqlite = Database::isSqlite();
$exists = $sqlite
    ? Database::one("SELECT name FROM sqlite_master WHERE type='table' AND name='research_call_themes'") !== null
    : Database::one("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='research_call_themes'") !== null;

if (!$exists) {
    if ($sqlite) {
        Database::run('CREATE TABLE research_call_themes (
            call_id INTEGER NOT NULL,
            theme_id INTEGER NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (call_id, theme_id),
            FOREIGN KEY (call_id) REFERENCES research_calls(id) ON DELETE CASCADE,
            FOREIGN KEY (theme_id) REFERENCES research_themes(id) ON DELETE RESTRICT
        )');
    } else {
        Database::run('CREATE TABLE research_call_themes (
            call_id BIGINT UNSIGNED NOT NULL,
            theme_id BIGINT UNSIGNED NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (call_id, theme_id),
            KEY idx_rct_theme (theme_id),
            CONSTRAINT fk_rct_call FOREIGN KEY (call_id) REFERENCES research_calls(id) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_rct_theme FOREIGN KEY (theme_id) REFERENCES research_themes(id) ON DELETE RESTRICT ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }
    echo "Created research_call_themes.\n";
}

$legacyRows = Database::all('SELECT id, research_theme_id FROM research_calls WHERE research_theme_id IS NOT NULL');
foreach ($legacyRows as $row) {
    $present = Database::scalar('SELECT COUNT(*) FROM research_call_themes WHERE call_id = ? AND theme_id = ?', [(int) $row['id'], (int) $row['research_theme_id']]);
    if ((int) $present === 0) {
        Database::insert('research_call_themes', ['call_id' => (int) $row['id'], 'theme_id' => (int) $row['research_theme_id']]);
    }
}
echo 'Migrated legacy theme associations: ' . count($legacyRows) . "\n";
