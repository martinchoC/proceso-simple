<?php
/**
 * core/ajax_guard.php
 * Control de acceso común para los endpoints *_ajax.php.
 *
 * Uso típico, como primera instrucción del _ajax.php (antes de cualquier echo):
 *
 *   require_once __DIR__ . '/../../core/ajax_guard.php';
 *   $ctx = ajax_guard();                          // sesión + empresa (empresa_idx)
 *   $ctx = ajax_guard(['empresa' => false]);      // solo sesión (módulo conf, catálogos globales)
 *   $ctx = ajax_guard(['params' => ['empresa_id', 'empresa_idx']]);  // tenant en otro parámetro
 *   $ctx = ajax_guard(['pagina_idx' => 402]);     // además, acceso a la página
 *   $ctx = ajax_guard(['modulo_id' => 1, 'paginas_url' => ['usuarios.php', 'perfiles_usuarios.php']]);
 *                                                 // acceso a alguna de las páginas que lo usan
 *   $ctx = ajax_guard(['empresa' => false, 'solo_superadmin' => true]);  // legacy sin pantalla
 *
 * Devuelve ['usuario_id' => int, 'empresa_id' => int|null]. Si algo falla responde JSON
 * con código HTTP 401/400/403 y corta la ejecución.
 *
 * Empresa: se toma del primer parámetro de request de la lista 'params' que venga > 0; si no
 * viene ninguno, de $_SESSION['empresa_id'] (la fija header1.php al abrir la página). Nunca hay
 * empresa por defecto. Si el parámetro no vino en el request, se completa en $_GET/$_POST/
 * $_REQUEST con la empresa validada, para que el código existente que hace
 * `$_GET['empresa_idx'] ?? 2` use la empresa de la sesión y no la 2.
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/permisos.php';
require_once __DIR__ . '/auditoria.php';

function ajax_guard_error(int $http_code, string $codigo, string $mensaje): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code($http_code);
        header('Content-Type: application/json; charset=utf-8');
    }
    error_log("=== ajax_guard RECHAZO [$codigo] " . ($_SERVER['SCRIPT_NAME'] ?? '') . " : $mensaje ===");
    // Incluye las dos convenciones de respuesta del proyecto para que cualquier JS lo muestre.
    echo json_encode([
        'success' => false,
        'resultado' => false,
        'data' => null,
        'message' => $mensaje,
        'error' => $mensaje,
        'codigo' => $codigo,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/** Exige sesión iniciada por login.php. Devuelve el usuario_id. */
function ajax_guard_sesion(): int
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $usuario_id = (int) ($_SESSION['usuario_id'] ?? 0);
    if (empty($_SESSION['logueado']) || $_SESSION['logueado'] !== true || $usuario_id <= 0) {
        ajax_guard_error(401, 'SESSION_EXPIRED', 'Sesión expirada o no iniciada. Vuelva a ingresar al sistema.');
    }
    return $usuario_id;
}

/** True si el usuario tiene al menos un perfil vigente en la empresa (o es superadmin). */
function ajax_guard_usuario_tiene_empresa(mysqli $conexion, int $usuario_id, int $empresa_id): bool
{
    if ($empresa_id <= 0) {
        return false;
    }
    if (in_array($usuario_id, USUARIOS_ACCESO_TOTAL, true)) {
        return true;
    }
    return !empty(usuario_obtener_perfiles_empresa($conexion, $usuario_id, $empresa_id));
}

/** Corta con 403 si el usuario no tiene acceso a la empresa indicada. */
function ajax_guard_exigir_empresa(mysqli $conexion, int $usuario_id, int $empresa_id): void
{
    if (!ajax_guard_usuario_tiene_empresa($conexion, $usuario_id, $empresa_id)) {
        ajax_guard_error(403, 'ACCESS_DENIED_EMPRESA', 'No tiene permisos sobre la empresa seleccionada.');
    }
}

