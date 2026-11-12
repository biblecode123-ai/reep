-- Generated from seed.sql by make_sqlite.php — do not edit by hand.

PRAGMA foreign_keys = ON;

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

-- Technical administrator has no academic workflow or decision permissions.
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
  ON p.name IN ('user.view','user.manage','org.view','audit.view')
WHERE r.name = 'system_admin';

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

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
  ON p.name IN (
    'research.view','research.review','research.approve','research.reject','research.request_revision',
    'ethics.view','publication.view','extension.view','budget.view',
    'presentation.view','document.view','document.download','repository.view','project.view','reports.view'
  ) WHERE r.name = 'univ_committee';

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
  ON p.name IN (
    'research.view','research.screen','research.assign','research.evaluate','research.review','research.approve','research.reject','research.request_revision',
    'ethics.view','publication.view','extension.view','budget.view',
    'presentation.view','presentation.manage','document.view','document.upload','document.download',
    'project.view','user.view','researcher.view','org.view','reports.view'
  ) WHERE r.name = 'college_coordinator';

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
  ON p.name IN (
    'research.view','research.screen','ethics.view','publication.view','extension.view','budget.view',
    'presentation.view','document.view','document.download','project.view','user.view','org.view','reports.view'
  ) WHERE r.name = 'dept_coordinator';

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
  ON p.name IN (
    'research.view','research.create','research.edit','research.submit','research.evaluate',
    'ethics.view','ethics.submit','publication.view','publication.create','publication.edit',
    'extension.view','extension.create',
    'document.view','document.upload','document.download',
    'repository.view','project.view','project.report','researcher.view'
  ) WHERE r.name = 'researcher';

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

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
  ON p.name IN ('research.view','publication.view','extension.view','document.view','document.download','repository.view','project.view','project.report')
  WHERE r.name = 'co_investigator';

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
  ON p.name IN ('research.view','research.evaluate','document.view','document.download')
  WHERE r.name = 'evaluator';

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
  ON p.name IN ('ethics.view','ethics.review','research.view','document.view','document.download')
  WHERE r.name IN ('ethics_member','ethics_reviewer');

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
  ON p.name IN ('publication.view','publication.create','publication.edit','publication.review','publication.approve','publication.manage','research.view','document.view','document.download','repository.view')
  WHERE r.name = 'publication_officer';

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
  ON p.name IN ('extension.view','extension.create','extension.review','extension.approve','extension.manage','research.view','document.view','document.download')
  WHERE r.name = 'extension_officer';

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
  ON p.name IN ('extension.view','extension.review','document.view','document.download')
  WHERE r.name = 'extension_evaluator';

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
  ON p.name IN ('presentation.view','presentation.evaluate','research.view','document.view','document.download')
  WHERE r.name = 'panel_member';

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
  ON p.name IN ('repository.view','repository.publish','repository.archive','repository.manage','research.view','project.view','document.view','document.download','document.archive','document.upload')
  WHERE r.name = 'repository_manager';

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
  ON p.name IN ('budget.view','budget.approve','budget.release','budget.modify','project.view','document.view','document.download','reports.view')
  WHERE r.name = 'finance_officer';

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
  ON p.name IN ('research.view','ethics.view','publication.view','extension.view','budget.view','document.view','document.download','repository.view','project.view','reports.view','audit.view','user.view','org.view')
  WHERE r.name = 'readonly_mgmt';

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

INSERT INTO workflows (name, entity_type, description) VALUES
('Research Application Lifecycle','research_application','Full research application state machine (spec §18)'),
('Research Project Lifecycle','research_project','Implementation to completion state machine'),
('Ethics Review Workflow','ethics_application','Screening, review, decision (§31)');

