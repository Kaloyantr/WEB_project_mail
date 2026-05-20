<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

$db = getDb();
$method = currentMethod();

function anonymousBoxPayload(array $box, bool $showRealUser): array
{
    $payload = [
        'id' => (int) $box['id'],
        'display_name' => $box['display_name'],
        'topic_id' => $box['topic_id'] !== null ? (int) $box['topic_id'] : null,
        'topic_title' => $box['topic_title'] ?? null,
        'created_at' => $box['created_at'] ?? null,
    ];

    if ($showRealUser) {
        $payload['real_user_id'] = (int) $box['real_user_id'];
        $payload['real_user_name'] = $box['real_user_name'];
        $payload['real_user_email'] = $box['real_user_email'];
    }

    return $payload;
}

if ($method === 'GET') {
    $user = requireLogin();
    $showRealUser = isPrivileged($user);
    $mine = (string) ($_GET['mine'] ?? '') === '1';

    $sql = 'SELECT ab.*, u.name AS real_user_name, u.email AS real_user_email, t.title AS topic_title
            FROM anonymous_boxes ab
            JOIN users u ON u.id = ab.real_user_id
            LEFT JOIN topics t ON t.id = ab.topic_id';
    $params = [];

    if ($mine) {
        $sql .= ' WHERE ab.real_user_id = :user_id';
        $params['user_id'] = (int) $user['id'];
    }

    $sql .= ' ORDER BY ab.display_name ASC';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    $items = array_map(
        static fn(array $box): array => anonymousBoxPayload($box, $showRealUser),
        $stmt->fetchAll()
    );

    jsonResponse(['success' => true, 'items' => $items]);
}

if ($method === 'POST') {
    $currentUser = requirePrivileged();
    $input = readJsonInput();
    $displayName = trim((string) ($input['display_name'] ?? ''));
    $realUserId = intValue($input['real_user_id'] ?? 0);
    $topicId = intValue($input['topic_id'] ?? 0);
    $topicId = $topicId > 0 ? $topicId : null;

    if ($displayName === '') {
        jsonResponse(['success' => false, 'message' => 'Display name is required.'], 400);
    }

    if (!fetchUserById($db, $realUserId)) {
        jsonResponse(['success' => false, 'message' => 'Real user not found.'], 404);
    }

    if ($topicId !== null && !getTopic($db, $topicId)) {
        jsonResponse(['success' => false, 'message' => 'Topic not found.'], 404);
    }

    $stmt = $db->prepare(
        'INSERT INTO anonymous_boxes (display_name, real_user_id, topic_id, created_by)
         VALUES (:display_name, :real_user_id, :topic_id, :created_by)'
    );
    $stmt->execute([
        'display_name' => $displayName,
        'real_user_id' => $realUserId,
        'topic_id' => $topicId,
        'created_by' => (int) $currentUser['id'],
    ]);

    $id = (int) $db->lastInsertId();
    logAction((int) $currentUser['id'], 'create_anonymous_box', ['box_id' => $id]);

    jsonResponse(['success' => true, 'box' => anonymousBoxPayload(getAnonymousBox($db, $id), true)], 201);
}

if ($method === 'PUT') {
    $currentUser = requirePrivileged();
    $input = readJsonInput();
    $id = getRequestId($input);
    requirePositiveId($id);

    if (!getAnonymousBox($db, $id)) {
        jsonResponse(['success' => false, 'message' => 'Anonymous box not found.'], 404);
    }

    $displayName = trim((string) ($input['display_name'] ?? ''));
    $realUserId = intValue($input['real_user_id'] ?? 0);
    $topicId = intValue($input['topic_id'] ?? 0);
    $topicId = $topicId > 0 ? $topicId : null;

    if ($displayName === '') {
        jsonResponse(['success' => false, 'message' => 'Display name is required.'], 400);
    }

    if (!fetchUserById($db, $realUserId)) {
        jsonResponse(['success' => false, 'message' => 'Real user not found.'], 404);
    }

    if ($topicId !== null && !getTopic($db, $topicId)) {
        jsonResponse(['success' => false, 'message' => 'Topic not found.'], 404);
    }

    $stmt = $db->prepare(
        'UPDATE anonymous_boxes
         SET display_name = :display_name, real_user_id = :real_user_id, topic_id = :topic_id
         WHERE id = :id'
    );
    $stmt->execute([
        'id' => $id,
        'display_name' => $displayName,
        'real_user_id' => $realUserId,
        'topic_id' => $topicId,
    ]);

    logAction((int) $currentUser['id'], 'update_anonymous_box', ['box_id' => $id]);
    jsonResponse(['success' => true, 'box' => anonymousBoxPayload(getAnonymousBox($db, $id), true)]);
}

if ($method === 'DELETE') {
    $currentUser = requirePrivileged();
    $input = readJsonInput();
    $id = getRequestId($input);
    requirePositiveId($id);

    if (!getAnonymousBox($db, $id)) {
        jsonResponse(['success' => false, 'message' => 'Anonymous box not found.'], 404);
    }

    try {
        $db->prepare('DELETE FROM anonymous_boxes WHERE id = :id')->execute(['id' => $id]);
    } catch (PDOException $exception) {
        jsonResponse(['success' => false, 'message' => 'Anonymous box is used by existing records.'], 409);
    }

    logAction((int) $currentUser['id'], 'delete_anonymous_box', ['box_id' => $id]);
    jsonResponse(['success' => true]);
}

jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
