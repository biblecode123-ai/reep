<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Gate;
use App\Core\Session;
use App\Models\ApplicationModel;
use App\Models\CallModel;
use App\Models\EvaluationModel;
use App\Models\RubricModel;
use App\Services\RubricScoringService;
use App\Services\WorkflowService;

/**
 * Proposal evaluation by assigned evaluators (spec §22–§24).
 */
final class EvaluationController
{
    public static function form(): void
    {
        Gate::authorize('research.evaluate');
        $appId = (int) ($_GET['application_id'] ?? 0);
        $app = ApplicationModel::find($appId);
        if ($app === null) {
            Session::flash('error', 'Application not found.');
            redirect('index.php?action=my_evaluations');
        }
        $assignment = Database::one(
            'SELECT * FROM evaluator_assignments WHERE application_id = ? AND evaluator_user_id = ? AND assignment_level = "college" AND is_active = 1 AND status NOT IN ("conflict_declared","declined","cancelled")',
            [$appId, Auth::id()]
        );
        if ($assignment === null) {
            Session::flash('error', 'You are not assigned to evaluate this application.');
            redirect('index.php?action=my_evaluations');
        }
        if (!in_array($assignment['status'], ['accepted', 'conflict_declared'], true) && $assignment['status'] !== 'assigned') {
            Session::flash('error', 'This assignment is ' . $assignment['status'] . '.');
            redirect('index.php?action=my_evaluations');
        }

        // Check deadline - if overdue and policy prohibits, block access
        if ($assignment['deadline'] && strtotime($assignment['deadline']) < time()) {
            // Allow viewing but warn - policy can be configured to block
            Session::flash('warning', 'This evaluation is overdue by ' . floor((time() - strtotime($assignment['deadline'])) / 86400) . ' days.');
        }

        $round = EvaluationModel::nextRound($appId, Auth::id());
        $draft = EvaluationModel::findDraft($appId, Auth::id(), $round);
        $rubric = !empty($draft['rubric_template_id'])
            ? RubricModel::findById((int) $draft['rubric_template_id'])
            : RubricModel::activeFor('college_proposal');
        if ($rubric === null) {
            Session::flash('error', 'The Research Director must publish a complete College Proposal rubric before evaluation can begin.');
            redirect('index.php?action=my_evaluations');
        }
        $criteria = $rubric['criteria'];

        // Load proposal team (co-investigators) for display / blind review check.
        // Uses application_team_members — the proposal-stage team table (§26);
        // research_project_members only exists after a project is created.
        $teamMembers = Database::all(
            'SELECT atm.user_id, u.full_name, atm.role
             FROM application_team_members atm
             JOIN users u ON u.id = atm.user_id
             WHERE atm.application_id = ? AND atm.status <> "declined"',
            [$appId]
        );

        // Check if blind review is required
        $evaluationMode = Database::scalar('SELECT evaluation_mode FROM research_calls WHERE id = ?', [(int) $app['call_id']]);
        $isBlind = $evaluationMode === 'double_blind' || $evaluationMode === 'single_blind';

        $docHead = Database::all(
            'SELECT d.*, dt.name AS type_name FROM documents d JOIN document_types dt ON dt.id = d.document_type_id
             WHERE d.entity_type = "research_application" AND d.entity_id = ? AND d.status = "active"',
            [$appId]
        );

        // Get work plan
        $workPlan = Database::all(
            'SELECT * FROM research_work_plan_items WHERE application_id = ? ORDER BY sort_order',
            [$appId]
        );

        // Load draft scores and comments if draft exists
        $draftData = [];
        if ($draft) {
            $draftScores = RubricScoringService::scoresForEvaluation((int) $draft['id']);
            foreach ($draftScores as $indicatorId => $scoreData) {
                $draftData['scores'][$indicatorId] = $scoreData['score'];
                $draftData['score_comments'][$indicatorId] = $scoreData['comment'];
            }

            // Parse structured feedback from recommendations field
            $recommendations = $draft['recommendations'] ?? '';
            if ($recommendations) {
                if (strpos($recommendations, 'Methodology Feedback:') !== false) {
                    preg_match('/Methodology Feedback:\s*(.*?)(?=\n\n|\nEthics|$)/s', $recommendations, $matches);
                    $draftData['methodology_feedback'] = trim($matches[1] ?? '');
                }
                if (strpos($recommendations, 'Ethics and Feasibility:') !== false) {
                    preg_match('/Ethics and Feasibility:\s*(.*?)(?=\n\n|\nRecommendations|$)/s', $recommendations, $matches);
                    $draftData['ethics_feasibility'] = trim($matches[1] ?? '');
                }
                if (strpos($recommendations, 'Recommendations for Improvement:') !== false) {
                    preg_match('/Recommendations for Improvement:\s*(.*)/s', $recommendations, $matches);
                    $draftData['improvement_recommendations'] = trim($matches[1] ?? '');
                }
            }

            // Merge draft main fields
            $draftData['strengths'] = $draft['strengths'] ?? '';
            $draftData['weaknesses'] = $draft['weaknesses'] ?? '';
            $draftData['overall_comment'] = $draft['overall_comment'] ?? '';
            $draftData['recommendation'] = $draft['recommendation'] ?? '';
        }

        include APP_PATH . '/views/research/evaluation_form.php';
    }

