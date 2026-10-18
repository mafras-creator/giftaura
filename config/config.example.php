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
        // Full address used in links inside e-mails (no trailing slash).
        // On the development laptop: http://localhost  -  on a web host: https://www.your-domain.lk
        'site_url' => 'http://localhost',
    ],

    // E-mail. Easiest set-up: C:\xampp\php\php.exe scripts\setup_mail.php
    'mail' => [
        // 'file' = TEST MODE: e-mails are saved in storage/mail/ instead of being sent.
        // 'smtp' = really send (e.g. Gmail).   'off' = do not send at all.
        'transport'  => 'file',
        'host'       => 'smtp.gmail.com',
        'port'       => 465,
        'encryption' => 'ssl',               // ssl (port 465) or tls (port 587)
        'username'   => '',                  // your Gmail address
        'password'   => '',                  // a Gmail APP PASSWORD (16 letters), not your normal password
        'from_email' => '',                  // leave empty to use the username
        'from_name'  => 'GiftAura',
        'cafile'     => '',                  // only if certificates fail on Windows, e.g. C:\xampp\apache\bin\curl-ca-bundle.crt
    ],

    'security' => [
        'session_name'          => 'GIFTAURA_SID',
        'session_idle_minutes'  => 30,   // log out after 30 minutes without activity
        'login_max_per_email'   => 5,    // failed logins per email ...
        'login_max_per_ip'      => 20,   // ... or per IP address ...
        'login_window_minutes'  => 15,   // ... within this many minutes
    ],
];
