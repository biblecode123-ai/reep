<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Auth;
use App\Core\Audit;

final class RepositoryService
{
    public static function submit(string $entityType, int $entityId, array $metadata): int
    {
        return Database::transaction(function () use ($entityType, $entityId, $metadata) {
            $itemId = Database::insert('research_repository_items', [
                'entity_type'  => $entityType,
                'entity_id'    => $entityId,
                'title'        => $metadata['title'],
                'abstract'     => $metadata['abstract'] ?? null,
                'access_level' => $metadata['access_level'] ?? 'private',
                'status'       => 'submitted',
                'metadata'     => json_encode($metadata),
                'created_by'   => Auth::id(),
            ]);

            Audit::log('repository.submitted', 'research_repository_item', $itemId, null, ['entity' => $entityType]);
            return (int)$itemId;
        });
    }

    public static function approve(int $itemId): void
    {
        Database::update('research_repository_items', [
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$itemId]);
        
        Audit::log('repository.published', 'research_repository_item', $itemId);
    }
}

