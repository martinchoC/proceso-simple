<?php
/**
 * Modelo de datos para Territorios y Husos Horarios (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/territorios_model.php
 * Conforme a CLAUDE.md y _local/docs/matriz-legal/09-diseno-m1-territorio-y-grupo.md
 */

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
$conexion = $conn;

/**
 * Inicializa los tipos de territorios estándar si la tabla está vacía
 */
function inicializarTiposTerritorioSiVacio($conexion) {
    $sql = "SELECT COUNT(*) as total FROM ml__territorios_tipos";
    $res = mysqli_query($conexion, $sql);
    if ($res) {
        $row = mysqli_fetch_assoc($res);
        if (intval($row['total']) === 0) {
            $tipos = [
                ['MUNDO', 'Mundo / Global'],
                ['REGION', 'Región / Bloque'],
                ['SUBREGION', 'Subregión'],
                ['PAIS', 'País'],
                ['PROVINCIA', 'Provincia / Estado / Depto'],
                ['LOCALIDAD', 'Localidad / Ciudad / Municipio']
            ];
            $stmt = mysqli_prepare($conexion, "INSERT INTO ml__territorios_tipos (codigo, nombre, tabla_estado_registro_id) VALUES (?, ?, 1)");
            if ($stmt) {
                foreach ($tipos as $t) {
                    mysqli_stmt_bind_param($stmt, "ss", $t[0], $t[1]);
                    mysqli_stmt_execute($stmt);
                }
                mysqli_stmt_close($stmt);
            }
        }
    }
}

/**
 * Obtiene las funciones asignadas a una página desde conf__paginas_funciones
 */
function obtenerFuncionesPagina($conexion, $pagina_id) {
    $pagina_id = intval($pagina_id);
    $sql = "SELECT pf.*, i.icono_clase, c.color_clase, c.bg_clase, c.text_clase
              FROM conf__paginas_funciones pf
              LEFT JOIN conf__iconos i ON pf.icono_id = i.icono_id
              LEFT JOIN conf__colores c ON pf.color_id = c.color_id
             WHERE pf.pagina_id = ?
               AND pf.tabla_estado_registro_id = 1
             ORDER BY pf.tabla_estado_registro_origen_id, pf.orden";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    mysqli_stmt_bind_param($stmt, "i", $pagina_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $funciones = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $funciones[] = $fila;
    }
    mysqli_stmt_close($stmt);
    return $funciones;
}

/**
 * Genera el HTML de los botones de acción para cada fila según su estado
 */
function obtenerBotonesPorEstado($conexion, $pagina_id, $estado_actual_id, $registro_id, $funciones_precargadas = null) {
    $funciones = is_array($funciones_precargadas) ? $funciones_precargadas : permisos_filtrar_funciones($conexion, obtenerFuncionesPagina($conexion, $pagina_id));
    $botones = [];
    $registro_id = intval($registro_id);

    foreach ($funciones as $f) {
        if (intval($f['tabla_estado_registro_origen_id']) === intval($estado_actual_id)) {
            $accion = htmlspecialchars($f['accion_js'] ?? '', ENT_QUOTES, 'UTF-8');
            $nombre = htmlspecialchars($f['nombre_funcion'] ?? '', ENT_QUOTES, 'UTF-8');
            $destino = intval($f['tabla_estado_registro_destino_id']);

            $clase_btn = 'btn-outline-primary';
            $icono = 'fas fa-cog';

            if ($accion === 'editar') {
                $clase_btn = 'btn-outline-primary';
                $icono = 'fas fa-edit';
            } elseif ($accion === 'cambiar_pertenencia') {
                $clase_btn = 'btn-outline-warning';
                $icono = 'fas fa-sitemap';
            } elseif ($accion === 'inactivar') {
                $clase_btn = 'btn-outline-danger';
                $icono = 'fas fa-ban';
            } elseif ($accion === 'activar') {
                $clase_btn = 'btn-outline-success';
                $icono = 'fas fa-check';
            }

            $botones[] = "<button type=\"button\" class=\"btn btn-sm {$clase_btn} me-1 btn-accion\" " .
                         "data-accion=\"{$accion}\" data-id=\"{$registro_id}\" data-destino=\"{$destino}\" " .
                         "title=\"{$nombre}\"><i class=\"{$icono}\"></i></button>";
        }
    }

    if (empty($botones)) {
        if (intval($estado_actual_id) === 1) {
            $botones[] = "<button type=\"button\" class=\"btn btn-sm btn-outline-primary me-1 btn-accion\" data-accion=\"editar\" data-id=\"{$registro_id}\" data-destino=\"1\" title=\"Editar Territorio\"><i class=\"fas fa-edit\"></i></button>";
            $botones[] = "<button type=\"button\" class=\"btn btn-sm btn-outline-warning me-1 btn-accion\" data-accion=\"cambiar_pertenencia\" data-id=\"{$registro_id}\" data-destino=\"1\" title=\"Cambiar Pertenencia\"><i class=\"fas fa-sitemap\"></i></button>";
            $botones[] = "<button type=\"button\" class=\"btn btn-sm btn-outline-danger me-1 btn-accion\" data-accion=\"inactivar\" data-id=\"{$registro_id}\" data-destino=\"2\" title=\"Inactivar\"><i class=\"fas fa-ban\"></i></button>";
        } else {
            $botones[] = "<button type=\"button\" class=\"btn btn-sm btn-outline-success me-1 btn-accion\" data-accion=\"activar\" data-id=\"{$registro_id}\" data-destino=\"1\" title=\"Activar\"><i class=\"fas fa-check\"></i></button>";
        }
    }

    return '<div class="btn-group btn-group-sm" role="group">' . implode('', $botones) . '</div>';
}

