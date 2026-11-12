<?php
declare(strict_types=1);

/**
 * Madda Walabu University — canonical organizational structure (§5).
 * Idempotent: safe to re-run. Renames legacy colleges, inserts new
 * colleges/schools and the full department list, and re-homes existing
 * departments (e.g. Computer Science → College of Computing).
 *
 * Run:  php scripts/update_org_structure.php
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Database;

/** Canonical colleges + departments (shared with install.php and seed_roles.php). */
$STRUCT = require __DIR__ . '/org_structure.php';
$COLLEGES = $STRUCT['colleges'];
$DEPARTMENTS = $STRUCT['departments'];

$insertCollege = Database::insertIgnore('colleges') . ' (name, code) VALUES (?, ?)';
$insertDept    = Database::insertIgnore('departments') . ' (college_id, name, code) VALUES (?, ?, ?)';

$renamed = $created = 0;
$collegeIds = [];

foreach ($COLLEGES as $code => [$name, $legacy]) {
    // Match by canonical code, canonical name, then any legacy name.
    $inSql = $legacy ? ' OR name IN (' . implode(',', array_fill(0, count($legacy), '?')) . ')' : '';
    $id = Database::scalar("SELECT id FROM colleges WHERE code = ? OR name = ?$inSql LIMIT 1",
        array_merge([$code, $name], $legacy));
    if ($id !== null) {
        $id = (int) $id;
        $current = Database::one('SELECT name, code FROM colleges WHERE id = ?', [$id]);
        if ($current['name'] !== $name || $current['code'] !== $code) {
            Database::update('colleges', ['name' => $name, 'code' => $code], 'id = ?', [$id]);
            $renamed++;
            echo "  college renamed: {$current['name']} → {$name} ({$code})\n";
        }
    } else {
        Database::run($insertCollege, [$name, $code]);
        $id = (int) Database::scalar('SELECT id FROM colleges WHERE code = ?', [$code]);
        $created++;
        echo "  college created: {$name} ({$code})\n";
    }
    $collegeIds[$code] = $id;
}

$moved = $newDepts = 0;
foreach ($DEPARTMENTS as $cc => $depts) {
    $cid = $collegeIds[$cc];
    foreach ($depts as [$name, $code]) {
        // Re-home by department name (any college) so moves keep their id.
        $id = Database::scalar('SELECT id FROM departments WHERE name = ? LIMIT 1', [$name]);
        if ($id !== null) {
            $id = (int) $id;
            $cur = Database::one('SELECT college_id, code FROM departments WHERE id = ?', [$id]);
            if ((int) $cur['college_id'] !== $cid || $cur['code'] !== $code) {
                Database::update('departments', ['college_id' => $cid, 'code' => $code], 'id = ?', [$id]);
                if ((int) $cur['college_id'] !== $cid) {
                    $moved++;
                }
            }
        } else {
            Database::run($insertDept, [$cid, $name, $code]);
            $newDepts++;
        }
    }
}

$cCount = (int) Database::scalar('SELECT COUNT(*) FROM colleges');
$dCount = (int) Database::scalar('SELECT COUNT(*) FROM departments');
echo "\nDone. Colleges: {$cCount} (renamed {$renamed}, created {$created}) · Departments: {$dCount} (re-homed {$moved}, created {$newDepts})\n";

