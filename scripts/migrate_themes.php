<?php
declare(strict_types=1);

use App\Core\Database;

require __DIR__ . '/../app/bootstrap.php';

if (!Database::isSqlite()) {
    fwrite(STDERR, "This migration is only for SQLite.\n");
    exit(1);
}

echo "== Migrating research_call_themes ==\n";

Database::run(
    "CREATE TABLE IF NOT EXISTS research_call_themes (
        call_id INTEGER NOT NULL,
        theme_id INTEGER NOT NULL,
        PRIMARY KEY (call_id, theme_id),
        FOREIGN KEY (call_id) REFERENCES research_calls (id) ON DELETE CASCADE ON UPDATE CASCADE,
        FOREIGN KEY (theme_id) REFERENCES research_themes (id) ON DELETE CASCADE ON UPDATE CASCADE
    )"
);

$calls = Database::all(
    "SELECT id, research_theme_id FROM research_calls WHERE research_theme_id IS NOT NULL"
);

foreach ($calls as $call) {
    Database::run(
        "INSERT OR IGNORE INTO research_call_themes (call_id, theme_id) VALUES (?, ?)",
        [(int) $call['id'], (int) $call['research_theme_id']]
    );
}

echo "Migration complete.\n";
