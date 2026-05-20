<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

$db = getDb();
$method = currentMethod();

function baseMessageSelect(): string
{
    return 'SELECT m.*, c.topic_id,
                   su.name AS sender_user_name,
                   su.email AS sender_user_email,
                   sb.display_name AS sender_box_name,
                   sb.real_user_id AS sender_box_real_user_id
            FROM messages m
            JOIN conversations c ON c.id = m.conversation_id
            LEFT JOIN users su ON su.id = m.sender_user_id
            LEFT JOIN anonymous_boxes sb ON sb.id = m.sender_box_id';
}

function loadMessage(PDO $db, int $messageId): ?array
{
    $stmt = $db->prepare(baseMessageSelect() . ' WHERE m.id = :id');
    $stmt->execute(['id' => $messageId]);
    $message = $stmt->fetch();

    if (!$message) {
        return null;
    }

    $recipients = $db->prepare(
        'SELECT mr.*, u.name AS recipient_user_name, u.email AS recipient_user_email,
                rb.display_name AS recipient_box_name
         FROM message_recipients mr
         LEFT JOIN users u ON u.id = mr.recipient_user_id
         LEFT JOIN anonymous_boxes rb ON rb.id = mr.recipient_box_id
         WHERE mr.message_id = :message_id
         ORDER BY COALESCE(rb.display_name, u.name) ASC'
    );
    $recipients->execute(['message_id' => $messageId]);
    $message['recipients'] = $recipients->fetchAll();
    $message['sender_display_name'] = messageSenderDisplay($message);

    return $message;
}

function recipientDisplayList(array $recipients): string
{
    $names = [];

    foreach ($recipients as $recipient) {
        $names[] = $recipient['recipient_box_name'] ?: $recipient['recipient_user_name'];
    }

    return implode(', ', array_values(array_unique(array_filter($names))));
}

function insertMessageRecipients(PDO $db, int $messageId, array $recipients): void
{
    $stmt = $db->prepare(
        'INSERT INTO message_recipients (message_id, recipient_user_id, recipient_box_id)
         VALUES (:message_id, :recipient_user_id, :recipient_box_id)'
    );

    foreach ($recipients as $recipient) {
        $stmt->execute([
            'message_id' => $messageId,
            'recipient_user_id' => $recipient['user_id'],
            'recipient_box_id' => $recipient['box_id'],
        ]);
    }
}

function collectRecipients(PDO $db, array $input): array
{
    $recipients = [];
    $seenUsers = [];

    $addUser = static function (int $userId, ?int $boxId = null) use (&$recipients, &$seenUsers): void {
        if ($userId <= 0 || isset($seenUsers[$userId])) {
            return;
        }

        $seenUsers[$userId] = true;
        $recipients[] = ['user_id' => $userId, 'box_id' => $boxId];
    };

    foreach (($input['recipient_user_ids'] ?? []) as $userId) {
        if (fetchUserById($db, (int) $userId)) {
            $addUser((int) $userId);
        }
    }

    foreach (($input['recipient_group_ids'] ?? []) as $groupId) {
        $stmt = $db->prepare('SELECT user_id FROM group_members WHERE group_id = :group_id');
        $stmt->execute(['group_id' => (int) $groupId]);

        foreach ($stmt->fetchAll() as $row) {
            $addUser((int) $row['user_id']);
        }
    }

    foreach (($input['recipient_box_ids'] ?? []) as $boxId) {
        $box = getAnonymousBox($db, (int) $boxId);

        if ($box) {
            $addUser((int) $box['real_user_id'], (int) $box['id']);
        }
    }

    return $recipients;
}

function reviewDeadlineBlocked(PDO $db): bool
{
    $stmt = $db->query(
        "SELECT id FROM rules
         WHERE rule_type = 'review_deadline'
           AND is_active = 1
           AND end_date IS NOT NULL
           AND end_date < CURDATE()
         LIMIT 1"
    );

    return (bool) $stmt->fetch();
}

