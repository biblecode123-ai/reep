<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use InvalidArgumentException;
use JsonException;

/**
 * Immutable, versioned institutional evaluation rubrics.
 *
 * Published versions are never edited in place. A new save creates a new
 * version and makes it the active version for that evaluation stage.
 */
final class RubricModel
{
    public const TYPES = [
        'college_proposal' => 'College Proposal Evaluation',
        'college_completed_report' => 'College Completed Research Report Evaluation',
        'university_proposal' => 'University Proposal Evaluation',
        'university_completed_report' => 'University Completed Research Report Evaluation',
    ];

    /**
     * Validate a complete 100-point definition before it can be activated.
     * Every criterion must contain at least one required indicator and the
     * indicator maxima must add exactly to the criterion maximum.
     */
    public static function validateDefinition(array $criteria): array
    {
        $errors = [];
        if ($criteria === []) {
            return ['At least one criterion is required.'];
        }

        $criterionTotal = 0.0;
        $criterionNames = [];
        foreach ($criteria as $criterionIndex => $criterion) {
            $name = trim((string) ($criterion['name'] ?? ''));
            $max = $criterion['max_points'] ?? null;
            if ($name === '') {
                $errors[] = 'Criterion ' . ($criterionIndex + 1) . ' needs a name.';
            } elseif (in_array(mb_strtolower($name), $criterionNames, true)) {
                $errors[] = 'Duplicate criterion name: ' . $name . '.';
            } else {
                $criterionNames[] = mb_strtolower($name);
            }

            if (!is_numeric($max) || (float) $max <= 0 || (float) $max > 100) {
                $errors[] = 'Criterion ' . ($criterionIndex + 1) . ' must have a maximum between 0 and 100.';
                continue;
            }
            $criterionMax = (float) $max;
            $criterionTotal += $criterionMax;

            $indicators = (array) ($criterion['indicators'] ?? []);
            if ($indicators === []) {
                $errors[] = 'Criterion "' . ($name ?: ($criterionIndex + 1)) . '" needs at least one indicator.';
                continue;
            }

            $indicatorTotal = 0.0;
            $indicatorNames = [];
            foreach ($indicators as $indicatorIndex => $indicator) {
                $indicatorName = trim((string) ($indicator['name'] ?? ''));
                $indicatorMax = $indicator['max_points'] ?? null;
                if ($indicatorName === '') {
                    $errors[] = 'An indicator under "' . ($name ?: ($criterionIndex + 1)) . '" needs a name.';
                } elseif (in_array(mb_strtolower($indicatorName), $indicatorNames, true)) {
                    $errors[] = 'Duplicate indicator name under "' . $name . '": ' . $indicatorName . '.';
                } else {
                    $indicatorNames[] = mb_strtolower($indicatorName);
                }
                if (!is_numeric($indicatorMax) || (float) $indicatorMax <= 0 || (float) $indicatorMax > $criterionMax) {
                    $errors[] = 'Indicator "' . ($indicatorName ?: ($indicatorIndex + 1)) . '" has an invalid maximum.';
                    continue;
                }
                $indicatorTotal += (float) $indicatorMax;
            }

            if (abs($indicatorTotal - $criterionMax) > 0.001) {
                $errors[] = 'Indicator maxima under "' . $name . '" total ' . round($indicatorTotal, 2)
                    . ', but the criterion maximum is ' . round($criterionMax, 2) . '.';
            }
        }

        if (abs($criterionTotal - 100.0) > 0.001) {
            $errors[] = 'All criterion maxima must total exactly 100; current total is ' . round($criterionTotal, 2) . '.';
        }

        return $errors;
    }

    public static function createVersion(string $type, array $criteria, ?int $userId): int
    {
        if (!isset(self::TYPES[$type])) {
            throw new InvalidArgumentException('Unknown institutional rubric type.');
        }
        $errors = self::validateDefinition($criteria);
        if ($errors !== []) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }

