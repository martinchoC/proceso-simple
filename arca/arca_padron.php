<?php
// ============================================================
// ARCA - Padrón: web service Constancia de Inscripción
// (WSAA servicio ws_sr_constancia_inscripcion, personaServiceA5, operaciones dummy y getPersona_v2).
// Misma infraestructura que WSFE (arca_lib.php): configuración en gestion__integraciones_empresas con un ambiente
// de la integración ARCA_PADRON, TA reutilizado, cada llamada en gestion__integraciones_resultados.
// Script: _local/sql/mantenimiento/20261104_arca_padron_01.sql. El cruce con el ERP está en core/arca_padron_service.php.
// ============================================================

require_once __DIR__ . '/arca_lib.php';

define('ARCA_PADRON_NS', 'http://a5.soap.ws.server.puc.sr/');
define('ARCA_PADRON_SERVICIO', 'ws_sr_constancia_inscripcion');

// Configuración de padrón de la empresa: su integración activa (o una delegada vigente), producción antes que
// homologación. Null si no tiene.
function arca_padron_configuracion($conexion, $empresa_id)
{
    $empresa_id = intval($empresa_id);
    $delegada = arca_delegaciones_existen($conexion)
        ? " OR EXISTS (SELECT 1 FROM gestion__integraciones_delegaciones d
                       WHERE d.integracion_empresa_id = ie.integracion_empresa_id AND d.empresa_destino_id = ? AND "
          . arca_sql_delegacion_vigente() . ")"
        : "";
    $sql = "SELECT ie.integracion_empresa_id
            FROM gestion__integraciones_empresas ie
            INNER JOIN conf__integraciones_ambientes a ON a.integracion_ambiente_id = ie.integracion_ambiente_id AND a.activo = 1
            INNER JOIN conf__integraciones i ON i.integracion_id = a.integracion_id AND i.codigo = 'ARCA_PADRON' AND i.activo = 1
            WHERE ie.tabla_estado_registro_id = 1 AND ie.servicio = ? AND (ie.empresa_id = ?$delegada)
            ORDER BY (ie.empresa_id = ?) DESC, (a.codigo = 'produccion') DESC, ie.integracion_empresa_id
            LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("arca_padron_configuracion: " . mysqli_error($conexion));
        return null;
    }
    $servicio = ARCA_PADRON_SERVICIO;
    if ($delegada !== '') {
        mysqli_stmt_bind_param($stmt, "siii", $servicio, $empresa_id, $empresa_id, $empresa_id);
    } else {
        mysqli_stmt_bind_param($stmt, "sii", $servicio, $empresa_id, $empresa_id);
    }
    mysqli_stmt_execute($stmt);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $fila ? arca_obtener_configuracion_para_empresa($conexion, intval($fila['integracion_empresa_id']), $empresa_id) : null;
}

// Llamada SOAP al padrón. Devuelve ['ok', 'xml', 'error', 'no_existe', 'timeout', 'resultado_id'].
function arca_padron_llamar($conexion, $cfg, $metodo, $cuerpo, $operacion_codigo, $con_auth = true, $descripcion = '')
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
        $auth = '<token>' . htmlspecialchars($ta['token'], ENT_XML1, 'UTF-8') . '</token>'
            . '<sign>' . htmlspecialchars($ta['sign'], ENT_XML1, 'UTF-8') . '</sign>'
            . '<cuitRepresentada>' . preg_replace('/\D/', '', (string)$cfg['cuit']) . '</cuitRepresentada>';
    }
    $soap = '<?xml version="1.0" encoding="UTF-8"?>'
        . '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:a5="' . ARCA_PADRON_NS . '">'
        . '<soapenv:Header/><soapenv:Body>'
        . '<a5:' . $metodo . '>' . $auth . $cuerpo . '</a5:' . $metodo . '>'
        . '</soapenv:Body></soapenv:Envelope>';

    $fecha_solicitud = date('Y-m-d H:i:s');
    $http = arca_http_soap($cfg['url_servicio'], '', $soap, 30);
    $registro = [
        'fecha_hora_solicitud' => $fecha_solicitud,
        'fecha_hora_respuesta' => date('Y-m-d H:i:s'),
        'solicitud' => $soap,
        'respuesta' => $http['body']
    ];

    if (!$http['ok']) {
        $registro['resultado'] = $http['timeout'] ? 'TIMEOUT' : 'ERROR';
        $registro['codigo'] = 'curl_' . $http['errno'];
        $registro['descripcion'] = trim($descripcion . ' ' . $http['error']);
        $id = arca_registrar_resultado($conexion, $cfg, $operacion_codigo, $registro);
        return ['ok' => false, 'timeout' => $http['timeout'], 'resultado_id' => $id,
                'error' => ($http['timeout'] ? 'ARCA no respondió a tiempo' : 'Error de comunicación con ARCA') . ': ' . $http['error']];
    }
    $xml = arca_xml_cargar($http['body']);
    if ($xml === false) {
        $registro['resultado'] = 'ERROR';
        $registro['codigo'] = 'HTTP ' . $http['http_code'];
        $registro['descripcion'] = trim($descripcion . ' Respuesta no XML');
        $id = arca_registrar_resultado($conexion, $cfg, $operacion_codigo, $registro);
        return ['ok' => false, 'error' => 'ARCA devolvió una respuesta que no es XML (HTTP ' . $http['http_code'] . ')', 'resultado_id' => $id];
    }

    $fault = arca_xml_valor($xml, 'faultstring');
    // "No existe persona con ese Id" llega como SOAP Fault: no es una falla del servicio
    $no_existe = $fault !== '' && stripos($fault, 'no existe persona') !== false;
    $registro['resultado'] = $fault === '' || $no_existe ? 'OK' : 'ERROR';
    $registro['codigo'] = $fault === '' ? 'HTTP ' . $http['http_code'] : mb_substr(preg_replace('/^.*:/', '', arca_xml_valor($xml, 'faultcode')), 0, 100);
    $registro['descripcion'] = trim($descripcion . ' ' . $fault);
    $id = arca_registrar_resultado($conexion, $cfg, $operacion_codigo, $registro);

    return ['ok' => $fault === '', 'xml' => $xml, 'no_existe' => $no_existe, 'error' => $fault, 'resultado_id' => $id];
}

