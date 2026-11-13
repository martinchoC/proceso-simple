<?php
// ============================================================
// Framework de integración ARCA: WSAA (Ticket de Acceso) + WSFEv1
// Especificación: Especificacion_Integraciones_ARCA_ERP.docx
//
// Reglas que este archivo garantiza:
//   - URLs, CUIT y certificados salen de la configuración en BD
//     (gestion__integraciones_empresas + conf__integraciones_ambientes);
//     nada de ambiente hardcodeado.
//   - El TA (Token/Sign) se reutiliza mientras esté vigente y solo se pide
//     a WSAA cuando no existe o venció.
//   - Toda llamada externa queda en gestion__integraciones_resultados, con
//     Token, Sign y CMS enmascarados.
//   - Token/Sign nunca salen de este archivo: las funciones públicas
//     devuelven solo vencimientos y datos de negocio.
//   - Un timeout se informa como TIMEOUT, nunca como rechazo.
//
// Uso: $cfg = arca_obtener_configuracion($conexion, $integracion_empresa_id, $empresa_id);
//      $r   = arca_wsfe_ultimo_autorizado($conexion, $cfg, 1, 11);
// ============================================================

define('ARCA_WSFE_NS', 'http://ar.gov.afip.dif.FEV1/');
// Margen antes del vencimiento a partir del cual el TA se considera vencido.
define('ARCA_TA_MARGEN_SEGUNDOS', 300);

// ------------------------------------------------------------
// Configuración
// ------------------------------------------------------------

// Configuración completa de una vinculación empresa + ambiente (o null).
// Valida empresa: nunca devuelve la configuración de otra empresa.
function arca_obtener_configuracion($conexion, $integracion_empresa_id, $empresa_id)
{
    $sql = "SELECT ie.integracion_empresa_id, ie.empresa_id, ie.integracion_ambiente_id,
                   ie.servicio, ie.cuit, ie.certificado_ruta, ie.clave_privada_ruta,
                   ie.tabla_estado_registro_id,
                   a.integracion_id, a.codigo AS ambiente_codigo, a.nombre AS ambiente_nombre,
                   a.url_wsaa, a.url_servicio, a.activo AS ambiente_activo
            FROM gestion__integraciones_empresas ie
            INNER JOIN conf__integraciones_ambientes a
                ON a.integracion_ambiente_id = ie.integracion_ambiente_id
            WHERE ie.integracion_empresa_id = ? AND ie.empresa_id = ?";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("arca_obtener_configuracion: " . mysqli_error($conexion));
        return null;
    }
    $integracion_empresa_id = intval($integracion_empresa_id);
    $empresa_id = intval($empresa_id);
    mysqli_stmt_bind_param($stmt, "ii", $integracion_empresa_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $cfg = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if ($cfg) {
        // La titular usa su certificado con su CUIT (ver arca_obtener_configuracion_para_empresa)
        $cfg['titular_empresa_id'] = $cfg['empresa_id'];
        $cfg['certificado_cuit'] = $cfg['cuit'];
        $cfg['delegada'] = 0;
    }
    return $cfg ?: null;
}

// gestion__integraciones_delegaciones (_local/sql/mantenimiento/20261019_arca_certificado_parametros_02_delegaciones.sql)
function arca_delegaciones_existen($conexion)
{
    static $existe = null;
    if ($existe === null) {
        $res = mysqli_query($conexion, "SHOW TABLES LIKE 'gestion__integraciones_delegaciones'");
        $existe = $res && mysqli_num_rows($res) > 0;
    }
    return $existe;
}

// Condición SQL de delegación vigente; el alias de la tabla es d.
function arca_sql_delegacion_vigente()
{
    return "d.tabla_estado_registro_id = 1 AND d.fecha_desde <= CURDATE()
            AND (d.fecha_hasta IS NULL OR d.fecha_hasta >= CURDATE())";
}

// Configuración de una integración para la empresa que la usa: la propia o una que la titular le
// delegó (vigente). En la delegada, el certificado y el TA son los de la titular, pero la llamada a
// WSFE va con el CUIT de la empresa que factura (en ARCA esa empresa delegó el servicio en el CUIT
// del certificado) y el historial queda en la empresa que factura. Null si no la puede usar.
function arca_obtener_configuracion_para_empresa($conexion, $integracion_empresa_id, $empresa_id)
{
    $cfg = arca_obtener_configuracion($conexion, $integracion_empresa_id, $empresa_id);
    if ($cfg || !arca_delegaciones_existen($conexion)) {
        return $cfg;
    }

    $sql = "SELECT ie.integracion_empresa_id, ie.empresa_id, ie.integracion_ambiente_id,
                   ie.servicio, ie.cuit, ie.certificado_ruta, ie.clave_privada_ruta,
                   ie.tabla_estado_registro_id,
                   a.integracion_id, a.codigo AS ambiente_codigo, a.nombre AS ambiente_nombre,
                   a.url_wsaa, a.url_servicio, a.activo AS ambiente_activo,
                   e.documento_numero AS cuit_destino
            FROM gestion__integraciones_empresas ie
            INNER JOIN conf__integraciones_ambientes a
                ON a.integracion_ambiente_id = ie.integracion_ambiente_id
            INNER JOIN gestion__integraciones_delegaciones d
                ON d.integracion_empresa_id = ie.integracion_empresa_id AND d.empresa_destino_id = ?
            INNER JOIN conf__empresas e ON e.empresa_id = d.empresa_destino_id
            WHERE ie.integracion_empresa_id = ? AND " . arca_sql_delegacion_vigente();
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("arca_obtener_configuracion_para_empresa: " . mysqli_error($conexion));
        return null;
    }
    $integracion_empresa_id = intval($integracion_empresa_id);
    $empresa_id = intval($empresa_id);
    mysqli_stmt_bind_param($stmt, "ii", $empresa_id, $integracion_empresa_id);
    mysqli_stmt_execute($stmt);
    $cfg = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$cfg) {
        return null;
    }

    $cfg['titular_empresa_id'] = $cfg['empresa_id'];
    $cfg['certificado_cuit'] = $cfg['cuit'];
    $cfg['empresa_id'] = $empresa_id;
    $cfg['cuit'] = preg_replace('/\D/', '', (string)$cfg['cuit_destino']);
    $cfg['delegada'] = 1;
    unset($cfg['cuit_destino']);
    return $cfg;
}

// Ruta absoluta de un certificado/clave, o null si no existe o queda fuera de arca/.
// Las rutas se guardan relativas a la raíz del proyecto (ej. arca/wsaa/cert/x.crt).
function arca_resolver_ruta($ruta)
{
    $ruta = trim((string)$ruta);
    if ($ruta === '') {
        return null;
    }

    $raiz = dirname(__DIR__);
    $absoluta = realpath($raiz . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $ruta));
    $base = realpath(__DIR__);

    if ($absoluta === false || $base === false || !is_file($absoluta)) {
        return null;
    }
    if (strpos($absoluta, $base . DIRECTORY_SEPARATOR) !== 0) {
        return null;
    }
    return $absoluta;
}

