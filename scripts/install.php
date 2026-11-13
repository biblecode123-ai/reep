<?php
declare(strict_types=1);

/**
 * CLI installer (php scripts/install.php).
 * Creates the database, loads schema.sql + seed.sql, prepares storage
 * directories and bootstraps the initial administrator account.
 * Passwords are hashed with password_hash() — never in seed.sql (spec §9).
 */

if (PHP_SAPI !== 'cli') {
    exit("Run from CLI only.\n");
}

require __DIR__ . '/../config/app.php';
require __DIR__ . '/../app/autoload.php';
require __DIR__ . '/../app/helpers/functions.php';

use App\Core\Database;
use App\Services\NumberingService;

echo "=== MWU RPEE System Installer ===\n";

// 1. Connect (and for MySQL, create the database).
$cfg = require BASE_PATH . '/config/database.php';
$ig = static fn (string $table): string =>
    ($cfg['driver'] ?? 'mysql') === 'sqlite' ? "INSERT OR IGNORE INTO $table" : "INSERT IGNORE INTO $table";

if (($cfg['driver'] ?? 'mysql') === 'sqlite') {
    $dbPath = (string) $cfg['database'];
    $isAbsolute = str_contains($dbPath, ':') || str_starts_with($dbPath, '/') || str_starts_with($dbPath, '\\');
    if ($dbPath !== '' && $dbPath !== ':memory:' && !$isAbsolute) {
        $dbPath = BASE_PATH . '/' . ltrim($dbPath, '/\\');
    }
    if (!is_dir(dirname($dbPath))) {
        mkdir(dirname($dbPath), 0775, true);
    }
    $pdo = new PDO('sqlite:' . $dbPath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    echo "[1/6] Using SQLite database {$dbPath}\n";
    $schemaFile = BASE_PATH . '/database/sqlite_schema.sql';
    $seedFile   = BASE_PATH . '/database/sqlite_seed.sql';
} else {
    $pdo = new PDO(
        sprintf('%s:host=%s;port=%d;charset=%s', $cfg['driver'], $cfg['host'], $cfg['port'], $cfg['charset']),
        $cfg['username'],
        $cfg['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $dbName = $cfg['database'];
    echo "[1/6] Creating database {$dbName} if missing…\n";
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbName`");
    $schemaFile = BASE_PATH . '/database/schema.sql';
    $seedFile   = BASE_PATH . '/database/seed.sql';
}

// 2. Schema.
echo "[2/6] Loading " . basename($schemaFile) . "…\n";
$pdo->exec((string) file_get_contents($schemaFile));

// 3. Seed.
echo "[3/6] Loading " . basename($seedFile) . "…\n";
$pdo->exec((string) file_get_contents($seedFile));

// 4. Seed current-year number sequences.
echo "[4/6] Seeding number sequences for " . date('Y') . "…\n";
foreach (['research_call', 'research_application', 'research_project', 'ethics_application',
          'repository_record', 'progress_report', 'integrity_case', 'appeal'] as $key) {
    Database::pdo()->exec(
        $ig('number_sequences') . " (seq_key, seq_year, next_value) VALUES ('$key', " . date('Y') . ', 1)'
    );
}

// 5. Storage directories (spec §91).
echo "[5/6] Preparing storage directories…\n";
foreach (STORAGE_SUBDIRS as $dir) {
    $path = STORAGE_PATH . '/' . $dir;
    if (!is_dir($path)) {
        mkdir($path, 0775, true);
    }
}
foreach (['research', 'ethics', 'publication', 'extension', 'repository', 'private/logs'] as $dir) {
    $path = STORAGE_PATH . '/' . $dir;
    if (!is_dir($path)) {
        mkdir($path, 0775, true);
    }
}
if (!is_dir(STORAGE_PATH . '/.htaccess')) {
    @file_put_contents(STORAGE_PATH . '/.htaccess', "Require all denied\n");
}

// 6. Initial users.
echo "[6/6] Creating initial users…\n";
$readline = static function (string $prompt, ?string $default = null): string {
    if (function_exists('readline')) {
        $in = (string) readline($prompt . ($default !== null ? " [$default]" : '') . ': ');
    } else {
        // Windows CLI fallback (readline is often unavailable).
        echo $prompt . ($default !== null ? " [$default]" : '') . ': ';
        $in = (string) fgets(STDIN);
    }
    $in = trim($in);
    return $in !== '' ? $in : (string) $default;
};

$adminEmail = $readline('Admin email', 'admin@mwu.edu.et');
$adminPass  = $readline('Admin password (min 8 chars)', 'ChangeMe123!');
$adminName  = $readline('Admin full name', 'System Administrator');

$hash = password_hash($adminPass, PASSWORD_BCRYPT, ['cost' => 12]);
$pdo->prepare($ig('colleges') . ' (name, code) VALUES (?, ?)')
    ->execute(['RPEE Office', 'RPEE']);
$collegeId = (int) $pdo->lastInsertId();
$pdo->prepare($ig('departments') . ' (college_id, name, code) VALUES (?, ?, ?)')
    ->execute([$collegeId, 'Research Management', 'RM']);
$deptId = (int) $pdo->lastInsertId();

$stmt = $pdo->prepare(
    'INSERT INTO users (employee_id, full_name, email, password_hash, status, position_title, college_id, department_id, must_change_password)
     VALUES (?, ?, ?, ?, "active", ?, ?, ?, 1)'
);
$stmt->execute(['EMP-0001', $adminName, $adminEmail, $hash, 'System Administrator', $collegeId, $deptId]);
$adminId = (int) $pdo->lastInsertId();
$roleId  = (int) $pdo->query("SELECT id FROM roles WHERE name = 'system_admin'")->fetchColumn();
$pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)')->execute([$adminId, $roleId]);
echo "    Admin created: {$adminEmail}\n";

$mkSample = strtolower((string) readline('Create sample org data + users (college, coordinators, researchers, evaluator)? [yes/no]: '));
if ($mkSample === 'yes' || $mkSample === 'y') {
    // Canonical MWU structure (shared with seed_roles.php / update_org_structure.php).
    $STRUCT = require __DIR__ . '/org_structure.php';
    $collegeIds = [];
    foreach ($STRUCT['colleges'] as $code => [$name, $legacy]) {
        $pdo->prepare(Database::insertIgnore('colleges') . ' (name, code) VALUES (?, ?)')
            ->execute([$name, $code]);
        $collegeIds[$code] = (int) $pdo->query("SELECT id FROM colleges WHERE code = " . $pdo->quote($code))->fetchColumn();
    }
    foreach ($STRUCT['departments'] as $cc => $depts) {
        foreach ($depts as [$dName, $dCode]) {
            $pdo->prepare(Database::insertIgnore('departments') . ' (college_id, name, code) VALUES (?, ?, ?)')
                ->execute([$collegeIds[$cc], $dName, $dCode]);
        }
    }
    $deptIds = [];
    foreach ($STRUCT['departments'] as $cc => $depts) {
        foreach ($depts as [$dName, $dCode]) {
            $deptIds[$dCode] = (int) $pdo->query('SELECT id FROM departments WHERE code = ' . $pdo->quote($dCode) . ' AND college_id = ' . $collegeIds[$cc])->fetchColumn();
        }
    }
    $people = [
        ['director@mwu.edu.et', 'Dr. Alemu Bekele', 'rpee_director', 'RPEE Director', 'RPEE', 'RM'],
        ['officer@mwu.edu.et', 'Ms. Hanna Tesfaye', 'rpee_officer', 'RPEE Officer', 'RPEE', 'RM'],
        ['ccoordinator@mwu.edu.et', 'Dr. Kebede Worku', 'college_coordinator', 'College Research Coordinator', 'CNCS', 'MATH'],
        ['dcoordinator@mwu.edu.et', 'Mr. Solomon Tadesse', 'dept_coordinator', 'Department Research Coordinator', 'CC', 'CS'],
        ['researcher@mwu.edu.et', 'Dr. Tigist Ayele', 'researcher', 'Researcher', 'CC', 'CS'],
        ['evaluator@mwu.edu.et', 'Dr. Yonas Girma', 'evaluator', 'Evaluator', 'CANR', 'ANSC'],
        ['finance@mwu.edu.et', 'Mr. Daniel Mekonnen', 'finance_officer', 'Finance Officer', 'RPEE', 'RM'],
        ['ethics@mwu.edu.et', 'Dr. Marta Abebe', 'ethics_reviewer', 'Ethics Reviewer', 'CSSH', 'SOC'],
    ];
    $num = 2;
    foreach ($people as [$email, $name, $role, $title, $cc, $dc]) {
        $cid = (int) $pdo->query("SELECT id FROM colleges WHERE code = '$cc'")->fetchColumn();
        $did = $dc ? (int) $pdo->query("SELECT id FROM departments WHERE code = '$dc'")->fetchColumn() : null;
        $hash2 = password_hash('Password123!', PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $pdo->prepare(
            'INSERT INTO users (employee_id, full_name, email, password_hash, status, position_title, college_id, department_id, must_change_password)
             VALUES (?, ?, ?, ?, "active", ?, ?, ?, 1)'
        );
        $stmt->execute([sprintf('EMP-%04d', $num), $name, $email, $hash2, $title, $cid, $did]);
        $uid = (int) $pdo->lastInsertId();
        $rid = (int) $pdo->query("SELECT id FROM roles WHERE name = '$role'")->fetchColumn();
        $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)')->execute([$uid, $rid]);
        if ($role === 'researcher') {
            $pdo->prepare('INSERT INTO researchers (user_id) VALUES (?)')->execute([$uid]);
        }
        $num++;
    }
    echo "    Sample users created (password: Password123! — must change at first login).\n";
}

echo "\nDone. Web root: public/  ·  Default URLs: index.php, login.php\n";
echo "Next: log in, create your colleges/departments/users (or keep the samples).\n";
