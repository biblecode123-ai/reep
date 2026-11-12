-- =============================================================================
-- MWU RPEE — Seed data (roles, permissions, workflows, settings, classifications)
-- Institutional data lives HERE (spec Rule 2) — nothing hardcoded in PHP.
-- Initial users are created by scripts/install.php (hashed passwords), never here.
-- =============================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------- roles (§7)
INSERT INTO roles (name, display_name, description, is_system) VALUES
('system_admin',          'System Administrator',                 'Technical system administration without academic decision authority', 1),
('rpee_director',         'RPEE Director',                        'Head of the Research, Publication, Ethics and Extension Office', 1),
('rpee_officer',          'RPEE Officer',                         'RPEE staff managing calls, review, budget and reporting', 1),
('univ_committee',        'University Research Committee Member', 'Member of the university research committee', 1),
('college_coordinator',   'College Research Coordinator',         'Routes, screens, assigns evaluators, college decisions', 1),
('dept_coordinator',      'Department Research Coordinator',      'Department-level coordination', 1),
('researcher',            'Researcher',                           'Applies to calls and implements research', 1),
('principal_investigator','Principal Investigator',               'Leads an approved research project', 1),
('co_investigator',       'Co-Investigator',                      'Team member of a research project', 1),
('evaluator',             'Evaluator',                            'Evaluates proposals when assigned', 1),
('ethics_member',         'Ethics Committee Member',              'Member of the research ethics committee', 1),
('ethics_reviewer',       'Ethics Reviewer',                      'Performs ethics reviews', 1),
('publication_officer',   'Publication Officer',                  'Manages publication records and workflow', 1),
('extension_officer',     'Extension Officer',                    'Manages extension module', 1),
('extension_evaluator',   'Extension Evaluator',                  'Evaluates extension proposals', 1),
('panel_member',          'Presentation Panel Member',            'Participates in final presentations', 1),
('repository_manager',    'Repository Manager',                   'Publishes and archives repository records', 1),
('finance_officer',       'Finance Officer',                      'Budget approval and funding release', 1),
('readonly_mgmt',         'Read-Only Management User',            'Institutional oversight, view only', 1);

-- --------------------------------------------------------- permissions (§8)
INSERT INTO permissions (name, module, description) VALUES
('research.view',            'research',    'View research applications'),
('research.create',          'research',    'Create research applications'),
('research.edit',            'research',    'Edit own applications'),
('research.submit',          'research',    'Submit applications for review'),
('research.screen',          'research',    'College screening of applications'),
('research.assign',          'research',    'Assign evaluators'),
('research.evaluate',        'research',    'Perform assigned evaluations'),
('research.review',          'research',    'Review at committee/university level'),
('research.approve',         'research',    'Approve applications'),
('research.reject',          'research',    'Reject applications'),
('research.request_revision','research',    'Request revisions'),
('research.manage',          'research',    'Full research management (RPEE)'),
('ethics.view',              'ethics',      'View ethics applications'),
('ethics.submit',            'ethics',      'Submit ethics applications'),
('ethics.screen',            'ethics',      'Ethics screening decisions'),
('ethics.review',            'ethics',      'Perform ethics reviews'),
('ethics.decide',            'ethics',      'Record ethics decisions'),
('ethics.manage',            'ethics',      'Manage ethics module'),
('publication.view',         'publication', 'View publications'),
('publication.create',       'publication', 'Create publication records'),
('publication.edit',         'publication', 'Edit publication records'),
('publication.review',       'publication', 'Review publications'),
('publication.approve',      'publication', 'Approve publications'),
('publication.manage',       'publication', 'Manage publication module'),
('extension.view',           'extension',   'View extension module'),
('extension.create',         'extension',   'Create extension applications'),
('extension.review',         'extension',   'Review extension applications'),
('extension.approve',        'extension',   'Approve extension applications'),
('extension.manage',         'extension',   'Manage extension module'),
('budget.view',              'budget',      'View budgets'),
('budget.create',            'budget',      'Create and submit budgets'),
('budget.approve',           'budget',      'Approve budgets'),
('budget.release',           'budget',      'Release funds'),
('budget.modify',            'budget',      'Request and apply budget revisions'),
('presentation.view',        'presentation','View presentations'),
('presentation.manage',      'presentation','Schedule and manage presentations'),
('presentation.evaluate',    'presentation','Evaluate presentations'),
('document.view',            'document',    'View documents by visibility rules'),
('document.upload',          'document',    'Upload documents'),
('document.download',        'document',    'Download accessible documents'),
('document.delete',          'document',    'Delete documents (logged)'),
('document.archive',         'document',    'Archive documents'),
('repository.view',          'repository',  'View repository'),
('repository.publish',       'repository',  'Publish repository records'),
('repository.archive',       'repository',  'Archive repository records'),
('repository.manage',        'repository',  'Manage repository module'),
('project.view',             'project',     'View research projects'),
('project.activate',         'project',     'Activate approved projects'),
('project.report',           'project',     'Submit progress and final reports'),
('project.finalize',         'project',     'Final evaluation, presentation, completion'),
('user.view',                'user',        'View users'),
('researcher.view',          'user',        'View researcher profiles (self; coordinator: own college; director: all)'),
('user.manage',              'user',        'Create and manage users'),
('user.roles',               'user',        'Assign roles to users'),
('user.delegate',            'user',        'Manage temporary delegations'),
('org.view',                 'org',         'View organizational structure'),
('org.manage',               'org',         'Manage colleges and departments'),
('committee.manage',         'org',         'Create research committees and manage their members (spec §11)'),
('classification.manage',    'org',         'Manage themes, types, document types, criteria'),
('workflow.manage',          'system',      'Configure workflows'),
('settings.manage',          'system',      'Manage system settings'),
('reports.view',             'system',      'Access reports and dashboards'),
('audit.view',               'system',      'View audit logs'),
('integrity.view',           'system',      'View research integrity cases'),
('integrity.manage',         'system',      'Manage research integrity cases');

