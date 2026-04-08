<?php
require_once __DIR__ . '/config.php';

/**
 * Envia email usando SMTP via stream sockets (sem dependências externas)
 * ou usando mail() nativo se MAIL_USE_SMTP = false
 */
function sendMail(string $to, string $toName, string $subject, string $htmlBody): bool {
    if (MAIL_USE_SMTP && MAIL_SMTP_USER !== '') {
        return sendSmtpMail($to, $toName, $subject, $htmlBody);
    }
    // Fallback: mail() nativo (funciona em XAMPP com sendmail configurado)
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    return mail($to, $subject, $htmlBody, $headers);
}

function sendSmtpMail(string $to, string $toName, string $subject, string $htmlBody): bool {
    $host = MAIL_SMTP_HOST;
    $port = MAIL_SMTP_PORT;
    $user = MAIL_SMTP_USER;
    $pass = MAIL_SMTP_PASS;

    $errno = 0; $errstr = '';
    $ctx = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
    $conn = stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$conn) return false;

    $read = fn() => fgets($conn, 515);
    $write = fn($cmd) => fputs($conn, $cmd . "\r\n");

    $read(); // greeting
    $write("EHLO localhost");
    while (($line = $read()) && substr($line, 3, 1) === '-') {} // read multi-line

    $write("STARTTLS");
    $read();
    stream_socket_enable_crypto($conn, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);

    $write("EHLO localhost");
    while (($line = $read()) && substr($line, 3, 1) === '-') {}

    $write("AUTH LOGIN");
    $read();
    $write(base64_encode($user)); $read();
    $write(base64_encode($pass)); $read();

    $write("MAIL FROM:<{$user}>");      $read();
    $write("RCPT TO:<{$to}>");          $read();
    $write("DATA");                     $read();

    $boundary = md5(uniqid());
    $msg  = "From: " . MAIL_FROM_NAME . " <{$user}>\r\n";
    $msg .= "To: {$toName} <{$to}>\r\n";
    $msg .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
    $msg .= "MIME-Version: 1.0\r\n";
    $msg .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
    $msg .= $htmlBody . "\r\n";
    $msg .= ".";
    $write($msg); $read();
    $write("QUIT"); fclose($conn);
    return true;
}

function sendPasswordResetEmail(string $to, string $username, string $token): bool {
    $url     = APP_URL . '/reset-password-confirm.php?token=' . urlencode($token);
    $appName = APP_NAME;
    $html = <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8">
<style>
  body{font-family:Inter,sans-serif;background:#060B18;color:#F1F3F6;margin:0;padding:0}
  .wrap{max-width:560px;margin:40px auto;background:#0B1225;border:1px solid rgba(255,255,255,.07);border-radius:16px;overflow:hidden}
  .header{background:linear-gradient(135deg,#4F8EF7,#00D4AA);padding:32px;text-align:center}
  .header h1{margin:0;font-size:1.4rem;color:#fff}
  .body{padding:32px}
  .btn{display:inline-block;background:linear-gradient(135deg,#4F8EF7,#2A65D6);color:#fff;
       text-decoration:none;padding:14px 32px;border-radius:10px;font-weight:700;margin:20px 0}
  .warn{background:rgba(255,184,0,.1);border:1px solid rgba(255,184,0,.3);border-radius:8px;
        padding:12px;font-size:.85rem;color:#FFB800;margin-top:20px}
  .footer{text-align:center;padding:20px;font-size:.78rem;color:#4A5A6F;border-top:1px solid rgba(255,255,255,.05)}
</style></head><body>
<div class="wrap">
  <div class="header"><h1>🔐 {$appName}</h1></div>
  <div class="body">
    <p>Olá <strong>{$username}</strong>,</p>
    <p>Recebemos um pedido de redefinição de palavra-passe para a tua conta. Clica no botão abaixo para definires uma nova palavra-passe:</p>
    <div style="text-align:center"><a href="{$url}" class="btn">Redefinir Palavra-Passe</a></div>
    <div class="warn">⚠️ Este link expira em <strong>1 hora</strong>. Se não fizeste este pedido, ignora este email.</div>
    <p style="margin-top:20px;font-size:.85rem;color:#8B9AB0">Ou copia este link: <a href="{$url}" style="color:#4F8EF7">{$url}</a></p>
  </div>
  <div class="footer">{$appName} · Sistema Gestor de Credenciais</div>
</div>
</body></html>
HTML;
    return sendMail($to, $username, "🔐 Redefinição de Palavra-Passe — {$appName}", $html);
}
