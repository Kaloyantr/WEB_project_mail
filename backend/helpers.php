<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

function jsonResponse($data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');

    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($json === false) {
        $fallback = [
            'error' => 'Failed to encode JSON response.',
            'json_error' => json_last_error_msg(),
        ];

        http_response_code(500);
        echo json_encode($fallback, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    echo $json;
    exit;
}

function readJsonInput(): array
{
    $rawInput = file_get_contents('php://input');

    if ($rawInput === false || trim($rawInput) === '') {
        return [];
    }

    $data = json_decode($rawInput, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        jsonResponse([
            'error' => 'Invalid JSON input.',
            'json_error' => json_last_error_msg(),
        ], 400);
    }

    return $data;
}

function requireMethod(string $method): void
{
    $currentMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $requiredMethod = strtoupper($method);

    if ($currentMethod !== $requiredMethod) {
        jsonResponse([
            'error' => 'Method not allowed.',
            'expected' => $requiredMethod,
            'received' => $currentMethod,
        ], 405);
    }
}

function getCurrentUser(): ?array
{
    if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
        return null;
    }

    return $_SESSION['user'];
}

function requireLogin(): array
{
    $user = getCurrentUser();

    if ($user === null) {
        jsonResponse(['error' => 'Authentication required.'], 401);
    }

    return $user;
}

function requireRole($roles): array
{
    $user = requireLogin();
    $allowedRoles = is_array($roles) ? $roles : [$roles];

    if (!in_array($user['role'] ?? null, $allowedRoles, true)) {
        jsonResponse(['error' => 'Forbidden.'], 403);
    }

    return $user;
}

function logAction($userId, string $action, $details): void
{
    try {
        $db = getDb();
        $statement = $db->prepare(
            'INSERT INTO audit_logs (user_id, action, details) VALUES (:user_id, :action, :details)'
        );

        if (is_array($details) || is_object($details)) {
            $details = json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $statement->bindValue(':user_id', $userId, $userId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->bindValue(':action', $action, PDO::PARAM_STR);
        $statement->bindValue(':details', $details, $details === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $statement->execute();
    } catch (Throwable $exception) {
        error_log('Failed to write audit log: ' . $exception->getMessage());
    }
}

function isRuleActive(array $rule): bool
{
    if ((int) ($rule['is_active'] ?? 0) !== 1) {
        return false;
    }

    $today = date('Y-m-d');
    $startDate = $rule['start_date'] ?? null;
    $endDate = $rule['end_date'] ?? null;

    if ($startDate !== null && $startDate !== '' && $startDate > $today) {
        return false;
    }

    if ($endDate !== null && $endDate !== '' && $endDate < $today) {
        return false;
    }

    return true;
}