        return Database::transaction(function () use ($type, $criteria, $userId): int {
            $version = (int) (Database::scalar(
                'SELECT COALESCE(MAX(version), 0) FROM rubric_templates WHERE rubric_type = ?',
                [$type]
            ) ?? 0) + 1;

            Database::run('UPDATE rubric_templates SET is_active = 0 WHERE rubric_type = ?', [$type]);
            $templateId = Database::insert('rubric_templates', [
                'rubric_type' => $type,
                'name' => self::TYPES[$type],
                'version' => $version,
                'is_active' => 1,
                'created_by' => $userId,
                'approved_by' => $userId,
                'approved_at' => date('Y-m-d H:i:s'),
            ]);

            foreach (array_values($criteria) as $criterionOrder => $criterion) {
                $criterionId = Database::insert('rubric_criteria', [
                    'template_id' => $templateId,
                    'name' => trim((string) $criterion['name']),
                    'description' => trim((string) ($criterion['description'] ?? '')) ?: null,
                    'instructions' => trim((string) ($criterion['instructions'] ?? '')) ?: null,
                    'max_points' => (float) $criterion['max_points'],
                    'weight_pct' => (float) ($criterion['weight_pct'] ?? 0),
                    'sort_order' => $criterionOrder,
                ]);
                foreach (array_values((array) $criterion['indicators']) as $indicatorOrder => $indicator) {
                    Database::insert('rubric_indicators', [
                        'criterion_id' => $criterionId,
                        'name' => trim((string) $indicator['name']),
                        'description' => trim((string) ($indicator['description'] ?? '')) ?: null,
                        'max_points' => (float) $indicator['max_points'],
                        'is_required' => array_key_exists('required', $indicator) ? (int) (bool) $indicator['required'] : 1,
                        'sort_order' => $indicatorOrder,
                    ]);
                }
            }

            return $templateId;
        });
    }

    public static function activeFor(string $type): ?array
    {
        $id = Database::scalar(
            'SELECT id FROM rubric_templates WHERE rubric_type = ? AND is_active = 1 ORDER BY version DESC LIMIT 1',
            [$type]
        );
        return $id === null ? null : self::findById((int) $id);
    }

    /** Load an exact version, including archived versions referenced by drafts. */
    public static function findById(int $id): ?array
    {
        $template = Database::one('SELECT * FROM rubric_templates WHERE id = ?', [$id]);
        if ($template === null) {
            return null;
        }
        $criteria = Database::all(
            'SELECT * FROM rubric_criteria WHERE template_id = ? ORDER BY sort_order, id',
            [$id]
        );
        foreach ($criteria as &$criterion) {
            $criterion['indicators'] = Database::all(
                'SELECT * FROM rubric_indicators WHERE criterion_id = ? ORDER BY sort_order, id',
                [(int) $criterion['id']]
            );
        }
        unset($criterion);
        $template['criteria'] = $criteria;
        return $template;
    }

    public static function snapshot(array $template): string
    {
        $snapshot = [
            'id' => (int) $template['id'],
            'rubric_type' => $template['rubric_type'],
            'name' => $template['name'],
            'version' => (int) $template['version'],
            'criteria' => array_map(static function (array $criterion): array {
                return [
                    'id' => (int) $criterion['id'],
                    'name' => $criterion['name'],
                    'max_points' => (float) $criterion['max_points'],
                    'indicators' => array_map(static fn(array $indicator): array => [
                        'id' => (int) $indicator['id'],
                        'name' => $indicator['name'],
                        'description' => $indicator['description'],
                        'max_points' => (float) $indicator['max_points'],
                        'is_required' => (bool) $indicator['is_required'],
                    ], $criterion['indicators'] ?? []),
                    'description' => $criterion['description'] ?? '',
                    'instructions' => $criterion['instructions'] ?? '',
                    'weight_pct' => (float) $criterion['weight_pct'],
                ];
            }, $template['criteria'] ?? []),
        ];

        try {
            return json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Could not create an immutable rubric snapshot.', 0, $e);
        }
    }

    public static function allVersions(): array
    {
        return Database::all(
            'SELECT rt.*, u.full_name AS approved_by_name
             FROM rubric_templates rt
             LEFT JOIN users u ON u.id = rt.approved_by
             ORDER BY rt.rubric_type, rt.version DESC'
        );
    }
}
