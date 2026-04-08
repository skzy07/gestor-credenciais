<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json; charset=utf-8');
requireLoginApi();

$action = $_GET['action'] ?? '';
$userId = currentUserId();

switch ($action) {

    // ── Listar notificações ───────────────────────────────
    case 'list': {
        $db    = getDB();
        $page  = max(1, (int)($_GET['page'] ?? 1));
        $limit = 20;
        $off   = ($page - 1) * $limit;

        $stmt = $db->prepare(
            'SELECT id, type, title, body, related_id, read_at, created_at
             FROM notifications WHERE user_id = ?
             ORDER BY created_at DESC LIMIT ' . $limit . ' OFFSET ' . $off
        );
        $stmt->execute([$userId]);
        $notifs = $stmt->fetchAll();

        $countStmt = $db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ?');
        $countStmt->execute([$userId]);
        $total = (int)$countStmt->fetchColumn();

        jsonResponse(true, ['notifications' => $notifs, 'total' => $total]);
    }

    // ── Contar não lidas ──────────────────────────────────
    case 'unread_count': {
        $db   = getDB();
        $stmt = $db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL');
        $stmt->execute([$userId]);
        jsonResponse(true, ['count' => (int)$stmt->fetchColumn()]);
    }

    // ── Marcar como lida ──────────────────────────────────
    case 'mark_read': {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, null, 'Método inválido.', 405);
        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $notifId = (int)($body['notification_id'] ?? 0);

        $db = getDB();
        if ($notifId) {
            $db->prepare('UPDATE notifications SET read_at = NOW() WHERE id = ? AND user_id = ?')
               ->execute([$notifId, $userId]);
        } else {
            // Marcar todas como lidas
            $db->prepare('UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL')
               ->execute([$userId]);
        }
        jsonResponse(true, null);
    }

    // ── Apagar uma notificação ────────────────────────────
    case 'delete': {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, null, 'Método inválido.', 405);
        $body    = json_decode(file_get_contents('php://input'), true) ?? [];
        $notifId = (int)($body['notification_id'] ?? 0);
        if (!$notifId) jsonResponse(false, null, 'ID inválido.', 400);

        getDB()->prepare('DELETE FROM notifications WHERE id = ? AND user_id = ?')
               ->execute([$notifId, $userId]);
        jsonResponse(true, null);
    }

    // ── Limpar todo o histórico ───────────────────────────
    case 'clear_all': {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, null, 'Método inválido.', 405);
        getDB()->prepare('DELETE FROM notifications WHERE user_id = ?')
               ->execute([$userId]);
        jsonResponse(true, null);
    }

    default:
        jsonResponse(false, null, 'Ação desconhecida.', 404);
}


