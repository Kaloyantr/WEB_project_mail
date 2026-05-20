<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

$db = getDb();
requireMethod('GET');
requireAdmin();

$userId = intValue($_GET['user_id'] ?? 0);
$action = trim((string) ($_GET['action'] ?? ''));
$where = [];
$params = [];

if ($userId > 0) {
    $where[] = 'al.user_id = :user_id';
    $params['user_id'] = $userId;
}

if ($action !== '') {
    $where[] = 'al.action = :action';
    $params['action'] = $action;
}

$sql = 'SELECT al.*, u.name AS user_name, u.email AS user_email
        FROM audit_logs al
        LEFT JOIN users u ON u.id = al.user_id';

if (count($where) > 0) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= ' ORDER BY al.created_at DESC LIMIT 200';
$stmt = $db->prepare($sql);
$stmt->execute($params);

jsonResponse(['success' => true, 'items' => $stmt->fetchAll()]);
