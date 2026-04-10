<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

try {
    $db = getDB();
    $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS avatar_url VARCHAR(255) NULL DEFAULT NULL");
    $db->exec("ALTER TABLE companies ADD COLUMN IF NOT EXISTS logo_url VARCHAR(255) NULL DEFAULT NULL");
    echo "OK — colunas adicionadas (ou já existiam).";
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
?>
