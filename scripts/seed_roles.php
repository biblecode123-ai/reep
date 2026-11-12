<?php
declare(strict_types=1);

/**
 * Comprehensive role + demo data seeder (CLI).
 *
 * Creates one dedicated user for EVERY role in the system (§7), with realistic
 * organizational context, plus a demonstration dataset: an open research call,
 * a funded research application with an active project, an approved ethics
 * application, and role-appropriate assignments — so every dashboard has data.
 *
 * Idempotent: existing users (matched by email) are updated, not duplicated.
 * All passwords are hashed with password_hash() — never stored in plain text.
 *
 * Usage: php scripts/seed_roles.php
 */

if (PHP_SAPI !== 'cli') {
    exit("Run from CLI only.\n");
}

require __DIR__ . '/../config/app.php';
require __DIR__ . '/../app/autoload.php';
require __DIR__ . '/../app/helpers/functions.php';

use App\Core\Database;

echo "=== MWU RPEE — Role & Demo Seeder ===\n";

const DEMO_PASSWORD = 'Password123!';


// ---------------------------------------------------------------------------
// Ensure organizational structure exists
// ---------------------------------------------------------------------------
$ensureCollege = static function (string $name, string $code): int {
    $id = Database::scalar('SELECT id FROM colleges WHERE code = ? OR name = ? LIMIT 1', [$code, $name]);
    if ($id === null) {
        Database::run(Database::insertIgnore('colleges') . ' (name, code) VALUES (?, ?)', [$name, $code]);
        $id = Database::scalar('SELECT id FROM colleges WHERE code = ?', [$code]);
    }
    return (int) $id;
};
$ensureDept = static function (int $collegeId, string $name, string $code): int {
    $id = Database::scalar('SELECT id FROM departments WHERE name = ? LIMIT 1', [$name]);
    if ($id === null) {
        Database::run(Database::insertIgnore('departments') . ' (college_id, name, code) VALUES (?, ?, ?)', [$collegeId, $name, $code]);
        $id = Database::scalar('SELECT id FROM departments WHERE college_id = ? AND code = ?', [$collegeId, $code]);
    }
    return (int) $id;
};

// Full canonical MWU structure (shared with install.php / update_org_structure.php).
$STRUCT = require __DIR__ . '/org_structure.php';
$collegeIds = [];
foreach ($STRUCT['colleges'] as $code => [$name, $legacy]) {
    $collegeIds[$code] = $ensureCollege($name, $code);
}
foreach ($STRUCT['departments'] as $cc => $depts) {
    foreach ($depts as [$name, $code]) {
        $ensureDept($collegeIds[$cc], $name, $code);
    }
}

$cRpee = $collegeIds['RPEE'];
$cCncs = $collegeIds['CNCS'];
$cCanr = $collegeIds['CANR'];
$cCssh = $collegeIds['CSSH'];
$cCoe  = $collegeIds['COE'];
$cBus  = $collegeIds['CBE'];
$cCc   = $collegeIds['CC'];

$dRpee  = $ensureDept($cRpee, 'Research Management', 'RM');
$dCs    = $ensureDept($cCc,   'Computer Science', 'CS');
$dMath  = $ensureDept($cCncs, 'Mathematics', 'MATH');
$dAnsc  = $ensureDept($cCanr, 'Animal Science', 'ANSC');
$dPlant = $ensureDept($cCanr, 'Plant Science', 'PLSC');
$dSoc   = $ensureDept($cCssh, 'Sociology', 'SOC');
$dEng   = $ensureDept($cCoe,  'Civil Engineering', 'CIVL');
$dMgmt  = $ensureDept($cBus,  'Management', 'MGMT');

$ensureCommittee = static function (string $name, string $code, string $type, ?int $collegeId): int {
    Database::run(Database::insertIgnore('committees') . ' (name, code, committee_type, college_id, status) VALUES (?, ?, ?, ?, "active")', [$name, $code, $type, $collegeId]);
    return (int) Database::scalar('SELECT id FROM committees WHERE code = ?', [$code]);
};

