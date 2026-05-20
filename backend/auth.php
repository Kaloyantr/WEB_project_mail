<?php

require_once __DIR__ . '/helpers.php';

function require_auth(): void
{
    if (empty($_SESSION['user'])) {
        json_response(['error' => 'Unauthorized'], 401);
    }
}