// Datos del certificado X.509 (sin exponer la clave): CN, CUIT del subject y vigencia.
function arca_info_certificado($ruta)
{
    $absoluta = arca_resolver_ruta($ruta);
    if ($absoluta === null) {
        return ['ok' => false, 'error' => 'Certificado no encontrado dentro de arca/'];
    }

    $info = openssl_x509_parse(file_get_contents($absoluta));
    if ($info === false) {
        return ['ok' => false, 'error' => 'El archivo no es un certificado X.509 válido'];
    }

    $serial = $info['subject']['serialNumber'] ?? '';
    return [
        'ok' => true,
        'cn' => $info['subject']['CN'] ?? '',
        'cuit' => preg_replace('/\D/', '', $serial),
        'emisor' => $info['issuer']['CN'] ?? '',
        'valido_desde' => date('Y-m-d H:i:s', $info['validFrom_time_t']),
        'valido_hasta' => date('Y-m-d H:i:s', $info['validTo_time_t']),
        'vencido' => $info['validTo_time_t'] < time()
    ];
}

// Valida que la clave privada exista, sea legible por OpenSSL y corresponda al certificado.
function arca_validar_par_certificado($ruta_certificado, $ruta_clave)
{
    $cert = arca_resolver_ruta($ruta_certificado);
    $clave = arca_resolver_ruta($ruta_clave);
    if ($cert === null) {
        return 'El certificado no existe o está fuera del directorio arca/';
    }
    if ($clave === null) {
        return 'La clave privada no existe o está fuera del directorio arca/';
    }

    $x509 = openssl_x509_read(file_get_contents($cert));
    if ($x509 === false) {
        return 'El certificado no es un X.509 válido';
    }
    $pkey = openssl_pkey_get_private(file_get_contents($clave));
    if ($pkey === false) {
        return 'OpenSSL no pudo interpretar la clave privada';
    }
    if (!openssl_x509_check_private_key($x509, $pkey)) {
        return 'La clave privada no corresponde al certificado';
    }
    return null;
}

// Archivos .crt / .pem / .key disponibles bajo arca/ (para los combos del ABM).
// El certificado de ARCA es de cada empresa: con $cuit solo se listan los certificados emitidos para ese
// CUIT y las claves privadas que corresponden a alguno de ellos (nunca los de otra empresa).
function arca_listar_archivos_certificados($cuit = null)
{
    $archivos = arca_listar_archivos_certificados_todos();
    if ($cuit === null) {
        return $archivos;
    }
    $cuit = preg_replace('/\D/', '', (string)$cuit);
    $propios = ['certificados' => [], 'claves' => []];
    if ($cuit === '') {
        return $propios;
    }
    $x509s = [];
    foreach ($archivos['certificados'] as $ruta) {
        $info = arca_info_certificado($ruta);
        if (!empty($info['ok']) && $info['cuit'] === $cuit) {
            $propios['certificados'][] = $ruta;
            $x509s[] = openssl_x509_read(file_get_contents(arca_resolver_ruta($ruta)));
        }
    }
    foreach ($archivos['claves'] as $ruta) {
        $absoluta = arca_resolver_ruta($ruta);
        $pkey = $absoluta ? @openssl_pkey_get_private(file_get_contents($absoluta)) : false;
        if ($pkey === false) {
            continue;
        }
        foreach ($x509s as $x509) {
            if ($x509 !== false && openssl_x509_check_private_key($x509, $pkey)) {
                $propios['claves'][] = $ruta;
                break;
            }
        }
    }
    return $propios;
}

function arca_listar_archivos_certificados_todos()
{
    $raiz = realpath(dirname(__DIR__));
    $archivos = ['certificados' => [], 'claves' => []];

    $iterador = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(__DIR__, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterador as $archivo) {
        $extension = strtolower($archivo->getExtension());
        $relativa = str_replace('\\', '/', substr($archivo->getRealPath(), strlen($raiz) + 1));
        if ($extension === 'crt' || $extension === 'pem') {
            $archivos['certificados'][] = $relativa;
        } elseif ($extension === 'key') {
            $archivos['claves'][] = $relativa;
        }
    }
    sort($archivos['certificados']);
    sort($archivos['claves']);
    return $archivos;
}

// ------------------------------------------------------------
// Utilidades
// ------------------------------------------------------------

// Enmascara Token, Sign y CMS en requests/responses antes de guardarlos.
// Cubre tags con o sin prefijo de namespace y la versión escapada
// (&lt;token&gt;) que viene dentro de loginCmsReturn.
function arca_ocultar_credenciales($texto)
{
    if ($texto === null || $texto === '') {
        return $texto;
    }
    $texto = preg_replace('#(<(?:[\w-]+:)?(token|sign)>)(.*?)(</(?:[\w-]+:)?\2>)#is', '$1***$4', $texto);
    $texto = preg_replace('#(&lt;(token|sign)&gt;)(.*?)(&lt;/\2&gt;)#is', '$1***$4', $texto);
    $texto = preg_replace('#(<(?:[\w-]+:)?in0>)(.*?)(</(?:[\w-]+:)?in0>)#is', '$1[CMS omitido]$3', $texto);
    return $texto;
}

// ISO 8601 con offset (respuesta de ARCA) -> 'Y-m-d H:i:s' en la zona horaria de PHP.
function arca_fecha_local($iso)
{
    try {
        $fecha = new DateTime($iso);
        $fecha->setTimezone(new DateTimeZone(date_default_timezone_get()));
        return $fecha->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        return null;
    }
}

// Primer nodo por nombre local (ignora namespaces), como string; '' si no existe.
function arca_xml_valor($xml, $nombre)
{
    // ".//" busca dentro del nodo recibido; con "//" un <Err> u <Obs> leía el primer
    // Code/Msg de todo el documento (el evento informativo 39 tapaba el motivo real).
    $nodos = $xml->xpath('.//*[local-name()="' . $nombre . '"]');
    return isset($nodos[0]) ? trim((string)$nodos[0]) : '';
}

function arca_xml_cargar($texto)
{
    if ($texto === null || $texto === '') {
        return false;
    }
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($texto);
    libxml_clear_errors();
    return $xml;
}

// Id de la operación en conf__integraciones_operaciones (null si no está registrada).
function arca_operacion_id($conexion, $integracion_id, $codigo)
{
    static $cache = [];
    $clave = $integracion_id . '|' . $codigo;
    if (array_key_exists($clave, $cache)) {
        return $cache[$clave];
    }

    $sql = "SELECT integracion_operacion_id FROM conf__integraciones_operaciones
            WHERE integracion_id = ? AND codigo = ? LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, "is", $integracion_id, $codigo);
    mysqli_stmt_execute($stmt);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    $cache[$clave] = $fila ? intval($fila['integracion_operacion_id']) : null;
    if ($cache[$clave] === null) {
        error_log("arca_operacion_id: operación '$codigo' no registrada en conf__integraciones_operaciones");
    }
    return $cache[$clave];
}

