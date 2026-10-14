<?php
declare(strict_types=1);

/**
 * modules/sindicatos/empleadores_model.php
 * Modelo para el padrón de empleadores (Página 1010).
 */

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/accesos_empleadores.php';
require_once __DIR__ . '/sindi_base_model.php';

function sindi_listar_empleadores(mysqli $conexion, int $empresa_id, int $usuario_id, int $pagina_id): array
{
    $filtroAcceso = AccesosEmpleadoresHelper::filtroSql($conexion, $usuario_id, $empresa_id, 'emp.empleador_id');

    $sql = "SELECT emp.*, ent.entidad_nombre AS entidad_razon_social, ent.cuit AS entidad_cuit,
                   suc.sucursal_telefono AS entidad_telefono, suc.sucursal_email AS entidad_email,
                   c.codigo AS convenio_codigo, cm.nombre AS mora_nombre, cm.color AS mora_color,
                   (SELECT COUNT(*) FROM gestion__entidades_sucursales sx
                     WHERE sx.entidad_id = ent.entidad_id AND sx.sucursal_numero IS NOT NULL
                       AND sx.tabla_estado_registro_id = 1) AS sucursales_activas,
                   er.estado_registro
            FROM sindi__empleadores emp
            INNER JOIN gestion__entidades ent ON ent.entidad_id = emp.entidad_id
            LEFT JOIN gestion__entidades_sucursales suc ON suc.entidad_id = ent.entidad_id AND suc.sucursal_numero = 1
            LEFT JOIN sindi__convenios c ON c.convenio_id = emp.convenio_id
            LEFT JOIN sindi__categorias_mora cm ON cm.categoria_mora_id = emp.categoria_mora_id
            LEFT JOIN conf__estados_registros er ON er.estado_registro_id = emp.tabla_estado_registro_id
            WHERE emp.empresa_id = ? AND {$filtroAcceso}
            ORDER BY ent.entidad_nombre ASC";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    mysqli_stmt_bind_param($stmt, "i", $empresa_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $lista = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $estadoId = (int)$row['tabla_estado_registro_id'];
        $id = (int)$row['empleador_id'];
        $row['acciones'] = sindi_obtener_botones_acciones($conexion, $pagina_id, $estadoId, $id);
        $lista[] = $row;
    }
    mysqli_stmt_close($stmt);

    return $lista;
}

