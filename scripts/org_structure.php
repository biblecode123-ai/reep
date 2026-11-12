<?php
declare(strict_types=1);

/**
 * Madda Walabu University — canonical organizational structure (§5).
 * Single source of truth shared by install.php, seed_roles.php and
 * update_org_structure.php. Do not rename codes once deployed.
 */

return [
    'colleges' => [
        // code => [canonical name, legacy names renamed to it]
        'RPEE' => ['RPEE Office', []],
        'CANR' => ['College of Agriculture and Natural Resources', ['College of Agriculture and Veterinary Medicine']],
        'CBE'  => ['College of Business and Economics', []],
        'COE'  => ['College of Engineering', ['College of Engineering and Technology']],
        'CC'   => ['College of Computing', []],
        'CNCS' => ['College of Natural and Computational Sciences', []],
        'CSSH' => ['College of Social Sciences and Humanities', []],
        'CEBS' => ['College of Education and Behavioral Sciences', []],
        'SHS'  => ['School of Health Sciences', []],
        'SM'   => ['School of Medicine', []],
        'SL'   => ['School of Law', []],
    ],

    'departments' => [
        'CANR' => [['Agricultural Economics', 'AGEC'], ['Animal and Range Science', 'ANRS'], ['Plant Science', 'PLSC'], ['Natural Resources Management', 'NRM'], ['Forestry', 'FOR'], ['Rural Development and Agricultural Extension', 'RDAE']],
        'CBE'  => [['Accounting and Finance', 'ACCFIN'], ['Economics', 'ECON'], ['Management', 'MGMT'], ['Marketing Management', 'MKTG'], ['Tourism Management', 'TOURM']],
        'COE'  => [['Civil Engineering', 'CIVL'], ['Electrical and Computer Engineering', 'ECE'], ['Mechanical Engineering', 'MECH'], ['Construction Technology and Management', 'CTM'], ['Water Resources and Irrigation Engineering', 'WRIE'], ['Surveying Engineering', 'SURV'], ['Architecture', 'ARCH']],
        'CC'   => [['Computer Science', 'CS'], ['Information Technology', 'IT'], ['Information Systems', 'IS'], ['Information Science', 'ISCI']],
        'CNCS' => [['Biology', 'BIO'], ['Chemistry', 'CHEM'], ['Physics', 'PHYS'], ['Mathematics', 'MATH'], ['Statistics', 'STAT'], ['Sport Science', 'SPORT']],
        'CSSH' => [['Afaan Oromo Language and Literature', 'AFO'], ['Amharic Language and Literature', 'AMH'], ['English Language and Literature', 'ENG'], ['Civics and Ethical Studies', 'CES'], ['Geography and Environmental Studies', 'GES'], ['History and Heritage Management', 'HHM'], ['Journalism and Communication', 'JCOM'], ['Sociology', 'SOC']],
        'CEBS' => [['Psychology', 'PSY'], ['Educational Planning and Management', 'EPM'], ['Adult Education', 'ADE'], ['Early Childhood Education', 'ECEC']],
        'SHS'  => [['Nursing', 'NURS'], ['Midwifery', 'MIDW'], ['Public Health', 'PUBH'], ['Medical Laboratory Science', 'MEDLAB']],
        'SM'   => [['Medicine', 'MED']],
        'SL'   => [['Law', 'LAW']],
        'RPEE' => [['Research Management', 'RM']],
    ],
];