// Estado de los servidores del padrón (no requiere autenticación).
function arca_padron_dummy($conexion, $cfg)
{
    $r = arca_padron_llamar($conexion, $cfg, 'dummy', '', 'padron_dummy', false);
    if (!$r['ok']) {
        return $r;
    }
    return ['ok' => true, 'data' => [
        'AppServer' => arca_xml_valor($r['xml'], 'appserver'),
        'DbServer' => arca_xml_valor($r['xml'], 'dbserver'),
        'AuthServer' => arca_xml_valor($r['xml'], 'authserver')
    ], 'resultado_id' => $r['resultado_id']];
}

// Constancia de inscripción de un CUIT, tal cual la devuelve ARCA (arrays, sin interpretar).
// ['ok' => true, 'persona' => [...]] | ['ok' => false, 'no_existe' => bool, 'error' => string]
function arca_padron_get_persona($conexion, $cfg, $cuit)
{
    $cuit = preg_replace('/\D/', '', (string)$cuit);
    if (strlen($cuit) !== 11) {
        return ['ok' => false, 'error' => 'El CUIT tiene que tener 11 dígitos'];
    }
    $r = arca_padron_llamar($conexion, $cfg, 'getPersona_v2', '<idPersona>' . $cuit . '</idPersona>', 'padron_get_persona', true, "CUIT $cuit");
    if (!$r['ok']) {
        return ['ok' => false, 'no_existe' => !empty($r['no_existe']), 'timeout' => !empty($r['timeout']),
                'error' => !empty($r['no_existe']) ? "ARCA no tiene registrado el CUIT $cuit" : $r['error']];
    }
    $ret = $r['xml']->xpath('//*[local-name()="personaReturn"]');
    if (!isset($ret[0])) {
        return ['ok' => false, 'error' => 'ARCA no devolvió datos del CUIT'];
    }
    return ['ok' => true, 'persona' => arca_padron_a_array($ret[0]), 'resultado_id' => $r['resultado_id']];
}

// SimpleXML -> array. Los elementos que el WSDL define como lista (maxOccurs unbounded) quedan siempre como lista.
function arca_padron_a_array($nodo)
{
    static $listas = ['actividad', 'impuesto', 'regimen', 'caracterizacion', 'componenteDeSociedad', 'error'];
    $hijos = $nodo->children();
    if (count($hijos) === 0) {
        return trim((string)$nodo);
    }
    $out = [];
    foreach ($hijos as $nombre => $hijo) {
        $valor = arca_padron_a_array($hijo);
        if (in_array($nombre, $listas, true)) {
            $out[$nombre][] = $valor;
        } elseif (array_key_exists($nombre, $out)) {
            $out[$nombre] = [$out[$nombre], $valor];   // repetido fuera de las listas conocidas: no pasa en getPersona_v2
        } else {
            $out[$nombre] = $valor;
        }
    }
    return $out;
}