$cUrc = $ensureCommittee('University Research Committee', 'URC', 'university_research', null);
$cCrc = $ensureCommittee('CNCS College Research Committee', 'CRC-CNCS', 'college_research', $cCncs);
$cErc = $ensureCommittee('Research Ethics Committee', 'ERC', 'ethics', null);

echo "[1/6] Organizational structure ready (" . count($STRUCT['colleges']) . " colleges/schools, " . array_sum(array_map('count', $STRUCT['departments'])) . " departments, 3 committees)\n";

// ---------------------------------------------------------------------------
// Role accounts — one dedicated user per role (§7)
// ---------------------------------------------------------------------------
$roleId = static function (string $name): int {
    return (int) Database::scalar('SELECT id FROM roles WHERE name = ?', [$name]);
};

/**
 * [email, name, role, title, college, dept, extra roles]
 */
$people = [
    ['admin@mwu.edu.et',            'Ato Bekele Negash',        'system_admin',           'System Administrator',              $cRpee, $dRpee,  []],
    ['director@mwu.edu.et',         'Dr. Alemu Bekele',         'rpee_director',          'RPEE Director',                     $cRpee, $dRpee,  []],
    ['officer@mwu.edu.et',          'Ms. Hanna Tesfaye',        'rpee_officer',           'RPEE Officer',                      $cRpee, $dRpee,  []],
    ['committee@mwu.edu.et',        'Prof. Solomon Girma',      'univ_committee',         'University Research Committee Member', $cCncs, $dMath, []],
    ['ccoordinator@mwu.edu.et',     'Dr. Kebede Worku',         'college_coordinator',    'College Research Coordinator',      $cCncs, $dMath,  []],
    ['dcoordinator@mwu.edu.et',     'Mr. Tadesse Ayele',        'dept_coordinator',       'Department Research Coordinator',   $cCc,   $dCs,    []],
    ['researcher@mwu.edu.et',       'Dr. Tigist Ayele',         'researcher',             'Researcher',                        $cCc,   $dCs,    ['principal_investigator']],
    ['coinvestigator@mwu.edu.et',   'Dr. Meron Girma',          'co_investigator',        'Co-Investigator',                   $cCc,   $dCs,    []],
    ['evaluator@mwu.edu.et',        'Dr. Yonas Girma',          'evaluator',              'Evaluator',                         $cCanr, $dAnsc,  []],
    ['ethics.member@mwu.edu.et',    'Dr. Marta Abebe',          'ethics_member',          'Ethics Committee Member',           $cCssh, $dSoc,   []],
    ['ethics.reviewer@mwu.edu.et',  'Dr. Liya Haile',           'ethics_reviewer',        'Ethics Reviewer',                   $cCssh, $dSoc,   []],
    ['publication@mwu.edu.et',      'Mr. Dawit Kebede',         'publication_officer',    'Publication Officer',               $cRpee, $dRpee,  []],
    ['extension@mwu.edu.et',        'Mrs. Sifan Ahmed',         'extension_officer',      'Extension Officer',                 $cRpee, $dRpee,  []],
    ['extension.evaluator@mwu.edu.et', 'Dr. Chala Degaga',      'extension_evaluator',    'Extension Evaluator',               $cCanr, $dPlant, []],
    ['panel@mwu.edu.et',            'Dr. Nardos Fikru',         'panel_member',           'Presentation Panel Member',         $cCoe,  $dEng,   []],
    ['repository@mwu.edu.et',       'Mr. Yonatan Tesfaye',      'repository_manager',     'Repository Manager',                $cRpee, $dRpee,  []],
    ['finance@mwu.edu.et',          'Mr. Daniel Mekonnen',      'finance_officer',        'Finance Officer',                   $cRpee, $dRpee,  []],
    ['readonly@mwu.edu.et',         'Mrs. Selam Worku',         'readonly_mgmt',          'Read-Only Management User',         $cRpee, $dRpee,  []],
];

$hash = password_hash(DEMO_PASSWORD, PASSWORD_BCRYPT, ['cost' => 12]);
$empSeq = 100;
$userIds = [];

