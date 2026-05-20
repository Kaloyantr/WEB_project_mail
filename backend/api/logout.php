<?php

declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';

requireMethod('POST');

$currentUser = getCurrentUser();

if ($currentUser !== null) {
    logAction((int) $currentUser['id'], 'logout', 'User logged out');
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

jsonResponse([
    'success' => true,
]);