-- ------------------------------------------------------------ role_permissions
-- System Administrator — technical account administration only.
-- Academic decisions, research management, policy settings, evaluator assignment,
-- and confidential academic review remain with the designated academic roles.
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
  ON p.name IN ('user.view','user.manage','org.view','audit.view')
WHERE r.name = 'system_admin';

-- RPEE Director — full institutional authority
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
  ON p.name IN (
    'research.view','research.create','research.edit','research.submit','research.screen','research.assign',
    'research.evaluate','research.review','research.approve','research.reject','research.request_revision','research.manage',
    'ethics.view','ethics.submit','ethics.screen','ethics.review','ethics.decide','ethics.manage',
    'publication.view','publication.create','publication.edit','publication.review','publication.approve','publication.manage',
    'extension.view','extension.create','extension.review','extension.approve','extension.manage',
    'budget.view','budget.approve','budget.modify','budget.create',
    'presentation.view','presentation.manage','presentation.evaluate',
    'document.view','document.upload','document.download','document.archive',
    'repository.view','repository.publish','repository.archive','repository.manage',
    'project.view','project.activate','project.report','project.finalize',
    'user.view','researcher.view','org.view','committee.manage','workflow.manage','settings.manage','reports.view','audit.view','integrity.view'
  ) WHERE r.name = 'rpee_director';

-- RPEE Officer — operational management
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
  ON p.name IN (
    'research.view','research.create','research.edit','research.submit','research.screen','research.assign',
    'research.evaluate','research.review','research.approve','research.reject','research.request_revision','research.manage',
    'ethics.view','ethics.screen','ethics.review','ethics.decide','ethics.manage',
    'publication.view','publication.create','publication.edit','publication.review','publication.approve','publication.manage',
    'extension.view','extension.review','extension.approve','extension.manage',
    'budget.view','budget.create','budget.modify',
    'presentation.view','presentation.manage','presentation.evaluate',
    'document.view','document.upload','document.download','document.archive',
    'repository.view','repository.publish','repository.archive','repository.manage',
    'project.view','project.activate','project.report','project.finalize',
    'user.view','researcher.view','org.view','committee.manage','reports.view','audit.view'
  ) WHERE r.name = 'rpee_officer';

-- University Research Committee Member
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
  ON p.name IN (
    'research.view','research.review','research.approve','research.reject','research.request_revision',
    'ethics.view','publication.view','extension.view','budget.view',
    'presentation.view','document.view','document.download','repository.view','project.view','reports.view'
  ) WHERE r.name = 'univ_committee';

-- College Research Coordinator
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
  ON p.name IN (
    'research.view','research.screen','research.assign','research.evaluate','research.review','research.approve','research.reject','research.request_revision',
    'ethics.view','publication.view','extension.view','budget.view',
    'presentation.view','presentation.manage','document.view','document.upload','document.download',
    'project.view','user.view','researcher.view','org.view','reports.view'
  ) WHERE r.name = 'college_coordinator';

-- Department Research Coordinator
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
  ON p.name IN (
    'research.view','research.screen','ethics.view','publication.view','extension.view','budget.view',
    'presentation.view','document.view','document.download','project.view','user.view','org.view','reports.view'
  ) WHERE r.name = 'dept_coordinator';

-- Researcher
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
  ON p.name IN (
    'research.view','research.create','research.edit','research.submit','research.evaluate',
    'ethics.view','ethics.submit','publication.view','publication.create','publication.edit',
    'extension.view','extension.create',
    'document.view','document.upload','document.download',
    'repository.view','project.view','project.report','researcher.view'
  ) WHERE r.name = 'researcher';

-- Principal Investigator — researcher rights plus budget and finalize
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
  ON p.name IN (
    'research.view','research.create','research.edit','research.submit',
    'ethics.view','ethics.submit','publication.view','publication.create','publication.edit',
    'extension.view','extension.create',
    'document.view','document.upload','document.download',
    'repository.view','project.view','project.report','researcher.view',
    'budget.view','budget.create','budget.modify','project.finalize'
  ) WHERE r.name = 'principal_investigator';

-- Co-Investigator — view and report on own projects
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
  ON p.name IN ('research.view','publication.view','extension.view','document.view','document.download','repository.view','project.view','project.report')
  WHERE r.name = 'co_investigator';

-- Evaluator
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
  ON p.name IN ('research.view','research.evaluate','document.view','document.download')
  WHERE r.name = 'evaluator';

-- Ethics Committee Member and Ethics Reviewer
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
  ON p.name IN ('ethics.view','ethics.review','research.view','document.view','document.download')
  WHERE r.name IN ('ethics_member','ethics_reviewer');

-- Publication Officer
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
  ON p.name IN ('publication.view','publication.create','publication.edit','publication.review','publication.approve','publication.manage','research.view','document.view','document.download','repository.view')
  WHERE r.name = 'publication_officer';

