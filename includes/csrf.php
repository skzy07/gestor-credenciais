<?php
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken(string $token): bool {
    return isset($_SESSION['csrf_token']) &&
           hash_equals($_SESSION['csrf_token'], $token);
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCsrfToken()) . '">';
}

// Para APIs JSON — lê do header X-CSRF-Token ou do body
function validateCsrfApi(): void {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN']
           ?? $_POST['csrf_token']
           ?? '';
    if (!validateCsrfToken($token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'CSRF token inválido.']);
        exit;
    }
}
