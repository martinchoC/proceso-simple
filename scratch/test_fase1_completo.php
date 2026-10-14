<?php
declare(strict_types=1);

/**
 * scratch/test_fase1_completo.php
 * Suite de validación integral y smoke test de Fase 1 (Base & Seguridad).
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../core/secretos.php';
require_once __DIR__ . '/../core/accesos_empleadores.php';
require_once __DIR__ . '/../modules/sindicatos/parametros_model.php';
require_once __DIR__ . '/../modules/sindicatos/empleadores_model.php';
require_once __DIR__ . '/../modules/sindicatos/estudios_model.php';
require_once __DIR__ . '/../modules/sindicatos/convenios_model.php';
require_once __DIR__ . '/../modules/sindicatos/conceptos_model.php';
require_once __DIR__ . '/../modules/sindicatos/tasas_interes_model.php';
require_once __DIR__ . '/../modules/sindicatos/medios_pago_model.php';

$conexion = $conn;
$empresa_id = 3; // CEC Patagones / AECBB
$usuario_id = 1; // Superadmin

echo "=== INICIO SMOKE TEST FASE 1 - MÓDULO SINDICATOS ===\n\n";

$errores = 0;
$pruebas = 0;

function verificar(string $nombre, bool $condicion, string $detalle = ''): void {
    global $pruebas, $errores;
    $pruebas++;
    if ($condicion) {
        echo "  [OK] {$nombre}\n";
    } else {
        $errores++;
        echo "  [FAIL] {$nombre}: {$detalle}\n";
    }
}

function http_post_json(string $url, array $data): array {
    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data),
            'timeout' => 10,
            'ignore_errors' => true
        ]
    ];
    $context  = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    if ($result === false) {
        return ['success' => false, 'message' => 'Fallo la conexion HTTP con ' . $url];
    }
    $decoded = json_decode($result, true);
    return is_array($decoded) ? $decoded : ['success' => false, 'message' => 'Respuesta no es JSON', 'raw' => $result];
}

function http_get_json(string $url): array {
    $options = [
        'http' => [
            'method'  => 'GET',
            'timeout' => 10,
            'ignore_errors' => true
        ]
    ];
    $context  = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    if ($result === false) {
        return ['success' => false, 'message' => 'Fallo la conexion HTTP con ' . $url];
    }
    $decoded = json_decode($result, true);
    return is_array($decoded) ? $decoded : ['success' => false, 'message' => 'Respuesta no es JSON', 'raw' => $result];
}

// 1. Criptografía AES-256-GCM
echo "1. Criptografía y Secretos (.env + core/secretos.php):\n";
$clavePlana = "MiClaveSuperSecretaSFTP_2026!";
$cifrado = secreto_cifrar($clavePlana);
$descifrado = secreto_descifrar($cifrado);
$enmascarado = secreto_enmascarar($clavePlana);
verificar("Cifrado no retorna vacío", !empty($cifrado));
verificar("Descifrado coincide con texto original", $descifrado === $clavePlana);
verificar("Enmascarado oculta el secreto", str_contains($enmascarado, '•'));

// 2. Catálogos en Base de Datos
echo "\n2. Catálogos Sembrados en gestion_multipyme:\n";
$contadores = [];
$tablas = [
    'sindi__parametros', 'sindi__convenios', 'sindi__conceptos', 'sindi__categorias',
    'sindi__categorias_escalas', 'sindi__tasas_interes', 'sindi__categorias_mora',
    'sindi__obras_sociales', 'sindi__novedades', 'sindi__bajas_motivos',
    'sindi__medios_pago', 'sindi__vencimientos'
];
foreach ($tablas as $t) {
    $res = mysqli_query($conexion, "SELECT count(*) as c FROM {$t}");
    $c = (int)mysqli_fetch_assoc($res)['c'];
    $contadores[$t] = $c;
    verificar("Tabla {$t} tiene registros", $c > 0, "Filas: {$c}");
}

// 3. Parámetros del Sindicato
echo "\n3. Parámetros del Sindicato (Página 1001):\n";
$param = sindi_obtener_parametros($conexion, $empresa_id);
verificar("Parámetros obtenidos correctamente", $param !== null);
verificar("Slug del portal es 'sindicato'", ($param['portal_slug'] ?? '') === 'sindicato');
verificar("Piso de media jornada configurado en COMPLETO", ($param['media_jornada_piso'] ?? '') === 'COMPLETO');

// 4. Accesos y Aislamiento Multi-empleador
echo "\n4. Accesos y Autorización Multi-empleador (core/accesos_empleadores.php):\n";
$accesosAdmin = AccesosEmpleadoresHelper::obtenerAccesos($conexion, 1, $empresa_id);
verificar("Superadmin (UID 1) tiene acceso total", $accesosAdmin['tiene_acceso_total'] === true);
$filtroAdmin = AccesosEmpleadoresHelper::filtroSql($conexion, 1, $empresa_id, 'emp.empleador_id');
verificar("Cláusula SQL para admin es ' 1=1 '", trim($filtroAdmin) === '1=1');

// Usuario externo ficticio UID 99999 sin asignaciones
$accesosExterno = AccesosEmpleadoresHelper::obtenerAccesos($conexion, 99999, $empresa_id);
verificar("Usuario externo no tiene acceso total", $accesosExterno['tiene_acceso_total'] === false);
$filtroExterno = AccesosEmpleadoresHelper::filtroSql($conexion, 99999, $empresa_id, 'emp.empleador_id');
verificar("Cláusula SQL para externo sin asignación bloquea (' 1=0 ')", trim($filtroExterno) === '1=0');

// 5. Flujo de Autoalta Pública en el Portal y Activación de Cuenta por HTTP
echo "\n5. Simulación de Autoalta y Activación por Portal (HTTP Real):\n";

$cuitPrueba = "30708090103";
$razonPrueba = "Comercio de Prueba SAS";
$emailPrueba = "admin@comercioprueba.com";
$baseUrlPortal = "http://localhost/casalucho/modules/sindicatos/portal/registro_ajax.php";

// Limpiar datos previos de prueba si existen
mysqli_query($conexion, "DELETE FROM conf__usuarios WHERE usuario = '{$cuitPrueba}'");
$resPrevEnt = mysqli_query($conexion, "SELECT entidad_id FROM gestion__entidades WHERE empresa_id = {$empresa_id} AND cuit = '{$cuitPrueba}'");
if ($rowPrevEnt = mysqli_fetch_assoc($resPrevEnt)) {
    $eIdPrev = (int)$rowPrevEnt['entidad_id'];
    mysqli_query($conexion, "DELETE FROM sindi__empleadores WHERE entidad_id = {$eIdPrev}");
    mysqli_query($conexion, "DELETE FROM gestion__entidades WHERE entidad_id = {$eIdPrev}");
}

// 5.1 Consultar info institucional del sindicato por portal_slug
$infoSind = http_get_json($baseUrlPortal . "?accion=info_sindicato&slug=sindicato");
verificar("Endpoint info_sindicato resuelve slug", ($infoSind['success'] ?? false) === true);

// 5.2 Enviar Autoalta
$resAlta = http_post_json($baseUrlPortal, [
    'accion' => 'registrar_empleador',
    'slug' => 'sindicato',
    'cuit' => $cuitPrueba,
    'razon_social' => $razonPrueba,
    'email' => $emailPrueba,
    'telefono' => '2914567890',
    'domicilio' => 'Alsina 123, Bahía Blanca',
    'rubro' => 'Comercio Mayorista'
]);

verificar("Respuesta exitosa en registrar_empleador", ($resAlta['success'] ?? false) === true, $resAlta['message'] ?? '');
$tokenSimulado = $resAlta['token_simulado'] ?? '';
verificar("Token de activación generado", !empty($tokenSimulado));

// Verificar entidad en BD
$resEnt = mysqli_query($conexion, "SELECT * FROM gestion__entidades WHERE empresa_id = {$empresa_id} AND cuit = '{$cuitPrueba}'");
$entidadCreada = mysqli_fetch_assoc($resEnt);
verificar("Entidad creada en gestion__entidades", $entidadCreada !== null);

$entidadId = (int)($entidadCreada['entidad_id'] ?? 0);
$resRol = mysqli_query($conexion, "SELECT * FROM gestion__entidades_roles WHERE entidad_id = {$entidadId} AND rol_entidad_id = 5");
verificar("Rol EMPLEADOR asignado en gestion__entidades_roles", mysqli_num_rows($resRol) > 0);

// Verificar padrón sindi__empleadores
$resEmp = mysqli_query($conexion, "SELECT * FROM sindi__empleadores WHERE entidad_id = {$entidadId} AND empresa_id = {$empresa_id}");
$empleadorCreado = mysqli_fetch_assoc($resEmp);
verificar("Registro creado en sindi__empleadores", $empleadorCreado !== null);
$empleadorId = (int)($empleadorCreado['empleador_id'] ?? 0);

// Verificar usuario en conf__usuarios con password_temporal
$resUser = mysqli_query($conexion, "SELECT * FROM conf__usuarios WHERE usuario = '{$cuitPrueba}'");
$userCreado = mysqli_fetch_assoc($resUser);
verificar("Usuario creado con usuario = CUIT", $userCreado !== null);
verificar("password_temporal coincide con token", ($userCreado['password_temporal'] ?? '') === $tokenSimulado);
$usuarioIdCreado = (int)($userCreado['usuario_id'] ?? 0);

// Verificar relación sindi__usuarios_empleadores
$resUE = mysqli_query($conexion, "SELECT * FROM sindi__usuarios_empleadores WHERE usuario_id = {$usuarioIdCreado} AND empleador_id = {$empleadorId}");
$ueCreado = mysqli_fetch_assoc($resUE);
verificar("Fila TITULAR creada en sindi__usuarios_empleadores", ($ueCreado['rol'] ?? '') === 'TITULAR');

// 5.3 Validar token por HTTP
$resVal = http_get_json($baseUrlPortal . "?accion=validar_token&slug=sindicato&token=" . urlencode($tokenSimulado));
verificar("Validación de token exitosa por HTTP", ($resVal['success'] ?? false) === true);

// 5.4 Activar cuenta y establecer contraseña por HTTP
$claveNueva = "ClaveSindical2026!";
$resAct = http_post_json($baseUrlPortal, [
    'accion' => 'activar_cuenta',
    'slug' => 'sindicato',
    'token' => $tokenSimulado,
    'clave' => $claveNueva,
    'clave_confirmacion' => $claveNueva
]);
verificar("Activación de cuenta con contraseña exitosa por HTTP", ($resAct['success'] ?? false) === true);

// Comprobar que en BD password_temporal se vació y password_verify funciona
$resUserAct = mysqli_query($conexion, "SELECT * FROM conf__usuarios WHERE usuario_id = {$usuarioIdCreado}");
$uAct = mysqli_fetch_assoc($resUserAct);
verificar("password_temporal quedó NULL tras activación", empty($uAct['password_temporal']));
verificar("password_verify valida la contraseña establecida", password_verify($claveNueva, $uAct['password']));

// Verificar que ahora el usuario nuevo tiene acceso SOLO a su propio empleador
AccesosEmpleadoresHelper::limpiarCache();
$accesosNuevo = AccesosEmpleadoresHelper::obtenerAccesos($conexion, $usuarioIdCreado, $empresa_id);
verificar("Usuario nuevo no tiene acceso total", $accesosNuevo['tiene_acceso_total'] === false);
verificar("Usuario nuevo tiene acceso concedido a su empleador", isset($accesosNuevo['empleadores'][$empleadorId]));
$filtroNuevo = AccesosEmpleadoresHelper::filtroSql($conexion, $usuarioIdCreado, $empresa_id, 'emp.empleador_id');
verificar("Filtro SQL del usuario nuevo restringe a su ID", strpos($filtroNuevo, "IN ({$empleadorId})") !== false);

// 6. Prueba de creación y vinculación de Estudio Contable (Página 1011)
echo "\n6. Creación y Vinculación de Estudio Contable:\n";
$cuitEstudio = "20222222223";
$resEstudioPrev = mysqli_query($conexion, "SELECT entidad_id FROM gestion__entidades WHERE empresa_id = {$empresa_id} AND cuit = '{$cuitEstudio}'");
if ($rEp = mysqli_fetch_assoc($resEstudioPrev)) {
    $eId = (int)$rEp['entidad_id'];
    mysqli_query($conexion, "DELETE FROM sindi__estudios WHERE entidad_id = {$eId}");
    mysqli_query($conexion, "DELETE FROM gestion__entidades WHERE entidad_id = {$eId}");
}

$dataEstudio = [
    'estudio_id' => 0,
    'cuit' => $cuitEstudio,
    'razon_social' => 'Estudio Impositivo Contable López & Asoc.',
    'email' => 'lopez@estudio.com',
    'telefono' => '2914112233',
    'domicilio' => 'San Martín 456',
    'observaciones' => 'Estudio habilitado'
];
$resGuardarEst = sindi_guardar_estudio($conexion, $empresa_id, $dataEstudio, $usuario_id);
verificar("Estudio contable guardado exitosamente", $resGuardarEst['success'] === true);

$resEstObj = mysqli_query($conexion, "SELECT est.estudio_id FROM sindi__estudios est INNER JOIN gestion__entidades ent ON ent.entidad_id = est.entidad_id WHERE ent.cuit = '{$cuitEstudio}'");
$rowEstObj = mysqli_fetch_assoc($resEstObj);
$estudioId = (int)($rowEstObj['estudio_id'] ?? 0);
verificar("ID de estudio contable recuperado", $estudioId > 0);

// Vincular estudio con el empleador de prueba (iniciado por SINDICATO -> activo directo)
$resVinc = sindi_vincular_estudio_empleador($conexion, $empresa_id, $estudioId, $empleadorId, 'SINDICATO', $usuario_id);
verificar("Vinculación estudio-empleador registrada", $resVinc['success'] === true);

$listaVinc = sindi_listar_vinculaciones($conexion, $empresa_id, $estudioId);
verificar("Listado de vinculaciones contiene al empleador", count($listaVinc) > 0 && (int)$listaVinc[0]['empleador_id'] === $empleadorId);

echo "\n============================================\n";
echo "RESULTADOS SMOKE TEST FASE 1:\n";
echo "Pruebas ejecutadas: {$pruebas}\n";
echo "Errores detectados: {$errores}\n";
echo "============================================\n";

if ($errores === 0) {
    echo "\n>>> ¡TODAS LAS PRUEBAS DE FASE 1 PASARON CON ÉXITO! <<<\n";
    exit(0);
} else {
    echo "\n>>> SE ENCONTRARON ERRORES EN LA EJECUCIÓN <<<\n";
    exit(1);
}