foreach ($people as [$email, $name, $role, $title, $cid, $did, $extraRoles]) {
    $empSeq++;
    $empId = 'EMP-' . str_pad((string) $empSeq, 4, '0', STR_PAD_LEFT);
    $userData = [
        'employee_id'          => $empId,
        'full_name'            => $name,
        'email'                => $email,
        'phone'                => '+2519100000' . str_pad((string) ($empSeq % 100), 2, '0', STR_PAD_LEFT),
        'password_hash'        => $hash,
        'status'               => 'active',
        'position_title'       => $title,
        'college_id'           => $cid,
        'department_id'        => $did,
        'must_change_password' => 0,
    ];
    $existingId = Database::scalar('SELECT id FROM users WHERE email = ?', [$email]);
    if ($existingId !== null) {
        Database::update('users', $userData, 'id = ?', [(int) $existingId]);
        $uid = (int) $existingId;
    } else {
        $uid = Database::insert('users', $userData);
    }
    $userIds[$role] = $uid;

    $allRoles = array_merge([$role], $extraRoles);
    foreach ($allRoles as $r) {
        Database::run(Database::insertIgnore('user_roles') . ' (user_id, role_id) VALUES (?, ?)', [$uid, $roleId($r)]);
    }
    if (in_array($role, ['researcher', 'principal_investigator', 'co_investigator'], true)) {
        $researcherData = ['academic_position' => $title, 'qualification' => 'PhD',
            'research_interests' => 'ICT4D, data science for agriculture', 'orcid' => '0000-0002-1825-0097'];
        if (Database::scalar('SELECT user_id FROM researchers WHERE user_id = ?', [$uid]) !== null) {
            Database::update('researchers', $researcherData, 'user_id = ?', [$uid]);
        } else {
            Database::insert('researchers', ['user_id' => $uid] + $researcherData);
        }
    }
}
echo "[2/6] " . count($people) . " role accounts ready (password: " . DEMO_PASSWORD . ")\n";

// ---------------------------------------------------------------------------
// Ensure an evaluator record for the evaluator user (§20)
// ---------------------------------------------------------------------------
Database::run(Database::insertIgnore('research_evaluators') . ' (user_id, expertise, max_concurrent_evaluations) VALUES (?, ?, ?)',
    [$userIds['evaluator'], 'Agricultural economics; rural livelihoods; survey methodology', 5]);
Database::run(Database::insertIgnore('research_evaluators') . ' (user_id, expertise, max_concurrent_evaluations) VALUES (?, ?, ?)',
    [$userIds['univ_committee'], 'Applied mathematics; modelling', 3]);

// ---------------------------------------------------------------------------
// Committee memberships — so committee pages and org charts have real data
// ---------------------------------------------------------------------------
$committeeSeats = [
    [$cUrc, 'univ_committee',      'chair'],
    [$cUrc, 'rpee_director',       'secretary'],
    [$cUrc, 'rpee_officer',        'member'],
    [$cCrc, 'college_coordinator', 'chair'],
    [$cCrc, 'dept_coordinator',    'member'],
    [$cCrc, 'researcher',          'member'],
    [$cErc, 'ethics_member',       'chair'],
    [$cErc, 'ethics_reviewer',     'secretary'],
    [$cErc, 'rpee_officer',        'member'],
];
foreach ($committeeSeats as [$committeeId, $roleKey, $seatRole]) {
    Database::run(Database::insertIgnore('committee_members') . ' (committee_id, user_id, member_role, appointed_from, status) VALUES (?, ?, ?, ?, "active")',
        [$committeeId, $userIds[$roleKey], $seatRole, date('Y-m-d')]);
}
echo "[2b] Committee memberships ready (URC, CRC-CNCS, ERC)\n";

// ---------------------------------------------------------------------------
// Demo dataset: call → application → project → ethics
// ---------------------------------------------------------------------------
echo "[3/6] Creating demo dataset…\n";
$year = (int) date('Y');

