<?php
function jsonResponse(bool $success, mixed $data = null, ?string $error = null, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(compact('success', 'data', 'error'), JSON_UNESCAPED_UNICODE);
    exit;
}

function sanitize(string $str): string {
    return htmlspecialchars(trim($str), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)     return 'agora mesmo';
    if ($diff < 3600)   return floor($diff / 60) . ' min atrás';
    if ($diff < 86400)  return floor($diff / 3600) . 'h atrás';
    if ($diff < 604800) return floor($diff / 86400) . 'd atrás';
    return date('d/m/Y', strtotime($datetime));
}

function avatarColor(string $username): string {
    $colors = ['#4F8EF7','#00D4AA','#FF6B6B','#FFB800','#9B59B6','#E91E9B','#00BCD4'];
    return $colors[crc32($username) % count($colors)];
}

function initials(string $username): string {
    $parts = explode(' ', trim($username));
    if (count($parts) >= 2) return strtoupper($parts[0][0] . $parts[1][0]);
    return strtoupper(substr($username, 0, 2));
}

function generateSecureToken(int $bytes = 32): string {
    return bin2hex(random_bytes($bytes));
}
