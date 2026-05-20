<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

$db = getDb();
requireMethod('GET');
$user = requireLogin();
$conversationId = intValue($_GET['id'] ?? 0);
requirePositiveId($conversationId, 'conversation_id');

if (!userParticipatesInConversation($db, $conversationId, (int) $user['id']) && !isPrivileged($user)) {
    jsonResponse(['success' => false, 'message' => 'Conversation not found.'], 404);
}

$conversationStmt = $db->prepare(
    'SELECT c.*, t.title AS topic_title
     FROM conversations c
     LEFT JOIN topics t ON t.id = c.topic_id
     WHERE c.id = :id'
);
$conversationStmt->execute(['id' => $conversationId]);
$conversation = $conversationStmt->fetch();

if (!$conversation) {
    jsonResponse(['success' => false, 'message' => 'Conversation not found.'], 404);
}

$statusFilter = isPrivileged($user) ? '' : " AND (m.status NOT IN ('pending', 'rejected') OR m.sender_user_id = :sender_user_id OR sb.real_user_id = :box_user_id)";
$stmt = $db->prepare(
    'SELECT m.*, su.name AS sender_user_name, sb.display_name AS sender_box_name,
            sb.real_user_id AS sender_box_real_user_id
     FROM messages m
     LEFT JOIN users su ON su.id = m.sender_user_id
     LEFT JOIN anonymous_boxes sb ON sb.id = m.sender_box_id
     WHERE m.conversation_id = :conversation_id' . $statusFilter . '
     ORDER BY m.created_at ASC'
);
$params = ['conversation_id' => $conversationId];

if (!isPrivileged($user)) {
    $params['sender_user_id'] = (int) $user['id'];
    $params['box_user_id'] = (int) $user['id'];
}

$stmt->execute($params);
$messages = [];

foreach ($stmt->fetchAll() as $row) {
    $readStmt = $db->prepare(
        'SELECT COUNT(*) AS total, SUM(is_read = 1) AS read_total
         FROM message_recipients
         WHERE message_id = :message_id'
    );
    $readStmt->execute(['message_id' => (int) $row['id']]);
    $read = $readStmt->fetch();
    $currentRecipient = isMessageRecipient($db, (int) $row['id'], (int) $user['id']);

    $messages[] = [
        'id' => (int) $row['id'],
        'conversation_id' => (int) $row['conversation_id'],
        'sender_display_name' => messageSenderDisplay($row),
        'body' => $row['body'],
        'created_at' => $row['created_at'],
        'message_type' => $row['message_type'],
        'status' => $row['status'],
        'is_anonymous' => !empty($row['sender_box_id']),
        'current_user_is_recipient' => $currentRecipient,
        'read_status' => [
            'read' => (int) ($read['read_total'] ?? 0),
            'total' => (int) ($read['total'] ?? 0),
        ],
    ];
}

jsonResponse([
    'success' => true,
    'conversation' => $conversation,
    'items' => $messages,
]);
