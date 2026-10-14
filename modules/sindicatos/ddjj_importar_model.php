<?php
declare(strict_types=1);

/**
 * modules/sindicatos/ddjj_importar_model.php
 * Importación de nóminas para DDJJ (Página 1021, tabla sindi__ddjj_importaciones).
 *
 * Cada importación lee el archivo (F.931 / SICOSS de ARCA o planilla CSV), crea o actualiza el
 * borrador de la DDJJ del empleador y período, guarda el archivo y deja registro de las filas
 * leídas, aceptadas y rechazadas con su log. Los archivos van a uploads/sindicatos/<empresa>/
 * importaciones/ con nombre aleatorio (la carpeta niega el acceso web con .htaccess).
 */

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/accesos_empleadores.php';
require_once __DIR__ . '/sindi_base_model.php';
require_once __DIR__ . '/sindi_ddjj_core_model.php';
require_once __DIR__ . '/sindi_importadores.php';

const SINDI_IMPORT_MAX_BYTES = 5 * 1024 * 1024;
const SINDI_IMPORT_FORMATOS = ['F931_SICOSS' => 'F931', 'EXCEL_CSV' => 'PLANILLA'];

function sindi_listar_importaciones(mysqli $conexion, int $empresa_id, int $usuario_id, int $pagina_id): array
{
    $filtro = AccesosEmpleadoresHelper::filtroSql($conexion, $usuario_id, $empresa_id, 'i.empleador_id');
    $sql = "SELECT i.importacion_id, i.empleador_id, i.periodo, i.formato, i.archivo_nombre, i.filas_total,
                   i.filas_aceptadas, i.filas_rechazadas, i.ddjj_id, i.tabla_estado_registro_id, i.fecha_creacion,
                   ent.entidad_nombre AS razon_social, ent.cuit, d.secuencia, d.tabla_estado_registro_id AS ddjj_estado,
                   COALESCE(ter.tabla_estado_registro, er.estado_registro) AS estado_nombre
            FROM sindi__ddjj_importaciones i
            LEFT JOIN sindi__empleadores emp ON emp.empleador_id = i.empleador_id AND emp.empresa_id = i.empresa_id
            LEFT JOIN gestion__entidades ent ON ent.entidad_id = emp.entidad_id
            LEFT JOIN sindi__ddjj d ON d.ddjj_id = i.ddjj_id AND d.empresa_id = i.empresa_id
            LEFT JOIN conf__tablas_estados_registros ter ON ter.tabla_id = 525 AND ter.estado_registro_id = i.tabla_estado_registro_id
            LEFT JOIN conf__estados_registros er ON er.estado_registro_id = i.tabla_estado_registro_id
            WHERE i.empresa_id = ? AND {$filtro}
            ORDER BY i.importacion_id DESC";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $empresa_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $lista = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $row['acciones'] = sindi_obtener_botones_acciones($conexion, $pagina_id, (int)$row['tabla_estado_registro_id'], (int)$row['importacion_id']);
        $lista[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $lista;
}

function sindi_obtener_importacion(mysqli $conexion, int $empresa_id, int $usuario_id, int $importacion_id): ?array
{
    $stmt = mysqli_prepare($conexion, "SELECT i.*, ent.entidad_nombre AS razon_social, ent.cuit
                                       FROM sindi__ddjj_importaciones i
                                       LEFT JOIN sindi__empleadores emp ON emp.empleador_id = i.empleador_id AND emp.empresa_id = i.empresa_id
                                       LEFT JOIN gestion__entidades ent ON ent.entidad_id = emp.entidad_id
                                       WHERE i.importacion_id = ? AND i.empresa_id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "ii", $importacion_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$row) {
        return null;
    }
    AccesosEmpleadoresHelper::exigirAcceso($conexion, $usuario_id, $empresa_id, (int)$row['empleador_id']);
    $row['errores'] = json_decode((string)($row['errores_detalle'] ?? '[]'), true) ?: [];
    unset($row['errores_detalle'], $row['archivo_ruta']);
    return $row;
}

/** Carpeta privada de importaciones de la empresa (la crea con un .htaccess que niega el acceso web). */
function sindi_carpeta_importaciones(int $empresa_id): string
{
    $base = dirname(__DIR__, 2) . '/uploads/sindicatos';
    if (!is_dir($base)) {
        mkdir($base, 0755, true);
    }
    if (!file_exists($base . '/.htaccess')) {
        file_put_contents($base . '/.htaccess', "Require all denied\nDeny from all\n");
    }
    $dir = $base . '/' . $empresa_id . '/importaciones';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

/**
 * Importa una nómina: valida, lee el archivo, crea o actualiza el borrador de la DDJJ del período y
 * registra la importación. Si ninguna fila es válida no toca la DDJJ y queda "Con errores" (27).
 */
function sindi_importar_nomina(
    mysqli $conexion,
    int $empresa_id,
    int $usuario_id,
    int $empleador_id,
    int $periodo,
    string $formato,
    string $nombreOriginal,
    string $contenido,
    ?int $categoria_default_id = null
): array {
    if (!isset(SINDI_IMPORT_FORMATOS[$formato])) {
        return ['success' => false, 'message' => 'Formato de archivo no válido.'];
    }
    $mes = $periodo % 100;
    if ($periodo < 200001 || $periodo > 209912 || $mes < 1 || $mes > 12) {
        return ['success' => false, 'message' => 'Período inválido (AAAAMM).'];
    }
    if ($contenido === '' || strlen($contenido) > SINDI_IMPORT_MAX_BYTES) {
        return ['success' => false, 'message' => 'El archivo está vacío o supera los 5 MB.'];
    }
    $ext = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
    if (!in_array($ext, ['txt', 'csv'], true)) {
        return ['success' => false, 'message' => 'Solo se aceptan archivos .txt (F.931) o .csv (planilla).'];
    }
    AccesosEmpleadoresHelper::exigirAcceso($conexion, $usuario_id, $empresa_id, $empleador_id);

    $stmt = mysqli_prepare($conexion, "SELECT 1 FROM sindi__empleadores WHERE empleador_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1");
    mysqli_stmt_bind_param($stmt, "ii", $empleador_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $existeEmp = (bool)mysqli_fetch_row(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$existeEmp) {
        return ['success' => false, 'message' => 'Empleador no encontrado o inactivo.'];
    }

    error_log("=== INICIO sindi_importar_nomina empresa {$empresa_id} empleador {$empleador_id} periodo {$periodo} formato {$formato} ===");

    // 1. Lectura del archivo
    $parse = $formato === 'F931_SICOSS'
        ? sindi_parsear_f931($contenido, $conexion, $empresa_id, $categoria_default_id)
        : sindi_parsear_csv($contenido, $conexion, $empresa_id, $categoria_default_id);
    $empleados = $parse['empleados'] ?? [];
    $errores = $parse['errores'] ?? [];
    $aceptadas = count($empleados);
    $rechazadas = count($errores);
    $total = $aceptadas + $rechazadas;

    // 2. Archivo guardado con nombre aleatorio
    $dir = sindi_carpeta_importaciones($empresa_id);
    $nombreGuardado = date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (file_put_contents($dir . '/' . $nombreGuardado, $contenido) === false) {
        return ['success' => false, 'message' => 'No se pudo guardar el archivo importado.'];
    }
    $ruta = 'uploads/sindicatos/' . $empresa_id . '/importaciones/' . $nombreGuardado;

    // 3. Borrador de la DDJJ: el que ya esté abierto para ese empleador y período, o uno nuevo
    $ddjj_id = null;
    $mensajeDdjj = '';
    if ($aceptadas > 0) {
        $stmt = mysqli_prepare($conexion, "SELECT ddjj_id FROM sindi__ddjj WHERE empresa_id = ? AND empleador_id = ? AND periodo = ?
                                           AND tabla_estado_registro_id = 3 ORDER BY ddjj_id DESC LIMIT 1");
        mysqli_stmt_bind_param($stmt, "iii", $empresa_id, $empleador_id, $periodo);
        mysqli_stmt_execute($stmt);
        $borrador = mysqli_fetch_row(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        $res = sindi_guardar_borrador_ddjj($conexion, $empresa_id, $usuario_id, [
            'empleador_id' => $empleador_id,
            'periodo' => $periodo,
            'ddjj_id' => $borrador ? (int)$borrador[0] : 0,
            'tipo' => 'EMPLEADOR',
            'origen_ingreso' => SINDI_IMPORT_FORMATOS[$formato],
        ], $empleados);
        if (empty($res['success'])) {
            $errores[] = 'No se pudo generar la DDJJ: ' . ($res['message'] ?? 'error desconocido');
        } else {
            $ddjj_id = (int)$res['ddjj_id'];
            $mensajeDdjj = $borrador ? " Se actualizó el borrador de DDJJ {$ddjj_id}." : " Se generó el borrador de DDJJ {$ddjj_id}.";
        }
    }
    $estado = $ddjj_id ? 5 : 27;

    // 4. Registro de la importación
    $erroresJson = json_encode(array_values($errores), JSON_UNESCAPED_UNICODE);
    $nombreReg = mb_substr($nombreOriginal, 0, 255);
    $stmt = mysqli_prepare($conexion, "INSERT INTO sindi__ddjj_importaciones
            (empresa_id, empleador_id, periodo, formato, archivo_nombre, archivo_ruta, filas_total, filas_aceptadas,
             filas_rechazadas, errores_detalle, ddjj_id, usuario_id, tabla_estado_registro_id, fecha_creacion)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    mysqli_stmt_bind_param($stmt, "iiisssiiisiii", $empresa_id, $empleador_id, $periodo, $formato, $nombreReg, $ruta,
        $total, $aceptadas, $rechazadas, $erroresJson, $ddjj_id, $usuario_id, $estado);
    mysqli_stmt_execute($stmt);
    $importacion_id = (int)mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);

    return [
        'success' => $estado === 5,
        'message' => $estado === 5
            ? "Importación {$importacion_id}: {$aceptadas} empleados aceptados, {$rechazadas} filas rechazadas.{$mensajeDdjj}"
            : "Importación {$importacion_id}: no se encontraron filas válidas; la DDJJ no se modificó.",
        'data' => [
            'importacion_id' => $importacion_id,
            'ddjj_id' => $ddjj_id,
            'aceptadas' => $aceptadas,
            'rechazadas' => $rechazadas,
            'errores' => $errores,
        ],
    ];
}
