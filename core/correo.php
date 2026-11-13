<?php
/**
 * Envío de correo HTML con la configuración MAIL_* del .env (MAIL_DRIVER, MAIL_HOST, MAIL_PORT,
 * MAIL_USERNAME, MAIL_PASSWORD, MAIL_ENCRYPTION tls/ssl, MAIL_FROM_ADDRESS, MAIL_FROM_NAME).
 * SMTP por socket (STARTTLS o SSL, AUTH LOGIN) y, si no hay SMTP o falla, mail() de PHP.
 * Mismo criterio que el envío del catálogo (exportar_productos_offline.php), pero valida la
 * respuesta de cada comando y no guarda copia local del mensaje.
 *
 * Uso: $r = correo_enviar('cliente@dominio.com', 'Asunto', '<p>Cuerpo</p>', ['responder_a' => 'x@y.com']);
 *      $r = ['success' => bool, 'message' => string]
 */

function correo_email_valido(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function correo_enviar(string $para, string $asunto, string $html, array $opciones = []): array
{
    $para = trim($para);
    if (!correo_email_valido($para)) {
        return ['success' => false, 'message' => 'La dirección de correo no es válida.'];
    }

    $driver     = defined('MAIL_DRIVER') ? strtolower(MAIL_DRIVER) : 'smtp';
    $host       = defined('MAIL_HOST') ? MAIL_HOST : '';
    $port       = defined('MAIL_PORT') ? intval(MAIL_PORT) : 587;
    $usuario    = defined('MAIL_USERNAME') ? MAIL_USERNAME : '';
    $clave      = defined('MAIL_PASSWORD') ? MAIL_PASSWORD : '';
    $cifrado    = defined('MAIL_ENCRYPTION') ? strtolower(MAIL_ENCRYPTION) : 'tls';
    $de_email   = defined('MAIL_FROM_ADDRESS') ? MAIL_FROM_ADDRESS : 'no-reply@procesosimple.com';
    $de_nombre  = $opciones['de_nombre'] ?? (defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : '');
    $responder  = trim((string) ($opciones['responder_a'] ?? ''));

    $cod = function (string $t): string {
        return '=?UTF-8?B?' . base64_encode($t) . '?=';
    };
    $dominio = substr(strrchr($de_email, '@') ?: '@localhost', 1);
    $cabeceras = [
        'Date: ' . date('r'),
        'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $dominio . '>',
        'MIME-Version: 1.0',
        'From: ' . ($de_nombre !== '' ? $cod($de_nombre) . ' ' : '') . '<' . $de_email . '>',
        'Reply-To: ' . (correo_email_valido($responder) ? $responder : $de_email),
        'X-Mailer: gestion_multipyme',
        'Content-Type: text/html; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
    ];
    $cuerpo = chunk_split(base64_encode($html));
    $errores = [];

    if ($driver === 'smtp' && $host !== '') {
        try {
            correo_smtp($host, $port, $cifrado, $usuario, $clave, $de_email, $para,
                implode("\r\n", array_merge($cabeceras, ['To: <' . $para . '>', 'Subject: ' . $cod($asunto)])) . "\r\n\r\n" . $cuerpo);
            return ['success' => true, 'message' => 'Correo enviado a ' . $para . '.'];
        } catch (Throwable $e) {
            $errores[] = 'SMTP: ' . $e->getMessage();
        }
    }

    // Respaldo: mail() de PHP (en Windows/XAMPP usa SMTP / smtp_port del php.ini)
    if (@mail($para, $cod($asunto), $cuerpo, implode("\r\n", $cabeceras))) {
        return ['success' => true, 'message' => 'Correo enviado a ' . $para . '.'];
    }
    $errores[] = 'mail(): ' . (error_get_last()['message'] ?? 'sin detalle');
    error_log('=== correo_enviar a ' . $para . ' fallido: ' . implode(' | ', $errores));
    return ['success' => false, 'message' => 'No se pudo enviar el correo. Revise la configuración de correo del servidor.'];
}

/** Diálogo SMTP. Lanza RuntimeException con la respuesta del servidor si un comando falla. */
function correo_smtp(string $host, int $port, string $cifrado, string $usuario, string $clave,
                     string $de, string $para, string $mensaje): void
{
    $ctx = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]]);
    $sock = @stream_socket_client(($cifrado === 'ssl' ? 'ssl://' : '') . $host . ':' . $port, $errno, $errstr, 10,
                                  STREAM_CLIENT_CONNECT, $ctx);
    if (!$sock) {
        throw new RuntimeException("no se pudo conectar a $host:$port ($errstr)");
    }
    stream_set_timeout($sock, 20);

    // Lee una respuesta completa (multilínea "250-...") y exige el código esperado
    $leer = function (array $esperados) use ($sock): string {
        $resp = '';
        while (($linea = fgets($sock, 1024)) !== false) {
            $resp .= $linea;
            if (strlen($linea) < 4 || $linea[3] !== '-') break;
        }
        if (!in_array(intval(substr($resp, 0, 3)), $esperados, true)) {
            throw new RuntimeException(trim($resp) !== '' ? trim($resp) : 'sin respuesta del servidor');
        }
        return $resp;
    };
    $cmd = function (string $linea, array $esperados) use ($sock, $leer): string {
        fwrite($sock, $linea . "\r\n");
        return $leer($esperados);
    };

    try {
        $leer([220]);
        $ehlo = $cmd('EHLO ' . (gethostname() ?: 'localhost'), [250]);
        if ($cifrado === 'tls' && stripos($ehlo, 'STARTTLS') !== false) {
            $cmd('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('no se pudo iniciar TLS');
            }
            $cmd('EHLO ' . (gethostname() ?: 'localhost'), [250]);
        }
        if ($usuario !== '' && $clave !== '') {
            $cmd('AUTH LOGIN', [334]);
            $cmd(base64_encode($usuario), [334]);
            $cmd(base64_encode($clave), [235]);
        }
        $cmd('MAIL FROM:<' . $de . '>', [250]);
        $cmd('RCPT TO:<' . $para . '>', [250, 251]);
        $cmd('DATA', [354]);
        // Dot-stuffing: una línea que empieza con "." se duplica
        fwrite($sock, preg_replace('/^\./m', '..', $mensaje) . "\r\n.\r\n");
        $leer([250]);
        @fwrite($sock, "QUIT\r\n");
    } finally {
        fclose($sock);
    }
}
