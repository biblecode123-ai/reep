<?php
declare(strict_types=1);

use App\Core\Database;

require __DIR__ . '/../app/bootstrap.php';

/**
 * Install the versioned indicator-level rubric engine on existing MySQL /
 * MariaDB databases. Safe to re-run; it does not seed invented rubric content.
 */
if (Database::isSqlite()) {
    echo "SQLite installations should be recreated from database/sqlite_schema.sql for this development/test schema update.\n";
    exit(0);
}

$tableExists = static fn(string $table): bool => (int) Database::scalar(
    'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
    [$table]
) > 0;
$columnExists = static fn(string $table, string $column): bool => (int) Database::scalar(
    'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
    [$table, $column]
) > 0;
$constraintExists = static fn(string $table, string $constraint): bool => (int) Database::scalar(
    'SELECT COUNT(*) FROM information_schema.table_constraints
     WHERE constraint_schema = DATABASE() AND table_name = ? AND constraint_name = ?',
    [$table, $constraint]
) > 0;

if (!$tableExists('rubric_templates')) {
    Database::run("CREATE TABLE rubric_templates (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        rubric_type ENUM('college_proposal','college_completed_report','university_proposal','university_completed_report') NOT NULL,
        name VARCHAR(180) NOT NULL,
        version INT NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 0,
        created_by BIGINT UNSIGNED NULL,
        approved_by BIGINT UNSIGNED NULL,
        approved_at DATETIME NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_rubric_version (rubric_type, version),
        KEY idx_rubric_active (rubric_type, is_active),
        CONSTRAINT fk_rubric_created FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
        CONSTRAINT fk_rubric_approved FOREIGN KEY (approved_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
if (!$tableExists('rubric_criteria')) {
    Database::run("CREATE TABLE rubric_criteria (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        template_id BIGINT UNSIGNED NOT NULL,
        name VARCHAR(180) NOT NULL,
        max_points DECIMAL(6,2) NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        UNIQUE KEY uq_rubric_criterion_name (template_id, name),
        CONSTRAINT fk_rubric_criterion_template FOREIGN KEY (template_id) REFERENCES rubric_templates (id) ON DELETE RESTRICT ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
if (!$tableExists('rubric_indicators')) {
    Database::run("CREATE TABLE rubric_indicators (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        criterion_id BIGINT UNSIGNED NOT NULL,
        name VARCHAR(220) NOT NULL,
        description TEXT NULL,
        max_points DECIMAL(6,2) NOT NULL,
        is_required TINYINT(1) NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        UNIQUE KEY uq_rubric_indicator_name (criterion_id, name),
        CONSTRAINT fk_rubric_indicator_criterion FOREIGN KEY (criterion_id) REFERENCES rubric_criteria (id) ON DELETE RESTRICT ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

if (!$columnExists('evaluations', 'rubric_template_id')) {
    Database::run('ALTER TABLE evaluations ADD COLUMN rubric_template_id BIGINT UNSIGNED NULL AFTER assignment_id');
}
if (!$columnExists('evaluations', 'rubric_snapshot')) {
    Database::run('ALTER TABLE evaluations ADD COLUMN rubric_snapshot JSON NULL AFTER rubric_template_id');
}
if (!$constraintExists('evaluations', 'fk_ev_rubric')) {
    Database::run('ALTER TABLE evaluations ADD CONSTRAINT fk_ev_rubric FOREIGN KEY (rubric_template_id) REFERENCES rubric_templates (id) ON DELETE RESTRICT ON UPDATE CASCADE');
}
if (!$tableExists('rubric_indicator_scores')) {
    Database::run("CREATE TABLE rubric_indicator_scores (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        evaluation_id BIGINT UNSIGNED NOT NULL,
        indicator_id BIGINT UNSIGNED NOT NULL,
        score DECIMAL(6,2) NOT NULL,
        comment VARCHAR(1000) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_rubric_indicator_score (evaluation_id, indicator_id),
        CONSTRAINT fk_rubric_score_eval FOREIGN KEY (evaluation_id) REFERENCES evaluations (id) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT fk_rubric_score_indicator FOREIGN KEY (indicator_id) REFERENCES rubric_indicators (id) ON DELETE RESTRICT ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

if ($tableExists('university_budget_recommendations') && !$columnExists('university_budget_recommendations', 'justification')) {
    Database::run('ALTER TABLE university_budget_recommendations ADD COLUMN justification TEXT NULL AFTER amount');
}

echo "Versioned indicator-level rubric tables, evaluation snapshot columns, and university budget justification are installed. No rubric templates were fabricated or activated.\n";