    public static function saveDraft(): void
    {
        Gate::authorize('research.evaluate');
        Csrf::verifyRequest();
        $appId = (int) ($_POST['application_id'] ?? 0);
        $app = ApplicationModel::find($appId);
        if ($app === null) {
            echo json_encode(['ok' => false, 'message' => 'Application not found.']);
            exit;
        }
        $assignment = Database::one(
            'SELECT * FROM evaluator_assignments
             WHERE application_id = ? AND evaluator_user_id = ? AND assignment_level = "college"
               AND is_active = 1 AND status IN ("assigned","accepted")',
            [$appId, Auth::id()]
        );
        if ($assignment === null) {
            echo json_encode(['ok' => false, 'message' => 'No active college evaluation assignment found.']);
            exit;
        }

        $round = EvaluationModel::nextRound($appId, Auth::id());
        $draft = EvaluationModel::findDraft($appId, Auth::id(), $round);
        $rubric = !empty($draft['rubric_template_id'])
            ? RubricModel::findById((int) $draft['rubric_template_id'])
            : RubricModel::activeFor('college_proposal');
        if ($rubric === null) {
            echo json_encode(['ok' => false, 'message' => 'No published College Proposal rubric is available.']);
            exit;
        }
        $validated = RubricScoringService::validate($rubric, $_POST, false);
        if ($validated['errors'] !== []) {
            echo json_encode(['ok' => false, 'message' => implode(' ', $validated['errors'])]);
            exit;
        }

        $methodologyFeedback = trim((string) ($_POST['methodology_feedback'] ?? '')) ?: null;
        $improvementRecommendations = trim((string) ($_POST['improvement_recommendations'] ?? '')) ?: null;
        $ethicsFeasibility = trim((string) ($_POST['ethics_feasibility'] ?? '')) ?: null;
        $fullRecommendations = '';
        if ($methodologyFeedback) {
            $fullRecommendations .= "Methodology Feedback:\n" . $methodologyFeedback . "\n\n";
        }
        if ($ethicsFeasibility) {
            $fullRecommendations .= "Ethics and Feasibility:\n" . $ethicsFeasibility . "\n\n";
        }
        if ($improvementRecommendations) {
            $fullRecommendations .= "Recommendations for Improvement:\n" . $improvementRecommendations;
        }

        $weighted = $validated['total'];
        $evalId = Database::transaction(function () use ($appId, $assignment, $draft, $round, $rubric, $validated, $weighted, $fullRecommendations): int {
            if ($draft === null) {
                $evalId = EvaluationModel::createEvaluation([
                    'application_id' => $appId,
                    'assignment_id' => (int) $assignment['id'],
                    'rubric_template_id' => (int) $rubric['id'],
                    'rubric_snapshot' => RubricModel::snapshot($rubric),
                    'evaluator_user_id' => Auth::id(),
                    'evaluation_type' => 'proposal',
                    'evaluation_round' => $round,
                    'status' => 'draft',
                ]);
            } else {
                $evalId = (int) $draft['id'];
                if (empty($draft['rubric_template_id'])) {
                    EvaluationModel::updateEvaluation($evalId, [
                        'rubric_template_id' => (int) $rubric['id'],
                        'rubric_snapshot' => RubricModel::snapshot($rubric),
                    ]);
                }
            }

            RubricScoringService::replaceScores($evalId, $validated['scores']);
            EvaluationModel::updateEvaluation($evalId, [
                'weighted_score' => $weighted,
                'max_score' => 100,
                'strengths' => trim((string) ($_POST['strengths'] ?? '')) ?: null,
                'weaknesses' => trim((string) ($_POST['weaknesses'] ?? '')) ?: null,
                'recommendations' => $fullRecommendations ?: null,
                'overall_comment' => trim((string) ($_POST['overall_comment'] ?? '')) ?: null,
                'recommendation' => trim((string) ($_POST['recommendation'] ?? '')) ?: null,
                'status' => 'draft',
            ]);
            return $evalId;
        });

        echo json_encode(['ok' => true, 'message' => 'Draft saved at ' . date('H:i'), 'score' => $weighted]);
        exit;
    }

