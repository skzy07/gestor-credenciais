<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/mailer.php';

header('Content-Type: application/json; charset=utf-8');
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    // ── Registo ──────────────────────────────────────────
    case 'register': {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, null, 'Método inválido.', 405);
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $username                        = trim($body['username'] ?? '');
        $email                           = trim($body['email'] ?? '');
        $password                        = $body['password'] ?? '';
        $publicKey                       = $body['public_key'] ?? '';
        $encryptedPrivateKey             = $body['encrypted_private_key'] ?? '';
        $privateKeySalt                  = $body['private_key_salt'] ?? '';
        $privateKeyIv                    = $body['private_key_iv'] ?? '';
        $recoveryEncryptedPrivateKey     = $body['recovery_encrypted_private_key'] ?? '';
        $recoveryKeySalt                 = $body['recovery_key_salt'] ?? '';
        $recoveryKeyIv                   = $body['recovery_key_iv'] ?? '';

        if (!$username || !$email || !$password || !$publicKey || !$encryptedPrivateKey)
            jsonResponse(false, null, 'Campos obrigatórios em falta.', 400);
        if (strlen($username) < 3 || strlen($username) > 50)
            jsonResponse(false, null, 'Username deve ter entre 3 e 50 caracteres.', 400);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))
            jsonResponse(false, null, 'Email inválido.', 400);
        if (strlen($password) < 8)
            jsonResponse(false, null, 'A password deve ter pelo menos 8 caracteres.', 400);

        $db = getDB();

        $stmt = $db->prepare('SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1');
        $stmt->execute([$email, $username]);
        if ($stmt->fetch()) jsonResponse(false, null, 'Email ou username já em uso.', 409);

        $hash        = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
        $color       = avatarColor($username);

        $stmt = $db->prepare(
            'INSERT INTO users (username, email, password_hash, public_key, encrypted_private_key,
             private_key_salt, private_key_iv, recovery_encrypted_private_key,
             recovery_key_salt, recovery_key_iv, avatar_color)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            $username, $email, $hash, $publicKey, $encryptedPrivateKey,
            $privateKeySalt, $privateKeyIv, $recoveryEncryptedPrivateKey,
            $recoveryKeySalt, $recoveryKeyIv, $color
        ]);

        $userId = (int)$db->lastInsertId();
        jsonResponse(true, ['user_id' => $userId, 'username' => $username, 'avatar_color' => $color]);
    }

    // ── Login ─────────────────────────────────────────────
    case 'login': {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, null, 'Método inválido.', 405);
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $email    = trim($body['email'] ?? '');
        $password = $body['password'] ?? '';

        if (!$email || !$password) jsonResponse(false, null, 'Email e password obrigatórios.', 400);

        $db   = getDB();
        $stmt = $db->prepare(
            'SELECT id, username, email, password_hash, public_key,
                    encrypted_private_key, private_key_salt, private_key_iv, avatar_color
             FROM users WHERE email = ? LIMIT 1'
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash']))
            jsonResponse(false, null, 'Email ou password incorretos.', 401);

        loginUser($user);
        jsonResponse(true, [
            'user_id'              => $user['id'],
            'username'             => $user['username'],
            'email'                => $user['email'],
            'avatar_color'         => $user['avatar_color'],
            'public_key'           => $user['public_key'],
            'encrypted_private_key'=> $user['encrypted_private_key'],
            'private_key_salt'     => $user['private_key_salt'],
            'private_key_iv'       => $user['private_key_iv'],
        ]);
    }

    // ── Logout ────────────────────────────────────────────
    case 'logout': {
        session_unset();
        session_destroy();
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }

    // ── Forgot Password ───────────────────────────────────
    case 'forgot_password': {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, null, 'Método inválido.', 405);
        $body  = json_decode(file_get_contents('php://input'), true) ?? [];
        $email = trim($body['email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))
            jsonResponse(false, null, 'Email inválido.', 400);

        $db   = getDB();
        $stmt = $db->prepare('SELECT id, username FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Mesmo se não existir, responde OK (segurança anti-enumeration)
        if ($user) {
            $token   = generateSecureToken(32);
            $expires = date('Y-m-d H:i:s', time() + RESET_TOKEN_EXPIRE);
            $db->prepare('DELETE FROM password_reset_tokens WHERE user_id = ?')->execute([$user['id']]);
            $db->prepare('INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?,?,?)')
               ->execute([$user['id'], $token, $expires]);
            sendPasswordResetEmail($email, $user['username'], $token);
        }
        jsonResponse(true, null, null);
    }

    // ── Reset Password ────────────────────────────────────
    case 'reset_password': {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, null, 'Método inválido.', 405);
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $token                       = trim($body['token'] ?? '');
        $newPassword                 = $body['new_password'] ?? '';
        $encryptedPrivateKey         = $body['encrypted_private_key'] ?? '';
        $privateKeySalt              = $body['private_key_salt'] ?? '';
        $privateKeyIv                = $body['private_key_iv'] ?? '';

        if (!$token || !$newPassword || !$encryptedPrivateKey)
            jsonResponse(false, null, 'Dados obrigatórios em falta.', 400);
        if (strlen($newPassword) < 8)
            jsonResponse(false, null, 'A password deve ter pelo menos 8 caracteres.', 400);

        $db   = getDB();
        $stmt = $db->prepare(
            'SELECT prt.user_id FROM password_reset_tokens prt
             WHERE prt.token = ? AND prt.expires_at > NOW() AND prt.used_at IS NULL LIMIT 1'
        );
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        if (!$row) jsonResponse(false, null, 'Token inválido ou expirado.', 400);

        $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
        $db->prepare(
            'UPDATE users SET password_hash=?, encrypted_private_key=?, private_key_salt=?, private_key_iv=? WHERE id=?'
        )->execute([$hash, $encryptedPrivateKey, $privateKeySalt, $privateKeyIv, $row['user_id']]);

        $db->prepare('UPDATE password_reset_tokens SET used_at=NOW() WHERE token=?')->execute([$token]);

        jsonResponse(true, null, null);
    }

    // ── Get recovery-encrypted private key (for reset flow) ──
    case 'get_reset_data': {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, null, 'Método inválido.', 405);
        $body  = json_decode(file_get_contents('php://input'), true) ?? [];
        $token = trim($body['token'] ?? '');
        if (!$token) jsonResponse(false, null, 'Token em falta.', 400);

        $db   = getDB();
        $stmt = $db->prepare(
            'SELECT u.recovery_encrypted_private_key, u.recovery_key_salt, u.recovery_key_iv
             FROM password_reset_tokens prt JOIN users u ON u.id = prt.user_id
             WHERE prt.token = ? AND prt.expires_at > NOW() AND prt.used_at IS NULL LIMIT 1'
        );
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        if (!$row || !$row['recovery_encrypted_private_key'])
            jsonResponse(false, null, 'Token inválido ou sem código de recuperação definido.', 400);

        jsonResponse(true, $row);
    }

    default:
        jsonResponse(false, null, 'Ação desconhecida.', 404);
}


