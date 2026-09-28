<?php
/**
 * Copy to config/config.local.php on each server and adjust.
 * Values here override config/config.php.
 */
return [
    'app' => [
        'env'      => 'production',
        'debug'    => false,
        'base_url' => 'https://kingscityministries.org',
    ],
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'kings_city',
        'user' => 'kings_city_user',
        'pass' => 'CHANGE-ME-strong-password',
    ],
    'mail' => [
        'from_email' => 'no-reply@kingscityministries.org',
    ],
];
