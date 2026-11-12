<?php
$dbPath = 'database/mwu_rpee.sqlite';
$migrationPath = 'database/migrations/001_rubric_reporting.sql';

try {
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $sql = file_get_contents($migrationPath);
    $db->exec($sql);
    
    echo "Migration 001_rubric_reporting executed successfully.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
