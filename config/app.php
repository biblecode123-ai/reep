<?php
declare(strict_types=1);

/**
 * MWU RPEE — Application configuration.
 * Institutional text values (university name etc.) come from system_settings, NOT here.
 */

define('APP_NAME', 'MWU RPEE — Research Management System');
define('APP_ENV', getenv('APP_ENV') ?: 'development');       // production | development
define('APP_DEBUG', APP_ENV === 'development');
define('APP_TIMEZONE', 'Africa/Addis_Ababa');

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('PUBLIC_PATH', BASE_PATH . '/public');
define('STORAGE_PATH', BASE_PATH . '/storage');
define('LOG_PATH', STORAGE_PATH . '/private/logs');

// Subdirectories created by the document service (spec §91, §64).
define('STORAGE_SUBDIRS', ['research', 'ethics', 'publication', 'extension', 'repository', 'private']);

date_default_timezone_set(APP_TIMEZONE);
