<?php
declare(strict_types=1);

use App\Core\Database;

require __DIR__ . '/../app/bootstrap.php';

/** Install completed-report evaluation tables on an existing MySQL/MariaDB database. */
if (Database::isSqlite()) {
    echo "Use database/sqlite_schema.sql when recreating an isolated SQLite database.\n";
    exit(0);
}

$ddl = [
"CREATE TABLE IF NOT EXISTS project_final_reports (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id BIGINT UNSIGNED NOT NULL,
    document_id BIGINT UNSIGNED NOT NULL,
    version INT NOT NULL,
    status ENUM('submitted_to_college','college_evaluation','college_revision_required','college_passed','university_evaluation','university_revision_required','university_passed','approved','rejected') NOT NULL DEFAULT 'submitted_to_college',
    submitted_by BIGINT UNSIGNED NOT NULL,
    submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    college_average_score DECIMAL(6,2) NULL,
    university_average_score DECIMAL(6,2) NULL,
    director_decision VARCHAR(40) NULL,
    director_comments TEXT NULL,
    director_user_id BIGINT UNSIGNED NULL,
    director_decided_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_final_report_version (project_id, version),
    KEY idx_final_report_status (status, project_id),
    CONSTRAINT fk_final_report_project FOREIGN KEY (project_id) REFERENCES research_projects (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_final_report_document FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_final_report_submitter FOREIGN KEY (submitted_by) REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_final_report_director FOREIGN KEY (director_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
"CREATE TABLE IF NOT EXISTS project_evaluator_assignments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    final_report_id BIGINT UNSIGNED NOT NULL,
    evaluator_user_id BIGINT UNSIGNED NOT NULL,
    assignment_level ENUM('college','university') NOT NULL,
    assigned_by BIGINT UNSIGNED NOT NULL,
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deadline DATETIME NULL,
    status ENUM('assigned','accepted','declined','conflict_declared','completed','cancelled') NOT NULL DEFAULT 'assigned',
    response_reason TEXT NULL,
    responded_at DATETIME NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_project_eval_assignment (final_report_id, evaluator_user_id, assignment_level),
    KEY idx_project_eval_assignment_user (evaluator_user_id, assignment_level, status),
    CONSTRAINT fk_project_eval_asg_report FOREIGN KEY (final_report_id) REFERENCES project_final_reports (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_project_eval_asg_user FOREIGN KEY (evaluator_user_id) REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_project_eval_asg_by FOREIGN KEY (assigned_by) REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
"CREATE TABLE IF NOT EXISTS project_evaluations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    final_report_id BIGINT UNSIGNED NOT NULL,
    assignment_id BIGINT UNSIGNED NOT NULL,
    evaluator_user_id BIGINT UNSIGNED NOT NULL,
    rubric_template_id BIGINT UNSIGNED NOT NULL,
    rubric_snapshot JSON NOT NULL,
    evaluation_round INT NOT NULL DEFAULT 1,
    weighted_score DECIMAL(6,2) NOT NULL DEFAULT 0,
    max_score DECIMAL(6,2) NOT NULL DEFAULT 100,
    recommendation ENUM('accept','accept_with_revision','major_revision','reject') NULL,
    strengths TEXT NULL,
    weaknesses TEXT NULL,
    recommendations TEXT NULL,
    overall_comment TEXT NULL,
    status ENUM('draft','submitted','returned') NOT NULL DEFAULT 'draft',
    submitted_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_project_eval_assignment_round (assignment_id, evaluation_round),
    KEY idx_project_eval_report (final_report_id, status),
    CONSTRAINT fk_project_eval_report FOREIGN KEY (final_report_id) REFERENCES project_final_reports (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_project_eval_assignment FOREIGN KEY (assignment_id) REFERENCES project_evaluator_assignments (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_project_eval_user FOREIGN KEY (evaluator_user_id) REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_project_eval_rubric FOREIGN KEY (rubric_template_id) REFERENCES rubric_templates (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
"CREATE TABLE IF NOT EXISTS project_evaluation_indicator_scores (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_evaluation_id BIGINT UNSIGNED NOT NULL,
    indicator_id BIGINT UNSIGNED NOT NULL,
    score DECIMAL(6,2) NOT NULL,
    comment VARCHAR(1000) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_project_eval_indicator_score (project_evaluation_id, indicator_id),
    CONSTRAINT fk_project_eval_score_eval FOREIGN KEY (project_evaluation_id) REFERENCES project_evaluations (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_project_eval_score_indicator FOREIGN KEY (indicator_id) REFERENCES rubric_indicators (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
"CREATE TABLE IF NOT EXISTS project_final_report_decisions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    final_report_id BIGINT UNSIGNED NOT NULL,
    stage ENUM('college','university','directorate') NOT NULL,
    decision ENUM('forward_to_university','revision_required','reject','approve') NOT NULL,
    comments TEXT NULL,
    decided_by BIGINT UNSIGNED NOT NULL,
    decided_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_final_decision_report (final_report_id, stage, decided_at),
    CONSTRAINT fk_final_decision_report FOREIGN KEY (final_report_id) REFERENCES project_final_reports (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_final_decision_user FOREIGN KEY (decided_by) REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
];

foreach ($ddl as $statement) {
    Database::run($statement);
}

echo "Completed research report evaluation tables installed. No report or evaluation data was changed.\n";