    public static function submit(): void
    {
        Gate::authorize('research.evaluate');
        Csrf::verifyRequest();
        $appId = (int) ($_POST['application_id'] ?? 0);
        $app = ApplicationModel::find($appId);
        if ($app === null) {
            Session::flash('error', 'Application not found.');
            redirect('index.php?action=my_evaluations');
        }
        $assignment = Database::one(
            'SELECT * FROM evaluator_assignments
             WHERE application_id = ? AND evaluator_user_id = ? AND assignment_level = "college"
               AND is_active = 1 AND status = "accepted"',
            [$appId, Auth::id()]
        );
        if ($assignment === null) {
            Session::flash('error', 'No active college evaluation assignment found.');
            redirect('index.php?action=my_evaluations');
        }
        if ($assignment['deadline'] && strtotime($assignment['deadline']) < time()) {
            Session::flash('warning', 'This evaluation is overdue. Submission is being recorded.');
        }

        $round = EvaluationModel::nextRound($appId, Auth::id());
        $draft = EvaluationModel::findDraft($appId, Auth::id(), $round);
        $rubric = !empty($draft['rubric_template_id'])
            ? RubricModel::findById((int) $draft['rubric_template_id'])
            : RubricModel::activeFor('college_proposal');
        if ($rubric === null) {
            Session::flash('error', 'No published College Proposal rubric is available.');
            redirect('index.php?action=my_evaluations');
        }
        $validated = RubricScoringService::validate($rubric, $_POST, true);
        if ($validated['errors'] !== []) {
            Session::flash('error', implode(' ', $validated['errors']));
            redirect('index.php?action=evaluation_form&application_id=' . $appId);
        }

        $recommendation = (string) ($_POST['recommendation'] ?? '');
        if (!in_array($recommendation, ['accept', 'accept_with_revision', 'major_revision', 'reject'], true)) {
            Session::flash('error', 'Select a final recommendation.');
            redirect('index.php?action=evaluation_form&application_id=' . $appId);
        }
        $strengths = trim((string) ($_POST['strengths'] ?? '')) ?: null;
        $weaknesses = trim((string) ($_POST['weaknesses'] ?? '')) ?: null;
        $methodologyFeedback = trim((string) ($_POST['methodology_feedback'] ?? '')) ?: null;
        $improvementRecommendations = trim((string) ($_POST['improvement_recommendations'] ?? '')) ?: null;
        $ethicsFeasibility = trim((string) ($_POST['ethics_feasibility'] ?? '')) ?: null;
        $overall = trim((string) ($_POST['overall_comment'] ?? '')) ?: null;

        $fullRecommendations = '';
        if ($methodologyFeedback) {
            $fullRecommendations .= "Methodology Feedback:\n" . $methodologyFeedback . "\n\n";
        }
        if ($ethicsFeasibility) {
            $fullRecommendations .= "Ethics and Feasibility:\n" . $ethicsFeasibility . "\n\n";
        }
        if ($improvementRecommendations) {
            $fullRecommendations .= "Recommendations for Improvement:\n" . $improvementRecommendations;
        }
        $toResearcher = array_values(array_filter(array_map(
            static fn($comment) => trim((string) $comment), (array) ($_POST['comments'] ?? [])
        )));
        $committeeOnly = array_values(array_filter(array_map(
            static fn($comment) => trim((string) $comment), (array) ($_POST['committee_comments'] ?? [])
        )));
        $weighted = $validated['total'];

        $evalId = Database::transaction(function () use (
            $appId, $assignment, $draft, $round, $rubric, $validated, $weighted,
            $recommendation, $strengths, $weaknesses, $fullRecommendations, $overall,
            $toResearcher, $committeeOnly
        ): int {
            if ($draft === null) {
                $evalId = EvaluationModel::createEvaluation([
                    'application_id' => $appId,
                    'assignment_id' => (int) $assignment['id'],
                    'rubric_template_id' => (int) $rubric['id'],
                    'rubric_snapshot' => RubricModel::snapshot($rubric),
                    'evaluator_user_id' => Auth::id(),
                    'evaluation_type' => 'proposal',
                    'evaluation_round' => $round,
                    'status' => 'draft',
                ]);
            } else {
                $evalId = (int) $draft['id'];
                if (empty($draft['rubric_template_id'])) {
                    EvaluationModel::updateEvaluation($evalId, [
                        'rubric_template_id' => (int) $rubric['id'],
                        'rubric_snapshot' => RubricModel::snapshot($rubric),
                    ]);
                }
            }

            RubricScoringService::replaceScores($evalId, $validated['scores']);
            EvaluationModel::updateEvaluation($evalId, [
                'recommendation' => $recommendation,
                'weighted_score' => $weighted,
                'max_score' => 100,
                'strengths' => $strengths,
                'weaknesses' => $weaknesses,
                'recommendations' => $fullRecommendations ?: null,
                'overall_comment' => $overall,
                'status' => 'submitted',
                'submitted_at' => date('Y-m-d H:i:s'),
            ]);
            foreach ($toResearcher as $comment) {
                EvaluationModel::addComment($evalId, $comment, 'to_researcher', Auth::id());
            }
            foreach ($committeeOnly as $comment) {
                EvaluationModel::addComment($evalId, $comment, 'committee_only', Auth::id());
            }
            EvaluationModel::setAssignmentStatus((int) $assignment['id'], 'completed');
            return $evalId;
        });

        Audit::log('evaluation.submitted', 'research_application', $appId, null, [
            'evaluation' => $evalId,
            'score' => $weighted,
            'rubric_version' => (int) $rubric['version'],
        ]);

        // Move the workflow only when all active college assignments have
        // accepted and submitted their independent rubric evaluation.
        $app = ApplicationModel::find($appId);
        if (in_array($app['status_code'], ['EVALUATOR_ASSIGNED', 'EVALUATOR_ACCEPTED'], true)) {
            $notAccepted = (int) Database::scalar(
                'SELECT COUNT(*) FROM evaluator_assignments
                 WHERE application_id = ? AND assignment_level = "college"
                   AND is_active = 1 AND status IN ("assigned","conflict_declared")',
                [$appId]
            );
            if ($notAccepted === 0) {
                WorkflowService::fire($app, 'research_application', 'Evaluator accepts', null);
                $app = ApplicationModel::find($appId);
                if (($app['status_code'] ?? '') === 'EVALUATOR_ACCEPTED') {
                    WorkflowService::fire($app, 'research_application', 'Start evaluation', null);
                }
            }
        }
        $app = ApplicationModel::find($appId);
        if (in_array($app['status_code'], ['UNDER_EVALUATION', 'RE_EVALUATION'], true)) {
            $required = (int) Database::scalar(
                'SELECT COUNT(*) FROM evaluator_assignments
                 WHERE application_id = ? AND assignment_level = "college" AND is_active = 1
                   AND status NOT IN ("cancelled","declined","conflict_declared")',
                [$appId]
            );
            $done = (int) Database::scalar(
                'SELECT COUNT(DISTINCT e.assignment_id) FROM evaluations e
                 JOIN evaluator_assignments ea ON ea.id = e.assignment_id
                 WHERE e.application_id = ? AND e.status = "submitted"
                   AND e.evaluation_type = "proposal" AND ea.assignment_level = "college"
                   AND ea.is_active = 1 AND ea.status = "completed"',
                [$appId]
            );
            if ($required > 0 && $done >= $required) {
                WorkflowService::fire($app, 'research_application', 'Submit evaluation', null);
            }
        }
        Session::flash('success', 'Evaluation submitted and locked.');
        redirect('index.php?action=my_evaluations');
    }

    public static function view(): void
    {
        Gate::authorize('research.view');
        $evalId = (int) ($_GET['id'] ?? 0);
        $eval = EvaluationModel::evaluationById($evalId);
        if ($eval === null) {
            Session::flash('error', 'Evaluation not found.');
            redirect('index.php');
        }
        $app = ApplicationModel::find((int) $eval['application_id']);
        $isEvaluator = (int) $eval['evaluator_user_id'] === Auth::id();
        $canReview = Gate::allows('research.screen', 'research.review', 'research.manage');
        if (!$isEvaluator && !$canReview) {
            Session::flash('error', 'Not authorized.');
            redirect('index.php');
        }
        $scores = EvaluationModel::scores($evalId);
        $comments = EvaluationModel::commentsFor($evalId, $canReview ? null : 'to_researcher');
        include APP_PATH . '/views/research/evaluation_view.php';
    }
}
