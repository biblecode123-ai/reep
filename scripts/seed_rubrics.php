<?php
$dbPath = 'database/mwu_rpee.sqlite';
try {
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $db->beginTransaction();
    
    // Insert Proposal Rubric (100 points)
    $db->exec("INSERT INTO rubric_templates (name, version) VALUES ('College Proposal Evaluation', 1)");
    $tId = $db->lastInsertId();
    
    $db->exec("INSERT INTO rubric_criteria (template_id, name, max_points) VALUES ($tId, 'Research Design and Methodology', 20)");
    $cId = $db->lastInsertId();
    $db->exec("INSERT INTO rubric_indicators (criterion_id, name, max_points) VALUES ($cId, 'Appropriateness of research design', 4)");
    
    // ... insert all other criteria and indicators ...

    $db->commit();
    echo "Initial rubrics seeded.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
