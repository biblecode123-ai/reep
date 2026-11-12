<?php
declare(strict_types=1);

/**
 * The ONLY file-serving endpoint (spec §64, §114).
 * Verifies authentication, permission, entity access and visibility
 * inside DocumentService before streaming a single byte.
 */

require_once __DIR__ . '/../app/bootstrap.php';

use App\Core\Auth;
use App\Services\DocumentService;

if (!Auth::check()) {
    http_response_code(401);
    exit('Authentication required.');
}

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('Invalid document id.');
}

DocumentService::download($id);
