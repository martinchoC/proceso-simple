<?php
declare(strict_types=1);

/**
 * modules/sindicatos/sindi_base_model.php
 * Funciones compartidas para el motor de botones, estados y transiciones del Módulo 6.
 */

require_once __DIR__ . '/../../core/permisos.php';

function sindi_obtener_funciones_pagina(mysqli $conexion, int $pagina_id): array
{
    $sql = "SELECT pf.*, i.icono_clase, c.color_clase, c.bg_clase, c.text_clase
            FROM conf__paginas_funciones pf
            LEFT JOIN conf__iconos i ON pf.icono_id = i.icono_id
            LEFT JOIN conf__colores c ON pf.color_id = c.color_id
            WHERE pf.pagina_id = ? 
              AND pf.tabla_estado_registro_id = 1
            ORDER BY pf.tabla_estado_registro_origen_id, pf.orden";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return [];
    }

    mysqli_stmt_bind_param($stmt, "i", $pagina_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $funciones = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $funciones[] = $fila;
    }
    mysqli_stmt_close($stmt);

    return $funciones;
}

// ---------------------------------------------------------------------------
// Contexto del pedido y permisos por función
// ---------------------------------------------------------------------------

/**
 * Usuario y empresa del pedido. Cada _ajax.php del módulo lo fija con el $ctx de ajax_guard()
 * (sindi_contexto($ctx)); si no se fijó, se toma de la sesión.
 */
function sindi_contexto(?array $ctx = null): array
{
    static $actual = null;
    if ($ctx !== null) {
        $actual = ['usuario_id' => (int)($ctx['usuario_id'] ?? 0), 'empresa_id' => (int)($ctx['empresa_id'] ?? 0)];
    }
    return $actual ?? [
        'usuario_id' => (int)($_SESSION['usuario_id'] ?? 0),
        'empresa_id' => (int)($_SESSION['empresa_id'] ?? 0),
    ];
}

/**
 * accion_js de una página que el usuario puede ejecutar en la empresa, por sus perfiles vigentes
 * (conf__empresas_perfiles_funciones). El superadmin puede todas.
 */