INSERT INTO workflow_stages (workflow_id, stage_code, stage_name, stage_type, is_terminal_exception, sort_order) VALUES
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1),'DRAFT','Draft','start',0,10),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1),'SUBMITTED','Submitted','normal',0,20),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1),'COLLEGE_RECEIVED','College Received','normal',0,30),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1),'UNDER_COLLEGE_SCREENING','Under College Screening','normal',0,40),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1),'EVALUATOR_ASSIGNMENT_PENDING','Evaluator Assignment Pending','normal',0,50),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1),'EVALUATOR_ASSIGNED','Evaluator Assigned','normal',0,60),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1),'EVALUATOR_ACCEPTED','Evaluator Accepted','normal',0,70),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1),'UNDER_EVALUATION','Under Evaluation','normal',0,80),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1),'EVALUATION_COMPLETED','Evaluation Completed','normal',0,90),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1),'UNIV_EVAL_ASSIGNMENT_PENDING','Univ Evaluator Assignment Pending','normal',0,92),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1),'UNIV_EVAL_ASSIGNED','Univ Evaluator Assigned','normal',0,94),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1),'UNIV_EVAL_ACCEPTED','Univ Evaluator Accepted','normal',0,96),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1),'UNIV_UNDER_EVALUATION','Univ Under Evaluation','normal',0,98),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1),'UNIV_EVALUATION_COMPLETED','Univ Evaluation Completed','normal',0,99),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1),'REVISION_REQUIRED','Revision Required','normal',0,100),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1),'REVISION_SUBMITTED','Revision Submitted','normal',0,110),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1),'RE_EVALUATION','Re-evaluation','normal',0,120),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1),'COLLEGE_REVIEW','College Review','normal',0,130),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1),'COLLEGE_APPROVED','College Approved','normal',0,140),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1),'SUBMITTED_TO_UNIVERSITY','Submitted to University','normal',0,150),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1),'UNIVERSITY_REVIEW','University Review','normal',0,160),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1),'UNIVERSITY_COMMITTEE_REVIEW','University Committee Review','normal',0,165),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1),'ETHICS_REVIEW','Ethics Review','normal',0,170),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1),'APPROVED','Approved','normal',0,180),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1),'BUDGET_REVIEW','Budget Review','normal',0,190),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1),'BUDGET_APPROVED','Budget Approved','normal',0,200),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1),'FUNDED','Funded','normal',0,210),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1),'ACTIVE','Active','normal',0,220),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1),'PROGRESS_MONITORING','Progress Monitoring','normal',0,230),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1),'FINAL_REPORT_SUBMITTED','Final Report Submitted','normal',0,240),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1),'FINAL_EVALUATION','Final Evaluation','normal',0,250),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1),'PRESENTATION_SCHEDULED','Presentation Scheduled','normal',0,260),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1),'PRESENTED','Presented','normal',0,270),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1),'PRESENTATION_EVALUATION','Presentation Evaluation','normal',0,280),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1),'FINAL_APPROVAL','Final Approval','normal',0,290),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1),'COMPLETED','Completed','terminal',0,1000),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1),'REPOSITORY','Repository','terminal',0,1010),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1),'ARCHIVED','Archived','terminal',0,1020),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1),'REJECTED','Rejected','terminal',1,1100),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1),'WITHDRAWN','Withdrawn','terminal',1,1101),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1),'CANCELLED','Cancelled','terminal',1,1102),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1),'SUSPENDED','Suspended','normal',0,1103),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1),'TERMINATED','Terminated','terminal',1,1104),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1),'EXPIRED','Expired','terminal',1,1105);

INSERT INTO workflow_stages (workflow_id, stage_code, stage_name, stage_type, is_terminal_exception, sort_order) VALUES
((SELECT id FROM workflows WHERE entity_type='research_project' LIMIT 1),'APPROVED','Approved (awaiting activation)','start',0,10),
((SELECT id FROM workflows WHERE entity_type='research_project' LIMIT 1),'ACTIVE','Active','normal',0,20),
((SELECT id FROM workflows WHERE entity_type='research_project' LIMIT 1),'PROGRESS_MONITORING','Progress Monitoring','normal',0,30),
((SELECT id FROM workflows WHERE entity_type='research_project' LIMIT 1),'FINAL_REPORT_SUBMITTED','Final Report Submitted','normal',0,40),
((SELECT id FROM workflows WHERE entity_type='research_project' LIMIT 1),'FINAL_EVALUATION','Final Evaluation','normal',0,50),
((SELECT id FROM workflows WHERE entity_type='research_project' LIMIT 1),'PRESENTATION_SCHEDULED','Presentation Scheduled','normal',0,60),
((SELECT id FROM workflows WHERE entity_type='research_project' LIMIT 1),'PRESENTATION_EVALUATION','Presentation Evaluation','normal',0,70),
((SELECT id FROM workflows WHERE entity_type='research_project' LIMIT 1),'FINAL_APPROVAL','Final Approval','normal',0,80),
((SELECT id FROM workflows WHERE entity_type='research_project' LIMIT 1),'COMPLETED','Completed','terminal',0,1000),
((SELECT id FROM workflows WHERE entity_type='research_project' LIMIT 1),'SUSPENDED','Suspended','normal',0,1100),
((SELECT id FROM workflows WHERE entity_type='research_project' LIMIT 1),'TERMINATED','Terminated','terminal',1,1101),
((SELECT id FROM workflows WHERE entity_type='research_project' LIMIT 1),'CANCELLED','Cancelled','terminal',1,1102);

