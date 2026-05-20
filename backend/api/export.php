<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

$db = getDb();
requireMethod('GET');
$user = requirePrivileged();
$type = trim((string) ($_GET['type'] ?? ''));
$allowedTypes = ['users', 'groups', 'anonymous_boxes', 'assignments', 'topics', 'reviews', 'conversations_by_topic'];

if (!in_array($type, $allowedTypes, true)) {
    jsonResponse(['success' => false, 'message' => 'Invalid export type.'], 400);
}

logAction((int) $user['id'], 'export_data', ['type' => $type]);

csvResponse($type . '.csv', function ($handle) use ($db, $type): void {
    if ($type === 'users') {
        fputcsv($handle, ['id', 'name', 'email', 'role', 'created_at']);
        $stmt = $db->query('SELECT id, name, email, role, created_at FROM users ORDER BY id ASC');
    } elseif ($type === 'groups') {
        fputcsv($handle, ['group_name', 'description', 'user_name', 'user_email']);
        $stmt = $db->query(
            'SELECT g.name AS group_name, g.description, u.name AS user_name, u.email AS user_email
             FROM `groups` g
             LEFT JOIN group_members gm ON gm.group_id = g.id
             LEFT JOIN users u ON u.id = gm.user_id
             ORDER BY g.name, u.name'
        );
    } elseif ($type === 'anonymous_boxes') {
        fputcsv($handle, ['display_name', 'real_user_name', 'real_user_email', 'topic_title']);
        $stmt = $db->query(
            'SELECT ab.display_name, u.name AS real_user_name, u.email AS real_user_email, t.title AS topic_title
             FROM anonymous_boxes ab
             JOIN users u ON u.id = ab.real_user_id
             LEFT JOIN topics t ON t.id = ab.topic_id
             ORDER BY ab.display_name'
        );
    } elseif ($type === 'assignments') {
        fputcsv($handle, ['topic_title', 'reviewer_name', 'reviewer_email', 'anonymous_box', 'deadline']);
        $stmt = $db->query(
            'SELECT t.title AS topic_title, u.name AS reviewer_name, u.email AS reviewer_email,
                    ab.display_name AS anonymous_box, ra.deadline
             FROM review_assignments ra
             JOIN topics t ON t.id = ra.topic_id
             JOIN users u ON u.id = ra.reviewer_user_id
             JOIN anonymous_boxes ab ON ab.id = ra.anonymous_box_id
             ORDER BY ra.deadline'
        );
    } elseif ($type === 'topics') {
        fputcsv($handle, ['title', 'description', 'author_name', 'author_email', 'created_at']);
        $stmt = $db->query(
            'SELECT t.title, t.description, u.name AS author_name, u.email AS author_email, t.created_at
             FROM topics t
             JOIN users u ON u.id = t.author_id
             ORDER BY t.created_at DESC'
        );
    } elseif ($type === 'reviews') {
        fputcsv($handle, ['topic_title', 'reviewer_box_name', 'author_name', 'message_body', 'created_at']);
        $stmt = $db->query(
            "SELECT t.title AS topic_title, ab.display_name AS reviewer_box_name,
                    author.name AS author_name, m.body AS message_body, m.created_at
             FROM messages m
             JOIN conversations c ON c.id = m.conversation_id
             JOIN topics t ON t.id = c.topic_id
             JOIN users author ON author.id = t.author_id
             LEFT JOIN anonymous_boxes ab ON ab.id = m.sender_box_id
             WHERE m.message_type = 'review'
             ORDER BY m.created_at DESC"
        );
    } else {
        fputcsv($handle, ['topic_title', 'conversation_id', 'subject', 'message_sender', 'message_body', 'created_at']);
        $stmt = $db->query(
            'SELECT t.title AS topic_title, c.id AS conversation_id, c.subject,
                    COALESCE(ab.display_name, u.name, "System") AS message_sender,
                    m.body AS message_body, m.created_at
             FROM conversations c
             JOIN topics t ON t.id = c.topic_id
             JOIN messages m ON m.conversation_id = c.id
             LEFT JOIN users u ON u.id = m.sender_user_id
             LEFT JOIN anonymous_boxes ab ON ab.id = m.sender_box_id
             ORDER BY t.title, c.id, m.created_at'
        );
    }

    foreach ($stmt->fetchAll(PDO::FETCH_NUM) as $row) {
        fputcsv($handle, $row);
    }
});