// 1. An open call with criteria (reuse the deterministic demo call if it exists)
$callId = (int) Database::scalar('SELECT id FROM research_calls WHERE call_number = ?', ['MWU-RPEE-CALL-' . $year . '-0001']);
if ($callId === 0) {
    Database::run('INSERT INTO research_calls (call_number, reference_number, title, description, opens_at, closes_at, max_budget, max_duration_months, max_applications_per_researcher, evaluation_mode, required_evaluators, status, published_at, created_by)
        VALUES (?, ?, ?, ?, ?, ?, 250000, 18, 2, "single_blind", 2, "open", ?, ?)',
        ['MWU-RPEE-CALL-' . $year . '-0001', 'MWU/RPEE/CALL/' . $year . '/01',
         'Institutional Research Grant Call ' . $year,
         'Annual competitive call for research proposals addressing university and community priorities.',
         date('Y-m-d H:i:s'), date('Y-m-d H:i:s', strtotime('+45 days')),
         date('Y-m-d H:i:s'), $userIds['rpee_officer']]);
    $callId = (int) Database::scalar('SELECT id FROM research_calls WHERE call_number = ?', ['MWU-RPEE-CALL-' . $year . '-0001']);
}
foreach ([['Significance and relevance', 20], ['Literature review', 10], ['Objectives', 10], ['Methodology', 25], ['Innovation', 10], ['Feasibility', 10], ['Budget justification', 15]] as $i => [$nm, $w]) {
    Database::run(Database::insertIgnore('research_call_criteria') . ' (call_id, criterion_name, weight_pct, sort_order) VALUES (?, ?, ?, ?)', [$callId, $nm, $w, $i]);
}
foreach (['proposal', 'cv', 'budget_doc', 'work_plan'] as $i => $tc) {
    Database::run(Database::insertIgnore('research_call_requirements') . ' (call_id, document_type_id, requirement, sort_order)
                   SELECT ?, id, "required", ? FROM document_types WHERE type_code = ?', [$callId, $i, $tc]);
}
Database::run(Database::insertIgnore('research_call_requirements') . ' (call_id, document_type_id, requirement, condition_note, sort_order)
               SELECT ?, id, "conditional", "Required when the research involves human participants", 4 FROM document_types WHERE type_code = "ethics_doc"', [$callId]);

// 2. A funded application with an active project
$existsApp = (int) Database::scalar('SELECT COUNT(*) FROM research_applications WHERE application_number = ?', ['MWU-RPEE-APP-' . $year . '-0100']);
if ($existsApp === 0) {
    $wfResearch = (int) Database::scalar('SELECT id FROM workflows WHERE entity_type = "research_application" LIMIT 1');
    $stageSubmitted = (int) Database::scalar('SELECT id FROM workflow_stages WHERE workflow_id = ? AND stage_code = "SUBMITTED"', [$wfResearch]);
    $stageFunded = (int) Database::scalar('SELECT id FROM workflow_stages WHERE workflow_id = ? AND stage_code = "FUNDED"', [$wfResearch]);

    Database::run('INSERT INTO research_applications (application_number, call_id, pi_user_id, title, research_type_id, research_theme_id, college_id, department_id, duration_months, proposed_start_date, proposed_end_date, abstract, budget_requested, requires_ethics, workflow_id, current_stage_id, status_code, submitted_at, funded_at, created_by)
        VALUES (?, ?, ?, ?, (SELECT id FROM research_types WHERE code = "applied"), (SELECT id FROM research_themes WHERE code = "agri"), ?, ?, 12, ?, ?, ?, 180000, "yes", ?, ?, "FUNDED", ?, ?, ?)',
        ['MWU-RPEE-APP-' . $year . '-0100', $callId, $userIds['researcher'],
         'Climate-Smart Irrigation for Smallholder Resilience in Bale Zone',
         $cCncs, $dCs, date('Y-m-d', strtotime('+1 month')), date('Y-m-d', strtotime('+13 month')),
         'This study designs and evaluates low-cost climate-smart irrigation scheduling for smallholder farmers in Bale Zone, measuring yield, water use and income effects across two growing seasons.',
         $wfResearch, $stageFunded, date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), $userIds['researcher']]);
    $appId = (int) Database::scalar('SELECT id FROM research_applications WHERE application_number = ?', ['MWU-RPEE-APP-' . $year . '-0100']);

    // Co-investigator on the team
    $projSeed = [
        'project_number' => 'MWU-RPEE-' . $year . '-0100',
        'application_id' => $appId,
        'title'          => 'Climate-Smart Irrigation for Smallholder Resilience in Bale Zone',
        'pi_user_id'     => $userIds['researcher'],
        'college_id'     => $cCncs,
        'department_id'  => $dCs,
        'duration_months' => 12,
        'planned_start'  => date('Y-m-d', strtotime('+1 month')),
        'planned_end'    => date('Y-m-d', strtotime('+13 month')),
        'approved_budget' => 180000,
        'status_code'    => 'ACTIVE',
        'actual_start'   => date('Y-m-d'),
        'created_by'     => $userIds['rpee_officer'],
    ];
    Database::run(Database::insertIgnore('research_projects') . ' (project_number, application_id, title, pi_user_id, college_id, department_id, duration_months, planned_start, planned_end, approved_budget, status_code, actual_start, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$projSeed['project_number'], $appId, $projSeed['title'], $projSeed['pi_user_id'], $projSeed['college_id'], $projSeed['department_id'],
         $projSeed['duration_months'], $projSeed['planned_start'], $projSeed['planned_end'], $projSeed['approved_budget'], $projSeed['status_code'], $projSeed['actual_start'], $projSeed['created_by']]);
    $projId = (int) Database::scalar('SELECT id FROM research_projects WHERE project_number = ?', [$projSeed['project_number']]);

    foreach ([[$userIds['researcher'], 'principal_investigator'], [$userIds['co_investigator'], 'co_investigator']] as [$muid, $mrole]) {
        Database::run(Database::insertIgnore('research_project_members') . ' (project_id, user_id, member_role, joined_at, status) VALUES (?, ?, ?, ?, "active")', [$projId, $muid, $mrole, date('Y-m-d')]);
    }
    foreach ([['Literature review and instrument design', 20], ['Baseline survey and data collection', 40], ['Analysis and validation workshop', 70], ['Final report and presentation', 100]] as $i => [$nm, $day]) {
        Database::run('INSERT INTO research_milestones (project_id, milestone_name, due_date, responsible_user_id, sort_order) VALUES (?, ?, ?, ?, ?)', [$projId, $nm, date('Y-m-d', time() + $day * 86400), $userIds['researcher'], $i]);
    }
    Database::run('INSERT INTO research_budgets (project_id, requested_amount, approved_amount, released_amount, status, approved_at, created_by) VALUES (?, 180000, 150000, 90000, "approved", ?, ?)', [$projId, date('Y-m-d H:i:s'), $userIds['finance_officer']]);
    Database::run('INSERT INTO funding_releases (project_id, budget_id, amount, release_reference, released_by) SELECT ?, id, 90000, "DEMO-TRANCHE-1", ? FROM research_budgets WHERE project_id = ?', [$projId, $userIds['finance_officer'], $projId]);
}