-- Extension Officer
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
  ON p.name IN ('extension.view','extension.create','extension.review','extension.approve','extension.manage','research.view','document.view','document.download')
  WHERE r.name = 'extension_officer';

-- Extension Evaluator
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
  ON p.name IN ('extension.view','extension.review','document.view','document.download')
  WHERE r.name = 'extension_evaluator';

-- Presentation Panel Member
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
  ON p.name IN ('presentation.view','presentation.evaluate','research.view','document.view','document.download')
  WHERE r.name = 'panel_member';

-- Repository Manager
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
  ON p.name IN ('repository.view','repository.publish','repository.archive','repository.manage','research.view','project.view','document.view','document.download','document.archive','document.upload')
  WHERE r.name = 'repository_manager';

-- Finance Officer
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
  ON p.name IN ('budget.view','budget.approve','budget.release','budget.modify','project.view','document.view','document.download','reports.view')
  WHERE r.name = 'finance_officer';

-- Read-Only Management User
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
  ON p.name IN ('research.view','ethics.view','publication.view','extension.view','budget.view','document.view','document.download','repository.view','project.view','reports.view','audit.view','user.view','org.view')
  WHERE r.name = 'readonly_mgmt';

-- ---------------------------------------------------------------- classifications
INSERT INTO research_types (name, code) VALUES
('Basic/Fundamental Research','basic'),
('Applied Research','applied'),
('Community-Based Research','community'),
('Institutional Research','institutional');

INSERT INTO research_themes (name, code) VALUES
('Agriculture and Food Security','agri'),
('Health and Nutrition','health'),
('Education and Pedagogy','edu'),
('Environment and Climate','env'),
('Engineering and Technology','engtech'),
('Social Sciences and Governance','social'),
('Culture, Heritage and Tourism','culture'),
('Economics and Business','econ');

INSERT INTO document_types (type_code, name) VALUES
('proposal','Research Proposal'),
('cv','Curriculum Vitae'),
('budget_doc','Budget Document'),
('work_plan','Work Plan'),
('ethics_doc','Ethics Document'),
('dmp','Data Management Plan'),
('supporting','Supporting Document'),
('progress_report','Progress Report'),
('final_report','Final Report'),
('presentation_doc','Presentation Material'),
('ethics_certificate','Ethics Certificate'),
('publication_evidence','Publication Evidence'),
('research_agreement','Research Agreement'),
('repository_file','Repository File'),
('dataset_file','Dataset File'),
('profile_photo','Profile Photo'),
('committee_minutes','Committee Minutes'),
('plagiarism_report','Plagiarism Report'),
('other','Other Document');

-- ---------------------------------------------------------------- workflows (§18, §92)
INSERT INTO workflows (name, entity_type, description) VALUES
('Research Application Lifecycle','research_application','Full research application state machine (spec §18)'),
('Research Project Lifecycle','research_project','Implementation to completion state machine'),
('Ethics Review Workflow','ethics_application','Screening, review, decision (§31)');

SET @wf_research = (SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1);
SET @wf_project  = (SELECT id FROM workflows WHERE entity_type='research_project' LIMIT 1);
SET @wf_ethics   = (SELECT id FROM workflows WHERE entity_type='ethics_application' LIMIT 1);

-- Research application stages (§18)
INSERT INTO workflow_stages (workflow_id, stage_code, stage_name, stage_type, is_terminal_exception, sort_order) VALUES
(@wf_research,'DRAFT','Draft','start',0,10),
(@wf_research,'SUBMITTED','Submitted','normal',0,20),
(@wf_research,'COLLEGE_RECEIVED','College Received','normal',0,30),
(@wf_research,'UNDER_COLLEGE_SCREENING','Under College Screening','normal',0,40),
(@wf_research,'EVALUATOR_ASSIGNMENT_PENDING','Evaluator Assignment Pending','normal',0,50),
(@wf_research,'EVALUATOR_ASSIGNED','Evaluator Assigned','normal',0,60),
(@wf_research,'EVALUATOR_ACCEPTED','Evaluator Accepted','normal',0,70),
(@wf_research,'UNDER_EVALUATION','Under Evaluation','normal',0,80),
(@wf_research,'EVALUATION_COMPLETED','Evaluation Completed','normal',0,90),
(@wf_research,'UNIV_EVAL_ASSIGNMENT_PENDING','Univ Evaluator Assignment Pending','normal',0,92),
(@wf_research,'UNIV_EVAL_ASSIGNED','Univ Evaluator Assigned','normal',0,94),
(@wf_research,'UNIV_EVAL_ACCEPTED','Univ Evaluator Accepted','normal',0,96),
(@wf_research,'UNIV_UNDER_EVALUATION','Univ Under Evaluation','normal',0,98),
(@wf_research,'UNIV_EVALUATION_COMPLETED','Univ Evaluation Completed','normal',0,99),
(@wf_research,'REVISION_REQUIRED','Revision Required','normal',0,100),
(@wf_research,'REVISION_SUBMITTED','Revision Submitted','normal',0,110),
(@wf_research,'RE_EVALUATION','Re-evaluation','normal',0,120),
(@wf_research,'COLLEGE_REVIEW','College Review','normal',0,130),
(@wf_research,'COLLEGE_APPROVED','College Approved','normal',0,140),
(@wf_research,'SUBMITTED_TO_UNIVERSITY','Submitted to University','normal',0,150),
(@wf_research,'UNIVERSITY_REVIEW','University Review','normal',0,160),
(@wf_research,'UNIVERSITY_COMMITTEE_REVIEW','University Committee Review','normal',0,165),
(@wf_research,'ETHICS_REVIEW','Ethics Review','normal',0,170),
(@wf_research,'APPROVED','Approved','normal',0,180),
(@wf_research,'BUDGET_REVIEW','Budget Review','normal',0,190),
(@wf_research,'BUDGET_APPROVED','Budget Approved','normal',0,200),
(@wf_research,'FUNDED','Funded','normal',0,210),
(@wf_research,'ACTIVE','Active','normal',0,220),
(@wf_research,'PROGRESS_MONITORING','Progress Monitoring','normal',0,230),
(@wf_research,'FINAL_REPORT_SUBMITTED','Final Report Submitted','normal',0,240),
(@wf_research,'FINAL_EVALUATION','Final Evaluation','normal',0,250),
(@wf_research,'PRESENTATION_SCHEDULED','Presentation Scheduled','normal',0,260),
(@wf_research,'PRESENTED','Presented','normal',0,270),
(@wf_research,'PRESENTATION_EVALUATION','Presentation Evaluation','normal',0,280),
(@wf_research,'FINAL_APPROVAL','Final Approval','normal',0,290),
(@wf_research,'COMPLETED','Completed','terminal',0,1000),
(@wf_research,'REPOSITORY','Repository','terminal',0,1010),
(@wf_research,'ARCHIVED','Archived','terminal',0,1020),
(@wf_research,'REJECTED','Rejected','terminal',1,1100),
(@wf_research,'WITHDRAWN','Withdrawn','terminal',1,1101),
(@wf_research,'CANCELLED','Cancelled','terminal',1,1102),
(@wf_research,'SUSPENDED','Suspended','normal',0,1103),
(@wf_research,'TERMINATED','Terminated','terminal',1,1104),
(@wf_research,'EXPIRED','Expired','terminal',1,1105);

