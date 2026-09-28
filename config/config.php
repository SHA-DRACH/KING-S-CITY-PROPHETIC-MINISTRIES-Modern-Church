<?php
/**
 * Application configuration.
 *
 * Environment-specific overrides (production DB credentials, base URL, debug)
 * belong in config/config.local.php, which returns an array that is merged on
 * top of these defaults and should never be committed.
 */
$config = [
    'app' => [
        'env'      => 'development',       // 'production' on the live server
        'debug'    => true,                // never true in production
        'base_url' => '',                  // '' = auto-detect, or e.g. 'https://kingscityministries.org'
        'timezone' => 'Africa/Monrovia',
    ],
    'db' => [
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'name'     => 'kings_city',
        'user'     => 'root',
        'pass'     => '',
        'charset'  => 'utf8mb4',
    ],
    'mail' => [
        'from_email' => 'no-reply@kingscityministries.org',
        'from_name'  => "King's City Prophetic Ministries",
    ],
    'security' => [
        'session_name'        => 'KCPM_SESSID',
        'login_window_min'    => 15,  // throttling window for failed logins
        'password_min_length' => 8,
        'reset_token_minutes' => 60,
    ],
];

$local = __DIR__ . '/config.local.php';
if (is_file($local)) {
    $config = array_replace_recursive($config, require $local);
}

return $config;