function sindi_obtener_empleador(mysqli $conexion, int $empresa_id, int $usuario_id, int $empleador_id): ?array
{
    AccesosEmpleadoresHelper::exigirAcceso($conexion, $usuario_id, $empresa_id, $empleador_id);

    $sql = "SELECT emp.*, ent.entidad_nombre AS entidad_razon_social, ent.cuit AS entidad_cuit,
                   suc.sucursal_telefono AS entidad_telefono, suc.sucursal_email AS entidad_email,
                   ent.domicilio_legal AS entidad_domicilio, ent.localidad_id
            FROM sindi__empleadores emp
            INNER JOIN gestion__entidades ent ON ent.entidad_id = emp.entidad_id
            LEFT JOIN gestion__entidades_sucursales suc ON suc.entidad_id = ent.entidad_id AND suc.sucursal_numero = 1
            WHERE emp.empleador_id = ? AND emp.empresa_id = ? LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return null;
    mysqli_stmt_bind_param($stmt, "ii", $empleador_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row;
}

function sindi_guardar_empleador(mysqli $conexion, int $empresa_id, array $data, int $usuario_id): array
{
    $empleador_id = isset($data['empleador_id']) ? (int)$data['empleador_id'] : 0;
    $cuit = preg_replace('/\D/', '', (string)($data['cuit'] ?? ''));
    $razon_social = trim($data['razon_social'] ?? '');
    $email = trim($data['email'] ?? '');
    $telefono = trim($data['telefono'] ?? '');
    $domicilio = trim($data['domicilio'] ?? '');
    $rubro = trim($data['rubro'] ?? '');
    $convenio_id = !empty($data['convenio_id']) ? intval($data['convenio_id']) : null;
    $f_alta_padron = !empty($data['f_alta_padron']) ? $data['f_alta_padron'] : date('Y-m-d');

    if (strlen($cuit) !== 11 || empty($razon_social)) {
        return ['success' => false, 'message' => 'CUIT (11 dígitos) y Razón Social son obligatorios.'];
    }

    if ($empleador_id > 0) {
        AccesosEmpleadoresHelper::exigirAcceso($conexion, $usuario_id, $empresa_id, $empleador_id);
    }

    mysqli_begin_transaction($conexion);
    try {
        // 1. Buscar o crear entidad en gestion__entidades
        $sqlEnt = "SELECT entidad_id FROM gestion__entidades WHERE empresa_id = ? AND cuit = ? LIMIT 1";
        $stmtE = mysqli_prepare($conexion, $sqlEnt);
        mysqli_stmt_bind_param($stmtE, "is", $empresa_id, $cuit);
        mysqli_stmt_execute($stmtE);
        $resE = mysqli_stmt_get_result($stmtE);
        $entidadExistente = mysqli_fetch_assoc($resE);
        mysqli_stmt_close($stmtE);

        $entidad_id = 0;
        if ($entidadExistente) {
            $entidad_id = (int)$entidadExistente['entidad_id'];
            $sqlUpdE = "UPDATE gestion__entidades SET entidad_nombre = ?, domicilio_legal = ? WHERE entidad_id = ?";
            $stmtUpdE = mysqli_prepare($conexion, $sqlUpdE);
            mysqli_stmt_bind_param($stmtUpdE, "ssi", $razon_social, $domicilio, $entidad_id);
            mysqli_stmt_execute($stmtUpdE);
            mysqli_stmt_close($stmtUpdE);
        } else {
            $sqlInsE = "INSERT INTO gestion__entidades (empresa_id, cuit, entidad_nombre, domicilio_legal, tabla_estado_registro_id, fecha_alta)
                        VALUES (?, ?, ?, ?, 1, NOW())";
            $stmtInsE = mysqli_prepare($conexion, $sqlInsE);
            mysqli_stmt_bind_param($stmtInsE, "isss", $empresa_id, $cuit, $razon_social, $domicilio);
            mysqli_stmt_execute($stmtInsE);
            $entidad_id = mysqli_insert_id($conexion);
            mysqli_stmt_close($stmtInsE);
        }

        // 2. Rol EMPLEADOR (por código) y sucursal N° 1 = Casa Central, que todo empleador tiene desde el alta
        sindi_asegurar_rol_entidad($conexion, $entidad_id, 'EMPL');
        sindi_guardar_sucursal_central($conexion, $empresa_id, $entidad_id, $domicilio, $telefono, $email);

        // 3. Crear o actualizar en sindi__empleadores
        if ($empleador_id > 0) {
            $sqlEmp = "UPDATE sindi__empleadores SET rubro = ?, convenio_id = ?, f_alta_padron = ?, usuario_actualizacion_id = ?, fecha_actualizacion = NOW()
                       WHERE empleador_id = ? AND empresa_id = ?";
            $stmtEmp = mysqli_prepare($conexion, $sqlEmp);
            mysqli_stmt_bind_param($stmtEmp, "sisiii", $rubro, $convenio_id, $f_alta_padron, $usuario_id, $empleador_id, $empresa_id);
            mysqli_stmt_execute($stmtEmp);
            mysqli_stmt_close($stmtEmp);
        } else {
            $stmtEmp = mysqli_prepare($conexion, "INSERT INTO sindi__empleadores (empresa_id, entidad_id, rubro, convenio_id, f_alta_padron, origen, tabla_estado_registro_id, usuario_creacion_id, fecha_creacion) VALUES (?, ?, ?, ?, ?, 'SINDICATO', 1, ?, NOW()) ON DUPLICATE KEY UPDATE rubro = VALUES(rubro), convenio_id = VALUES(convenio_id)");
            mysqli_stmt_bind_param($stmtEmp, "iisisi", $empresa_id, $entidad_id, $rubro, $convenio_id, $f_alta_padron, $usuario_id);
            mysqli_stmt_execute($stmtEmp);
            $empleador_id = mysqli_insert_id($conexion) ?: $empleador_id;
            mysqli_stmt_close($stmtEmp);
        }

        mysqli_commit($conexion);
        AccesosEmpleadoresHelper::limpiarCache();
        return ['success' => true, 'message' => 'Empleador guardado con éxito.', 'data' => ['empleador_id' => $empleador_id]];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

// ---------------------------------------------------------------------------
// Sucursales del empleador (gestion__entidades_sucursales con sucursal_numero)
// ---------------------------------------------------------------------------

/** Entidad del empleador, validando empresa y acceso del usuario. */
function sindi_entidad_de_empleador(mysqli $conexion, int $empresa_id, int $usuario_id, int $empleador_id, bool $bloquear = false): int
{
    AccesosEmpleadoresHelper::exigirAcceso($conexion, $usuario_id, $empresa_id, $empleador_id);
    $sql = "SELECT entidad_id FROM sindi__empleadores WHERE empleador_id = ? AND empresa_id = ? LIMIT 1" . ($bloquear ? " FOR UPDATE" : "");
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $empleador_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$fila) {
        throw new RuntimeException('Empleador no encontrado en esta empresa.');
    }
    return (int)$fila['entidad_id'];
}

function sindi_listar_sucursales_empleador(mysqli $conexion, int $empresa_id, int $usuario_id, int $empleador_id): array
{
    $entidad_id = sindi_entidad_de_empleador($conexion, $empresa_id, $usuario_id, $empleador_id);
    $sql = "SELECT s.sucursal_id, s.sucursal_numero, s.sucursal_nombre, s.sucursal_direccion, s.localidad_id,
                   l.localidad, s.sucursal_telefono, s.sucursal_email, s.sucursal_contacto, s.tabla_estado_registro_id
            FROM gestion__entidades_sucursales s
            LEFT JOIN conf__localidades l ON l.localidad_id = s.localidad_id
            WHERE s.entidad_id = ? AND s.empresa_id = ? AND s.sucursal_numero IS NOT NULL
            ORDER BY s.sucursal_numero";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $entidad_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $lista = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $lista[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $lista;
}

/**
 * Alta o edición de una sucursal. En el alta, el número es el siguiente de la entidad,
 * con el empleador bloqueado para que dos altas simultáneas no tomen el mismo.
 */
function sindi_guardar_sucursal_empleador(mysqli $conexion, int $empresa_id, int $usuario_id, array $data): array
{
    $empleador_id = (int)($data['empleador_id'] ?? 0);
    $sucursal_id = (int)($data['sucursal_id'] ?? 0);
    $nombre = trim((string)($data['sucursal_nombre'] ?? ''));
    $direccion = trim((string)($data['sucursal_direccion'] ?? ''));
    $localidad_id = !empty($data['localidad_id']) ? (int)$data['localidad_id'] : null;
    $telefono = trim((string)($data['sucursal_telefono'] ?? ''));
    $email = trim((string)($data['sucursal_email'] ?? ''));
    $contacto = trim((string)($data['sucursal_contacto'] ?? ''));

    if ($nombre === '') {
        return ['success' => false, 'message' => 'El nombre de la sucursal es obligatorio.'];
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'El correo electrónico no es válido.'];
    }

    error_log("=== INICIO sindi_guardar_sucursal_empleador empleador {$empleador_id} sucursal {$sucursal_id} ===");
    mysqli_begin_transaction($conexion);
    try {
        $entidad_id = sindi_entidad_de_empleador($conexion, $empresa_id, $usuario_id, $empleador_id, true);
        $numero = 0;

        if ($sucursal_id > 0) {
            $stmt = mysqli_prepare($conexion, "UPDATE gestion__entidades_sucursales
                SET sucursal_nombre = ?, sucursal_direccion = ?, localidad_id = ?, sucursal_telefono = ?,
                    sucursal_email = ?, sucursal_contacto = ?
                WHERE sucursal_id = ? AND entidad_id = ? AND empresa_id = ? AND sucursal_numero IS NOT NULL");
            mysqli_stmt_bind_param($stmt, "ssisssiii", $nombre, $direccion, $localidad_id, $telefono, $email, $contacto,
                $sucursal_id, $entidad_id, $empresa_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        } else {
            $stmt = mysqli_prepare($conexion, "SELECT COALESCE(MAX(sucursal_numero), 0) + 1 AS prox
                                               FROM gestion__entidades_sucursales WHERE entidad_id = ?");
            mysqli_stmt_bind_param($stmt, "i", $entidad_id);
            mysqli_stmt_execute($stmt);
            $numero = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['prox'];
            mysqli_stmt_close($stmt);

            $stmt = mysqli_prepare($conexion, "INSERT INTO gestion__entidades_sucursales
                    (entidad_id, empresa_id, sucursal_numero, sucursal_nombre, sucursal_direccion, localidad_id,
                     sucursal_telefono, sucursal_email, sucursal_contacto, tabla_estado_registro_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
            mysqli_stmt_bind_param($stmt, "iiississs", $entidad_id, $empresa_id, $numero, $nombre, $direccion, $localidad_id,
                $telefono, $email, $contacto);
            if (!mysqli_stmt_execute($stmt)) {
                throw new RuntimeException('No se pudo crear la sucursal: ' . mysqli_stmt_error($stmt));
            }
            mysqli_stmt_close($stmt);
        }

        mysqli_commit($conexion);
        return ['success' => true, 'message' => $numero > 0 ? "Sucursal N° {$numero} creada." : 'Sucursal actualizada.'];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        error_log("=== ERROR sindi_guardar_sucursal_empleador: " . $e->getMessage() . " ===");
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/** Habilita (1) o inhabilita (2) una sucursal. La Casa Central (N° 1) no se puede inhabilitar. */
function sindi_cambiar_estado_sucursal_empleador(mysqli $conexion, int $empresa_id, int $usuario_id, int $empleador_id, int $sucursal_id, int $estado): array
{
    if (!in_array($estado, [1, 2], true)) {
        return ['success' => false, 'message' => 'Estado no válido.'];
    }
    $entidad_id = sindi_entidad_de_empleador($conexion, $empresa_id, $usuario_id, $empleador_id);

    $stmt = mysqli_prepare($conexion, "SELECT sucursal_numero FROM gestion__entidades_sucursales
                                       WHERE sucursal_id = ? AND entidad_id = ? AND empresa_id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "iii", $sucursal_id, $entidad_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$fila || $fila['sucursal_numero'] === null) {
        return ['success' => false, 'message' => 'Sucursal no encontrada.'];
    }
    if ((int)$fila['sucursal_numero'] === 1 && $estado === 2) {
        return ['success' => false, 'message' => 'La Casa Central (sucursal N° 1) no se puede inhabilitar.'];
    }

    $stmt = mysqli_prepare($conexion, "UPDATE gestion__entidades_sucursales SET tabla_estado_registro_id = ? WHERE sucursal_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $estado, $sucursal_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return ['success' => true, 'message' => $estado === 1 ? 'Sucursal habilitada.' : 'Sucursal inhabilitada.'];
}

/** Búsqueda de localidades (catálogo común conf__localidades, ~23.000 filas) por nombre o CP. */
function sindi_buscar_localidades(mysqli $conexion, string $texto): array
{
    $texto = trim($texto);
    if (mb_strlen($texto) < 3) {
        return [];
    }
    $like = '%' . $texto . '%';
    $cp = ctype_digit($texto) ? (int)$texto : -1;
    $stmt = mysqli_prepare($conexion, "SELECT localidad_id, localidad, cp FROM conf__localidades
                                       WHERE localidad LIKE ? OR cp = ?
                                       ORDER BY localidad LIMIT 30");
    mysqli_stmt_bind_param($stmt, "si", $like, $cp);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $lista = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $lista[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $lista;
}
