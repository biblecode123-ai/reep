<?php declare(strict_types=1);

require __DIR__. '/../app/bootstrap.php';

use App\Core\Database;

$sqlite = Database::isSqlite();

echo "== Migrating research_call_themes ==
";

if (!$sqlite)

echo "This migration is only for SQLite.
";

exit(1);

}

//
run("CREATE TABLE IF NOT EXISTS research_call_themes ( call_id INTEGER NOT NULL, theme_id INTEGER NOT NULL, PRIMARY KEY (call_id, theme_id), FOREIGN KEY (call_id)
EFERENCES research_calls (id)
N DELETE CASCADE ON UPDATE CASCADE, FOREIGN KEY (theme_id)
EFERENCES research_themes (id)
N DELETE CASCADE ON UPDATE CASCADE )");

//
all("SELECT id, research_theme_id FROM research_calls WHERE research_theme_id IS NOT NULL");

foreach ($calls as $call)

Database::run("INSERT OR IGNORE INTO research_call_themes (call_id, theme_id)
ALUES (?, ?)", [$call['id'], $call['research_theme_id']]);

}

echo "Migration complete.
";

