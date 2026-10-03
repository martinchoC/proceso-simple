<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
require_once 'funciones.php';

echo '<h2>ARCA - WSAA Homologación</h2>';

echo '<hr>';

echo '<b>CUIT:</b> ' . htmlspecialchars($cuit) . '<br>';
echo '<b>Servicio:</b> ' . htmlspecialchars($servicio) . '<br>';
echo '<b>Certificado:</b> ' . htmlspecialchars($certificado) . '<br>';
echo '<b>WSAA:</b> ' . htmlspecialchars($url_wsaa) . '<br>';

echo '<hr>';


// ==========================================
// 1. VERIFICAR ARCHIVOS
// ==========================================

if (!file_exists($certificado)) {

    die(
        '<h3>Error</h3>' .
        'No existe el certificado:<br>' .
        '<pre>' . htmlspecialchars($certificado) . '</pre>'
    );
}

if (!file_exists($clave_privada)) {

    die(
        '<h3>Error</h3>' .
        'No existe la clave privada:<br>' .
        '<pre>' . htmlspecialchars($clave_privada) . '</pre>'
    );
}

echo '<b>Certificado:</b> OK<br>';
echo '<b>Clave privada:</b> OK<br>';


// ==========================================
// 2. GENERAR TRA
// ==========================================

echo '<hr>';
echo '<h3>1. Generando LoginTicketRequest</h3>';

$tra = generarTRA($servicio);

echo '<pre>';
echo htmlspecialchars($tra);
echo '</pre>';


// ==========================================
// 3. FIRMAR TRA
// ==========================================

echo '<h3>2. Firmando TRA</h3>';

$cmsBase64 = firmarTRA(
    $tra,
    $certificado,
    $clave_privada
);

if ($cmsBase64 == '') {

    die('No se pudo generar el CMS.');
}

echo 'CMS generado correctamente.<br>';

echo 'Longitud CMS Base64: ' . strlen($cmsBase64) . ' caracteres.<br>';


// ==========================================
// 4. ENVIAR A WSAA
// ==========================================

echo '<h3>3. Enviando solicitud a WSAA</h3>';

$resultadoWSAA = consultarWSAA(
    $cmsBase64,
    $url_wsaa
);

echo '<b>HTTP:</b> ' . $resultadoWSAA['http_code'] . '<br>';


// Guardamos respuesta completa
$archivoRespuesta =
    $directorio_respuesta .
    'loginTicketResponse_' .
    date('Ymd_His') .
    '.xml';

if (!is_dir($directorio_respuesta)) {
    mkdir($directorio_respuesta, 0777, true);
}

file_put_contents(
    $archivoRespuesta,
    $resultadoWSAA['respuesta']
);

echo '<b>Respuesta guardada en:</b><br>';
echo '<pre>' . htmlspecialchars($archivoRespuesta) . '</pre>';


// ==========================================
// 5. EXTRAER TOKEN Y SIGN
// ==========================================

echo '<h3>4. Procesando respuesta de ARCA</h3>';

$credenciales = extraerCredenciales(
    $resultadoWSAA['respuesta']
);

if (!$credenciales['ok']) {

    echo '<h2>ERROR</h2>';

    echo '<pre>';
    echo htmlspecialchars($credenciales['error']);
    echo '</pre>';

    echo '<h3>Respuesta completa de ARCA</h3>';

    echo '<pre>';
    echo htmlspecialchars($credenciales['respuesta']);
    echo '</pre>';

    exit;
}


// ==========================================
// 6. MOSTRAR RESULTADO
// ==========================================

echo '<hr>';

echo '<h2>WSAA OK</h2>';

echo '<h3>Token</h3>';

echo '<textarea style="width:100%;height:180px;">';
echo htmlspecialchars($credenciales['token']);
echo '</textarea>';

echo '<h3>Sign</h3>';

echo '<textarea style="width:100%;height:180px;">';
echo htmlspecialchars($credenciales['sign']);
echo '</textarea>';

echo '<h3>Vigencia</h3>';

echo '<b>Generación:</b> ' .
    htmlspecialchars($credenciales['generationTime']) .
    '<br>';

echo '<b>Expiración:</b> ' .
    htmlspecialchars($credenciales['expirationTime']) .
    '<br>';

echo '<hr>';

echo '<h3>Resultado</h3>';

echo '<p>';
echo '<b>Token:</b> obtenido correctamente<br>';
echo '<b>Sign:</b> obtenido correctamente';
echo '</p>';

?>