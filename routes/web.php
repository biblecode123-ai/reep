<?php
declare(strict_types=1);

/**
 * Route table (spec §91). Maps `?action=` to [Controller::method, permission|null].
 * Every protected action declares its permission — enforced server-side in
 * index.php BEFORE the controller runs (spec §87, Rule 5).
 */

return [
    // Dashboard
    ''                    => [\App\Controllers\DashboardController::class, 'index', null],
    'researcher_dashboard' => [\App\Controllers\ResearcherDashboardController::class, 'index', 'researcher.view'],
    'coordinator_dashboard' => [\App\Controllers\CollegeCoordinatorController::class, 'index', 'research.view'],
    'coordinator_proposals' => [\App\Controllers\CollegeCoordinatorController::class, 'proposals', 'research.view'],
    'director_dashboard'   => [\App\Controllers\DirectorDashboardController::class, 'index', 'research.manage'],

    // Auth (login.php handles these too; kept for redirects)
    'forgot'              => [\App\Controllers\AuthController::class, 'showForgot', null],
    'send_reset'          => [\App\Controllers\AuthController::class, 'sendReset', null],
    'reset_password'      => [\App\Controllers\AuthController::class, 'showReset', null],
    'do_reset'            => [\App\Controllers\AuthController::class, 'doReset', null],

    // Research themes (§6)
    'themes'              => [\App\Controllers\ThemeController::class, 'index', 'research.manage'],
    'theme_form'          => [\App\Controllers\ThemeController::class, 'form', 'research.manage'],
    'theme_save'          => [\App\Controllers\ThemeController::class, 'save', 'research.manage'],
    'theme_status'        => [\App\Controllers\ThemeController::class, 'changeStatus', 'research.manage'],
    'theme_delete'        => [\App\Controllers\ThemeController::class, 'delete', 'research.manage'],

    // Research calls
    'calls'               => [\App\Controllers\CallController::class, 'index', 'research.view'],
    'calls_public'        => [\App\Controllers\CallController::class, 'indexPublic', null],
    'call_form'           => [\App\Controllers\CallController::class, 'form', 'research.manage'],
    'call_save'           => [\App\Controllers\CallController::class, 'save', 'research.manage'],
    'call_view'           => [\App\Controllers\CallController::class, 'view', 'research.view'],
    'call_config'         => [\App\Controllers\CallController::class, 'saveConfiguration', 'research.manage'],
    'call_publish'        => [\App\Controllers\CallController::class, 'publish', 'research.manage'],
    'call_status'         => [\App\Controllers\CallController::class, 'changeStatus', 'research.manage'],

    // Applications
    'applications'        => [\App\Controllers\ApplicationController::class, 'index', 'research.view'],
    'application_form'    => [\App\Controllers\ApplicationController::class, 'form', 'research.create'],
    'application_save'    => [\App\Controllers\ApplicationController::class, 'save', 'research.create'],
    'application_view'    => [\App\Controllers\ApplicationController::class, 'view', 'research.view'],
    'application_submit'  => [\App\Controllers\ApplicationController::class, 'submit', 'research.submit'],
    'application_withdraw' => [\App\Controllers\ApplicationController::class, 'withdraw', 'research.submit'],
    'application_upload'  => [\App\Controllers\ApplicationController::class, 'uploadDocument', 'document.upload'],
    'application_add_member' => [\App\Controllers\ApplicationController::class, 'apiAddMember', 'research.create'],
    'application_remove_member' => [\App\Controllers\ApplicationController::class, 'apiRemoveMember', 'research.create'],
    'application_respond_member' => [\App\Controllers\ApplicationController::class, 'apiRespondInvitation', 'research.create'],

    // Screening / evaluators
    'screening_receive'   => [\App\Controllers\ScreeningController::class, 'receive', 'research.screen'],
    'screening_decide'    => [\App\Controllers\ScreeningController::class, 'decide', 'research.screen'],
    'college_decision'    => [\App\Controllers\ScreeningController::class, 'collegeDecision', 'research.screen'],
    'assign_evaluators'   => [\App\Controllers\ScreeningController::class, 'assignEvaluatorForm', 'research.assign'],
    'assign_evaluator'    => [\App\Controllers\ScreeningController::class, 'assignEvaluator', 'research.assign'],
    'evaluator_respond'   => [\App\Controllers\ScreeningController::class, 'evaluatorRespond', 'research.evaluate'],
    'conflict_resolve'    => [\App\Controllers\ScreeningController::class, 'resolveConflict', 'research.assign'],
    'extend_deadline'     => [\App\Controllers\ScreeningController::class, 'extendDeadline', 'research.assign'],
    'reassign_evaluator'  => [\App\Controllers\ScreeningController::class, 'reassignEvaluator', 'research.assign'],
    'send_reminder'       => [\App\Controllers\ScreeningController::class, 'sendReminder', 'research.assign'],
    'my_evaluations'      => [\App\Controllers\ScreeningController::class, 'myEvaluations', 'research.evaluate'],
    'evaluator_create'    => [\App\Controllers\ScreeningController::class, 'createEvaluator', 'research.assign'],

    // Revision loop (§8, §25) — researcher responses + new versions
    'application_request_revision' => [\App\Controllers\ApplicationController::class, 'requestRevision', 'research.request_revision'],
    'application_respond_revision' => [\App\Controllers\ApplicationController::class, 'respondRevision', 'research.submit'],
    'application_submit_revision'  => [\App\Controllers\ApplicationController::class, 'submitRevision', 'research.submit'],

    // University research committee (§11–§12; Rules 8 & 9)
    'committee_queue'      => [\App\Controllers\CommitteeController::class, 'queue', 'research.review'],
    'committee_assign'     => [\App\Controllers\CommitteeController::class, 'assignForm', 'research.manage'],
    'committee_assign_save' => [\App\Controllers\CommitteeController::class, 'assign', 'research.manage'],
    'app_committee_decide' => [\App\Controllers\CommitteeController::class, 'decide', 'research.review'],

    // Budget allocation (§28 step 32; Rule 13)
    'application_budget'   => [\App\Controllers\ApplicationController::class, 'recordBudgetDecision', 'budget.approve'],

    // Research agreement (§13; Rules 10–13)
    'agreement_issue'      => [\App\Controllers\AgreementController::class, 'issue', 'research.manage'],
    'agreement_sign'       => [\App\Controllers\AgreementController::class, 'sign', 'research.submit'],

    // Institutional rubric versions — only the Research Director publishes policy rubrics.
    'rubrics'              => [\App\Controllers\RubricController::class, 'index', 'research.manage'],
    'rubric_save'          => [\App\Controllers\RubricController::class, 'save', 'research.manage'],

    // Evaluation
    'evaluation_form'     => [\App\Controllers\EvaluationController::class, 'form', 'research.evaluate'],
    'evaluation_save_draft' => [\App\Controllers\EvaluationController::class, 'saveDraft', 'research.evaluate'],
    'evaluation_submit'   => [\App\Controllers\EvaluationController::class, 'submit', 'research.evaluate'],
    'evaluation_view'     => [\App\Controllers\EvaluationController::class, 'view', 'research.view'],
    'univ_eval_assign_form' => [\App\Controllers\UniversityEvaluationController::class, 'assignEvaluatorsForm', 'research.assign'],
    'univ_eval_assign_save' => [\App\Controllers\UniversityEvaluationController::class, 'assignEvaluators', 'research.assign'],
    'univ_evaluation_form'  => [\App\Controllers\UniversityEvaluationController::class, 'universityEvaluationForm', 'research.evaluate'],
    'submit_university_evaluation' => [\App\Controllers\UniversityEvaluationController::class, 'submitUniversityEvaluation', 'research.evaluate'],


    // Ethics (§30–§34)
    'ethics'              => [\App\Controllers\EthicsController::class, 'index', 'ethics.view'],
    'ethics_create'       => [\App\Controllers\EthicsController::class, 'createFromApplication', 'ethics.submit'],
    'ethics_edit'         => [\App\Controllers\EthicsController::class, 'edit', 'ethics.submit'],
    'ethics_save'         => [\App\Controllers\EthicsController::class, 'save', 'ethics.submit'],
    'ethics_submit'       => [\App\Controllers\EthicsController::class, 'submit', 'ethics.submit'],
    'ethics_view'         => [\App\Controllers\EthicsController::class, 'view', 'ethics.view'],
    'ethics_screen'       => [\App\Controllers\EthicsController::class, 'screen', 'ethics.screen'],
    'ethics_assign'       => [\App\Controllers\EthicsController::class, 'assignForm', 'ethics.manage'],
    'ethics_assign_save'  => [\App\Controllers\EthicsController::class, 'assign', 'ethics.manage'],
    'ethics_my'           => [\App\Controllers\EthicsController::class, 'myReviews', 'ethics.review'],
    'ethics_review_form'  => [\App\Controllers\EthicsController::class, 'reviewForm', 'ethics.review'],
    'ethics_review_respond' => [\App\Controllers\EthicsController::class, 'reviewRespond', 'ethics.review'],
    'ethics_decide'       => [\App\Controllers\EthicsController::class, 'decide', 'ethics.decide'],
    'ethics_certificate'  => [\App\Controllers\EthicsController::class, 'certificate', 'ethics.decide'],
    'ethics_renew'        => [\App\Controllers\EthicsController::class, 'renew', 'ethics.decide'],
    'ethics_amendment'    => [\App\Controllers\EthicsController::class, 'amendmentRequest', 'ethics.submit'],
    'ethics_amendment_decide' => [\App\Controllers\EthicsController::class, 'amendmentDecide', 'ethics.decide'],
    'workflow_fire_ethics' => [\App\Controllers\WorkflowController::class, 'fireEthics', 'ethics.view'],

    // Projects — implementation (§35, §39–§44, §73)
    'projects'            => [\App\Controllers\ProjectController::class, 'index', 'project.view'],
    'project_view'        => [\App\Controllers\ProjectController::class, 'view', 'project.view'],
    'project_activate'    => [\App\Controllers\ProjectController::class, 'activate', 'project.activate'],
    'project_milestone_add' => [\App\Controllers\ProjectController::class, 'addMilestone', 'project.activate'],
    'project_milestone_update' => [\App\Controllers\ProjectController::class, 'updateMilestone', 'project.report'],
    'project_report_submit' => [\App\Controllers\MonthlyReportController::class, 'submit', 'project.report'],
    'monthly_report_submit' => [\App\Controllers\MonthlyReportController::class, 'submit', 'project.report'],
    'project_report_review' => [\App\Controllers\ProjectController::class, 'reviewReport', 'research.screen'],

    // Completed research report: independent college and university evaluation.
    'final_report_submit' => [\App\Controllers\FinalReportController::class, 'submit', 'project.report'],
    'final_report_view' => [\App\Controllers\FinalReportController::class, 'view', 'research.view'],
    'final_report_assign_college' => [\App\Controllers\FinalReportController::class, 'assignCollegeEvaluators', 'research.assign'],
    'final_report_assign_university' => [\App\Controllers\FinalReportController::class, 'assignUniversityEvaluators', 'research.assign'],
    'final_report_assignment_respond' => [\App\Controllers\FinalReportController::class, 'respond', 'research.evaluate'],
    'final_report_evaluation_form' => [\App\Controllers\FinalReportController::class, 'evaluationForm', 'research.evaluate'],
    'final_report_evaluation_save' => [\App\Controllers\FinalReportController::class, 'saveDraft', 'research.evaluate'],
    'final_report_evaluation_submit' => [\App\Controllers\FinalReportController::class, 'submitEvaluation', 'research.evaluate'],
    'final_report_forward' => [\App\Controllers\FinalReportController::class, 'forwardToUniversity', 'research.screen'],
    'final_report_decide' => [\App\Controllers\FinalReportController::class, 'finalDecision', 'research.manage'],
    'project_release_funds' => [\App\Controllers\ProjectController::class, 'releaseFunds', 'budget.release'],
    'project_extension_request' => [\App\Controllers\ProjectController::class, 'requestExtension', 'project.report'],
    'project_extension_decide' => [\App\Controllers\ProjectController::class, 'decideExtension', 'research.screen'],
    'project_action'      => [\App\Controllers\ProjectController::class, 'action', 'research.manage'],
    'workflow_fire_project' => [\App\Controllers\WorkflowController::class, 'fireProject', 'project.view'],

    // Final presentation (§45–§49)
    'presentations'        => [\App\Controllers\PresentationController::class, 'index', 'presentation.view'],
    'presentation_schedule' => [\App\Controllers\PresentationController::class, 'schedule', 'presentation.manage'],
    'presentation_panel'   => [\App\Controllers\PresentationController::class, 'panel', 'presentation.manage'],
    'presentation_eval'    => [\App\Controllers\PresentationController::class, 'evaluate', 'presentation.evaluate'],
    'presentation_decide'  => [\App\Controllers\PresentationController::class, 'decide', 'presentation.decide'],

    'publications'        => [\App\Controllers\PublicationController::class, 'index', 'publication.view'],
    'publication_create'  => [\App\Controllers\PublicationController::class, 'create', 'publication.manage'],
    'publication_decide'  => [\App\Controllers\PublicationController::class, 'approve', 'publication.publish'],

    // Reporting (§75)
    'reports'             => [\App\Controllers\ReportingController::class, 'index', 'report.view'],
    'reporting_export'    => [\App\Controllers\ReportingController::class, 'export', 'report.export'],

    // Institutional repository (§69-§74)
    'repository'          => [\App\Controllers\RepositoryController::class, 'index', 'repository.view'],
    'repository_view'     => [\App\Controllers\RepositoryController::class, 'view', 'repository.view'],
    'repository_form'     => [\App\Controllers\RepositoryController::class, 'form', 'repository.manage'],
    'repository_save'     => [\App\Controllers\RepositoryController::class, 'save', 'repository.manage'],
    'repository_approve'  => [\App\Controllers\RepositoryController::class, 'approve', 'repository.publish'],
    'repository_unpublish' => [\App\Controllers\RepositoryController::class, 'unpublish', 'repository.publish'],
    'repository_delete'   => [\App\Controllers\RepositoryController::class, 'delete', 'repository.manage'],

    // Documents
    'document_upload'     => [\App\Controllers\DocumentController::class, 'upload', 'document.upload'],
    'document_download'   => [\App\Controllers\DocumentController::class, 'download', null],
    'document_archive'    => [\App\Controllers\DocumentController::class, 'archive', 'document.archive'],

    // Researcher directory & profiles (§4, §5, §11) — policy layers inside the controller
    'researchers'         => [\App\Controllers\ResearcherController::class, 'index', 'researcher.view'],
    'researcher_view'     => [\App\Controllers\ResearcherController::class, 'show', 'researcher.view'],
    'researcher_photo'    => [\App\Controllers\ResearcherController::class, 'photo', null],
    'researcher_update_own' => [\App\Controllers\ResearcherController::class, 'updateOwn', 'researcher.view'],
    'researcher_photo_upload' => [\App\Controllers\ResearcherController::class, 'uploadPhoto', 'researcher.view'],
    'api_researchers'     => [\App\Controllers\ResearcherController::class, 'apiIndex', 'researcher.view'],
    'api_researcher'      => [\App\Controllers\ResearcherController::class, 'apiShow', 'researcher.view'],
    'api_researcher_profile' => [\App\Controllers\ResearcherController::class, 'apiOwn', 'researcher.view'],

    // Profile & notifications
    'profile'             => [\App\Controllers\ProfileController::class, 'show', null],
    'profile_researcher'  => [\App\Controllers\ProfileController::class, 'updateResearcher', null],
    'profile_password'    => [\App\Controllers\ProfileController::class, 'changePassword', null],
    'notifications'       => [\App\Controllers\ProfileController::class, 'notifications', null],
    'notification_read'   => [\App\Controllers\ProfileController::class, 'markRead', null],
    'notifications_read_all' => [\App\Controllers\ProfileController::class, 'markAllRead', null],

    // Workflow (auth-only here: each transition enforces its own permission
    // server-side inside WorkflowService — Rule 5 stays intact)
    'workflow_fire'       => [\App\Controllers\WorkflowController::class, 'fire', null],
    'workflows'           => [\App\Controllers\WorkflowController::class, 'viewWorkflows', 'workflow.manage'],
    'workflow_toggle'     => [\App\Controllers\WorkflowController::class, 'toggleTransition', 'workflow.manage'],

    // Administration
    'users'               => [\App\Controllers\AdminController::class, 'users', 'user.view'],
    'user_form'           => [\App\Controllers\AdminController::class, 'userForm', 'user.manage'],
    'user_save'           => [\App\Controllers\AdminController::class, 'userSave', 'user.manage'],
    'user_reset'          => [\App\Controllers\AdminController::class, 'userReset', 'user.manage'],
    'user_delete'         => [\App\Controllers\AdminController::class, 'userDelete', 'user.manage'],
    'user_destroy'        => [\App\Controllers\AdminController::class, 'userDestroy', 'user.manage'],
    'user_roles'          => [\App\Controllers\AdminController::class, 'userRoles', 'user.roles'],
    'user_roles_save'     => [\App\Controllers\AdminController::class, 'userRolesSave', 'user.roles'],
    'api_departments'     => [\App\Controllers\AdminController::class, 'apiDepartments', 'user.view'],
    'colleges'            => [\App\Controllers\AdminController::class, 'colleges', 'org.view'],
    'college_save'        => [\App\Controllers\AdminController::class, 'collegeSave', 'org.manage'],
    'departments'         => [\App\Controllers\AdminController::class, 'departments', 'org.view'],
    'department_save'     => [\App\Controllers\AdminController::class, 'departmentSave', 'org.manage'],
    'committees'          => [\App\Controllers\AdminController::class, 'committees', 'org.view'],
    'committee_save'      => [\App\Controllers\AdminController::class, 'committeeSave', 'committee.manage'],
    'committee_members'   => [\App\Controllers\AdminController::class, 'committeeMembers', 'org.view'],
    'committee_member_save' => [\App\Controllers\AdminController::class, 'committeeMemberSave', 'committee.manage'],
    'committee_member_remove' => [\App\Controllers\AdminController::class, 'committeeMemberRemove', 'committee.manage'],
    'classifications'     => [\App\Controllers\AdminController::class, 'classifications', 'classification.manage'],
    'classification_save' => [\App\Controllers\AdminController::class, 'classificationSave', 'classification.manage'],
    'settings'            => [\App\Controllers\AdminController::class, 'settings', 'settings.manage'],
    'settings_save'       => [\App\Controllers\AdminController::class, 'settingsSave', 'settings.manage'],
    'audit'               => [\App\Controllers\AdminController::class, 'auditLogs', 'audit.view'],
    'health'              => [\App\Controllers\AdminHealthController::class, 'index', 'settings.manage'],
];

