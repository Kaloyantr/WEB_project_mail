<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

$db = getDb();
requireMethod('POST');
$user = requirePrivileged();
$importType = trim((string) ($_POST['import_type'] ?? ''));
$allowedTypes = ['users', 'groups', 'topics', 'assignments', 'anonymous_boxes'];

if (!in_array($importType, $allowedTypes, true)) {
    jsonResponse(['success' => false, 'message' => 'Invalid import type.'], 400);
}

$rows = readUploadedCsv();
$errors = [];
$prepared = [];

function findUserByEmail(PDO $db, string $email): ?array
{
    $stmt = $db->prepare('SELECT id, name, email, role FROM users WHERE email = :email');
    $stmt->execute(['email' => $email]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function findTopicByTitle(PDO $db, string $title): ?array
{
    $stmt = $db->prepare('SELECT * FROM topics WHERE title = :title ORDER BY id DESC LIMIT 1');
    $stmt->execute(['title' => $title]);
    $row = $stmt->fetch();

    return $row ?: null;
}

foreach ($rows as $rowInfo) {
    $line = $rowInfo['line'];
    $row = $rowInfo['data'];

    if ($importType === 'users') {
        $name = trim($row['name'] ?? '');
        $email = trim($row['email'] ?? '');
        $role = trim($row['role'] ?? 'user');
        $password = (string) ($row['password'] ?? '');

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !validateRoleValue($role) || $password === '') {
            $errors[] = ['line' => $line, 'message' => 'Invalid user row.'];
            continue;
        }

        if (emailExists($db, $email)) {
            $errors[] = ['line' => $line, 'message' => 'Email already exists.'];
            continue;
        }

        $prepared[] = compact('name', 'email', 'role', 'password');
    }

    if ($importType === 'groups') {
        $groupName = trim($row['group_name'] ?? '');
        $description = trim($row['description'] ?? '');
        $userEmail = trim($row['user_email'] ?? '');
        $member = findUserByEmail($db, $userEmail);

        if ($groupName === '' || !$member) {
            $errors[] = ['line' => $line, 'message' => 'Invalid group row.'];
            continue;
        }

        $prepared[] = compact('groupName', 'description', 'member');
    }

    if ($importType === 'topics') {
        $title = trim($row['title'] ?? '');
        $description = trim($row['description'] ?? '');
        $authorEmail = trim($row['author_email'] ?? '');
        $author = findUserByEmail($db, $authorEmail);

        if ($title === '' || !$author) {
            $errors[] = ['line' => $line, 'message' => 'Invalid topic row.'];
            continue;
        }

        $prepared[] = compact('title', 'description', 'author');
    }

    if ($importType === 'assignments') {
        $topicTitle = trim($row['topic_title'] ?? '');
        $reviewerEmail = trim($row['reviewer_email'] ?? '');
        $deadline = trim($row['deadline'] ?? '');
        $topic = findTopicByTitle($db, $topicTitle);
        $reviewer = findUserByEmail($db, $reviewerEmail);

        if (!$topic || !$reviewer || $deadline === '') {
            $errors[] = ['line' => $line, 'message' => 'Invalid assignment row.'];
            continue;
        }

        $prepared[] = compact('topic', 'reviewer', 'deadline');
    }

    if ($importType === 'anonymous_boxes') {
        $displayName = trim($row['display_name'] ?? '');
        $realUserEmail = trim($row['real_user_email'] ?? '');
        $topicTitle = trim($row['topic_title'] ?? '');
        $realUser = findUserByEmail($db, $realUserEmail);
        $topic = $topicTitle !== '' ? findTopicByTitle($db, $topicTitle) : null;

        if ($displayName === '' || !$realUser || ($topicTitle !== '' && !$topic)) {
            $errors[] = ['line' => $line, 'message' => 'Invalid anonymous box row.'];
            continue;
        }

        $prepared[] = compact('displayName', 'realUser', 'topic');
    }
}

if (count($errors) > 0) {
    jsonResponse(['success' => false, 'errors' => $errors], 400);
}

$db->beginTransaction();
try {
    if ($importType === 'users') {
        $stmt = $db->prepare(
            'INSERT INTO users (name, email, role, password_hash) VALUES (:name, :email, :role, :password_hash)'
        );

        foreach ($prepared as $row) {
            $stmt->execute([
                'name' => $row['name'],
                'email' => $row['email'],
                'role' => $row['role'],
                'password_hash' => password_hash($row['password'], PASSWORD_DEFAULT),
            ]);
        }
    }

    if ($importType === 'groups') {
        foreach ($prepared as $row) {
            $stmt = $db->prepare('SELECT id FROM `groups` WHERE name = :name');
            $stmt->execute(['name' => $row['groupName']]);
            $group = $stmt->fetch();

            if ($group) {
                $groupId = (int) $group['id'];
            } else {
                $create = $db->prepare(
                    'INSERT INTO `groups` (name, description, created_by) VALUES (:name, :description, :created_by)'
                );
                $create->execute([
                    'name' => $row['groupName'],
                    'description' => $row['description'],
                    'created_by' => (int) $user['id'],
                ]);
                $groupId = (int) $db->lastInsertId();
            }

            $member = $db->prepare(
                'INSERT IGNORE INTO group_members (group_id, user_id) VALUES (:group_id, :user_id)'
            );
            $member->execute(['group_id' => $groupId, 'user_id' => (int) $row['member']['id']]);
        }
    }

    if ($importType === 'topics') {
        $stmt = $db->prepare(
            'INSERT INTO topics (title, description, author_id) VALUES (:title, :description, :author_id)'
        );

        foreach ($prepared as $row) {
            $stmt->execute([
                'title' => $row['title'],
                'description' => $row['description'],
                'author_id' => (int) $row['author']['id'],
            ]);
        }
    }

    if ($importType === 'assignments') {
        foreach ($prepared as $row) {
            $count = $db->prepare('SELECT COUNT(*) AS total FROM review_assignments WHERE topic_id = :topic_id');
            $count->execute(['topic_id' => (int) $row['topic']['id']]);
            $displayName = 'Reviewer ' . ((int) $count->fetch()['total'] + 1);

            $box = $db->prepare(
                'INSERT INTO anonymous_boxes (display_name, real_user_id, topic_id, created_by)
                 VALUES (:display_name, :real_user_id, :topic_id, :created_by)'
            );
            $box->execute([
                'display_name' => $displayName,
                'real_user_id' => (int) $row['reviewer']['id'],
                'topic_id' => (int) $row['topic']['id'],
                'created_by' => (int) $user['id'],
            ]);

            $assignment = $db->prepare(
                'INSERT INTO review_assignments (topic_id, reviewer_user_id, anonymous_box_id, assigned_by, deadline)
                 VALUES (:topic_id, :reviewer_user_id, :anonymous_box_id, :assigned_by, :deadline)'
            );
            $assignment->execute([
                'topic_id' => (int) $row['topic']['id'],
                'reviewer_user_id' => (int) $row['reviewer']['id'],
                'anonymous_box_id' => (int) $db->lastInsertId(),
                'assigned_by' => (int) $user['id'],
                'deadline' => $row['deadline'],
            ]);
        }
    }

    if ($importType === 'anonymous_boxes') {
        $stmt = $db->prepare(
            'INSERT INTO anonymous_boxes (display_name, real_user_id, topic_id, created_by)
             VALUES (:display_name, :real_user_id, :topic_id, :created_by)'
        );

        foreach ($prepared as $row) {
            $stmt->execute([
                'display_name' => $row['displayName'],
                'real_user_id' => (int) $row['realUser']['id'],
                'topic_id' => $row['topic'] ? (int) $row['topic']['id'] : null,
                'created_by' => (int) $user['id'],
            ]);
        }
    }

    $db->commit();
} catch (Throwable $exception) {
    $db->rollBack();
    jsonResponse(['success' => false, 'message' => 'Import failed.'], 500);
}

logAction((int) $user['id'], 'import_data', ['type' => $importType, 'rows' => count($prepared)]);
jsonResponse(['success' => true, 'imported' => count($prepared)]);
