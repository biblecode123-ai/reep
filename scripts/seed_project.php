<?php
$dbPath = 'database/mwu_rpee.sqlite';
$db = new PDO("sqlite:$dbPath");

// Seed a project
$db->exec("INSERT INTO research_applications (application_number, call_id, pi_user_id, title, college_id, status_code) VALUES ('APP001', 1, 1, 'Test App', 1, 'APPROVED')");
$appId = $db->lastInsertId();
$db->exec("INSERT INTO research_projects (project_number, application_id, title, pi_user_id, college_id, status_code) VALUES ('PROJ001', $appId, 'Test Proj', 1, 1, 'ACTIVE')");

echo "Project seeded.\n";