-- Project stages
INSERT INTO workflow_stages (workflow_id, stage_code, stage_name, stage_type, is_terminal_exception, sort_order) VALUES
(@wf_project,'APPROVED','Approved (awaiting activation)','start',0,10),
(@wf_project,'ACTIVE','Active','normal',0,20),
(@wf_project,'PROGRESS_MONITORING','Progress Monitoring','normal',0,30),
(@wf_project,'FINAL_REPORT_SUBMITTED','Final Report Submitted','normal',0,40),
(@wf_project,'FINAL_EVALUATION','Final Evaluation','normal',0,50),
(@wf_project,'PRESENTATION_SCHEDULED','Presentation Scheduled','normal',0,60),
(@wf_project,'PRESENTATION_EVALUATION','Presentation Evaluation','normal',0,70),
(@wf_project,'FINAL_APPROVAL','Final Approval','normal',0,80),
(@wf_project,'COMPLETED','Completed','terminal',0,1000),
(@wf_project,'SUSPENDED','Suspended','normal',0,1100),
(@wf_project,'TERMINATED','Terminated','terminal',1,1101),
(@wf_project,'CANCELLED','Cancelled','terminal',1,1102);

-- Ethics stages (§31)
INSERT INTO workflow_stages (workflow_id, stage_code, stage_name, stage_type, is_terminal_exception, sort_order) VALUES
(@wf_ethics,'DRAFT','Draft','start',0,10),
(@wf_ethics,'SUBMITTED','Submitted','normal',0,20),
(@wf_ethics,'UNDER_SCREENING','Under Screening','normal',0,30),
(@wf_ethics,'UNDER_REVIEW','Under Review','normal',0,40),
(@wf_ethics,'REVISION_REQUIRED','Revision Required','normal',0,50),
(@wf_ethics,'APPROVED','Approved','terminal',0,1000),
(@wf_ethics,'APPROVED_WITH_CONDITIONS','Approved with Conditions','terminal',0,1001),
(@wf_ethics,'REJECTED','Rejected','terminal',1,1100),
(@wf_ethics,'EXEMPT','Exempt','terminal',0,1002);