function createMessageRecord(
    PDO $db,
    int $conversationId,
    ?int $senderUserId,
    ?int $senderBoxId,
    string $subject,
    string $body,
    string $messageType,
    string $status,
    ?int $parentMessageId
): int {
    $stmt = $db->prepare(
        'INSERT INTO messages
            (conversation_id, sender_user_id, sender_box_id, subject, body, message_type, status, parent_message_id)
         VALUES
            (:conversation_id, :sender_user_id, :sender_box_id, :subject, :body, :message_type, :status, :parent_message_id)'
    );
    $stmt->execute([
        'conversation_id' => $conversationId,
        'sender_user_id' => $senderUserId,
        'sender_box_id' => $senderBoxId,
        'subject' => $subject,
        'body' => $body,
        'message_type' => $messageType,
        'status' => $status,
        'parent_message_id' => $parentMessageId,
    ]);

    return (int) $db->lastInsertId();
}

function sendReview(PDO $db, array $user, array $input): array
{
    $topicId = intValue($input['topic_id'] ?? 0);
    $senderBoxId = intValue($input['sender_box_id'] ?? 0);
    $body = trim((string) ($input['body'] ?? ''));

    if ($topicId <= 0 || $senderBoxId <= 0 || $body === '') {
        jsonResponse(['success' => false, 'message' => 'Review requires topic, anonymous box and body.'], 400);
    }

    if (reviewDeadlineBlocked($db)) {
        jsonResponse(['success' => false, 'message' => 'Review deadline rule blocks new reviews.'], 400);
    }

    $assignmentStmt = $db->prepare(
        'SELECT ra.*, t.title AS topic_title, t.author_id
         FROM review_assignments ra
         JOIN topics t ON t.id = ra.topic_id
         WHERE ra.topic_id = :topic_id
           AND ra.reviewer_user_id = :reviewer_user_id
           AND ra.anonymous_box_id = :anonymous_box_id
         LIMIT 1'
    );
    $assignmentStmt->execute([
        'topic_id' => $topicId,
        'reviewer_user_id' => (int) $user['id'],
        'anonymous_box_id' => $senderBoxId,
    ]);
    $assignment = $assignmentStmt->fetch();

    if (!$assignment) {
        jsonResponse(['success' => false, 'message' => 'You are not assigned as reviewer with this box.'], 403);
    }

    if (strtotime((string) $assignment['deadline']) < time()) {
        jsonResponse(['success' => false, 'message' => 'Review deadline has expired.'], 400);
    }

    $subject = trim((string) ($input['subject'] ?? ('Review: ' . $assignment['topic_title'])));
    $status = 'pending';

    $db->beginTransaction();
    try {
        $conversationStmt = $db->prepare(
            'INSERT INTO conversations (subject, topic_id) VALUES (:subject, :topic_id)'
        );
        $conversationStmt->execute(['subject' => $subject, 'topic_id' => $topicId]);
        $conversationId = (int) $db->lastInsertId();

        $messageId = createMessageRecord(
            $db,
            $conversationId,
            null,
            $senderBoxId,
            $subject,
            $body,
            'review',
            $status,
            null
        );

        insertMessageRecipients($db, $messageId, [[
            'user_id' => (int) $assignment['author_id'],
            'box_id' => null,
        ]]);
        createModerationQueue($db, $messageId);
        $db->commit();
    } catch (Throwable $exception) {
        $db->rollBack();
        jsonResponse(['success' => false, 'message' => 'Could not send review.'], 500);
    }

    logAction((int) $user['id'], 'send_review', ['message_id' => $messageId, 'topic_id' => $topicId]);

    return loadMessage($db, $messageId);
}

