<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/nif.php';

header('Content-Type: application/json; charset=utf-8');
requireLoginApi();

$action = $_GET['action'] ?? '';
$userId = currentUserId();

switch ($action) {

    // ── Feed de empresas ──────────────────────────────────
    case 'list': {
        $db    = getDB();
        $page  = max(1, (int)($_GET['page'] ?? 1));
        $limit = 12;
        $off   = ($page - 1) * $limit;
        $search = trim($_GET['q'] ?? '');

        $where = $search ? 'WHERE (c.name LIKE ? OR c.nif LIKE ? OR c.description LIKE ?)' : '';
        $params = $search ? ["%$search%", "%$search%", "%$search%"] : [];

        $stmt = $db->prepare(
            "SELECT c.id, c.name, c.nif, c.description, c.created_at,
                    u.username AS owner_username, u.avatar_color AS owner_avatar,
                    (c.owner_id = ?) AS is_mine,
                    (SELECT COUNT(*) FROM credentials cr WHERE cr.company_id = c.id AND (cr.is_private = 0 OR cr.added_by = ?)) AS cred_count
             FROM companies c JOIN users u ON u.id = c.owner_id
             $where
             ORDER BY c.created_at DESC LIMIT $limit OFFSET $off"
        );
        $stmt->execute(array_merge([$userId, $userId], $params));
        $companies = $stmt->fetchAll();

        $countStmt = $db->prepare("SELECT COUNT(*) FROM companies c $where");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        jsonResponse(true, ['companies' => $companies, 'total' => $total, 'page' => $page, 'pages' => ceil($total/$limit)]);
    }

    // ── Criar empresa ─────────────────────────────────────
    case 'create': {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, null, 'Método inválido.', 405);
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $name        = trim($body['name'] ?? '');
        $nif         = trim($body['nif'] ?? '');
        $description = trim($body['description'] ?? '');

        if (!$name) jsonResponse(false, null, 'O nome da empresa é obrigatório.', 400);
        if (strlen($name) > 255) jsonResponse(false, null, 'Nome demasiado longo.', 400);

        $nifCheck = validateNIF($nif);
        if (!$nifCheck['valid']) jsonResponse(false, null, $nifCheck['error'], 400);

        $db = getDB();

        // Ver se já existe empresa com este owner
        $stmt = $db->prepare('SELECT id FROM companies WHERE owner_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        if ($stmt->fetch()) jsonResponse(false, null, 'Já tens uma empresa registada. Cada técnico só pode ter uma empresa.', 409);

        // Verificar NIF único
        $stmt = $db->prepare('SELECT id FROM companies WHERE nif = ? LIMIT 1');
        $stmt->execute([$nif]);
        if ($stmt->fetch()) jsonResponse(false, null, 'Já existe uma empresa registada com este NIF.', 409);

        $stmt = $db->prepare(
            'INSERT INTO companies (name, nif, description, owner_id) VALUES (?,?,?,?)'
        );
        $stmt->execute([$name, $nif, $description, $userId]);
        $companyId = (int)$db->lastInsertId();

        jsonResponse(true, ['company_id' => $companyId, 'name' => $name]);
    }

    // ── Detalhes de uma empresa ───────────────────────────
    case 'get': {
        $companyId = (int)($_GET['id'] ?? 0);
        if (!$companyId) jsonResponse(false, null, 'ID inválido.', 400);

        $db   = getDB();
        $stmt = $db->prepare(
            'SELECT c.*, u.username AS owner_username, u.avatar_color AS owner_avatar,
                    (c.owner_id = ?) AS is_mine
             FROM companies c JOIN users u ON u.id = c.owner_id
             WHERE c.id = ? LIMIT 1'
        );
        $stmt->execute([$userId, $companyId]);
        $company = $stmt->fetch();
        if (!$company) jsonResponse(false, null, 'Empresa não encontrada.', 404);

        jsonResponse(true, $company);
    }

    // ── Atualizar empresa (só o dono) ─────────────────────
    case 'update': {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, null, 'Método inválido.', 405);
        $body      = json_decode(file_get_contents('php://input'), true) ?? [];
        $companyId = (int)($body['company_id'] ?? 0);
        $name      = trim($body['name'] ?? '');
        $desc      = trim($body['description'] ?? '');

        if (!$companyId || !$name) jsonResponse(false, null, 'Dados inválidos.', 400);

        $db = getDB();
        $stmt = $db->prepare('SELECT owner_id FROM companies WHERE id = ? LIMIT 1');
        $stmt->execute([$companyId]);
        $co = $stmt->fetch();
        if (!$co || (int)$co['owner_id'] !== $userId)
            jsonResponse(false, null, 'Sem permissão.', 403);

        $db->prepare('UPDATE companies SET name=?, description=? WHERE id=?')
           ->execute([$name, $desc, $companyId]);
        jsonResponse(true, null);
    }

    // ── Empresa do utilizador atual ───────────────────────
    case 'mine': {
        $db   = getDB();
        $stmt = $db->prepare('SELECT * FROM companies WHERE owner_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $co = $stmt->fetch();
        jsonResponse(true, $co ?: null);
    }

    default:
        jsonResponse(false, null, 'Ação desconhecida.', 404);
}


