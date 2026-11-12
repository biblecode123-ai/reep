<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

use App\Models\ThemeModel;
use App\Models\CallModel;
use App\Core\Auth;
use App\Core\Database;

echo "Running Director Smoke Tests...\n";

// 1. Theme Test
$data = ['name' => 'Test Theme', 'code' => 'TT', 'status' => 'active'];
$themeId = ThemeModel::create($data);
if (!$themeId) die("Failed to create theme\n");
echo "Theme created with ID: $themeId\n";

// 2. Call Test
$callData = [
    'title' => 'Test Call',
    'call_number' => 'TEST-' . time(),
    'reference_number' => 'TC-2026',
    'research_theme_id' => $themeId,
    'status' => 'draft',
    'created_by' => 2, // Director ID
];
$callId = CallModel::create($callData);
if (!$callId) die("Failed to create call\n");
echo "Call created with ID: $callId\n";

echo "All Director Smoke Tests passed.\n";