INSERT INTO workflow_stages (workflow_id, stage_code, stage_name, stage_type, is_terminal_exception, sort_order) VALUES
((SELECT id FROM workflows WHERE entity_type='ethics_application' LIMIT 1),'DRAFT','Draft','start',0,10),
((SELECT id FROM workflows WHERE entity_type='ethics_application' LIMIT 1),'SUBMITTED','Submitted','normal',0,20),
((SELECT id FROM workflows WHERE entity_type='ethics_application' LIMIT 1),'UNDER_SCREENING','Under Screening','normal',0,30),
((SELECT id FROM workflows WHERE entity_type='ethics_application' LIMIT 1),'UNDER_REVIEW','Under Review','normal',0,40),
((SELECT id FROM workflows WHERE entity_type='ethics_application' LIMIT 1),'REVISION_REQUIRED','Revision Required','normal',0,50),
((SELECT id FROM workflows WHERE entity_type='ethics_application' LIMIT 1),'APPROVED','Approved','terminal',0,1000),
((SELECT id FROM workflows WHERE entity_type='ethics_application' LIMIT 1),'APPROVED_WITH_CONDITIONS','Approved with Conditions','terminal',0,1001),
((SELECT id FROM workflows WHERE entity_type='ethics_application' LIMIT 1),'REJECTED','Rejected','terminal',1,1100),
((SELECT id FROM workflows WHERE entity_type='ethics_application' LIMIT 1),'EXEMPT','Exempt','terminal',0,1002);