-- Transitions for research application lifecycle
SET @s_draft      = (SELECT id FROM workflow_stages WHERE workflow_id=@wf_research AND stage_code='DRAFT');
SET @s_submitted  = (SELECT id FROM workflow_stages WHERE workflow_id=@wf_research AND stage_code='SUBMITTED');
SET @s_received   = (SELECT id FROM workflow_stages WHERE workflow_id=@wf_research AND stage_code='COLLEGE_RECEIVED');
SET @s_screening  = (SELECT id FROM workflow_stages WHERE workflow_id=@wf_research AND stage_code='UNDER_COLLEGE_SCREENING');
SET @s_assignpend = (SELECT id FROM workflow_stages WHERE workflow_id=@wf_research AND stage_code='EVALUATOR_ASSIGNMENT_PENDING');
SET @s_assigned   = (SELECT id FROM workflow_stages WHERE workflow_id=@wf_research AND stage_code='EVALUATOR_ASSIGNED');
SET @s_accepted   = (SELECT id FROM workflow_stages WHERE workflow_id=@wf_research AND stage_code='EVALUATOR_ACCEPTED');
SET @s_undereval  = (SELECT id FROM workflow_stages WHERE workflow_id=@wf_research AND stage_code='UNDER_EVALUATION');
SET @s_evaldone   = (SELECT id FROM workflow_stages WHERE workflow_id=@wf_research AND stage_code='EVALUATION_COMPLETED');
SET @s_univ_assign_pend = (SELECT id FROM workflow_stages WHERE workflow_id=@wf_research AND stage_code='UNIV_EVAL_ASSIGNMENT_PENDING');
SET @s_univ_assigned    = (SELECT id FROM workflow_stages WHERE workflow_id=@wf_research AND stage_code='UNIV_EVAL_ASSIGNED');
SET @s_univ_accepted    = (SELECT id FROM workflow_stages WHERE workflow_id=@wf_research AND stage_code='UNIV_EVAL_ACCEPTED');
SET @s_univ_undereval   = (SELECT id FROM workflow_stages WHERE workflow_id=@wf_research AND stage_code='UNIV_UNDER_EVALUATION');
SET @s_univ_evaldone    = (SELECT id FROM workflow_stages WHERE workflow_id=@wf_research AND stage_code='UNIV_EVALUATION_COMPLETED');
SET @s_revreq     = (SELECT id FROM workflow_stages WHERE workflow_id=@wf_research AND stage_code='REVISION_REQUIRED');
SET @s_revsub     = (SELECT id FROM workflow_stages WHERE workflow_id=@wf_research AND stage_code='REVISION_SUBMITTED');
SET @s_reeval     = (SELECT id FROM workflow_stages WHERE workflow_id=@wf_research AND stage_code='RE_EVALUATION');
SET @s_colrev     = (SELECT id FROM workflow_stages WHERE workflow_id=@wf_research AND stage_code='COLLEGE_REVIEW');
SET @s_colappr    = (SELECT id FROM workflow_stages WHERE workflow_id=@wf_research AND stage_code='COLLEGE_APPROVED');
SET @s_touniv     = (SELECT id FROM workflow_stages WHERE workflow_id=@wf_research AND stage_code='SUBMITTED_TO_UNIVERSITY');
SET @s_univrev    = (SELECT id FROM workflow_stages WHERE workflow_id=@wf_research AND stage_code='UNIVERSITY_REVIEW');
SET @s_univcom    = (SELECT id FROM workflow_stages WHERE workflow_id=@wf_research AND stage_code='UNIVERSITY_COMMITTEE_REVIEW');
SET @s_ethics     = (SELECT id FROM workflow_stages WHERE workflow_id=@wf_research AND stage_code='ETHICS_REVIEW');
SET @s_approved   = (SELECT id FROM workflow_stages WHERE workflow_id=@wf_research AND stage_code='APPROVED');
SET @s_budrev     = (SELECT id FROM workflow_stages WHERE workflow_id=@wf_research AND stage_code='BUDGET_REVIEW');
SET @s_budappr    = (SELECT id FROM workflow_stages WHERE workflow_id=@wf_research AND stage_code='BUDGET_APPROVED');
SET @s_funded     = (SELECT id FROM workflow_stages WHERE workflow_id=@wf_research AND stage_code='FUNDED');
SET @s_active     = (SELECT id FROM workflow_stages WHERE workflow_id=@wf_research AND stage_code='ACTIVE');
SET @s_progress   = (SELECT id FROM workflow_stages WHERE workflow_id=@wf_research AND stage_code='PROGRESS_MONITORING');
SET @s_finalsub   = (SELECT id FROM workflow_stages WHERE workflow_id=@wf_research AND stage_code='FINAL_REPORT_SUBMITTED');
SET @sfinaleval   = (SELECT id FROM workflow_stages WHERE workflow_id=@wf_research AND stage_code='FINAL_EVALUATION');
SET @s_presentsch = (SELECT id FROM workflow_stages WHERE workflow_id=@wf_research AND stage_code='PRESENTATION_SCHEDULED');
SET @s_presented  = (SELECT id FROM workflow_stages WHERE workflow_id=@wf_research AND stage_code='PRESENTED');
SET @s_preseval   = (SELECT id FROM workflow_stages WHERE workflow_id=@wf_research AND stage_code='PRESENTATION_EVALUATION');
SET @s_finalappr  = (SELECT id FROM workflow_stages WHERE workflow_id=@wf_research AND stage_code='FINAL_APPROVAL');
SET @s_completed  = (SELECT id FROM workflow_stages WHERE workflow_id=@wf_research AND stage_code='COMPLETED');
SET @s_repository = (SELECT id FROM workflow_stages WHERE workflow_id=@wf_research AND stage_code='REPOSITORY');
SET @s_archived   = (SELECT id FROM workflow_stages WHERE workflow_id=@wf_research AND stage_code='ARCHIVED');
SET @s_rejected   = (SELECT id FROM workflow_stages WHERE workflow_id=@wf_research AND stage_code='REJECTED');
SET @s_withdrawn  = (SELECT id FROM workflow_stages WHERE workflow_id=@wf_research AND stage_code='WITHDRAWN');