// Registra una llamada en gestion__integraciones_resultados y devuelve su id.
// Se ejecuta fuera de cualquier transacción de negocio a propósito: si quien
// llama hace rollback, la evidencia de la llamada externa tiene que quedar.
function arca_registrar_resultado($conexion, $cfg, $operacion_codigo, $datos)
{
    $empresa_id = intval($cfg['empresa_id']);
    $integracion_id = intval($cfg['integracion_id']);
    $ambiente_id = intval($cfg['integracion_ambiente_id']);
    $operacion_id = arca_operacion_id($conexion, $integracion_id, $operacion_codigo);
    $codigo = isset($datos['codigo']) ? mb_substr((string)$datos['codigo'], 0, 100) : null;
    $resultado = $datos['resultado'] ?? 'ERROR';
    $descripcion = isset($datos['descripcion']) ? mb_substr((string)$datos['descripcion'], 0, 500) : null;
    // Referencia del registro de negocio: la llamada puede fijarla en $cfg (p. ej. la factura que
    // pide CAE) para que todo el histórico de esa operación quede atado a ese registro.
    $referencia_tipo = $datos['referencia_tipo'] ?? ($cfg['referencia_tipo'] ?? 'gestion__integraciones_empresas');
    $referencia_id = isset($datos['referencia_id']) ? intval($datos['referencia_id'])
        : intval($cfg['referencia_id'] ?? $cfg['integracion_empresa_id']);
    $intento = intval($datos['intento'] ?? 1);
    $fecha_solicitud = $datos['fecha_hora_solicitud'] ?? date('Y-m-d H:i:s');
    $fecha_respuesta = $datos['fecha_hora_respuesta'] ?? null;
    $solicitud = arca_ocultar_credenciales($datos['solicitud'] ?? null);
    $respuesta = arca_ocultar_credenciales($datos['respuesta'] ?? null);

    $sql = "INSERT INTO gestion__integraciones_resultados
                (empresa_id, integracion_id, integracion_ambiente_id, integracion_operacion_id,
                 codigo, resultado, descripcion, referencia_tipo, referencia_id, intento,
                 fecha_hora_solicitud, fecha_hora_respuesta, solicitud, respuesta)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("arca_registrar_resultado: " . mysqli_error($conexion));
        return null;
    }
    mysqli_stmt_bind_param(
        $stmt,
        "iiiissssiissss",
        $empresa_id,
        $integracion_id,
        $ambiente_id,
        $operacion_id,
        $codigo,
        $resultado,
        $descripcion,
        $referencia_tipo,
        $referencia_id,
        $intento,
        $fecha_solicitud,
        $fecha_respuesta,
        $solicitud,
        $respuesta
    );
    if (!mysqli_stmt_execute($stmt)) {
        error_log("arca_registrar_resultado: " . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return null;
    }
    $id = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);
    return $id;
}

// POST SOAP. Distingue timeout (errno 28) del resto de los errores de transporte.
function arca_http_soap($url, $soap_action, $xml, $timeout = 60)
{
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $xml,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: text/xml; charset=utf-8',
            'SOAPAction: "' . $soap_action . '"',
            'Content-Length: ' . strlen($xml)
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        // Los servidores de WSFE negocian DH de 1024 bits: OpenSSL 3 los rechaza con
        // "dh key too small" (en Ubuntu también con SECLEVEL=1). Se baja solo el nivel
        // de seguridad, el certificado del servidor se sigue verificando.
        CURLOPT_SSL_CIPHER_LIST => 'DEFAULT@SECLEVEL=0',
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_TIMEOUT => $timeout
    ]);

    $body = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $http_code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
    curl_close($ch);

    return [
        'ok' => $body !== false && $errno === 0,
        'http_code' => $http_code,
        'body' => $body === false ? null : $body,
        'errno' => $errno,
        'error' => $error,
        'timeout' => $errno === CURLE_OPERATION_TIMEDOUT
    ];
}

// ------------------------------------------------------------
// WSAA
// ------------------------------------------------------------

function arca_generar_tra($servicio)
{
    $ahora = time();
    return '<?xml version="1.0" encoding="UTF-8"?>'
        . '<loginTicketRequest version="1.0"><header>'
        . '<uniqueId>' . $ahora . '</uniqueId>'
        . '<generationTime>' . gmdate('Y-m-d\TH:i:s\Z', $ahora - 600) . '</generationTime>'
        . '<expirationTime>' . gmdate('Y-m-d\TH:i:s\Z', $ahora + 600) . '</expirationTime>'
        . '</header>'
        . '<service>' . htmlspecialchars($servicio, ENT_XML1, 'UTF-8') . '</service>'
        . '</loginTicketRequest>';
}

// Firma el TRA como CMS attached (sin PKCS7_DETACHED, como el ejemplo oficial).
// Devuelve ['ok' => bool, 'cms' => base64, 'error' => string].
function arca_firmar_tra($tra, $ruta_certificado, $ruta_clave)
{
    $cert = arca_resolver_ruta($ruta_certificado);
    $clave = arca_resolver_ruta($ruta_clave);
    if ($cert === null || $clave === null) {
        return ['ok' => false, 'error' => 'Certificado o clave privada no encontrados dentro de arca/'];
    }

    $archivo_tra = tempnam(sys_get_temp_dir(), 'arca_tra_');
    $archivo_cms = tempnam(sys_get_temp_dir(), 'arca_cms_');
    if ($archivo_tra === false || $archivo_cms === false) {
        return ['ok' => false, 'error' => 'No se pudieron crear los archivos temporales'];
    }

    file_put_contents($archivo_tra, $tra);
    $firmado = openssl_pkcs7_sign(
        $archivo_tra,
        $archivo_cms,
        file_get_contents($cert),
        [file_get_contents($clave), ''],
        [],
        0
    );

    $contenido = $firmado ? file_get_contents($archivo_cms) : false;
    @unlink($archivo_tra);
    @unlink($archivo_cms);

    if (!$firmado || $contenido === false) {
        $errores = [];
        while ($e = openssl_error_string()) {
            $errores[] = $e;
        }
        return ['ok' => false, 'error' => 'Error al firmar el TRA: ' . implode(' | ', $errores)];
    }

    // openssl_pkcs7_sign antepone encabezados MIME: el CMS en base64 empieza
    // después de la primera línea en blanco.
    $partes = preg_split("/\r?\n\r?\n/", $contenido, 2);
    $base64 = preg_replace('/\s+/', '', $partes[1] ?? '');
    $binario = base64_decode($base64, true);
    if ($base64 === '' || $binario === false) {
        return ['ok' => false, 'error' => 'No se pudo extraer el CMS en base64'];
    }

    return ['ok' => true, 'cms' => base64_encode($binario)];
}

