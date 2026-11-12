<?php
declare(strict_types=1);

/**
 * One-shot converter: MySQL schema.sql / seed.sql -> SQLite dialect.
 * Run: php database/make_sqlite.php   (writes sqlite_schema.sql + sqlite_seed.sql)
 *
 * Used when the MySQL files change; SQLite is the default runtime driver.
 */

$in  = static fn (string $f): string => file_get_contents(__DIR__ . '/' . $f);
$out = static function (string $f, string $c): void {
    file_put_contents(__DIR__ . '/' . $f, $c);
    echo "wrote $f (" . substr_count($c, "\n") . " lines)\n";
};

function stripComments(string $s): string
{
    $lines = explode("\n", $s);
    $keep  = [];
    foreach ($lines as $ln) {
        $t = ltrim($ln);
        if ($t !== '' && $t[0] === '-' && $t[1] === '-') {
            continue; // drop MySQL comment banners
        }
        $keep[] = $ln;
    }
    $s = implode("\n", $keep);
    $s = preg_replace('/\n{3,}/', "\n\n", $s);
    return trim($s) . "\n";
}

function convertSchema(string $s): string
{
    // Datatypes
    $s = str_replace('BIGINT UNSIGNED NOT NULL AUTO_INCREMENT', 'INTEGER PRIMARY KEY AUTOINCREMENT', $s);
    $s = preg_replace('/TINYINT\(1\)/i', 'INTEGER', $s);
    $s = preg_replace('/(TINY|SMALL|MEDIUM)?INT(eger)?\s+UNSIGNED/i', 'INTEGER', $s);
    $s = preg_replace('/\bINT UNSIGNED\b/i', 'INTEGER', $s);
    $s = preg_replace('/\b(BIG|TINY|SMALL|MEDIUM)?INT(eger)?(\(\d+\))?/i', 'INTEGER', $s);
    $s = preg_replace('/\bDOUBLE\b/i', 'REAL', $s);
    $s = preg_replace('/\bFLOAT\b/i', 'REAL', $s);
    $s = preg_replace('/\bDECIMAL\(\s*(\d+)\s*,\s*(\d+)\s*\)/i', 'NUMERIC', $s);
    $s = str_ireplace('LONGTEXT', 'TEXT', $s);
    $s = str_ireplace('MEDIUMTEXT', 'TEXT', $s);
    $s = str_ireplace('TINYTEXT', 'TEXT', $s);
    $s = preg_replace('/\bVARCHAR\(\s*(\d+)\s*\)/i', 'TEXT', $s);

    // ENUM: `col ENUM('a','b')` -> `col TEXT CHECK (col IN ('a','b'))`
    $s = preg_replace_callback('/(["\w]+)\s+ENUM\s*\(([^)]*)\)/i', function ($m) {
        $col = str_replace('"', '', $m[1]);
        return "$col TEXT CHECK ($col IN ($m[2]))";
    }, $s);

    // Column modifiers MySQL-only
    $s = preg_replace('/\s+COMMENT\s+\'(?:[^\']|\'\')*\'/i', '', $s);              // COMMENT '...'
    $s = preg_replace('/\s+ON UPDATE CURRENT_TIMESTAMP/i', '', $s);                // ON UPDATE CURRENT_TIMESTAMP
    $s = preg_replace('/\bCHARACTER SET \w+/i', '', $s);
    $s = preg_replace('/\bCOLLATE \w+/i', '', $s);
    $s = str_ireplace('CURRENT_TIMESTAMP', "CURRENT_TIMESTAMP", $s);               // supported by SQLite

    // Table options and constraints naming
    $s = preg_replace('/^SET NAMES.*;\s*$/mi', 'PRAGMA foreign_keys = OFF;', $s);
    $s = str_ireplace('SET FOREIGN_KEY_CHECKS = 0;', 'PRAGMA foreign_keys = OFF;', $s);
    $s = str_ireplace('SET FOREIGN_KEY_CHECKS = 1;', 'PRAGMA foreign_keys = ON;', $s);
    $s = preg_replace('/\)\s*ENGINE\s*=\s*InnoDB[^;]*/i', ');', $s);
    $s = preg_replace('/\bCONSTRAINT\s+`?(\w+)`?\s+FOREIGN KEY/i', 'FOREIGN KEY', $s);
    $s = preg_replace('/\bUNIQUE KEY\s+`?(\w+)`?/i', 'CONSTRAINT $1 UNIQUE', $s);
    $s = preg_replace('/(?<!PRIMARY )(?<!UNIQUE )\bKEY\s+`?(\w+)`?\s*\([^)]*\)\s*,?\s*\n?/i', '', $s); // plain secondary KEY lines
    $s = preg_replace('/\bALTER TABLE\s+`?(\w+)`?\s+ADD\s+FOREIGN KEY[^;]*;/i', '-- sqlite: $1 FK via ALTER omitted (column-level integrity kept in app layer)', $s);
    $s = preg_replace('/\bUSING\s+BTREE/i', '', $s);
    $s = str_ireplace('`', '"', $s);

    // Normalize whitespace around commas collapsed by KEY removal,
    // then drop table-level PRIMARY KEY (id) — inline INTEGER PRIMARY KEY covers it.
    $s = preg_replace('/PRIMARY KEY \(id\)\s*,\s*/i', '', $s);      // had a trailing comma
    $s = preg_replace('/,\s*PRIMARY KEY \(id\)(?=\s*\))/i', '', $s); // was the last item
    $s = preg_replace('/,\s*\)/', ')', $s);
    $s = preg_replace('/\(\s*,/', '(', $s);
    $s = str_replace(';;', ';', $s);
    return stripComments($s);
}

/** Convert seed.sql: inline @vars as scalar subqueries, add sqlite pragmas. */
function convertSeed(string $s): string
{
    $s = str_ireplace('SET NAMES utf8mb4;', 'PRAGMA foreign_keys = ON;', $s);
    $s = str_replace('`', '"', $s);

    // Collect `SET @var = (SELECT ...);` definitions (MySQL user variables).
    $vars = [];
    $s = preg_replace_callback('/^SET @(\w+)\s*=\s*\((.+)\);\s*$/m', static function ($m) use (&$vars) {
        $vars[$m[1]] = trim($m[2]);
        return '';
    }, $s);

    // Expand nested @refs inside the collected expressions.
    foreach (array_keys($vars) as $k) {
        $vars[$k] = preg_replace_callback('/@(\w+)/', static function ($m) use ($vars) {
            return isset($vars[$m[1]]) ? '(' . $vars[$m[1]] . ')' : $m[0];
        }, $vars[$k]);
    }

    // Substitute every remaining @var at use sites with its scalar subquery.
    $s = preg_replace_callback('/@(\w+)/', static function ($m) use ($vars) {
        return isset($vars[$m[1]]) ? '(' . $vars[$m[1]] . ')' : $m[0];
    }, $s);

    // Strip comment lines: they may contain semicolons that break exec()-splitting.
    return stripComments($s);
}

$schema = convertSchema($in('schema.sql'));
$out('sqlite_schema.sql', "-- Generated from schema.sql by make_sqlite.php — do not edit by hand.\n\n" . $schema . "\n");

$seed = convertSeed($in('seed.sql'));
$out('sqlite_seed.sql', "-- Generated from seed.sql by make_sqlite.php — do not edit by hand.\n\n" . $seed . "\n");

echo "done\n";
