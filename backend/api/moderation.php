<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

$db = getDb();
$method = currentMethod();

function moderationList(PDO $db): array
{
    $stmt = $db->query(
        'SELECT mq.*, m.subject, m.body, m.message_type, m.status AS message_status, m.created_at AS message_created_at,
                su.name AS sender_user_name, sb.display_name AS sender_box_name,
                moderator.name AS moderator_name
         FROM moderation_queue mq
         JOIN messages m ON m.id = mq.message_id
         LEFT JOIN users su ON su.id = m.sender_user_id
         LEFT JOIN anonymous_boxes sb ON sb.id = m.sender_box_id
         LEFT JOIN users moderator ON moderator.id = mq.moderator_id
         WHERE mq.status = "pending" OR mq.anonymity_violation = 1
         ORDER BY mq.created_at DESC'
    );

    $items = [];

    foreach ($stmt->fetchAll() as $row) {
        $row['sender_display_name'] = messageSenderDisplay($row);
        $items[] = $row;
    }

    return $items;
}

function ensureMessageExists(PDO $db, int $messageId): void
{
    $stmt = $db->prepare('SELECT id FROM messages WHERE id = :id');
    $stmt->execute(['id' => $messageId]);

    if (!$stmt->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Message not found.'], 404);
    }
}

if ($method === 'GET') {
    requirePrivileged();
    jsonResponse(['success' => true, 'items' => moderationList($db)]);
}

if ($method === 'POST') {
    $user = requireLogin();
    $input = readJsonInput();
    $action = trim((string) ($input['action'] ?? ''));

    if ($action === 'approve') {
        requirePrivileged();
        $messageId = intValue($input['message_id'] ?? 0);
        requirePositiveId($messageId, 'message_id');
        ensureMessageExists($db, $messageId);

        $db->beginTransaction();
        $db->prepare("UPDATE messages SET status = 'approved' WHERE id = :id")->execute(['id' => $messageId]);
        $db->prepare(
            "UPDATE moderation_queue
             SET status = 'approved', moderator_id = :moderator_id, reviewed_at = NOW()
             WHERE message_id = :message_id"
        )->execute(['message_id' => $messageId, 'moderator_id' => (int) $user['id']]);
        $db->commit();

        logAction((int) $user['id'], 'approve_message', ['message_id' => $messageId]);
        jsonResponse(['success' => true]);
    }

    if ($action === 'reject') {
        requirePrivileged();
        $messageId = intValue($input['message_id'] ?? 0);
        $note = trim((string) ($input['moderator_note'] ?? ''));
        requirePositiveId($messageId, 'message_id');
        ensureMessageExists($db, $messageId);

        $db->beginTransaction();
        $db->prepare("UPDATE messages SET status = 'rejected' WHERE id = :id")->execute(['id' => $messageId]);
        $db->prepare(
            "UPDATE moderation_queue
             SET status = 'rejected', moderator_id = :moderator_id, moderator_note = :note, reviewed_at = NOW()
             WHERE message_id = :message_id"
        )->execute([
            'message_id' => $messageId,
            'moderator_id' => (int) $user['id'],
            'note' => $note,
        ]);
        $db->commit();

        logAction((int) $user['id'], 'reject_message', ['message_id' => $messageId, 'note' => $note]);
        jsonResponse(['success' => true]);
    }

    if ($action === 'bulk_approve') {
        requirePrivileged();
        $messageIds = is_array($input['message_ids'] ?? null) ? array_unique(array_map('intval', $input['message_ids'])) : [];

        if (count($messageIds) === 0) {
            jsonResponse(['success' => false, 'message' => 'No messages selected.'], 400);
        }

        $db->beginTransaction();
        $messageStmt = $db->prepare("UPDATE messages SET status = 'approved' WHERE id = :id");
        $queueStmt = $db->prepare(
            "UPDATE moderation_queue
             SET status = 'approved', moderator_id = :moderator_id, reviewed_at = NOW()
             WHERE message_id = :message_id"
        );

        foreach ($messageIds as $messageId) {
            if ($messageId <= 0) {
                continue;
            }

            $messageStmt->execute(['id' => $messageId]);
            $queueStmt->execute(['message_id' => $messageId, 'moderator_id' => (int) $user['id']]);
        }

        $db->commit();
        logAction((int) $user['id'], 'approve_message', ['bulk' => true, 'message_ids' => $messageIds]);
        jsonResponse(['success' => true]);
    }

    if ($action === 'flag_violation') {
        $messageId = intValue($input['message_id'] ?? 0);
        $note = trim((string) ($input['note'] ?? ''));
        requirePositiveId($messageId, 'message_id');
        ensureMessageExists($db, $messageId);

        if (!isPrivileged($user) && !isMessageRecipient($db, $messageId, (int) $user['id'])) {
            jsonResponse(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $stmt = $db->prepare(
            'INSERT INTO moderation_queue
                (message_id, status, moderator_note, anonymity_violation, created_at)
             VALUES
                (:message_id, "pending", :note, 1, NOW())
             ON DUPLICATE KEY UPDATE
                anonymity_violation = 1,
                moderator_note = COALESCE(NULLIF(:note_update, ""), moderator_note)'
        );
        $stmt->execute([
            'message_id' => $messageId,
            'note' => $note,
            'note_update' => $note,
        ]);

        logAction((int) $user['id'], 'flag_anonymity_violation', ['message_id' => $messageId]);
        jsonResponse(['success' => true]);
    }

    jsonResponse(['success' => false, 'message' => 'Invalid moderation action.'], 400);
}

jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