function sindi_acciones_permitidas(mysqli $conexion, int $usuario_id, int $empresa_id, int $pagina_id): array
{
    static $cache = [];
    $clave = "{$usuario_id}_{$empresa_id}_{$pagina_id}";
    if (isset($cache[$clave])) {
        return $cache[$clave];
    }
    $acciones = [];
    if (defined('USUARIOS_ACCESO_TOTAL') && in_array($usuario_id, USUARIOS_ACCESO_TOTAL, true)) {
        $stmt = mysqli_prepare($conexion, "SELECT DISTINCT accion_js FROM conf__paginas_funciones WHERE pagina_id = ? AND tabla_estado_registro_id = 1");
        mysqli_stmt_bind_param($stmt, "i", $pagina_id);
    } else {
        $stmt = mysqli_prepare($conexion, "SELECT DISTINCT pf.accion_js
            FROM conf__usuarios_perfiles up
            INNER JOIN conf__empresas_perfiles ep ON ep.empresa_perfil_id = up.empresa_perfil_id
            INNER JOIN conf__empresas_perfiles_funciones epf ON epf.empresa_perfil_id = ep.empresa_perfil_id AND epf.asignado = 1
            INNER JOIN conf__paginas_funciones pf ON pf.pagina_funcion_id = epf.pagina_funcion_id
            WHERE up.usuario_id = ? AND ep.empresa_id = ? AND pf.pagina_id = ?
              AND up.tabla_estado_registro_id = 1 AND ep.tabla_estado_registro_id = 1 AND pf.tabla_estado_registro_id = 1
              AND CURDATE() BETWEEN up.fecha_inicio AND up.fecha_fin");
        mysqli_stmt_bind_param($stmt, "iii", $usuario_id, $empresa_id, $pagina_id);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($fila = mysqli_fetch_row($res)) {
        if ($fila[0] !== null) {
            $acciones[(string)$fila[0]] = true;
        }
    }
    mysqli_stmt_close($stmt);
    return $cache[$clave] = $acciones;
}

function sindi_usuario_puede(mysqli $conexion, int $pagina_id, string $accion_js, ?int $usuario_id = null, ?int $empresa_id = null): bool
{
    $ctx = sindi_contexto();
    $usuario_id = $usuario_id ?? $ctx['usuario_id'];
    $empresa_id = $empresa_id ?? $ctx['empresa_id'];
    if ($usuario_id <= 0 || $empresa_id <= 0) {
        return false;
    }
    return isset(sindi_acciones_permitidas($conexion, $usuario_id, $empresa_id, $pagina_id)[$accion_js]);
}

/** Lanza RuntimeException si el usuario del pedido no tiene la función en esa página. */
function sindi_exigir_funcion(mysqli $conexion, int $pagina_id, string $accion_js): void
{
    if (!sindi_usuario_puede($conexion, $pagina_id, $accion_js)) {
        $ctx = sindi_contexto();
        error_log("[SEGURIDAD_SINDICATOS] Función denegada: usuario {$ctx['usuario_id']} empresa {$ctx['empresa_id']} página {$pagina_id} acción {$accion_js}");
        throw new RuntimeException('No tiene permiso para esta acción.');
    }
}

function sindi_obtener_boton_agregar(mysqli $conexion, int $pagina_id): ?array
{
    if (!sindi_usuario_puede($conexion, $pagina_id, 'agregar')) {
        return null;
    }
    foreach (sindi_obtener_funciones_pagina($conexion, $pagina_id) as $f) {
        if ((int)$f['tabla_estado_registro_origen_id'] === 0 && $f['accion_js'] === 'agregar') {
            return $f;
        }
    }
    return null;
}

/**
 * Botones de un registro: funciones de la página cuyo estado de origen es el actual y que el usuario
 * puede ejecutar. Cada botón lleva la clase btn-accion y btn-accion-<accion_js> para el JS.
 */
function sindi_obtener_botones_acciones(mysqli $conexion, int $pagina_id, int $estado_actual_id, int $registro_id): string
{
    $funciones = sindi_obtener_funciones_pagina($conexion, $pagina_id);
    $html = '<div class="btn-group btn-group-sm" role="group">';

    foreach ($funciones as $f) {
        if ((int)$f['tabla_estado_registro_origen_id'] !== $estado_actual_id || $f['accion_js'] === 'agregar'
            || $f['accion_js'] === null || !sindi_usuario_puede($conexion, $pagina_id, (string)$f['accion_js'])) {
            continue;
        }
        $accion = htmlspecialchars($f['accion_js'], ENT_QUOTES, 'UTF-8');
        $nombre = htmlspecialchars($f['nombre_funcion'], ENT_QUOTES, 'UTF-8');
        $color = !empty($f['color_clase']) ? $f['color_clase'] : 'btn-outline-primary';
        $icono = !empty($f['icono_clase']) ? '<i class="' . htmlspecialchars($f['icono_clase'], ENT_QUOTES, 'UTF-8') . '"></i> ' : '';

        $html .= sprintf(
            '<button type="button" class="btn btn-sm %s btn-accion btn-accion-%s me-1" data-accion="%s" data-id="%d" title="%s">%s%s</button>',
            $color,
            $accion,
            $accion,
            $registro_id,
            $nombre,
            $icono,
            $nombre
        );
    }

    $html .= '</div>';
    return $html;
}

function sindi_ejecutar_transicion(mysqli $conexion, string $tabla, string $id_columna, int $registro_id, string $accion_js, int $pagina_id, int $empresa_id): array
{
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $tabla) || !preg_match('/^[a-zA-Z0-9_]+$/', $id_columna)) {
        return ['success' => false, 'message' => 'Parámetros de tabla o clave no válidos.'];
    }
    if (!sindi_usuario_puede($conexion, $pagina_id, $accion_js)) {
        return ['success' => false, 'message' => 'No tiene permiso para esta acción.'];
    }

    // 1. Estado actual del registro
    $sqlCheck = "SELECT tabla_estado_registro_id FROM {$tabla} WHERE {$id_columna} = ? AND empresa_id = ? LIMIT 1";
    $stmtC = mysqli_prepare($conexion, $sqlCheck);
    if (!$stmtC) {
        return ['success' => false, 'message' => 'Error al consultar estado actual.'];
    }
    mysqli_stmt_bind_param($stmtC, "ii", $registro_id, $empresa_id);
    mysqli_stmt_execute($stmtC);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtC));
    mysqli_stmt_close($stmtC);
    if (!$fila) {
        return ['success' => false, 'message' => 'Registro no encontrado en esta empresa.'];
    }
    $estadoOrigen = (int)$fila['tabla_estado_registro_id'];

    // 2. La función de esa acción para ESE estado de origen (una acción puede tener una fila por estado)
    $sqlFunc = "SELECT pf.* FROM conf__paginas_funciones pf
                WHERE pf.pagina_id = ? AND pf.accion_js = ? AND pf.tabla_estado_registro_origen_id = ?
                  AND pf.tabla_estado_registro_id = 1
                LIMIT 1";
    $stmtF = mysqli_prepare($conexion, $sqlFunc);
    mysqli_stmt_bind_param($stmtF, "isi", $pagina_id, $accion_js, $estadoOrigen);
    mysqli_stmt_execute($stmtF);
    $funcion = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtF));
    mysqli_stmt_close($stmtF);
    if (!$funcion) {
        return ['success' => false, 'message' => 'La acción no corresponde al estado actual del registro.'];
    }
    $estadoDestino = (int)$funcion['tabla_estado_registro_destino_id'];

    // 3. Ejecutar transición
    $sqlUpdate = "UPDATE {$tabla} SET tabla_estado_registro_id = ?, fecha_actualizacion = NOW() WHERE {$id_columna} = ? AND empresa_id = ?";
    $stmtU = mysqli_prepare($conexion, $sqlUpdate);
    if (!$stmtU) {
        return ['success' => false, 'message' => 'Error al preparar actualización de estado.'];
    }
    mysqli_stmt_bind_param($stmtU, "iii", $estadoDestino, $registro_id, $empresa_id);
    $ok = mysqli_stmt_execute($stmtU);
    mysqli_stmt_close($stmtU);

    if (!$ok) {
        return ['success' => false, 'message' => 'No se pudo actualizar el estado del registro.'];
    }

    return ['success' => true, 'message' => 'Acción ejecutada correctamente.', 'nuevo_estado_id' => $estadoDestino];
}