// Interpreta un loginTicketResponse (sobre SOAP completo o el XML interno).
// Devuelve ['ok', 'token', 'sign', 'generacion', 'vencimiento', 'source', 'destination', 'error', 'codigo'].
function arca_parsear_login_ticket($texto)
{
    $xml = arca_xml_cargar($texto);
    if ($xml === false) {
        return ['ok' => false, 'codigo' => 'xml_invalido', 'error' => 'La respuesta de WSAA no es XML válido'];
    }

    // En el sobre SOAP el ticket viene como texto escapado dentro de loginCmsReturn.
    $interno = arca_xml_valor($xml, 'loginCmsReturn');
    if ($interno !== '') {
        $xml = arca_xml_cargar($interno);
        if ($xml === false) {
            return ['ok' => false, 'codigo' => 'xml_invalido', 'error' => 'loginCmsReturn no contiene XML válido'];
        }
    }

    $token = arca_xml_valor($xml, 'token');
    $sign = arca_xml_valor($xml, 'sign');
    if ($token === '' || $sign === '') {
        $codigo = arca_xml_valor($xml, 'faultcode');
        $mensaje = arca_xml_valor($xml, 'faultstring');
        return [
            'ok' => false,
            'codigo' => $codigo !== '' ? preg_replace('/^.*:/', '', $codigo) : 'sin_credenciales',
            'error' => $mensaje !== '' ? $mensaje : 'WSAA no devolvió Token y Sign'
        ];
    }

    return [
        'ok' => true,
        'token' => $token,
        'sign' => $sign,
        'generacion' => arca_fecha_local(arca_xml_valor($xml, 'generationTime')),
        'vencimiento' => arca_fecha_local(arca_xml_valor($xml, 'expirationTime')),
        'source' => arca_xml_valor($xml, 'source'),
        'destination' => arca_xml_valor($xml, 'destination')
    ];
}

// El TA es de cada certificado: desde 20261018_arca_certificado_parametros_01.sql se guarda por
// integracion_empresa_id (puede haber varias integraciones por empresa + ambiente + servicio).
// Sin la columna (script sin correr): una fila por empresa + ambiente + servicio, como antes.
function arca_ta_por_integracion($conexion)
{
    static $existe = null;
    if ($existe === null) {
        $res = mysqli_query($conexion, "SHOW COLUMNS FROM gestion__integraciones_autenticaciones LIKE 'integracion_empresa_id'");
        $existe = $res && mysqli_num_rows($res) > 0;
    }
    return $existe;
}

// TA vigente en BD (con margen) o null.
function arca_ta_vigente($conexion, $cfg)
{
    $limite = date('Y-m-d H:i:s', time() + ARCA_TA_MARGEN_SEGUNDOS);
    $sql = "SELECT token, sign, fecha_hora_generacion, fecha_hora_vencimiento
            FROM gestion__integraciones_autenticaciones
            WHERE empresa_id = ? AND integracion_ambiente_id = ? AND servicio = ?
              AND activo = 1 AND fecha_hora_vencimiento > ?"
        . (arca_ta_por_integracion($conexion) ? " AND integracion_empresa_id = ?" : "")
        . " LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return null;
    }
    // El TA es del certificado: en una integración delegada se guarda con la empresa titular
    $empresa_id = intval($cfg['titular_empresa_id'] ?? $cfg['empresa_id']);
    $ambiente_id = intval($cfg['integracion_ambiente_id']);
    if (arca_ta_por_integracion($conexion)) {
        $integracion_id = intval($cfg['integracion_empresa_id']);
        mysqli_stmt_bind_param($stmt, "iissi", $empresa_id, $ambiente_id, $cfg['servicio'], $limite, $integracion_id);
    } else {
        mysqli_stmt_bind_param($stmt, "iiss", $empresa_id, $ambiente_id, $cfg['servicio'], $limite);
    }
    mysqli_stmt_execute($stmt);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $fila ?: null;
}

// Vencimiento del TA vigente para mostrar en pantalla (sin Token/Sign).
function arca_estado_ta($conexion, $cfg)
{
    $ta = arca_ta_vigente($conexion, $cfg);
    return $ta
        ? ['vigente' => true, 'generacion' => $ta['fecha_hora_generacion'], 'vencimiento' => $ta['fecha_hora_vencimiento']]
        : ['vigente' => false];
}

function arca_guardar_ta($conexion, $cfg, $ticket)
{
    $por_integracion = arca_ta_por_integracion($conexion);
    $sql = "INSERT INTO gestion__integraciones_autenticaciones
                (empresa_id, integracion_ambiente_id, servicio, tipo_autenticacion, token, sign,
                 fecha_hora_generacion, fecha_hora_vencimiento, activo, fecha_hora_actualizacion"
        . ($por_integracion ? ", integracion_empresa_id" : "") . ")
            VALUES (?, ?, ?, 'token_sign', ?, ?, ?, ?, 1, ?" . ($por_integracion ? ", ?" : "") . ")
            ON DUPLICATE KEY UPDATE
                tipo_autenticacion = VALUES(tipo_autenticacion), token = VALUES(token), sign = VALUES(sign),
                fecha_hora_generacion = VALUES(fecha_hora_generacion),
                fecha_hora_vencimiento = VALUES(fecha_hora_vencimiento),
                activo = 1, fecha_hora_actualizacion = VALUES(fecha_hora_actualizacion)";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("arca_guardar_ta: " . mysqli_error($conexion));
        return false;
    }
    $empresa_id = intval($cfg['titular_empresa_id'] ?? $cfg['empresa_id']);
    $ambiente_id = intval($cfg['integracion_ambiente_id']);
    $ahora = date('Y-m-d H:i:s');
    $params = [$empresa_id, $ambiente_id, $cfg['servicio'], $ticket['token'], $ticket['sign'],
        $ticket['generacion'], $ticket['vencimiento'], $ahora];
    $tipos = "iissssss";
    if ($por_integracion) {
        $params[] = intval($cfg['integracion_empresa_id']);
        $tipos .= "i";
    }
    mysqli_stmt_bind_param($stmt, $tipos, ...$params);
    $ok = mysqli_stmt_execute($stmt);
    if (!$ok) {
        error_log("arca_guardar_ta: " . mysqli_stmt_error($stmt));
    }
    mysqli_stmt_close($stmt);
    return $ok;
}

