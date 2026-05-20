<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

$db = getDb();
$method = currentMethod();
$validTypes = ['general', 'anonymity', 'review_deadline'];

if ($method === 'GET') {
    $user = requireLogin();

    if (isPrivileged($user)) {
        $stmt = $db->query('SELECT * FROM rules ORDER BY created_at DESC');
        jsonResponse(['success' => true, 'items' => $stmt->fetchAll()]);
    }

    $stmt = $db->query('SELECT * FROM rules WHERE is_active = 1 ORDER BY created_at DESC');
    $items = array_values(array_filter($stmt->fetchAll(), 'isRuleActive'));
    jsonResponse(['success' => true, 'items' => $items]);
}

if ($method === 'POST') {
    $currentUser = requirePrivileged();
    $input = readJsonInput();
    $title = trim((string) ($input['title'] ?? ''));
    $description = trim((string) ($input['description'] ?? ''));
    $ruleType = trim((string) ($input['rule_type'] ?? 'general'));
    $startDate = trim((string) ($input['start_date'] ?? ''));
    $endDate = trim((string) ($input['end_date'] ?? ''));
    $isActive = !empty($input['is_active']) ? 1 : 0;

    if ($title === '' || !in_array($ruleType, $validTypes, true)) {
        jsonResponse(['success' => false, 'message' => 'Valid title and rule type are required.'], 400);
    }

    $stmt = $db->prepare(
        'INSERT INTO rules (title, description, rule_type, start_date, end_date, is_active, created_by)
         VALUES (:title, :description, :rule_type, :start_date, :end_date, :is_active, :created_by)'
    );
    $stmt->execute([
        'title' => $title,
        'description' => $description,
        'rule_type' => $ruleType,
        'start_date' => $startDate !== '' ? $startDate : null,
        'end_date' => $endDate !== '' ? $endDate : null,
        'is_active' => $isActive,
        'created_by' => (int) $currentUser['id'],
    ]);

    $id = (int) $db->lastInsertId();
    logAction((int) $currentUser['id'], 'create_rule', ['rule_id' => $id]);
    jsonResponse(['success' => true, 'id' => $id], 201);
}

if ($method === 'PUT') {
    $currentUser = requirePrivileged();
    $input = readJsonInput();
    $id = getRequestId($input);
    requirePositiveId($id);

    $title = trim((string) ($input['title'] ?? ''));
    $description = trim((string) ($input['description'] ?? ''));
    $ruleType = trim((string) ($input['rule_type'] ?? 'general'));
    $startDate = trim((string) ($input['start_date'] ?? ''));
    $endDate = trim((string) ($input['end_date'] ?? ''));
    $isActive = !empty($input['is_active']) ? 1 : 0;

    if ($title === '' || !in_array($ruleType, $validTypes, true)) {
        jsonResponse(['success' => false, 'message' => 'Valid title and rule type are required.'], 400);
    }

    $stmt = $db->prepare(
        'UPDATE rules
         SET title = :title, description = :description, rule_type = :rule_type,
             start_date = :start_date, end_date = :end_date, is_active = :is_active
         WHERE id = :id'
    );
    $stmt->execute([
        'id' => $id,
        'title' => $title,
        'description' => $description,
        'rule_type' => $ruleType,
        'start_date' => $startDate !== '' ? $startDate : null,
        'end_date' => $endDate !== '' ? $endDate : null,
        'is_active' => $isActive,
    ]);

    logAction((int) $currentUser['id'], 'update_rule', ['rule_id' => $id]);
    jsonResponse(['success' => true]);
}

if ($method === 'DELETE') {
    $currentUser = requirePrivileged();
    $input = readJsonInput();
    $id = getRequestId($input);
    requirePositiveId($id);

    $db->prepare('DELETE FROM rules WHERE id = :id')->execute(['id' => $id]);
    logAction((int) $currentUser['id'], 'delete_rule', ['rule_id' => $id]);
    jsonResponse(['success' => true]);
}

jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