// 3. Approved ethics application for the demo research
$appId = (int) Database::scalar('SELECT id FROM research_applications WHERE application_number = ?', ['MWU-RPEE-APP-' . $year . '-0100']);
$ethExists = (int) Database::scalar('SELECT COUNT(*) FROM ethics_applications WHERE research_application_id = ?', [$appId]);
if ($ethExists === 0) {
    $wfEthics = (int) Database::scalar('SELECT id FROM workflows WHERE entity_type = "ethics_application" LIMIT 1');
    $ethStage = (int) Database::scalar('SELECT id FROM workflow_stages WHERE workflow_id = ? AND stage_code = "APPROVED"', [$wfEthics]);
    Database::run(Database::insertIgnore('ethics_applications') . ' (application_number, research_application_id, researcher_user_id, participant_count, participant_description, risk_level, consent_process, confidentiality_measures, review_track, status_code, status, workflow_id, current_stage_id, submitted_at, decided_at, approved_until, created_by)
        VALUES (?, ?, ?, 180, ?, "low", "Written informed consent in Afaan Oromo and Amharic", "Anonymized codes; encrypted storage", "full", "APPROVED", "approved", ?, ?, ?, ?, ?, ?)',
        ['MWU-RPEE-ETH-' . $year . '-0100', $appId, $userIds['researcher'],
         'Smallholder farming households and key informants', $wfEthics, $ethStage,
         date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), date('Y-m-d', strtotime('+1 year')), $userIds['rpee_officer']]);
    $ethId = (int) Database::scalar('SELECT id FROM ethics_applications WHERE research_application_id = ?', [$appId]);
    Database::run(Database::insertIgnore('ethics_reviews') . ' (ethics_application_id, reviewer_user_id, assigned_by, status, comments, recommendation, submitted_at) VALUES (?, ?, ?, "completed", "Consent process adequate for the target community.", "approve", ?)', [$ethId, $userIds['ethics_reviewer'], $userIds['rpee_officer'], date('Y-m-d H:i:s')]);
    Database::run(Database::insertIgnore('ethics_decisions') . ' (ethics_application_id, decision, comments, decided_by, expires_at) VALUES (?, "approved", "Approved by the ethics committee.", ?, ?)', [$ethId, $userIds['rpee_officer'], date('Y-m-d', strtotime('+1 year'))]);
    $certNum = \App\Services\NumberingService::next('ethics_certificate');
    Database::run(Database::insertIgnore('ethics_certificates') . ' (ethics_application_id, certificate_number, issued_date, expires_at, issued_by) VALUES (?, ?, ?, ?, ?)', [$ethId, $certNum, date('Y-m-d'), date('Y-m-d', strtotime('+1 year')), $userIds['rpee_officer']]);
}

