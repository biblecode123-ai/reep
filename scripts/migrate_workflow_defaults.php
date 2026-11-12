<?php
declare(strict_types=1);

use App\Core\Database;

require __DIR__ . '/../app/bootstrap.php';

/**
 * Idempotent production-safe settings migration for the institutional
 * evaluation defaults. Existing director-customized values are preserved.
 */
$defaults = [
    'college_evaluators_default' => ['1', 'research', 'integer', 'Default number of college evaluators per stage'],
    'university_evaluators_default' => ['1', 'research', 'integer', 'Default number of university evaluators per stage'],
    'college_proposal_passing_score' => ['50', 'research', 'decimal', 'College proposal passing threshold out of 100'],
    'college_report_passing_score' => ['50', 'research', 'decimal', 'College completed report passing threshold out of 100'],
    'university_proposal_passing_score' => ['50', 'research', 'decimal', 'University proposal passing threshold out of 100'],
    'university_report_passing_score' => ['50', 'research', 'decimal', 'University completed report passing threshold out of 100'],
];

Database::transaction(function () use ($defaults): void {
    foreach ($defaults as $key => [$value, $group, $type, $description]) {
        $existing = Database::one(
            'SELECT id FROM system_settings WHERE setting_key = ?',
            [$key]
        );
        if ($existing === null) {
            Database::insert('system_settings', [
                'setting_key' => $key,
                'setting_value' => $value,
                'setting_group' => $group,
                'value_type' => $type,
                'description' => $description,
            ]);
        }
    }
});

echo "Institutional workflow defaults are installed. Existing customized values were preserved.\n";
