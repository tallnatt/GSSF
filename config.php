<?php
declare(strict_types=1);

/**
 * GSSF Family Match Tracker Configuration
 *
 * Storage drivers:
 * - 'json': Zero-configuration flat-file JSON storage in /data (works immediately on Apache).
 * - 'mysql': MySQL / MariaDB via PDO (import schema.sql first and set credentials below).
 *
 * You can override these settings by creating config.local.php.
 */

$config = [
    'app_name' => 'GSSF Family Match Tracker',
    'storage_driver' => 'json', // 'json' or 'mysql'
    'data_dir' => __DIR__ . '/data',
    'mysql' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'gssf_tracker',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
    'session_name' => 'gssf_session',
    'session_lifetime' => 86400 * 7, // 7 days
];

$localConfig = __DIR__ . '/config.local.php';
if (file_exists($localConfig)) {
    $overrides = require $localConfig;
    if (is_array($overrides)) {
        $config = array_replace_recursive($config, $overrides);
    }
}

return $config;