// 4. A second submitted application awaiting college screening (for coordinator/evaluator dashboards)
// Re-run guard: if the app 0100 block was skipped but 0101 exists (or vice versa),
// refresh the row here so both sections stay idempotent independently.
$secondApp = (int) Database::scalar('SELECT COUNT(*) FROM research_applications WHERE application_number = ?', ['MWU-RPEE-APP-' . $year . '-0101']);
if ($secondApp === 0) {
    $wfResearch = (int) Database::scalar('SELECT id FROM workflows WHERE entity_type = "research_application" LIMIT 1');
    $stageSubmitted = (int) Database::scalar('SELECT id FROM workflow_stages WHERE workflow_id = ? AND stage_code = "SUBMITTED"', [$wfResearch]);
    Database::run('INSERT INTO research_applications (application_number, call_id, pi_user_id, title, college_id, department_id, duration_months, abstract, budget_requested, requires_ethics, workflow_id, current_stage_id, status_code, submitted_at, created_by)
        VALUES (?, ?, ?, ?, ?, ?, 10, ?, 120000, "no", ?, ?, "SUBMITTED", ?, ?)',
        ['MWU-RPEE-APP-' . $year . '-0101', $callId, $userIds['co_investigator'],
         'Digital Extension Advisory Services: Adoption and Impact among Smallholders',
         $cCncs, $dCs,
         'Examines adoption determinants and income effects of mobile-based extension advisory services among smallholder farmers, using a difference-in-differences design across woredas.',
         $wfResearch, $stageSubmitted, date('Y-m-d H:i:s'), $userIds['co_investigator']]);
}
// ---------------------------------------------------------------------------
// Demo publications, presentations, extension and repository records so the
// specialist dashboards (publication officer, extension officer/evaluator,
// panel member, repository manager) show real content (§45–§49, §55).
// ---------------------------------------------------------------------------
echo "[3b] Creating specialist module demo data…\n";
$projId = (int) Database::scalar('SELECT id FROM research_projects WHERE project_number = ?', ['MWU-RPEE-' . $year . '-0100']);