INSERT INTO workflow_transitions (workflow_id, from_stage_id, to_stage_id, transition_name, required_permission, condition_key, sort_order) VALUES
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='DRAFT'),     (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='SUBMITTED'),  'Submit application',           'research.submit',            'documents_complete', 10),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='SUBMITTED'), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='COLLEGE_RECEIVED'),   'College receives',             'research.screen',            NULL, 20),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='COLLEGE_RECEIVED'),  (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='UNDER_COLLEGE_SCREENING'),  'Start screening',              'research.screen',            NULL, 30),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='UNDER_COLLEGE_SCREENING'), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='EVALUATOR_ASSIGNMENT_PENDING'), 'Forward to evaluation',        'research.screen',            'documents_complete', 40),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='UNDER_COLLEGE_SCREENING'), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='REVISION_REQUIRED'),     'Return for correction',        'research.screen',            NULL, 41),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='EVALUATOR_ASSIGNMENT_PENDING'),(SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='EVALUATOR_ASSIGNED'),   'Assign evaluators',            'research.assign',            NULL, 50),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='EVALUATOR_ASSIGNED'),  (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='EVALUATOR_ACCEPTED'),   'Evaluator accepts',            NULL,                         'coi_cleared', 60),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='EVALUATOR_ACCEPTED'),  (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='UNDER_EVALUATION'),  'Start evaluation',             NULL,                         NULL, 70),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='UNDER_EVALUATION'), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='EVALUATION_COMPLETED'),   'Submit evaluation',            'research.evaluate',          NULL, 80),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='EVALUATION_COMPLETED'),  (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='REVISION_REQUIRED'),     'Request revision',             'research.request_revision',  NULL, 90),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='EVALUATION_COMPLETED'),  (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='REJECTED'),             'Reject at college',             'research.reject',             NULL, 94),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='EVALUATION_COMPLETED'),  (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='UNIV_EVAL_ASSIGNMENT_PENDING'), 'Send to university evaluation', 'research.screen',     'evaluations_passed', 95),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='UNIV_EVAL_ASSIGNMENT_PENDING'), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='UNIV_EVAL_ASSIGNED'), 'Assign university evaluators', 'research.assign',   NULL, 96),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='UNIV_EVAL_ASSIGNED'),    (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='UNIV_EVAL_ACCEPTED'), 'Univ evaluator accepts', NULL,                       NULL, 97),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='UNIV_EVAL_ACCEPTED'),    (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='UNIV_UNDER_EVALUATION'), 'Start university evaluation', NULL,                 NULL, 98),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='UNIV_UNDER_EVALUATION'),   (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='UNIV_EVALUATION_COMPLETED'), 'Submit university evaluation', 'research.evaluate',  NULL, 99),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='REVISION_REQUIRED'),    (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='REVISION_SUBMITTED'),     'Submit revision',              'research.submit',            NULL, 100),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='REVISION_SUBMITTED'),    (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='RE_EVALUATION'),     'Re-evaluate',                  'research.evaluate',          NULL, 110),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='REVISION_SUBMITTED'),    (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='UNDER_COLLEGE_SCREENING'),  'Return for screening',         'research.screen',            NULL, 111),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='RE_EVALUATION'),    (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='COLLEGE_REVIEW'),     'Send to college review',       'research.screen',            'evaluations_complete', 120),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='UNIV_EVALUATION_COMPLETED'), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='COLLEGE_REVIEW'), 'Send to college review',       'research.screen',            'univ_evaluations_complete', 122),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='COLLEGE_REVIEW'),    (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='COLLEGE_APPROVED'),    'College approves',             'research.approve',           NULL, 130),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='COLLEGE_REVIEW'),    (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='REVISION_REQUIRED'),     'Request revision',             'research.request_revision',  NULL, 131),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='COLLEGE_REVIEW'),    (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='REJECTED'),   'College rejects',              'research.reject',            NULL, 132),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='COLLEGE_APPROVED'),   (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='SUBMITTED_TO_UNIVERSITY'),     'Submit to university',         'research.screen',            NULL, 140),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='SUBMITTED_TO_UNIVERSITY'),    (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='UNIVERSITY_REVIEW'),    'University review starts',     'research.review',            NULL, 150),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='UNIVERSITY_REVIEW'),   (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='ETHICS_REVIEW'),     'Route to ethics',              'research.review',            'ethics_required', 160),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='UNIVERSITY_REVIEW'),   (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='UNIVERSITY_COMMITTEE_REVIEW'),    'Assign to research committee','research.manage',            'committee_ready', 161),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='UNIVERSITY_REVIEW'),   (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='REVISION_REQUIRED'),     'Request revision',             'research.request_revision',  NULL, 162),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='UNIVERSITY_REVIEW'),   (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='REJECTED'),   'University rejects',           'research.reject',            NULL, 163),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='UNIVERSITY_COMMITTEE_REVIEW'),   (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='APPROVED'),   'Approve (directorate)',        'research.manage',            'committee_approved', 164),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='UNIVERSITY_COMMITTEE_REVIEW'),   (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='REJECTED'),   'Reject (directorate)',         'research.manage',            'committee_rejected', 165),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='UNIVERSITY_COMMITTEE_REVIEW'),   (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='REVISION_REQUIRED'),     'Request revision (directorate)','research.manage',           'committee_wants_revision', 166),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='ETHICS_REVIEW'),    (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='UNIVERSITY_REVIEW'),    'Ethics cleared',               'ethics.decide',              'ethics_cleared', 170),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='APPROVED'),  (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='BUDGET_REVIEW'),     'Budget review starts',         'budget.view',                'budget_required', 180),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='BUDGET_REVIEW'),    (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='BUDGET_APPROVED'),    'Budget approved',              'budget.approve',             'budget_recorded', 190),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='BUDGET_APPROVED'),   (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='FUNDED'),     'Release funds',                'budget.release',             NULL, 200),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='FUNDED'),    (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='ACTIVE'),     'Activate project',             'project.activate',           'agreement_signed', 210),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='ACTIVE'),    (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='PROGRESS_MONITORING'),   'Submit progress report',       'project.report',             NULL, 220),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='PROGRESS_MONITORING'),  (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='FINAL_REPORT_SUBMITTED'),   'Submit final report',          'project.report',             'final_docs_complete', 230),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='FINAL_REPORT_SUBMITTED'),  (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='FINAL_EVALUATION'),   'Start final evaluation',       'project.finalize',           NULL, 240),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='FINAL_EVALUATION'),  (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='PRESENTATION_SCHEDULED'), 'Schedule presentation',        'presentation.manage',        NULL, 250),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='PRESENTATION_SCHEDULED'),(SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='PRESENTED'),  'Mark presented',               'presentation.manage',        NULL, 260),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='PRESENTED'), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='PRESENTATION_EVALUATION'),   'Evaluate presentation',        'presentation.evaluate',      NULL, 270),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='PRESENTATION_EVALUATION'),  (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='FINAL_APPROVAL'),  'Final approval',               'research.approve',           NULL, 280),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='FINAL_APPROVAL'), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='COMPLETED'),  'Mark completed',               'research.manage',            NULL, 290),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='COMPLETED'), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='REPOSITORY'), 'Publish to repository',        'repository.publish',         NULL, 300),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='REPOSITORY'),(SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='ARCHIVED'),   'Archive',                      'repository.archive',         NULL, 310),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='SUBMITTED'), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='WITHDRAWN'),  'Withdraw',                     'research.submit',            NULL, 900),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='UNDER_COLLEGE_SCREENING'), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='REJECTED'),   'Reject as ineligible',         'research.reject',            NULL, 901),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='EVALUATOR_ASSIGNED'),  (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='REJECTED'),   'Reject during assignment',     'research.reject',            NULL, 902),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='UNDER_EVALUATION'), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='REJECTED'),   'Reject after evaluation',      'research.reject',            NULL, 903),
((SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='BUDGET_REVIEW'),    (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_application' LIMIT 1) AND stage_code='REJECTED'),   'Reject after budget',          'budget.approve',             NULL, 904);

INSERT INTO workflow_transitions (workflow_id, from_stage_id, to_stage_id, transition_name, required_permission, condition_key, sort_order) VALUES
((SELECT id FROM workflows WHERE entity_type='research_project' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_project' LIMIT 1) AND stage_code='APPROVED'),  (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_project' LIMIT 1) AND stage_code='ACTIVE'),    'Activate project',       'project.activate',    'funding_released', 10),
((SELECT id FROM workflows WHERE entity_type='research_project' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_project' LIMIT 1) AND stage_code='ACTIVE'),    (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_project' LIMIT 1) AND stage_code='PROGRESS_MONITORING'),  'Progress monitoring',    'project.report',      NULL, 20),
((SELECT id FROM workflows WHERE entity_type='research_project' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_project' LIMIT 1) AND stage_code='PROGRESS_MONITORING'),  (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_project' LIMIT 1) AND stage_code='FINAL_REPORT_SUBMITTED'),  'Submit final report',    'project.report',      'final_docs_complete', 30),
((SELECT id FROM workflows WHERE entity_type='research_project' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_project' LIMIT 1) AND stage_code='FINAL_REPORT_SUBMITTED'),  (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_project' LIMIT 1) AND stage_code='FINAL_EVALUATION'), 'Final evaluation',       'project.finalize',    NULL, 40),
((SELECT id FROM workflows WHERE entity_type='research_project' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_project' LIMIT 1) AND stage_code='FINAL_EVALUATION'), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_project' LIMIT 1) AND stage_code='PRESENTATION_SCHEDULED'),    'Schedule presentation',  'presentation.manage', NULL, 50),
((SELECT id FROM workflows WHERE entity_type='research_project' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_project' LIMIT 1) AND stage_code='PRESENTATION_SCHEDULED'),    (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_project' LIMIT 1) AND stage_code='PRESENTATION_EVALUATION'), 'Evaluate presentation',  'presentation.evaluate', NULL, 60),
((SELECT id FROM workflows WHERE entity_type='research_project' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_project' LIMIT 1) AND stage_code='PRESENTATION_EVALUATION'), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_project' LIMIT 1) AND stage_code='FINAL_APPROVAL'), 'Final approval',         'research.approve',    NULL, 70),
((SELECT id FROM workflows WHERE entity_type='research_project' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_project' LIMIT 1) AND stage_code='FINAL_APPROVAL'), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_project' LIMIT 1) AND stage_code='COMPLETED'), 'Complete project',       'research.manage',     NULL, 80),
((SELECT id FROM workflows WHERE entity_type='research_project' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_project' LIMIT 1) AND stage_code='ACTIVE'),    (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_project' LIMIT 1) AND stage_code='SUSPENDED'), 'Suspend project',        'research.manage',     NULL, 900),
((SELECT id FROM workflows WHERE entity_type='research_project' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_project' LIMIT 1) AND stage_code='SUSPENDED'), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_project' LIMIT 1) AND stage_code='ACTIVE'),    'Resume project',         'research.manage',     NULL, 901),
((SELECT id FROM workflows WHERE entity_type='research_project' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_project' LIMIT 1) AND stage_code='ACTIVE'),    (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_project' LIMIT 1) AND stage_code='CANCELLED'), 'Cancel project',         'research.manage',     NULL, 902),
((SELECT id FROM workflows WHERE entity_type='research_project' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_project' LIMIT 1) AND stage_code='ACTIVE'),    (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='research_project' LIMIT 1) AND stage_code='TERMINATED'),'Terminate project',      'research.manage',     NULL, 903);

INSERT INTO workflow_transitions (workflow_id, from_stage_id, to_stage_id, transition_name, required_permission, condition_key, sort_order) VALUES
((SELECT id FROM workflows WHERE entity_type='ethics_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='ethics_application' LIMIT 1) AND stage_code='DRAFT'),     (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='ethics_application' LIMIT 1) AND stage_code='SUBMITTED'), 'Submit ethics application', 'ethics.submit',     'ethics_docs_complete', 10),
((SELECT id FROM workflows WHERE entity_type='ethics_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='ethics_application' LIMIT 1) AND stage_code='SUBMITTED'), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='ethics_application' LIMIT 1) AND stage_code='UNDER_SCREENING'), 'Ethics screening',          'ethics.screen',     NULL, 20),
((SELECT id FROM workflows WHERE entity_type='ethics_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='ethics_application' LIMIT 1) AND stage_code='UNDER_SCREENING'), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='ethics_application' LIMIT 1) AND stage_code='UNDER_REVIEW'),    'Assign reviewers',          'ethics.manage',     NULL, 30),
((SELECT id FROM workflows WHERE entity_type='ethics_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='ethics_application' LIMIT 1) AND stage_code='UNDER_SCREENING'), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='ethics_application' LIMIT 1) AND stage_code='EXEMPT'),    'Mark exempt',               'ethics.decide',     NULL, 31),
((SELECT id FROM workflows WHERE entity_type='ethics_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='ethics_application' LIMIT 1) AND stage_code='UNDER_SCREENING'), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='ethics_application' LIMIT 1) AND stage_code='REJECTED'),  'Screening rejection',       'ethics.decide',     NULL, 32),
((SELECT id FROM workflows WHERE entity_type='ethics_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='ethics_application' LIMIT 1) AND stage_code='UNDER_REVIEW'),    (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='ethics_application' LIMIT 1) AND stage_code='APPROVED'),  'Approve',                   'ethics.decide',     'ethics_reviews_complete', 40),
((SELECT id FROM workflows WHERE entity_type='ethics_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='ethics_application' LIMIT 1) AND stage_code='UNDER_REVIEW'),    (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='ethics_application' LIMIT 1) AND stage_code='APPROVED_WITH_CONDITIONS'),  'Approve with conditions',   'ethics.decide',     'ethics_reviews_complete', 41),
((SELECT id FROM workflows WHERE entity_type='ethics_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='ethics_application' LIMIT 1) AND stage_code='UNDER_REVIEW'),    (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='ethics_application' LIMIT 1) AND stage_code='REVISION_REQUIRED'),    'Request revision',          'ethics.decide',     NULL, 42),
((SELECT id FROM workflows WHERE entity_type='ethics_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='ethics_application' LIMIT 1) AND stage_code='UNDER_REVIEW'),    (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='ethics_application' LIMIT 1) AND stage_code='REJECTED'),  'Reject',                    'ethics.decide',     'ethics_reviews_complete', 43),
((SELECT id FROM workflows WHERE entity_type='ethics_application' LIMIT 1), (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='ethics_application' LIMIT 1) AND stage_code='REVISION_REQUIRED'),    (SELECT id FROM workflow_stages WHERE workflow_id=(SELECT id FROM workflows WHERE entity_type='ethics_application' LIMIT 1) AND stage_code='UNDER_REVIEW'),    'Resubmit for review',       'ethics.submit',     NULL, 50);

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

