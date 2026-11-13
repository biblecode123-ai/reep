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
            'SELECT * FROM evaluator_assignments WHERE application_id = ? AND evaluator_user_id = ? AND is_active = 1',
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

        $criteria = CallModel::criteriaFor((int) $app['call_id']);
        $round = EvaluationModel::nextRound($appId, Auth::id());
        $draft = EvaluationModel::findDraft($appId, Auth::id(), $round);

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
            $draftScores = Database::all(
                'SELECT criterion_id, score, comment FROM evaluation_scores WHERE evaluation_id = ?',
                [(int) $draft['id']]
            );
            foreach ($draftScores as $s) {
                $draftData['scores'][$s['criterion_id']] = $s['score'];
                $draftData['score_comments'][$s['criterion_id']] = $s['comment'];
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
            'SELECT * FROM evaluator_assignments WHERE application_id = ? AND evaluator_user_id = ? AND is_active = 1 AND status IN ("assigned","accepted")',
            [$appId, Auth::id()]
        );
        if ($assignment === null) {
            echo json_encode(['ok' => false, 'message' => 'No active evaluation assignment found.']);
            exit;
        }
        $criteria = CallModel::criteriaFor((int) $app['call_id']);
        if (!$criteria) {
            echo json_encode(['ok' => false, 'message' => 'The call has no evaluation criteria configured.']);
            exit;
        }
        $round = EvaluationModel::nextRound($appId, Auth::id());
        $draft = EvaluationModel::findDraft($appId, Auth::id(), $round);

        // Persist draft + scores atomically so a partial save never leaves an
        // evaluation row without its scores (§6 database transactions).
        [$evalId, $weighted] = Database::transaction(function () use ($appId, $assignment, $draft, $round, $criteria) {
        // Create or update draft
        if ($draft === null) {
            $evalId = EvaluationModel::createEvaluation([
                'application_id'    => $appId,
                'assignment_id'     => (int) $assignment['id'],
                'evaluator_user_id' => Auth::id(),
                'evaluation_type'   => 'proposal',
                'evaluation_round'  => $round,
                'status'            => 'draft',
            ]);
        } else {
            $evalId = (int) $draft['id'];
        }

        // Save scores
        $total = 0.0; $weightSum = 0.0; $given = 0;
        foreach ($criteria as $c) {
            $score = $_POST['score_' . $c['id']] ?? null;
            if ($score === null || $score === '') {
                continue;
            }
            if (!is_numeric($score)) {
                continue;
            }
            $s = max(0, min(100, (float) $score));
            EvaluationModel::setScore($evalId, (int) $c['id'], $s, trim((string) ($_POST['score_comment_' . $c['id']] ?? '')) ?: null);
            $total += $s * ((float) $c['weight_pct'] / 100);
            $weightSum += (float) $c['weight_pct'];
            $given++;
        }
        // Calculate current weighted score
        $weighted = $weightSum > 0 ? round($total * (100 / $weightSum), 2) : round($total, 2);
        return [$evalId, $weighted];
        });

        // Save feedback fields
        $recommendation = (string) ($_POST['recommendation'] ?? '');
        $strengths = trim((string) ($_POST['strengths'] ?? '')) ?: null;
        $weaknesses = trim((string) ($_POST['weaknesses'] ?? '')) ?: null;
        $methodologyFeedback = trim((string) ($_POST['methodology_feedback'] ?? '')) ?: null;
        $improvementRecommendations = trim((string) ($_POST['improvement_recommendations'] ?? '')) ?: null;
        $ethicsFeasibility = trim((string) ($_POST['ethics_feasibility'] ?? '')) ?: null;
        $overall = trim((string) ($_POST['overall_comment'] ?? '')) ?: null;

        EvaluationModel::updateEvaluation($evalId, [
            'recommendation'  => $recommendation ?: null,
            'weighted_score'  => $weighted,
            'strengths'       => $strengths,
            'weaknesses'      => $weaknesses,
            'recommendations' => $improvementRecommendations,
            'overall_comment' => $overall,
            'status'          => 'draft',
        ]);

        // Store additional structured feedback in a separate field or use existing fields
        // For now, we'll append to recommendations with clear section markers
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

        if ($fullRecommendations) {
            EvaluationModel::updateEvaluation($evalId, ['recommendations' => $fullRecommendations]);
        }

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
            'SELECT * FROM evaluator_assignments WHERE application_id = ? AND evaluator_user_id = ? AND is_active = 1 AND status IN ("assigned","accepted")',
            [$appId, Auth::id()]
        );
        if ($assignment === null) {
            Session::flash('error', 'No active evaluation assignment found.');
            redirect('index.php?action=my_evaluations');
        }

        // Check deadline - if overdue and policy prohibits, block submission
        if ($assignment['deadline'] && strtotime($assignment['deadline']) < time()) {
            // For now, allow with warning - policy can be configured to block
            Session::flash('warning', 'This evaluation is overdue. Submission is being recorded.');
        }

        $criteria = CallModel::criteriaFor((int) $app['call_id']);
        if (!$criteria) {
            Session::flash('error', 'The call has no evaluation criteria configured.');
            redirect('index.php?action=my_evaluations');
        }
        $round = EvaluationModel::nextRound($appId, Auth::id());
        $draft = EvaluationModel::findDraft($appId, Auth::id(), $round);

        // Validate everything BEFORE writing so a rejected submission leaves
        // no partial rows (§6: transactions prevent partially saved evaluations).
        $scores = [];
        $total = 0.0; $weightSum = 0.0;
        foreach ($criteria as $c) {
            $score = $_POST['score_' . $c['id']] ?? null;
            if ($score === null || $score === '' || !is_numeric($score)) {
                continue;
            }
            $s = max(0, min(100, (float) $score));
            $scores[] = ['criterion_id' => (int) $c['id'], 'score' => $s,
                'comment' => trim((string) ($_POST['score_comment_' . $c['id']] ?? '')) ?: null];
            $total += $s * ((float) $c['weight_pct'] / 100);
            $weightSum += (float) $c['weight_pct'];
        }
        if (!$scores) {
            Session::flash('error', 'Provide scores for at least one criterion.');
            redirect('index.php?action=evaluation_form&application_id=' . $appId);
        }
        $weighted = $weightSum > 0 ? round($total * (100 / $weightSum), 2) : round($total, 2);

        // Validate recommendation (existing permitted set only, §5).
        $recommendation = (string) ($_POST['recommendation'] ?? '');
        if (!in_array($recommendation, ['accept', 'accept_with_revision', 'major_revision', 'reject'], true)) {
            Session::flash('error', 'Select a final recommendation.');
            redirect('index.php?action=evaluation_form&application_id=' . $appId);
        }

        // Save feedback fields
        $strengths = trim((string) ($_POST['strengths'] ?? '')) ?: null;
        $weaknesses = trim((string) ($_POST['weaknesses'] ?? '')) ?: null;
        $methodologyFeedback = trim((string) ($_POST['methodology_feedback'] ?? '')) ?: null;
        $improvementRecommendations = trim((string) ($_POST['improvement_recommendations'] ?? '')) ?: null;
        $ethicsFeasibility = trim((string) ($_POST['ethics_feasibility'] ?? '')) ?: null;
        $overall = trim((string) ($_POST['overall_comment'] ?? '')) ?: null;

        // Combine structured feedback
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
            static fn($c) => trim((string) $c), (array) ($_POST['comments'] ?? []))));
        $committeeOnly = array_values(array_filter(array_map(
            static fn($c) => trim((string) $c), (array) ($_POST['committee_comments'] ?? []))));

        // Persist evaluation, scores, comments and assignment atomically.
        $evalId = Database::transaction(function () use (
            $appId, $assignment, $draft, $round, $scores, $weighted, $recommendation,
            $strengths, $weaknesses, $fullRecommendations, $overall, $toResearcher, $committeeOnly
        ) {
            if ($draft === null) {
                $evalId = EvaluationModel::createEvaluation([
                    'application_id'    => $appId,
                    'assignment_id'     => (int) $assignment['id'],
                    'evaluator_user_id' => Auth::id(),
                    'evaluation_type'   => 'proposal',
                    'evaluation_round'  => $round,
                    'status'            => 'draft',
                ]);
            } else {
                $evalId = (int) $draft['id'];
            }

            foreach ($scores as $s) {
                EvaluationModel::setScore($evalId, $s['criterion_id'], $s['score'], $s['comment']);
            }

            EvaluationModel::updateEvaluation($evalId, [
                'recommendation'  => $recommendation,
                'weighted_score'  => $weighted,
                'strengths'       => $strengths,
                'weaknesses'      => $weaknesses,
                'recommendations' => $fullRecommendations ?: null,
                'overall_comment' => $overall,
                'status'          => 'submitted',
                'submitted_at'    => date('Y-m-d H:i:s'),
            ]);

            // Save individual comments to researcher
            foreach ($toResearcher as $text) {
                EvaluationModel::addComment($evalId, $text, 'to_researcher', Auth::id());
            }
            // Save confidential committee comments
            foreach ($committeeOnly as $text) {
                EvaluationModel::addComment($evalId, $text, 'committee_only', Auth::id());
            }

            EvaluationModel::setAssignmentStatus((int) $assignment['id'], 'completed');
            return $evalId;
        });
        Audit::log('evaluation.submitted', 'research_application', $appId, null, ['evaluation' => $evalId, 'score' => $weighted]);

        // Advance the workflow: assigned -> accepted -> under evaluation. The
        // completion transition only fires once the call's required number of
        // submitted evaluations is reached (multi-evaluator calls stay
        // UNDER_EVALUATION until the last required evaluation lands).
        $app = ApplicationModel::find($appId);
        if (in_array($app['status_code'], ['EVALUATOR_ASSIGNED', 'EVALUATOR_ACCEPTED'], true)) {
            WorkflowService::fire($app, 'research_application', 'Evaluator accepts', null);
            $app = ApplicationModel::find($appId);
            if ($app['status_code'] === 'EVALUATOR_ACCEPTED') {
                WorkflowService::fire($app, 'research_application', 'Start evaluation', null);
            }
        }
        $app = ApplicationModel::find($appId);
        if (in_array($app['status_code'], ['UNDER_EVALUATION', 'RE_EVALUATION'], true)) {
            // Minimum of two submitted assessments. Extra pending evaluators do not block.
            $done = (int) Database::scalar(
                'SELECT COUNT(*) FROM evaluations WHERE application_id = ? AND status = "submitted" AND evaluation_type = "proposal"',
                [$appId]
            );
            if ($done >= 2) {
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
