<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

$db = getDb();
$method = currentMethod();

if ($method === 'GET') {
    $user = requireLogin();
    $id = intValue($_GET['id'] ?? 0);

    if ($id > 0) {
        $topic = getTopic($db, $id);

        if (!$topic) {
            jsonResponse(['success' => false, 'message' => 'Topic not found.'], 404);
        }

        if (!userCanAccessTopic($db, $user, $id)) {
            jsonResponse(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        jsonResponse(['success' => true, 'topic' => $topic]);
    }

    if (isPrivileged($user)) {
        $stmt = $db->query(
            'SELECT t.*, u.name AS author_name, u.email AS author_email
             FROM topics t
             JOIN users u ON u.id = t.author_id
             ORDER BY t.created_at DESC'
        );
    } else {
        $stmt = $db->prepare(
            'SELECT DISTINCT t.*, u.name AS author_name, u.email AS author_email
             FROM topics t
             JOIN users u ON u.id = t.author_id
             LEFT JOIN review_assignments ra ON ra.topic_id = t.id
             WHERE t.author_id = :author_user_id OR ra.reviewer_user_id = :reviewer_user_id
             ORDER BY t.created_at DESC'
        );
        $stmt->execute([
            'author_user_id' => (int) $user['id'],
            'reviewer_user_id' => (int) $user['id'],
        ]);
    }

    jsonResponse(['success' => true, 'items' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    $user = requireLogin();
    $input = readJsonInput();
    $title = trim((string) ($input['title'] ?? ''));
    $description = trim((string) ($input['description'] ?? ''));
    $authorId = isPrivileged($user) ? intValue($input['author_id'] ?? $user['id']) : (int) $user['id'];

    if ($title === '') {
        jsonResponse(['success' => false, 'message' => 'Title is required.'], 400);
    }

    if (!fetchUserById($db, $authorId)) {
        jsonResponse(['success' => false, 'message' => 'Author not found.'], 404);
    }

    $stmt = $db->prepare(
        'INSERT INTO topics (title, description, author_id) VALUES (:title, :description, :author_id)'
    );
    $stmt->execute([
        'title' => $title,
        'description' => $description,
        'author_id' => $authorId,
    ]);

    $id = (int) $db->lastInsertId();
    logAction((int) $user['id'], 'create_topic', ['topic_id' => $id, 'author_id' => $authorId]);

    jsonResponse(['success' => true, 'topic' => getTopic($db, $id)], 201);
}

if ($method === 'PUT') {
    $user = requireLogin();
    $input = readJsonInput();
    $id = getRequestId($input);
    requirePositiveId($id);

    $topic = getTopic($db, $id);

    if (!$topic) {
        jsonResponse(['success' => false, 'message' => 'Topic not found.'], 404);
    }

    if (!isPrivileged($user) && (int) $topic['author_id'] !== (int) $user['id']) {
        jsonResponse(['success' => false, 'message' => 'Forbidden.'], 403);
    }

    $title = trim((string) ($input['title'] ?? ''));
    $description = trim((string) ($input['description'] ?? ''));
    $authorId = isPrivileged($user) ? intValue($input['author_id'] ?? $topic['author_id']) : (int) $topic['author_id'];

    if ($title === '') {
        jsonResponse(['success' => false, 'message' => 'Title is required.'], 400);
    }

    if (!fetchUserById($db, $authorId)) {
        jsonResponse(['success' => false, 'message' => 'Author not found.'], 404);
    }

    $stmt = $db->prepare(
        'UPDATE topics SET title = :title, description = :description, author_id = :author_id WHERE id = :id'
    );
    $stmt->execute([
        'id' => $id,
        'title' => $title,
        'description' => $description,
        'author_id' => $authorId,
    ]);

    logAction((int) $user['id'], 'update_topic', ['topic_id' => $id]);
    jsonResponse(['success' => true, 'topic' => getTopic($db, $id)]);
}

if ($method === 'DELETE') {
    $user = requireLogin();
    $input = readJsonInput();
    $id = getRequestId($input);
    requirePositiveId($id);

    $topic = getTopic($db, $id);

    if (!$topic) {
        jsonResponse(['success' => false, 'message' => 'Topic not found.'], 404);
    }

    if (!isPrivileged($user) && (int) $topic['author_id'] !== (int) $user['id']) {
        jsonResponse(['success' => false, 'message' => 'Forbidden.'], 403);
    }

    if (!isPrivileged($user)) {
        $stmt = $db->prepare('SELECT COUNT(*) AS total FROM review_assignments WHERE topic_id = :topic_id');
        $stmt->execute(['topic_id' => $id]);

        if ((int) $stmt->fetch()['total'] > 0) {
            jsonResponse(['success' => false, 'message' => 'Topic has review assignments.'], 409);
        }
    }

    $db->prepare('DELETE FROM topics WHERE id = :id')->execute(['id' => $id]);
    logAction((int) $user['id'], 'delete_topic', ['topic_id' => $id]);
    jsonResponse(['success' => true]);
}

jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
