<?php
// =============================================
// VaultKeeper — Configuração
// =============================================

// Base de Dados
define('DB_HOST',    'localhost');
define('DB_NAME',    'gestor_credenciais');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');

// Aplicação
define('APP_NAME',   'VaultKeeper');
define('APP_URL',    'http://localhost/gestor-credenciais');
define('APP_SECRET', 'vk_secret_change_me_in_production_2026');

// Email — configurar com as tuas credenciais SMTP
define('MAIL_FROM',       'noreply@vaultkeeper.local');
define('MAIL_FROM_NAME',  'VaultKeeper');
define('MAIL_SMTP_HOST',  'smtp.gmail.com');
define('MAIL_SMTP_PORT',  587);
define('MAIL_SMTP_USER',  ''); // Preenche com o teu email
define('MAIL_SMTP_PASS',  ''); // Preenche com a tua app password
define('MAIL_USE_SMTP',   false); // true = usa SMTP, false = usa mail() nativo

// Segurança
define('BCRYPT_COST',         12);
define('RESET_TOKEN_EXPIRE',  3600);      // 1 hora em segundos
define('SESSION_LIFETIME',    86400);     // 24 horas

// Ambiente
define('DEBUG_MODE', true); // Muda para false em produção

// Iniciar sessão segura
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
    session_start();
}

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}