/**
 * Resuelve y valida la empresa activa del request.
 * @param string[] $params nombres de parámetro que llevan la empresa, en orden de prioridad
 */
function ajax_guard_empresa(mysqli $conexion, int $usuario_id, array $params = ['empresa_idx']): int
{
    $empresa_id = 0;
    foreach ($params as $p) {
        $valor = (int) ($_GET[$p] ?? $_POST[$p] ?? 0);
        if ($valor > 0) {
            $empresa_id = $valor;
            break;
        }
    }
    if ($empresa_id <= 0) {
        $empresa_id = (int) ($_SESSION['empresa_id'] ?? 0);
    }
    if ($empresa_id <= 0) {
        ajax_guard_error(400, 'INVALID_TENANT', 'Empresa no especificada. Vuelva a abrir la página desde el menú.');
    }

    ajax_guard_exigir_empresa($conexion, $usuario_id, $empresa_id);

    foreach ($params as $p) {
        if ((int) ($_GET[$p] ?? $_POST[$p] ?? 0) <= 0) {
            $_GET[$p] = $_POST[$p] = $_REQUEST[$p] = (string) $empresa_id;
        }
    }
    return $empresa_id;
}

/** Corta con 403 si el usuario no tiene acceso a la página en la empresa. */
function ajax_guard_pagina(mysqli $conexion, int $usuario_id, int $empresa_id, int $pagina_id): void
{
    if (!usuario_tiene_acceso_pagina($conexion, $usuario_id, $empresa_id, $pagina_id)) {
        ajax_guard_error(403, 'ACCESS_DENIED', 'No tiene permisos para esta página en la empresa seleccionada.');
    }
}

/**
 * Corta con 403 salvo que el usuario tenga acceso a alguna de las páginas configuradas en
 * conf__paginas con esas urls dentro del módulo. Para endpoints que se llaman desde más de una
 * pantalla o cuyo pagina_id difiere entre entornos. Si ninguna url está configurada como página,
 * solo pasa el superadmin (endpoint sin pantalla en el menú = herramienta interna).
 * @param string[] $urls
 */
function ajax_guard_paginas_url(mysqli $conexion, int $usuario_id, int $empresa_id, int $modulo_id, array $urls): void
{
    if (in_array($usuario_id, USUARIOS_ACCESO_TOTAL, true)) {
        return;
    }
    $permitidas = usuario_obtener_paginas_permitidas($conexion, $usuario_id, $empresa_id);
    foreach ($urls as $url) {
        // Puede haber más de una página activa con la misma url: alcanza con una permitida.
        $sql ="SELECT pagina_id FROM conf__paginas WHERE modulo_id = ? AND url = ? AND tabla_estado_registro_id = 1";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "is", $modulo_id, $url);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($res)) {
            if (isset($permitidas[(int) $row['pagina_id']])) {
                mysqli_stmt_close($stmt);
                return;
            }
        }
        mysqli_stmt_close($stmt);
    }
    ajax_guard_error(403, 'ACCESS_DENIED', 'No tiene permisos para esta funcionalidad.');
}

/**
 * Punto de entrada. Opciones:
 *  - 'empresa'         (bool, default true): resolver y validar la empresa.
 *  - 'params'          (string[], default ['empresa_idx']): parámetros que llevan la empresa.
 *  - 'pagina_idx'      (int|null): exige acceso a esa página (requiere empresa).
 *  - 'paginas_url'     (string[]) + 'modulo_id' (int): exige acceso a alguna de esas páginas
 *                      (ver ajax_guard_paginas_url; requiere empresa).
 *  - 'solo_superadmin' (bool): solo USUARIOS_ACCESO_TOTAL (endpoints legacy sin pantalla).
 */
