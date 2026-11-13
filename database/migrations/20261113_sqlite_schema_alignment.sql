-- MWU REEP SQLite Schema Alignment Migration
-- Aligns SQLite schema with finalized institutional requirements

CREATE TABLE IF NOT EXISTS rubric_templates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    rubric_type TEXT NOT NULL,
    name TEXT NOT NULL,
    version INTEGER NOT NULL DEFAULT 1,
    is_active INTEGER NOT NULL DEFAULT 0,
    created_by INTEGER NULL,
    approved_by INTEGER NULL,
    approved_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS rubric_criteria (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    template_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    description TEXT NULL,
    instructions TEXT NULL,
    max_points NUMERIC NOT NULL DEFAULT 0,
    weight_pct NUMERIC NOT NULL DEFAULT 0,
    sort_order INTEGER NOT NULL DEFAULT 0,
    FOREIGN KEY (template_id) REFERENCES rubric_templates(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS rubric_indicators (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    criterion_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    description TEXT NULL,
    max_points NUMERIC NOT NULL DEFAULT 0,
    is_required INTEGER NOT NULL DEFAULT 1,
    sort_order INTEGER NOT NULL DEFAULT 0,
    FOREIGN KEY (criterion_id) REFERENCES rubric_criteria(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS rubric_indicator_scores (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    evaluation_id INTEGER NOT NULL,
    indicator_id INTEGER NOT NULL,
    score NUMERIC NOT NULL,
    comment TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (evaluation_id) REFERENCES evaluations(id) ON DELETE CASCADE,
    FOREIGN KEY (indicator_id) REFERENCES rubric_indicators(id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS monthly_report_schedules (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    institutional_timezone TEXT NOT NULL DEFAULT 'Africa/Addis_Ababa',
    deadline_day_of_month INTEGER NOT NULL DEFAULT 5,
    is_active INTEGER NOT NULL DEFAULT 1,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS monthly_reports (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER NOT NULL,
    reporting_month INTEGER NOT NULL,
    reporting_year INTEGER NOT NULL,
    status TEXT CHECK (status IN ('due', 'submitted', 'reviewed', 'overdue')) NOT NULL DEFAULT 'due',
    submitted_at DATETIME NULL,
    reviewed_at DATETIME NULL,
    content_data TEXT NULL,
    FOREIGN KEY (project_id) REFERENCES research_projects(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS research_call_themes (
    call_id INTEGER NOT NULL,
    theme_id INTEGER NOT NULL,
    PRIMARY KEY (call_id, theme_id),
    FOREIGN KEY (call_id) REFERENCES research_calls(id) ON DELETE CASCADE,
    FOREIGN KEY (theme_id) REFERENCES research_themes(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS committee_meetings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    committee_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    meeting_date DATETIME NOT NULL,
    venue_or_link TEXT NULL,
    agenda TEXT NULL,
    minutes TEXT NULL,
    status TEXT CHECK (status IN ('scheduled', 'in_progress', 'completed', 'cancelled')) NOT NULL DEFAULT 'scheduled',
    created_by INTEGER NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (committee_id) REFERENCES committees(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS committee_meeting_attendance (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    meeting_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    status TEXT CHECK (status IN ('invited', 'present', 'absent', 'excused')) NOT NULL DEFAULT 'invited',
    notes TEXT NULL,
    FOREIGN KEY (meeting_id) REFERENCES committee_meetings(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
