-- Evaluation Framework System Migration

CREATE TABLE rubric_templates (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    rubric_type VARCHAR(50) NOT NULL,
    name VARCHAR(200) NOT NULL,
    description TEXT NULL,
    version INT NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 0,
    passing_score DECIMAL(5,2) NOT NULL DEFAULT 50.00,
    created_by BIGINT UNSIGNED NULL,
    approved_by BIGINT UNSIGNED NULL,
    approved_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rubric_criteria (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    template_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(200) NOT NULL,
    description TEXT NULL,
    instructions TEXT NULL,
    max_points DECIMAL(5,2) NOT NULL DEFAULT 0,
    weight_pct DECIMAL(5,2) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    CONSTRAINT fk_rubric_criteria_template FOREIGN KEY (template_id) REFERENCES rubric_templates (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rubric_indicators (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    criterion_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(200) NOT NULL,
    description TEXT NULL,
    max_points DECIMAL(5,2) NOT NULL DEFAULT 0,
    is_required TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    CONSTRAINT fk_rubric_indicators_criterion FOREIGN KEY (criterion_id) REFERENCES rubric_criteria (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Link calls to rubric templates
ALTER TABLE research_calls ADD COLUMN rubric_template_id BIGINT UNSIGNED NULL;
ALTER TABLE research_calls ADD CONSTRAINT fk_calls_rubric_template FOREIGN KEY (rubric_template_id) REFERENCES rubric_templates (id) ON DELETE SET NULL;