function ajax_guard(array $opciones = []): array
{
    global $conn;

    $usuario_id = ajax_guard_sesion();
    $empresa_id = null;

    if (!empty($opciones['solo_superadmin']) && !in_array($usuario_id, USUARIOS_ACCESO_TOTAL, true)) {
        ajax_guard_error(403, 'ACCESS_DENIED', 'Funcionalidad restringida al administrador del sistema.');
    }

    if ($opciones['empresa'] ?? true) {
        $empresa_id = ajax_guard_empresa($conn, $usuario_id, $opciones['params'] ?? ['empresa_idx']);
        if (!empty($opciones['pagina_idx'])) {
            ajax_guard_pagina($conn, $usuario_id, $empresa_id, (int) $opciones['pagina_idx']);
        }
        if (!empty($opciones['paginas_url'])) {
            ajax_guard_paginas_url($conn, $usuario_id, $empresa_id, (int) ($opciones['modulo_id'] ?? 0), $opciones['paginas_url']);
        }
    }

    // Activar interceptor automático de mutaciones (altas, modificaciones, eliminaciones)
    ajax_guard_activar_auditoria_automatica($conn, $usuario_id, $empresa_id, (int) ($opciones['modulo_id'] ?? 0));

    return ['usuario_id' => $usuario_id, 'empresa_id' => $empresa_id];
}

/**
 * Interceptor Automático de Mutaciones para peticiones AJAX.
 * Si la petición es POST y contiene una acción de cambio de estado o datos (alta, modificación, baja),
 * registra automáticamente el evento en la auditoría anual al completarse exitosamente la petición.
 */