/**
 * Exige que un registro de proceso (acta, plan, volante...) pertenezca a la empresa y a un empleador
 * al que el usuario tiene acceso. Para los _ajax.php cuyas funciones de núcleo reciben solo el id.
 * Lanza RuntimeException si no corresponde.
 */
function sindi_exigir_registro_empresa(mysqli $conexion, string $tabla, int $registro_id, int $empresa_id, int $usuario_id): void
{
    $tablas = [
        'sindi__actas' => 'acta_id',
        'sindi__planes' => 'plan_id',
        'sindi__volantes' => 'volante_id',
        'sindi__ddjj' => 'ddjj_id',
    ];
    if (!isset($tablas[$tabla])) {
        throw new InvalidArgumentException("Tabla no habilitada para control de pertenencia: {$tabla}");
    }
    $pk = $tablas[$tabla];
    $stmt = mysqli_prepare($conexion, "SELECT empleador_id FROM {$tabla} WHERE {$pk} = ? AND empresa_id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "ii", $registro_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$fila) {
        throw new RuntimeException('Registro no encontrado en esta empresa.');
    }
    require_once __DIR__ . '/../../core/accesos_empleadores.php';
    AccesosEmpleadoresHelper::exigirAcceso($conexion, $usuario_id, $empresa_id, (int)$fila['empleador_id']);
}

// ---------------------------------------------------------------------------
// Entidades del módulo 6: rol y sucursales (gestion__entidades_sucursales)
// ---------------------------------------------------------------------------

/**
 * Asegura que la entidad tenga el rol indicado (EMPL, EST...) en gestion__entidades_roles.
 * El rol se resuelve por código en gestion__roles_entidades, nunca por id.
 */