if ((int) Database::scalar('SELECT COUNT(*) FROM publications') === 0) {
    foreach ([
        ['Climate-smart irrigation scheduling and yield response in Bale Zone smallholder systems', 'Ethiopian Journal of Agricultural Sciences', 2026, 'published', 1],
        ['Adoption determinants of mobile-based extension advisory services', 'African Journal of Agricultural Research', 2026, 'accepted', 3],
        ['Participatory variety selection of drought-tolerant durum wheat', 'Journal of Plant Breeding and Crop Science', 2025, 'under_review', null],
    ] as $i => [$pt, $jn, $py, $ps, $pjid]) {
        $subMonths = 6 - $i;
        Database::run('INSERT INTO publications (project_id, title, journal_name, publication_year, publication_type, status, submission_date, acceptance_date, published_date, created_by)
            VALUES (?, ?, ?, ?, "journal_article", ?, ?, ?, ?, ?)',
            [$pjid !== null ? $projId : null, $pt, $jn, $py, $ps, date('Y-m-d', strtotime("-$subMonths months")),
             $ps === 'accepted' ? date('Y-m-d') : null,
             $ps === 'published' ? date('Y-m-d') : null,
             $userIds['publication_officer']]);
    }
}

// Final presentation with a full panel — scheduled_at is the deterministic
// handle for idempotency (one scheduled presentation per project in demo data).
if ((int) Database::scalar('SELECT COUNT(*) FROM presentations') === 0) {
    Database::run('INSERT INTO presentations (project_id, scheduled_at, venue, status, scheduled_by, notes)
        VALUES (?, ?, "Main Campus — Senate Hall", "scheduled", ?, "Final project presentation")',
        [$projId, date('Y-m-d 02:00:00', strtotime('+21 days')), $userIds['rpee_officer']]);
    $presId = (int) Database::scalar('SELECT id FROM presentations WHERE project_id = ? AND scheduled_at = ?',
        [$projId, date('Y-m-d 02:00:00', strtotime('+21 days'))]);
    foreach ([[$userIds['panel_member'], 'chair', null], [$userIds['univ_committee'], 'member', null], [$userIds['evaluator'], 'external_expert', 'External expert (CAVM)'], [$userIds['rpee_officer'], 'secretary', null]] as $i => [$pu, $prole, $aff]) {
        Database::run('INSERT INTO presentation_panels (presentation_id, user_id, member_name, member_role, affiliation)
            SELECT ?, id, full_name, ?, ? FROM users WHERE id = ?', [$presId, $prole, $aff, $pu]);
    }
}

if ((int) Database::scalar('SELECT COUNT(*) FROM extension_calls') === 0) {
    Database::run('INSERT INTO extension_calls (call_number, reference_number, title, description, opens_at, closes_at, max_budget, max_duration_months, status, published_at, created_by)
        VALUES (?, ?, ?, ?, ?, ?, 120000, 9, "open", ?, ?)',
        ['MWU-RPEE-EXTCALL-' . $year . '-0001', 'MWU/RPEE/EXT/' . $year . '/01',
         'Community Extension Program Call ' . $year,
         'Call for community-based extension projects addressing local livelihoods, health and education priorities.',
         date('Y-m-d H:i:s'), date('Y-m-d H:i:s', strtotime('+40 days')),
         date('Y-m-d H:i:s'), $userIds['extension_officer']]);
    $extCallId = (int) Database::scalar('SELECT id FROM extension_calls WHERE call_number = ?', ['MWU-RPEE-EXTCALL-' . $year . '-0001']);
    Database::run('INSERT INTO extension_applications (application_number, call_id, pi_user_id, title, community, location, partner_organization, target_beneficiaries, objectives, planned_activities, duration_months, start_date, end_date, budget_requested, current_stage_id, workflow_id, status, submitted_at, approved_at, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 8, ?, ?, 95000, NULL, NULL, "approved", ?, ?, ?)',
        ['MWU-RPEE-EXTAPP-' . $year . '-0001', $extCallId, $userIds['extension_officer'],
         'Smallholder Dairy Value Chain Improvement in Goba Woreda',
         'Goba Woreda dairy farming community', 'Bale Zone, Oromia', 'Bale Zone Agriculture Office',
         '250 farming households',
         'Improve milk yield and market access through feeding, hygienic handling and cooperative strengthening.',
         'Training on improved feeding; milk handling demonstration; cooperative business skills.',
         date('Y-m-d', strtotime('+1 month')), date('Y-m-d', strtotime('+9 month')), date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), $userIds['extension_officer']]);
    $extAppId = (int) Database::scalar('SELECT id FROM extension_applications WHERE application_number = ?', ['MWU-RPEE-EXTAPP-' . $year . '-0001']);
    Database::run('INSERT INTO extension_projects (project_number, application_id, title, pi_user_id, community, location, status, planned_start, planned_end, approved_budget)
        VALUES (?, ?, ?, ?, ?, ?, "active", ?, ?, 95000)',
        ['MWU-RPEE-EXT-' . $year . '-0001', $extAppId, 'Smallholder Dairy Value Chain Improvement in Goba Woreda',
         $userIds['extension_officer'], 'Goba Woreda dairy farming community', 'Bale Zone, Oromia',
         date('Y-m-d', strtotime('+1 month')), date('Y-m-d', strtotime('+9 month'))]);
    $extProjId = (int) Database::scalar('SELECT id FROM extension_projects WHERE project_number = ?', ['MWU-RPEE-EXT-' . $year . '-0001']);
    foreach ([['Baseline assessment of dairy households', 'completed', 180], ['Improved feeding training (round 1)', 'completed', 95], ['Hygienic milk handling demonstration', 'in_progress', null], ['Cooperative business skills training', 'planned', null]] as $i => [$an, $as, $bc]) {
        Database::run('INSERT INTO extension_activities (project_id, activity_name, description, planned_date, actual_date, status, beneficiary_count, sort_order)
            VALUES (?, ?, NULL, ?, ?, ?, ?, ?)',
            [$extProjId, $an, date('Y-m-d', time() + 30 * $i * 86400), $as === 'completed' ? date('Y-m-d') : null, $as, $bc, $i]);
    }
    Database::run('INSERT INTO extension_partners (project_id, partner_name, partner_type, contact_person, partnership_role)
        VALUES (?, "Bale Zone Agriculture Office", "government", "Woreda Livestock Development Officer", "Technical backstopping and farmer mobilization")', [$extProjId]);
}

if ((int) Database::scalar('SELECT COUNT(*) FROM repository_records') === 0) {
    Database::run('INSERT INTO repository_records (repository_id_code, project_id, title, abstract, keywords, year, college_id, department_id, citation_text, visibility, status, published_at, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "public", "published", ?, ?)',
        ['MWU-RPEE-REP-' . $year . '-0001', $projId,
         'Climate-Smart Irrigation for Smallholder Resilience in Bale Zone — final dataset and report',
         'Final project report and anonymized household survey dataset (version 1.0).',
         'climate-smart agriculture; irrigation; smallholders; Bale Zone',
         $year, $cCncs, $dCs,
         'Ayele, T., & Girma, M. (' . $year . '). Climate-Smart Irrigation for Smallholder Resilience in Bale Zone. MWU-RPEE-' . $year . '-0001.',
         date('Y-m-d H:i:s'), $userIds['repository_manager']]);
}

echo "[4/6] Demo call, applications, project, ethics and assignments ready\n";

// ---------------------------------------------------------------------------
// Number sequences for the current year
// ---------------------------------------------------------------------------
foreach (['research_call', 'research_application', 'research_project', 'ethics_application',
          'repository_record', 'progress_report', 'integrity_case', 'appeal', 'ethics_certificate'] as $key) {
    Database::run(Database::insertIgnore('number_sequences') . ' (seq_key, seq_year, next_value) VALUES (?, ?, 1)', [$key, $year]);
}
echo "[5/6] Number sequences ready for {$year}\n";

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------
echo "[6/6] DONE.\n\n";
echo str_pad('EMAIL', 32) . str_pad('ROLE', 24) . 'NAME' . PHP_EOL;
echo str_repeat('-', 78) . PHP_EOL;
foreach ($people as [$email, $name, $role]) {
    echo str_pad($email, 32) . str_pad($role, 24) . $name . PHP_EOL;
}
echo str_repeat('-', 78) . PHP_EOL;
echo "Shared demo password: " . DEMO_PASSWORD . "  (researcher@mwu.edu.et also holds principal_investigator)\n";
