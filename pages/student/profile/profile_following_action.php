<?php
require_once __DIR__ . '/../../../backend/db_connect.php';
require_once __DIR__ . '/profile_following.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
    exit;
}

$currentRole = (string) ($_SESSION['role'] ?? '');
$currentUserId = (int) ($_SESSION['user_id'] ?? 0);
$validRoles = ['student', 'employer', 'adviser'];

if ($currentUserId <= 0 || !in_array($currentRole, $validRoles, true)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
    exit;
}

$errors = [];
$success = '';

profile_following_ensure_schema($pdo);
profile_following_handle_action($pdo, $currentRole, $currentUserId, $errors, $success);

if ($errors) {
    echo json_encode(['ok' => false, 'message' => implode(' ', $errors)]);
    exit;
}

echo json_encode(['ok' => true, 'message' => $success !== '' ? $success : 'Action completed']);