// Obtiene un TA: reutiliza el vigente de BD; si no hay, pide uno a WSAA y lo guarda.
// Uso interno: devuelve Token/Sign, NO pasar el resultado tal cual a AJAX.
function arca_obtener_ta($conexion, $cfg)
{
    $ta = arca_ta_vigente($conexion, $cfg);
    if ($ta) {
        return [
            'ok' => true,
            'reutilizado' => true,
            'token' => $ta['token'],
            'sign' => $ta['sign'],
            'generacion' => $ta['fecha_hora_generacion'],
            'vencimiento' => $ta['fecha_hora_vencimiento']
        ];
    }

    if (empty($cfg['url_wsaa'])) {
        return ['ok' => false, 'error' => 'El ambiente no tiene URL de WSAA configurada'];
    }

    $tra = arca_generar_tra($cfg['servicio']);
    $fecha_solicitud = date('Y-m-d H:i:s');
    $firma = arca_firmar_tra($tra, $cfg['certificado_ruta'], $cfg['clave_privada_ruta']);
    if (!$firma['ok']) {
        arca_registrar_resultado($conexion, $cfg, 'wsaa_login_cms', [
            'resultado' => 'ERROR',
            'codigo' => 'firma',
            'descripcion' => $firma['error'],
            'fecha_hora_solicitud' => $fecha_solicitud,
            'solicitud' => $tra
        ]);
        return ['ok' => false, 'error' => $firma['error']];
    }

    // Mismo sobre que arca/wsaa/funciones.php (probado contra wsaahomo).
    $soap = '<?xml version="1.0" encoding="UTF-8"?>'
        . '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ws="http://ar.gov.afip.dif.facturaelectronica/">'
        . '<soapenv:Header/><soapenv:Body><ws:loginCms><ws:in0>' . $firma['cms'] . '</ws:in0></ws:loginCms></soapenv:Body>'
        . '</soapenv:Envelope>';

    $http = arca_http_soap($cfg['url_wsaa'], 'loginCms', $soap);
    $fecha_respuesta = date('Y-m-d H:i:s');
    // Se registra el TRA (legible) + el sobre con el CMS enmascarado.
    $solicitud = $tra . "\n\n" . $soap;

    if (!$http['ok']) {
        $resultado = $http['timeout'] ? 'TIMEOUT' : 'ERROR';
        arca_registrar_resultado($conexion, $cfg, 'wsaa_login_cms', [
            'resultado' => $resultado,
            'codigo' => 'curl_' . $http['errno'],
            'descripcion' => $http['error'],
            'fecha_hora_solicitud' => $fecha_solicitud,
            'fecha_hora_respuesta' => $fecha_respuesta,
            'solicitud' => $solicitud
        ]);
        return ['ok' => false, 'timeout' => $http['timeout'], 'error' => 'Sin respuesta de WSAA: ' . $http['error']];
    }

    $ticket = arca_parsear_login_ticket($http['body']);
    arca_registrar_resultado($conexion, $cfg, 'wsaa_login_cms', [
        'resultado' => $ticket['ok'] ? 'OK' : 'ERROR',
        'codigo' => $ticket['ok'] ? 'HTTP ' . $http['http_code'] : $ticket['codigo'],
        'descripcion' => $ticket['ok'] ? 'TA obtenido, vence ' . $ticket['vencimiento'] : $ticket['error'],
        'fecha_hora_solicitud' => $fecha_solicitud,
        'fecha_hora_respuesta' => $fecha_respuesta,
        'solicitud' => $solicitud,
        'respuesta' => $http['body']
    ]);

    if (!$ticket['ok']) {
        $error = $ticket['error'];
        if (stripos($ticket['codigo'] . ' ' . $error, 'alreadyAuthenticated') !== false) {
            $error = 'ARCA ya emitió un TA vigente para este certificado y servicio que no está en la base '
                . '(por ejemplo, uno pedido con arca/wsaa/index.php). Importelo desde la respuesta guardada '
                . 'o espere a que venza (hasta 12 h).';
        }
        return ['ok' => false, 'codigo' => $ticket['codigo'], 'error' => $error];
    }

    if (!arca_guardar_ta($conexion, $cfg, $ticket)) {
        return ['ok' => false, 'error' => 'TA obtenido pero no se pudo guardar en la base'];
    }

    return [
        'ok' => true,
        'reutilizado' => false,
        'token' => $ticket['token'],
        'sign' => $ticket['sign'],
        'generacion' => $ticket['generacion'],
        'vencimiento' => $ticket['vencimiento']
    ];
}

// Importa el TA vigente más reciente desde las respuestas loginCms guardadas en
// arca/**/respuesta/*.xml (las que deja arca/wsaa/index.php). Sirve para no
// chocar con coe.alreadyAuthenticated cuando el TA se pidió fuera del ERP.
// Solo acepta tickets emitidos para este CUIT, este servicio y este ambiente.
function arca_importar_ta_respuestas($conexion, $cfg)
{
    $fecha_solicitud = date('Y-m-d H:i:s');
    $archivos = glob(__DIR__ . '/*/respuesta/loginTicketResponse_*.xml') ?: [];
    $limite = date('Y-m-d H:i:s', time() + ARCA_TA_MARGEN_SEGUNDOS);
    $es_homologacion = $cfg['ambiente_codigo'] === 'homologacion';

    $mejor = null;
    $mejor_archivo = null;
    foreach ($archivos as $archivo) {
        $ticket = arca_parsear_login_ticket(file_get_contents($archivo));
        if (!$ticket['ok'] || $ticket['vencimiento'] === null || $ticket['vencimiento'] <= $limite) {
            continue;
        }
        if (strpos($ticket['destination'], 'CUIT ' . ($cfg['certificado_cuit'] ?? $cfg['cuit'])) === false) {
            continue;
        }
        if ((stripos($ticket['source'], 'wsaahomo') !== false) !== $es_homologacion) {
            continue;
        }
        // El servicio solo figura dentro del token (XML firmado en base64).
        $token_xml = arca_xml_cargar(base64_decode($ticket['token']));
        $nodos = $token_xml ? $token_xml->xpath('//*[local-name()="login"]/@service') : [];
        if (!isset($nodos[0]) || (string)$nodos[0] !== $cfg['servicio']) {
            continue;
        }
        if ($mejor === null || $ticket['vencimiento'] > $mejor['vencimiento']) {
            $mejor = $ticket;
            $mejor_archivo = $archivo;
        }
    }

    if ($mejor === null) {
        $mensaje = 'No hay respuestas guardadas con un TA vigente para CUIT ' . ($cfg['certificado_cuit'] ?? $cfg['cuit'])
            . ', servicio ' . $cfg['servicio'] . ' y ambiente ' . $cfg['ambiente_nombre'];
        arca_registrar_resultado($conexion, $cfg, 'wsaa_importar_ta', [
            'resultado' => 'ERROR',
            'codigo' => 'sin_ta',
            'descripcion' => $mensaje,
            'fecha_hora_solicitud' => $fecha_solicitud,
            'fecha_hora_respuesta' => date('Y-m-d H:i:s')
        ]);
        return ['ok' => false, 'error' => $mensaje];
    }

    $ok = arca_guardar_ta($conexion, $cfg, $mejor);
    $nombre_archivo = basename($mejor_archivo);
    arca_registrar_resultado($conexion, $cfg, 'wsaa_importar_ta', [
        'resultado' => $ok ? 'OK' : 'ERROR',
        'codigo' => $nombre_archivo,
        'descripcion' => $ok ? 'TA importado, vence ' . $mejor['vencimiento'] : 'No se pudo guardar el TA',
        'fecha_hora_solicitud' => $fecha_solicitud,
        'fecha_hora_respuesta' => date('Y-m-d H:i:s')
    ]);

    return $ok
        ? ['ok' => true, 'archivo' => $nombre_archivo, 'generacion' => $mejor['generacion'], 'vencimiento' => $mejor['vencimiento']]
        : ['ok' => false, 'error' => 'No se pudo guardar el TA'];
}

