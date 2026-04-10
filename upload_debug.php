<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

// Enable all errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

requireLogin();
$userId = currentUserId();

echo "<h2>Diagnóstico de Upload</h2>";

// 1. Verificar extensão GD
echo "<h3>1. GD Extension</h3>";
if (extension_loaded('gd')) {
    $info = gd_info();
    echo "✅ GD disponível<br>";
    echo "WebP: " . ($info['WebP Support'] ? '✅ Suportado' : '❌ NÃO suportado') . "<br>";
    echo "JPEG: " . ($info['JPEG Support'] ? '✅' : '❌') . "<br>";
    echo "PNG: " . ($info['PNG Support'] ? '✅' : '❌') . "<br>";
} else {
    echo "❌ GD NÃO está disponível!<br>";
}

// 2. Verificar pastas de upload
echo "<h3>2. Pastas de Upload</h3>";
$dirs = [
    __DIR__ . '/assets/uploads/avatars',
    __DIR__ . '/assets/uploads/companies',
];
foreach ($dirs as $d) {
    $exists   = is_dir($d);
    $writable = $exists && is_writable($d);
    echo "$d: " . ($exists ? '✅ existe' : '❌ não existe') . " | " . ($writable ? '✅ escrita OK' : '❌ sem permissão de escrita') . "<br>";
}

// 3. Limites PHP
echo "<h3>3. Limites PHP (upload)</h3>";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "<br>";
echo "post_max_size: " . ini_get('post_max_size') . "<br>";
echo "file_uploads: " . ini_get('file_uploads') . "<br>";
echo "max_file_uploads: " . ini_get('max_file_uploads') . "<br>";

// 4. Teste de escrita
echo "<h3>4. Teste de escrita</h3>";
$testFile = __DIR__ . '/assets/uploads/avatars/_test.txt';
if (file_put_contents($testFile, 'test') !== false) {
    echo "✅ Escrita na pasta avatars OK<br>";
    unlink($testFile);
} else {
    echo "❌ Falhou a escrever na pasta avatars<br>";
}

// 5. APP_URL
echo "<h3>5. APP_URL</h3>";
echo APP_URL . "<br>";
echo "PHP_SELF: " . $_SERVER['PHP_SELF'] . "<br>";
?>
