<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Settings;

/**
 * Numbering service (spec §35, §103).
 * Atomic per-year sequences: MWU-RPEE-YYYY-NNNN style, from number_sequences.
 * Prefixes come from system_settings — never hardcoded (Rule 2).
 */
final class NumberingService
{
    private const SEQUENCE_KEYS = [
        'research_call', 'research_application', 'research_project', 'ethics_application',
        'repository_record', 'progress_report', 'integrity_case', 'appeal', 'ethics_certificate',
        'research_agreement',
    ];

    /** Generate the next number, e.g. MWU-RPEE-2027-0001. */
    public static function next(string $sequenceKey, ?int $year = null): string
    {
        if (!in_array($sequenceKey, self::SEQUENCE_KEYS, true)) {
            throw new \InvalidArgumentException('Unknown number sequence: ' . $sequenceKey);
        }
        $year = $year ?? (int) date('Y');

        return Database::transaction(function () use ($sequenceKey, $year): string {
            $row = Database::one(
                'SELECT id, next_value FROM number_sequences WHERE seq_key = ? AND seq_year = ?'
                    . (Database::isSqlite() ? '' : ' FOR UPDATE'),
                [$sequenceKey, $year]
            );
            if ($row === null) {
                Database::insert('number_sequences', ['seq_key' => $sequenceKey, 'seq_year' => $year, 'next_value' => 2]);
                $value = 1;
            } else {
                $value = (int) $row['next_value'];
                Database::update('number_sequences', ['next_value' => $value + 1], 'id = ?', [(int) $row['id']]);
            }
            return self::format($sequenceKey, $year, $value);
        });
    }

    public static function format(string $sequenceKey, int $year, int $value): string
    {
        $prefix = self::prefixFor($sequenceKey);
        return sprintf('%s-%d-%04d', $prefix, $year, $value);
    }

    private static function prefixFor(string $sequenceKey): string
    {
        $map = [
            'research_call'        => 'call_number_prefix',
            'research_application' => 'application_number_prefix',
            'research_project'     => 'project_number_prefix',
            'ethics_application'   => 'ethics_number_prefix',
            'ethics_certificate'   => 'certificate_number_prefix',
            'repository_record'    => 'repository_number_prefix',
            'research_agreement'   => 'agreement_number_prefix',
        ];
        $setting = $map[$sequenceKey] ?? null;
        $prefix  = $setting !== null ? Settings::get($setting) : null;
        $fallback = 'MWU-RPEE';
        if ($prefix === null || $prefix === '') {
            $base = Settings::get('project_number_prefix', $fallback);
            $suffix = match ($sequenceKey) {
                'progress_report'   => 'PR',
                'integrity_case'    => 'INT',
                'appeal'            => 'APL',
                'ethics_certificate' => 'CERT',
                default             => null,
            };
            $prefix = $suffix !== null ? $base . '-' . $suffix : $base;
        }
        return (string) $prefix;
    }
}

