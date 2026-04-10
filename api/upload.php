<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json; charset=utf-8');
requireLoginApi();

$type    = $_GET['type'] ?? '';
$userId  = currentUserId();
$db      = getDB();

$allowedMime = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
$uploadBase  = __DIR__ . '/../assets/uploads/';

// ── GD helpers ────────────────────────────────────────────
function loadImageGD(string $path, string $mime) {
    return match ($mime) {
        'image/jpeg' => imagecreatefromjpeg($path),
        'image/png'  => imagecreatefrompng($path),
        'image/webp' => imagecreatefromwebp($path),
        'image/gif'  => imagecreatefromgif($path),
        default      => false,
    };
}

function resizeAndSaveWebP(string $srcPath, string $mime, string $destPath, int $size): bool {
    // Se GD não estiver disponível, guarda o ficheiro original directamente
    if (!extension_loaded('gd')) {
        return copy($srcPath, $destPath);
    }

    $src = loadImageGD($srcPath, $mime);
    if (!$src) {
        // Fallback: guardar original sem processamento
        return copy($srcPath, $destPath);
    }

    $sw = imagesx($src); $sh = imagesy($src);
    $minDim = min($sw, $sh);
    $x = (int)(($sw - $minDim) / 2);
    $y = (int)(($sh - $minDim) / 2);
    $dst = imagecreatetruecolor($size, $size);
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefill($dst, 0, 0, $white);
    imagecopyresampled($dst, $src, 0, 0, $x, $y, $size, $size, $minDim, $minDim);

    // Tentar guardar como WebP; se falhar, guardar como JPEG
    $ok = function_exists('imagewebp') ? imagewebp($dst, $destPath, 85) : imagejpeg($dst, $destPath, 85);
    imagedestroy($src); imagedestroy($dst);

    if (!$ok) {
        // Último fallback: copia o original
        return copy($srcPath, $destPath);
    }
    return true;
}

function validateFile(array $allowedMime): array {
    if (!isset($_FILES['photo'])) {
        jsonResponse(false, null, 'Nenhum ficheiro recebido (campo "photo" ausente).', 400);
    }
    $errCode = $_FILES['photo']['error'];
    if ($errCode !== UPLOAD_ERR_OK) {
        $errMap = [
            UPLOAD_ERR_INI_SIZE   => 'Ficheiro excede upload_max_filesize no php.ini (' . ini_get('upload_max_filesize') . ').',
            UPLOAD_ERR_FORM_SIZE  => 'Ficheiro excede MAX_FILE_SIZE do formulário.',
            UPLOAD_ERR_PARTIAL    => 'Upload incompleto.',
            UPLOAD_ERR_NO_FILE    => 'Nenhum ficheiro foi enviado.',
            UPLOAD_ERR_NO_TMP_DIR => 'Pasta temporária em falta.',
            UPLOAD_ERR_CANT_WRITE => 'Falha ao escrever ficheiro no disco.',
            UPLOAD_ERR_EXTENSION  => 'Extensão PHP bloqueou o upload.',
        ];
        jsonResponse(false, null, $errMap[$errCode] ?? "Erro de upload (#$errCode).", 400);
    }
    $file = $_FILES['photo'];
    $mime = mime_content_type($file['tmp_name']);
    if (!in_array($mime, $allowedMime, true))
        jsonResponse(false, null, "Tipo de ficheiro inválido ($mime). Usa JPG, PNG ou WebP.", 400);
    if ($file['size'] > 5 * 1024 * 1024)
        jsonResponse(false, null, 'O ficheiro não pode ter mais de 5 MB.', 400);
    return ['file' => $file, 'mime' => $mime];
}

// ── Dispatcher ────────────────────────────────────────────
switch ($type) {

    case 'avatar': {
        ['file' => $file, 'mime' => $mime] = validateFile($allowedMime);
        $filename = $userId . '.webp';
        $destPath = $uploadBase . 'avatars/' . $filename;
        if (!resizeAndSaveWebP($file['tmp_name'], $mime, $destPath, 256))
            jsonResponse(false, null, 'Erro ao processar a imagem.', 500);
        $url = APP_URL . '/assets/uploads/avatars/' . $filename . '?v=' . time();
        $db->prepare('UPDATE users SET avatar_url = ? WHERE id = ?')->execute([$url, $userId]);
        jsonResponse(true, ['url' => $url]);
    }

    case 'company_logo': {
        ['file' => $file, 'mime' => $mime] = validateFile($allowedMime);
        $companyId = (int)($_GET['company_id'] ?? 0);
        if (!$companyId) jsonResponse(false, null, 'company_id em falta.', 400);
        $stmt = $db->prepare('SELECT owner_id FROM companies WHERE id = ? LIMIT 1');
        $stmt->execute([$companyId]);
        $co = $stmt->fetch();
        if (!$co || (int)$co['owner_id'] !== $userId)
            jsonResponse(false, null, 'Apenas o dono pode alterar o logo da empresa.', 403);
        $filename = $companyId . '.webp';
        $destPath = $uploadBase . 'companies/' . $filename;
        if (!resizeAndSaveWebP($file['tmp_name'], $mime, $destPath, 512))
            jsonResponse(false, null, 'Erro ao processar a imagem.', 500);
        $url = APP_URL . '/assets/uploads/companies/' . $filename . '?v=' . time();
        $db->prepare('UPDATE companies SET logo_url = ? WHERE id = ?')->execute([$url, $companyId]);
        jsonResponse(true, ['url' => $url]);
    }

    case 'remove_avatar': {
        $path = $uploadBase . 'avatars/' . $userId . '.webp';
        if (file_exists($path)) @unlink($path);
        $db->prepare('UPDATE users SET avatar_url = NULL WHERE id = ?')->execute([$userId]);
        jsonResponse(true, null);
    }

    case 'remove_company_logo': {
        $companyId = (int)($_GET['company_id'] ?? 0);
        if (!$companyId) jsonResponse(false, null, 'company_id em falta.', 400);
        $stmt = $db->prepare('SELECT owner_id FROM companies WHERE id = ? LIMIT 1');
        $stmt->execute([$companyId]); $co = $stmt->fetch();
        if (!$co || (int)$co['owner_id'] !== $userId)
            jsonResponse(false, null, 'Sem permissão.', 403);
        $path = $uploadBase . 'companies/' . $companyId . '.webp';
        if (file_exists($path)) @unlink($path);
        $db->prepare('UPDATE companies SET logo_url = NULL WHERE id = ?')->execute([$companyId]);
        jsonResponse(true, null);
    }

    default:
        jsonResponse(false, null, 'Tipo de upload desconhecido.', 400);
}
