<?php
declare(strict_types=1);

use App\Core\Database;
use App\Models\RubricModel;

require __DIR__ . '/../app/bootstrap.php';

/**
 * Seed the four master-prompt institutional rubrics on a clean database.
 *
 * Safe to re-run: if a rubric type already has any version, that type is left
 * untouched. Existing versions are never deleted or overwritten.
 *
 * Run after scripts/migrate_rubric_engine.php:
 *   php scripts/seed_institutional_rubrics.php
 */
$rubrics = [
    'college_proposal' => [
        [
            'name' => 'Research Title, Background and Context',
            'max_points' => 10,
            'indicators' => [
                ['name' => 'Clarity, specificity, and relevance of title', 'max_points' => 3],
                ['name' => 'Adequacy of research background', 'max_points' => 4],
                ['name' => 'Institutional, disciplinary, or contextual relevance', 'max_points' => 3],
            ],
        ],
        [
            'name' => 'Problem Statement and Justification',
            'max_points' => 12,
            'indicators' => [
                ['name' => 'Clarity and precision of research problem', 'max_points' => 4],
                ['name' => 'Evidence supporting the existence of the problem', 'max_points' => 4],
                ['name' => 'Justification and importance of investigating it', 'max_points' => 4],
            ],
        ],
        [
            'name' => 'Research Objectives, Questions and Hypotheses',
            'max_points' => 8,
            'indicators' => [
                ['name' => 'Clarity and measurability of objectives', 'max_points' => 3],
                ['name' => 'Alignment between objectives and research questions', 'max_points' => 3],
                ['name' => 'Appropriateness of hypotheses, where applicable', 'max_points' => 2],
            ],
        ],
        [
            'name' => 'Literature Review and Theoretical Framework',
            'max_points' => 15,
            'indicators' => [
                ['name' => 'Relevance and coverage of literature', 'max_points' => 4],
                ['name' => 'Critical analysis and synthesis', 'max_points' => 4],
                ['name' => 'Use of current and credible sources', 'max_points' => 3],
                ['name' => 'Theoretical or conceptual framework', 'max_points' => 4],
            ],
        ],
        [
            'name' => 'Research Gap, Relevance and Expected Contribution',
            'max_points' => 10,
            'indicators' => [
                ['name' => 'Identification of a genuine research gap', 'max_points' => 4],
                ['name' => 'Disciplinary and institutional relevance', 'max_points' => 3],
                ['name' => 'Academic and practical contribution', 'max_points' => 3],
            ],
        ],
        [
            'name' => 'Research Design and Methodology',
            'max_points' => 20,
            'indicators' => [
                ['name' => 'Appropriateness of research design', 'max_points' => 4],
                ['name' => 'Sampling technique and justification', 'max_points' => 3],
                ['name' => 'Data-collection instruments', 'max_points' => 3],
                ['name' => 'Validity, reliability, or equivalent quality assurance', 'max_points' => 3],
                ['name' => 'Data-collection procedures', 'max_points' => 3],
                ['name' => 'Analysis methods and alignment with objectives', 'max_points' => 4],
            ],
        ],
        [
            'name' => 'Research Ethics and Ethical Considerations',
            'max_points' => 8,
            'indicators' => [
                ['name' => 'Identification of ethical issues', 'max_points' => 2],
                ['name' => 'Informed consent and participant protection', 'max_points' => 2],
                ['name' => 'Confidentiality and data protection', 'max_points' => 2],
                ['name' => 'Ethical approval and permissions, where applicable', 'max_points' => 2],
            ],
        ],
        [
            'name' => 'Work Plan, Timeline and Feasibility',
            'max_points' => 7,
            'indicators' => [
                ['name' => 'Realistic activities and milestones', 'max_points' => 3],
                ['name' => 'Appropriate timeline', 'max_points' => 2],
                ['name' => 'Feasibility of implementation', 'max_points' => 2],
            ],
        ],
        [
            'name' => 'Budget and Resource Planning',
            'max_points' => 5,
            'indicators' => [
                ['name' => 'Appropriate budget breakdown', 'max_points' => 2],
                ['name' => 'Justification of costs', 'max_points' => 2],
                ['name' => 'Availability and efficient use of resources', 'max_points' => 1],
            ],
        ],
        [
            'name' => 'Academic Writing, Referencing and Presentation',
            'max_points' => 5,
            'indicators' => [
                ['name' => 'Organization and clarity', 'max_points' => 2],
                ['name' => 'Academic language and presentation', 'max_points' => 1],
                ['name' => 'Referencing and citation consistency', 'max_points' => 2],
            ],
        ],
    ],

    'college_completed_report' => [
        [
            'name' => 'Research Title and Problem Statement',
            'max_points' => 10,
            'indicators' => [
                ['name' => 'Clarity and appropriateness of title', 'max_points' => 3],
                ['name' => 'Clarity of the research problem', 'max_points' => 4],
                ['name' => 'Consistency between title and actual research', 'max_points' => 3],
            ],
        ],
        [
            'name' => 'Research Objectives and Questions',
            'max_points' => 8,
            'indicators' => [
                ['name' => 'Achievement and coverage of objectives', 'max_points' => 4],
                ['name' => 'Alignment of research questions with findings', 'max_points' => 2],
                ['name' => 'Consistency of completed study with stated objectives', 'max_points' => 2],
            ],
        ],
        [
            'name' => 'Literature Review',
            'max_points' => 10,
            'indicators' => [
                ['name' => 'Relevance and adequacy of literature', 'max_points' => 3],
                ['name' => 'Critical analysis and synthesis', 'max_points' => 3],
                ['name' => 'Use of credible and current sources', 'max_points' => 2],
                ['name' => 'Connection to findings and discussion', 'max_points' => 2],
            ],
        ],
        [
            'name' => 'Research Methodology',
            'max_points' => 15,
            'indicators' => [
                ['name' => 'Appropriateness of research design', 'max_points' => 4],
                ['name' => 'Sampling and participant selection', 'max_points' => 3],
                ['name' => 'Data collection methods and instruments', 'max_points' => 3],
                ['name' => 'Validity, reliability, or equivalent quality assurance', 'max_points' => 2],
                ['name' => 'Analysis procedures and methodological consistency', 'max_points' => 3],
            ],
        ],
        [
            'name' => 'Relevance and Originality',
            'max_points' => 10,
            'indicators' => [
                ['name' => 'Relevance to the identified problem', 'max_points' => 3],
                ['name' => 'Originality and independent contribution', 'max_points' => 4],
                ['name' => 'Academic, institutional, or practical value', 'max_points' => 3],
            ],
        ],
        [
            'name' => 'Data Collection and Quality',
            'max_points' => 12,
            'indicators' => [
                ['name' => 'Adequacy and completeness of collected data', 'max_points' => 3],
                ['name' => 'Appropriate data collection procedures', 'max_points' => 3],
                ['name' => 'Data credibility and quality control', 'max_points' => 3],
                ['name' => 'Proper handling and documentation of data', 'max_points' => 3],
            ],
        ],
        [
            'name' => 'Data Analysis and Interpretation',
            'max_points' => 12,
            'indicators' => [
                ['name' => 'Appropriateness of analytical methods', 'max_points' => 3],
                ['name' => 'Accuracy of analysis', 'max_points' => 3],
                ['name' => 'Quality of interpretation', 'max_points' => 3],
                ['name' => 'Alignment between analysis and research objectives', 'max_points' => 3],
            ],
        ],
        [
            'name' => 'Findings, Conclusions and Recommendations',
            'max_points' => 8,
            'indicators' => [
                ['name' => 'Clarity and relevance of findings', 'max_points' => 3],
                ['name' => 'Conclusions supported by evidence', 'max_points' => 2],
                ['name' => 'Practical and appropriate recommendations', 'max_points' => 3],
            ],
        ],
        [
            'name' => 'Organization and Academic Writing',
            'max_points' => 8,
            'indicators' => [
                ['name' => 'Logical organization and structure', 'max_points' => 3],
                ['name' => 'Clarity and academic writing quality', 'max_points' => 3],
                ['name' => 'Tables, figures, and presentation', 'max_points' => 2],
            ],
        ],
        [
            'name' => 'Referencing, Citation and Research Ethics',
            'max_points' => 7,
            'indicators' => [
                ['name' => 'Accuracy and consistency of references', 'max_points' => 2],
                ['name' => 'Appropriate citation practices', 'max_points' => 2],
                ['name' => 'Ethical compliance and participant protection', 'max_points' => 2],
                ['name' => 'Research integrity declaration', 'max_points' => 1],
            ],
        ],
    ],

    'university_proposal' => [
        [
            'name' => 'Research Title, Background and Context',
            'max_points' => 8,
            'indicators' => [
                ['name' => 'Research title precision and relevance', 'max_points' => 2],
                ['name' => 'Depth and adequacy of background', 'max_points' => 3],
                ['name' => 'Contextual and disciplinary significance', 'max_points' => 3],
            ],
        ],
        [
            'name' => 'Research Problem and Justification',
            'max_points' => 12,
            'indicators' => [
                ['name' => 'Significance and clarity of the problem', 'max_points' => 4],
                ['name' => 'Evidence and scholarly grounding', 'max_points' => 4],
                ['name' => 'Strength of justification', 'max_points' => 4],
            ],
        ],
        [
            'name' => 'Objectives, Research Questions and Hypotheses',
            'max_points' => 8,
            'indicators' => [
                ['name' => 'Clarity and researchability of objectives', 'max_points' => 3],
                ['name' => 'Alignment of questions with objectives', 'max_points' => 3],
                ['name' => 'Appropriateness of hypotheses, where applicable', 'max_points' => 2],
            ],
        ],
        [
            'name' => 'Literature Review and Theoretical Foundation',
            'max_points' => 15,
            'indicators' => [
                ['name' => 'Breadth and relevance of literature', 'max_points' => 4],
                ['name' => 'Critical synthesis and scholarly depth', 'max_points' => 4],
                ['name' => 'Theoretical foundation', 'max_points' => 4],
                ['name' => 'Engagement with recent and authoritative research', 'max_points' => 3],
            ],
        ],
        [
            'name' => 'Research Gap, Originality and Expected Contribution',
            'max_points' => 15,
            'indicators' => [
                ['name' => 'Identification and substantiation of research gap', 'max_points' => 5],
                ['name' => 'Originality and novelty', 'max_points' => 4],
                ['name' => 'Potential contribution to knowledge', 'max_points' => 3],
                ['name' => 'Academic, policy, or societal significance', 'max_points' => 3],
            ],
        ],
        [
            'name' => 'Research Design and Methodological Rigor',
            'max_points' => 20,
            'indicators' => [
                ['name' => 'Appropriateness and rigor of research design', 'max_points' => 4],
                ['name' => 'Sampling strategy and justification', 'max_points' => 3],
                ['name' => 'Instruments and measurement quality', 'max_points' => 3],
                ['name' => 'Validity, reliability, or equivalent trustworthiness', 'max_points' => 3],
                ['name' => 'Data collection procedures', 'max_points' => 3],
                ['name' => 'Analytical framework and methodological alignment', 'max_points' => 4],
            ],
        ],
        [
            'name' => 'Research Ethics and Ethical Approval Considerations',
            'max_points' => 7,
            'indicators' => [
                ['name' => 'Identification of ethical risks', 'max_points' => 2],
                ['name' => 'Participant rights and informed consent', 'max_points' => 2],
                ['name' => 'Confidentiality and data protection', 'max_points' => 1],
                ['name' => 'Ethical approval and regulatory compliance', 'max_points' => 2],
            ],
        ],
        [
            'name' => 'Feasibility, Work Plan and Resources',
            'max_points' => 7,
            'indicators' => [
                ['name' => 'Feasibility and institutional capacity', 'max_points' => 2],
                ['name' => 'Work plan and milestones', 'max_points' => 2],
                ['name' => 'Timeline realism', 'max_points' => 2],
                ['name' => 'Resource planning', 'max_points' => 1],
            ],
        ],
        [
            'name' => 'Academic Writing and Scholarly Referencing',
            'max_points' => 8,
            'indicators' => [
                ['name' => 'Academic structure and clarity', 'max_points' => 3],
                ['name' => 'Scholarly writing quality', 'max_points' => 2],
                ['name' => 'Citation and referencing accuracy', 'max_points' => 3],
            ],
        ],
    ],

    'university_completed_report' => [
        [
            'name' => 'Title and Problem Formulation',
            'max_points' => 10,
            'indicators' => [
                ['name' => 'Precision and scholarly relevance of title', 'max_points' => 3],
                ['name' => 'Research problem formulation', 'max_points' => 4],
                ['name' => 'Consistency of study with stated problem', 'max_points' => 3],
            ],
        ],
        [
            'name' => 'Literature Review and Theoretical Framework',
            'max_points' => 12,
            'indicators' => [
                ['name' => 'Coverage and relevance', 'max_points' => 3],
                ['name' => 'Critical synthesis', 'max_points' => 3],
                ['name' => 'Theoretical or conceptual framework', 'max_points' => 3],
                ['name' => 'Current scholarly evidence', 'max_points' => 3],
            ],
        ],
        [
            'name' => 'Research Gap, Originality and Contribution',
            'max_points' => 12,
            'indicators' => [
                ['name' => 'Demonstration of research gap', 'max_points' => 3],
                ['name' => 'Originality and novelty', 'max_points' => 3],
                ['name' => 'Contribution to existing knowledge', 'max_points' => 3],
                ['name' => 'Academic and societal significance', 'max_points' => 3],
            ],
        ],
        [
            'name' => 'Research Objectives, Questions and Hypotheses',
            'max_points' => 8,
            'indicators' => [
                ['name' => 'Achievement of research objectives', 'max_points' => 3],
                ['name' => 'Alignment of research questions', 'max_points' => 3],
                ['name' => 'Appropriate treatment of hypotheses, where applicable', 'max_points' => 2],
            ],
        ],
        [
            'name' => 'Research Design and Methodology',
            'max_points' => 18,
            'indicators' => [
                ['name' => 'Research design appropriateness', 'max_points' => 4],
                ['name' => 'Sampling strategy and justification', 'max_points' => 3],
                ['name' => 'Data collection instruments and procedures', 'max_points' => 3],
                ['name' => 'Validity, reliability, or equivalent trustworthiness', 'max_points' => 3],
                ['name' => 'Appropriateness of analytical methods', 'max_points' => 3],
                ['name' => 'Methodological consistency and rigor', 'max_points' => 2],
            ],
        ],
        [
            'name' => 'Data Collection and Data Quality',
            'max_points' => 10,
            'indicators' => [
                ['name' => 'Adequacy and completeness of data', 'max_points' => 3],
                ['name' => 'Data credibility and quality control', 'max_points' => 3],
                ['name' => 'Data management and documentation', 'max_points' => 2],
                ['name' => 'Transparency and reproducibility of procedures', 'max_points' => 2],
            ],
        ],
        [
            'name' => 'Data Analysis and Interpretation',
            'max_points' => 12,
            'indicators' => [
                ['name' => 'Accuracy of data analysis', 'max_points' => 3],
                ['name' => 'Appropriateness of analytical techniques', 'max_points' => 3],
                ['name' => 'Depth of interpretation', 'max_points' => 3],
                ['name' => 'Alignment of analysis with research objectives', 'max_points' => 3],
            ],
        ],
        [
            'name' => 'Discussion and Academic Contribution',
            'max_points' => 8,
            'indicators' => [
                ['name' => 'Critical discussion of findings', 'max_points' => 3],
                ['name' => 'Comparison with existing literature', 'max_points' => 2],
                ['name' => 'Contribution to knowledge and practice', 'max_points' => 3],
            ],
        ],
        [
            'name' => 'Conclusions and Recommendations',
            'max_points' => 4,
            'indicators' => [
                ['name' => 'Conclusions supported by evidence', 'max_points' => 2],
                ['name' => 'Practical and scholarly recommendations', 'max_points' => 2],
            ],
        ],
        [
            'name' => 'Academic Writing and Presentation',
            'max_points' => 3,
            'indicators' => [
                ['name' => 'Structure and coherence', 'max_points' => 1],
                ['name' => 'Academic language', 'max_points' => 1],
                ['name' => 'Tables, figures, and presentation', 'max_points' => 1],
            ],
        ],
        [
            'name' => 'Referencing, Academic Integrity and Research Ethics',
            'max_points' => 3,
            'indicators' => [
                ['name' => 'Citation and referencing accuracy', 'max_points' => 1],
                ['name' => 'Academic integrity and originality', 'max_points' => 1],
                ['name' => 'Ethical compliance', 'max_points' => 1],
            ],
        ],
    ],
];

foreach ($rubrics as $type => $criteria) {
    $existingVersions = (int) Database::scalar(
        'SELECT COUNT(*) FROM rubric_templates WHERE rubric_type = ?',
        [$type]
    );
    if ($existingVersions > 0) {
        echo "SKIP: {$type} already has a version; existing history preserved.\n";
        continue;
    }

    $templateId = RubricModel::createVersion($type, $criteria, null);
    $template = RubricModel::findById($templateId);
    $snapshot = RubricModel::snapshot($template);
    if ($snapshot === '') {
        throw new RuntimeException('Failed to create rubric snapshot for ' . $type);
    }
    echo "SEEDED: {$type} version 1 (100 points).\n";
}

echo "Institutional rubric seeding complete. Existing rubric versions were not modified.\n";
