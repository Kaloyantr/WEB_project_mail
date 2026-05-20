<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

$db = getDb();
$method = currentMethod();

if ($method === 'GET') {
    if ((string) ($_GET['directory'] ?? '') === '1') {
        requireLogin();
        $stmt = $db->query('SELECT id, name, email FROM users ORDER BY name ASC');
        jsonResponse(['success' => true, 'items' => $stmt->fetchAll()]);
    }

    requirePrivileged();

    $stmt = $db->query('SELECT id, name, email, role, created_at FROM users ORDER BY name ASC');
    jsonResponse(['success' => true, 'items' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    $currentUser = requirePrivileged();
    $input = readJsonInput();
    $name = trim((string) ($input['name'] ?? ''));
    $email = trim((string) ($input['email'] ?? ''));
    $password = (string) ($input['password'] ?? '');
    $role = trim((string) ($input['role'] ?? 'user'));

    if ($name === '') {
        jsonResponse(['success' => false, 'message' => 'Name is required.'], 400);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['success' => false, 'message' => 'Valid email is required.'], 400);
    }

    if ($password === '') {
        jsonResponse(['success' => false, 'message' => 'Password is required.'], 400);
    }

    if (!validateRoleValue($role)) {
        jsonResponse(['success' => false, 'message' => 'Invalid role.'], 400);
    }

    if (emailExists($db, $email)) {
        jsonResponse(['success' => false, 'message' => 'Email already exists.'], 400);
    }

    $stmt = $db->prepare(
        'INSERT INTO users (name, email, password_hash, role) VALUES (:name, :email, :password_hash, :role)'
    );
    $stmt->execute([
        'name' => $name,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'role' => $role,
    ]);

    $id = (int) $db->lastInsertId();
    logAction((int) $currentUser['id'], 'create_user', ['user_id' => $id, 'email' => $email, 'role' => $role]);

    jsonResponse(['success' => true, 'user' => fetchUserById($db, $id)], 201);
}

if ($method === 'PUT') {
    $currentUser = requirePrivileged();
    $input = readJsonInput();
    $id = getRequestId($input);
    requirePositiveId($id);

    $name = trim((string) ($input['name'] ?? ''));
    $email = trim((string) ($input['email'] ?? ''));
    $role = trim((string) ($input['role'] ?? 'user'));
    $password = (string) ($input['password'] ?? '');

    if (!fetchUserById($db, $id)) {
        jsonResponse(['success' => false, 'message' => 'User not found.'], 404);
    }

    if ($name === '') {
        jsonResponse(['success' => false, 'message' => 'Name is required.'], 400);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['success' => false, 'message' => 'Valid email is required.'], 400);
    }

    if (!validateRoleValue($role)) {
        jsonResponse(['success' => false, 'message' => 'Invalid role.'], 400);
    }

    if (emailExists($db, $email, $id)) {
        jsonResponse(['success' => false, 'message' => 'Email already exists.'], 400);
    }

    if ($password !== '') {
        $stmt = $db->prepare(
            'UPDATE users SET name = :name, email = :email, role = :role, password_hash = :password_hash WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);
    } else {
        $stmt = $db->prepare('UPDATE users SET name = :name, email = :email, role = :role WHERE id = :id');
        $stmt->execute([
            'id' => $id,
            'name' => $name,
            'email' => $email,
            'role' => $role,
        ]);
    }

    logAction((int) $currentUser['id'], 'update_user', ['user_id' => $id, 'email' => $email, 'role' => $role]);

    jsonResponse(['success' => true, 'user' => fetchUserById($db, $id)]);
}

if ($method === 'DELETE') {
    $currentUser = requirePrivileged();
    $input = readJsonInput();
    $id = getRequestId($input);
    requirePositiveId($id);

    if ($id === (int) $currentUser['id']) {
        jsonResponse(['success' => false, 'message' => 'You cannot delete your own account.'], 400);
    }

    if (!fetchUserById($db, $id)) {
        jsonResponse(['success' => false, 'message' => 'User not found.'], 404);
    }

    try {
        $stmt = $db->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
    } catch (PDOException $exception) {
        jsonResponse([
            'success' => false,
            'message' => 'User cannot be deleted because related records exist.',
        ], 409);
    }

    logAction((int) $currentUser['id'], 'delete_user', ['user_id' => $id]);

    jsonResponse(['success' => true]);
}

jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
