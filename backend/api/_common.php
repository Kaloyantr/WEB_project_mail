<?php

declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';

function currentMethod(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function requirePrivileged(): array
{
    return requireRole(['moderator', 'admin']);
}

function requireAdmin(): array
{
    return requireRole('admin');
}

function isPrivileged(array $user): bool
{
    return in_array($user['role'] ?? '', ['moderator', 'admin'], true);
}

function intValue($value, int $default = 0): int
{
    if ($value === null || $value === '') {
        return $default;
    }

    return (int) $value;
}

function getRequestId(array $input = []): int
{
    return intValue($_GET['id'] ?? $input['id'] ?? 0);
}

function requirePositiveId(int $id, string $name = 'id'): void
{
    if ($id <= 0) {
        jsonResponse(['success' => false, 'message' => "Invalid {$name}."], 400);
    }
}

function validateRoleValue(string $role): bool
{
    return in_array($role, ['user', 'moderator', 'admin'], true);
}

function fetchUserById(PDO $db, int $id): ?array
{
    $stmt = $db->prepare('SELECT id, name, email, role, created_at FROM users WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function emailExists(PDO $db, string $email, ?int $exceptId = null): bool
{
    $sql = 'SELECT id FROM users WHERE email = :email';
    $params = ['email' => $email];

    if ($exceptId !== null) {
        $sql .= ' AND id <> :id';
        $params['id'] = $exceptId;
    }

    $stmt = $db->prepare($sql . ' LIMIT 1');
    $stmt->execute($params);

    return (bool) $stmt->fetch();
}

function userCanAccessTopic(PDO $db, array $user, int $topicId): bool
{
    if (isPrivileged($user)) {
        return true;
    }

    $stmt = $db->prepare(
        'SELECT t.id
         FROM topics t
         LEFT JOIN review_assignments ra
            ON ra.topic_id = t.id AND ra.reviewer_user_id = :reviewer_user_id
         WHERE t.id = :topic_id AND (t.author_id = :author_user_id OR ra.id IS NOT NULL)
         LIMIT 1'
    );
    $stmt->execute([
        'topic_id' => $topicId,
        'reviewer_user_id' => (int) $user['id'],
        'author_user_id' => (int) $user['id'],
    ]);

    return (bool) $stmt->fetch();
}

function getTopic(PDO $db, int $topicId): ?array
{
    $stmt = $db->prepare(
        'SELECT t.*, u.name AS author_name, u.email AS author_email
         FROM topics t
         JOIN users u ON u.id = t.author_id
         WHERE t.id = :id'
    );
    $stmt->execute(['id' => $topicId]);
    $topic = $stmt->fetch();

    return $topic ?: null;
}

function getAnonymousBox(PDO $db, int $boxId): ?array
{
    $stmt = $db->prepare(
        'SELECT ab.*, u.name AS real_user_name, u.email AS real_user_email, t.title AS topic_title
         FROM anonymous_boxes ab
         JOIN users u ON u.id = ab.real_user_id
         LEFT JOIN topics t ON t.id = ab.topic_id
         WHERE ab.id = :id'
    );
    $stmt->execute(['id' => $boxId]);
    $box = $stmt->fetch();

    return $box ?: null;
}

function verifyBoxOwner(PDO $db, int $boxId, int $userId): array
{
    $box = getAnonymousBox($db, $boxId);

    if (!$box || (int) $box['real_user_id'] !== $userId) {
        jsonResponse(['success' => false, 'message' => 'Invalid anonymous sender box.'], 403);
    }

    return $box;
}

function conversationParticipantQuery(): string
{
    return 'SELECT c.id
            FROM conversations c
            LEFT JOIN messages sm ON sm.conversation_id = c.id
            LEFT JOIN anonymous_boxes sb ON sb.id = sm.sender_box_id
            LEFT JOIN message_recipients mr ON mr.message_id = sm.id
            WHERE c.id = :conversation_id
              AND (
                sm.sender_user_id = :sender_user_id
                OR sb.real_user_id = :box_user_id
                OR mr.recipient_user_id = :recipient_user_id
              )
            LIMIT 1';
}

function userParticipatesInConversation(PDO $db, int $conversationId, int $userId): bool
{
    $stmt = $db->prepare(conversationParticipantQuery());
    $stmt->execute([
        'conversation_id' => $conversationId,
        'sender_user_id' => $userId,
        'box_user_id' => $userId,
        'recipient_user_id' => $userId,
    ]);

    return (bool) $stmt->fetch();
}

function userCanAccessMessage(PDO $db, int $messageId, int $userId): bool
{
    $stmt = $db->prepare(
        'SELECT m.id
         FROM messages m
         LEFT JOIN anonymous_boxes sb ON sb.id = m.sender_box_id
         LEFT JOIN message_recipients mr ON mr.message_id = m.id
         WHERE m.id = :message_id
           AND (
             m.sender_user_id = :sender_user_id
             OR sb.real_user_id = :box_user_id
             OR mr.recipient_user_id = :recipient_user_id
           )
         LIMIT 1'
    );
    $stmt->execute([
        'message_id' => $messageId,
        'sender_user_id' => $userId,
        'box_user_id' => $userId,
        'recipient_user_id' => $userId,
    ]);

    return (bool) $stmt->fetch();
}

function isMessageRecipient(PDO $db, int $messageId, int $userId): bool
{
    $stmt = $db->prepare(
        'SELECT id FROM message_recipients WHERE message_id = :message_id AND recipient_user_id = :user_id LIMIT 1'
    );
    $stmt->execute([
        'message_id' => $messageId,
        'user_id' => $userId,
    ]);

    return (bool) $stmt->fetch();
}

function messageSenderDisplay(array $row): string
{
    if (!empty($row['sender_box_id'])) {
        return $row['sender_box_name'] ?? 'Anonymous';
    }

    return $row['sender_user_name'] ?? 'System';
}

function createModerationQueue(PDO $db, int $messageId, string $status = 'pending', ?string $note = null, int $violation = 0): void
{
    $stmt = $db->prepare(
        'INSERT INTO moderation_queue (message_id, status, moderator_note, anonymity_violation)
         VALUES (:message_id, :status, :note, :violation)
         ON DUPLICATE KEY UPDATE
            status = VALUES(status),
            moderator_note = COALESCE(VALUES(moderator_note), moderator_note),
            anonymity_violation = GREATEST(anonymity_violation, VALUES(anonymity_violation))'
    );
    $stmt->execute([
        'message_id' => $messageId,
        'status' => $status,
        'note' => $note,
        'violation' => $violation,
    ]);
}

function csvResponse(string $filename, callable $writer): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo "\xEF\xBB\xBF";

    $handle = fopen('php://output', 'w');
    $writer($handle);
    fclose($handle);
    exit;
}

function readUploadedCsv(string $fieldName = 'file'): array
{
    if (empty($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(['success' => false, 'message' => 'CSV file is required.'], 400);
    }

    $handle = fopen($_FILES[$fieldName]['tmp_name'], 'r');

    if (!$handle) {
        jsonResponse(['success' => false, 'message' => 'Could not read uploaded CSV.'], 400);
    }

    $header = fgetcsv($handle, 0, ',', '"', '\\');

    if (!$header) {
        fclose($handle);
        jsonResponse(['success' => false, 'message' => 'CSV header row is required.'], 400);
    }

    $header = array_map(static function ($value): string {
        return trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $value));
    }, $header);
    $rows = [];
    $line = 1;

    while (($values = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
        $line++;

        if (count($values) === 1 && trim((string) $values[0]) === '') {
            continue;
        }

        $row = [];

        foreach ($header as $index => $name) {
            $row[$name] = isset($values[$index]) ? trim((string) $values[$index]) : '';
        }

        $rows[] = [
            'line' => $line,
            'data' => $row,
        ];
    }

    fclose($handle);

    return $rows;
}
