<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
requireLoginApi();

$targetUserId = (int)($_GET['user_id'] ?? 0);
$email = $_GET['email'] ?? '';

if (!$targetUserId && !$email) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'user_id ou email em falta.']);
    exit;
}

$db   = getDB();
if ($targetUserId) {
    $stmt = $db->prepare('SELECT id, public_key, username, avatar_color FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$targetUserId]);
} else {
    $stmt = $db->prepare('SELECT id, public_key, username, avatar_color FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([trim($email)]);
}
$user = $stmt->fetch();

if (!$user) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Utilizador não encontrado.']);
    exit;
}

echo json_encode(['success' => true, 'data' => $user]);
