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

    // ── Listar credenciais de uma empresa ─────────────────
    case 'list': {
        $companyId = (int)($_GET['company_id'] ?? 0);
        if (!$companyId) jsonResponse(false, null, 'company_id em falta.', 400);

        $db = getDB();

        // Verificar se a empresa existe
        $stmt = $db->prepare('SELECT owner_id FROM companies WHERE id = ? LIMIT 1');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch();
        if (!$company) jsonResponse(false, null, 'Empresa não encontrada.', 404);

        $isOwner = (int)$company['owner_id'] === $userId;

        // Buscar credenciais visíveis:
        // Se dono: vê as não-privadas + privadas suas
        // Se outro: vê apenas as suas privadas
        if ($isOwner) {
            $stmt = $db->prepare(
                'SELECT cr.id, cr.label, cr.iv, cr.is_private, cr.created_at,
                        cr.added_by, u.username AS added_by_username,
                        (cr.added_by = ?) AS is_mine,
                        (ck.id IS NOT NULL) AS has_access
                 FROM credentials cr
                 JOIN users u ON u.id = cr.added_by
                 LEFT JOIN credential_keys ck ON ck.credential_id = cr.id AND ck.user_id = ?
                 WHERE cr.company_id = ?
                 ORDER BY cr.created_at DESC'
            );
            $stmt->execute([$userId, $userId, $companyId]);
        } else {
            $stmt = $db->prepare(
                'SELECT cr.id, cr.label, cr.iv, cr.is_private, cr.created_at,
                        cr.added_by, u.username AS added_by_username,
                        (cr.added_by = ?) AS is_mine,
                        (ck.id IS NOT NULL) AS has_access
                 FROM credentials cr
                 JOIN users u ON u.id = cr.added_by
                 LEFT JOIN credential_keys ck ON ck.credential_id = cr.id AND ck.user_id = ?
                 WHERE cr.company_id = ? AND (cr.is_private = 0 OR cr.added_by = ?)
                 ORDER BY cr.created_at DESC'
            );
            $stmt->execute([$userId, $userId, $companyId, $userId]);
        }
        $credentials = $stmt->fetchAll();
        jsonResponse(true, ['credentials' => $credentials, 'is_owner' => $isOwner]);
    }

    // ── Obter as próprias chaves de uma empresa (para mega-payload de convite) ──
    case 'get_my_company_keys': {
        $companyId = (int)($_GET['company_id'] ?? 0);
        if (!$companyId) jsonResponse(false, null, 'ID inválido.', 400);

        $db = getDB();
        $stmt = $db->prepare(
            'SELECT cr.id AS credential_id, ck.encrypted_aes_key
             FROM credentials cr
             JOIN credential_keys ck ON ck.credential_id = cr.id
             WHERE cr.company_id = ? AND ck.user_id = ?'
        );
        $stmt->execute([$companyId, $userId]);
        jsonResponse(true, ['keys' => $stmt->fetchAll()]);
    }

    // ── Adicionar credencial ──────────────────────────────
    case 'add': {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, null, 'Método inválido.', 405);
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $companyId        = (int)($body['company_id'] ?? 0);
        $label            = trim($body['label'] ?? '');
        $encryptedData    = $body['encrypted_data'] ?? '';
        $iv               = $body['iv'] ?? '';
        $encryptedAesKey  = $body['encrypted_aes_key'] ?? '';

        if (!$companyId || !$label || !$encryptedData || !$iv || !$encryptedAesKey)
            jsonResponse(false, null, 'Dados obrigatórios em falta.', 400);

        $db = getDB();
        $stmt = $db->prepare('SELECT owner_id FROM companies WHERE id = ? LIMIT 1');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch();
        if (!$company) jsonResponse(false, null, 'Empresa não encontrada.', 404);

        $isOwner  = (int)$company['owner_id'] === $userId;
        $isPrivate = 0; // Credenciais de empresa são sempre visíveis para o dono (mesmo que trancadas por E2EE)

        // Se não for o dono, verificar se tem autorização aprovada OU é um técnico convidado
        $addRequestId = null;
        $addRequestType = null;
        if (!$isOwner) {
            $stmt = $db->prepare(
                'SELECT id, type FROM access_requests
                 WHERE (
                     (type = "add_to_company" AND requester_id = ?)
                     OR (type = "invite_technician" AND owner_id = ?)
                 ) AND company_id = ? AND status = ? LIMIT 1'
            );
            $stmt->execute([$userId, $userId, $companyId, 'approved']);
            $req = $stmt->fetch();
            if (!$req) jsonResponse(false, null, 'Sem autorização para adicionar credenciais a esta empresa.', 403);
            $addRequestId = $req['id'];
            $addRequestType = $req['type'];
        }

        $db->beginTransaction();
        try {
            $stmt = $db->prepare(
                'INSERT INTO credentials (company_id, label, encrypted_data, iv, added_by, is_private) VALUES (?,?,?,?,?,?)'
            );
            $stmt->execute([$companyId, $label, $encryptedData, $iv, $userId, $isPrivate]);
            $credId = (int)$db->lastInsertId();

            // Guardar a AES key encriptada para o próprio utilizador
            $db->prepare(
                'INSERT INTO credential_keys (credential_id, user_id, encrypted_aes_key, granted_by) VALUES (?,?,?,?)'
            )->execute([$credId, $userId, $encryptedAesKey, $userId]);

            // O pedido de 'add_to_company' não é consumido, a fim de conceder autorização permanente para futuras adições.
            // Para retirar acesso, usa-se a funcionalidade de "Remover Técnico".

            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(false, null, 'Erro ao guardar credencial.', 500);
        }

        jsonResponse(true, ['credential_id' => $credId]);
    }

    // ── Obter dados encriptados + AES key para desencriptar ─
    case 'get_encrypted': {
        $credId = (int)($_GET['credential_id'] ?? 0);
        if (!$credId) jsonResponse(false, null, 'ID inválido.', 400);

        $db = getDB();
        $stmt = $db->prepare(
            'SELECT cr.id, cr.encrypted_data, cr.iv, cr.added_by, cr.is_private, cr.company_id,
                    ck.encrypted_aes_key, c.owner_id AS company_owner, u.username AS added_by_username
             FROM credentials cr
             LEFT JOIN companies c ON c.id = cr.company_id
             LEFT JOIN credential_keys ck ON ck.credential_id = cr.id AND ck.user_id = ?
             LEFT JOIN users u ON u.id = cr.added_by
             WHERE cr.id = ? LIMIT 1'
        );
        $stmt->execute([$userId, $credId]);
        $row = $stmt->fetch();

        if (!$row) jsonResponse(false, null, 'Credencial não encontrada.', 404);
        if (!$row['encrypted_aes_key']) jsonResponse(false, null, 'Sem acesso a esta credencial.', 403);

        // BURN ON READ: Se o utilizador atual NÃO for o criador da credencial e não for um técnico convidado
        // A chave é descartada da BD imediatamente para acessos únicos (view_credential).
        if ((int)$row['added_by'] !== $userId) {
            $isPermanent = false;

            // Verificar se é dono (se quisermos que donos também sejam permanentes, podemos adicionar aqui, mas mantêmos só para técnico convidado agora)
            
            // Verificar se é um técnico convidado com acesso permanente
            $stmt = $db->prepare(
                'SELECT id FROM access_requests
                 WHERE (
                     (type = "add_to_company" AND requester_id = ?)
                     OR (type = "invite_technician" AND owner_id = ?)
                 ) AND company_id = ? AND status = "approved" LIMIT 1'
            );
            $stmt->execute([$userId, $userId, $row['company_id']]);
            if ($stmt->fetch()) {
                $isPermanent = true;
            }

            if (!$isPermanent) {
                $db->prepare('DELETE FROM credential_keys WHERE credential_id = ? AND user_id = ?')
                   ->execute([$credId, $userId]);
            }
        }

        jsonResponse(true, $row);
    }

    // ── Eliminar credencial ───────────────────────────────
    case 'delete': {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, null, 'Método inválido.', 405);
        $body  = json_decode(file_get_contents('php://input'), true) ?? [];
        $credId = (int)($body['credential_id'] ?? 0);
        if (!$credId) jsonResponse(false, null, 'ID inválido.', 400);

        $db = getDB();
        $stmt = $db->prepare(
            'SELECT cr.added_by, co.owner_id FROM credentials cr
             JOIN companies co ON co.id = cr.company_id WHERE cr.id = ? LIMIT 1'
        );
        $stmt->execute([$credId]);
        $row = $stmt->fetch();

        if (!$row) jsonResponse(false, null, 'Credencial não encontrada.', 404);
        if ((int)$row['added_by'] !== $userId && (int)$row['owner_id'] !== $userId)
            jsonResponse(false, null, 'Sem permissão.', 403);

        $db->prepare('DELETE FROM credentials WHERE id = ?')->execute([$credId]);
        jsonResponse(true, null);
    }

    default:
        jsonResponse(false, null, 'Ação desconhecida.', 404);
}


