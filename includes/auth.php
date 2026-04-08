<?php
require_once __DIR__ . '/db.php';

function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }
}

function requireLoginApi(): void {
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Não autenticado.']);
        exit;
    }
}

function currentUserId(): ?int {
    return $_SESSION['user_id'] ?? null;
}

function currentUser(): ?array {
    if (!isLoggedIn()) return null;
    static $user = null;
    if ($user === null) {
        $db   = getDB();
        $stmt = $db->prepare('SELECT id, username, email, public_key, avatar_color FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch() ?: null;
    }
    return $user;
}

function loginUser(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user_id']  = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email']    = $user['email'];
}

function logoutUser(): void {
    VaultCrypto_clearSession: // placeholder for JS side
    session_unset();
    session_destroy();
    session_start();
    session_regenerate_id(true);
}

function getUserPublicKey(int $userId): ?string {
    $db   = getDB();
    $stmt = $db->prepare('SELECT public_key FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $row ? $row['public_key'] : null;
}

function createNotification(
    int $userId, string $type, string $title,
    string $body = '', ?int $relatedId = null
): void {
    $db   = getDB();
    $stmt = $db->prepare(
        'INSERT INTO notifications (user_id, type, title, body, related_id) VALUES (?,?,?,?,?)'
    );
    $stmt->execute([$userId, $type, $title, $body, $relatedId]);
}

function getUnreadNotifCount(int $userId): int {
    $db   = getDB();
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL'
    );
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}
