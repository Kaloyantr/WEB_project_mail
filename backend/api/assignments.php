<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

$db = getDb();
$method = currentMethod();

function assignmentQuery(): string
{
    return 'SELECT ra.*, t.title AS topic_title,
                   reviewer.name AS reviewer_name, reviewer.email AS reviewer_email,
                   ab.display_name AS anonymous_box_name,
                   assigner.name AS assigned_by_name
            FROM review_assignments ra
            JOIN topics t ON t.id = ra.topic_id
            JOIN users reviewer ON reviewer.id = ra.reviewer_user_id
            JOIN anonymous_boxes ab ON ab.id = ra.anonymous_box_id
            JOIN users assigner ON assigner.id = ra.assigned_by';
}

if ($method === 'GET') {
    $user = requireLogin();
    $topicId = intValue($_GET['topic_id'] ?? 0);
    $mine = (string) ($_GET['mine'] ?? '') === '1';

    if ($topicId > 0) {
        if (!userCanAccessTopic($db, $user, $topicId)) {
            jsonResponse(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $sql = assignmentQuery() . ' WHERE ra.topic_id = :topic_id';
        $params = ['topic_id' => $topicId];

        if ($mine) {
            $sql .= ' AND ra.reviewer_user_id = :user_id';
            $params['user_id'] = (int) $user['id'];
        }

        $sql .= ' ORDER BY ra.created_at ASC';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        if ($mine || !isPrivileged($user)) {
            $rows = array_map(static function (array $row): array {
                return [
                    'id' => (int) $row['id'],
                    'topic_id' => (int) $row['topic_id'],
                    'topic_title' => $row['topic_title'],
                    'anonymous_box_id' => (int) $row['anonymous_box_id'],
                    'anonymous_box_name' => $row['anonymous_box_name'],
                    'deadline' => $row['deadline'],
                    'created_at' => $row['created_at'],
                ];
            }, $rows);
        }

        jsonResponse(['success' => true, 'items' => $rows]);
    }

    if ($mine) {
        $stmt = $db->prepare(assignmentQuery() . ' WHERE ra.reviewer_user_id = :user_id ORDER BY ra.deadline ASC');
        $stmt->execute(['user_id' => (int) $user['id']]);
    } elseif (isPrivileged($user)) {
        $stmt = $db->query(assignmentQuery() . ' ORDER BY ra.deadline ASC');
    } else {
        $stmt = $db->prepare(assignmentQuery() . ' WHERE ra.reviewer_user_id = :user_id ORDER BY ra.deadline ASC');
        $stmt->execute(['user_id' => (int) $user['id']]);
    }

    jsonResponse(['success' => true, 'items' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    $currentUser = requirePrivileged();
    $input = readJsonInput();
    $topicId = intValue($input['topic_id'] ?? 0);
    $reviewerUserId = intValue($input['reviewer_user_id'] ?? 0);
    $deadline = trim((string) ($input['deadline'] ?? ''));

    if (!getTopic($db, $topicId)) {
        jsonResponse(['success' => false, 'message' => 'Topic not found.'], 404);
    }

    if (!fetchUserById($db, $reviewerUserId)) {
        jsonResponse(['success' => false, 'message' => 'Reviewer not found.'], 404);
    }

    if ($deadline === '') {
        jsonResponse(['success' => false, 'message' => 'Deadline is required.'], 400);
    }

    $countStmt = $db->prepare('SELECT COUNT(*) AS total FROM review_assignments WHERE topic_id = :topic_id');
    $countStmt->execute(['topic_id' => $topicId]);
    $reviewerNumber = (int) $countStmt->fetch()['total'] + 1;
    $displayName = 'Reviewer ' . $reviewerNumber;

    $db->beginTransaction();
    try {
        $boxStmt = $db->prepare(
            'INSERT INTO anonymous_boxes (display_name, real_user_id, topic_id, created_by)
             VALUES (:display_name, :real_user_id, :topic_id, :created_by)'
        );
        $boxStmt->execute([
            'display_name' => $displayName,
            'real_user_id' => $reviewerUserId,
            'topic_id' => $topicId,
            'created_by' => (int) $currentUser['id'],
        ]);
        $boxId = (int) $db->lastInsertId();

        $assignmentStmt = $db->prepare(
            'INSERT INTO review_assignments (topic_id, reviewer_user_id, anonymous_box_id, assigned_by, deadline)
             VALUES (:topic_id, :reviewer_user_id, :anonymous_box_id, :assigned_by, :deadline)'
        );
        $assignmentStmt->execute([
            'topic_id' => $topicId,
            'reviewer_user_id' => $reviewerUserId,
            'anonymous_box_id' => $boxId,
            'assigned_by' => (int) $currentUser['id'],
            'deadline' => $deadline,
        ]);
        $assignmentId = (int) $db->lastInsertId();
        $db->commit();
    } catch (Throwable $exception) {
        $db->rollBack();
        jsonResponse(['success' => false, 'message' => 'Could not assign reviewer.'], 400);
    }

    logAction((int) $currentUser['id'], 'assign_reviewer', [
        'assignment_id' => $assignmentId,
        'topic_id' => $topicId,
        'reviewer_user_id' => $reviewerUserId,
    ]);

    $stmt = $db->prepare(assignmentQuery() . ' WHERE ra.id = :id');
    $stmt->execute(['id' => $assignmentId]);

    jsonResponse(['success' => true, 'assignment' => $stmt->fetch()], 201);
}

if ($method === 'DELETE') {
    $currentUser = requirePrivileged();
    $input = readJsonInput();
    $id = getRequestId($input);
    requirePositiveId($id);

    $stmt = $db->prepare('SELECT id, anonymous_box_id FROM review_assignments WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $assignment = $stmt->fetch();

    if (!$assignment) {
        jsonResponse(['success' => false, 'message' => 'Assignment not found.'], 404);
    }

    $boxId = (int) $assignment['anonymous_box_id'];

    $db->beginTransaction();
    try {
        $db->prepare('DELETE FROM review_assignments WHERE id = :id')->execute(['id' => $id]);

        $usageStmt = $db->prepare(
            'SELECT
                (SELECT COUNT(*) FROM review_assignments WHERE anonymous_box_id = :box_id) +
                (SELECT COUNT(*) FROM messages WHERE sender_box_id = :box_id) +
                (SELECT COUNT(*) FROM message_recipients WHERE recipient_box_id = :box_id) AS total'
        );
        $usageStmt->execute(['box_id' => $boxId]);
        $usage = (int) ($usageStmt->fetch()['total'] ?? 0);

        if ($usage === 0) {
            $db->prepare('DELETE FROM anonymous_boxes WHERE id = :id')->execute(['id' => $boxId]);
        }

        $db->commit();
    } catch (Throwable $exception) {
        $db->rollBack();
        jsonResponse(['success' => false, 'message' => 'Could not delete assignment.'], 500);
    }

    logAction((int) $currentUser['id'], 'delete_assignment', ['assignment_id' => $id]);
    jsonResponse(['success' => true]);
}

jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
