<?php
declare(strict_types=1);

/**
 * modules/sindicatos/afiliaciones_solicitudes_model.php
 * Solicitudes de afiliación (Página 1051): 3 Borrador -> 4 Pendiente de aprobación -> 30 Aprobada / 25 Rechazada;
 * 3 -> 38 Anulada. Al aprobar se crea (o reutiliza) la persona y la afiliación titular o familiar.
 */

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/sindi_base_model.php';
require_once __DIR__ . '/sindi_afiliaciones_core_model.php';

function sindi_listar_solicitudes_afiliacion(mysqli $conexion, int $empresa_id, int $pagina_id, ?int $estado = null): array
{
    $sql = "SELECT s.*, t.nombre AS tipo_nombre, t.grupo, par.nombre AS parentesco,
                   tp.apellido AS titular_apellido, tp.nombre AS titular_nombre, ta.numero_afiliado AS titular_numero,
                   ent.entidad_nombre AS empleador_nombre, a.numero_afiliado,
                   COALESCE(ter.tabla_estado_registro, er.estado_registro) AS estado_nombre
            FROM sindi__afiliaciones_solicitudes s
            INNER JOIN sindi__afiliaciones_tipos t ON t.afiliacion_tipo_id = s.afiliacion_tipo_id
            LEFT JOIN sindi__parentescos par ON par.parentesco_id = s.parentesco_id
            LEFT JOIN sindi__afiliaciones ta ON ta.afiliacion_id = s.titular_afiliacion_id
            LEFT JOIN sindi__personas tp ON tp.persona_id = ta.persona_id
            LEFT JOIN sindi__empleadores emp ON emp.empleador_id = s.empleador_id
            LEFT JOIN gestion__entidades ent ON ent.entidad_id = emp.entidad_id
            LEFT JOIN sindi__afiliaciones a ON a.afiliacion_id = s.afiliacion_id
            LEFT JOIN conf__tablas_estados_registros ter ON ter.tabla_id = 543 AND ter.estado_registro_id = s.tabla_estado_registro_id
            LEFT JOIN conf__estados_registros er ON er.estado_registro_id = s.tabla_estado_registro_id
            WHERE s.empresa_id = ?" . ($estado ? " AND s.tabla_estado_registro_id = ?" : "") . "
            ORDER BY s.solicitud_id DESC LIMIT 2000";
    $stmt = mysqli_prepare($conexion, $sql);
    if ($estado) {
        mysqli_stmt_bind_param($stmt, "ii", $empresa_id, $estado);
    } else {
        mysqli_stmt_bind_param($stmt, "i", $empresa_id);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $lista = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $row['acciones'] = sindi_obtener_botones_acciones($conexion, $pagina_id, (int)$row['tabla_estado_registro_id'], (int)$row['solicitud_id']);
        $lista[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $lista;
}

function sindi_obtener_solicitud_afiliacion(mysqli $conexion, int $empresa_id, int $solicitud_id, bool $bloquear = false): array
{
    $stmt = mysqli_prepare($conexion, "SELECT s.*, t.codigo AS tipo_codigo, t.grupo, t.requiere_empleador
                                       FROM sindi__afiliaciones_solicitudes s
                                       INNER JOIN sindi__afiliaciones_tipos t ON t.afiliacion_tipo_id = s.afiliacion_tipo_id
                                       WHERE s.solicitud_id = ? AND s.empresa_id = ? LIMIT 1" . ($bloquear ? " FOR UPDATE" : ""));
    mysqli_stmt_bind_param($stmt, "ii", $solicitud_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$fila) {
        throw new RuntimeException('Solicitud no encontrada en esta empresa.');
    }
    return $fila;
}

/** Alta o edición de una solicitud en Borrador, con validación de datos según el tipo. */
function sindi_guardar_solicitud_afiliacion(mysqli $conexion, int $empresa_id, int $usuario_id, array $d): array
{
    try {
        $solicitud_id = (int)($d['solicitud_id'] ?? 0);
        $tipo = sindi_afil_tipo($conexion, $empresa_id, (string)($d['tipo_codigo'] ?? ''));
        if ($tipo['alta'] !== 'SOLICITUD') {
            throw new RuntimeException("La afiliación {$tipo['nombre']} no se pide por solicitud (la da la DDJJ del empleador).");
        }
        $cuil = preg_replace('/\D/', '', (string)($d['cuil'] ?? ''));
        if (!sindi_validar_cuil_arg($cuil)) {
            throw new RuntimeException("CUIL inválido '{$cuil}'.");
        }
        $apellido = mb_strtoupper(trim((string)($d['apellido'] ?? '')));
        if ($apellido === '') {
            throw new RuntimeException('El apellido es obligatorio.');
        }
        $nombre = mb_strtoupper(trim((string)($d['nombre'] ?? '')));
        $f_nac = !empty($d['f_nacimiento']) ? (string)$d['f_nacimiento'] : null;
        $sexo = in_array($d['sexo'] ?? '', ['M', 'F', 'X'], true) ? $d['sexo'] : null;
        $email = trim((string)($d['email'] ?? '')) ?: null;
        $telefono = trim((string)($d['telefono'] ?? '')) ?: null;
        $empleador_id = !empty($d['empleador_id']) ? (int)$d['empleador_id'] : null;
        $titular_id = !empty($d['titular_afiliacion_id']) ? (int)$d['titular_afiliacion_id'] : null;
        $parentesco_id = !empty($d['parentesco_id']) ? (int)$d['parentesco_id'] : null;
        $obs = mb_substr(trim((string)($d['observaciones'] ?? '')), 0, 255) ?: null;

        if ($tipo['grupo'] === 'FAMILIAR') {
            if (!$titular_id || !$parentesco_id) {
                throw new RuntimeException('Para un familiar indique el titular y el parentesco.');
            }
            $tit = sindi_afil_obtener($conexion, $empresa_id, $titular_id);
            if ($tit['grupo'] !== 'TITULAR' || (int)$tit['tabla_estado_registro_id'] !== 1) {
                throw new RuntimeException('El titular indicado no tiene una afiliación vigente.');
            }
            $empleador_id = null;
        } else {
            $titular_id = null;
            $parentesco_id = null;
            if ((int)$tipo['requiere_empleador'] === 1 && !$empleador_id) {
                throw new RuntimeException('Este tipo de afiliación requiere empleador.');
            }
        }
        if ($empleador_id) {
            AccesosEmpleadoresHelper::exigirAcceso($conexion, $usuario_id, $empresa_id, $empleador_id);
        }
        $tipo_id = (int)$tipo['afiliacion_tipo_id'];

        if ($solicitud_id > 0) {
            $actual = sindi_obtener_solicitud_afiliacion($conexion, $empresa_id, $solicitud_id);
            if ((int)$actual['tabla_estado_registro_id'] !== 3) {
                throw new RuntimeException('Solo se editan solicitudes en Borrador.');
            }
            $stmt = mysqli_prepare($conexion, "UPDATE sindi__afiliaciones_solicitudes SET afiliacion_tipo_id = ?, cuil = ?, apellido = ?, nombre = ?,
                    f_nacimiento = ?, sexo = ?, email = ?, telefono = ?, empleador_id = ?, titular_afiliacion_id = ?, parentesco_id = ?,
                    observaciones = ?, usuario_actualizacion_id = ?
                WHERE solicitud_id = ? AND empresa_id = ?");
            mysqli_stmt_bind_param($stmt, "isssssssiiisiii", $tipo_id, $cuil, $apellido, $nombre, $f_nac, $sexo, $email, $telefono,
                $empleador_id, $titular_id, $parentesco_id, $obs, $usuario_id, $solicitud_id, $empresa_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        } else {
            $hoy = date('Y-m-d');
            $stmt = mysqli_prepare($conexion, "INSERT INTO sindi__afiliaciones_solicitudes
                    (empresa_id, afiliacion_tipo_id, cuil, apellido, nombre, f_nacimiento, sexo, email, telefono, empleador_id,
                     titular_afiliacion_id, parentesco_id, canal, f_solicitud, observaciones, tabla_estado_registro_id, usuario_creacion_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'INTERNO', ?, ?, 3, ?)");
            mysqli_stmt_bind_param($stmt, "iisssssssiiissi", $empresa_id, $tipo_id, $cuil, $apellido, $nombre, $f_nac, $sexo, $email, $telefono,
                $empleador_id, $titular_id, $parentesco_id, $hoy, $obs, $usuario_id);
            mysqli_stmt_execute($stmt);
            $solicitud_id = (int)mysqli_insert_id($conexion);
            mysqli_stmt_close($stmt);
        }
        return ['success' => true, 'message' => 'Solicitud guardada.', 'data' => ['solicitud_id' => $solicitud_id]];
    } catch (Throwable $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Transición de una solicitud. 'aprobar' crea la persona y la afiliación (titular o familiar) en la misma transacción.
 */
function sindi_transicion_solicitud_afiliacion(mysqli $conexion, int $empresa_id, int $usuario_id, int $solicitud_id, string $accion, string $motivo = ''): array
{
    $reglas = ['enviar' => [3, 4], 'anular' => [3, 38], 'aprobar' => [4, 30], 'rechazar' => [4, 25]];
    if (!isset($reglas[$accion])) {
        return ['success' => false, 'message' => 'Acción no válida.'];
    }
    [$origen, $destino] = $reglas[$accion];

    mysqli_begin_transaction($conexion);
    try {
        sindi_exigir_funcion($conexion, 1051, $accion);
        $s = sindi_obtener_solicitud_afiliacion($conexion, $empresa_id, $solicitud_id, true);
        if ((int)$s['tabla_estado_registro_id'] !== $origen) {
            throw new RuntimeException('La solicitud no está en el estado que requiere la acción.');
        }
        $afiliacion_id = null;
        if ($accion === 'aprobar') {
            $persona_id = sindi_afil_persona($conexion, $empresa_id, $usuario_id, $s);
            if ($s['grupo'] === 'FAMILIAR') {
                $afiliacion_id = sindi_afil_alta_familiar($conexion, $empresa_id, $usuario_id, (int)$s['titular_afiliacion_id'], $persona_id,
                    (int)$s['parentesco_id'], date('Y-m-d'), 'SOLICITUD', $solicitud_id);
            } else {
                $afiliacion_id = sindi_afil_alta_titular($conexion, $empresa_id, $usuario_id, $persona_id, (string)$s['tipo_codigo'],
                    $s['empleador_id'] ? (int)$s['empleador_id'] : null, date('Y-m-d'), 'SOLICITUD', $solicitud_id);
            }
        }
        if ($accion === 'rechazar' && trim($motivo) === '') {
            throw new RuntimeException('Indique el motivo del rechazo.');
        }
        $resuelve = in_array($accion, ['aprobar', 'rechazar'], true);
        $fRes = $resuelve ? date('Y-m-d') : null;
        $motivoReg = $accion === 'rechazar' ? mb_substr(trim($motivo), 0, 255) : null;
        $stmt = mysqli_prepare($conexion, "UPDATE sindi__afiliaciones_solicitudes
            SET tabla_estado_registro_id = ?, f_resolucion = COALESCE(?, f_resolucion), motivo_rechazo = COALESCE(?, motivo_rechazo),
                afiliacion_id = COALESCE(?, afiliacion_id), persona_id = COALESCE(persona_id, (SELECT persona_id FROM sindi__afiliaciones WHERE afiliacion_id = ?)),
                usuario_actualizacion_id = ?
            WHERE solicitud_id = ? AND empresa_id = ?");
        mysqli_stmt_bind_param($stmt, "issiiiii", $destino, $fRes, $motivoReg, $afiliacion_id, $afiliacion_id, $usuario_id, $solicitud_id, $empresa_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        mysqli_commit($conexion);
        $mensajes = ['enviar' => 'Solicitud enviada para aprobación.', 'anular' => 'Solicitud anulada.',
                     'aprobar' => 'Solicitud aprobada: afiliación registrada.', 'rechazar' => 'Solicitud rechazada.'];
        return ['success' => true, 'message' => $mensajes[$accion], 'data' => ['afiliacion_id' => $afiliacion_id]];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/** Titulares vigentes para el combo de familiares (búsqueda por CUIL, apellido o número). */
function sindi_buscar_titulares_vigentes(mysqli $conexion, int $empresa_id, string $texto): array
{
    $texto = trim($texto);
    if (mb_strlen($texto) < 3) {
        return [];
    }
    $like = '%' . $texto . '%';
    $stmt = mysqli_prepare($conexion, "SELECT a.afiliacion_id, a.numero_afiliado, p.cuil, p.apellido, p.nombre, t.nombre AS tipo_nombre
                                       FROM sindi__afiliaciones a
                                       INNER JOIN sindi__personas p ON p.persona_id = a.persona_id
                                       INNER JOIN sindi__afiliaciones_tipos t ON t.afiliacion_tipo_id = a.afiliacion_tipo_id
                                       WHERE a.empresa_id = ? AND a.titular_afiliacion_id IS NULL AND a.tabla_estado_registro_id = 1
                                         AND (p.cuil LIKE ? OR p.apellido LIKE ? OR a.numero_afiliado LIKE ?)
                                       ORDER BY p.apellido LIMIT 30");
    mysqli_stmt_bind_param($stmt, "isss", $empresa_id, $like, $like, $like);
    mysqli_stmt_execute($stmt);
    $lista = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $lista;
}
