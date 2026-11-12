-- =============================================================================
-- MWU RPEE — Integrated Research Management System
-- Database schema (Phase 1, spec §88–§89)
-- Target: MySQL 8+ / MariaDB 10.4+ · Engine InnoDB · utf8mb4
-- Rules: FKs protect official records (RESTRICT), transactions used in services,
--        no institutional data hardcoded — data lives in seed.sql / admin UI.
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Drop existing objects (idempotent re-install) — reverse dependency order.
DROP TABLE IF EXISTS workflow_history;
DROP TABLE IF EXISTS workflow_transitions;
DROP TABLE IF EXISTS workflow_stages;
DROP TABLE IF EXISTS workflows;
DROP TABLE IF EXISTS document_access_log;
DROP TABLE IF EXISTS documents;
DROP TABLE IF EXISTS repository_embargoes;
DROP TABLE IF EXISTS repository_records;
DROP TABLE IF EXISTS dataset_access_requests;
DROP TABLE IF EXISTS research_datasets;
DROP TABLE IF EXISTS extension_impact_reports;
DROP TABLE IF EXISTS extension_partners;
DROP TABLE IF EXISTS extension_activities;
DROP TABLE IF EXISTS extension_projects;
DROP TABLE IF EXISTS extension_applications;
DROP TABLE IF EXISTS extension_calls;
DROP TABLE IF EXISTS publication_authors;
DROP TABLE IF EXISTS publications;
DROP TABLE IF EXISTS research_outputs;
DROP TABLE IF EXISTS presentation_scores;
DROP TABLE IF EXISTS presentation_evaluations;
DROP TABLE IF EXISTS presentation_panels;
DROP TABLE IF EXISTS presentations;
DROP TABLE IF EXISTS ethics_certificates;
DROP TABLE IF EXISTS ethics_amendments;
DROP TABLE IF EXISTS ethics_decisions;
DROP TABLE IF EXISTS ethics_reviews;
DROP TABLE IF EXISTS ethics_applications;
DROP TABLE IF EXISTS project_actions;
DROP TABLE IF EXISTS research_extension_requests;
DROP TABLE IF EXISTS progress_reports;
DROP TABLE IF EXISTS research_milestones;
DROP TABLE IF EXISTS funding_releases;
DROP TABLE IF EXISTS budget_revisions;
DROP TABLE IF EXISTS research_budget_items;
DROP TABLE IF EXISTS research_budgets;
DROP TABLE IF EXISTS university_decisions;
DROP TABLE IF EXISTS college_decisions;
DROP TABLE IF EXISTS revision_responses;
DROP TABLE IF EXISTS revision_requests;
DROP TABLE IF EXISTS rubric_indicator_scores;
DROP TABLE IF EXISTS evaluation_comments;
DROP TABLE IF EXISTS evaluation_scores;
DROP TABLE IF EXISTS rubric_indicators;
DROP TABLE IF EXISTS rubric_criteria;
DROP TABLE IF EXISTS rubric_templates;
DROP TABLE IF EXISTS evaluations;
DROP TABLE IF EXISTS evaluation_criteria;
DROP TABLE IF EXISTS evaluator_conflicts;
DROP TABLE IF EXISTS evaluator_assignments;
DROP TABLE IF EXISTS research_evaluators;
DROP TABLE IF EXISTS research_work_plan_items;
DROP TABLE IF EXISTS research_applications;
DROP TABLE IF EXISTS research_call_criteria;
DROP TABLE IF EXISTS research_call_requirements;
DROP TABLE IF EXISTS research_call_eligibility;
DROP TABLE IF EXISTS research_calls;
DROP TABLE IF EXISTS research_project_members;
DROP TABLE IF EXISTS research_projects;
DROP TABLE IF EXISTS research_integrity_cases;
DROP TABLE IF EXISTS appeals;
DROP TABLE IF EXISTS committee_members;
DROP TABLE IF EXISTS committees;
DROP TABLE IF EXISTS researchers;
DROP TABLE IF EXISTS user_delegations;
DROP TABLE IF EXISTS password_resets;
DROP TABLE IF EXISTS login_attempts;
DROP TABLE IF EXISTS user_roles;
DROP TABLE IF EXISTS role_permissions;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS departments;
DROP TABLE IF EXISTS colleges;
DROP TABLE IF EXISTS research_themes;
DROP TABLE IF EXISTS research_types;
DROP TABLE IF EXISTS document_types;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS number_sequences;
DROP TABLE IF EXISTS system_settings;
DROP TABLE IF EXISTS roles;
DROP TABLE IF EXISTS permissions;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- SECTION A — ORGANIZATION & CLASSIFICATION
-- =============================================================================

