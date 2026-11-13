-- Migration: Add Research Proposal Evaluation Criteria Management
-- Author: Research Director Dashboard Task

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Rubric Management Tables
CREATE TABLE evaluation_rubrics (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(200) NOT NULL,
    description TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE evaluation_rubric_versions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    rubric_id BIGINT UNSIGNED NOT NULL,
    version_number INT NOT NULL,
    status ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft',
    passing_score DECIMAL(5,2) NOT NULL DEFAULT 50.00,
    created_by BIGINT UNSIGNED NULL,
    published_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_rubric_version FOREIGN KEY (rubric_id) REFERENCES evaluation_rubrics (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Modify existing evaluation_criteria for versioning and sub-criteria
-- NOTE: Existing call_id links will remain for legacy compatibility until migrated.
ALTER TABLE evaluation_criteria ADD COLUMN rubric_version_id BIGINT UNSIGNED NULL;
ALTER TABLE evaluation_criteria ADD COLUMN parent_criterion_id BIGINT UNSIGNED NULL;

ALTER TABLE evaluation_criteria ADD CONSTRAINT fk_ecrit_version FOREIGN KEY (rubric_version_id) REFERENCES evaluation_rubric_versions (id) ON DELETE CASCADE;
ALTER TABLE evaluation_criteria ADD CONSTRAINT fk_ecrit_parent FOREIGN KEY (parent_criterion_id) REFERENCES evaluation_criteria (id) ON DELETE CASCADE;

-- 3. Link Research Calls to Rubric Versions
ALTER TABLE research_calls ADD COLUMN evaluation_rubric_version_id BIGINT UNSIGNED NULL;
ALTER TABLE research_calls ADD CONSTRAINT fk_call_rubric_version FOREIGN KEY (evaluation_rubric_version_id) REFERENCES evaluation_rubric_versions (id) ON DELETE SET NULL;

SET FOREIGN_KEY_CHECKS = 1;