if ($method === 'GET') {
    $user = requireLogin();
    $id = intValue($_GET['id'] ?? 0);

    if ($id > 0) {
        if (!userCanAccessMessage($db, $id, (int) $user['id']) && !isPrivileged($user)) {
            jsonResponse(['success' => false, 'message' => 'Message not found.'], 404);
        }

        if (isMessageRecipient($db, $id, (int) $user['id'])) {
            $stmt = $db->prepare(
                'UPDATE message_recipients
                 SET is_read = 1, read_at = COALESCE(read_at, NOW())
                 WHERE message_id = :message_id AND recipient_user_id = :user_id'
            );
            $stmt->execute(['message_id' => $id, 'user_id' => (int) $user['id']]);
        }

        $message = loadMessage($db, $id);
        $message['recipients_display'] = recipientDisplayList($message['recipients']);
        $message['is_anonymous'] = !empty($message['sender_box_id']);
        $message['current_user_is_recipient'] = isMessageRecipient($db, $id, (int) $user['id']);
        jsonResponse(['success' => true, 'message' => $message]);
    }

    $folder = (string) ($_GET['folder'] ?? 'inbox');

    if ($folder === 'sent') {
        $stmt = $db->prepare(
            baseMessageSelect() . '
             WHERE m.sender_user_id = :sender_user_id OR sb.real_user_id = :box_user_id
             ORDER BY m.created_at DESC'
        );
        $stmt->execute([
            'sender_user_id' => (int) $user['id'],
            'box_user_id' => (int) $user['id'],
        ]);
        $rows = $stmt->fetchAll();
        $items = [];

        foreach ($rows as $row) {
            $message = loadMessage($db, (int) $row['id']);
            $items[] = [
                'id' => (int) $row['id'],
                'conversation_id' => (int) $row['conversation_id'],
                'subject' => $row['subject'],
                'recipients_display' => recipientDisplayList($message['recipients']),
                'created_at' => $row['created_at'],
                'message_type' => $row['message_type'],
                'status' => $row['status'],
            ];
        }

        jsonResponse(['success' => true, 'items' => $items]);
    }

    $statusFilter = isPrivileged($user) ? '' : " AND m.status NOT IN ('pending', 'rejected')";
    $stmt = $db->prepare(
        'SELECT m.*, c.topic_id, mr.is_read,
                su.name AS sender_user_name,
                su.email AS sender_user_email,
                sb.display_name AS sender_box_name,
                sb.real_user_id AS sender_box_real_user_id
         FROM messages m
         JOIN conversations c ON c.id = m.conversation_id
         LEFT JOIN users su ON su.id = m.sender_user_id
         LEFT JOIN anonymous_boxes sb ON sb.id = m.sender_box_id
         JOIN message_recipients mr ON mr.message_id = m.id
         WHERE mr.recipient_user_id = :user_id' . $statusFilter . '
         ORDER BY m.created_at DESC'
    );
    $stmt->execute(['user_id' => (int) $user['id']]);
    $items = [];

    foreach ($stmt->fetchAll() as $row) {
        $items[] = [
            'id' => (int) $row['id'],
            'conversation_id' => (int) $row['conversation_id'],
            'subject' => $row['subject'],
            'sender_display_name' => messageSenderDisplay($row),
            'created_at' => $row['created_at'],
            'is_read' => (int) $row['is_read'],
            'message_type' => $row['message_type'],
            'status' => $row['status'],
        ];
    }

    jsonResponse(['success' => true, 'items' => $items]);
}

if ($method === 'POST') {
    $user = requireLogin();
    $input = readJsonInput();
    $messageType = trim((string) ($input['message_type'] ?? 'normal'));

    if (!in_array($messageType, ['normal', 'review'], true)) {
        jsonResponse(['success' => false, 'message' => 'Invalid message type.'], 400);
    }

    if ($messageType === 'review') {
        jsonResponse(['success' => true, 'message' => sendReview($db, $user, $input)], 201);
    }

    $subject = trim((string) ($input['subject'] ?? ''));
    $body = trim((string) ($input['body'] ?? ''));
    $topicId = intValue($input['topic_id'] ?? 0);
    $senderBoxId = intValue($input['sender_box_id'] ?? 0);
    $senderBoxId = $senderBoxId > 0 ? $senderBoxId : null;

    if ($subject === '' || $body === '') {
        jsonResponse(['success' => false, 'message' => 'Subject and body are required.'], 400);
    }

    if ($senderBoxId !== null) {
        verifyBoxOwner($db, $senderBoxId, (int) $user['id']);
    }

    if ($topicId > 0 && !userCanAccessTopic($db, $user, $topicId)) {
        jsonResponse(['success' => false, 'message' => 'Invalid topic.'], 403);
    }

    $recipients = collectRecipients($db, $input);

    if (count($recipients) === 0) {
        jsonResponse(['success' => false, 'message' => 'At least one recipient is required.'], 400);
    }

    $hasBoxRecipient = count(array_filter($recipients, static fn(array $r): bool => $r['box_id'] !== null)) > 0;
    $status = ($senderBoxId !== null || $hasBoxRecipient) ? 'pending' : 'sent';

    $db->beginTransaction();
    try {
        $conversationStmt = $db->prepare(
            'INSERT INTO conversations (subject, topic_id) VALUES (:subject, :topic_id)'
        );
        $conversationStmt->execute(['subject' => $subject, 'topic_id' => $topicId > 0 ? $topicId : null]);
        $conversationId = (int) $db->lastInsertId();

        $messageId = createMessageRecord(
            $db,
            $conversationId,
            $senderBoxId === null ? (int) $user['id'] : null,
            $senderBoxId,
            $subject,
            $body,
            'normal',
            $status,
            null
        );

        insertMessageRecipients($db, $messageId, $recipients);

        if ($status === 'pending') {
            createModerationQueue($db, $messageId);
        }

        $db->commit();
    } catch (Throwable $exception) {
        $db->rollBack();
        jsonResponse(['success' => false, 'message' => 'Could not send message.'], 500);
    }

    logAction((int) $user['id'], 'send_message', ['message_id' => $messageId]);
    jsonResponse(['success' => true, 'message' => loadMessage($db, $messageId)], 201);
}