INSERT INTO workflow_transitions (workflow_id, from_stage_id, to_stage_id, transition_name, required_permission, condition_key, sort_order) VALUES
(@wf_research, @s_draft,     @s_submitted,  'Submit application',           'research.submit',            'documents_complete', 10),
(@wf_research, @s_submitted, @s_received,   'College receives',             'research.screen',            NULL, 20),
(@wf_research, @s_received,  @s_screening,  'Start screening',              'research.screen',            NULL, 30),
(@wf_research, @s_screening, @s_assignpend, 'Forward to evaluation',        'research.screen',            'documents_complete', 40),
(@wf_research, @s_screening, @s_revreq,     'Return for correction',        'research.screen',            NULL, 41),
(@wf_research, @s_assignpend,@s_assigned,   'Assign evaluators',            'research.assign',            NULL, 50),
(@wf_research, @s_assigned,  @s_accepted,   'Evaluator accepts',            NULL,                         'coi_cleared', 60),
(@wf_research, @s_accepted,  @s_undereval,  'Start evaluation',             NULL,                         NULL, 70),
(@wf_research, @s_undereval, @s_evaldone,   'Submit evaluation',            'research.evaluate',          NULL, 80),
(@wf_research, @s_evaldone,  @s_revreq,     'Request revision',             'research.request_revision',  NULL, 90),
(@wf_research, @s_evaldone,  @s_univ_assign_pend, 'Send to university evaluation', 'research.manage',     'evaluations_passed', 95),
(@wf_research, @s_univ_assign_pend, @s_univ_assigned, 'Assign university evaluators', 'research.assign',   NULL, 96),
(@wf_research, @s_univ_assigned,    @s_univ_accepted, 'Univ evaluator accepts', NULL,                       NULL, 97),
(@wf_research, @s_univ_accepted,    @s_univ_undereval, 'Start university evaluation', NULL,                 NULL, 98),
(@wf_research, @s_univ_undereval,   @s_univ_evaldone, 'Submit university evaluation', 'research.evaluate',  NULL, 99),
(@wf_research, @s_revreq,    @s_revsub,     'Submit revision',              'research.submit',            NULL, 100),
(@wf_research, @s_revsub,    @s_reeval,     'Re-evaluate',                  'research.evaluate',          NULL, 110),
(@wf_research, @s_revsub,    @s_screening,  'Return for screening',         'research.screen',            NULL, 111),
(@wf_research, @s_reeval,    @s_colrev,     'Send to college review',       'research.screen',            'evaluations_complete', 120),
(@wf_research, @s_univ_evaldone, @s_colrev, 'Send to college review',       'research.screen',            'univ_evaluations_complete', 122),
(@wf_research, @s_colrev,    @s_colappr,    'College approves',             'research.approve',           NULL, 130),
(@wf_research, @s_colrev,    @s_revreq,     'Request revision',             'research.request_revision',  NULL, 131),
(@wf_research, @s_colrev,    @s_rejected,   'College rejects',              'research.reject',            NULL, 132),
(@wf_research, @s_colappr,   @s_touniv,     'Submit to university',         'research.screen',            NULL, 140),
(@wf_research, @s_touniv,    @s_univrev,    'University review starts',     'research.review',            NULL, 150),
(@wf_research, @s_univrev,   @s_ethics,     'Route to ethics',              'research.review',            'ethics_required', 160),
(@wf_research, @s_univrev,   @s_univcom,    'Assign to research committee','research.manage',            'committee_ready', 161),
(@wf_research, @s_univrev,   @s_revreq,     'Request revision',             'research.request_revision',  NULL, 162),
(@wf_research, @s_univrev,   @s_rejected,   'University rejects',           'research.reject',            NULL, 163),
(@wf_research, @s_univcom,   @s_approved,   'Approve (directorate)',        'research.manage',            'committee_approved', 164),
(@wf_research, @s_univcom,   @s_rejected,   'Reject (directorate)',         'research.manage',            'committee_rejected', 165),
(@wf_research, @s_univcom,   @s_revreq,     'Request revision (directorate)','research.manage',           'committee_wants_revision', 166),
(@wf_research, @s_ethics,    @s_univrev,    'Ethics cleared',               'ethics.decide',              'ethics_cleared', 170),
(@wf_research, @s_approved,  @s_budrev,     'Budget review starts',         'budget.view',                'budget_required', 180),
(@wf_research, @s_budrev,    @s_budappr,    'Budget approved',              'budget.approve',             'budget_recorded', 190),
(@wf_research, @s_budappr,   @s_funded,     'Release funds',                'budget.release',             NULL, 200),
(@wf_research, @s_funded,    @s_active,     'Activate project',             'project.activate',           'agreement_signed', 210),
(@wf_research, @s_active,    @s_progress,   'Submit progress report',       'project.report',             NULL, 220),
(@wf_research, @s_progress,  @s_finalsub,   'Submit final report',          'project.report',             'final_docs_complete', 230),
(@wf_research, @s_finalsub,  @sfinaleval,   'Start final evaluation',       'project.finalize',           NULL, 240),
(@wf_research, @sfinaleval,  @s_presentsch, 'Schedule presentation',        'presentation.manage',        NULL, 250),
(@wf_research, @s_presentsch,@s_presented,  'Mark presented',               'presentation.manage',        NULL, 260),
(@wf_research, @s_presented, @s_preseval,   'Evaluate presentation',        'presentation.evaluate',      NULL, 270),
(@wf_research, @s_preseval,  @s_finalappr,  'Final approval',               'research.approve',           NULL, 280),
(@wf_research, @s_finalappr, @s_completed,  'Mark completed',               'research.manage',            NULL, 290),
(@wf_research, @s_completed, @s_repository, 'Publish to repository',        'repository.publish',         NULL, 300),
(@wf_research, @s_repository,@s_archived,   'Archive',                      'repository.archive',         NULL, 310),
(@wf_research, @s_submitted, @s_withdrawn,  'Withdraw',                     'research.submit',            NULL, 900),
(@wf_research, @s_screening, @s_rejected,   'Reject as ineligible',         'research.reject',            NULL, 901),
(@wf_research, @s_assigned,  @s_rejected,   'Reject during assignment',     'research.reject',            NULL, 902),
(@wf_research, @s_undereval, @s_rejected,   'Reject after evaluation',      'research.reject',            NULL, 903),
(@wf_research, @s_budrev,    @s_rejected,   'Reject after budget',          'budget.approve',             NULL, 904);

