<?php
declare(strict_types=1);

/**
 * core/secretos.php
 * Utilidad de cifrado y descifrado simétrico autenticado (AES-256-GCM)
 * para credenciales bancarias, tokens, claves privadas y accesos de servicios.
 *
 * La clave maestra se toma exclusivamente de SINDI_CLAVE_CIFRADO (.env).
 */

if (!defined('SINDI_CLAVE_CIFRADO')) {
    $envPath = __DIR__ . '/../.env';
    if (file_exists($envPath)) {
        require_once $envPath;
    }
}

/**
 * Cifra un texto plano utilizando AES-256-GCM.
 * Retorna string en Base64 con el payload: IV (12 bytes) + Tag (16 bytes) + Ciphertext.
 *
 * @param string $valor Texto en claro a cifrar.
 * @return string Texto cifrado en Base64.
 * @throws RuntimeException Si falta la clave o falla el cifrado.
 */
function secreto_cifrar(string $valor): string
{
    if ($valor === '') {
        return '';
    }

    if (!defined('SINDI_CLAVE_CIFRADO') || empty(SINDI_CLAVE_CIFRADO)) {
        throw new RuntimeException('SINDI_CLAVE_CIFRADO no está definida en la configuración del servidor.');
    }

    // Derivar clave de 256 bits (32 bytes)
    $key = hash('sha256', (string)SINDI_CLAVE_CIFRADO, true);

    // IV de 12 bytes recomendado para GCM
    $iv = openssl_random_pseudo_bytes(12);
    if ($iv === false) {
        throw new RuntimeException('No se pudo generar un vector de inicialización seguro.');
    }

    $tag = '';
    $ciphertext = openssl_encrypt(
        $valor,
        'aes-256-gcm',
        $key,
        OPENSSL_RAW_DATA,
        $iv,
        $tag,
        '',
        16
    );

    if ($ciphertext === false) {
        throw new RuntimeException('Error al cifrar el contenido: ' . openssl_error_string());
    }

    // Estructura: 12 bytes IV + 16 bytes Tag + Ciphertext
    return base64_encode($iv . $tag . $ciphertext);
}

/**
 * Descifra un payload cifrado previamente con secreto_cifrar().
 *
 * @param string|null $cifradoBase64 Cadena en Base64 retornada por secreto_cifrar().
 * @return string|null Texto descifrado o null si es inválido.
 */
function secreto_descifrar(?string $cifradoBase64): ?string
{
    if ($cifradoBase64 === null || trim($cifradoBase64) === '') {
        return null;
    }

    if (!defined('SINDI_CLAVE_CIFRADO') || empty(SINDI_CLAVE_CIFRADO)) {
        error_log('[SECRETOS] Advertencia: intento de descifrar sin SINDI_CLAVE_CIFRADO.');
        return null;
    }

    $raw = base64_decode($cifradoBase64, true);
    if ($raw === false || strlen($raw) < 28) {
        // Mínimo 12 bytes IV + 16 bytes Tag = 28 bytes
        return null;
    }

    $iv = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $ciphertext = substr($raw, 28);

    $key = hash('sha256', (string)SINDI_CLAVE_CIFRADO, true);

    $plaintext = openssl_decrypt(
        $ciphertext,
        'aes-256-gcm',
        $key,
        OPENSSL_RAW_DATA,
        $iv,
        $tag
    );

    if ($plaintext === false) {
        error_log('[SECRETOS] Error al descifrar secreto: firma GCM inválida o clave errónea.');
        return null;
    }

    return $plaintext;
}

/**
 * Enmascara un valor para vista pública o logs (ej: '••••••••').
 */
function secreto_enmascarar(?string $valor, int $visibles = 0): string
{
    if ($valor === null || $valor === '') {
        return '(no configurado)';
    }
    if ($visibles <= 0) {
        return '••••••••••••';
    }
    $len = strlen($valor);
    if ($len <= $visibles) {
        return str_repeat('•', $len);
    }
    return str_repeat('•', $len - $visibles) . substr($valor, -$visibles);
}
