<?php declare(strict_types=1);

require __DIR__. '/../app/bootstrap.php';

use App\Core\Database;

$sqlite = Database::isSqlite();

$table = 'research_application_invitations';

$tableExists = $sqlite ? Database::one("SELECT name FROM sqlite_master WHERE type='table' AND name = ?", [$table])
== null : Database::one('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()
ND TABLE_NAME = ?', [$table])
== null;

if ($tableExists)

echo "Table $table already exists.
";

exit(0);

}

$pk = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT';

$fkType = $sqlite ? 'INTEGER' : 'BIGINT UNSIGNED';

$ts = 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP';

$enumStatus = $sqlite ? "TEXT CHECK (status IN ('pending','accepted','declined','withdrawn'))" : "ENUM('pending','accepted','declined','withdrawn')";

$sql = "CREATE TABLE $table ( id $pk, application_id $fkType NOT NULL, researcher_id $fkType NOT NULL, role VARCHAR(50)
OT NULL DEFAULT 'co_investigator', status $enumStatus NOT NULL DEFAULT 'pending', invited_at $ts, responded_at DATETIME NULL, created_at $ts". ($sqlite ? "" : ", PRIMARY KEY (id)"). ", FOREIGN KEY (application_id)
EFERENCES research_applications (id)
N DELETE CASCADE ON UPDATE CASCADE, FOREIGN KEY (researcher_id)
EFERENCES users (id)
N DELETE CASCADE ON UPDATE CASCADE )
. ($sqlite ? "" : "ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

Database::run($sql);

echo "Table $table created.
";

