<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Core\Database;

Database::run("ALTER TABLE research_themes ADD COLUMN research_priorities TEXT NULL");
Database::run("ALTER TABLE research_themes ADD COLUMN objectives TEXT NULL");
Database::run("ALTER TABLE research_themes ADD COLUMN start_date DATE NULL");
Database::run("ALTER TABLE research_themes ADD COLUMN end_date DATE NULL");
Database::run("ALTER TABLE research_themes ADD COLUMN created_at TIMESTAMP NULL");
Database::run("ALTER TABLE research_themes ADD COLUMN updated_at TIMESTAMP NULL");

Database::run("UPDATE research_themes SET created_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP");

echo "Schema updated successfully.";

