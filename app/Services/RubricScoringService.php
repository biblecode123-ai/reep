<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/** Shared indicator-level scoring and persistence for all four rubric stages. */
final class RubricScoringService
{
    /**
     * Validate indicator scores against the exact maxima in a rubric version.
     * Scores are raw points (0..indicator maximum), not percentages.
     *
     * @return array{errors: array, scores: array, total: float}
     */
    public static function validate(array $template, array $input, bool $requireAll): array
    {
        $errors = [];
        $scores = [];
        $total = 0.0;

        foreach (($template['criteria'] ?? []) as $criterion) {
            foreach (($criterion['indicators'] ?? []) as $indicator) {
                $id = (int) $indicator['id'];
                $field = 'score_' . $id;
                $raw = $input[$field] ?? '';
                $required = (int) ($indicator['is_required'] ?? 1) === 1;

                if ($raw === '' || $raw === null) {
                    if ($requireAll && $required) {
                        $errors[] = 'Score the required indicator: ' . $indicator['name'] . '.';
                    }
                    continue;
                }
                if (!is_numeric($raw)) {
                    $errors[] = 'The score for "' . $indicator['name'] . '" must be numeric.';
                    continue;
                }

                $score = (float) $raw;
                $maximum = (float) $indicator['max_points'];
                if ($score < 0 || $score > $maximum) {
                    $errors[] = 'The score for "' . $indicator['name'] . '" must be between 0 and ' . $maximum . '.';
                    continue;
                }

                $scores[] = [
                    'indicator_id' => $id,
                    'score' => round($score, 2),
                    'comment' => trim((string) ($input['score_comment_' . $id] ?? '')) ?: null,
                ];
                $total += $score;
            }
        }

        return [
            'errors' => $errors,
            'scores' => $scores,
            'total' => round($total, 2),
        ];
    }

    /** Replace the draft's indicator scores atomically inside caller transaction. */
    public static function replaceScores(int $evaluationId, array $scores): void
    {
        Database::run('DELETE FROM rubric_indicator_scores WHERE evaluation_id = ?', [$evaluationId]);
        foreach ($scores as $score) {
            Database::insert('rubric_indicator_scores', [
                'evaluation_id' => $evaluationId,
                'indicator_id' => (int) $score['indicator_id'],
                'score' => (float) $score['score'],
                'comment' => $score['comment'] ?? null,
            ]);
        }
    }

    /** Draft scores keyed by indicator id for rendering. */
    public static function scoresForEvaluation(int $evaluationId): array
    {
        $rows = Database::all(
            'SELECT indicator_id, score, comment FROM rubric_indicator_scores WHERE evaluation_id = ?',
            [$evaluationId]
        );
        $scores = [];
        foreach ($rows as $row) {
            $scores[(int) $row['indicator_id']] = [
                'score' => $row['score'],
                'comment' => $row['comment'],
            ];
        }
        return $scores;
    }
}
