<?php

declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';

requireMethod('POST');

$input = readJsonInput();
$email = trim((string) ($input['email'] ?? ''));
$password = (string) ($input['password'] ?? '');

if ($email === '' || $password === '') {
    jsonResponse([
        'success' => false,
        'message' => 'Email и парола са задължителни.',
    ], 400);
}

$db = getDb();
$statement = $db->prepare(
    'SELECT id, name, email, password_hash, role FROM users WHERE email = :email LIMIT 1'
);
$statement->execute(['email' => $email]);
$user = $statement->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    jsonResponse([
        'success' => false,
        'message' => 'Невалиден имейл или парола.',
    ], 401);
}

$_SESSION['user'] = [
    'id' => (int) $user['id'],
    'name' => $user['name'],
    'email' => $user['email'],
    'role' => $user['role'],
];

logAction((int) $user['id'], 'login', 'User logged in');

jsonResponse([
    'success' => true,
    'user' => $_SESSION['user'],
]);
