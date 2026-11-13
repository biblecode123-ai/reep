CREATE TABLE research_appeals (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    application_id INTEGER NOT NULL,
    appeal_type TEXT CHECK(appeal_type IN ('budget', 'rejection')),
    grounds TEXT NOT NULL,
    status TEXT DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    decision_record_id INTEGER,
    FOREIGN KEY(application_id) REFERENCES research_applications(id)
);