-- Transitions for project lifecycle
SET @p_approved  = (SELECT id FROM workflow_stages WHERE workflow_id=@wf_project AND stage_code='APPROVED');
SET @p_active    = (SELECT id FROM workflow_stages WHERE workflow_id=@wf_project AND stage_code='ACTIVE');
SET @p_progress  = (SELECT id FROM workflow_stages WHERE workflow_id=@wf_project AND stage_code='PROGRESS_MONITORING');
SET @p_finalsub  = (SELECT id FROM workflow_stages WHERE workflow_id=@wf_project AND stage_code='FINAL_REPORT_SUBMITTED');
SET @p_finaleval = (SELECT id FROM workflow_stages WHERE workflow_id=@wf_project AND stage_code='FINAL_EVALUATION');
SET @p_presch    = (SELECT id FROM workflow_stages WHERE workflow_id=@wf_project AND stage_code='PRESENTATION_SCHEDULED');
SET @p_presented = (SELECT id FROM workflow_stages WHERE workflow_id=@wf_project AND stage_code='PRESENTATION_EVALUATION');
SET @p_finalappr = (SELECT id FROM workflow_stages WHERE workflow_id=@wf_project AND stage_code='FINAL_APPROVAL');
SET @p_completed = (SELECT id FROM workflow_stages WHERE workflow_id=@wf_project AND stage_code='COMPLETED');
SET @p_suspended = (SELECT id FROM workflow_stages WHERE workflow_id=@wf_project AND stage_code='SUSPENDED');
SET @p_terminated= (SELECT id FROM workflow_stages WHERE workflow_id=@wf_project AND stage_code='TERMINATED');
SET @p_cancelled = (SELECT id FROM workflow_stages WHERE workflow_id=@wf_project AND stage_code='CANCELLED');

INSERT INTO workflow_transitions (workflow_id, from_stage_id, to_stage_id, transition_name, required_permission, condition_key, sort_order) VALUES
(@wf_project, @p_approved,  @p_active,    'Activate project',       'project.activate',    'funding_released', 10),
(@wf_project, @p_active,    @p_progress,  'Progress monitoring',    'project.report',      NULL, 20),
(@wf_project, @p_progress,  @p_finalsub,  'Submit final report',    'project.report',      'final_docs_complete', 30),
(@wf_project, @p_finalsub,  @p_finaleval, 'Final evaluation',       'project.finalize',    NULL, 40),
(@wf_project, @p_finaleval, @p_presch,    'Schedule presentation',  'presentation.manage', NULL, 50),
(@wf_project, @p_presch,    @p_presented, 'Evaluate presentation',  'presentation.evaluate', NULL, 60),
(@wf_project, @p_presented, @p_finalappr, 'Final approval',         'research.approve',    NULL, 70),
(@wf_project, @p_finalappr, @p_completed, 'Complete project',       'research.manage',     NULL, 80),
(@wf_project, @p_active,    @p_suspended, 'Suspend project',        'research.manage',     NULL, 900),
(@wf_project, @p_suspended, @p_active,    'Resume project',         'research.manage',     NULL, 901),
(@wf_project, @p_active,    @p_cancelled, 'Cancel project',         'research.manage',     NULL, 902),
(@wf_project, @p_active,    @p_terminated,'Terminate project',      'research.manage',     NULL, 903);

-- Transitions for ethics lifecycle
SET @e_draft    = (SELECT id FROM workflow_stages WHERE workflow_id=@wf_ethics AND stage_code='DRAFT');
SET @e_submitted= (SELECT id FROM workflow_stages WHERE workflow_id=@wf_ethics AND stage_code='SUBMITTED');
SET @e_screening= (SELECT id FROM workflow_stages WHERE workflow_id=@wf_ethics AND stage_code='UNDER_SCREENING');
SET @e_review   = (SELECT id FROM workflow_stages WHERE workflow_id=@wf_ethics AND stage_code='UNDER_REVIEW');
SET @e_revreq   = (SELECT id FROM workflow_stages WHERE workflow_id=@wf_ethics AND stage_code='REVISION_REQUIRED');
SET @e_approved = (SELECT id FROM workflow_stages WHERE workflow_id=@wf_ethics AND stage_code='APPROVED');
SET @e_apprcond = (SELECT id FROM workflow_stages WHERE workflow_id=@wf_ethics AND stage_code='APPROVED_WITH_CONDITIONS');
SET @e_rejected = (SELECT id FROM workflow_stages WHERE workflow_id=@wf_ethics AND stage_code='REJECTED');
SET @e_exempt   = (SELECT id FROM workflow_stages WHERE workflow_id=@wf_ethics AND stage_code='EXEMPT');

