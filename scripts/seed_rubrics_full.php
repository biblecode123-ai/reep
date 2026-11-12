<?php
$dbPath = 'database/mwu_rpee.sqlite';
try {
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->beginTransaction();

    $db->exec("DELETE FROM rubric_templates");
    $db->exec("INSERT INTO rubric_templates (name, version) VALUES ('College Proposal Evaluation', 1)");
    $tId = $db->lastInsertId();

    $criteria = [
        ['name' => 'Research Title, Background and Context', 'points' => 10],
        ['name' => 'Problem Statement and Justification', 'points' => 12],
        ['name' => 'Research Objectives, Questions and Hypotheses', 'points' => 8],
        ['name' => 'Literature Review and Theoretical Framework', 'points' => 15],
        ['name' => 'Research Gap, Relevance and Expected Contribution', 'points' => 10],
        ['name' => 'Research Design and Methodology', 'points' => 20],
        ['name' => 'Research Ethics and Ethical Considerations', 'points' => 8],
        ['name' => 'Work Plan, Timeline and Feasibility', 'points' => 7],
        ['name' => 'Budget and Resource Planning', 'points' => 5],
        ['name' => 'Academic Writing, Referencing and Presentation', 'points' => 5],
    ];

    foreach ($criteria as $c) {
        $db->prepare("INSERT INTO rubric_criteria (template_id, name, max_points) VALUES (?, ?, ?)")
           ->execute([$tId, $c['name'], $c['points']]);
    }

    $db->commit();
    echo "Full College Proposal Rubric seeded.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
