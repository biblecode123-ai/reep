<?php declare(strict_types=1);

require __DIR__. '/../app/bootstrap.php';

use App\Core\Database;

$settings = [ ['max_pi_proposals', '1', 'research', 'integer', 'Maximum proposals a researcher can lead as PI'], ['max_co_investigator_proposals', '3', 'research', 'integer', 'Maximum proposals a researcher can join as co-investigator'], ];

foreach ($settings as $s)

Database::run("INSERT OR IGNORE INTO system_settings (setting_key, setting_value, setting_group, value_type, description)
ALUES (?, ?, ?, ?, ?)", $s);

}

echo "Settings seeded.
";