INSERT INTO workflow_transitions (workflow_id, from_stage_id, to_stage_id, transition_name, required_permission, condition_key, sort_order) VALUES
(@wf_ethics, @e_draft,     @e_submitted, 'Submit ethics application', 'ethics.submit',     'ethics_docs_complete', 10),
(@wf_ethics, @e_submitted, @e_screening, 'Ethics screening',          'ethics.screen',     NULL, 20),
(@wf_ethics, @e_screening, @e_review,    'Assign reviewers',          'ethics.manage',     NULL, 30),
(@wf_ethics, @e_screening, @e_exempt,    'Mark exempt',               'ethics.decide',     NULL, 31),
(@wf_ethics, @e_screening, @e_rejected,  'Screening rejection',       'ethics.decide',     NULL, 32),
(@wf_ethics, @e_review,    @e_approved,  'Approve',                   'ethics.decide',     'ethics_reviews_complete', 40),
(@wf_ethics, @e_review,    @e_apprcond,  'Approve with conditions',   'ethics.decide',     'ethics_reviews_complete', 41),
(@wf_ethics, @e_review,    @e_revreq,    'Request revision',          'ethics.decide',     NULL, 42),
(@wf_ethics, @e_review,    @e_rejected,  'Reject',                    'ethics.decide',     'ethics_reviews_complete', 43),
(@wf_ethics, @e_revreq,    @e_review,    'Resubmit for review',       'ethics.submit',     NULL, 50);

-- ---------------------------------------------------------------- settings (§103)
INSERT INTO system_settings (setting_key, setting_value, setting_group, value_type, description) VALUES
('university_name',            'Madda Walabu University',              'general',      'string',  'Institution display name'),
('university_short_name',      'MWU',                                  'general',      'string',  'Short abbreviation'),
('office_name',                'Research, Publication, Ethics and Extension Office', 'general', 'string', 'Office display name'),
('office_abbreviation',        'RPEE',                                 'general',      'string',  'Office abbreviation'),
('project_number_prefix',      'MWU-RPEE',                             'numbering',    'string',  'Prefix for generated numbers (§35)'),
('application_number_prefix',  'MWU-RPEE-APP',                         'numbering',    'string',  'Application number prefix'),
('call_number_prefix',         'MWU-RPEE-CALL',                        'numbering',    'string',  'Call number prefix'),
('ethics_number_prefix',       'MWU-RPEE-ETH',                         'numbering',    'string',  'Ethics application number prefix'),
('repository_number_prefix',   'MWU-RPEE-REP',                         'numbering',    'string',  'Repository record number prefix'),
('agreement_number_prefix',    'MWU-RPEE-AGR',                         'numbering',    'string',  'Research agreement number prefix (spec §13)'),
('session_timeout_minutes',    '30',                                   'security',     'integer', 'Idle session timeout in minutes'),
('max_login_attempts',         '5',                                    'security',     'integer', 'Failed logins before lockout'),
('lockout_minutes',            '15',                                   'security',     'integer', 'Lockout duration in minutes'),
('max_upload_size_mb',         '25',                                   'documents',    'integer', 'Maximum upload size in MB'),
('allowed_file_types',         'pdf,doc,docx,xls,xlsx,csv,ppt,pptx,txt,zip,rar,jpg,jpeg,png', 'documents', 'string', 'Comma-separated allowed extensions'),
('default_evaluation_days',    '14',                                   'deadlines',    'integer', 'POLICY DECISION DEL-1: default evaluation deadline (days)'),
('default_revision_days',      '14',                                   'deadlines',    'integer', 'POLICY DECISION DEL-1: default revision deadline (days)'),
('default_final_report_days',  '14',                                   'deadlines',    'integer', 'POLICY DECISION DEL-1: default final report deadline (days)'),
('ethics_expiry_reminder_1',   '30',                                   'ethics',       'integer', 'Days before expiry for first reminder (§33)'),
('ethics_expiry_reminder_2',   '7',                                    'ethics',       'integer', 'Days before expiry for second reminder (§33)'),
('escalation_after_days',      '7',                                    'notifications','integer', 'POLICY DECISION: overdue escalation period (§67)'),
('notification_email_enabled', '0',                                    'notifications','boolean', 'Email dispatch (disabled by default)'),
('repository_public_visibility','university_only',                     'repository',   'string',  'Default visibility for new repository records'),
('evaluation_mode_default',    'single_blind',                         'research',     'string',  'Default evaluation mode (§24)'),
('required_evaluators_default','1',                                    'research',     'integer', 'Default college evaluator count'),
('college_evaluators_default', '1',                                    'research',     'integer', 'Default college proposal evaluator count'),
('university_evaluators_default','1',                                  'research',     'integer', 'Default university evaluator count'),
('college_proposal_passing_score','50',                                 'research',     'decimal', 'College proposal passing threshold out of 100'),
('college_report_passing_score','50',                                   'research',     'decimal', 'College completed report passing threshold out of 100'),
('university_proposal_passing_score','50',                              'research',     'decimal', 'University proposal passing threshold out of 100'),
('university_report_passing_score','50',                                'research',     'decimal', 'University completed report passing threshold out of 100'),
('budget_currency',            'ETB',                                  'budget',       'string',  'Budget currency code'),
('fiscal_year_start_month',    '7',                                    'general',      'integer', 'Ethiopian fiscal year start month'),
('timezone',                   'Africa/Addis_Ababa',                   'general',      'string',  'Application timezone');

-- ---------------------------------------------------------------- number sequences
-- Seeded at year 0 with next_value=1; scripts/install.php seeds the current year.
INSERT INTO number_sequences (seq_key, seq_year, next_value) VALUES
('research_call',       0, 1),
('research_application',0, 1),
('research_project',    0, 1),
('ethics_application',  0, 1),
('repository_record',   0, 1),
('progress_report',     0, 1),
('integrity_case',      0, 1),
('appeal',              0, 1),
('research_agreement',  0, 1);
