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

    // ── Pedir acesso para VER uma credencial ──────────────
    case 'request_view': {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, null, 'Método inválido.', 405);
        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $credId = (int)($body['credential_id'] ?? 0);
        if (!$credId) jsonResponse(false, null, 'ID inválido.', 400);

        $db   = getDB();
        $stmt = $db->prepare(
            'SELECT cr.id, cr.label, cr.company_id, cr.added_by,
                    co.owner_id, co.name AS company_name, u.username AS owner_username
             FROM credentials cr
             JOIN companies co ON co.id = cr.company_id
             JOIN users u ON u.id = co.owner_id
             WHERE cr.id = ? LIMIT 1'
        );
        $stmt->execute([$credId]);
        $cred = $stmt->fetch();
        if (!$cred) jsonResponse(false, null, 'Credencial não encontrada.', 404);

        $ownerId = (int)$cred['owner_id'];
        $addedBy = (int)$cred['added_by'];

        $targetUserId = $ownerId;

        // Se o próprio dono estiver a pedir acesso (credenciais criadas antes do auto-encrypt)
        // o pedido tem de ir para a pessoa que criou a credencial (pois só ela tem a AES key)
        if ($ownerId === $userId) {
            if ($addedBy === $userId) {
                jsonResponse(false, null, 'Esta credencial já é tua.', 400);
            }
            $targetUserId = $addedBy;
        }

        // Verificar se já tem acesso
        $stmt = $db->prepare('SELECT id FROM credential_keys WHERE credential_id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$credId, $userId]);
        if ($stmt->fetch()) jsonResponse(false, null, 'Já tens acesso a esta credencial.', 409);

        // Verificar pedido pendente
        $stmt = $db->prepare(
            'SELECT id FROM access_requests WHERE type=? AND credential_id=? AND requester_id=? AND status=? LIMIT 1'
        );
        $stmt->execute(['view_credential', $credId, $userId, 'pending']);
        if ($stmt->fetch()) jsonResponse(false, null, 'Já existe um pedido pendente para esta credencial.', 409);

        $reqId = null;
        $db->beginTransaction();
        try {
            $stmt = $db->prepare(
                'INSERT INTO access_requests (type, credential_id, company_id, requester_id, owner_id)
                 VALUES (?,?,?,?,?)'
            );
            $stmt->execute(['view_credential', $credId, $cred['company_id'], $userId, $targetUserId]);
            $reqId = (int)$db->lastInsertId();

            $me = $db->prepare('SELECT username FROM users WHERE id = ? LIMIT 1');
            $me->execute([$userId]); $me = $me->fetch();

            createNotification(
                $targetUserId, 'view_request',
                '👁 Pedido de visualização',
                $me['username'] . ' quer ver a credencial "' . $cred['label'] . '" da empresa ' . $cred['company_name'],
                $reqId
            );
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(false, null, 'Erro ao criar pedido.', 500);
        }
        jsonResponse(true, ['request_id' => $reqId]);
    }

    // ── Pedir permissão para ADICIONAR na empresa ─────────
    case 'request_add': {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, null, 'Método inválido.', 405);
        $body      = json_decode(file_get_contents('php://input'), true) ?? [];
        $companyId = (int)($body['company_id'] ?? 0);
        if (!$companyId) jsonResponse(false, null, 'ID inválido.', 400);

        $db   = getDB();
        $stmt = $db->prepare(
            'SELECT c.owner_id, c.name, u.username AS owner_username
             FROM companies c JOIN users u ON u.id = c.owner_id WHERE c.id = ? LIMIT 1'
        );
        $stmt->execute([$companyId]);
        $co = $stmt->fetch();
        if (!$co) jsonResponse(false, null, 'Empresa não encontrada.', 404);

        $ownerId = (int)$co['owner_id'];
        if ($ownerId === $userId) jsonResponse(false, null, 'Não precisas de pedir permissão na tua própria empresa.', 400);

        // Verificar pedido pendente ou aprovado
        $stmt = $db->prepare(
            'SELECT id,status FROM access_requests WHERE type=? AND company_id=? AND requester_id=? AND status IN (?,?) LIMIT 1'
        );
        $stmt->execute(['add_to_company', $companyId, $userId, 'pending', 'approved']);
        $existing = $stmt->fetch();
        if ($existing) {
            $msg = $existing['status'] === 'pending' ? 'Já existe um pedido pendente.' : 'Já tens autorização para adicionar credenciais.';
            jsonResponse(false, null, $msg, 409);
        }

        $db->beginTransaction();
        try {
            $stmt = $db->prepare(
                'INSERT INTO access_requests (type, company_id, requester_id, owner_id) VALUES (?,?,?,?)'
            );
            $stmt->execute(['add_to_company', $companyId, $userId, $ownerId]);
            $reqId = (int)$db->lastInsertId();

            $me = $db->prepare('SELECT username FROM users WHERE id = ? LIMIT 1');
            $me->execute([$userId]); $me = $me->fetch();

            createNotification(
                $ownerId, 'add_request',
                '➕ Pedido para adicionar credenciais',
                $me['username'] . ' quer adicionar credenciais à empresa ' . $co['name'],
                $reqId
            );
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(false, null, 'Erro ao criar pedido.', 500);
        }
        jsonResponse(true, ['request_id' => $reqId]);
    }

    // ── Convidar Técnico (Dono convida, Técnico recebe no 'pending_for_me') ──
    case 'invite_technician': {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, null, 'Método inválido.', 405);
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $companyId = (int)($body['company_id'] ?? 0);
        $email = trim($body['technician_email'] ?? '');
        $encryptedKeysArray = $body['encrypted_keys'] ?? []; // Array of { credential_id, encrypted_aes_key }

        if (!$companyId || !$email) jsonResponse(false, null, 'Dados em falta.', 400);

        $db = getDB();
        
        // Assegurar que quem está a convidar é o dono da empresa!
        $stmt = $db->prepare('SELECT id, name FROM companies WHERE id = ? AND owner_id = ? LIMIT 1');
        $stmt->execute([$companyId, $userId]);
        $co = $stmt->fetch();
        if (!$co) jsonResponse(false, null, 'Empresa não encontrada ou não és o dono.', 403);

        // Encontrar os dados do técnico
        $stmt = $db->prepare('SELECT id, username FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $tech = $stmt->fetch();
        if (!$tech) jsonResponse(false, null, 'Nenhuma conta encontrada com esse e-mail.', 404);
        $techId = (int)$tech['id'];
        if ($techId === $userId) jsonResponse(false, null, 'Não te podes convidar a ti próprio.', 400);

        // Verificar se já existe um convite ou add_to_company pendente/aprovado para este técnico
        $stmt = $db->prepare('SELECT id, status FROM access_requests WHERE type IN ("add_to_company", "invite_technician") AND company_id = ? AND requester_id = ? AND status IN ("pending", "approved") LIMIT 1');
        $stmt->execute([$companyId, $techId]);
        if ($stmt->fetch()) jsonResponse(false, null, 'Já existe um pedido de acesso ou convite para este técnico.', 409);

        $msgJson = json_encode(['encrypted_keys' => $encryptedKeysArray]);

        $db->beginTransaction();
        try {
            // Nota invertida: O Target que deve APROVAR é o técnico (owner_id da request)
            // O requester do convite é o Dono da empresa
            $stmt = $db->prepare(
                'INSERT INTO access_requests (type, company_id, requester_id, owner_id, message) VALUES (?,?,?,?,?)'
            );
            $stmt->execute(['invite_technician', $companyId, $userId, $techId, $msgJson]);
            $reqId = (int)$db->lastInsertId();

            createNotification(
                $techId, 'add_request', // usamos add_request genérico para que caia nos mesmos painéis
                '👨‍💻 Convite para a Empresa',
                'Foste convidado para gerir a empresa ' . $co['name'],
                $reqId
            );
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(false, null, 'Erro ao criar convite.', 500);
        }
        jsonResponse(true, ['request_id' => $reqId]);
    }

    // ── Aprovar pedido (dono responde) ────────────────────
    // Para view_credential: o dono envia a AES key re-encriptada para o solicitante
    // Para add_to_company: apenas aprova
    case 'approve': {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, null, 'Método inválido.', 405);
        $body  = json_decode(file_get_contents('php://input'), true) ?? [];
        $reqId = (int)($body['request_id'] ?? 0);
        // Para view_credential: AES key re-encriptada com a pubkey do solicitante
        $reEncryptedAesKey = $body['re_encrypted_aes_key'] ?? null;

        $db   = getDB();
        $stmt = $db->prepare(
            'SELECT ar.*, c.owner_id
             FROM access_requests ar
             LEFT JOIN companies co ON co.id = ar.company_id
             LEFT JOIN credentials cr ON cr.id = ar.credential_id
             LEFT JOIN companies c ON c.id = COALESCE(ar.company_id, cr.company_id)
             WHERE ar.id = ? AND ar.owner_id = ? AND ar.status = ? LIMIT 1'
        );
        $stmt->execute([$reqId, $userId, 'pending']);
        $req = $stmt->fetch();
        if (!$req) jsonResponse(false, null, 'Pedido não encontrado ou sem permissão.', 404);

        $db->beginTransaction();
        try {
            $db->prepare('UPDATE access_requests SET status=?, resolved_at=NOW() WHERE id=?')
               ->execute(['approved', $reqId]);

            // Alterar a notificação para mostrar o estado "Aprovado" em vez de a apagar
            $db->prepare("UPDATE notifications SET type = 'access_granted', title = '✅ Pedido Aprovado', body = 'Tu aprovaste este pedido de acesso.' WHERE related_id = ? AND type IN ('view_request', 'add_request')")
               ->execute([$reqId]);

            if ($req['type'] === 'view_credential') {
                if (!$reEncryptedAesKey) jsonResponse(false, null, 'AES key re-encriptada em falta.', 400);
                // Guardar a AES key encriptada com a pubkey do solicitante
                $db->prepare(
                    'INSERT INTO credential_keys (credential_id, user_id, encrypted_aes_key, granted_by)
                     VALUES (?,?,?,?)
                     ON DUPLICATE KEY UPDATE encrypted_aes_key = VALUES(encrypted_aes_key)'
                )->execute([$req['credential_id'], $req['requester_id'], $reEncryptedAesKey, $userId]);

                // Notificar o solicitante
                $credLabel = $db->prepare('SELECT label FROM credentials WHERE id = ? LIMIT 1');
                $credLabel->execute([$req['credential_id']]); $credLabel = $credLabel->fetch();

                createNotification(
                    $req['requester_id'], 'access_granted',
                    '✅ Acesso concedido',
                    'Podes agora ver a credencial "' . ($credLabel['label'] ?? '') . '".',
                    $reqId
                );
            } elseif ($req['type'] === 'invite_technician') {
                // Inserir todas as chaves no credential_keys
                $payload = json_decode($req['message'], true) ?? [];
                $keys = $payload['encrypted_keys'] ?? [];
                if (!empty($keys)) {
                    $insertKey = $db->prepare('INSERT INTO credential_keys (credential_id, user_id, encrypted_aes_key, granted_by) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE encrypted_aes_key = VALUES(encrypted_aes_key)');
                    foreach ($keys as $k) {
                        $insertKey->execute([$k['credential_id'], $userId, $k['encrypted_aes_key'], $req['requester_id']]);
                    }
                }
                
                $coName = $db->prepare('SELECT name FROM companies WHERE id = ? LIMIT 1');
                $coName->execute([$req['company_id']]); $coName = $coName->fetch();

                createNotification(
                    $req['requester_id'], 'access_granted',
                    '✅ Convite Aceite',
                    'O técnico agora tem acesso às credenciais da empresa "' . ($coName['name'] ?? '') . '".',
                    $reqId
                );
            } else {
                // add_to_company
                $coName = $db->prepare('SELECT name FROM companies WHERE id = ? LIMIT 1');
                $coName->execute([$req['company_id']]); $coName = $coName->fetch();

                createNotification(
                    $req['requester_id'], 'access_granted',
                    '✅ Autorização concedida',
                    'Podes agora adicionar credenciais à empresa "' . ($coName['name'] ?? '') . '".',
                    $reqId
                );
            }
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(false, null, 'Erro ao aprovar.', 500);
        }
        jsonResponse(true, null);
    }

    // ── Negar pedido ──────────────────────────────────────
    case 'deny': {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, null, 'Método inválido.', 405);
        $body  = json_decode(file_get_contents('php://input'), true) ?? [];
        $reqId = (int)($body['request_id'] ?? 0);

        $db   = getDB();
        $stmt = $db->prepare(
            'SELECT ar.* FROM access_requests ar WHERE ar.id = ? AND ar.owner_id = ? AND ar.status = ? LIMIT 1'
        );
        $stmt->execute([$reqId, $userId, 'pending']);
        $req = $stmt->fetch();
        if (!$req) jsonResponse(false, null, 'Pedido não encontrado.', 404);

        $db->prepare('UPDATE access_requests SET status=?, resolved_at=NOW() WHERE id=?')
           ->execute(['denied', $reqId]);

        // Alterar a notificação para mostrar o estado "Negado"
        $db->prepare("UPDATE notifications SET type = 'access_denied', title = '❌ Pedido Negado', body = 'Tu recusaste este pedido de acesso.' WHERE related_id = ? AND type IN ('view_request', 'add_request')")
           ->execute([$reqId]);

        createNotification(
            $req['requester_id'], 'access_denied',
            '❌ Pedido negado',
            'O teu pedido de acesso foi negado.',
            $reqId
        );
        jsonResponse(true, null);
    }

    // ── Listar pedidos pendentes ────────────────────────── (para o dono processar)
    case 'pending_for_me': {
        $db   = getDB();
        $stmt = $db->prepare(
            'SELECT ar.id, ar.type, ar.status, ar.created_at,
                    ar.credential_id, ar.company_id, ar.requester_id,
                    cr.label AS credential_label,
                    co.name AS company_name,
                    u.username AS requester_username, u.avatar_color AS requester_avatar,
                    u.public_key AS requester_public_key,
                    ck.encrypted_aes_key AS my_encrypted_aes_key
             FROM access_requests ar
             JOIN users u ON u.id = ar.requester_id
             LEFT JOIN credentials cr ON cr.id = ar.credential_id
             LEFT JOIN companies co ON co.id = COALESCE(ar.company_id, cr.company_id)
             LEFT JOIN credential_keys ck ON ck.credential_id = ar.credential_id AND ck.user_id = ?
             WHERE ar.owner_id = ? AND ar.status = ?
             ORDER BY ar.created_at DESC'
        );
        $stmt->execute([$userId, $userId, 'pending']);
        jsonResponse(true, ['requests' => $stmt->fetchAll()]);
    }

    default:
        jsonResponse(false, null, 'Ação desconhecida.', 404);
}