/**
 * Lista todos los tipos de territorios disponibles
 */
function obtenerTiposTerritorios($conexion) {
    inicializarTiposTerritorioSiVacio($conexion);
    $sql = "SELECT territorio_tipo_id, codigo, nombre 
              FROM ml__territorios_tipos 
             WHERE tabla_estado_registro_id = 1
             ORDER BY territorio_tipo_id ASC";
    $result = mysqli_query($conexion, $sql);
    $tipos = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $tipos[] = $row;
        }
    }
    return $tipos;
}

/**
 * Lista territorios para selectores de nodo superior (excluyendo el nodo dado y sus descendientes)
 */
function obtenerTerritoriosSuperiores($conexion, $excluir_id = 0) {
    $excluir_id = intval($excluir_id);
    $sql = "SELECT t.territorio_id, t.codigo, t.nombre, tt.nombre AS tipo_nombre
              FROM ml__territorios t
              JOIN ml__territorios_tipos tt ON tt.territorio_tipo_id = t.territorio_tipo_id
             WHERE t.tabla_estado_registro_id = 1";
    if ($excluir_id > 0) {
        $sql .= " AND t.territorio_id <> {$excluir_id}";
    }
    $sql .= " ORDER BY t.territorio_id ASC, t.nombre ASC";
    $result = mysqli_query($conexion, $sql);
    $lista = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $lista[] = $row;
        }
    }
    return $lista;
}

/**
 * Lista los territorios con su pertenencia principal abierta y botones de acción
 */
function listarTerritorios($conexion, $pagina_idx, $filtro_tipo = '', $filtro_estado = '') {
    $sql = "SELECT t.territorio_id, t.codigo, t.nombre, t.huso_horario, t.tabla_estado_registro_id,
                   tt.nombre AS tipo_nombre, tt.codigo AS tipo_codigo,
                   tp.territorio_superior_id, tsup.nombre AS superior_nombre, tp.es_principal,
                   tp.vigente_desde AS pertenencia_desde,
                   er.estado_registro, er.color_id
              FROM ml__territorios t
              LEFT JOIN ml__territorios_tipos tt ON tt.territorio_tipo_id = t.territorio_tipo_id
              LEFT JOIN ml__territorios_pertenencias tp 
                ON tp.territorio_id = t.territorio_id 
               AND tp.es_principal = 1 
               AND tp.vigente_hasta IS NULL
              LEFT JOIN ml__territorios tsup ON tsup.territorio_id = tp.territorio_superior_id
              LEFT JOIN conf__estados_registros er ON er.estado_registro_id = t.tabla_estado_registro_id
             WHERE 1=1";

    $params = [];
    $types = "";

    if (!empty($filtro_tipo)) {
        $sql .= " AND t.territorio_tipo_id = ?";
        $params[] = intval($filtro_tipo);
        $types .= "i";
    }

    if (!empty($filtro_estado)) {
        $sql .= " AND t.tabla_estado_registro_id = ?";
        $params[] = intval($filtro_estado);
        $types .= "i";
    }

    $sql .= " ORDER BY t.territorio_tipo_id ASC, t.nombre ASC";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];

    if (!empty($types)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $datos = [];

    $funciones = permisos_filtrar_funciones($conexion, obtenerFuncionesPagina($conexion, $pagina_idx));

    while ($row = mysqli_fetch_assoc($result)) {
        $row['acciones'] = obtenerBotonesPorEstado($conexion, $pagina_idx, $row['tabla_estado_registro_id'], $row['territorio_id'], $funciones);
        $datos[] = $row;
    }
    mysqli_stmt_close($stmt);

    return $datos;
}

