<?php

declare(strict_types=1);

define('DB_HOST', 'localhost');
define('DB_NAME', 'mail_system');
define('DB_USER', 'root');
define('DB_PASS', '');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