// ------------------------------------------------------------
// WSFEv1
// ------------------------------------------------------------

// Llamada genérica a WSFEv1. $cuerpo son los hijos del método, con prefijo ar:.
// Si $con_auth, antepone <ar:Auth> con el TA vigente (pidiéndolo si hace falta).
// Devuelve ['ok', 'xml', 'errores', 'eventos', 'timeout', 'error', 'http_code'].
// 'ok' = hubo respuesta SOAP interpretable sin errores de ARCA.
function arca_wsfe_llamar($conexion, $cfg, $metodo, $cuerpo, $operacion_codigo, $con_auth = true, $descripcion = '')
{
    if (empty($cfg['url_servicio'])) {
        return ['ok' => false, 'error' => 'El ambiente no tiene URL de servicio configurada'];
    }

    $auth = '';
    if ($con_auth) {
        $ta = arca_obtener_ta($conexion, $cfg);
        if (!$ta['ok']) {
            return ['ok' => false, 'error' => 'Autenticación WSAA: ' . $ta['error']];
        }
        $auth = '<ar:Auth>'
            . '<ar:Token>' . htmlspecialchars($ta['token'], ENT_XML1, 'UTF-8') . '</ar:Token>'
            . '<ar:Sign>' . htmlspecialchars($ta['sign'], ENT_XML1, 'UTF-8') . '</ar:Sign>'
            . '<ar:Cuit>' . $cfg['cuit'] . '</ar:Cuit>'
            . '</ar:Auth>';
    }

    $soap = '<?xml version="1.0" encoding="UTF-8"?>'
        . '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ar="' . ARCA_WSFE_NS . '">'
        . '<soap:Header/><soap:Body>'
        . '<ar:' . $metodo . '>' . $auth . $cuerpo . '</ar:' . $metodo . '>'
        . '</soap:Body></soap:Envelope>';

    $fecha_solicitud = date('Y-m-d H:i:s');
    $http = arca_http_soap($cfg['url_servicio'], ARCA_WSFE_NS . $metodo, $soap);
    $fecha_respuesta = date('Y-m-d H:i:s');

    $registro = [
        'fecha_hora_solicitud' => $fecha_solicitud,
        'fecha_hora_respuesta' => $fecha_respuesta,
        'solicitud' => $soap,
        'respuesta' => $http['body']
    ];

    if (!$http['ok']) {
        $registro['resultado'] = $http['timeout'] ? 'TIMEOUT' : 'ERROR';
        $registro['codigo'] = 'curl_' . $http['errno'];
        $registro['descripcion'] = trim($descripcion . ' ' . $http['error']);
        $id = arca_registrar_resultado($conexion, $cfg, $operacion_codigo, $registro);
        return [
            'ok' => false,
            'timeout' => $http['timeout'],
            'error' => ($http['timeout'] ? 'Timeout' : 'Error de comunicación') . ' con WSFE: ' . $http['error'],
            'resultado_id' => $id
        ];
    }

    $xml = arca_xml_cargar($http['body']);
    if ($xml === false) {
        $registro['resultado'] = 'ERROR';
        $registro['codigo'] = 'HTTP ' . $http['http_code'];
        $registro['descripcion'] = trim($descripcion . ' Respuesta no XML');
        $id = arca_registrar_resultado($conexion, $cfg, $operacion_codigo, $registro);
        return ['ok' => false, 'error' => 'WSFE devolvió una respuesta que no es XML (HTTP ' . $http['http_code'] . ')', 'resultado_id' => $id];
    }

    $errores = [];
    foreach ($xml->xpath('//*[local-name()="Errors"]/*[local-name()="Err"]') as $err) {
        $errores[] = ['codigo' => arca_xml_valor($err, 'Code'), 'mensaje' => arca_xml_valor($err, 'Msg')];
    }
    $fault = arca_xml_valor($xml, 'faultstring');
    if ($fault !== '') {
        $errores[] = ['codigo' => preg_replace('/^.*:/', '', arca_xml_valor($xml, 'faultcode')), 'mensaje' => $fault];
    }
    $eventos = [];
    foreach ($xml->xpath('//*[local-name()="Events"]/*[local-name()="Evt"]') as $evt) {
        $eventos[] = ['codigo' => arca_xml_valor($evt, 'Code'), 'mensaje' => arca_xml_valor($evt, 'Msg')];
    }

    $registro['resultado'] = empty($errores) ? 'OK' : 'ERROR';
    $registro['codigo'] = empty($errores) ? 'HTTP ' . $http['http_code'] : $errores[0]['codigo'];
    $registro['descripcion'] = trim($descripcion . ' ' . (empty($errores) ? '' : $errores[0]['mensaje']));
    $id = arca_registrar_resultado($conexion, $cfg, $operacion_codigo, $registro);

    return [
        'ok' => empty($errores),
        'xml' => $xml,
        'errores' => $errores,
        'eventos' => $eventos,
        'http_code' => $http['http_code'],
        'error' => empty($errores) ? '' : implode(' | ', array_map(function ($e) {
            return $e['codigo'] . ': ' . $e['mensaje'];
        }, $errores)),
        'resultado_id' => $id
    ];
}

// Estado de los servidores de ARCA (no requiere autenticación).
function arca_wsfe_dummy($conexion, $cfg)
{
    $r = arca_wsfe_llamar($conexion, $cfg, 'FEDummy', '', 'wsfe_dummy', false);
    if (!isset($r['xml'])) {
        return $r;
    }
    $r['data'] = [
        'AppServer' => arca_xml_valor($r['xml'], 'AppServer'),
        'DbServer' => arca_xml_valor($r['xml'], 'DbServer'),
        'AuthServer' => arca_xml_valor($r['xml'], 'AuthServer')
    ];
    unset($r['xml']);
    return $r;
}

// Puntos de venta habilitados para web services en ARCA.
function arca_wsfe_ptos_venta($conexion, $cfg)
{
    $r = arca_wsfe_llamar($conexion, $cfg, 'FEParamGetPtosVenta', '', 'wsfe_ptos_venta');
    if (!isset($r['xml'])) {
        return $r;
    }
    $puntos = [];
    foreach ($r['xml']->xpath('//*[local-name()="PtoVenta"]') as $pto) {
        $puntos[] = [
            'Nro' => arca_xml_valor($pto, 'Nro'),
            'EmisionTipo' => arca_xml_valor($pto, 'EmisionTipo'),
            'Bloqueado' => arca_xml_valor($pto, 'Bloqueado'),
            'FchBaja' => arca_xml_valor($pto, 'FchBaja')
        ];
    }
    $r['data'] = ['puntos_venta' => $puntos];
    unset($r['xml']);
    return $r;
}

