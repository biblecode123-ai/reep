<?php
declare(strict_types=1);

/**
 * Migration: Add application_team_members table to support co-investigator selection.
 *
 * Idempotent: safe to re-run.
 *
 * Run: php scripts/migrate_team_members.php
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Database;

$sqlite = Database::isSqlite();
$tableExists = static function (string $table) use ($sqlite): bool {
    if ($sqlite) {
        return Database::one("SELECT name FROM sqlite_master WHERE type='table' AND name = ?", [$table]) !== null;
    }
    return Database::one('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$table]) !== null;
};

if (!$tableExists('application_team_members')) {
    $pk = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGINT UNSIGNED NOT NULL AUTOINCREMENT';
    $pkLine = $sqlite ? '' : "        PRIMARY KEY (id),\n";
    $fkType = $sqlite ? 'INTEGER' : 'BIGINT UNSIGNED';
    $enumRole = $sqlite ? "TEXT CHECK (role IN ('PI', 'co_investigator'))" : "ENUM('PI', 'co_investigator')";
    $enumStatus = $sqlite ? "TEXT CHECK (status IN ('pending', 'accepted', 'declined'))" : "ENUM('pending', 'accepted', 'declined')";
    $ts = $sqlite ? 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP' : 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP';

    Database::run("CREATE TABLE application_team_members (
        id $pk,
        application_id $fkType NOT NULL,
        user_id $fkType NOT NULL,
        role $enumRole NOT NULL DEFAULT 'co_investigator',
        status $enumStatus NOT NULL DEFAULT 'pending',
        invited_at $ts,
        responded_at DATETIME NULL,
$pkLine        CONSTRAINT uq_app_team_member UNIQUE (application_id, user_id),
        FOREIGN KEY (application_id) REFERENCES research_applications (id) ON DELETE CASCADE ON UPDATE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
    )");
    echo "  + table application_team_members created\n";
} else {
    echo "  + table application_team_members already exists\n";
}
