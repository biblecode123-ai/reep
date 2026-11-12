<?php
declare(strict_types=1);

/**
 * MWU RPEE — Database configuration.
 * Driver: 'sqlite' (default, zero-dependency — spec §2 local install) or 'mysql'.
 * Values may be overridden via environment variables.
 *
 * SQLite: DATABASE is the database file path (relative to BASE_PATH or absolute).
 * MySQL:  host/port/username/password apply as usual.
 */

return [
    'driver'    => getenv('DB_DRIVER')   ?: 'sqlite',
    // SQLite database file (kept under database/ with the schema files).
    'database'  => getenv('DB_NAME')     ?: (BASE_PATH . '/database/mwu_rpee.sqlite'),
    'host'      => getenv('DB_HOST')     ?: '127.0.0.1',
    'port'      => (int) (getenv('DB_PORT') ?: 3306),
    'username'  => getenv('DB_USER')     ?: 'root',
    'password'  => getenv('DB_PASS')     ?: '',
    'charset'   => 'utf8mb4',
    'options'   => [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_STRINGIFY_FETCHES  => false,
    ],
];

