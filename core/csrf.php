<?php
/**
 * Token CSRF por sesión. La página lo publica con csrf_token() (p. ej. en una variable JS) y el
 * endpoint lo exige con csrf_exigir(): viaja en el header X-CSRF-Token o en el campo csrf_token.
 * Por ahora lo usan solo las acciones de lectura de archivos de facturas_proveedores (gestion_normal).
 */

function csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_valido(?string $token): bool
{
    $esperado = $_SESSION['csrf_token'] ?? '';
    return is_string($esperado) && $esperado !== '' && is_string($token) && hash_equals($esperado, $token);
}

/** Corta con 403 JSON si el token no coincide. */
function csrf_exigir(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? null);
    if (!csrf_valido($token)) {
        if (ob_get_length()) ob_clean();
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'La sesión de la página venció. Recargue la página e intente de nuevo.'],
                         JSON_UNESCAPED_UNICODE);
        exit;
    }
}