function sindi_asegurar_rol_entidad(mysqli $conexion, int $entidad_id, string $rol_codigo): void
{
    $stmt = mysqli_prepare($conexion, "SELECT rol_entidad_id FROM gestion__roles_entidades WHERE rol_entidad_codigo = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "s", $rol_codigo);
    mysqli_stmt_execute($stmt);
    $rol = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$rol) {
        throw new RuntimeException("No existe el rol de entidad '{$rol_codigo}' en gestion__roles_entidades.");
    }
    $rol_id = (int)$rol['rol_entidad_id'];

    $stmt = mysqli_prepare($conexion, "SELECT entidad_rol_id, f_baja, tabla_estado_registro_id FROM gestion__entidades_roles
                                       WHERE entidad_id = ? AND rol_entidad_id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "ii", $entidad_id, $rol_id);
    mysqli_stmt_execute($stmt);
    $existe = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if ($existe) {
        // Una fila por entidad y rol (uk_entidad_rol): si estaba dada de baja, se reactiva
        if ($existe['f_baja'] !== null || (int)$existe['tabla_estado_registro_id'] !== 1) {
            $er_id = (int)$existe['entidad_rol_id'];
            $stmt = mysqli_prepare($conexion, "UPDATE gestion__entidades_roles SET f_baja = NULL, tabla_estado_registro_id = 1 WHERE entidad_rol_id = ?");
            mysqli_stmt_bind_param($stmt, "i", $er_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        return;
    }

    $stmt = mysqli_prepare($conexion, "INSERT INTO gestion__entidades_roles (entidad_id, rol_entidad_id, f_alta, tabla_estado_registro_id)
                                       VALUES (?, ?, CURDATE(), 1)");
    mysqli_stmt_bind_param($stmt, "ii", $entidad_id, $rol_id);
    if (!mysqli_stmt_execute($stmt)) {
        throw new RuntimeException('No se pudo asignar el rol de la entidad: ' . mysqli_stmt_error($stmt));
    }
    mysqli_stmt_close($stmt);
}

/**
 * Crea la sucursal 1 (Casa Central) de la entidad si no existe, o actualiza sus datos de contacto.
 * Es la única forma de dar de alta la Casa Central: la usan el alta de empleadores (página 1010),
 * la autoalta del portal y el alta de estudios. Devuelve el sucursal_id. Llamar dentro de la
 * transacción del alta.
 */
function sindi_guardar_sucursal_central(
    mysqli $conexion,
    int $empresa_id,
    int $entidad_id,
    string $direccion,
    string $telefono,
    string $email,
    ?int $localidad_id = null
): int {
    $stmt = mysqli_prepare($conexion, "SELECT sucursal_id FROM gestion__entidades_sucursales
                                       WHERE entidad_id = ? AND empresa_id = ? AND sucursal_numero = 1
                                       LIMIT 1 FOR UPDATE");
    mysqli_stmt_bind_param($stmt, "ii", $entidad_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if ($fila) {
        $sucursal_id = (int)$fila['sucursal_id'];
        $stmt = mysqli_prepare($conexion, "UPDATE gestion__entidades_sucursales
                                           SET sucursal_direccion = ?, sucursal_telefono = ?, sucursal_email = ?,
                                               localidad_id = COALESCE(?, localidad_id)
                                           WHERE sucursal_id = ?");
        mysqli_stmt_bind_param($stmt, "sssii", $direccion, $telefono, $email, $localidad_id, $sucursal_id);
        if (!mysqli_stmt_execute($stmt)) {
            throw new RuntimeException('No se pudo actualizar la Casa Central: ' . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);
        return $sucursal_id;
    }

    $stmt = mysqli_prepare($conexion, "INSERT INTO gestion__entidades_sucursales
                                           (entidad_id, empresa_id, sucursal_numero, sucursal_nombre, sucursal_direccion,
                                            localidad_id, sucursal_telefono, sucursal_email, tabla_estado_registro_id)
                                       VALUES (?, ?, 1, 'Casa Central', ?, ?, ?, ?, 1)");
    mysqli_stmt_bind_param($stmt, "iisiss", $entidad_id, $empresa_id, $direccion, $localidad_id, $telefono, $email);
    if (!mysqli_stmt_execute($stmt)) {
        throw new RuntimeException('No se pudo crear la Casa Central: ' . mysqli_stmt_error($stmt));
    }
    $sucursal_id = (int)mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);
    error_log("=== sindi_guardar_sucursal_central: entidad {$entidad_id} empresa {$empresa_id} -> sucursal {$sucursal_id} (nro 1) ===");
    return $sucursal_id;
}
