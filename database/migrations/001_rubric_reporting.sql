-- Phase 2 Migration: Rubric Engine & Monthly Reporting

-- 1. Rubric Engine
CREATE TABLE rubric_templates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL, -- e.g., 'College Proposal Evaluation'
    version INTEGER NOT NULL DEFAULT 1,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE rubric_criteria (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    template_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    max_points NUMERIC NOT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    FOREIGN KEY (template_id) REFERENCES rubric_templates(id) ON DELETE CASCADE
);

CREATE TABLE rubric_indicators (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    criterion_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    max_points NUMERIC NOT NULL,
    description TEXT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    FOREIGN KEY (criterion_id) REFERENCES rubric_criteria(id) ON DELETE CASCADE
);

-- 2. Monthly Reporting
CREATE TABLE monthly_report_schedules (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    institutional_timezone TEXT NOT NULL DEFAULT 'Africa/Addis_Ababa',
    deadline_day_of_month INTEGER NOT NULL DEFAULT 5, -- e.g., 5th of next month
    is_active INTEGER NOT NULL DEFAULT 1,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE monthly_reports (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER NOT NULL,
    reporting_month INTEGER NOT NULL,
    reporting_year INTEGER NOT NULL,
    status TEXT CHECK (status IN ('due', 'submitted', 'reviewed', 'overdue')) NOT NULL DEFAULT 'due',
    submitted_at DATETIME NULL,
    reviewed_at DATETIME NULL,
    content_data TEXT NULL, -- Storing JSON/blob of report fields
    FOREIGN KEY (project_id) REFERENCES research_projects(id) ON DELETE CASCADE
);