// Tipos de tributo (FEParamGetTiposTributos): códigos para <Tributos> (percepciones IIBB, etc.).
function arca_wsfe_tipos_tributos($conexion, $cfg)
{
    $r = arca_wsfe_llamar($conexion, $cfg, 'FEParamGetTiposTributos', '', 'wsfe_tipos_tributos');
    if (!isset($r['xml'])) {
        return $r;
    }
    $tipos = [];
    foreach ($r['xml']->xpath('//*[local-name()="TributoTipo"]') as $t) {
        $tipos[] = [
            'Id' => arca_xml_valor($t, 'Id'),
            'Desc' => arca_xml_valor($t, 'Desc'),
            'FchDesde' => arca_xml_valor($t, 'FchDesde'),
            'FchHasta' => arca_xml_valor($t, 'FchHasta')
        ];
    }
    $r['data'] = ['tipos_tributos' => $tipos];
    unset($r['xml']);
    return $r;
}

// Último número autorizado para punto de venta + tipo de comprobante.
function arca_wsfe_ultimo_autorizado($conexion, $cfg, $pto_vta, $cbte_tipo)
{
    $pto_vta = intval($pto_vta);
    $cbte_tipo = intval($cbte_tipo);
    $cuerpo = '<ar:PtoVta>' . $pto_vta . '</ar:PtoVta><ar:CbteTipo>' . $cbte_tipo . '</ar:CbteTipo>';
    $r = arca_wsfe_llamar($conexion, $cfg, 'FECompUltimoAutorizado', $cuerpo, 'wsfe_ultimo_autorizado', true,
        "PtoVta $pto_vta Tipo $cbte_tipo");
    if (!isset($r['xml'])) {
        return $r;
    }
    $r['data'] = [
        'PtoVta' => $pto_vta,
        'CbteTipo' => $cbte_tipo,
        'CbteNro' => $r['ok'] ? intval(arca_xml_valor($r['xml'], 'CbteNro')) : null
    ];
    unset($r['xml']);
    return $r;
}

// Consulta de un comprobante emitido (base de la conciliación ante timeout).
function arca_wsfe_comp_consultar($conexion, $cfg, $pto_vta, $cbte_tipo, $cbte_nro)
{
    $pto_vta = intval($pto_vta);
    $cbte_tipo = intval($cbte_tipo);
    $cbte_nro = intval($cbte_nro);
    $cuerpo = '<ar:FeCompConsReq>'
        . '<ar:CbteTipo>' . $cbte_tipo . '</ar:CbteTipo>'
        . '<ar:CbteNro>' . $cbte_nro . '</ar:CbteNro>'
        . '<ar:PtoVta>' . $pto_vta . '</ar:PtoVta>'
        . '</ar:FeCompConsReq>';
    $r = arca_wsfe_llamar($conexion, $cfg, 'FECompConsultar', $cuerpo, 'wsfe_comp_consultar', true,
        "PtoVta $pto_vta Tipo $cbte_tipo Nro $cbte_nro");
    if (!isset($r['xml'])) {
        return $r;
    }
    $campos = ['Concepto', 'DocTipo', 'DocNro', 'CbteDesde', 'CbteHasta', 'CbteFch', 'ImpTotal', 'ImpNeto',
        'ImpIVA', 'MonId', 'MonCotiz', 'Resultado', 'CodAutorizacion', 'EmisionTipo', 'FchVto', 'FchProceso'];
    $data = ['PtoVta' => $pto_vta, 'CbteTipo' => $cbte_tipo];
    if ($r['ok']) {
        foreach ($campos as $campo) {
            $data[$campo] = arca_xml_valor($r['xml'], $campo);
        }
    }
    $r['data'] = $data;
    unset($r['xml']);
    return $r;
}