function ajax_guard_activar_auditoria_automatica(mysqli $conexion, int $usuario_id, ?int $empresa_id, int $modulo_id = 0): void
{
    $metodo = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if ($metodo !== 'POST' && $metodo !== 'PUT' && $metodo !== 'DELETE') {
        return;
    }

    $accionRaw = strtolower(trim((string)($_REQUEST['accion'] ?? $_POST['accion'] ?? '')));
    if ($accionRaw === '') {
        return;
    }

    // Excluir acciones de solo lectura
    $accionesLectura = [
        'listar', 'obtener', 'buscar', 'get', 'catalogos', 'kpis', 'detalle',
        'cargar', 'consultar', 'exportar', 'ver', 'check', 'validar', 'leer',
        'descargar', 'imprimir', 'preview', 'autocompletar'
    ];
    if (in_array($accionRaw, $accionesLectura, true)) {
        return;
    }

    // Excluir endpoints del propio panel de auditoría para evitar recursividad
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    if (strpos($script, 'auditoria_ajax.php') !== false) {
        return;
    }

    // Iniciar buffer de salida para poder inspeccionar la respuesta JSON y capturar IDs generados
    ob_start();

    // Clasificar el tipo de acción
    $tipoAccion = 'MODIFICACION';
    if (preg_match('/^(agregar|crear|guardar_nuevo|alta|nuevo|insertar|registrar|subir)/i', $accionRaw)) {
        $tipoAccion = 'ALTA';
    } elseif (preg_match('/^(eliminar|borrar|baja|cambiar_estado|inhabilitar|desactivar|quitar)/i', $accionRaw)) {
        $tipoAccion = 'BAJA';
    } elseif (preg_match('/^(anular|aprobar|rechazar|cerrar|reabrir|liquidar|facturar|cobrar|pagar|importar)/i', $accionRaw)) {
        $tipoAccion = strtoupper($accionRaw);
    }

    // Clonar payload para auditoría
    $postCopia = $_POST;
    unset($postCopia['csrf_token'], $postCopia['accion'], $postCopia['token']);

    // Identificar ID del registro si vino en el POST
    $registroId = null;
    $posiblesKeysId = ['id', 'registro_id', 'usuario_id', 'cliente_id', 'proveedor_id', 'empresa_id', 'comprobante_id', 'producto_id'];
    foreach ($posiblesKeysId as $pk) {
        if (!empty($_POST[$pk]) && (is_numeric($_POST[$pk]) || is_string($_POST[$pk])) && (string)$_POST[$pk] !== '0') {
            $registroId = (string)$_POST[$pk];
            break;
        }
    }

    // Inferir tabla y mapa multitabla del proceso
    $baseScript = basename($script, '.php');
    $entidad = preg_replace('/_ajax$/', '', $baseScript);

    $mapaProcesos = [
        'productos_consulta_remito' => [
            'tabla' => 'gestion__ventas_remitos',
            'tablas_involucradas' => [
                ['tabla' => 'gestion__ventas_remitos', 'rol' => 'Cabecera de Remito'],
                ['tabla' => 'gestion__ventas_remitos_detalles', 'rol' => 'Detalle de Productos'],
                ['tabla' => 'gestion__comprobantes', 'rol' => 'Comprobante y Numeración'],
                ['tabla' => 'gestion__stock_movimientos', 'rol' => 'Movimiento de Stock'],
            ]
        ],
        'ventas_remitos' => [
            'tabla' => 'gestion__ventas_remitos',
            'tablas_involucradas' => [
                ['tabla' => 'gestion__ventas_remitos', 'rol' => 'Cabecera de Remito'],
                ['tabla' => 'gestion__ventas_remitos_detalles', 'rol' => 'Detalle de Productos'],
                ['tabla' => 'gestion__comprobantes', 'rol' => 'Comprobante Asociado'],
            ]
        ],
        'ventas_pedidos' => [
            'tabla' => 'gestion__ventas_pedidos',
            'tablas_involucradas' => [
                ['tabla' => 'gestion__ventas_pedidos', 'rol' => 'Cabecera de Pedido'],
                ['tabla' => 'gestion__ventas_pedidos_detalles', 'rol' => 'Detalle de Productos'],
            ]
        ],
        'ventas_facturas' => [
            'tabla' => 'gestion__ventas_facturas',
            'tablas_involucradas' => [
                ['tabla' => 'gestion__ventas_facturas', 'rol' => 'Factura de Venta'],
                ['tabla' => 'gestion__ventas_facturas_detalles', 'rol' => 'Detalle de Factura'],
                ['tabla' => 'gestion__comprobantes', 'rol' => 'Comprobante Fiscal'],
            ]
        ],
        'compras_facturas' => [
            'tabla' => 'gestion__compras_facturas',
            'tablas_involucradas' => [
                ['tabla' => 'gestion__compras_facturas', 'rol' => 'Factura de Compra'],
                ['tabla' => 'gestion__compras_facturas_detalles', 'rol' => 'Detalle de Compra'],
            ]
        ],
        'clientes' => [
            'tabla' => 'gestion__entidades',
            'tablas_involucradas' => [
                ['tabla' => 'gestion__entidades', 'rol' => 'Entidad Cliente'],
                ['tabla' => 'gestion__entidades_sucursales', 'rol' => 'Sucursales / Domicilio'],
            ]
        ],
        'proveedores' => [
            'tabla' => 'gestion__entidades',
            'tablas_involucradas' => [
                ['tabla' => 'gestion__entidades', 'rol' => 'Entidad Proveedor'],
                ['tabla' => 'gestion__entidades_sucursales', 'rol' => 'Sucursales / Domicilio'],
            ]
        ],
    ];

    $tablaAfectada = $entidad;
    $tablasInvolucradasBase = [];

    if (isset($mapaProcesos[$entidad])) {
        $tablaAfectada = $mapaProcesos[$entidad]['tabla'];
        $tablasInvolucradasBase = $mapaProcesos[$entidad]['tablas_involucradas'];
    } elseif (strpos($script, '/modules/conf/') !== false) {
        $tablaAfectada = 'conf__' . $entidad;
    } elseif (strpos($script, '/modules/gestion/') !== false || strpos($script, '/modules/gestion_normal/') !== false) {
        $tablaAfectada = 'gestion__' . $entidad;
    }

    register_shutdown_function(function() use ($conexion, $usuario_id, $empresa_id, $modulo_id, $tipoAccion, $accionRaw, $tablaAfectada, $registroId, $postCopia, $tablasInvolucradasBase) {
        // Si el modelo registró auditoría manual detallada durante el request, omitir
        if (!empty($GLOBALS['__auditoria_manual_registrada'])) {
            return;
        }

        // Si la petición terminó con error HTTP >= 400, no registrar como mutación exitosa
        $status = http_response_code();
        if ($status >= 400) {
            return;
        }

        // Inspeccionar el buffer de salida para capturar IDs generados en ALTA o respuestas JSON
        $regIdFinal = $registroId;
        $buffer = '';
        if (ob_get_level() > 0) {
            $buffer = (string)ob_get_contents();
        }

        if (!empty($buffer)) {
            $respJson = json_decode($buffer, true);
            if (is_array($respJson)) {
                // Si la respuesta fue un error de negocio (success: false o resultado: false), no auditar
                if (isset($respJson['success']) && $respJson['success'] === false) return;
                if (isset($respJson['resultado']) && $respJson['resultado'] === false) return;

                // Extraer el ID generado en inserciones
                $candidatos = [
                    'id', 'nuevo_id', 'insert_id', 'registro_id',
                    'venta_remito_id', 'remito_id', 'comprobante_id', 'pedido_id',
                    'factura_id', 'cliente_id', 'proveedor_id', 'producto_id',
                    'articulo_id', 'usuario_id', 'empresa_id', 'sucursal_id', 'boca_id', 'entidad_id'
                ];
                foreach ($candidatos as $c) {
                    if (!empty($respJson[$c]) && (is_numeric($respJson[$c]) || is_string($respJson[$c])) && (string)$respJson[$c] !== '0') {
                        $regIdFinal = (string)$respJson[$c];
                        break;
                    }
                }
                // Si aún no se encontró, probar cualquier clave terminada en '_id'
                if ($regIdFinal === null) {
                    foreach ($respJson as $k => $v) {
                        if (str_ends_with($k, '_id') && !empty($v) && (is_numeric($v) || is_string($v)) && (string)$v !== '0') {
                            $regIdFinal = (string)$v;
                            break;
                        }
                    }
                }
            }
        }

        // Si es ALTA y aún no se capturó el ID, tomar el último ID generado en MySQL
        if ($regIdFinal === null && $tipoAccion === 'ALTA') {
            $lastId = mysqli_insert_id($conexion);
            if ($lastId > 0) {
                $regIdFinal = (string)$lastId;
            }
        }

        // Armar lista de tablas involucradas
        $tablasInv = !empty($GLOBALS['__auditoria_tablas_involucradas']) ? $GLOBALS['__auditoria_tablas_involucradas'] : [];
        if (empty($tablasInv) && !empty($tablasInvolucradasBase)) {
            foreach ($tablasInvolucradasBase as $tb) {
                $tablasInv[] = [
                    'tabla' => $tb['tabla'],
                    'registro_id' => $regIdFinal,
                    'accion' => $tipoAccion,
                    'rol' => $tb['rol'] ?? 'Entidad del proceso'
                ];
            }
        }

        $datosDespues = !empty($postCopia) ? auditoria_sanitizar_payload($postCopia) : null;
        $desc = "Operación '{$accionRaw}' ejecutada en {$tablaAfectada}";
        if ($regIdFinal !== null) {
            $desc .= " (ID: #{$regIdFinal})";
        }

        auditoria_registrar($conexion, [
            'accion' => $tipoAccion,
            'tabla_afectada' => $tablaAfectada,
            'registro_id' => $regIdFinal,
            'tablas_involucradas' => !empty($tablasInv) ? $tablasInv : null,
            'datos_despues' => $datosDespues,
            'descripcion' => $desc,
            'usuario_id' => $usuario_id,
            'empresa_id' => $empresa_id,
            'modulo_id' => $modulo_id > 0 ? $modulo_id : null,
        ]);
    });
}