if ($method === 'PUT') {
    $user = requireLogin();
    $input = readJsonInput();
    $conversationId = intValue($input['conversation_id'] ?? 0);
    $parentMessageId = intValue($input['parent_message_id'] ?? 0);
    $body = trim((string) ($input['body'] ?? ''));
    $senderBoxId = intValue($input['sender_box_id'] ?? 0);
    $senderBoxId = $senderBoxId > 0 ? $senderBoxId : null;

    if ($conversationId <= 0 || $parentMessageId <= 0 || $body === '') {
        jsonResponse(['success' => false, 'message' => 'Conversation, parent message and body are required.'], 400);
    }

    if (!userParticipatesInConversation($db, $conversationId, (int) $user['id'])) {
        jsonResponse(['success' => false, 'message' => 'Conversation not found.'], 404);
    }

    if ($senderBoxId !== null) {
        verifyBoxOwner($db, $senderBoxId, (int) $user['id']);
    }

    $convStmt = $db->prepare('SELECT * FROM conversations WHERE id = :id');
    $convStmt->execute(['id' => $conversationId]);
    $conversation = $convStmt->fetch();

    if (!$conversation) {
        jsonResponse(['success' => false, 'message' => 'Conversation not found.'], 404);
    }

    $participantsStmt = $db->prepare(
        'SELECT DISTINCT
            COALESCE(sb.real_user_id, m.sender_user_id, mr.recipient_user_id) AS real_user_id,
            CASE WHEN m.sender_box_id IS NOT NULL THEN m.sender_box_id ELSE mr.recipient_box_id END AS box_id
         FROM messages m
         LEFT JOIN anonymous_boxes sb ON sb.id = m.sender_box_id
         LEFT JOIN message_recipients mr ON mr.message_id = m.id
         WHERE m.conversation_id = :conversation_id'
    );
    $participantsStmt->execute(['conversation_id' => $conversationId]);
    $recipients = [];
    $seen = [];

    foreach ($participantsStmt->fetchAll() as $participant) {
        $realUserId = (int) ($participant['real_user_id'] ?? 0);
        $boxId = $participant['box_id'] !== null ? (int) $participant['box_id'] : null;

        if ($realUserId <= 0 || $realUserId === (int) $user['id']) {
            continue;
        }

        $key = $boxId !== null ? 'box:' . $boxId : 'user:' . $realUserId;

        if (!isset($seen[$key])) {
            $seen[$key] = true;
            $recipients[] = ['user_id' => $realUserId, 'box_id' => $boxId];
        }
    }

    if (count($recipients) === 0) {
        jsonResponse(['success' => false, 'message' => 'No conversation recipients found.'], 400);
    }

    $status = $senderBoxId !== null ? 'pending' : 'sent';

    $db->beginTransaction();
    try {
        $messageId = createMessageRecord(
            $db,
            $conversationId,
            $senderBoxId === null ? (int) $user['id'] : null,
            $senderBoxId,
            (string) $conversation['subject'],
            $body,
            'normal',
            $status,
            $parentMessageId
        );
        insertMessageRecipients($db, $messageId, $recipients);

        if ($status === 'pending') {
            createModerationQueue($db, $messageId);
        }

        $db->commit();
    } catch (Throwable $exception) {
        $db->rollBack();
        jsonResponse(['success' => false, 'message' => 'Could not send reply.'], 500);
    }

    logAction((int) $user['id'], 'send_message', ['message_id' => $messageId, 'reply' => true]);
    jsonResponse(['success' => true, 'message' => loadMessage($db, $messageId)], 201);
}

jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
