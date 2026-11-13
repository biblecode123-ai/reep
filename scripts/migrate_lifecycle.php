<?php
declare(strict_types=1);

/**
 * Lifecycle migration — university research committee, research agreement,
 * application budget allocation, revision-loop support.
 *
 * Idempotent: safe to re-run. Applies to an EXISTING database (fresh installs
 * get everything from database/schema.sql + database/seed.sql).
 *
 * Run: php scripts/migrate_lifecycle.php
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Database;

$sqlite = Database::isSqlite();
$done = [];
$log = static function (string $msg) use (&$done): void {
    $done[] = $msg;
    echo '  + ' . $msg . "\n";
};

$tableExists = static function (string $table) use ($sqlite): bool {
    if ($sqlite) {
        return Database::one("SELECT name FROM sqlite_master WHERE type='table' AND name = ?", [$table]) !== null;
    }
    return Database::one('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$table]) !== null;
};

$columnExists = static function (string $table, string $column) use ($sqlite): bool {
    if ($sqlite) {
        foreach (Database::all("PRAGMA table_info($table)") as $c) {
            if ($c['name'] === $column) {
                return true;
            }
        }
        return false;
    }
    return Database::one('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?', [$table, $column]) !== null;
};

$exec = static function (string $sql, string $label) use ($log): void {
    Database::run($sql);
    $log($label);
};

echo "== MWU RPEE lifecycle migration ==\n";
echo 'driver: ' . Database::driver() . "\n";

// ---------------------------------------------------------------------------
// 1. Tables
// ---------------------------------------------------------------------------
$pk = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGINT UNSIGNED NOT NULL AUTOINCREMENT';
$pkLine = $sqlite ? '' : "        PRIMARY KEY (id),\n";
$fkType = $sqlite ? 'INTEGER' : 'BIGINT UNSIGNED';
$ts = $sqlite ? 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP' : 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP';
$enumStatus = $sqlite
    ? "TEXT CHECK (status IN ('active','completed','cancelled'))"
    : "ENUM('active','completed','cancelled')";
$enumDecision = $sqlite
    ? "TEXT CHECK (decision IN ('approve','approve_with_conditions','revision_required','reject'))"
    : "ENUM('approve','approve_with_conditions','revision_required','reject')";
$enumAgreement = $sqlite
    ? "TEXT CHECK (status IN ('draft','issued','signed','superseded','cancelled'))"
    : "ENUM('draft','issued','signed','superseded','cancelled')";
$decimal = $sqlite ? 'NUMERIC' : 'DECIMAL(12,2)';

if (!$tableExists('application_committee_assignments')) {
    $exec("CREATE TABLE application_committee_assignments (
        id $pk,
        application_id $fkType NOT NULL,
        committee_id $fkType NOT NULL,
        assigned_by $fkType NULL,
        assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        meeting_date DATE NULL,
        notes TEXT NULL,
        status $enumStatus NOT NULL DEFAULT 'active',
$pkLine        FOREIGN KEY (application_id) REFERENCES research_applications (id) ON DELETE RESTRICT ON UPDATE CASCADE,
        FOREIGN KEY (committee_id) REFERENCES committees (id) ON DELETE RESTRICT ON UPDATE CASCADE,
        FOREIGN KEY (assigned_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
    )", 'table application_committee_assignments');
}

if (!$tableExists('committee_decisions')) {
    $exec("CREATE TABLE committee_decisions (
        id $pk,
        assignment_id $fkType NOT NULL,
        application_id $fkType NOT NULL,
        committee_id $fkType NOT NULL,
        decision $enumDecision NOT NULL,
        comments TEXT NULL,
        meeting_date DATE NULL,
        decision_reference VARCHAR(100) NULL,
        recommended_amount $decimal NULL,
        recommendation_note TEXT NULL,
        decided_by $fkType NULL,
        decided_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        created_at $ts,
$pkLine        FOREIGN KEY (assignment_id) REFERENCES application_committee_assignments (id) ON DELETE RESTRICT ON UPDATE CASCADE,
        FOREIGN KEY (application_id) REFERENCES research_applications (id) ON DELETE RESTRICT ON UPDATE CASCADE,
        FOREIGN KEY (committee_id) REFERENCES committees (id) ON DELETE RESTRICT ON UPDATE CASCADE,
        FOREIGN KEY (decided_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
    )", 'table committee_decisions');
}

if (!$tableExists('research_agreements')) {
    $exec("CREATE TABLE research_agreements (
        id $pk,
        agreement_number VARCHAR(40) NOT NULL,
        application_id $fkType NOT NULL,
        project_id $fkType NULL,
        version INTEGER NOT NULL DEFAULT 1,
        status $enumAgreement NOT NULL DEFAULT 'draft',
        title VARCHAR(250) NOT NULL,
        terms TEXT NULL,
        agreed_amount $decimal NULL,
        previous_version_id $fkType NULL,
        prepared_by $fkType NULL,
        prepared_at DATETIME NULL,
        issued_by $fkType NULL,
        issued_at DATETIME NULL,
        signed_by $fkType NULL,
        signed_at DATETIME NULL,
        signature_name VARCHAR(150) NULL,
        signature_note TEXT NULL,
        created_at $ts,
        updated_at $ts,
$pkLine        FOREIGN KEY (application_id) REFERENCES research_applications (id) ON DELETE RESTRICT ON UPDATE CASCADE,
        FOREIGN KEY (project_id) REFERENCES research_projects (id) ON DELETE SET NULL ON UPDATE CASCADE,
        FOREIGN KEY (prepared_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
        FOREIGN KEY (issued_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
        FOREIGN KEY (signed_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
        FOREIGN KEY (previous_version_id) REFERENCES research_agreements (id) ON DELETE SET NULL ON UPDATE CASCADE
    )", 'table research_agreements');
    if ($sqlite) {
        Database::run('CREATE UNIQUE INDEX IF NOT EXISTS uq_agr_number ON research_agreements (agreement_number)');
    } else {
        Database::run('ALTER TABLE research_agreements ADD UNIQUE KEY uq_agr_number (agreement_number)');
    }
}

// ---------------------------------------------------------------------------
// 2. Columns: directorate-approved budget allocation (spec §28 step 32)
// ---------------------------------------------------------------------------
$colType = $sqlite ? 'NUMERIC' : 'DECIMAL(12,2)';
if (!$columnExists('research_applications', 'budget_approved_amount')) {
    $exec("ALTER TABLE research_applications ADD COLUMN budget_approved_amount $colType NULL", 'column research_applications.budget_approved_amount');
}
if (!$columnExists('research_applications', 'budget_approved_by')) {
    $exec('ALTER TABLE research_applications ADD COLUMN budget_approved_by ' . ($sqlite ? 'INTEGER' : 'BIGINT UNSIGNED') . ' NULL', 'column research_applications.budget_approved_by');
}

// ---------------------------------------------------------------------------
// 3. Workflow stage: UNIVERSITY_COMMITTEE_REVIEW
// ---------------------------------------------------------------------------
$wf = Database::one("SELECT id FROM workflows WHERE entity_type = 'research_application' LIMIT 1");
if ($wf === null) {
    echo "no research_application workflow found — run scripts/install.php first\n";
    exit(1);
}
$wfId = (int) $wf['id'];

$stageId = null;
$row = Database::one('SELECT id FROM workflow_stages WHERE workflow_id = ? AND stage_code = ?', [$wfId, 'UNIVERSITY_COMMITTEE_REVIEW']);
if ($row === null) {
    Database::run(
        "INSERT INTO workflow_stages (workflow_id, stage_code, stage_name, stage_type, is_terminal_exception, sort_order) VALUES (?, 'UNIVERSITY_COMMITTEE_REVIEW', 'University Committee Review', 'normal', 0, 165)",
        [$wfId]
    );
    $stageId = (int) Database::pdo()->lastInsertId();
    $log('stage UNIVERSITY_COMMITTEE_REVIEW');
} else {
    $stageId = (int) $row['id'];
}

$stageIdOf = static function (string $code) use ($wfId): int {
    $r = Database::one('SELECT id FROM workflow_stages WHERE workflow_id = ? AND stage_code = ?', [$wfId, $code]);
    if ($r === null) {
        throw new RuntimeException('Missing stage: ' . $code);
    }
    return (int) $r['id'];
};

// 3a. Remove the direct university approval (committee review is now mandatory)
foreach ([
    ['University approves', 'UNIVERSITY_REVIEW', 'APPROVED'],
    ['Skip to funded', 'APPROVED', 'FUNDED'],
] as [$name, $from, $to]) {
    $n = (int) Database::scalar(
        'SELECT COUNT(*) FROM workflow_transitions WHERE workflow_id = ? AND transition_name = ?
           AND from_stage_id = (SELECT id FROM workflow_stages WHERE workflow_id = ? AND stage_code = ?)
           AND to_stage_id   = (SELECT id FROM workflow_stages WHERE workflow_id = ? AND stage_code = ?)',
        [$wfId, $name, $wfId, $from, $wfId, $to]
    );
    if ($n > 0) {
        Database::run(
            'DELETE FROM workflow_transitions WHERE workflow_id = ? AND transition_name = ?
               AND from_stage_id = (SELECT id FROM workflow_stages WHERE workflow_id = ? AND stage_code = ?)
               AND to_stage_id   = (SELECT id FROM workflow_stages WHERE workflow_id = ? AND stage_code = ?)',
            [$wfId, $name, $wfId, $from, $wfId, $to]
        );
        $log("removed transition '$name' ($from → $to)");
    }
}

// 3b. Ethics cleared now returns to university review (committee is the approver)
$n = (int) Database::scalar(
    'SELECT COUNT(*) FROM workflow_transitions WHERE workflow_id = ? AND transition_name = ?
       AND from_stage_id = (SELECT id FROM workflow_stages WHERE workflow_id = ? AND stage_code = ?)
       AND to_stage_id   = (SELECT id FROM workflow_stages WHERE workflow_id = ? AND stage_code = ?)',
    [$wfId, 'Ethics cleared', $wfId, 'ETHICS_REVIEW', $wfId, 'APPROVED']
);
if ($n > 0) {
    Database::run(
        'UPDATE workflow_transitions SET to_stage_id = ?
           WHERE workflow_id = ? AND transition_name = ?
             AND from_stage_id = (SELECT id FROM workflow_stages WHERE workflow_id = ? AND stage_code = ?)
             AND to_stage_id   = (SELECT id FROM workflow_stages WHERE workflow_id = ? AND stage_code = ?)',
        [$stageIdOf('UNIVERSITY_REVIEW'), $wfId, 'Ethics cleared', $wfId, 'ETHICS_REVIEW', $wfId, 'APPROVED']
    );
    $log("'Ethics cleared' now routes ETHICS_REVIEW → UNIVERSITY_REVIEW");
}

// 3c. Activation requires a signed research agreement (spec Rules 10–11)
$changed = Database::run(
    "UPDATE workflow_transitions SET condition_key = 'agreement_signed'
       WHERE condition_key IS NOT 'agreement_signed'
         AND workflow_id = ?
         AND from_stage_id = (SELECT id FROM workflow_stages WHERE workflow_id = ? AND stage_code = 'FUNDED')
         AND to_stage_id   = (SELECT id FROM workflow_stages WHERE workflow_id = ? AND stage_code = 'ACTIVE')
         AND transition_name = 'Activate project'",
    [$wfId, $wfId, $wfId]
)->rowCount();
if ($changed > 0) {
    $log("'Activate project' gated by agreement_signed");
}

// Budget approval requires the recorded allocation (spec §28, Rule 13)
$changed = Database::run(
    "UPDATE workflow_transitions SET condition_key = 'budget_recorded'
       WHERE condition_key IS NOT 'budget_recorded'
         AND workflow_id = ?
         AND from_stage_id = (SELECT id FROM workflow_stages WHERE workflow_id = ? AND stage_code = 'BUDGET_REVIEW')
         AND to_stage_id   = (SELECT id FROM workflow_stages WHERE workflow_id = ? AND stage_code = 'BUDGET_APPROVED')
         AND transition_name = 'Budget approved'",
    [$wfId, $wfId, $wfId]
)->rowCount();
if ($changed > 0) {
    $log("'Budget approved' gated by budget_recorded");
}

// 3d. New transitions around the committee stage
$newTransitions = [
    // [name, from, to, permission, condition, sort]
    ['Assign to research committee', 'UNIVERSITY_REVIEW', 'UNIVERSITY_COMMITTEE_REVIEW', 'research.manage', 'committee_ready', 161],
    ['Approve (directorate)', 'UNIVERSITY_COMMITTEE_REVIEW', 'APPROVED', 'research.manage', 'committee_approved', 164],
    ['Reject (directorate)', 'UNIVERSITY_COMMITTEE_REVIEW', 'REJECTED', 'research.manage', 'committee_rejected', 165],
    ['Request revision (directorate)', 'UNIVERSITY_COMMITTEE_REVIEW', 'REVISION_REQUIRED', 'research.manage', 'committee_wants_revision', 166],
];
foreach ($newTransitions as [$name, $from, $to, $perm, $cond, $sort]) {
    $n = (int) Database::scalar(
        'SELECT COUNT(*) FROM workflow_transitions WHERE workflow_id = ? AND transition_name = ?
           AND from_stage_id = (SELECT id FROM workflow_stages WHERE workflow_id = ? AND stage_code = ?)',
        [$wfId, $name, $wfId, $from]
    );
    if ($n === 0) {
        Database::run(
            'INSERT INTO workflow_transitions (workflow_id, from_stage_id, to_stage_id, transition_name, required_permission, condition_key, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$wfId, $stageIdOf($from), $stageIdOf($to), $name, $perm, $cond, $sort]
        );
        $log("transition '$name' ($from → $to)");
    }
}

// ---------------------------------------------------------------------------
// 4. Permission: committee.manage (spec §11 — director manages committees)
// ---------------------------------------------------------------------------
$permId = null;
$row = Database::one("SELECT id FROM permissions WHERE name = 'committee.manage'");
if ($row === null) {
    Database::run("INSERT INTO permissions (name, module, description) VALUES ('committee.manage', 'org', 'Create research committees and manage their members (spec §11)')");
    $permId = (int) Database::pdo()->lastInsertId();
    $log("permission committee.manage");
} else {
    $permId = (int) $row['id'];
}
foreach (['system_admin', 'rpee_director', 'rpee_officer'] as $roleName) {
    $n = (int) Database::scalar(
        'SELECT COUNT(*) FROM role_permissions rp JOIN roles r ON r.id = rp.role_id WHERE r.name = ? AND rp.permission_id = ?',
        [$roleName, $permId]
    );
    if ($n === 0) {
        Database::run(
            'INSERT INTO role_permissions (role_id, permission_id) SELECT r.id, ? FROM roles r WHERE r.name = ?',
            [$permId, $roleName]
        );
        $log("granted committee.manage → $roleName");
    }
}

// ---------------------------------------------------------------------------
// 5. Supporting reference rows
// ---------------------------------------------------------------------------
$n = (int) Database::scalar("SELECT COUNT(*) FROM document_types WHERE type_code = 'research_agreement'");
if ($n === 0) {
    Database::run("INSERT INTO document_types (type_code, name) VALUES ('research_agreement', 'Research Agreement')");
    $log("document type 'research_agreement'");
}
$n = (int) Database::scalar("SELECT COUNT(*) FROM number_sequences WHERE seq_key = 'research_agreement' AND seq_year = 0");
if ($n === 0) {
    Database::run("INSERT INTO number_sequences (seq_key, seq_year, next_value) VALUES ('research_agreement', 0, 1)");
    $log("number sequence 'research_agreement'");
}
$n = (int) Database::scalar("SELECT COUNT(*) FROM system_settings WHERE setting_key = 'agreement_number_prefix'");
if ($n === 0) {
    Database::run("INSERT INTO system_settings (setting_key, setting_value, setting_group, value_type, description)
                   VALUES ('agreement_number_prefix', 'MWU-RPEE-AGR', 'numbering', 'string', 'Research agreement number prefix (spec §13)')");
    $log("setting 'agreement_number_prefix'");
}

// ---------------------------------------------------------------------------
// 6. Researcher-profile visibility (spec §5): directorate staff can list all
//    researcher profiles university-wide (rpee_officer was missing the grant)
// ---------------------------------------------------------------------------
$row = Database::one("SELECT id FROM permissions WHERE name = 'researcher.view'");
if ($row !== null) {
    $permId = (int) $row['id'];
    foreach (['rpee_officer'] as $roleName) {
        $n = (int) Database::scalar(
            'SELECT COUNT(*) FROM role_permissions rp JOIN roles r ON r.id = rp.role_id WHERE r.name = ? AND rp.permission_id = ?',
            [$roleName, $permId]
        );
        if ($n === 0) {
            Database::run(
                'INSERT INTO role_permissions (role_id, permission_id) SELECT r.id, ? FROM roles r WHERE r.name = ?',
                [$permId, $roleName]
            );
            $log("granted researcher.view → $roleName");
        }
    }
}

echo "\n" . (count($done) === 0 ? "nothing to do — database already migrated\n" : count($done) . " change(s) applied\n");
