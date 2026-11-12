<?php
declare(strict_types=1);

/**
 * MWU RPEE — Security configuration (spec §87, §109).
 * Runtime-tunable values (lockout, timeout, upload size) live in system_settings.
 */

return [
    'session' => [
        'name'                  => 'MWURPEESESSID',
        'cookie_httponly'       => true,
        'cookie_samesite'       => 'Lax',
        'cookie_secure'         => false,
        'use_strict_mode'       => true,
        'cookie_lifetime'       => 0,
        'gc_maxlifetime'        => 7200,
        'regenerate_every'      => 300,   // seconds between periodic ID regeneration
    ],
    'password' => [
        'algo'                  => PASSWORD_BCRYPT,
        'cost'                  => 12,
        'min_length'            => 8,
    ],
    'uploads' => [
        // Absolute deny-by-default; extension allowlist also checked against
        // finfo MIME via DocumentService.
        'blocked_extensions'    => ['php', 'php3', 'php4', 'php5', 'php7', 'phtml', 'pht',
                                    'exe', 'sh', 'bat', 'cmd', 'com', 'js', 'jsp', 'asp',
                                    'aspx', 'cgi', 'pl', 'py', 'dll', 'htaccess'],
    ],
];

