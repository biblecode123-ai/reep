<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Auth;
use App\Core\Audit;

final class DatasetService
{
    public static function create(array $data): int
    {
        return Database::transaction(function () use ($data) {
            $datasetId = Database::insert('research_datasets', [
                'project_id'        => (int)$data['project_id'],
                'title'             => $data['title'],
                'description'       => $data['description'] ?? null,
                'data_type'         => $data['data_type'] ?? null,
                'access_level'      => $data['access_level'] ?? 'private',
                'embargo_until'     => $data['embargo_until'] ?? null,
                'metadata'          => json_encode($data['metadata'] ?? []),
                'created_by'        => Auth::id(),
            ]);

            Audit::log('dataset.created', 'research_dataset', $datasetId, null, ['project_id' => $data['project_id']]);
            return (int)$datasetId;
        });
    }

    public static function uploadFile(int $datasetId, array $file, int $docTypeId, string $reason = ''): array
    {
        return DocumentService::upload(
            $file, 
            $docTypeId, 
            'research_dataset', 
            $datasetId, 
            'Dataset File', 
            'entity_team', 
            $reason
        );
    }
}