// Solicitud de CAE para UN comprobante. El número se toma de FECompUltimoAutorizado + 1.
// $datos: pto_vta, cbte_tipo, concepto, doc_tipo, doc_nro, condicion_iva_receptor_id,
//         imp_total, imp_neto, imp_iva, alicuota_iva_id (5 = 21%), fecha (Ymd)
//         opcionales: alicuotas ([['id', 'base', 'importe'], ...] en lugar de alicuota_iva_id,
//         para varias alícuotas), imp_tot_conc (no gravado), imp_op_ex (exento) y
//         tributos ([['id', 'desc', 'base', 'alic', 'importe'], ...]) + imp_trib (percepciones),
//         cbte_nro_esperado (si el próximo número de ARCA no es ese, no se envía la solicitud:
//         quien llama ya validó ese número contra su propia numeración).
// Resultado: ['ok', 'data' => [Resultado A/R, CAE, CAEFchVto, CbteNro, observaciones], 'timeout', 'error']
// Ante timeout NO reintenta: hay que conciliar con arca_wsfe_comp_consultar.
function arca_wsfe_solicitar_cae($conexion, $cfg, $datos)
{
    $pto_vta = intval($datos['pto_vta']);
    $cbte_tipo = intval($datos['cbte_tipo']);

    $ultimo = arca_wsfe_ultimo_autorizado($conexion, $cfg, $pto_vta, $cbte_tipo);
    if (!$ultimo['ok']) {
        return ['ok' => false, 'error' => 'No se pudo obtener el último autorizado: ' . $ultimo['error']];
    }
    $numero = intval($ultimo['data']['CbteNro']) + 1;
    if (!empty($datos['cbte_nro_esperado']) && intval($datos['cbte_nro_esperado']) !== $numero) {
        return [
            'ok' => false,
            'error' => 'ARCA asignaría el número ' . $numero . ' y se esperaba el ' . intval($datos['cbte_nro_esperado'])
                . ': otro comprobante se autorizó en el medio. Reintente.',
            'data' => ['PtoVta' => $pto_vta, 'CbteTipo' => $cbte_tipo, 'CbteNro' => $numero, 'Resultado' => 'ERROR']
        ];
    }

    $fmt = function ($n) {
        return number_format((float)$n, 2, '.', '');
    };
    $alicuotas = $datos['alicuotas'] ?? null;
    if ($alicuotas === null && (float)$datos['imp_iva'] > 0) {
        $alicuotas = [['id' => $datos['alicuota_iva_id'], 'base' => $datos['imp_neto'], 'importe' => $datos['imp_iva']]];
    }
    $iva = '';
    if (!empty($alicuotas)) {
        $iva = '<ar:Iva>';
        foreach ($alicuotas as $alic) {
            $iva .= '<ar:AlicIva>'
                . '<ar:Id>' . intval($alic['id']) . '</ar:Id>'
                . '<ar:BaseImp>' . $fmt($alic['base']) . '</ar:BaseImp>'
                . '<ar:Importe>' . $fmt($alic['importe']) . '</ar:Importe>'
                . '</ar:AlicIva>';
        }
        $iva .= '</ar:Iva>';
    }

    // Otros tributos (percepciones IIBB practicadas, etc.): [['id', 'desc', 'base', 'alic', 'importe'], ...]
    $tributos = '';
    if (!empty($datos['tributos'])) {
        $tributos = '<ar:Tributos>';
        foreach ($datos['tributos'] as $t) {
            $tributos .= '<ar:Tributo>'
                . '<ar:Id>' . intval($t['id']) . '</ar:Id>'
                . '<ar:Desc>' . htmlspecialchars((string)$t['desc'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</ar:Desc>'
                . '<ar:BaseImp>' . $fmt($t['base']) . '</ar:BaseImp>'
                . '<ar:Alic>' . $fmt($t['alic']) . '</ar:Alic>'
                . '<ar:Importe>' . $fmt($t['importe']) . '</ar:Importe>'
                . '</ar:Tributo>';
        }
        $tributos .= '</ar:Tributos>';
    }

    // Comprobantes asociados de una NC/ND: [['tipo', 'pto_vta', 'nro', 'cuit'?, 'fecha'?], ...]
    $cbtes_asoc = '';
    if (!empty($datos['cbtes_asoc'])) {
        $cbtes_asoc = '<ar:CbtesAsoc>';
        foreach ($datos['cbtes_asoc'] as $c) {
            $cbtes_asoc .= '<ar:CbteAsoc>'
                . '<ar:Tipo>' . intval($c['tipo']) . '</ar:Tipo>'
                . '<ar:PtoVta>' . intval($c['pto_vta']) . '</ar:PtoVta>'
                . '<ar:Nro>' . intval($c['nro']) . '</ar:Nro>'
                . (!empty($c['cuit']) ? '<ar:Cuit>' . preg_replace('/\D/', '', (string)$c['cuit']) . '</ar:Cuit>' : '')
                . (!empty($c['fecha']) ? '<ar:CbteFch>' . preg_replace('/\D/', '', (string)$c['fecha']) . '</ar:CbteFch>' : '')
                . '</ar:CbteAsoc>';
        }
        $cbtes_asoc .= '</ar:CbtesAsoc>';
    }

    $cuerpo = '<ar:FeCAEReq>'
        . '<ar:FeCabReq><ar:CantReg>1</ar:CantReg><ar:PtoVta>' . $pto_vta . '</ar:PtoVta><ar:CbteTipo>' . $cbte_tipo . '</ar:CbteTipo></ar:FeCabReq>'
        . '<ar:FeDetReq><ar:FECAEDetRequest>'
        . '<ar:Concepto>' . intval($datos['concepto']) . '</ar:Concepto>'
        . '<ar:DocTipo>' . intval($datos['doc_tipo']) . '</ar:DocTipo>'
        . '<ar:DocNro>' . preg_replace('/\D/', '', (string)$datos['doc_nro']) . '</ar:DocNro>'
        . '<ar:CbteDesde>' . $numero . '</ar:CbteDesde>'
        . '<ar:CbteHasta>' . $numero . '</ar:CbteHasta>'
        . '<ar:CbteFch>' . preg_replace('/\D/', '', (string)$datos['fecha']) . '</ar:CbteFch>'
        . '<ar:ImpTotal>' . $fmt($datos['imp_total']) . '</ar:ImpTotal>'
        . '<ar:ImpTotConc>' . $fmt($datos['imp_tot_conc'] ?? 0) . '</ar:ImpTotConc>'
        . '<ar:ImpNeto>' . $fmt($datos['imp_neto']) . '</ar:ImpNeto>'
        . '<ar:ImpOpEx>' . $fmt($datos['imp_op_ex'] ?? 0) . '</ar:ImpOpEx>'
        . '<ar:ImpTrib>' . $fmt($datos['imp_trib'] ?? 0) . '</ar:ImpTrib>'
        . '<ar:ImpIVA>' . $fmt($datos['imp_iva']) . '</ar:ImpIVA>'
        . '<ar:MonId>PES</ar:MonId>'
        . '<ar:MonCotiz>1</ar:MonCotiz>'
        . '<ar:CondicionIVAReceptorId>' . intval($datos['condicion_iva_receptor_id']) . '</ar:CondicionIVAReceptorId>'
        . $cbtes_asoc
        . $tributos
        . $iva
        . '</ar:FECAEDetRequest></ar:FeDetReq>'
        . '</ar:FeCAEReq>';

    $descripcion = "PtoVta $pto_vta Tipo $cbte_tipo Nro $numero";
    $r = arca_wsfe_llamar($conexion, $cfg, 'FECAESolicitar', $cuerpo, 'wsfe_cae_solicitar', true, $descripcion);

    $data = ['PtoVta' => $pto_vta, 'CbteTipo' => $cbte_tipo, 'CbteNro' => $numero];
    if (!isset($r['xml'])) {
        // Sin respuesta interpretable: el número pudo haberse autorizado igual.
        $r['data'] = $data + ['Resultado' => !empty($r['timeout']) ? 'TIMEOUT' : 'ERROR'];
        return $r;
    }

    $data['Resultado'] = arca_xml_valor($r['xml'], 'Resultado');
    $data['CAE'] = arca_xml_valor($r['xml'], 'CAE');
    $data['CAEFchVto'] = arca_xml_valor($r['xml'], 'CAEFchVto');
    $data['observaciones'] = [];
    foreach ($r['xml']->xpath('//*[local-name()="Observaciones"]/*[local-name()="Obs"]') as $obs) {
        $data['observaciones'][] = ['codigo' => arca_xml_valor($obs, 'Code'), 'mensaje' => arca_xml_valor($obs, 'Msg')];
    }

    // Un rechazo (Resultado = R) viene sin <Errors>, solo con Observaciones:
    // se reclasifica el registro del histórico de OK a RECHAZO.
    if ($data['Resultado'] === 'R' && !empty($r['resultado_id'])) {
        $motivo = $data['observaciones'][0] ?? ($r['errores'][0] ?? ['codigo' => 'R', 'mensaje' => 'Rechazado']);
        $sql = "UPDATE gestion__integraciones_resultados
                SET resultado = 'RECHAZO', codigo = ?, descripcion = ?
                WHERE integracion_resultado_id = ? AND empresa_id = ?";
        $stmt = mysqli_prepare($conexion, $sql);
        if ($stmt) {
            $texto = mb_substr("$descripcion {$motivo['mensaje']}", 0, 500);
            $empresa_id = intval($cfg['empresa_id']);
            mysqli_stmt_bind_param($stmt, "ssii", $motivo['codigo'], $texto, $r['resultado_id'], $empresa_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        $r['ok'] = false;
        $r['error'] = 'ARCA rechazó el comprobante: ' . $motivo['codigo'] . ' ' . $motivo['mensaje'];
    } elseif ($data['Resultado'] === 'A' && $data['CAE'] !== '') {
        $r['ok'] = true;
    }

    $r['data'] = $data;
    unset($r['xml']);
    return $r;
}