/**
 * Obtiene un territorio específico junto con su historial de pertenencias
 */
function obtenerTerritorioPorId($conexion, $territorio_id) {
    $territorio_id = intval($territorio_id);
    $sql = "SELECT t.*, tt.nombre AS tipo_nombre, tt.codigo AS tipo_codigo,
                   tp.territorio_superior_id, tsup.nombre AS superior_nombre,
                   tp.vigente_desde AS pertenencia_desde
              FROM ml__territorios t
              JOIN ml__territorios_tipos tt ON tt.territorio_tipo_id = t.territorio_tipo_id
              LEFT JOIN ml__territorios_pertenencias tp 
                ON tp.territorio_id = t.territorio_id 
               AND tp.es_principal = 1 
               AND tp.vigente_hasta IS NULL
              LEFT JOIN ml__territorios tsup ON tsup.territorio_id = tp.territorio_superior_id
             WHERE t.territorio_id = ?";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return null;
    mysqli_stmt_bind_param($stmt, "i", $territorio_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $territorio = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if ($territorio) {
        // Obtener historial completo de pertenencias
        $sql_pert = "SELECT tp.territorio_pertenencia_id, tp.territorio_superior_id, tsup.nombre AS superior_nombre,
                            tp.es_principal, tp.vigente_desde, tp.vigente_hasta
                       FROM ml__territorios_pertenencias tp
                       JOIN ml__territorios tsup ON tsup.territorio_id = tp.territorio_superior_id
                      WHERE tp.territorio_id = ?
                      ORDER BY tp.vigente_desde DESC";
        $stmt_p = mysqli_prepare($conexion, $sql_pert);
        if ($stmt_p) {
            mysqli_stmt_bind_param($stmt_p, "i", $territorio_id);
            mysqli_stmt_execute($stmt_p);
            $res_p = mysqli_stmt_get_result($stmt_p);
            $pertenencias = [];
            while ($p = mysqli_fetch_assoc($res_p)) {
                $pertenencias[] = $p;
            }
            mysqli_stmt_close($stmt_p);
            $territorio['pertenencias'] = $pertenencias;
        } else {
            $territorio['pertenencias'] = [];
        }
    }

    return $territorio;
}

/**
 * Alta de un territorio nuevo con su pertenencia inicial en transacción
 */
function agregarTerritorio($conexion, $codigo, $nombre, $tipo_id, $huso_horario, $superior_id, $vigente_desde, $usuario_id) {
    $codigo = trim($codigo);
    $nombre = trim($nombre);
    $tipo_id = intval($tipo_id);
    $huso_horario = trim($huso_horario);
    $superior_id = intval($superior_id);
    $vigente_desde = empty($vigente_desde) ? date('Y-m-d') : $vigente_desde;
    $usuario_id = intval($usuario_id);

    if (empty($codigo) || empty($nombre) || $tipo_id <= 0 || empty($huso_horario)) {
        return ['success' => false, 'message' => 'Todos los campos obligatorios deben completarse.'];
    }

    mysqli_begin_transaction($conexion);

    try {
        // Verificar código único
        $stmt_check = mysqli_prepare($conexion, "SELECT territorio_id FROM ml__territorios WHERE codigo = ?");
        mysqli_stmt_bind_param($stmt_check, "s", $codigo);
        mysqli_stmt_execute($stmt_check);
        if (mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_check))) {
            mysqli_stmt_close($stmt_check);
            mysqli_rollback($conexion);
            return ['success' => false, 'message' => "El código de territorio '{$codigo}' ya existe."];
        }
        mysqli_stmt_close($stmt_check);

        // Insertar en ml__territorios (estado inicial 1: Activo)
        $sql_t = "INSERT INTO ml__territorios (territorio_tipo_id, codigo, nombre, huso_horario, tabla_estado_registro_id, creado_por) 
                  VALUES (?, ?, ?, ?, 1, ?)";
        $stmt_t = mysqli_prepare($conexion, $sql_t);
        mysqli_stmt_bind_param($stmt_t, "isssi", $tipo_id, $codigo, $nombre, $huso_horario, $usuario_id);
        if (!mysqli_stmt_execute($stmt_t)) {
            $err = mysqli_stmt_error($stmt_t);
            mysqli_stmt_close($stmt_t);
            mysqli_rollback($conexion);
            return ['success' => false, 'message' => "Error al crear territorio: {$err}"];
        }
        $territorio_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt_t);

        // Si se indicó territorio superior, crear la pertenencia principal inicial
        if ($superior_id > 0) {
            $sql_p = "INSERT INTO ml__territorios_pertenencias 
                      (territorio_id, territorio_superior_id, es_principal, vigente_desde, creado_por)
                      VALUES (?, ?, 1, ?, ?)";
            $stmt_p = mysqli_prepare($conexion, $sql_p);
            mysqli_stmt_bind_param($stmt_p, "iisi", $territorio_id, $superior_id, $vigente_desde, $usuario_id);
            if (!mysqli_stmt_execute($stmt_p)) {
                $err = mysqli_stmt_error($stmt_p);
                mysqli_stmt_close($stmt_p);
                mysqli_rollback($conexion);
                return ['success' => false, 'message' => "Error al registrar pertenencia: {$err}"];
            }
            mysqli_stmt_close($stmt_p);
        }

        mysqli_commit($conexion);
        return ['success' => true, 'message' => 'Territorio creado correctamente.', 'territorio_id' => $territorio_id];
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        return ['success' => false, 'message' => 'Excepción: ' . $e->getMessage()];
    }
}

