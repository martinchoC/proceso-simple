<?php
declare(strict_types=1);

/**
 * modules/sindicatos/sindi_base_model.php
 * Funciones compartidas para el motor de botones, estados y transiciones del Módulo 6.
 */

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

function sindi_obtener_boton_agregar(mysqli $conexion, int $pagina_id): ?array
{
    $funciones = sindi_obtener_funciones_pagina($conexion, $pagina_id);
    foreach ($funciones as $f) {
        if ((int)$f['tabla_estado_registro_origen_id'] === 0 && $f['accion_js'] === 'agregar') {
            return $f;
        }
    }
    return null;
}

function sindi_obtener_botones_acciones(mysqli $conexion, int $pagina_id, int $estado_actual_id, int $registro_id): string
{
    $funciones = sindi_obtener_funciones_pagina($conexion, $pagina_id);
    $html = '<div class="btn-group btn-group-sm" role="group">';

    foreach ($funciones as $f) {
        if ((int)$f['tabla_estado_registro_origen_id'] === $estado_actual_id && $f['accion_js'] !== 'agregar') {
            $accion = htmlspecialchars($f['accion_js'], ENT_QUOTES, 'UTF-8');
            $nombre = htmlspecialchars($f['nombre_funcion'], ENT_QUOTES, 'UTF-8');
            $color = !empty($f['color_clase']) ? $f['color_clase'] : 'btn-outline-primary';
            $icono = !empty($f['icono_clase']) ? '<i class="' . htmlspecialchars($f['icono_clase'], ENT_QUOTES, 'UTF-8') . '"></i> ' : '';

            $html .= sprintf(
                '<button type="button" class="btn btn-sm %s btn-accion me-1" data-accion="%s" data-id="%d" title="%s">%s%s</button>',
                $color,
                $accion,
                $registro_id,
                $nombre,
                $icono,
                $nombre
            );
        }
    }

    $html .= '</div>';
    return $html;
}

function sindi_ejecutar_transicion(mysqli $conexion, string $tabla, string $id_columna, int $registro_id, string $accion_js, int $pagina_id, int $empresa_id): array
{
    // 1. Obtener función configurada
    $sqlFunc = "SELECT pf.* FROM conf__paginas_funciones pf
                WHERE pf.pagina_id = ? AND pf.accion_js = ? AND pf.tabla_estado_registro_id = 1
                LIMIT 1";
    $stmtF = mysqli_prepare($conexion, $sqlFunc);
    if (!$stmtF) {
        return ['success' => false, 'message' => 'Error al preparar verificación de función.'];
    }
    mysqli_stmt_bind_param($stmtF, "is", $pagina_id, $accion_js);
    mysqli_stmt_execute($stmtF);
    $resF = mysqli_stmt_get_result($stmtF);
    $funcion = mysqli_fetch_assoc($resF);
    mysqli_stmt_close($stmtF);

    if (!$funcion) {
        return ['success' => false, 'message' => 'Acción no permitida o no configurada para esta página.'];
    }

    $estadoOrigen = (int)$funcion['tabla_estado_registro_origen_id'];
    $estadoDestino = (int)$funcion['tabla_estado_registro_destino_id'];

    // 2. Verificar estado actual del registro
    // Asegurar que nombre de tabla y columna sean válidos (alfanuméricos con guión bajo)
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $tabla) || !preg_match('/^[a-zA-Z0-9_]+$/', $id_columna)) {
        return ['success' => false, 'message' => 'Parámetros de tabla o clave no válidos.'];
    }

    $sqlCheck = "SELECT tabla_estado_registro_id FROM {$tabla} WHERE {$id_columna} = ? AND empresa_id = ? LIMIT 1";
    $stmtC = mysqli_prepare($conexion, $sqlCheck);
    if (!$stmtC) {
        return ['success' => false, 'message' => 'Error al consultar estado actual.'];
    }
    mysqli_stmt_bind_param($stmtC, "ii", $registro_id, $empresa_id);
    mysqli_stmt_execute($stmtC);
    $resC = mysqli_stmt_get_result($stmtC);
    $fila = mysqli_fetch_assoc($resC);
    mysqli_stmt_close($stmtC);

    if (!$fila) {
        return ['success' => false, 'message' => 'Registro no encontrado en esta empresa.'];
    }

    if ((int)$fila['tabla_estado_registro_id'] !== $estadoOrigen) {
        return ['success' => false, 'message' => 'El estado actual del registro no coincide con el requerido por la acción.'];
    }

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