CREATE TABLE colleges (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name          VARCHAR(150) NOT NULL,
    code          VARCHAR(20)  NOT NULL,
    description   VARCHAR(255) NULL,
    status        ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_colleges_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE departments (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    college_id    BIGINT UNSIGNED NOT NULL,
    name          VARCHAR(150) NOT NULL,
    code          VARCHAR(20)  NOT NULL,
    status        ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_departments_college_code (college_id, code),
    CONSTRAINT fk_departments_college FOREIGN KEY (college_id) REFERENCES colleges (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
    id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    employee_id           VARCHAR(50)  NULL,
    full_name             VARCHAR(150) NOT NULL,
    email                 VARCHAR(190) NOT NULL,
    phone                 VARCHAR(30)  NULL,
    password_hash         VARCHAR(255) NOT NULL,
    status                ENUM('active','inactive','suspended','pending') NOT NULL DEFAULT 'pending',
    position_title        VARCHAR(100) NULL,
    college_id            BIGINT UNSIGNED NULL,
    department_id         BIGINT UNSIGNED NULL,
    must_change_password  TINYINT(1) NOT NULL DEFAULT 0,
    last_login_at         DATETIME NULL,
    last_login_ip         VARCHAR(45) NULL,
    created_by            BIGINT UNSIGNED NULL,
    created_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    UNIQUE KEY uq_users_employee_id (employee_id),
    KEY idx_users_college (college_id),
    KEY idx_users_department (department_id),
    CONSTRAINT fk_users_college    FOREIGN KEY (college_id)    REFERENCES colleges (id)    ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_users_department FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE roles (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name         VARCHAR(60) NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    description  VARCHAR(255) NULL,
    is_system    TINYINT(1) NOT NULL DEFAULT 0,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_roles_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permissions (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(80) NOT NULL,
    module      VARCHAR(40) NOT NULL,
    description VARCHAR(255) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_permissions_name (name),
    KEY idx_permissions_module (module)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE role_permissions (
    role_id       BIGINT UNSIGNED NOT NULL,
    permission_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles (id)       ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_rp_perm FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_roles (
    user_id     BIGINT UNSIGNED NOT NULL,
    role_id     BIGINT UNSIGNED NOT NULL,
    assigned_by BIGINT UNSIGNED NULL,
    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, role_id),
    CONSTRAINT fk_ur_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_ur_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_ur_assigner FOREIGN KEY (assigned_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_resets (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at    DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_password_resets_token (token_hash),
    KEY idx_password_resets_user (user_id),
    CONSTRAINT fk_pr_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    identifier   VARCHAR(190) NOT NULL,
    ip_address   VARCHAR(45)  NOT NULL,
    successful   TINYINT(1) NOT NULL DEFAULT 0,
    attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_login_attempts_ident (identifier, attempted_at),
    KEY idx_login_attempts_ip (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Temporary delegation (spec §69). Every action still records the actual actor.
CREATE TABLE user_delegations (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    from_user_id BIGINT UNSIGNED NOT NULL,
    to_user_id   BIGINT UNSIGNED NOT NULL,
    scope_note   VARCHAR(255) NULL,
    start_date   DATE NOT NULL,
    end_date     DATE NOT NULL,
    status       ENUM('active','revoked','expired') NOT NULL DEFAULT 'active',
    created_by   BIGINT UNSIGNED NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_delegations_window (status, start_date, end_date),
    CONSTRAINT fk_del_from FOREIGN KEY (from_user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_del_to   FOREIGN KEY (to_user_id)   REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE researchers (
    id                       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id                  BIGINT UNSIGNED NOT NULL,
    academic_position        VARCHAR(100) NULL,
    qualification            VARCHAR(150) NULL,
    specialization           VARCHAR(200) NULL,
    research_interests       TEXT NULL,
    research_areas           TEXT NULL,
    biography                TEXT NULL,
    gender                   VARCHAR(20) NULL,
    orcid                    VARCHAR(50) NULL,
    google_scholar           VARCHAR(255) NULL,
    profile_photo_document_id BIGINT UNSIGNED NULL, -- FK added after documents table
    created_at               TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at               TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_researchers_user (user_id),
    CONSTRAINT fk_res_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE committees (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name           VARCHAR(150) NOT NULL,
    code           VARCHAR(30)  NOT NULL,
    committee_type ENUM('university_research','college_research','ethics','publication','extension','presentation_panel','integrity','other') NOT NULL DEFAULT 'other',
    college_id     BIGINT UNSIGNED NULL,
    description    VARCHAR(255) NULL,
    quorum_size    INT NULL, -- POLICY DECISION REQUIRED (REP-1): only enforced if set
    status         ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_committees_code (code),
    KEY idx_committees_college (college_id),
    CONSTRAINT fk_committees_college FOREIGN KEY (college_id) REFERENCES colleges (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE committee_members (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    committee_id   BIGINT UNSIGNED NOT NULL,
    user_id        BIGINT UNSIGNED NOT NULL,
    member_role    ENUM('chair','secretary','member') NOT NULL DEFAULT 'member',
    appointed_from DATE NULL,
    appointed_to   DATE NULL,
    status         ENUM('active','retired') NOT NULL DEFAULT 'active',
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cm_committee_user (committee_id, user_id),
    CONSTRAINT fk_cm_committee FOREIGN KEY (committee_id) REFERENCES committees (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_cm_user      FOREIGN KEY (user_id)      REFERENCES users (id)      ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE research_themes (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(150) NOT NULL,
    code        VARCHAR(30)  NOT NULL,
    description VARCHAR(255) NULL,
    status      ENUM('active','inactive') NOT NULL DEFAULT 'active',
    PRIMARY KEY (id),
    UNIQUE KEY uq_themes_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE research_types (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(150) NOT NULL,
    code        VARCHAR(30)  NOT NULL,
    description VARCHAR(255) NULL,
    status      ENUM('active','inactive') NOT NULL DEFAULT 'active',
    PRIMARY KEY (id),
    UNIQUE KEY uq_types_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE document_types (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    type_code   VARCHAR(50) NOT NULL,
    name        VARCHAR(100) NOT NULL,
    description VARCHAR(255) NULL,
    status      ENUM('active','inactive') NOT NULL DEFAULT 'active',
    PRIMARY KEY (id),
    UNIQUE KEY uq_document_types_code (type_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- SECTION B — WORKFLOW ENGINE (spec §18, §92–§96)
-- =============================================================================

CREATE TABLE workflows (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    description VARCHAR(255) NULL,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_workflows_entity_name (entity_type, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE workflow_stages (
    id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    workflow_id           BIGINT UNSIGNED NOT NULL,
    stage_code            VARCHAR(60) NOT NULL,
    stage_name            VARCHAR(100) NOT NULL,
    stage_type            ENUM('start','normal','terminal') NOT NULL DEFAULT 'normal',
    is_terminal_exception TINYINT(1) NOT NULL DEFAULT 0, -- rejected/withdrawn/… (§18)
    sort_order            INT NOT NULL DEFAULT 0,
    description           VARCHAR(255) NULL,
    responsible_role_id   BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_stages_wf_code (workflow_id, stage_code),
    CONSTRAINT fk_stages_wf  FOREIGN KEY (workflow_id) REFERENCES workflows (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_stages_role FOREIGN KEY (responsible_role_id) REFERENCES roles (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE workflow_transitions (
    id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    workflow_id        BIGINT UNSIGNED NOT NULL,
    from_stage_id      BIGINT UNSIGNED NOT NULL,
    to_stage_id        BIGINT UNSIGNED NOT NULL,
    transition_name    VARCHAR(100) NOT NULL,
    required_permission VARCHAR(100) NULL,
    condition_key      VARCHAR(60) NULL, -- evaluated by WorkflowService (§96)
    sort_order         INT NOT NULL DEFAULT 0,
    is_active          TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_transitions (workflow_id, from_stage_id, to_stage_id, transition_name),
    CONSTRAINT fk_tr_wf   FOREIGN KEY (workflow_id)   REFERENCES workflows (id)       ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_tr_from FOREIGN KEY (from_stage_id) REFERENCES workflow_stages (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_tr_to   FOREIGN KEY (to_stage_id)   REFERENCES workflow_stages (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Append-only transition history (spec §94; never deleted — Rule 9).
CREATE TABLE workflow_history (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    workflow_id  BIGINT UNSIGNED NULL,
    entity_type  VARCHAR(50) NOT NULL,
    entity_id    BIGINT UNSIGNED NOT NULL,
    from_stage_id BIGINT UNSIGNED NULL,
    to_stage_id   BIGINT UNSIGNED NULL,
    from_status  VARCHAR(60) NULL,
    to_status    VARCHAR(60) NULL,
    user_id      BIGINT UNSIGNED NULL,
    comment      VARCHAR(500) NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_wfhist_entity (entity_type, entity_id, created_at),
    CONSTRAINT fk_wfhist_wf   FOREIGN KEY (workflow_id)   REFERENCES workflows (id)       ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_wfhist_from FOREIGN KEY (from_stage_id) REFERENCES workflow_stages (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_wfhist_to   FOREIGN KEY (to_stage_id)   REFERENCES workflow_stages (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_wfhist_user FOREIGN KEY (user_id)       REFERENCES users (id)           ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- SECTION C — RESEARCH CALLS (spec §11–§14)
-- =============================================================================

CREATE TABLE research_calls (
    id                              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    call_number                     VARCHAR(40) NOT NULL,       -- generated: MWU-RPEE-CALL-YYYY-NNNN
    reference_number                VARCHAR(60) NOT NULL,
    title                           VARCHAR(200) NOT NULL,
    description                     TEXT NULL,
    research_priorities             TEXT NULL,
    research_theme_id               INT NULL,
    opens_at                        DATETIME NULL,
    closes_at                       DATETIME NULL,
    research_start_date             DATE NULL,
    completion_deadline             DATE NULL,
    max_duration_months             INT NULL,
    max_budget                      DECIMAL(12,2) NULL,
    budget_currency                 CHAR(3) NOT NULL DEFAULT 'ETB',
    max_applications_per_researcher INT NULL DEFAULT 1,
    evaluation_mode                 ENUM('open','single_blind','double_blind') NOT NULL DEFAULT 'single_blind',
    required_evaluators             INT NULL DEFAULT 2,         -- POLICY DECISION WF-2 default
    status                          ENUM('draft','published','open','closed','under_evaluation','completed','archived') NOT NULL DEFAULT 'draft',
    published_at                    DATETIME NULL,
    closed_at                       DATETIME NULL,
    created_by                      BIGINT UNSIGNED NULL,
    created_at                      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_calls_number (call_number),
    UNIQUE KEY uq_calls_reference (reference_number),
    KEY idx_calls_status (status),
    KEY idx_calls_closes (closes_at),
    CONSTRAINT fk_calls_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_calls_theme FOREIGN KEY (research_theme_id) REFERENCES research_themes (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE research_call_eligibility (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    call_id       BIGINT UNSIGNED NOT NULL,
    eligibility_key ENUM('eligible_college','eligible_department','research_theme','researcher_type','min_position') NOT NULL,
    ref_id        BIGINT UNSIGNED NULL,
    ref_value     VARCHAR(100) NULL,
    PRIMARY KEY (id),
    KEY idx_celig_call (call_id),
    CONSTRAINT fk_celig_call FOREIGN KEY (call_id) REFERENCES research_calls (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE research_call_requirements (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    call_id           BIGINT UNSIGNED NOT NULL,
    document_type_id  BIGINT UNSIGNED NOT NULL,
    requirement       ENUM('required','optional','conditional') NOT NULL DEFAULT 'required',
    condition_note    VARCHAR(255) NULL, -- for 'conditional' (e.g. ethics document)
    sort_order        INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_creq (call_id, document_type_id),
    CONSTRAINT fk_creq_call FOREIGN KEY (call_id) REFERENCES research_calls (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_creq_type FOREIGN KEY (document_type_id) REFERENCES document_types (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE research_call_criteria (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    call_id        BIGINT UNSIGNED NOT NULL,
    criterion_name VARCHAR(150) NOT NULL,
    description    TEXT NULL,
    weight_pct     DECIMAL(5,2) NOT NULL DEFAULT 0,
    sort_order     INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ccrit (call_id, criterion_name),
    CONSTRAINT fk_ccrit_call FOREIGN KEY (call_id) REFERENCES research_calls (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Criteria that are not call-specific (final/presentation evaluation, §46, §49).
CREATE TABLE evaluation_criteria (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    call_id     BIGINT UNSIGNED NULL, -- NULL => institution-wide criteria set
    applies_to  ENUM('proposal','final','presentation','ethics') NOT NULL DEFAULT 'proposal',
    name        VARCHAR(150) NOT NULL,
    description TEXT NULL,
    weight_pct  DECIMAL(5,2) NOT NULL DEFAULT 0,
    sort_order  INT NOT NULL DEFAULT 0,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_ecrit (call_id, applies_to),
    CONSTRAINT fk_ecrit_call FOREIGN KEY (call_id) REFERENCES research_calls (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- SECTION D — RESEARCH APPLICATIONS (spec §16–§27)
-- =============================================================================

-- Four independent, versioned 100-point institutional evaluation rubrics.
CREATE TABLE rubric_templates (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    rubric_type ENUM('college_proposal','college_completed_report','university_proposal','university_completed_report') NOT NULL,
    name        VARCHAR(180) NOT NULL,
    version     INT NOT NULL,
    is_active   TINYINT(1) NOT NULL DEFAULT 0,
    created_by  BIGINT UNSIGNED NULL,
    approved_by BIGINT UNSIGNED NULL,
    approved_at DATETIME NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rubric_version (rubric_type, version),
    KEY idx_rubric_active (rubric_type, is_active),
    CONSTRAINT fk_rubric_created FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_rubric_approved FOREIGN KEY (approved_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rubric_criteria (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    template_id BIGINT UNSIGNED NOT NULL,
    name        VARCHAR(180) NOT NULL,
    max_points  DECIMAL(6,2) NOT NULL,
    sort_order  INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rubric_criterion_name (template_id, name),
    CONSTRAINT fk_rubric_criterion_template FOREIGN KEY (template_id) REFERENCES rubric_templates (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rubric_indicators (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    criterion_id BIGINT UNSIGNED NOT NULL,
    name         VARCHAR(220) NOT NULL,
    description  TEXT NULL,
    max_points   DECIMAL(6,2) NOT NULL,
    is_required  TINYINT(1) NOT NULL DEFAULT 1,
    sort_order   INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rubric_indicator_name (criterion_id, name),
    CONSTRAINT fk_rubric_indicator_criterion FOREIGN KEY (criterion_id) REFERENCES rubric_criteria (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE research_applications (
    id                       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    application_number       VARCHAR(40) NOT NULL,          -- MWU-RPEE-APP-YYYY-NNNN
    call_id                  BIGINT UNSIGNED NOT NULL,
    pi_user_id               BIGINT UNSIGNED NOT NULL,
    title                    VARCHAR(250) NOT NULL,
    research_type_id         BIGINT UNSIGNED NULL,
    research_theme_id        BIGINT UNSIGNED NULL,
    research_area            VARCHAR(150) NULL,
    college_id               BIGINT UNSIGNED NOT NULL,      -- routing source (§17)
    department_id            BIGINT UNSIGNED NULL,
    duration_months          INT NULL,
    proposed_start_date      DATE NULL,
    proposed_end_date        DATE NULL,
    abstract                 TEXT NULL,
    background               TEXT NULL,
    problem_statement        TEXT NULL,
    objectives               TEXT NULL,
    research_questions       TEXT NULL,
    literature_review        TEXT NULL,
    methodology              TEXT NULL,
    expected_results         TEXT NULL,
    significance             TEXT NULL,
    innovation               TEXT NULL,
    references_text          TEXT NULL,
    budget_requested         DECIMAL(12,2) NOT NULL DEFAULT 0,
    budget_justification     TEXT NULL,
    budget_approved_amount   DECIMAL(12,2) NULL,   -- directorate-approved allocation (§28, Rule 13)
    budget_approved_by       BIGINT UNSIGNED NULL,
    requires_ethics          ENUM('yes','no','unsure') NULL, -- §30
    current_stage_id         BIGINT UNSIGNED NULL,
    workflow_id              BIGINT UNSIGNED NULL,
    status_code              VARCHAR(60) NOT NULL DEFAULT 'DRAFT', -- indexed mirror of stage code
    is_locked                TINYINT(1) NOT NULL DEFAULT 0, -- submission locking (§ Phase 5)
    version                  INT NOT NULL DEFAULT 1,
    -- Stage timestamps (§76) — set by services inside transactions
    submitted_at             DATETIME NULL,
    college_received_at      DATETIME NULL,
    screening_completed_at   DATETIME NULL,
    evaluator_assigned_at    DATETIME NULL,
    evaluation_started_at    DATETIME NULL,
    evaluation_completed_at  DATETIME NULL,
    revision_requested_at    DATETIME NULL,
    revision_submitted_at    DATETIME NULL,
    college_approved_at      DATETIME NULL,
    university_submitted_at  DATETIME NULL,
    university_approved_at   DATETIME NULL,
    budget_approved_at       DATETIME NULL,
    funded_at                DATETIME NULL,
    started_at               DATETIME NULL,
    progress_report_at       DATETIME NULL,
    final_submitted_at       DATETIME NULL,
    presented_at             DATETIME NULL,
    completed_at             DATETIME NULL,
    repository_at            DATETIME NULL,
    created_by               BIGINT UNSIGNED NULL,
    created_at               TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at               TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_app_number (application_number),
    KEY idx_app_call (call_id),
    KEY idx_app_pi (pi_user_id),
    KEY idx_app_status (status_code),
    KEY idx_app_college (college_id),
    CONSTRAINT fk_app_call  FOREIGN KEY (call_id)         REFERENCES research_calls (id)   ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_app_pi    FOREIGN KEY (pi_user_id)      REFERENCES users (id)            ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_app_type  FOREIGN KEY (research_type_id) REFERENCES research_types (id)  ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_app_theme FOREIGN KEY (research_theme_id) REFERENCES research_themes (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_app_col   FOREIGN KEY (college_id)      REFERENCES colleges (id)         ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_app_dept  FOREIGN KEY (department_id)   REFERENCES departments (id)      ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_app_stage FOREIGN KEY (current_stage_id) REFERENCES workflow_stages (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_app_wf    FOREIGN KEY (workflow_id)     REFERENCES workflows (id)        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Proposal-stage collaborators; copied into project membership on activation.
CREATE TABLE application_team_members (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    application_id BIGINT UNSIGNED NOT NULL,
    user_id        BIGINT UNSIGNED NOT NULL,
    role           ENUM('PI','co_investigator') NOT NULL DEFAULT 'co_investigator',
    status         ENUM('pending','accepted','declined') NOT NULL DEFAULT 'pending',
    invited_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    responded_at   DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_app_team_member (application_id, user_id),
    KEY idx_app_team_user (user_id, status),
    CONSTRAINT fk_app_team_app FOREIGN KEY (application_id) REFERENCES research_applications (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_app_team_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE research_work_plan_items (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    application_id  BIGINT UNSIGNED NOT NULL,
    activity        VARCHAR(200) NOT NULL,
    start_date      DATE NULL,
    end_date        DATE NULL,
    responsible     VARCHAR(150) NULL,
    expected_output VARCHAR(255) NULL,
    sort_order      INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_wplan_app (application_id),
    CONSTRAINT fk_wplan_app FOREIGN KEY (application_id) REFERENCES research_applications (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- SECTION E — EVALUATION (spec §20–§25)
-- =============================================================================

-- Evaluator pool (expertise / availability / workload for coordinator selection, §20).
CREATE TABLE research_evaluators (
    id                         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id                    BIGINT UNSIGNED NOT NULL,
    expertise                  TEXT NULL,
    max_concurrent_evaluations INT NOT NULL DEFAULT 5,
    availability_status        ENUM('available','busy','unavailable') NOT NULL DEFAULT 'available',
    notes                      VARCHAR(255) NULL,
    created_at                 TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                 TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_reseval_user (user_id),
    CONSTRAINT fk_reseval_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE evaluator_assignments (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    application_id    BIGINT UNSIGNED NOT NULL,
    evaluator_user_id BIGINT UNSIGNED NOT NULL,
    assignment_level  VARCHAR(20) NOT NULL DEFAULT 'college',
    assigned_by       BIGINT UNSIGNED NULL,
    assigned_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deadline          DATE NULL,
    status            ENUM('assigned','accepted','declined','conflict_declared','completed','cancelled') NOT NULL DEFAULT 'assigned',
    accepted_at       DATETIME NULL,
    declined_at       DATETIME NULL,
    completed_at      DATETIME NULL,
    decline_reason    VARCHAR(255) NULL,
    reminder_sent_at  DATETIME NULL,
    is_active         TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_evalassign_stage (application_id, evaluator_user_id, assignment_level),
    KEY idx_evalassign_evaluator (evaluator_user_id, status),
    CONSTRAINT fk_ea_app FOREIGN KEY (application_id)    REFERENCES research_applications (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ea_usr FOREIGN KEY (evaluator_user_id) REFERENCES users (id)                 ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ea_by  FOREIGN KEY (assigned_by)       REFERENCES users (id)                 ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE evaluator_conflicts (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    assignment_id   BIGINT UNSIGNED NOT NULL,
    conflict_type   ENUM('self_declared','system_detected') NOT NULL DEFAULT 'self_declared',
    description     TEXT NULL,
    detected_reason VARCHAR(255) NULL, -- e.g. same department / co-author (§75)
    declared_by     BIGINT UNSIGNED NULL,
    resolved        ENUM('open','resolved_reassigned','resolved_override') NOT NULL DEFAULT 'open',
    resolved_by     BIGINT UNSIGNED NULL,
    resolved_at     DATETIME NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_evalconf_assignment (assignment_id),
    CONSTRAINT fk_ec_assign FOREIGN KEY (assignment_id) REFERENCES evaluator_assignments (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_ec_by     FOREIGN KEY (declared_by)   REFERENCES users (id)                ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE evaluations (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    application_id    BIGINT UNSIGNED NOT NULL,
    assignment_id     BIGINT UNSIGNED NULL,
    evaluator_user_id BIGINT UNSIGNED NOT NULL,
    evaluation_type   ENUM('proposal','final') NOT NULL DEFAULT 'proposal',
    evaluation_round  INT NOT NULL DEFAULT 1, -- increments on re-evaluation (§25)
    amended_from_id   BIGINT UNSIGNED NULL,   -- amendment chain (§22)
    recommendation    ENUM('accept','accept_with_revision','major_revision','reject') NULL,
    weighted_score    DECIMAL(5,2) NULL,
    max_score         DECIMAL(5,2) NOT NULL DEFAULT 100,
    strengths         TEXT NULL,
    weaknesses        TEXT NULL,
    recommendations   TEXT NULL,
    overall_comment   TEXT NULL,
    status            ENUM('draft','submitted','locked','amended') NOT NULL DEFAULT 'draft',
    submitted_at      DATETIME NULL,
    locked_at         DATETIME NULL,
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_eval_app (application_id, evaluation_type, evaluation_round),
    KEY idx_eval_evaluator (evaluator_user_id, status),
    UNIQUE KEY uq_eval_assignment_round (assignment_id, evaluation_round),
    CONSTRAINT fk_ev_app   FOREIGN KEY (application_id)    REFERENCES research_applications (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ev_asg   FOREIGN KEY (assignment_id)     REFERENCES evaluator_assignments (id) ON DELETE SET NULL ON UPDATE CASCADE,\n    CONSTRAINT fk_ev_rubric FOREIGN KEY (rubric_template_id) REFERENCES rubric_templates (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ev_usr   FOREIGN KEY (evaluator_user_id) REFERENCES users (id)                 ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ev_amend FOREIGN KEY (amended_from_id)   REFERENCES evaluations (id)           ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE evaluation_scores (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    evaluation_id BIGINT UNSIGNED NOT NULL,
    criterion_id  BIGINT UNSIGNED NOT NULL,
    score         DECIMAL(5,2) NOT NULL,
    comment       VARCHAR(500) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_escore (evaluation_id, criterion_id),
    CONSTRAINT fk_es_eval FOREIGN KEY (evaluation_id) REFERENCES evaluations (id)            ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_es_crit FOREIGN KEY (criterion_id)  REFERENCES research_call_criteria (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rubric_indicator_scores (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    evaluation_id  BIGINT UNSIGNED NOT NULL,
    indicator_id   BIGINT UNSIGNED NOT NULL,
    score          DECIMAL(6,2) NOT NULL,
    comment        VARCHAR(1000) NULL,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rubric_indicator_score (evaluation_id, indicator_id),
    CONSTRAINT fk_rubric_score_eval FOREIGN KEY (evaluation_id) REFERENCES evaluations (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_rubric_score_indicator FOREIGN KEY (indicator_id) REFERENCES rubric_indicators (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE evaluation_comments (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    evaluation_id BIGINT UNSIGNED NOT NULL,
    visibility    ENUM('to_researcher','committee_only') NOT NULL DEFAULT 'to_researcher',
    comment       TEXT NOT NULL,
    created_by    BIGINT UNSIGNED NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ecom_eval (evaluation_id),
    CONSTRAINT fk_ecom_eval FOREIGN KEY (evaluation_id) REFERENCES evaluations (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_ecom_user FOREIGN KEY (created_by)    REFERENCES users (id)       ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE revision_requests (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    application_id  BIGINT UNSIGNED NOT NULL,
    requested_by    BIGINT UNSIGNED NULL,
    request_reason  TEXT NULL,
    deadline        DATE NULL,
    status          ENUM('open','responded','closed','cancelled') NOT NULL DEFAULT 'open',
    requested_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    responded_at    DATETIME NULL,
    closed_at       DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_revreq_app (application_id, status),
    CONSTRAINT fk_rr_app FOREIGN KEY (application_id) REFERENCES research_applications (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_rr_by  FOREIGN KEY (requested_by)   REFERENCES users (id)                 ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE revision_responses (
    id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    revision_request_id BIGINT UNSIGNED NOT NULL,
    comment_id         BIGINT UNSIGNED NULL, -- per-comment response (§25)
    response_text      TEXT NOT NULL,
    submitted_by       BIGINT UNSIGNED NULL,
    submitted_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_revresp_req (revision_request_id),
    CONSTRAINT fk_rv_req    FOREIGN KEY (revision_request_id) REFERENCES revision_requests (id)    ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_rv_com    FOREIGN KEY (comment_id)          REFERENCES evaluation_comments (id)  ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_rv_user   FOREIGN KEY (submitted_by)        REFERENCES users (id)                ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- SECTION F — DECISIONS (spec §19, §27, §28)
-- =============================================================================

CREATE TABLE college_decisions (
    id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    application_id     BIGINT UNSIGNED NOT NULL,
    decision_context   ENUM('screening','committee') NOT NULL DEFAULT 'screening',
    decision           ENUM('complete','incomplete','return_for_correction','reject_ineligible','forward_to_evaluation','approve','approve_with_conditions','revision_required','reject','forward_to_university') NOT NULL,
    committee_id       BIGINT UNSIGNED NULL,
    meeting_date       DATE NULL,
    comments           TEXT NULL,
    decision_reference VARCHAR(100) NULL,
    decided_by         BIGINT UNSIGNED NULL,
    decided_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_cdec_app (application_id, decision_context),
    CONSTRAINT fk_cdec_app FOREIGN KEY (application_id) REFERENCES research_applications (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_cdec_com FOREIGN KEY (committee_id)   REFERENCES committees (id)            ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_cdec_usr FOREIGN KEY (decided_by)     REFERENCES users (id)                 ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE university_budget_recommendations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    application_id BIGINT UNSIGNED NOT NULL,
    evaluator_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_ubr_app FOREIGN KEY (application_id) REFERENCES research_applications(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_ubr_eval FOREIGN KEY (evaluator_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE application_committee_assignments (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    application_id BIGINT UNSIGNED NOT NULL,
    committee_id   BIGINT UNSIGNED NOT NULL,
    assigned_by    BIGINT UNSIGNED NULL,
    assigned_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    meeting_date   DATE NULL,
    notes          TEXT NULL,
    status         ENUM('active','completed','cancelled') NOT NULL DEFAULT 'active',
    PRIMARY KEY (id),
    KEY idx_aca_app (application_id, status),
    CONSTRAINT fk_aca_app FOREIGN KEY (application_id) REFERENCES research_applications (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_aca_com FOREIGN KEY (committee_id)   REFERENCES committees (id)            ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_aca_usr FOREIGN KEY (assigned_by)    REFERENCES users (id)                 ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Committee decision (Rule 8) — separate from Research Directorate approval
-- (university_decisions). The funding recommendation (Rule 9) is separate from
-- the directorate-approved allocation (research_applications.budget_approved_amount).
CREATE TABLE committee_decisions (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    assignment_id       BIGINT UNSIGNED NOT NULL,
    application_id      BIGINT UNSIGNED NOT NULL,
    committee_id        BIGINT UNSIGNED NOT NULL,
    decision            ENUM('approve','approve_with_conditions','revision_required','reject') NOT NULL,
    comments            TEXT NULL,
    meeting_date        DATE NULL,
    decision_reference  VARCHAR(100) NULL,
    recommended_amount  DECIMAL(12,2) NULL,          -- committee funding recommendation (Rule 9)
    recommendation_note TEXT NULL,
    decided_by          BIGINT UNSIGNED NULL,
    decided_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_cvd_app (application_id),
    CONSTRAINT fk_cvd_asg FOREIGN KEY (assignment_id) REFERENCES application_committee_assignments (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_cvd_app FOREIGN KEY (application_id) REFERENCES research_applications (id)           ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_cvd_com FOREIGN KEY (committee_id)   REFERENCES committees (id)                       ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_cvd_usr FOREIGN KEY (decided_by)     REFERENCES users (id)                            ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- SECTION G — PROJECTS, BUDGET, IMPLEMENTATION (spec §35–§44, §73)
-- =============================================================================

CREATE TABLE research_projects (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_number    VARCHAR(40) NOT NULL,           -- MWU-RPEE-YYYY-NNNN (§35)
    application_id    BIGINT UNSIGNED NOT NULL,
    title             VARCHAR(250) NOT NULL,
    pi_user_id        BIGINT UNSIGNED NOT NULL,
    college_id        BIGINT UNSIGNED NOT NULL,
    department_id     BIGINT UNSIGNED NULL,
    research_type_id  BIGINT UNSIGNED NULL,
    research_theme_id BIGINT UNSIGNED NULL,
    workflow_id       BIGINT UNSIGNED NULL,
    current_stage_id  BIGINT UNSIGNED NULL,
    status_code       VARCHAR(60) NOT NULL DEFAULT 'APPROVED',
    duration_months   INT NULL,
    planned_start     DATE NULL,
    planned_end       DATE NULL,
    actual_start      DATE NULL,
    actual_end        DATE NULL,
    approved_budget   DECIMAL(12,2) NOT NULL DEFAULT 0,
    completion_note   TEXT NULL,
    created_by        BIGINT UNSIGNED NULL,
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_proj_number (project_number),
    UNIQUE KEY uq_proj_application (application_id),
    KEY idx_proj_pi (pi_user_id),
    KEY idx_proj_status (status_code),
    KEY idx_proj_college (college_id),
    CONSTRAINT fk_proj_app   FOREIGN KEY (application_id)    REFERENCES research_applications (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_proj_pi    FOREIGN KEY (pi_user_id)        REFERENCES users (id)                 ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_proj_col   FOREIGN KEY (college_id)        REFERENCES colleges (id)              ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_proj_dept  FOREIGN KEY (department_id)     REFERENCES departments (id)           ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_proj_type  FOREIGN KEY (research_type_id)  REFERENCES research_types (id)        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_proj_theme FOREIGN KEY (research_theme_id) REFERENCES research_themes (id)       ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_proj_wf    FOREIGN KEY (workflow_id)       REFERENCES workflows (id)             ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_proj_stage FOREIGN KEY (current_stage_id)  REFERENCES workflow_stages (id)       ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Team, not a single researcher_id (§73).
CREATE TABLE research_project_members (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id  BIGINT UNSIGNED NOT NULL,
    user_id     BIGINT UNSIGNED NOT NULL,
    member_role ENUM('principal_investigator','co_investigator','research_assistant','other') NOT NULL DEFAULT 'co_investigator',
    role_other  VARCHAR(100) NULL,
    allocation_pct DECIMAL(5,2) NULL,
    joined_at   DATE NULL,
    left_at     DATE NULL,
    status      ENUM('active','removed','role_changed') NOT NULL DEFAULT 'active',
    change_reason VARCHAR(255) NULL,
    requested_by BIGINT UNSIGNED NULL,
    approved_by  BIGINT UNSIGNED NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_members_proj (project_id, status),
    KEY idx_members_user (user_id),
    CONSTRAINT fk_memb_proj FOREIGN KEY (project_id) REFERENCES research_projects (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_memb_user FOREIGN KEY (user_id)    REFERENCES users (id)            ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE research_budgets (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id       BIGINT UNSIGNED NOT NULL,
    requested_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    approved_amount  DECIMAL(12,2) NOT NULL DEFAULT 0,
    allocated_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    released_amount  DECIMAL(12,2) NOT NULL DEFAULT 0,
    spent_amount     DECIMAL(12,2) NOT NULL DEFAULT 0,
    currency         CHAR(3) NOT NULL DEFAULT 'ETB',
    status           ENUM('draft','submitted','under_review','approved','revision_required','rejected') NOT NULL DEFAULT 'draft',
    approved_by      BIGINT UNSIGNED NULL,
    approved_at      DATETIME NULL,
    created_by       BIGINT UNSIGNED NULL,
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_budget_project (project_id),
    CONSTRAINT fb_bud_proj FOREIGN KEY (project_id)  REFERENCES research_projects (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fb_bud_by   FOREIGN KEY (approved_by) REFERENCES users (id)             ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE research_budget_items (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    budget_id       BIGINT UNSIGNED NOT NULL,
    item_category   ENUM('equipment','materials','data_collection','transportation','research_assistants','laboratory','training','other') NOT NULL,
    description     VARCHAR(255) NOT NULL,
    requested_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    approved_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    released_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    spent_amount    DECIMAL(12,2) NOT NULL DEFAULT 0,
    justification   TEXT NULL,
    sort_order      INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_bitems_budget (budget_id),
    CONSTRAINT fk_bitem_budget FOREIGN KEY (budget_id) REFERENCES research_budgets (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Every financial change is fully recorded (spec §38, §104). No silent edits.
CREATE TABLE budget_revisions (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id    BIGINT UNSIGNED NOT NULL,
    budget_item_id BIGINT UNSIGNED NULL,
    revision_type ENUM('reallocation','additional_funding','reduction') NOT NULL,
    old_amount    DECIMAL(12,2) NOT NULL,
    new_amount    DECIMAL(12,2) NOT NULL,
    reason        TEXT NOT NULL,
    status        ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
    requested_by  BIGINT UNSIGNED NULL,
    requested_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    approved_by   BIGINT UNSIGNED NULL,
    approved_at   DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_brev_project (project_id, status),
    CONSTRAINT fk_brev_proj FOREIGN KEY (project_id)     REFERENCES research_projects (id)    ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_brev_item FOREIGN KEY (budget_item_id) REFERENCES research_budget_items (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_brev_req  FOREIGN KEY (requested_by)   REFERENCES users (id)                ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_brev_app  FOREIGN KEY (approved_by)    REFERENCES users (id)                ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE funding_releases (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id       BIGINT UNSIGNED NOT NULL,
    budget_id        BIGINT UNSIGNED NOT NULL,
    amount           DECIMAL(12,2) NOT NULL,
    release_reference VARCHAR(100) NULL,
    note             TEXT NULL,
    released_by      BIGINT UNSIGNED NULL,
    released_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_fundrel_project (project_id),
    CONSTRAINT fk_frel_proj FOREIGN KEY (project_id)  REFERENCES research_projects (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_frel_bud  FOREIGN KEY (budget_id)   REFERENCES research_budgets (id)  ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_frel_by   FOREIGN KEY (released_by) REFERENCES users (id)             ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Official research agreement (spec §13, Rules 10–12): issued by the Research
-- Directorate, signed by the researcher before the research becomes ACTIVE.
-- Every re-issue creates a NEW version row — previous versions are preserved.
CREATE TABLE research_agreements (
    id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    agreement_number   VARCHAR(40) NOT NULL,          -- MWU-RPEE-AGR-YYYY-NNNN
    application_id     BIGINT UNSIGNED NOT NULL,
    project_id         BIGINT UNSIGNED NULL,
    version            INT NOT NULL DEFAULT 1,
    status             ENUM('draft','issued','signed','superseded','cancelled') NOT NULL DEFAULT 'draft',
    title              VARCHAR(250) NOT NULL,
    terms              TEXT NULL,
    agreed_amount      DECIMAL(12,2) NULL,
    previous_version_id BIGINT UNSIGNED NULL,
    prepared_by        BIGINT UNSIGNED NULL,
    prepared_at        DATETIME NULL,
    issued_by          BIGINT UNSIGNED NULL,
    issued_at          DATETIME NULL,
    signed_by          BIGINT UNSIGNED NULL,
    signed_at          DATETIME NULL,
    signature_name     VARCHAR(150) NULL,
    signature_note     TEXT NULL,
    created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_agr_number (agreement_number),
    KEY idx_agr_app (application_id, status),
    CONSTRAINT fk_agr_app FOREIGN KEY (application_id)      REFERENCES research_applications (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_agr_prj FOREIGN KEY (project_id)          REFERENCES research_projects (id)    ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_agr_pre FOREIGN KEY (prepared_by)         REFERENCES users (id)                ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_agr_iss FOREIGN KEY (issued_by)           REFERENCES users (id)                ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_agr_sig FOREIGN KEY (signed_by)           REFERENCES users (id)                ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_agr_pvv FOREIGN KEY (previous_version_id) REFERENCES research_agreements (id)  ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE research_milestones (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id          BIGINT UNSIGNED NOT NULL,
    milestone_name      VARCHAR(200) NOT NULL,
    description         TEXT NULL,
    due_date            DATE NULL,
    responsible_user_id BIGINT UNSIGNED NULL,
    status              ENUM('pending','in_progress','completed','delayed','cancelled') NOT NULL DEFAULT 'pending',
    progress_pct        DECIMAL(5,2) NOT NULL DEFAULT 0,
    completion_date     DATE NULL,
    sort_order          INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_mile_proj (project_id, status),
    CONSTRAINT fk_mile_proj FOREIGN KEY (project_id) REFERENCES research_projects (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_mile_user FOREIGN KEY (responsible_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE progress_reports (
    id                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id             BIGINT UNSIGNED NOT NULL,
    report_number          VARCHAR(40) NOT NULL,
    reporting_period       VARCHAR(100) NULL,
    period_start           DATE NULL,
    period_end             DATE NULL,
    progress_pct           DECIMAL(5,2) NOT NULL DEFAULT 0,
    completed_activities   TEXT NULL,
    pending_activities     TEXT NULL,
    challenges             TEXT NULL,
    achievements           TEXT NULL,
    financial_progress_pct DECIMAL(5,2) NULL,
    amount_spent_to_date   DECIMAL(12,2) NULL,
    status                 ENUM('draft','submitted','approved','revision_required','rejected') NOT NULL DEFAULT 'draft',
    submitted_at           DATETIME NULL,
    reviewed_by            BIGINT UNSIGNED NULL,
    reviewed_at            DATETIME NULL,
    review_comments        TEXT NULL,
    created_by             BIGINT UNSIGNED NULL,
    created_at             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_preport_number (report_number),
    KEY idx_preport_proj (project_id, status),
    CONSTRAINT fk_preport_proj FOREIGN KEY (project_id) REFERENCES research_projects (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_preport_rev  FOREIGN KEY (reviewed_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Extension of project deadline (spec §43) — distinct from the Extension module.
CREATE TABLE research_extension_requests (
    id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id         BIGINT UNSIGNED NOT NULL,
    original_deadline  DATE NOT NULL,
    requested_deadline DATE NOT NULL,
    approved_deadline  DATE NULL,
    reason             TEXT NOT NULL,
    status             ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
    requested_by       BIGINT UNSIGNED NULL,
    requested_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    decided_by         BIGINT UNSIGNED NULL,
    decided_at         DATETIME NULL,
    decision_comments  TEXT NULL,
    PRIMARY KEY (id),
    KEY idx_extreq_proj (project_id, status),
    CONSTRAINT fk_extreq_proj FOREIGN KEY (project_id) REFERENCES research_projects (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_extreq_req  FOREIGN KEY (requested_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_extreq_dec  FOREIGN KEY (decided_by)   REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Suspension / cancellation / termination etc. (spec §44) — recorded, never silent.
CREATE TABLE project_actions (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id      BIGINT UNSIGNED NOT NULL,
    action_type     ENUM('suspend','resume','cancel','terminate','withdraw') NOT NULL,
    reason          TEXT NOT NULL,
    comments        TEXT NULL,
    previous_status VARCHAR(60) NULL,
    new_status      VARCHAR(60) NULL,
    approved_by     BIGINT UNSIGNED NULL,
    action_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_paction_proj (project_id),
    CONSTRAINT fk_pact_proj FOREIGN KEY (project_id)  REFERENCES research_projects (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_pact_by   FOREIGN KEY (approved_by) REFERENCES users (id)             ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- SECTION H — FINALIZATION (spec §45–§50)
-- =============================================================================

CREATE TABLE presentations (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id    BIGINT UNSIGNED NOT NULL,
    scheduled_at  DATETIME NOT NULL,
    venue         VARCHAR(150) NULL,
    status        ENUM('scheduled','presented','cancelled','rescheduled','postponed') NOT NULL DEFAULT 'scheduled',
    presented_at  DATETIME NULL,
    scheduled_by  BIGINT UNSIGNED NULL,
    notes         VARCHAR(255) NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pres_proj (project_id, status),
    CONSTRAINT fk_pres_proj FOREIGN KEY (project_id)   REFERENCES research_projects (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_pres_by   FOREIGN KEY (scheduled_by) REFERENCES users (id)             ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE presentation_panels (
    id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    presentation_id      BIGINT UNSIGNED NOT NULL,
    user_id              BIGINT UNSIGNED NULL, -- NULL for authorized external experts (§48)
    member_name          VARCHAR(150) NOT NULL,
    member_role          ENUM('chair','member','external_expert','secretary') NOT NULL DEFAULT 'member',
    affiliation          VARCHAR(150) NULL,
    appointment_reference VARCHAR(100) NULL,
    created_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_panel_pres (presentation_id),
    CONSTRAINT fk_panel_pres FOREIGN KEY (presentation_id) REFERENCES presentations (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_panel_user FOREIGN KEY (user_id)         REFERENCES users (id)          ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE presentation_evaluations (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    presentation_id  BIGINT UNSIGNED NOT NULL,
    evaluator_user_id BIGINT UNSIGNED NOT NULL,
    recommendation   ENUM('accept','accept_with_revision','major_revision','reject') NULL,
    overall_comment  TEXT NULL,
    status           ENUM('draft','submitted') NOT NULL DEFAULT 'draft',
    submitted_at     DATETIME NULL,
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_preseval (presentation_id, evaluator_user_id),
    CONSTRAINT fk_pev_pres FOREIGN KEY (presentation_id)   REFERENCES presentations (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_pev_usr  FOREIGN KEY (evaluator_user_id) REFERENCES users (id)         ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE presentation_scores (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    evaluation_id BIGINT UNSIGNED NOT NULL,
    criterion_id  BIGINT UNSIGNED NOT NULL,
    score         DECIMAL(5,2) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pscore (evaluation_id, criterion_id),
    CONSTRAINT fk_ps_eval FOREIGN KEY (evaluation_id) REFERENCES presentation_evaluations (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_ps_crit FOREIGN KEY (criterion_id)  REFERENCES evaluation_criteria (id)     ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- SECTION I — OUTPUTS & PUBLICATION (spec §51–§53)
-- =============================================================================

CREATE TABLE research_outputs (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id   BIGINT UNSIGNED NOT NULL,
    output_type  ENUM('final_report','publication','conference_paper','book_chapter','policy_brief','dataset','training_material','patent','technology','community_output','other') NOT NULL,
    title        VARCHAR(250) NOT NULL,
    description  TEXT NULL,
    status       ENUM('draft','submitted','verified','accepted','published','archived') NOT NULL DEFAULT 'draft',
    recorded_by  BIGINT UNSIGNED NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_outputs_proj (project_id, output_type),
    CONSTRAINT fk_out_proj FOREIGN KEY (project_id) REFERENCES research_projects (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_out_by   FOREIGN KEY (recorded_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE publications (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    output_id           BIGINT UNSIGNED NULL,
    project_id          BIGINT UNSIGNED NULL,
    title               VARCHAR(250) NOT NULL,
    journal_name        VARCHAR(200) NULL,
    publisher           VARCHAR(150) NULL,
    issn                VARCHAR(20) NULL,
    doi                 VARCHAR(100) NULL,
    volume              VARCHAR(20) NULL,
    issue               VARCHAR(20) NULL,
    pages               VARCHAR(30) NULL,
    publication_year    SMALLINT NULL,
    publication_type    ENUM('journal_article','conference_paper','book_chapter','book','other') NOT NULL DEFAULT 'journal_article',
    url                 VARCHAR(255) NULL,
    status              ENUM('draft','submitted','under_review','revision_required','accepted','published','rejected','withdrawn') NOT NULL DEFAULT 'draft',
    submission_date     DATE NULL,
    acceptance_date     DATE NULL,
    published_date      DATE NULL,
    created_by          BIGINT UNSIGNED NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pub_project (project_id),
    KEY idx_pub_status (status),
    KEY idx_pub_doi (doi),
    CONSTRAINT fk_pub_output FOREIGN KEY (output_id)  REFERENCES research_outputs (id)  ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_pub_proj   FOREIGN KEY (project_id) REFERENCES research_projects (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_pub_by     FOREIGN KEY (created_by) REFERENCES users (id)             ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE publication_authors (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    publication_id   BIGINT UNSIGNED NOT NULL,
    user_id          BIGINT UNSIGNED NULL,
    author_name      VARCHAR(150) NOT NULL,
    affiliation      VARCHAR(150) NULL,
    author_order     INT NOT NULL DEFAULT 1,
    is_corresponding TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_pubauth_pub (publication_id),
    CONSTRAINT fk_pubauth_pub  FOREIGN KEY (publication_id) REFERENCES publications (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_pubauth_user FOREIGN KEY (user_id)        REFERENCES users (id)        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- SECTION J — ETHICS (spec §30–§34)
-- =============================================================================

CREATE TABLE ethics_applications (
    id                       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    application_number       VARCHAR(40) NOT NULL,        -- MWU-RPEE-ETH-YYYY-NNNN
    research_application_id  BIGINT UNSIGNED NOT NULL,
    researcher_user_id       BIGINT UNSIGNED NOT NULL,
    participant_count        INT NULL,
    participant_description  TEXT NULL,
    research_type_summary    VARCHAR(255) NULL,
    risk_level               ENUM('minimal','low','moderate','high') NULL,
    consent_process          TEXT NULL,
    confidentiality_measures TEXT NULL,
    data_collection_summary  TEXT NULL,
    review_track             ENUM('screening','full') NOT NULL DEFAULT 'full',
    screening_note           TEXT NULL,
    current_stage_id         BIGINT UNSIGNED NULL,
    workflow_id              BIGINT UNSIGNED NULL,
    status_code              VARCHAR(60) NOT NULL DEFAULT 'DRAFT',
    status                   ENUM('draft','submitted','under_screening','screened_no_review','under_review','revision_required','approved','approved_with_conditions','rejected','exempt','expired','withdrawn') NOT NULL DEFAULT 'draft',
    submitted_at             DATETIME NULL,
    decided_at               DATETIME NULL,
    approved_until           DATE NULL,                   -- ethics expiry (§33)
    created_by               BIGINT UNSIGNED NULL,
    created_at               TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at               TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_eth_app_number (application_number),
    UNIQUE KEY uq_eth_research_app (research_application_id),
    KEY idx_eth_status (status),
    KEY idx_eth_expiry (approved_until),
    CONSTRAINT fk_eth_rapp FOREIGN KEY (research_application_id) REFERENCES research_applications (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_eth_usr  FOREIGN KEY (researcher_user_id)      REFERENCES users (id)                ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_eth_wf   FOREIGN KEY (workflow_id)             REFERENCES workflows (id)            ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_eth_stg  FOREIGN KEY (current_stage_id)        REFERENCES workflow_stages (id)      ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ethics_reviews (
    id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ethics_application_id BIGINT UNSIGNED NOT NULL,
    reviewer_user_id     BIGINT UNSIGNED NOT NULL,
    assigned_by          BIGINT UNSIGNED NULL,
    assigned_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    review_deadline      DATE NULL,
    status               ENUM('assigned','accepted','declined','completed','conflict_declared') NOT NULL DEFAULT 'assigned',
    comments             TEXT NULL,
    recommendation       ENUM('approve','approve_with_conditions','revision','reject','exempt') NULL,
    submitted_at         DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ethrev (ethics_application_id, reviewer_user_id),
    KEY idx_ethrev_reviewer (reviewer_user_id, status),
    CONSTRAINT fk_ethrev_app FOREIGN KEY (ethics_application_id) REFERENCES ethics_applications (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ethrev_usr FOREIGN KEY (reviewer_user_id)      REFERENCES users (id)               ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ethrev_by  FOREIGN KEY (assigned_by)           REFERENCES users (id)               ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ethics_decisions (
    id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ethics_application_id BIGINT UNSIGNED NOT NULL,
    decision             ENUM('approved','approved_with_conditions','revision_required','rejected','exempt') NOT NULL,
    conditions           TEXT NULL,
    comments             TEXT NULL,
    decision_reference   VARCHAR(100) NULL,
    decided_by           BIGINT UNSIGNED NULL,
    decided_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at           DATE NULL,
    created_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ethdec_app (ethics_application_id),
    CONSTRAINT fk_ethdec_app FOREIGN KEY (ethics_application_id) REFERENCES ethics_applications (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ethdec_usr FOREIGN KEY (decided_by)            REFERENCES users (id)               ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ethics_amendments (
    id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ethics_application_id BIGINT UNSIGNED NOT NULL,
    amendment_number      VARCHAR(40) NOT NULL,
    amendment_type        ENUM('methodology','participants','location','data_collection','instruments','other') NOT NULL,
    description           TEXT NOT NULL,
    status                ENUM('submitted','under_review','approved','revision_required','rejected','withdrawn') NOT NULL DEFAULT 'submitted',
    requested_by          BIGINT UNSIGNED NULL,
    requested_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    decided_by            BIGINT UNSIGNED NULL,
    decided_at            DATETIME NULL,
    decision_comments     TEXT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ethamend_number (amendment_number),
    KEY idx_ethamend_app (ethics_application_id, status),
    CONSTRAINT fk_ethamend_app FOREIGN KEY (ethics_application_id) REFERENCES ethics_applications (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ethamend_req FOREIGN KEY (requested_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_ethamend_dec FOREIGN KEY (decided_by)   REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ethics_certificates (
    id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ethics_application_id BIGINT UNSIGNED NOT NULL,
    certificate_number    VARCHAR(60) NOT NULL,
    issued_date           DATE NULL,
    expires_at            DATE NULL,
    issued_by             BIGINT UNSIGNED NULL,
    notes                 VARCHAR(255) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ethcert_number (certificate_number),
    KEY idx_ethcert_app (ethics_application_id),
    CONSTRAINT fk_ethcert_app FOREIGN KEY (ethics_application_id) REFERENCES ethics_applications (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ethcert_by  FOREIGN KEY (issued_by)             REFERENCES users (id)               ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- SECTION K — EXTENSION MODULE (spec §54–§57)
-- =============================================================================

CREATE TABLE extension_calls (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    call_number         VARCHAR(40) NOT NULL,
    reference_number    VARCHAR(60) NULL,
    title               VARCHAR(200) NOT NULL,
    description         TEXT NULL,
    focus_areas         TEXT NULL,
    opens_at            DATETIME NULL,
    closes_at           DATETIME NULL,
    max_budget          DECIMAL(12,2) NULL,
    max_duration_months INT NULL,
    status              ENUM('draft','published','open','closed','under_evaluation','completed','archived') NOT NULL DEFAULT 'draft',
    published_at        DATETIME NULL,
    created_by          BIGINT UNSIGNED NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_extcall_number (call_number),
    KEY idx_extcall_status (status),
    CONSTRAINT fk_extcall_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE extension_applications (
    id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    application_number    VARCHAR(40) NOT NULL,
    call_id               BIGINT UNSIGNED NULL,
    pi_user_id            BIGINT UNSIGNED NOT NULL,
    title                 VARCHAR(250) NOT NULL,
    community             VARCHAR(150) NULL,
    location              VARCHAR(150) NULL,
    partner_organization  VARCHAR(200) NULL,
    target_beneficiaries  VARCHAR(255) NULL,
    objectives            TEXT NULL,
    planned_activities    TEXT NULL,
    duration_months       INT NULL,
    start_date            DATE NULL,
    end_date              DATE NULL,
    budget_requested      DECIMAL(12,2) NULL,
    budget_justification  TEXT NULL,
    expected_outputs      TEXT NULL,
    expected_outcomes     TEXT NULL,
    current_stage_id      BIGINT UNSIGNED NULL,
    workflow_id           BIGINT UNSIGNED NULL,
    status                ENUM('draft','submitted','under_department_review','under_college_review','under_evaluation','approved','rejected','returned','withdrawn') NOT NULL DEFAULT 'draft',
    submitted_at          DATETIME NULL,
    approved_at           DATETIME NULL,
    created_by            BIGINT UNSIGNED NULL,
    created_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_extapp_number (application_number),
    KEY idx_extapp_call (call_id),
    KEY idx_extapp_pi (pi_user_id),
    KEY idx_extapp_status (status),
    CONSTRAINT fk_extapp_call FOREIGN KEY (call_id)        REFERENCES extension_calls (id)  ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_extapp_pi   FOREIGN KEY (pi_user_id)     REFERENCES users (id)            ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_extapp_wf   FOREIGN KEY (workflow_id)    REFERENCES workflows (id)        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_extapp_stg  FOREIGN KEY (current_stage_id) REFERENCES workflow_stages (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE extension_projects (
    id                        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_number            VARCHAR(40) NOT NULL,
    application_id            BIGINT UNSIGNED NOT NULL,
    title                     VARCHAR(250) NOT NULL,
    pi_user_id                BIGINT UNSIGNED NOT NULL,
    community                 VARCHAR(150) NULL,
    location                  VARCHAR(150) NULL,
    status                    ENUM('active','suspended','completed','terminated','cancelled') NOT NULL DEFAULT 'active',
    planned_start             DATE NULL,
    planned_end               DATE NULL,
    actual_end                DATE NULL,
    approved_budget           DECIMAL(12,2) NULL,
    linked_research_project_id BIGINT UNSIGNED NULL,
    created_at                TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_extproj_number (project_number),
    UNIQUE KEY uq_extproj_application (application_id),
    KEY idx_extproj_pi (pi_user_id),
    CONSTRAINT fk_extproj_app FOREIGN KEY (application_id)             REFERENCES extension_applications (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_extproj_pi  FOREIGN KEY (pi_user_id)                 REFERENCES users (id)                  ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_extproj_rp  FOREIGN KEY (linked_research_project_id) REFERENCES research_projects (id)      ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE extension_activities (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id        BIGINT UNSIGNED NOT NULL,
    activity_name     VARCHAR(200) NOT NULL,
    description       TEXT NULL,
    planned_date      DATE NULL,
    actual_date       DATE NULL,
    status            ENUM('planned','in_progress','completed','cancelled') NOT NULL DEFAULT 'planned',
    beneficiary_count INT NULL,
    remarks           VARCHAR(255) NULL,
    sort_order        INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_extact_proj (project_id, status),
    CONSTRAINT fk_extact_proj FOREIGN KEY (project_id) REFERENCES extension_projects (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE extension_partners (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id      BIGINT UNSIGNED NOT NULL,
    partner_name    VARCHAR(200) NOT NULL,
    partner_type    ENUM('government','community_organization','ngo','industry','school','health_institution','other') NOT NULL DEFAULT 'other',
    contact_person  VARCHAR(150) NULL,
    contact_phone   VARCHAR(30) NULL,
    contact_email   VARCHAR(190) NULL,
    partnership_role VARCHAR(255) NULL,
    mou_reference   VARCHAR(100) NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_extpart_proj (project_id),
    CONSTRAINT fk_extpart_proj FOREIGN KEY (project_id) REFERENCES extension_projects (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE extension_impact_reports (
    id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id           BIGINT UNSIGNED NOT NULL,
    report_date          DATE NULL,
    participants_count   INT NULL,
    activities_completed INT NULL,
    outputs_achieved     TEXT NULL,
    outcomes             TEXT NULL,
    beneficiaries_count  INT NULL,
    impact_indicators    TEXT NULL,
    community_feedback   TEXT NULL,
    status               ENUM('draft','submitted','approved') NOT NULL DEFAULT 'draft',
    submitted_by         BIGINT UNSIGNED NULL,
    created_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_extimpact_proj (project_id),
    CONSTRAINT fk_extimpact_proj FOREIGN KEY (project_id) REFERENCES extension_projects (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_extimpact_by   FOREIGN KEY (submitted_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- SECTION L — DATA MANAGEMENT & REPOSITORY (spec §58–§62, §99)
-- =============================================================================

CREATE TABLE research_datasets (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id          BIGINT UNSIGNED NOT NULL,
    title               VARCHAR(250) NOT NULL,
    description         TEXT NULL,
    data_type           ENUM('csv','excel','spss','stata','r','zip','images','audio','video','other') NOT NULL DEFAULT 'other',
    year                SMALLINT NULL,
    keywords            VARCHAR(255) NULL,
    methodology_summary TEXT NULL,
    visibility          ENUM('public','university_only','college_only','restricted','confidential','embargoed','de_identified') NOT NULL DEFAULT 'restricted',
    embargo_until       DATE NULL,
    access_instructions TEXT NULL,
    status              ENUM('metadata_only','available','withdrawn') NOT NULL DEFAULT 'metadata_only',
    created_by          BIGINT UNSIGNED NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ds_proj (project_id),
    KEY idx_ds_visibility (visibility),
    CONSTRAINT fk_ds_proj FOREIGN KEY (project_id) REFERENCES research_projects (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ds_by   FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE dataset_access_requests (
    id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    dataset_id           BIGINT UNSIGNED NOT NULL,
    requester_user_id    BIGINT UNSIGNED NOT NULL,
    request_reason       TEXT NOT NULL,
    intended_use         TEXT NULL,
    status               ENUM('pending','approved','rejected','expired') NOT NULL DEFAULT 'pending',
    requested_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    decided_by           BIGINT UNSIGNED NULL,
    decided_at           DATETIME NULL,
    decision_note        TEXT NULL,
    access_granted_until DATE NULL,
    PRIMARY KEY (id),
    KEY idx_dsar_dataset (dataset_id, status),
    CONSTRAINT fk_dsar_ds   FOREIGN KEY (dataset_id)        REFERENCES research_datasets (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_dsar_user FOREIGN KEY (requester_user_id) REFERENCES users (id)             ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_dsar_dec  FOREIGN KEY (decided_by)        REFERENCES users (id)             ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE repository_records (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    repository_id_code VARCHAR(40) NOT NULL,          -- MWU-RPEE-REP-YYYY-NNNN
    project_id        BIGINT UNSIGNED NOT NULL,
    title             VARCHAR(250) NOT NULL,
    abstract          TEXT NULL,
    keywords          VARCHAR(255) NULL,
    year              SMALLINT NULL,
    research_type_id  BIGINT UNSIGNED NULL,
    research_theme_id BIGINT UNSIGNED NULL,
    college_id        BIGINT UNSIGNED NULL,
    department_id     BIGINT UNSIGNED NULL,
    citation_text     TEXT NULL,
    permanent_url     VARCHAR(255) NULL,
    visibility        ENUM('public','university_only','college_only','restricted','confidential','embargoed') NOT NULL DEFAULT 'university_only',
    status            ENUM('draft','published','embargoed','archived','withdrawn') NOT NULL DEFAULT 'draft',
    published_at      DATETIME NULL,
    archived_at       DATETIME NULL,
    created_by        BIGINT UNSIGNED NULL,
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_repo_code (repository_id_code),
    UNIQUE KEY uq_repo_project (project_id),
    KEY idx_repo_status (status),
    KEY idx_repo_visibility (visibility),
    KEY idx_repo_year (year),
    CONSTRAINT fk_repo_proj  FOREIGN KEY (project_id)        REFERENCES research_projects (id)  ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_repo_type  FOREIGN KEY (research_type_id)  REFERENCES research_types (id)     ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_repo_theme FOREIGN KEY (research_theme_id) REFERENCES research_themes (id)    ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_repo_col   FOREIGN KEY (college_id)        REFERENCES colleges (id)           ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_repo_dept  FOREIGN KEY (department_id)     REFERENCES departments (id)        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE repository_embargoes (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    repository_record_id BIGINT UNSIGNED NOT NULL,
    start_date          DATE NULL,
    end_date            DATE NOT NULL,
    reason              VARCHAR(255) NULL,
    approved_by         BIGINT UNSIGNED NULL,
    visibility_before   ENUM('public','university_only','college_only','restricted','confidential','embargoed') NOT NULL DEFAULT 'confidential',
    visibility_after    ENUM('public','university_only','college_only','restricted','confidential','embargoed') NOT NULL DEFAULT 'public',
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_embargo_repo (repository_record_id),
    CONSTRAINT fk_emb_repo FOREIGN KEY (repository_record_id) REFERENCES repository_records (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_emb_by   FOREIGN KEY (approved_by)          REFERENCES users (id)              ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- SECTION M — DOCUMENTS (spec §26, §63–§65, §90, §105)
-- =============================================================================

CREATE TABLE documents (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    document_type_id BIGINT UNSIGNED NOT NULL,
    entity_type      VARCHAR(50) NOT NULL,
    entity_id        BIGINT UNSIGNED NOT NULL,
    title            VARCHAR(250) NOT NULL,
    version          INT NOT NULL DEFAULT 1,
    root_document_id BIGINT UNSIGNED NULL,   -- head of the version chain (§26)
    superseded_by_id BIGINT UNSIGNED NULL,
    file_path        VARCHAR(255) NOT NULL,  -- relative to storage/, never public (§64)
    original_filename VARCHAR(255) NOT NULL, -- original name kept as metadata only (§90)
    file_size        BIGINT UNSIGNED NOT NULL DEFAULT 0,
    mime_type        VARCHAR(120) NOT NULL,
    file_hash        CHAR(64) NULL,
    visibility       ENUM('private','entity_team','college','university','public') NOT NULL DEFAULT 'entity_team',
    status           ENUM('active','superseded','archived','deleted') NOT NULL DEFAULT 'active',
    uploaded_by      BIGINT UNSIGNED NULL,
    uploaded_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deletion_reason  VARCHAR(255) NULL,
    deleted_by       BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    KEY idx_doc_entity (entity_type, entity_id),
    KEY idx_doc_root (root_document_id),
    KEY idx_doc_uploader (uploaded_by),
    CONSTRAINT fk_doc_type  FOREIGN KEY (document_type_id) REFERENCES document_types (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_doc_user  FOREIGN KEY (uploaded_by)      REFERENCES users (id)          ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_doc_delby FOREIGN KEY (deleted_by)       REFERENCES users (id)          ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_doc_root  FOREIGN KEY (root_document_id) REFERENCES documents (id)      ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_doc_supby FOREIGN KEY (superseded_by_id) REFERENCES documents (id)      ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sensitive document access trail (spec §105).
CREATE TABLE document_access_log (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    document_id BIGINT UNSIGNED NOT NULL,
    user_id     BIGINT UNSIGNED NULL,
    access_type ENUM('view','download','upload','delete','archive','failed_access') NOT NULL,
    ip_address  VARCHAR(45) NULL,
    details     VARCHAR(255) NULL,
    accessed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_dalog_doc (document_id, accessed_at),
    CONSTRAINT fk_dalog_doc  FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_dalog_user FOREIGN KEY (user_id)     REFERENCES users (id)    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- FK from researchers.profile_photo → documents (deferred above).
ALTER TABLE researchers
    ADD CONSTRAINT fk_res_photo FOREIGN KEY (profile_photo_document_id)
    REFERENCES documents (id) ON DELETE SET NULL ON UPDATE CASCADE;

-- =============================================================================
-- SECTION N — INTEGRITY, APPEALS, PLATFORM (spec §65–§71)
-- =============================================================================

CREATE TABLE research_integrity_cases (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    case_number         VARCHAR(40) NOT NULL,
    case_type           ENUM('plagiarism','fabrication','falsification','authorship_dispute','data_integrity','ethics_concern','other') NOT NULL,
    subject_user_id     BIGINT UNSIGNED NULL,
    related_entity_type VARCHAR(50) NULL,
    related_entity_id   BIGINT UNSIGNED NULL,
    description         TEXT NOT NULL,
    status              ENUM('reported','preliminary_review','investigation','decision','action_taken','closed','dismissed') NOT NULL DEFAULT 'reported',
    assigned_committee_id BIGINT UNSIGNED NULL,
    is_confidential     TINYINT(1) NOT NULL DEFAULT 1,
    opened_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    closed_at           DATETIME NULL,
    outcome             TEXT NULL,
    created_by          BIGINT UNSIGNED NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_integrity_number (case_number),
    KEY idx_integrity_status (status),
    CONSTRAINT fk_integrity_subj  FOREIGN KEY (subject_user_id)      REFERENCES users (id)      ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_integrity_com   FOREIGN KEY (assigned_committee_id) REFERENCES committees (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE appeals (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    appeal_number    VARCHAR(40) NOT NULL,
    appealable_type  VARCHAR(50) NOT NULL,
    appealable_id    BIGINT UNSIGNED NOT NULL,
    appellant_user_id BIGINT UNSIGNED NOT NULL,
    reason           TEXT NOT NULL,
    status           ENUM('submitted','under_review','upheld','rejected','withdrawn') NOT NULL DEFAULT 'submitted',
    submitted_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_by      BIGINT UNSIGNED NULL,
    reviewed_at      DATETIME NULL,
    decision_comments TEXT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_appeal_number (appeal_number),
    KEY idx_appeal_target (appealable_type, appealable_id),
    KEY idx_appeal_status (status),
    CONSTRAINT fk_appeal_user FOREIGN KEY (appellant_user_id) REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_appeal_rev  FOREIGN KEY (reviewed_by)       REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id          BIGINT UNSIGNED NOT NULL,
    notification_type VARCHAR(60) NOT NULL,
    title            VARCHAR(200) NOT NULL,
    body             TEXT NULL,
    link_url         VARCHAR(255) NULL,
    entity_type      VARCHAR(50) NULL,
    entity_id        BIGINT UNSIGNED NULL,
    priority         ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
    read_at          DATETIME NULL,
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notif_user (user_id, read_at),
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     BIGINT UNSIGNED NULL,
    action      VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) NULL,
    entity_id   BIGINT UNSIGNED NULL,
    old_values  LONGTEXT NULL,
    new_values  LONGTEXT NULL,
    ip_address  VARCHAR(45) NULL,
    user_agent  VARCHAR(255) NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_user (user_id, created_at),
    KEY idx_audit_entity (entity_type, entity_id),
    KEY idx_audit_action (action, created_at),
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE system_settings (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    setting_key   VARCHAR(100) NOT NULL,
    setting_value TEXT NULL,
    setting_group VARCHAR(50) NOT NULL DEFAULT 'general',
    value_type    VARCHAR(20) NOT NULL DEFAULT 'string',
    description   VARCHAR(255) NULL,
    updated_by    BIGINT UNSIGNED NULL,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_settings_key (setting_key),
    CONSTRAINT fk_settings_by FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE number_sequences (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    seq_key    VARCHAR(50) NOT NULL,
    seq_year   SMALLINT NOT NULL,
    next_value INT NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_seq (seq_key, seq_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