/**
 * Modifica datos básicos de un territorio
 */
function editarTerritorio($conexion, $territorio_id, $codigo, $nombre, $tipo_id, $huso_horario, $usuario_id) {
    $territorio_id = intval($territorio_id);
    $codigo = trim($codigo);
    $nombre = trim($nombre);
    $tipo_id = intval($tipo_id);
    $huso_horario = trim($huso_horario);
    $usuario_id = intval($usuario_id);

    if ($territorio_id <= 0 || empty($codigo) || empty($nombre) || $tipo_id <= 0 || empty($huso_horario)) {
        return ['success' => false, 'message' => 'Datos incompletos para actualizar el territorio.'];
    }

    // Verificar unicidad de código
    $stmt_check = mysqli_prepare($conexion, "SELECT territorio_id FROM ml__territorios WHERE codigo = ? AND territorio_id <> ?");
    mysqli_stmt_bind_param($stmt_check, "si", $codigo, $territorio_id);
    mysqli_stmt_execute($stmt_check);
    if (mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_check))) {
        mysqli_stmt_close($stmt_check);
        return ['success' => false, 'message' => "El código de territorio '{$codigo}' ya pertenece a otro registro."];
    }
    mysqli_stmt_close($stmt_check);

    $sql = "UPDATE ml__territorios 
               SET codigo = ?, nombre = ?, territorio_tipo_id = ?, huso_horario = ?, actualizado_por = ? 
             WHERE territorio_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return ['success' => false, 'message' => 'Error al preparar actualización.'];
    }
    mysqli_stmt_bind_param($stmt, "ssisii", $codigo, $nombre, $tipo_id, $huso_horario, $usuario_id, $territorio_id);
    $ok = mysqli_stmt_execute($stmt);
    $err = mysqli_stmt_error($stmt);
    mysqli_stmt_close($stmt);

    if ($ok) {
        return ['success' => true, 'message' => 'Territorio actualizado correctamente.'];
    }
    return ['success' => false, 'message' => "Error al actualizar: {$err}"];
}

