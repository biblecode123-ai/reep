<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class RepositoryCatalogService
{
    public static function search(array $filters): array
    {
        $sql = 'SELECT r.*, p.title as project_title FROM research_repository_items r 
                JOIN research_projects p ON r.entity_id = p.id 
                WHERE r.status = "published"';
        $params = [];

        if (!empty($filters['query'])) {
            $sql .= ' AND (r.title LIKE ? OR r.abstract LIKE ?)';
            $params[] = '%' . $filters['query'] . '%';
            $params[] = '%' . $filters['query'] . '%';
        }

        if (!empty($filters['college_id'])) {
            $sql .= ' AND p.college_id = ?';
            $params[] = (int)$filters['college_id'];
        }

        $sql .= ' ORDER BY r.published_at DESC';
        
        return Database::all($sql, $params);
    }
}

