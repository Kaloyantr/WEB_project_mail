<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

$db = getDb();
$method = currentMethod();

function loadGroup(PDO $db, int $id): ?array
{
    $stmt = $db->prepare(
        'SELECT g.*, u.name AS created_by_name
         FROM `groups` g
         JOIN users u ON u.id = g.created_by
         WHERE g.id = :id'
    );
    $stmt->execute(['id' => $id]);
    $group = $stmt->fetch();

    if (!$group) {
        return null;
    }

    $members = $db->prepare(
        'SELECT u.id, u.name, u.email, u.role
         FROM group_members gm
         JOIN users u ON u.id = gm.user_id
         WHERE gm.group_id = :id
         ORDER BY u.name ASC'
    );
    $members->execute(['id' => $id]);
    $group['members'] = $members->fetchAll();

    return $group;
}

function saveGroupMembers(PDO $db, int $groupId, array $memberIds): void
{
    $db->prepare('DELETE FROM group_members WHERE group_id = :group_id')->execute(['group_id' => $groupId]);
    $stmt = $db->prepare('INSERT INTO group_members (group_id, user_id) VALUES (:group_id, :user_id)');
    $uniqueIds = array_values(array_unique(array_map('intval', $memberIds)));

    foreach ($uniqueIds as $userId) {
        if ($userId > 0 && fetchUserById($db, $userId)) {
            $stmt->execute(['group_id' => $groupId, 'user_id' => $userId]);
        }
    }
}

if ($method === 'GET') {
    requireLogin();
    $id = intValue($_GET['id'] ?? 0);

    if ($id > 0) {
        $group = loadGroup($db, $id);

        if (!$group) {
            jsonResponse(['success' => false, 'message' => 'Group not found.'], 404);
        }

        jsonResponse(['success' => true, 'group' => $group]);
    }

    $stmt = $db->query(
        'SELECT g.id, g.name, g.description, g.created_at, COUNT(gm.id) AS member_count
         FROM `groups` g
         LEFT JOIN group_members gm ON gm.group_id = g.id
         GROUP BY g.id
         ORDER BY g.name ASC'
    );
    jsonResponse(['success' => true, 'items' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    $currentUser = requirePrivileged();
    $input = readJsonInput();
    $name = trim((string) ($input['name'] ?? ''));
    $description = trim((string) ($input['description'] ?? ''));
    $memberIds = is_array($input['member_user_ids'] ?? null) ? $input['member_user_ids'] : [];

    if ($name === '') {
        jsonResponse(['success' => false, 'message' => 'Group name is required.'], 400);
    }

    $db->beginTransaction();
    try {
        $stmt = $db->prepare(
            'INSERT INTO `groups` (name, description, created_by) VALUES (:name, :description, :created_by)'
        );
        $stmt->execute([
            'name' => $name,
            'description' => $description,
            'created_by' => (int) $currentUser['id'],
        ]);
        $id = (int) $db->lastInsertId();
        saveGroupMembers($db, $id, $memberIds);
        $db->commit();
    } catch (Throwable $exception) {
        $db->rollBack();
        jsonResponse(['success' => false, 'message' => 'Could not create group.'], 400);
    }

    logAction((int) $currentUser['id'], 'create_group', ['group_id' => $id, 'name' => $name]);
    jsonResponse(['success' => true, 'group' => loadGroup($db, $id)], 201);
}

if ($method === 'PUT') {
    $currentUser = requirePrivileged();
    $input = readJsonInput();
    $id = getRequestId($input);
    requirePositiveId($id);

    if (!loadGroup($db, $id)) {
        jsonResponse(['success' => false, 'message' => 'Group not found.'], 404);
    }

    $name = trim((string) ($input['name'] ?? ''));
    $description = trim((string) ($input['description'] ?? ''));
    $memberIds = is_array($input['member_user_ids'] ?? null) ? $input['member_user_ids'] : [];

    if ($name === '') {
        jsonResponse(['success' => false, 'message' => 'Group name is required.'], 400);
    }

    $db->beginTransaction();
    try {
        $stmt = $db->prepare('UPDATE `groups` SET name = :name, description = :description WHERE id = :id');
        $stmt->execute(['id' => $id, 'name' => $name, 'description' => $description]);
        saveGroupMembers($db, $id, $memberIds);
        $db->commit();
    } catch (Throwable $exception) {
        $db->rollBack();
        jsonResponse(['success' => false, 'message' => 'Could not update group.'], 400);
    }

    logAction((int) $currentUser['id'], 'update_group', ['group_id' => $id]);
    jsonResponse(['success' => true, 'group' => loadGroup($db, $id)]);
}

if ($method === 'DELETE') {
    $currentUser = requirePrivileged();
    $input = readJsonInput();
    $id = getRequestId($input);
    requirePositiveId($id);

    if (!loadGroup($db, $id)) {
        jsonResponse(['success' => false, 'message' => 'Group not found.'], 404);
    }

    $db->prepare('DELETE FROM `groups` WHERE id = :id')->execute(['id' => $id]);
    logAction((int) $currentUser['id'], 'delete_group', ['group_id' => $id]);
    jsonResponse(['success' => true]);
}

jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