/**
 * Cambio de pertenencia histórica (Caso C13): cierra la actual y abre la nueva sin solapamiento
 */
function cambiarPertenenciaTerritorio($conexion, $territorio_id, $nuevo_superior_id, $fecha_cambio, $es_principal, $usuario_id) {
    $territorio_id = intval($territorio_id);
    $nuevo_superior_id = intval($nuevo_superior_id);
    $fecha_cambio = trim($fecha_cambio);
    $es_principal = $es_principal ? 1 : 0;
    $usuario_id = intval($usuario_id);

    if ($territorio_id <= 0 || empty($fecha_cambio)) {
        return ['success' => false, 'message' => 'Parámetros inválidos para cambiar la pertenencia.'];
    }

    if ($nuevo_superior_id > 0 && $territorio_id === $nuevo_superior_id) {
        return ['success' => false, 'message' => 'Un territorio no puede pertenecer a sí mismo.'];
    }

    mysqli_begin_transaction($conexion);

    try {
        // Cerrar la pertenencia principal abierta actual si existe
        if ($es_principal === 1 || $nuevo_superior_id === 0) {
            $sql_close = "UPDATE ml__territorios_pertenencias 
                             SET vigente_hasta = ?, actualizado_por = ?
                           WHERE territorio_id = ? 
                             AND es_principal = 1 
                             AND vigente_hasta IS NULL";
            $stmt_close = mysqli_prepare($conexion, $sql_close);
            mysqli_stmt_bind_param($stmt_close, "sii", $fecha_cambio, $usuario_id, $territorio_id);
            mysqli_stmt_execute($stmt_close);
            mysqli_stmt_close($stmt_close);
        }

        // Si se indicó un nuevo superior (> 0), insertar la nueva pertenencia
        if ($nuevo_superior_id > 0) {
            $sql_new = "INSERT INTO ml__territorios_pertenencias 
                        (territorio_id, territorio_superior_id, es_principal, vigente_desde, creado_por)
                        VALUES (?, ?, ?, ?, ?)";
            $stmt_new = mysqli_prepare($conexion, $sql_new);
            mysqli_stmt_bind_param($stmt_new, "iiisi", $territorio_id, $nuevo_superior_id, $es_principal, $fecha_cambio, $usuario_id);
            if (!mysqli_stmt_execute($stmt_new)) {
                $err = mysqli_stmt_error($stmt_new);
                mysqli_stmt_close($stmt_new);
                mysqli_rollback($conexion);
                return ['success' => false, 'message' => "Error al registrar la nueva pertenencia: {$err}"];
            }
            mysqli_stmt_close($stmt_new);
        }

        mysqli_commit($conexion);
        return ['success' => true, 'message' => 'Pertenencia actualizada con vigencia histórica.'];
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        return ['success' => false, 'message' => 'Excepción: ' . $e->getMessage()];
    }
}

/**
 * Transición genérica de estado (Activar / Inactivar)
 */
function cambiarEstadoTerritorio($conexion, $territorio_id, $nuevo_estado_id, $usuario_id) {
    $territorio_id = intval($territorio_id);
    $nuevo_estado_id = intval($nuevo_estado_id);
    $usuario_id = intval($usuario_id);

    $sql = "UPDATE ml__territorios 
               SET tabla_estado_registro_id = ?, actualizado_por = ? 
             WHERE territorio_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return ['success' => false, 'message' => 'Error al preparar cambio de estado.'];
    }
    mysqli_stmt_bind_param($stmt, "iii", $nuevo_estado_id, $usuario_id, $territorio_id);
    $ok = mysqli_stmt_execute($stmt);
    $err = mysqli_stmt_error($stmt);
    mysqli_stmt_close($stmt);

    if ($ok) {
        return ['success' => true, 'message' => 'Estado actualizado exitosamente.'];
    }
    return ['success' => false, 'message' => "Error al actualizar estado: {$err}"];
}
