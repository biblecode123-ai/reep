-- Generated from schema.sql by make_sqlite.php — do not edit by hand.

PRAGMA foreign_keys = OFF;
PRAGMA foreign_keys = OFF;

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
DROP TABLE IF EXISTS project_evaluation_indicator_scores;
DROP TABLE IF EXISTS project_final_report_decisions;
DROP TABLE IF EXISTS project_evaluations;
DROP TABLE IF EXISTS project_evaluator_assignments;
DROP TABLE IF EXISTS project_final_reports;
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
DROP TABLE IF EXISTS evaluations;
DROP TABLE IF EXISTS rubric_indicators;
DROP TABLE IF EXISTS rubric_criteria;
DROP TABLE IF EXISTS rubric_templates;
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

PRAGMA foreign_keys = ON;

CREATE TABLE colleges (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    name          TEXT NOT NULL,
    code          TEXT  NOT NULL,
    description   TEXT NULL,
    status TEXT CHECK (status IN ('active','inactive')) NOT NULL DEFAULT 'active',
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_colleges_code UNIQUE (code)
);

CREATE TABLE departments (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    college_id    INTEGER NOT NULL,
    name          TEXT NOT NULL,
    code          TEXT  NOT NULL,
    status TEXT CHECK (status IN ('active','inactive')) NOT NULL DEFAULT 'active',
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_departments_college_code UNIQUE (college_id, code),
    FOREIGN KEY (college_id) REFERENCES colleges (id) ON DELETE RESTRICT ON UPDATE CASCADE
);

