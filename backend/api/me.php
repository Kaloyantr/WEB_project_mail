<?php

declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';

requireMethod('GET');

$user = getCurrentUser();

if ($user === null) {
    jsonResponse([
        'success' => false,
        'message' => 'Неоторизиран достъп.',
    ], 401);
}

jsonResponse([
    'success' => true,
    'user' => $user,
]);
