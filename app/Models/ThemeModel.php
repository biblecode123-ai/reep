<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Research themes (spec §6).
 */
final class ThemeModel
{
    public static function all(): array
    {
        return Database::all('SELECT * FROM research_themes ORDER BY name');
    }

    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM research_themes WHERE id = ?', [$id]);
    }

    public static function create(array $data): int
    {
        return Database::insert('research_themes', $data);
    }

    public static function update(int $id, array $data): void
    {
        Database::update('research_themes', $data, 'id = ?', [$id]);
    }

    public static function countCalls(int $themeId): int
    {
        return (int) Database::scalar('SELECT COUNT(*) FROM research_call_themes WHERE theme_id = ?', [$themeId])
            + (int) Database::scalar('SELECT COUNT(*) FROM research_calls WHERE research_theme_id = ? AND id NOT IN (SELECT call_id FROM research_call_themes)', [$themeId]);
    }
}