CREATE TABLE users (
    id                    INTEGER PRIMARY KEY AUTOINCREMENT,
    employee_id           TEXT  NULL,
    full_name             TEXT NOT NULL,
    email                 TEXT NOT NULL,
    phone                 TEXT  NULL,
    password_hash         TEXT NOT NULL,
    status TEXT CHECK (status IN ('active','inactive','suspended','pending')) NOT NULL DEFAULT 'pending',
    position_title        TEXT NULL,
    college_id            INTEGER NULL,
    department_id         INTEGER NULL,
    must_change_password  INTEGER NOT NULL DEFAULT 0,
    last_login_at         DATETIME NULL,
    last_login_ip         TEXT NULL,
    created_by            INTEGER NULL,
    created_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_users_email UNIQUE (email),
    CONSTRAINT uq_users_employee_id UNIQUE (employee_id),
    FOREIGN KEY (college_id)    REFERENCES colleges (id)    ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE roles (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    name         TEXT NOT NULL,
    display_name TEXT NOT NULL,
    description  TEXT NULL,
    is_system    INTEGER NOT NULL DEFAULT 0,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_roles_name UNIQUE (name)
);

CREATE TABLE permissions (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    name        TEXT NOT NULL,
    module      TEXT NOT NULL,
    description TEXT NULL,
    CONSTRAINT uq_permissions_name UNIQUE (name));

CREATE TABLE role_permissions (
    role_id       INTEGER NOT NULL,
    permission_id INTEGER NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles (id)       ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE user_roles (
    user_id     INTEGER NOT NULL,
    role_id     INTEGER NOT NULL,
    assigned_by INTEGER NULL,
    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, role_id),
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE password_resets (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id    INTEGER NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at    DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_password_resets_token UNIQUE (token_hash),
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE login_attempts (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    identifier   TEXT NOT NULL,
    ip_address   TEXT  NOT NULL,
    successful   INTEGER NOT NULL DEFAULT 0,
    attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP);

CREATE TABLE user_delegations (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    from_user_id INTEGER NOT NULL,
    to_user_id   INTEGER NOT NULL,
    scope_note   TEXT NULL,
    start_date   DATE NOT NULL,
    end_date     DATE NOT NULL,
    status TEXT CHECK (status IN ('active','revoked','expired')) NOT NULL DEFAULT 'active',
    created_by   INTEGER NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (from_user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (to_user_id)   REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE researchers (
    id                       INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id                  INTEGER NOT NULL,
    academic_position        TEXT NULL,
    qualification            TEXT NULL,
    specialization           TEXT NULL,
    research_interests       TEXT NULL,
    research_areas           TEXT NULL,
    biography                TEXT NULL,
    gender                   TEXT NULL,
    orcid                    TEXT NULL,
    google_scholar           TEXT NULL,
    profile_photo_document_id INTEGER NULL, -- FK added after documents table
    created_at               TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at               TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_researchers_user UNIQUE (user_id),
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE
);

CREATE TABLE committees (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    name           TEXT NOT NULL,
    code           TEXT  NOT NULL,
    committee_type TEXT CHECK (committee_type IN ('university_research','college_research','ethics','publication','extension','presentation_panel','INTEGERegrity','other')) NOT NULL DEFAULT 'other',
    college_id     INTEGER NULL,
    description    TEXT NULL,
    quorum_size    INTEGER NULL, -- POLICY DECISION REQUIRED (REP-1): only enforced if set
    status TEXT CHECK (status IN ('active','inactive')) NOT NULL DEFAULT 'active',
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_committees_code UNIQUE (code),
    FOREIGN KEY (college_id) REFERENCES colleges (id) ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE committee_members (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    committee_id   INTEGER NOT NULL,
    user_id        INTEGER NOT NULL,
    member_role TEXT CHECK (member_role IN ('chair','secretary','member')) NOT NULL DEFAULT 'member',
    appointed_from DATE NULL,
    appointed_to   DATE NULL,
    status TEXT CHECK (status IN ('active','retired')) NOT NULL DEFAULT 'active',
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_cm_committee_user UNIQUE (committee_id, user_id),
    FOREIGN KEY (committee_id) REFERENCES committees (id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (user_id)      REFERENCES users (id)      ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE research_themes (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    name        TEXT NOT NULL,
    code        TEXT  NOT NULL,
    description TEXT NULL,
    status TEXT CHECK (status IN ('active','inactive')) NOT NULL DEFAULT 'active',
    created_at DATETIME,
    updated_at DATETIME,
    CONSTRAINT uq_themes_code UNIQUE (code)
);

CREATE TABLE research_types (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    name        TEXT NOT NULL,
    code        TEXT  NOT NULL,
    description TEXT NULL,
    status TEXT CHECK (status IN ('active','inactive')) NOT NULL DEFAULT 'active',
    CONSTRAINT uq_types_code UNIQUE (code)
);

CREATE TABLE document_types (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    type_code   TEXT NOT NULL,
    name        TEXT NOT NULL,
    description TEXT NULL,
    status TEXT CHECK (status IN ('active','inactive')) NOT NULL DEFAULT 'active',
    CONSTRAINT uq_document_types_code UNIQUE (type_code)
);

CREATE TABLE workflows (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    name        TEXT NOT NULL,
    entity_type TEXT NOT NULL,
    description TEXT NULL,
    is_active   INTEGER NOT NULL DEFAULT 1,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_workflows_entity_name UNIQUE (entity_type, name)
);

CREATE TABLE workflow_stages (
    id                    INTEGER PRIMARY KEY AUTOINCREMENT,
    workflow_id           INTEGER NOT NULL,
    stage_code            TEXT NOT NULL,
    stage_name            TEXT NOT NULL,
    stage_type TEXT CHECK (stage_type IN ('start','normal','terminal')) NOT NULL DEFAULT 'normal',
    is_terminal_exception INTEGER NOT NULL DEFAULT 0, -- rejected/withdrawn/… (§18)
    sort_order            INTEGER NOT NULL DEFAULT 0,
    description           TEXT NULL,
    responsible_role_id   INTEGER NULL,
    CONSTRAINT uq_stages_wf_code UNIQUE (workflow_id, stage_code),
    FOREIGN KEY (workflow_id) REFERENCES workflows (id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (responsible_role_id) REFERENCES roles (id) ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE workflow_transitions (
    id                 INTEGER PRIMARY KEY AUTOINCREMENT,
    workflow_id        INTEGER NOT NULL,
    from_stage_id      INTEGER NOT NULL,
    to_stage_id        INTEGER NOT NULL,
    transition_name    TEXT NOT NULL,
    required_permission TEXT NULL,
    condition_key      TEXT NULL, -- evaluated by WorkflowService (§96)
    sort_order         INTEGER NOT NULL DEFAULT 0,
    is_active          INTEGER NOT NULL DEFAULT 1,
    CONSTRAINT uq_transitions UNIQUE (workflow_id, from_stage_id, to_stage_id, transition_name),
    FOREIGN KEY (workflow_id)   REFERENCES workflows (id)       ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (from_stage_id) REFERENCES workflow_stages (id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (to_stage_id)   REFERENCES workflow_stages (id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE workflow_history (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    workflow_id  INTEGER NULL,
    entity_type  TEXT NOT NULL,
    entity_id    INTEGER NOT NULL,
    from_stage_id INTEGER NULL,
    to_stage_id   INTEGER NULL,
    from_status  TEXT NULL,
    to_status    TEXT NULL,
    user_id      INTEGER NULL,
    comment      TEXT NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (workflow_id)   REFERENCES workflows (id)       ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (from_stage_id) REFERENCES workflow_stages (id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (to_stage_id)   REFERENCES workflow_stages (id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (user_id)       REFERENCES users (id)           ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE research_calls (
    id                              INTEGER PRIMARY KEY AUTOINCREMENT,
    call_number                     TEXT NOT NULL,       -- generated: MWU-RPEE-CALL-YYYY-NNNN
    reference_number                TEXT NOT NULL,
    title                           TEXT NOT NULL,
    description                     TEXT NULL,
    research_priorities             TEXT NULL,
    research_theme_id               INTEGER NULL,
    opens_at                        DATETIME NULL,
    closes_at                       DATETIME NULL,
    research_start_date             DATE NULL,
    completion_deadline             DATE NULL,
    max_duration_months             INTEGER NULL,
    max_budget                      NUMERIC NULL,
    budget_currency                 CHAR(3) NOT NULL DEFAULT 'ETB',
    max_applications_per_researcher INTEGER NULL DEFAULT 1,
    evaluation_mode TEXT CHECK (evaluation_mode IN ('open','single_blind','double_blind')) NOT NULL DEFAULT 'single_blind',
    required_evaluators             INTEGER NULL DEFAULT 2,         -- POLICY DECISION WF-2 default
    status TEXT CHECK (status IN ('draft','published','open','closed','under_evaluation','completed','archived')) NOT NULL DEFAULT 'draft',
    published_at                    DATETIME NULL,
    closed_at                       DATETIME NULL,
    created_by                      INTEGER NULL,
    created_at                      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_calls_number UNIQUE (call_number),
    CONSTRAINT uq_calls_reference UNIQUE (reference_number),
    FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (research_theme_id) REFERENCES research_themes (id) ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE research_call_eligibility (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    call_id       INTEGER NOT NULL,
    eligibility_key TEXT CHECK (eligibility_key IN ('eligible_college','eligible_department','research_theme','researcher_type','min_position')) NOT NULL,
    ref_id        INTEGER NULL,
    ref_value     TEXT NULL,
    FOREIGN KEY (call_id) REFERENCES research_calls (id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE research_call_requirements (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    call_id           INTEGER NOT NULL,
    document_type_id  INTEGER NOT NULL,
    requirement TEXT CHECK (requirement IN ('required','optional','conditional')) NOT NULL DEFAULT 'required',
    condition_note    TEXT NULL, -- for 'conditional' (e.g. ethics document)
    sort_order        INTEGER NOT NULL DEFAULT 0,
    CONSTRAINT uq_creq UNIQUE (call_id, document_type_id),
    FOREIGN KEY (call_id) REFERENCES research_calls (id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (document_type_id) REFERENCES document_types (id) ON DELETE RESTRICT ON UPDATE CASCADE
);

CREATE TABLE research_call_criteria (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    call_id        INTEGER NOT NULL,
    criterion_name TEXT NOT NULL,
    description    TEXT NULL,
    weight_pct     NUMERIC NOT NULL DEFAULT 0,
    sort_order     INTEGER NOT NULL DEFAULT 0,
    CONSTRAINT uq_ccrit UNIQUE (call_id, criterion_name),
    FOREIGN KEY (call_id) REFERENCES research_calls (id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE evaluation_criteria (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    call_id     INTEGER NULL, -- NULL => institution-wide criteria set
    applies_to TEXT CHECK (applies_to IN ('proposal','final','presentation','ethics')) NOT NULL DEFAULT 'proposal',
    name        TEXT NOT NULL,
    description TEXT NULL,
    weight_pct  NUMERIC NOT NULL DEFAULT 0,
    sort_order  INTEGER NOT NULL DEFAULT 0,
    is_active   INTEGER NOT NULL DEFAULT 1,
    FOREIGN KEY (call_id) REFERENCES research_calls (id) ON DELETE CASCADE ON UPDATE CASCADE
);

-- Four independent, versioned 100-point institutional evaluation rubrics.
CREATE TABLE rubric_templates (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    rubric_type TEXT CHECK (rubric_type IN ('college_proposal','college_completed_report','university_proposal','university_completed_report')) NOT NULL,
    name        TEXT NOT NULL,
    version     INTEGER NOT NULL,
    is_active   INTEGER NOT NULL DEFAULT 0,
    created_by  INTEGER NULL,
    approved_by INTEGER NULL,
    approved_at DATETIME NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_rubric_version UNIQUE (rubric_type, version),
    FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE rubric_criteria (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    template_id INTEGER NOT NULL,
    name        TEXT NOT NULL,
    max_points  NUMERIC NOT NULL,
    sort_order  INTEGER NOT NULL DEFAULT 0,
    CONSTRAINT uq_rubric_criterion_name UNIQUE (template_id, name),
    FOREIGN KEY (template_id) REFERENCES rubric_templates (id) ON DELETE RESTRICT ON UPDATE CASCADE
);

CREATE TABLE rubric_indicators (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    criterion_id INTEGER NOT NULL,
    name         TEXT NOT NULL,
    description  TEXT NULL,
    max_points   NUMERIC NOT NULL,
    is_required  INTEGER NOT NULL DEFAULT 1,
    sort_order   INTEGER NOT NULL DEFAULT 0,
    CONSTRAINT uq_rubric_indicator_name UNIQUE (criterion_id, name),
    FOREIGN KEY (criterion_id) REFERENCES rubric_criteria (id) ON DELETE RESTRICT ON UPDATE CASCADE
);

CREATE TABLE research_applications (
    id                       INTEGER PRIMARY KEY AUTOINCREMENT,
    application_number       TEXT NOT NULL,          -- MWU-RPEE-APP-YYYY-NNNN
    call_id                  INTEGER NOT NULL,
    pi_user_id               INTEGER NOT NULL,
    title                    TEXT NOT NULL,
    research_type_id         INTEGER NULL,
    research_theme_id        INTEGER NULL,
    research_area            TEXT NULL,
    college_id               INTEGER NOT NULL,      -- routing source (§17)
    department_id            INTEGER NULL,
    duration_months          INTEGER NULL,
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
    budget_requested         NUMERIC NOT NULL DEFAULT 0,
    budget_justification     TEXT NULL,
    budget_approved_amount   NUMERIC NULL,   -- directorate-approved allocation (§28, Rule 13)
    budget_approved_by       INTEGER NULL,
    requires_ethics TEXT CHECK (requires_ethics IN ('yes','no','unsure')) NULL, -- §30
    current_stage_id         INTEGER NULL,
    workflow_id              INTEGER NULL,
    status_code              TEXT NOT NULL DEFAULT 'DRAFT', -- indexed mirror of stage code
    is_locked                INTEGER NOT NULL DEFAULT 0, -- submission locking (§ Phase 5)
    version                  INTEGER NOT NULL DEFAULT 1,
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
    created_by               INTEGER NULL,
    created_at               TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at               TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_app_number UNIQUE (application_number),
    FOREIGN KEY (call_id)         REFERENCES research_calls (id)   ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (pi_user_id)      REFERENCES users (id)            ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (research_type_id) REFERENCES research_types (id)  ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (research_theme_id) REFERENCES research_themes (id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (college_id)      REFERENCES colleges (id)         ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (department_id)   REFERENCES departments (id)      ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (current_stage_id) REFERENCES workflow_stages (id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (workflow_id)     REFERENCES workflows (id)        ON DELETE SET NULL ON UPDATE CASCADE
);

-- Proposal-stage collaborators; copied into project membership on activation.
CREATE TABLE application_team_members (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    application_id INTEGER NOT NULL,
    user_id        INTEGER NOT NULL,
    role TEXT CHECK (role IN ('PI','co_investigator')) NOT NULL DEFAULT 'co_investigator',
    status TEXT CHECK (status IN ('pending','accepted','declined')) NOT NULL DEFAULT 'pending',
    invited_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    responded_at   DATETIME NULL,
    CONSTRAINT uq_app_team_member UNIQUE (application_id, user_id),
    FOREIGN KEY (application_id) REFERENCES research_applications (id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE research_work_plan_items (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    application_id  INTEGER NOT NULL,
    activity        TEXT NOT NULL,
    start_date      DATE NULL,
    end_date        DATE NULL,
    responsible     TEXT NULL,
    expected_output TEXT NULL,
    sort_order      INTEGER NOT NULL DEFAULT 0,
    FOREIGN KEY (application_id) REFERENCES research_applications (id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE research_evaluators (
    id                         INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id                    INTEGER NOT NULL,
    expertise                  TEXT NULL,
    max_concurrent_evaluations INTEGER NOT NULL DEFAULT 5,
    availability_status TEXT CHECK (availability_status IN ('available','busy','unavailable')) NOT NULL DEFAULT 'available',
    notes                      TEXT NULL,
    created_at                 TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                 TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_reseval_user UNIQUE (user_id),
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE
);

CREATE TABLE evaluator_assignments (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    application_id    INTEGER NOT NULL,
    evaluator_user_id INTEGER NOT NULL,
    assignment_level  TEXT NOT NULL DEFAULT 'college',
    assigned_by       INTEGER NULL,
    assigned_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deadline          DATE NULL,
    status TEXT CHECK (status IN ('assigned','accepted','declined','conflict_declared','completed','cancelled')) NOT NULL DEFAULT 'assigned',
    accepted_at       DATETIME NULL,
    declined_at       DATETIME NULL,
    completed_at      DATETIME NULL,
    decline_reason    TEXT NULL,
    reminder_sent_at  DATETIME NULL,
    is_active         INTEGER NOT NULL DEFAULT 1,
    CONSTRAINT uq_evalassign_stage UNIQUE (application_id, evaluator_user_id, assignment_level),
    FOREIGN KEY (application_id)    REFERENCES research_applications (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (evaluator_user_id) REFERENCES users (id)                 ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (assigned_by)       REFERENCES users (id)                 ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE evaluator_conflicts (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    assignment_id   INTEGER NOT NULL,
    conflict_type TEXT CHECK (conflict_type IN ('self_declared','system_detected')) NOT NULL DEFAULT 'self_declared',
    description     TEXT NULL,
    detected_reason TEXT NULL, -- e.g. same department / co-author (§75)
    declared_by     INTEGER NULL,
    resolved TEXT CHECK (resolved IN ('open','resolved_reassigned','resolved_override')) NOT NULL DEFAULT 'open',
    resolved_by     INTEGER NULL,
    resolved_at     DATETIME NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (assignment_id) REFERENCES evaluator_assignments (id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (declared_by)   REFERENCES users (id)                ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE evaluations (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    application_id    INTEGER NOT NULL,
    assignment_id     INTEGER NULL,
    rubric_template_id INTEGER NULL,
    rubric_snapshot    TEXT NULL,
    evaluator_user_id INTEGER NOT NULL,
    evaluation_type TEXT CHECK (evaluation_type IN ('proposal','final')) NOT NULL DEFAULT 'proposal',
    evaluation_round  INTEGER NOT NULL DEFAULT 1, -- increments on re-evaluation (§25)
    amended_from_id   INTEGER NULL,   -- amendment chain (§22)
    recommendation TEXT CHECK (recommendation IN ('accept','accept_with_revision','major_revision','reject')) NULL,
    weighted_score    NUMERIC NULL,
    max_score         NUMERIC NOT NULL DEFAULT 100,
    strengths         TEXT NULL,
    weaknesses        TEXT NULL,
    recommendations   TEXT NULL,
    overall_comment   TEXT NULL,
    status TEXT CHECK (status IN ('draft','submitted','locked','amended')) NOT NULL DEFAULT 'draft',
    submitted_at      DATETIME NULL,
    locked_at         DATETIME NULL,
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_eval_assignment_round UNIQUE (assignment_id, evaluation_round),
    FOREIGN KEY (application_id)    REFERENCES research_applications (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (assignment_id)     REFERENCES evaluator_assignments (id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (rubric_template_id) REFERENCES rubric_templates (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (evaluator_user_id) REFERENCES users (id)                 ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (amended_from_id)   REFERENCES evaluations (id)           ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE evaluation_scores (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    evaluation_id INTEGER NOT NULL,
    criterion_id  INTEGER NOT NULL,
    score         NUMERIC NOT NULL,
    comment       TEXT NULL,
    CONSTRAINT uq_escore UNIQUE (evaluation_id, criterion_id),
    FOREIGN KEY (evaluation_id) REFERENCES evaluations (id)            ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (criterion_id)  REFERENCES research_call_criteria (id) ON DELETE RESTRICT ON UPDATE CASCADE
);

CREATE TABLE rubric_indicator_scores (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    evaluation_id  INTEGER NOT NULL,
    indicator_id   INTEGER NOT NULL,
    score          NUMERIC NOT NULL,
    comment        TEXT NULL,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_rubric_indicator_score UNIQUE (evaluation_id, indicator_id),
    FOREIGN KEY (evaluation_id) REFERENCES evaluations (id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (indicator_id) REFERENCES rubric_indicators (id) ON DELETE RESTRICT ON UPDATE CASCADE
);

CREATE TABLE evaluation_comments (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    evaluation_id INTEGER NOT NULL,
    visibility TEXT CHECK (visibility IN ('to_researcher','committee_only')) NOT NULL DEFAULT 'to_researcher',
    comment       TEXT NOT NULL,
    created_by    INTEGER NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (evaluation_id) REFERENCES evaluations (id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (created_by)    REFERENCES users (id)       ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE revision_requests (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    application_id  INTEGER NOT NULL,
    requested_by    INTEGER NULL,
    request_reason  TEXT NULL,
    deadline        DATE NULL,
    status TEXT CHECK (status IN ('open','responded','closed','cancelled')) NOT NULL DEFAULT 'open',
    requested_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    responded_at    DATETIME NULL,
    closed_at       DATETIME NULL,
    FOREIGN KEY (application_id) REFERENCES research_applications (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (requested_by)   REFERENCES users (id)                 ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE revision_responses (
    id                 INTEGER PRIMARY KEY AUTOINCREMENT,
    revision_request_id INTEGER NOT NULL,
    comment_id         INTEGER NULL, -- per-comment response (§25)
    response_text      TEXT NOT NULL,
    submitted_by       INTEGER NULL,
    submitted_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (revision_request_id) REFERENCES revision_requests (id)    ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (comment_id)          REFERENCES evaluation_comments (id)  ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (submitted_by)        REFERENCES users (id)                ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE college_decisions (
    id                 INTEGER PRIMARY KEY AUTOINCREMENT,
    application_id     INTEGER NOT NULL,
    decision_context TEXT CHECK (decision_context IN ('screening','committee')) NOT NULL DEFAULT 'screening',
    decision TEXT CHECK (decision IN ('complete','incomplete','return_for_correction','reject_ineligible','forward_to_evaluation','approve','approve_with_conditions','revision_required','reject','forward_to_university')) NOT NULL,
    committee_id       INTEGER NULL,
    meeting_date       DATE NULL,
    comments           TEXT NULL,
    decision_reference TEXT NULL,
    decided_by         INTEGER NULL,
    decided_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES research_applications (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (committee_id)   REFERENCES committees (id)            ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (decided_by)     REFERENCES users (id)                 ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE university_budget_recommendations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    application_id INTEGER NOT NULL,
    evaluator_id INTEGER NOT NULL,
    amount NUMERIC NOT NULL,
    justification TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES research_applications(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (evaluator_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE application_committee_assignments (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    application_id INTEGER NOT NULL,
    committee_id   INTEGER NOT NULL,
    assigned_by    INTEGER NULL,
    assigned_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    meeting_date   DATE NULL,
    notes          TEXT NULL,
    status TEXT CHECK (status IN ('active','completed','cancelled')) NOT NULL DEFAULT 'active',
    FOREIGN KEY (application_id) REFERENCES research_applications (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (committee_id)   REFERENCES committees (id)            ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (assigned_by)    REFERENCES users (id)                 ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE committee_decisions (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    assignment_id       INTEGER NOT NULL,
    application_id      INTEGER NOT NULL,
    committee_id        INTEGER NOT NULL,
    decision TEXT CHECK (decision IN ('approve','approve_with_conditions','revision_required','reject')) NOT NULL,
    comments            TEXT NULL,
    meeting_date        DATE NULL,
    decision_reference  TEXT NULL,
    recommended_amount  NUMERIC NULL,          -- committee funding recommendation (Rule 9)
    recommendation_note TEXT NULL,
    decided_by          INTEGER NULL,
    decided_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (assignment_id) REFERENCES application_committee_assignments (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (application_id) REFERENCES research_applications (id)           ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (committee_id)   REFERENCES committees (id)                       ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (decided_by)     REFERENCES users (id)                            ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE research_projects (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    project_number    TEXT NOT NULL,           -- MWU-RPEE-YYYY-NNNN (§35)
    application_id    INTEGER NOT NULL,
    title             TEXT NOT NULL,
    pi_user_id        INTEGER NOT NULL,
    college_id        INTEGER NOT NULL,
    department_id     INTEGER NULL,
    research_type_id  INTEGER NULL,
    research_theme_id INTEGER NULL,
    workflow_id       INTEGER NULL,
    current_stage_id  INTEGER NULL,
    status_code       TEXT NOT NULL DEFAULT 'APPROVED',
    duration_months   INTEGER NULL,
    planned_start     DATE NULL,
    planned_end       DATE NULL,
    actual_start      DATE NULL,
    actual_end        DATE NULL,
    approved_budget   NUMERIC NOT NULL DEFAULT 0,
    completion_note   TEXT NULL,
    created_by        INTEGER NULL,
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_proj_number UNIQUE (project_number),
    CONSTRAINT uq_proj_application UNIQUE (application_id),
    FOREIGN KEY (application_id)    REFERENCES research_applications (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (pi_user_id)        REFERENCES users (id)                 ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (college_id)        REFERENCES colleges (id)              ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (department_id)     REFERENCES departments (id)           ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (research_type_id)  REFERENCES research_types (id)        ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (research_theme_id) REFERENCES research_themes (id)       ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (workflow_id)       REFERENCES workflows (id)             ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (current_stage_id)  REFERENCES workflow_stages (id)       ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE research_project_members (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id  INTEGER NOT NULL,
    user_id     INTEGER NOT NULL,
    member_role TEXT CHECK (member_role IN ('principal_investigator','co_investigator','research_assistant','other')) NOT NULL DEFAULT 'co_investigator',
    role_other  TEXT NULL,
    allocation_pct NUMERIC NULL,
    joined_at   DATE NULL,
    left_at     DATE NULL,
    status TEXT CHECK (status IN ('active','removed','role_changed')) NOT NULL DEFAULT 'active',
    change_reason TEXT NULL,
    requested_by INTEGER NULL,
    approved_by  INTEGER NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES research_projects (id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (user_id)    REFERENCES users (id)            ON DELETE RESTRICT ON UPDATE CASCADE
);

CREATE TABLE research_budgets (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id       INTEGER NOT NULL,
    requested_amount NUMERIC NOT NULL DEFAULT 0,
    approved_amount  NUMERIC NOT NULL DEFAULT 0,
    allocated_amount NUMERIC NOT NULL DEFAULT 0,
    released_amount  NUMERIC NOT NULL DEFAULT 0,
    spent_amount     NUMERIC NOT NULL DEFAULT 0,
    currency         CHAR(3) NOT NULL DEFAULT 'ETB',
    status TEXT CHECK (status IN ('draft','submitted','under_review','approved','revision_required','rejected')) NOT NULL DEFAULT 'draft',
    approved_by      INTEGER NULL,
    approved_at      DATETIME NULL,
    created_by       INTEGER NULL,
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_budget_project UNIQUE (project_id),
    FOREIGN KEY (project_id)  REFERENCES research_projects (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users (id)             ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE research_budget_items (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    budget_id       INTEGER NOT NULL,
    item_category TEXT CHECK (item_category IN ('equipment','materials','data_collection','transportation','research_assistants','laboratory','training','other')) NOT NULL,
    description     TEXT NOT NULL,
    requested_amount NUMERIC NOT NULL DEFAULT 0,
    approved_amount NUMERIC NOT NULL DEFAULT 0,
    released_amount NUMERIC NOT NULL DEFAULT 0,
    spent_amount    NUMERIC NOT NULL DEFAULT 0,
    justification   TEXT NULL,
    sort_order      INTEGER NOT NULL DEFAULT 0,
    FOREIGN KEY (budget_id) REFERENCES research_budgets (id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE budget_revisions (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id    INTEGER NOT NULL,
    budget_item_id INTEGER NULL,
    revision_type TEXT CHECK (revision_type IN ('reallocation','additional_funding','reduction')) NOT NULL,
    old_amount    NUMERIC NOT NULL,
    new_amount    NUMERIC NOT NULL,
    reason        TEXT NOT NULL,
    status TEXT CHECK (status IN ('pending','approved','rejected','cancelled')) NOT NULL DEFAULT 'pending',
    requested_by  INTEGER NULL,
    requested_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    approved_by   INTEGER NULL,
    approved_at   DATETIME NULL,
    FOREIGN KEY (project_id)     REFERENCES research_projects (id)    ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (budget_item_id) REFERENCES research_budget_items (id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (requested_by)   REFERENCES users (id)                ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (approved_by)    REFERENCES users (id)                ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE funding_releases (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id       INTEGER NOT NULL,
    budget_id        INTEGER NOT NULL,
    amount           NUMERIC NOT NULL,
    release_reference TEXT NULL,
    note             TEXT NULL,
    released_by      INTEGER NULL,
    released_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id)  REFERENCES research_projects (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (budget_id)   REFERENCES research_budgets (id)  ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (released_by) REFERENCES users (id)             ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE research_agreements (
    id                 INTEGER PRIMARY KEY AUTOINCREMENT,
    agreement_number   TEXT NOT NULL,          -- MWU-RPEE-AGR-YYYY-NNNN
    application_id     INTEGER NOT NULL,
    project_id         INTEGER NULL,
    version            INTEGER NOT NULL DEFAULT 1,
    status TEXT CHECK (status IN ('draft','issued','signed','superseded','cancelled')) NOT NULL DEFAULT 'draft',
    title              TEXT NOT NULL,
    terms              TEXT NULL,
    agreed_amount      NUMERIC NULL,
    previous_version_id INTEGER NULL,
    prepared_by        INTEGER NULL,
    prepared_at        DATETIME NULL,
    issued_by          INTEGER NULL,
    issued_at          DATETIME NULL,
    signed_by          INTEGER NULL,
    signed_at          DATETIME NULL,
    signature_name     TEXT NULL,
    signature_note     TEXT NULL,
    created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_agr_number UNIQUE (agreement_number),
    FOREIGN KEY (application_id)      REFERENCES research_applications (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (project_id)          REFERENCES research_projects (id)    ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (prepared_by)         REFERENCES users (id)                ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (issued_by)           REFERENCES users (id)                ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (signed_by)           REFERENCES users (id)                ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (previous_version_id) REFERENCES research_agreements (id)  ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE research_milestones (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id          INTEGER NOT NULL,
    milestone_name      TEXT NOT NULL,
    description         TEXT NULL,
    due_date            DATE NULL,
    responsible_user_id INTEGER NULL,
    status TEXT CHECK (status IN ('pending','in_progress','completed','delayed','cancelled')) NOT NULL DEFAULT 'pending',
    progress_pct        NUMERIC NOT NULL DEFAULT 0,
    completion_date     DATE NULL,
    sort_order          INTEGER NOT NULL DEFAULT 0,
    FOREIGN KEY (project_id) REFERENCES research_projects (id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (responsible_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE progress_reports (
    id                     INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id             INTEGER NOT NULL,
    report_number          TEXT NOT NULL,
    reporting_period       TEXT NULL,
    period_start           DATE NULL,
    period_end             DATE NULL,
    progress_pct           NUMERIC NOT NULL DEFAULT 0,
    completed_activities   TEXT NULL,
    pending_activities     TEXT NULL,
    challenges             TEXT NULL,
    achievements           TEXT NULL,
    financial_progress_pct NUMERIC NULL,
    amount_spent_to_date   NUMERIC NULL,
    status TEXT CHECK (status IN ('draft','submitted','approved','revision_required','rejected')) NOT NULL DEFAULT 'draft',
    submitted_at           DATETIME NULL,
    reviewed_by            INTEGER NULL,
    reviewed_at            DATETIME NULL,
    review_comments        TEXT NULL,
    created_by             INTEGER NULL,
    created_at             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_preport_number UNIQUE (report_number),
    CONSTRAINT uq_progress_project_period UNIQUE (project_id, reporting_period),
    FOREIGN KEY (project_id) REFERENCES research_projects (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
);

-- Final completed-research-report evaluation lifecycle.
CREATE TABLE project_final_reports (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER NOT NULL,
    document_id INTEGER NOT NULL,
    version INTEGER NOT NULL,
    status TEXT CHECK (status IN ('submitted_to_college','college_evaluation','college_revision_required','college_passed','university_evaluation','university_revision_required','university_passed','approved','rejected')) NOT NULL DEFAULT 'submitted_to_college',
    submitted_by INTEGER NOT NULL,
    submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    college_average_score NUMERIC NULL,
    university_average_score NUMERIC NULL,
    director_decision TEXT NULL,
    director_comments TEXT NULL,
    director_user_id INTEGER NULL,
    director_decided_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_final_report_version UNIQUE (project_id, version),
    FOREIGN KEY (project_id) REFERENCES research_projects (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (submitted_by) REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (director_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE project_evaluator_assignments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    final_report_id INTEGER NOT NULL,
    evaluator_user_id INTEGER NOT NULL,
    assignment_level TEXT CHECK (assignment_level IN ('college','university')) NOT NULL,
    assigned_by INTEGER NOT NULL,
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deadline DATETIME NULL,
    status TEXT CHECK (status IN ('assigned','accepted','declined','conflict_declared','completed','cancelled')) NOT NULL DEFAULT 'assigned',
    response_reason TEXT NULL,
    responded_at DATETIME NULL,
    is_active INTEGER NOT NULL DEFAULT 1,
    CONSTRAINT uq_project_eval_assignment UNIQUE (final_report_id, evaluator_user_id, assignment_level),
    FOREIGN KEY (final_report_id) REFERENCES project_final_reports (id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (evaluator_user_id) REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE
);

CREATE TABLE project_evaluations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    final_report_id INTEGER NOT NULL,
    assignment_id INTEGER NOT NULL,
    evaluator_user_id INTEGER NOT NULL,
    rubric_template_id INTEGER NOT NULL,
    rubric_snapshot TEXT NOT NULL,
    evaluation_round INTEGER NOT NULL DEFAULT 1,
    weighted_score NUMERIC NOT NULL DEFAULT 0,
    max_score NUMERIC NOT NULL DEFAULT 100,
    recommendation TEXT CHECK (recommendation IN ('accept','accept_with_revision','major_revision','reject')) NULL,
    strengths TEXT NULL,
    weaknesses TEXT NULL,
    recommendations TEXT NULL,
    overall_comment TEXT NULL,
    status TEXT CHECK (status IN ('draft','submitted','returned')) NOT NULL DEFAULT 'draft',
    submitted_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_project_eval_assignment_round UNIQUE (assignment_id, evaluation_round),
    FOREIGN KEY (final_report_id) REFERENCES project_final_reports (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (assignment_id) REFERENCES project_evaluator_assignments (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (evaluator_user_id) REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (rubric_template_id) REFERENCES rubric_templates (id) ON DELETE RESTRICT ON UPDATE CASCADE
);

CREATE TABLE project_evaluation_indicator_scores (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_evaluation_id INTEGER NOT NULL,
    indicator_id INTEGER NOT NULL,
    score NUMERIC NOT NULL,
    comment TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_project_eval_indicator_score UNIQUE (project_evaluation_id, indicator_id),
    FOREIGN KEY (project_evaluation_id) REFERENCES project_evaluations (id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (indicator_id) REFERENCES rubric_indicators (id) ON DELETE RESTRICT ON UPDATE CASCADE
);

CREATE TABLE project_final_report_decisions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    final_report_id INTEGER NOT NULL,
    stage TEXT CHECK (stage IN ('college','university','directorate')) NOT NULL,
    decision TEXT CHECK (decision IN ('forward_to_university','revision_required','reject','approve')) NOT NULL,
    comments TEXT NULL,
    decided_by INTEGER NOT NULL,
    decided_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (final_report_id) REFERENCES project_final_reports (id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (decided_by) REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE
);

CREATE TABLE research_extension_requests (
    id                 INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id         INTEGER NOT NULL,
    original_deadline  DATE NOT NULL,
    requested_deadline DATE NOT NULL,
    approved_deadline  DATE NULL,
    reason             TEXT NOT NULL,
    status TEXT CHECK (status IN ('pending','approved','rejected','cancelled')) NOT NULL DEFAULT 'pending',
    requested_by       INTEGER NULL,
    requested_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    decided_by         INTEGER NULL,
    decided_at         DATETIME NULL,
    decision_comments  TEXT NULL,
    FOREIGN KEY (project_id) REFERENCES research_projects (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (requested_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (decided_by)   REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE project_actions (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id      INTEGER NOT NULL,
    action_type TEXT CHECK (action_type IN ('suspend','resume','cancel','terminate','withdraw')) NOT NULL,
    reason          TEXT NOT NULL,
    comments        TEXT NULL,
    previous_status TEXT NULL,
    new_status      TEXT NULL,
    approved_by     INTEGER NULL,
    action_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id)  REFERENCES research_projects (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users (id)             ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE presentations (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id    INTEGER NOT NULL,
    scheduled_at  DATETIME NOT NULL,
    venue         TEXT NULL,
    status TEXT CHECK (status IN ('scheduled','presented','cancelled','rescheduled','postponed')) NOT NULL DEFAULT 'scheduled',
    presented_at  DATETIME NULL,
    scheduled_by  INTEGER NULL,
    notes         TEXT NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id)   REFERENCES research_projects (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (scheduled_by) REFERENCES users (id)             ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE presentation_panels (
    id                   INTEGER PRIMARY KEY AUTOINCREMENT,
    presentation_id      INTEGER NOT NULL,
    user_id              INTEGER NULL, -- NULL for authorized external experts (§48)
    member_name          TEXT NOT NULL,
    member_role TEXT CHECK (member_role IN ('chair','member','external_expert','secretary')) NOT NULL DEFAULT 'member',
    affiliation          TEXT NULL,
    appointment_reference TEXT NULL,
    created_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (presentation_id) REFERENCES presentations (id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (user_id)         REFERENCES users (id)          ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE presentation_evaluations (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    presentation_id  INTEGER NOT NULL,
    evaluator_user_id INTEGER NOT NULL,
    recommendation TEXT CHECK (recommendation IN ('accept','accept_with_revision','major_revision','reject')) NULL,
    overall_comment  TEXT NULL,
    status TEXT CHECK (status IN ('draft','submitted')) NOT NULL DEFAULT 'draft',
    submitted_at     DATETIME NULL,
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_preseval UNIQUE (presentation_id, evaluator_user_id),
    FOREIGN KEY (presentation_id)   REFERENCES presentations (id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (evaluator_user_id) REFERENCES users (id)         ON DELETE RESTRICT ON UPDATE CASCADE
);

CREATE TABLE presentation_scores (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    evaluation_id INTEGER NOT NULL,
    criterion_id  INTEGER NOT NULL,
    score         NUMERIC NOT NULL,
    CONSTRAINT uq_pscore UNIQUE (evaluation_id, criterion_id),
    FOREIGN KEY (evaluation_id) REFERENCES presentation_evaluations (id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (criterion_id)  REFERENCES evaluation_criteria (id)     ON DELETE RESTRICT ON UPDATE CASCADE
);

CREATE TABLE research_outputs (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id   INTEGER NOT NULL,
    output_type TEXT CHECK (output_type IN ('final_report','publication','conference_paper','book_chapter','policy_brief','dataset','training_material','patent','technology','community_output','other')) NOT NULL,
    title        TEXT NOT NULL,
    description  TEXT NULL,
    status TEXT CHECK (status IN ('draft','submitted','verified','accepted','published','archived')) NOT NULL DEFAULT 'draft',
    recorded_by  INTEGER NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES research_projects (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE publications (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    output_id           INTEGER NULL,
    project_id          INTEGER NULL,
    title               TEXT NOT NULL,
    journal_name        TEXT NULL,
    publisher           TEXT NULL,
    issn                TEXT NULL,
    doi                 TEXT NULL,
    volume              TEXT NULL,
    issue               TEXT NULL,
    pages               TEXT NULL,
    publication_year    INTEGER NULL,
    publication_type TEXT CHECK (publication_type IN ('journal_article','conference_paper','book_chapter','book','other')) NOT NULL DEFAULT 'journal_article',
    url                 TEXT NULL,
    status TEXT CHECK (status IN ('draft','submitted','under_review','revision_required','accepted','published','rejected','withdrawn')) NOT NULL DEFAULT 'draft',
    submission_date     DATE NULL,
    acceptance_date     DATE NULL,
    published_date      DATE NULL,
    created_by          INTEGER NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (output_id)  REFERENCES research_outputs (id)  ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (project_id) REFERENCES research_projects (id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users (id)             ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE publication_authors (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    publication_id   INTEGER NOT NULL,
    user_id          INTEGER NULL,
    author_name      TEXT NOT NULL,
    affiliation      TEXT NULL,
    author_order     INTEGER NOT NULL DEFAULT 1,
    is_corresponding INTEGER NOT NULL DEFAULT 0,
    FOREIGN KEY (publication_id) REFERENCES publications (id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (user_id)        REFERENCES users (id)        ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE ethics_applications (
    id                       INTEGER PRIMARY KEY AUTOINCREMENT,
    application_number       TEXT NOT NULL,        -- MWU-RPEE-ETH-YYYY-NNNN
    research_application_id  INTEGER NOT NULL,
    researcher_user_id       INTEGER NOT NULL,
    participant_count        INTEGER NULL,
    participant_description  TEXT NULL,
    research_type_summary    TEXT NULL,
    risk_level TEXT CHECK (risk_level IN ('minimal','low','moderate','high')) NULL,
    consent_process          TEXT NULL,
    confidentiality_measures TEXT NULL,
    data_collection_summary  TEXT NULL,
    review_track TEXT CHECK (review_track IN ('screening','full')) NOT NULL DEFAULT 'full',
    screening_note           TEXT NULL,
    current_stage_id         INTEGER NULL,
    workflow_id              INTEGER NULL,
    status_code              TEXT NOT NULL DEFAULT 'DRAFT',
    status TEXT CHECK (status IN ('draft','submitted','under_screening','screened_no_review','under_review','revision_required','approved','approved_with_conditions','rejected','exempt','expired','withdrawn')) NOT NULL DEFAULT 'draft',
    submitted_at             DATETIME NULL,
    decided_at               DATETIME NULL,
    approved_until           DATE NULL,                   -- ethics expiry (§33)
    created_by               INTEGER NULL,
    created_at               TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at               TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_eth_app_number UNIQUE (application_number),
    CONSTRAINT uq_eth_research_app UNIQUE (research_application_id),
    FOREIGN KEY (research_application_id) REFERENCES research_applications (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (researcher_user_id)      REFERENCES users (id)                ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (workflow_id)             REFERENCES workflows (id)            ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (current_stage_id)        REFERENCES workflow_stages (id)      ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE ethics_reviews (
    id                   INTEGER PRIMARY KEY AUTOINCREMENT,
    ethics_application_id INTEGER NOT NULL,
    reviewer_user_id     INTEGER NOT NULL,
    assigned_by          INTEGER NULL,
    assigned_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    review_deadline      DATE NULL,
    status TEXT CHECK (status IN ('assigned','accepted','declined','completed','conflict_declared')) NOT NULL DEFAULT 'assigned',
    comments             TEXT NULL,
    recommendation TEXT CHECK (recommendation IN ('approve','approve_with_conditions','revision','reject','exempt')) NULL,
    submitted_at         DATETIME NULL,
    CONSTRAINT uq_ethrev UNIQUE (ethics_application_id, reviewer_user_id),
    FOREIGN KEY (ethics_application_id) REFERENCES ethics_applications (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (reviewer_user_id)      REFERENCES users (id)               ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (assigned_by)           REFERENCES users (id)               ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE ethics_decisions (
    id                   INTEGER PRIMARY KEY AUTOINCREMENT,
    ethics_application_id INTEGER NOT NULL,
    decision TEXT CHECK (decision IN ('approved','approved_with_conditions','revision_required','rejected','exempt')) NOT NULL,
    conditions           TEXT NULL,
    comments             TEXT NULL,
    decision_reference   TEXT NULL,
    decided_by           INTEGER NULL,
    decided_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at           DATE NULL,
    created_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ethics_application_id) REFERENCES ethics_applications (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (decided_by)            REFERENCES users (id)               ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE ethics_amendments (
    id                    INTEGER PRIMARY KEY AUTOINCREMENT,
    ethics_application_id INTEGER NOT NULL,
    amendment_number      TEXT NOT NULL,
    amendment_type TEXT CHECK (amendment_type IN ('methodology','participants','location','data_collection','instruments','other')) NOT NULL,
    description           TEXT NOT NULL,
    status TEXT CHECK (status IN ('submitted','under_review','approved','revision_required','rejected','withdrawn')) NOT NULL DEFAULT 'submitted',
    requested_by          INTEGER NULL,
    requested_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    decided_by            INTEGER NULL,
    decided_at            DATETIME NULL,
    decision_comments     TEXT NULL,
    CONSTRAINT uq_ethamend_number UNIQUE (amendment_number),
    FOREIGN KEY (ethics_application_id) REFERENCES ethics_applications (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (requested_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (decided_by)   REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE ethics_certificates (
    id                    INTEGER PRIMARY KEY AUTOINCREMENT,
    ethics_application_id INTEGER NOT NULL,
    certificate_number    TEXT NOT NULL,
    issued_date           DATE NULL,
    expires_at            DATE NULL,
    issued_by             INTEGER NULL,
    notes                 TEXT NULL,
    CONSTRAINT uq_ethcert_number UNIQUE (certificate_number),
    FOREIGN KEY (ethics_application_id) REFERENCES ethics_applications (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (issued_by)             REFERENCES users (id)               ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE extension_calls (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    call_number         TEXT NOT NULL,
    reference_number    TEXT NULL,
    title               TEXT NOT NULL,
    description         TEXT NULL,
    focus_areas         TEXT NULL,
    opens_at            DATETIME NULL,
    closes_at           DATETIME NULL,
    max_budget          NUMERIC NULL,
    max_duration_months INTEGER NULL,
    status TEXT CHECK (status IN ('draft','published','open','closed','under_evaluation','completed','archived')) NOT NULL DEFAULT 'draft',
    published_at        DATETIME NULL,
    created_by          INTEGER NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_extcall_number UNIQUE (call_number),
    FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE extension_applications (
    id                    INTEGER PRIMARY KEY AUTOINCREMENT,
    application_number    TEXT NOT NULL,
    call_id               INTEGER NULL,
    pi_user_id            INTEGER NOT NULL,
    title                 TEXT NOT NULL,
    community             TEXT NULL,
    location              TEXT NULL,
    partner_organization  TEXT NULL,
    target_beneficiaries  TEXT NULL,
    objectives            TEXT NULL,
    planned_activities    TEXT NULL,
    duration_months       INTEGER NULL,
    start_date            DATE NULL,
    end_date              DATE NULL,
    budget_requested      NUMERIC NULL,
    budget_justification  TEXT NULL,
    expected_outputs      TEXT NULL,
    expected_outcomes     TEXT NULL,
    current_stage_id      INTEGER NULL,
    workflow_id           INTEGER NULL,
    status TEXT CHECK (status IN ('draft','submitted','under_department_review','under_college_review','under_evaluation','approved','rejected','returned','withdrawn')) NOT NULL DEFAULT 'draft',
    submitted_at          DATETIME NULL,
    approved_at           DATETIME NULL,
    created_by            INTEGER NULL,
    created_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_extapp_number UNIQUE (application_number),
    FOREIGN KEY (call_id)        REFERENCES extension_calls (id)  ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (pi_user_id)     REFERENCES users (id)            ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (workflow_id)    REFERENCES workflows (id)        ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (current_stage_id) REFERENCES workflow_stages (id) ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE extension_projects (
    id                        INTEGER PRIMARY KEY AUTOINCREMENT,
    project_number            TEXT NOT NULL,
    application_id            INTEGER NOT NULL,
    title                     TEXT NOT NULL,
    pi_user_id                INTEGER NOT NULL,
    community                 TEXT NULL,
    location                  TEXT NULL,
    status TEXT CHECK (status IN ('active','suspended','completed','terminated','cancelled')) NOT NULL DEFAULT 'active',
    planned_start             DATE NULL,
    planned_end               DATE NULL,
    actual_end                DATE NULL,
    approved_budget           NUMERIC NULL,
    linked_research_project_id INTEGER NULL,
    created_at                TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_extproj_number UNIQUE (project_number),
    CONSTRAINT uq_extproj_application UNIQUE (application_id),
    FOREIGN KEY (application_id)             REFERENCES extension_applications (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (pi_user_id)                 REFERENCES users (id)                  ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (linked_research_project_id) REFERENCES research_projects (id)      ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE extension_activities (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id        INTEGER NOT NULL,
    activity_name     TEXT NOT NULL,
    description       TEXT NULL,
    planned_date      DATE NULL,
    actual_date       DATE NULL,
    status TEXT CHECK (status IN ('planned','in_progress','completed','cancelled')) NOT NULL DEFAULT 'planned',
    beneficiary_count INTEGER NULL,
    remarks           TEXT NULL,
    sort_order        INTEGER NOT NULL DEFAULT 0,
    FOREIGN KEY (project_id) REFERENCES extension_projects (id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE extension_partners (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id      INTEGER NOT NULL,
    partner_name    TEXT NOT NULL,
    partner_type TEXT CHECK (partner_type IN ('government','community_organization','ngo','industry','school','health_institution','other')) NOT NULL DEFAULT 'other',
    contact_person  TEXT NULL,
    contact_phone   TEXT NULL,
    contact_email   TEXT NULL,
    partnership_role TEXT NULL,
    mou_reference   TEXT NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES extension_projects (id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE extension_impact_reports (
    id                   INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id           INTEGER NOT NULL,
    report_date          DATE NULL,
    participants_count   INTEGER NULL,
    activities_completed INTEGER NULL,
    outputs_achieved     TEXT NULL,
    outcomes             TEXT NULL,
    beneficiaries_count  INTEGER NULL,
    impact_indicators    TEXT NULL,
    community_feedback   TEXT NULL,
    status TEXT CHECK (status IN ('draft','submitted','approved')) NOT NULL DEFAULT 'draft',
    submitted_by         INTEGER NULL,
    created_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES extension_projects (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (submitted_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE research_datasets (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id          INTEGER NOT NULL,
    title               TEXT NOT NULL,
    description         TEXT NULL,
    data_type TEXT CHECK (data_type IN ('csv','excel','spss','stata','r','zip','images','audio','video','other')) NOT NULL DEFAULT 'other',
    year                INTEGER NULL,
    keywords            TEXT NULL,
    methodology_summary TEXT NULL,
    visibility TEXT CHECK (visibility IN ('public','university_only','college_only','restricted','confidential','embargoed','de_identified')) NOT NULL DEFAULT 'restricted',
    embargo_until       DATE NULL,
    access_instructions TEXT NULL,
    status TEXT CHECK (status IN ('metadata_only','available','withdrawn')) NOT NULL DEFAULT 'metadata_only',
    created_by          INTEGER NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES research_projects (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE dataset_access_requests (
    id                   INTEGER PRIMARY KEY AUTOINCREMENT,
    dataset_id           INTEGER NOT NULL,
    requester_user_id    INTEGER NOT NULL,
    request_reason       TEXT NOT NULL,
    INTEGERended_use         TEXT NULL,
    status TEXT CHECK (status IN ('pending','approved','rejected','expired')) NOT NULL DEFAULT 'pending',
    requested_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    decided_by           INTEGER NULL,
    decided_at           DATETIME NULL,
    decision_note        TEXT NULL,
    access_granted_until DATE NULL,
    FOREIGN KEY (dataset_id)        REFERENCES research_datasets (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (requester_user_id) REFERENCES users (id)             ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (decided_by)        REFERENCES users (id)             ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE repository_records (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    repository_id_code TEXT NOT NULL,          -- MWU-RPEE-REP-YYYY-NNNN
    project_id        INTEGER NOT NULL,
    title             TEXT NOT NULL,
    abstract          TEXT NULL,
    keywords          TEXT NULL,
    year              INTEGER NULL,
    research_type_id  INTEGER NULL,
    research_theme_id INTEGER NULL,
    college_id        INTEGER NULL,
    department_id     INTEGER NULL,
    citation_text     TEXT NULL,
    permanent_url     TEXT NULL,
    visibility TEXT CHECK (visibility IN ('public','university_only','college_only','restricted','confidential','embargoed')) NOT NULL DEFAULT 'university_only',
    status TEXT CHECK (status IN ('draft','published','embargoed','archived','withdrawn')) NOT NULL DEFAULT 'draft',
    published_at      DATETIME NULL,
    archived_at       DATETIME NULL,
    created_by        INTEGER NULL,
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_repo_code UNIQUE (repository_id_code),
    CONSTRAINT uq_repo_project UNIQUE (project_id),
    FOREIGN KEY (project_id)        REFERENCES research_projects (id)  ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (research_type_id)  REFERENCES research_types (id)     ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (research_theme_id) REFERENCES research_themes (id)    ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (college_id)        REFERENCES colleges (id)           ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (department_id)     REFERENCES departments (id)        ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE repository_embargoes (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    repository_record_id INTEGER NOT NULL,
    start_date          DATE NULL,
    end_date            DATE NOT NULL,
    reason              TEXT NULL,
    approved_by         INTEGER NULL,
    visibility_before TEXT CHECK (visibility_before IN ('public','university_only','college_only','restricted','confidential','embargoed')) NOT NULL DEFAULT 'confidential',
    visibility_after TEXT CHECK (visibility_after IN ('public','university_only','college_only','restricted','confidential','embargoed')) NOT NULL DEFAULT 'public',
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (repository_record_id) REFERENCES repository_records (id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (approved_by)          REFERENCES users (id)              ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE documents (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    document_type_id INTEGER NOT NULL,
    entity_type      TEXT NOT NULL,
    entity_id        INTEGER NOT NULL,
    title            TEXT NOT NULL,
    version          INTEGER NOT NULL DEFAULT 1,
    root_document_id INTEGER NULL,   -- head of the version chain (§26)
    superseded_by_id INTEGER NULL,
    file_path        TEXT NOT NULL,  -- relative to storage/, never public (§64)
    original_filename TEXT NOT NULL, -- original name kept as metadata only (§90)
    file_size        INTEGER NOT NULL DEFAULT 0,
    mime_type        TEXT NOT NULL,
    file_hash        CHAR(64) NULL,
    visibility TEXT CHECK (visibility IN ('private','entity_team','college','university','public')) NOT NULL DEFAULT 'entity_team',
    status TEXT CHECK (status IN ('active','superseded','archived','deleted')) NOT NULL DEFAULT 'active',
    uploaded_by      INTEGER NULL,
    uploaded_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deletion_reason  TEXT NULL,
    deleted_by       INTEGER NULL,
    FOREIGN KEY (document_type_id) REFERENCES document_types (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (uploaded_by)      REFERENCES users (id)          ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (deleted_by)       REFERENCES users (id)          ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (root_document_id) REFERENCES documents (id)      ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (superseded_by_id) REFERENCES documents (id)      ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE document_access_log (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    document_id INTEGER NOT NULL,
    user_id     INTEGER NULL,
    access_type TEXT CHECK (access_type IN ('view','download','upload','delete','archive','failed_access')) NOT NULL,
    ip_address  TEXT NULL,
    details     TEXT NULL,
    accessed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (user_id)     REFERENCES users (id)    ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE research_integrity_cases (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    case_number         TEXT NOT NULL,
    case_type TEXT CHECK (case_type IN ('plagiarism','fabrication','falsification','authorship_dispute','data_integrity','ethics_concern','other')) NOT NULL,
    subject_user_id     INTEGER NULL,
    related_entity_type TEXT NULL,
    related_entity_id   INTEGER NULL,
    description         TEXT NOT NULL,
    status TEXT CHECK (status IN ('reported','preliminary_review','investigation','decision','action_taken','closed','dismissed')) NOT NULL DEFAULT 'reported',
    assigned_committee_id INTEGER NULL,
    is_confidential     INTEGER NOT NULL DEFAULT 1,
    opened_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    closed_at           DATETIME NULL,
    outcome             TEXT NULL,
    created_by          INTEGER NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_integrity_number UNIQUE (case_number),
    FOREIGN KEY (subject_user_id)      REFERENCES users (id)      ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (assigned_committee_id) REFERENCES committees (id) ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE appeals (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    appeal_number    TEXT NOT NULL,
    appealable_type  TEXT NOT NULL,
    appealable_id    INTEGER NOT NULL,
    appellant_user_id INTEGER NOT NULL,
    reason           TEXT NOT NULL,
    status TEXT CHECK (status IN ('submitted','under_review','upheld','rejected','withdrawn')) NOT NULL DEFAULT 'submitted',
    submitted_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_by      INTEGER NULL,
    reviewed_at      DATETIME NULL,
    decision_comments TEXT NULL,
    CONSTRAINT uq_appeal_number UNIQUE (appeal_number),
    FOREIGN KEY (appellant_user_id) REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (reviewed_by)       REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE notifications (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id          INTEGER NOT NULL,
    notification_type TEXT NOT NULL,
    title            TEXT NOT NULL,
    body             TEXT NULL,
    link_url         TEXT NULL,
    entity_type      TEXT NULL,
    entity_id        INTEGER NULL,
    priority TEXT CHECK (priority IN ('low','normal','high','urgent')) NOT NULL DEFAULT 'normal',
    read_at          DATETIME NULL,
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE audit_logs (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id     INTEGER NULL,
    action      TEXT NOT NULL,
    entity_type TEXT NULL,
    entity_id   INTEGER NULL,
    old_values  TEXT NULL,
    new_values  TEXT NULL,
    ip_address  TEXT NULL,
    user_agent  TEXT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE system_settings (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    setting_key   TEXT NOT NULL,
    setting_value TEXT NULL,
    setting_group TEXT NOT NULL DEFAULT 'general',
    value_type    TEXT NOT NULL DEFAULT 'string',
    description   TEXT NULL,
    updated_by    INTEGER NULL,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_settings_key UNIQUE (setting_key),
    FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE number_sequences (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    seq_key    TEXT NOT NULL,
    seq_year   INTEGER NOT NULL,
    next_value INTEGER NOT NULL DEFAULT 1,
    CONSTRAINT uq_seq UNIQUE (seq_key, seq_year)
);


