<?php
/**
 * GiftAura configuration TEMPLATE.
 *
 * The real file is config/config.php. The easiest way to create it is:
 *     C:\xampp\php\php.exe scripts\setup.php
 * which asks for the database password, tests the connection and writes
 * config/config.php for you.
 *
 * config/config.php is listed in .gitignore and must never be committed,
 * because it contains the database password. This template is committed.
 */
return [
    // Database (MariaDB from XAMPP). The development laptop uses port 3307.
    'db' => [
        'host'     => '127.0.0.1',
        'port'     => 3307,
        'name'     => 'giftaura_db',
        'user'     => 'giftaura_app',
        'password' => 'CHANGE_THIS_PASSWORD',
    ],

    'app' => [
        // URL path where the site lives: http://localhost/giftaura/
        'base_url' => '/giftaura',
        'timezone' => 'Asia/Colombo',
        // true = show the error message on the error page (local development only).
        // Keep false for demonstration and deployment.
        'debug'    => false,
    ],

    'security' => [
        'session_name'          => 'GIFTAURA_SID',
        'session_idle_minutes'  => 30,   // log out after 30 minutes without activity
        'login_max_per_email'   => 5,    // failed logins per email ...
        'login_max_per_ip'      => 20,   // ... or per IP address ...
        'login_window_minutes'  => 15,   // ... within this many minutes
    ],
];
