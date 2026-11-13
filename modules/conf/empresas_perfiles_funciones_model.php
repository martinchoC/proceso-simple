<?php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/perfiles_predefinidos.php';
$conexion = $conn;

function obtenerModulos($conexion)
{
    $sql = "SELECT modulo_id, modulo FROM conf__modulos WHERE tabla_estado_registro_id = 1 ORDER BY modulo";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerEmpresas($conexion)
{
    $sql = "SELECT empresa_id, empresa as empresa_nombre FROM conf__empresas WHERE tabla_estado_registro_id = 1 ORDER BY empresa";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerEmpresasPerfilesPorModulo($conexion, $modulo_id)
{
    $modulo_condition = "";
    if ($modulo_id) {
        $modulo_id = intval($modulo_id);
        $modulo_condition = "AND ep.modulo_id = $modulo_id";
    }

    $sql = "SELECT ep.empresa_perfil_id, ep.empresa_perfil_nombre, 
                   e.empresa_id, e.empresa as empresa_nombre, 
                   m.modulo_id, m.modulo, 
                   p.perfil_id as perfil_id_base, p.perfil_nombre as perfil_base
            FROM conf__empresas_perfiles ep
            INNER JOIN conf__empresas e ON ep.empresa_id = e.empresa_id
            INNER JOIN conf__modulos m ON ep.modulo_id = m.modulo_id
            LEFT JOIN conf__perfiles p ON ep.perfil_id_base = p.perfil_id
            WHERE ep.tabla_estado_registro_id = 1 $modulo_condition 
            ORDER BY e.empresa, ep.empresa_perfil_nombre";

    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerEmpresasPerfilesPorModuloYEmpresa($conexion, $modulo_id, $empresa_id)
{
    $modulo_condition = "";
    if ($modulo_id) {
        $modulo_id = intval($modulo_id);
        $modulo_condition = "AND ep.modulo_id = $modulo_id";
    }

    $empresa_condition = "";
    if ($empresa_id) {
        $empresa_id = intval($empresa_id);
        $empresa_condition = "AND ep.empresa_id = $empresa_id";
    }

    $sql = "SELECT ep.empresa_perfil_id, ep.empresa_perfil_nombre, 
                   e.empresa_id, e.empresa as empresa_nombre, 
                   m.modulo_id, m.modulo, 
                   p.perfil_id as perfil_id_base, p.perfil_nombre as perfil_base
            FROM conf__empresas_perfiles ep
            INNER JOIN conf__empresas e ON ep.empresa_id = e.empresa_id
            INNER JOIN conf__modulos m ON ep.modulo_id = m.modulo_id
            LEFT JOIN conf__perfiles p ON ep.perfil_id_base = p.perfil_id
            WHERE ep.tabla_estado_registro_id = 1 
            $modulo_condition $empresa_condition
            ORDER BY e.empresa, ep.empresa_perfil_nombre";

    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerPaginasFuncionesPorModulo($conexion, $modulo_id)
{
    $modulo_id = intval($modulo_id);

    // CONSULTA CORREGIDA: Usar los nombres correctos de campos
    $sql = "SELECT pf.pagina_funcion_id, pf.nombre_funcion, pf.descripcion, pf.accion_js,
                   p.pagina_id, p.pagina as pagina_nombre, p.url as ruta, p.padre_id, p.orden as pagina_orden,
                   m.modulo_id, m.modulo,
                   pf.orden as funcion_orden
            FROM conf__paginas_funciones pf
            INNER JOIN conf__paginas p ON pf.pagina_id = p.pagina_id
            INNER JOIN conf__modulos m ON p.modulo_id = m.modulo_id
            WHERE m.modulo_id = $modulo_id 
            AND pf.tabla_estado_registro_id = 1
            AND p.tabla_estado_registro_id = 1
            ORDER BY p.padre_id, p.orden, pf.orden";

    $res = mysqli_query($conexion, $sql);

    if (!$res) {
        error_log("Error SQL en obtenerPaginasFuncionesPorModulo: " . mysqli_error($conexion));
        error_log("Consulta: " . $sql);
        return [];
    }

    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }

    error_log("Paginas funciones obtenidas para modulo $modulo_id: " . count($data));

    return $data;
}

function obtenerPaginasFuncionesPorEmpresaPerfil($conexion, $empresa_perfil_id)
{
    $empresa_perfil_id = intval($empresa_perfil_id);

    // Primero obtener el módulo del perfil
    $sql_modulo = "SELECT modulo_id FROM conf__empresas_perfiles WHERE empresa_perfil_id = $empresa_perfil_id";
    $res_modulo = mysqli_query($conexion, $sql_modulo);
    $modulo = mysqli_fetch_assoc($res_modulo);
    $modulo_id = $modulo ? $modulo['modulo_id'] : 0;

    if (!$modulo_id) {
        error_log("No se encontró módulo para empresa_perfil_id: $empresa_perfil_id");
        return [];
    }

    // CONSULTA CORREGIDA: Usar nombres correctos de campos
    $sql = "SELECT pf.pagina_funcion_id, pf.nombre_funcion, pf.descripcion, pf.accion_js,
                   p.pagina_id, p.pagina as pagina_nombre, p.url as ruta, p.padre_id, p.orden as pagina_orden,
                   m.modulo_id, m.modulo,
                   epf.empresa_perfil_funcion_id, epf.asignado,
                   CASE WHEN epf.empresa_perfil_funcion_id IS NOT NULL THEN 1 ELSE 0 END as asignada
            FROM conf__paginas_funciones pf
            INNER JOIN conf__paginas p ON pf.pagina_id = p.pagina_id
            INNER JOIN conf__modulos m ON p.modulo_id = m.modulo_id
            LEFT JOIN conf__empresas_perfiles_funciones epf ON pf.pagina_funcion_id = epf.pagina_funcion_id 
                AND epf.empresa_perfil_id = $empresa_perfil_id
            WHERE m.modulo_id = $modulo_id 
            AND pf.tabla_estado_registro_id = 1
            AND p.tabla_estado_registro_id = 1
            ORDER BY p.padre_id, p.orden, pf.orden";

    $res = mysqli_query($conexion, $sql);

    if (!$res) {
        error_log("Error SQL en obtenerPaginasFuncionesPorEmpresaPerfil: " . mysqli_error($conexion));
        error_log("Consulta: " . $sql);
        return [];
    }

    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }

    error_log("Paginas funciones para empresa_perfil_id $empresa_perfil_id: " . count($data));

    return $data;
}

function obtenerPaginasFuncionesDisponiblesPorModulo($conexion, $modulo_id)
{
    $modulo_id = intval($modulo_id);

    // CONSULTA CORREGIDA: Usar nombres correctos de campos
    $sql = "SELECT pf.pagina_funcion_id, pf.nombre_funcion, pf.descripcion, pf.accion_js,
                   p.pagina_id, p.pagina as pagina_nombre, p.url as ruta, p.padre_id, p.orden as pagina_orden,
                   m.modulo_id, m.modulo,
                   pf.orden as funcion_orden
            FROM conf__paginas_funciones pf
            INNER JOIN conf__paginas p ON pf.pagina_id = p.pagina_id
            INNER JOIN conf__modulos m ON p.modulo_id = m.modulo_id
            WHERE m.modulo_id = $modulo_id 
            AND pf.tabla_estado_registro_id = 1
            AND p.tabla_estado_registro_id = 1
            ORDER BY p.padre_id, p.orden, pf.orden";

    $res = mysqli_query($conexion, $sql);

    if (!$res) {
        error_log("Error SQL en obtenerPaginasFuncionesDisponiblesPorModulo: " . mysqli_error($conexion));
        return [];
    }

    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }

    return $data;
}

// NUEVA FUNCIÓN: Obtener estructura jerárquica de páginas
function obtenerEstructuraPaginasPorModulo($conexion, $modulo_id)
{
    $modulo_id = intval($modulo_id);

    // Obtener todas las páginas del módulo
    $sql = "SELECT p.pagina_id, p.pagina as pagina_nombre, p.url as ruta, 
                   p.padre_id, p.orden, p.pagina_descripcion,
                   m.modulo
            FROM conf__paginas p
            INNER JOIN conf__modulos m ON p.modulo_id = m.modulo_id
            WHERE p.modulo_id = $modulo_id 
            AND p.tabla_estado_registro_id = 1
            ORDER BY p.padre_id, p.orden";

    $res = mysqli_query($conexion, $sql);

    if (!$res) {
        error_log("Error SQL en obtenerEstructuraPaginasPorModulo: " . mysqli_error($conexion));
        return [];
    }

    $paginas = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $paginas[] = $fila;
    }

    // Obtener funciones para cada página
    foreach ($paginas as &$pagina) {
        $pagina_id = $pagina['pagina_id'];
        $sql_funciones = "SELECT pf.pagina_funcion_id, pf.nombre_funcion, pf.descripcion, 
                                 pf.accion_js, pf.orden as funcion_orden
                          FROM conf__paginas_funciones pf
                          WHERE pf.pagina_id = $pagina_id 
                          AND pf.tabla_estado_registro_id = 1
                          ORDER BY pf.orden";

        $res_funciones = mysqli_query($conexion, $sql_funciones);
        $funciones = [];
        while ($funcion = mysqli_fetch_assoc($res_funciones)) {
            $funciones[] = $funcion;
        }
        $pagina['funciones'] = $funciones;
    }

    return $paginas;
}

// NUEVA FUNCIÓN: Obtener estructura completa con asignaciones
function obtenerEstructuraCompletaPorEmpresaPerfil($conexion, $empresa_perfil_id)
{
    $empresa_perfil_id = intval($empresa_perfil_id);

    // Obtener módulo del perfil
    $sql_modulo = "SELECT modulo_id FROM conf__empresas_perfiles WHERE empresa_perfil_id = $empresa_perfil_id";
    $res_modulo = mysqli_query($conexion, $sql_modulo);
    $modulo = mysqli_fetch_assoc($res_modulo);
    $modulo_id = $modulo ? $modulo['modulo_id'] : 0;

    if (!$modulo_id) {
        return [];
    }

    // Obtener páginas del módulo
    $sql_paginas = "SELECT p.pagina_id, p.pagina as pagina_nombre, p.url as ruta, 
                           p.padre_id, p.orden as pagina_orden, p.pagina_descripcion
                    FROM conf__paginas p
                    WHERE p.modulo_id = $modulo_id 
                    AND p.tabla_estado_registro_id = 1
                    ORDER BY p.padre_id, p.orden";

    $res_paginas = mysqli_query($conexion, $sql_paginas);
    $paginas = [];

    while ($pagina = mysqli_fetch_assoc($res_paginas)) {
        $pagina_id = $pagina['pagina_id'];

        // Obtener funciones de esta página
        $sql_funciones = "SELECT pf.pagina_funcion_id, pf.nombre_funcion, pf.descripcion, 
                                 pf.accion_js, pf.orden as funcion_orden
                          FROM conf__paginas_funciones pf
                          WHERE pf.pagina_id = $pagina_id 
                          AND pf.tabla_estado_registro_id = 1
                          ORDER BY pf.orden";

        $res_funciones = mysqli_query($conexion, $sql_funciones);
        $funciones = [];

        while ($funcion = mysqli_fetch_assoc($res_funciones)) {
            $pagina_funcion_id = $funcion['pagina_funcion_id'];

            // Verificar si está asignada
            $sql_asignada = "SELECT empresa_perfil_funcion_id, asignado 
                             FROM conf__empresas_perfiles_funciones 
                             WHERE empresa_perfil_id = $empresa_perfil_id 
                             AND pagina_funcion_id = $pagina_funcion_id";

            $res_asignada = mysqli_query($conexion, $sql_asignada);
            $asignada = mysqli_fetch_assoc($res_asignada);

            $funcion['asignada'] = $asignada ? 1 : 0;
            $funcion['empresa_perfil_funcion_id'] = $asignada ? $asignada['empresa_perfil_funcion_id'] : null;
            $funcion['asignado'] = $asignada ? $asignada['asignado'] : 0;

            $funciones[] = $funcion;
        }

        $pagina['funciones'] = $funciones;
        $paginas[] = $pagina;
    }

    return $paginas;
}

// FUNCIONES RESTANTES (sin cambios)
function asignarPaginaFuncionAEmpresaPerfil($conexion, $empresa_id, $empresa_perfil_id, $pagina_funcion_id, $asignado, $usuario_creacion)
{
    $empresa_id = intval($empresa_id);
    $empresa_perfil_id = intval($empresa_perfil_id);
    $pagina_funcion_id = intval($pagina_funcion_id);
    $usuario_creacion = intval($usuario_creacion);
    $asignado = intval($asignado);

    error_log("Asignando: empresa_id=$empresa_id, empresa_perfil_id=$empresa_perfil_id, pagina_funcion_id=$pagina_funcion_id, asignado=$asignado");

    // Primero verificar si ya existe la asignación
    $sql_check = "SELECT empresa_perfil_funcion_id FROM conf__empresas_perfiles_funciones 
                  WHERE empresa_perfil_id = $empresa_perfil_id 
                  AND pagina_funcion_id = $pagina_funcion_id";
    $res_check = mysqli_query($conexion, $sql_check);

    if (mysqli_num_rows($res_check) > 0) {
        // Actualizar si ya existe
        $sql = "UPDATE conf__empresas_perfiles_funciones 
                SET asignado = $asignado, 
                    fecha_asignacion = NOW()
                WHERE empresa_perfil_id = $empresa_perfil_id 
                AND pagina_funcion_id = $pagina_funcion_id";
    } else {
        // Insertar si no existe
        $sql = "INSERT INTO conf__empresas_perfiles_funciones 
                (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado) 
                VALUES ($empresa_id, $empresa_perfil_id, $pagina_funcion_id, $asignado)";
    }

    error_log("SQL: " . $sql);
    $resultado = mysqli_query($conexion, $sql);

    if (!$resultado) {
        error_log("Error en asignarPaginaFuncionAEmpresaPerfil: " . mysqli_error($conexion));
    }

    return $resultado;
}

function eliminarPaginaFuncionDeEmpresaPerfil($conexion, $empresa_perfil_funcion_id)
{
    $empresa_perfil_funcion_id = intval($empresa_perfil_funcion_id);

    error_log("Eliminando empresa_perfil_funcion_id: $empresa_perfil_funcion_id");

    $sql = "DELETE FROM conf__empresas_perfiles_funciones 
            WHERE empresa_perfil_funcion_id = $empresa_perfil_funcion_id";

    $resultado = mysqli_query($conexion, $sql);

    if (!$resultado) {
        error_log("Error en eliminarPaginaFuncionDeEmpresaPerfil: " . mysqli_error($conexion));
    }

    return $resultado;
}

function actualizarAsignacionPaginaFuncion($conexion, $empresa_perfil_funcion_id, $asignado)
{
    $empresa_perfil_funcion_id = intval($empresa_perfil_funcion_id);
    $asignado = intval($asignado);

    $sql = "UPDATE conf__empresas_perfiles_funciones 
            SET asignado = $asignado, 
                fecha_asignacion = NOW()
            WHERE empresa_perfil_funcion_id = $empresa_perfil_funcion_id";

    return mysqli_query($conexion, $sql);
}

function obtenerDetalleEmpresaPerfil($conexion, $empresa_perfil_id)
{
    $empresa_perfil_id = intval($empresa_perfil_id);

    $sql = "SELECT ep.*, e.empresa as empresa_nombre, m.modulo, p.perfil_nombre as perfil_base_nombre
            FROM conf__empresas_perfiles ep
            INNER JOIN conf__empresas e ON ep.empresa_id = e.empresa_id
            INNER JOIN conf__modulos m ON ep.modulo_id = m.modulo_id
            LEFT JOIN conf__perfiles p ON ep.perfil_id_base = p.perfil_id
            WHERE ep.empresa_perfil_id = $empresa_perfil_id";

    $res = mysqli_query($conexion, $sql);

    if ($res && mysqli_num_rows($res) > 0) {
        return mysqli_fetch_assoc($res);
    }

    return null;
}

function obtenerDetalleEmpresaPerfilCompleto($conexion, $empresa_perfil_id)
{
    $empresa_perfil_id = intval($empresa_perfil_id);

    // Obtener empresa_id del perfil para usarlo en las asignaciones
    $sql_perfil = "SELECT empresa_id, modulo_id FROM conf__empresas_perfiles WHERE empresa_perfil_id = $empresa_perfil_id";
    $res_perfil = mysqli_query($conexion, $sql_perfil);
    $perfil = mysqli_fetch_assoc($res_perfil);
    $empresa_id = $perfil ? $perfil['empresa_id'] : 0;
    $modulo_id = $perfil ? $perfil['modulo_id'] : 0;

    $sql = "SELECT ep.*, e.empresa as empresa_nombre, m.modulo, p.perfil_nombre as perfil_base_nombre
            FROM conf__empresas_perfiles ep
            INNER JOIN conf__empresas e ON ep.empresa_id = e.empresa_id
            INNER JOIN conf__modulos m ON ep.modulo_id = m.modulo_id
            LEFT JOIN conf__perfiles p ON ep.perfil_id_base = p.perfil_id
            WHERE ep.empresa_perfil_id = $empresa_perfil_id";

    $res = mysqli_query($conexion, $sql);

    if ($res && mysqli_num_rows($res) > 0) {
        $detalle = mysqli_fetch_assoc($res);
        $detalle['empresa_id_actual'] = $empresa_id;
        $detalle['modulo_id_actual'] = $modulo_id;
        return $detalle;
    }

    return null;
}

function heredarPaginasFuncionesDesdePerfilBase($conexion, $empresa_perfil_id, $perfil_id_base, $empresa_id)
{
    $empresa_perfil_id = intval($empresa_perfil_id);
    $perfil_id_base = intval($perfil_id_base);
    $empresa_id = intval($empresa_id);

    error_log("Heredando funciones: empresa_perfil_id=$empresa_perfil_id, empresa_id=$empresa_id");

    // Obtener módulo del perfil de empresa
    $sql_modulo = "SELECT modulo_id FROM conf__empresas_perfiles WHERE empresa_perfil_id = $empresa_perfil_id";
    $res_modulo = mysqli_query($conexion, $sql_modulo);
    $modulo = mysqli_fetch_assoc($res_modulo);
    $modulo_id = $modulo ? $modulo['modulo_id'] : 0;

    if (!$modulo_id) {
        error_log("No se encontró módulo para heredar funciones");
        return 0;
    }

    // Obtener todas las páginas y funciones del módulo (sin las de bienes si la empresa es de servicios
    // ni las de "solo gestión completa" si es simplificada)
    $sql_funciones = "SELECT pf.pagina_funcion_id
                     FROM conf__paginas_funciones pf
                     INNER JOIN conf__paginas p ON pf.pagina_id = p.pagina_id
                     WHERE p.modulo_id = $modulo_id
                     AND pf.tabla_estado_registro_id = 1
                     AND p.tabla_estado_registro_id = 1"
                     . perfiles_sql_excluir_paginas($conexion, $empresa_id, 'p');

    $res_funciones = mysqli_query($conexion, $sql_funciones);

    if (!$res_funciones) {
        error_log("Error al obtener funciones del módulo: " . mysqli_error($conexion));
        return 0;
    }

    $contador = 0;
    $total_funciones = mysqli_num_rows($res_funciones);
    error_log("Total funciones encontradas en módulo $modulo_id: $total_funciones");

    while ($funcion = mysqli_fetch_assoc($res_funciones)) {
        $pagina_funcion_id = $funcion['pagina_funcion_id'];

        // Verificar si ya existe la asignación
        $sql_check = "SELECT empresa_perfil_funcion_id FROM conf__empresas_perfiles_funciones 
                      WHERE empresa_perfil_id = $empresa_perfil_id 
                      AND pagina_funcion_id = $pagina_funcion_id";
        $res_check = mysqli_query($conexion, $sql_check);

        if (mysqli_num_rows($res_check) == 0) {
            // Insertar función heredada (asignado por defecto = 1 para permitir)
            $sql_insert = "INSERT INTO conf__empresas_perfiles_funciones 
                          (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado) 
                          VALUES ($empresa_id, $empresa_perfil_id, $pagina_funcion_id, 1)";

            if (mysqli_query($conexion, $sql_insert)) {
                $contador++;
            } else {
                error_log("Error al insertar función $pagina_funcion_id: " . mysqli_error($conexion));
            }
        }
    }

    error_log("Funciones heredadas: $contador de $total_funciones");

    return $contador;
}

/**
 * Todo lo que necesita la pantalla en una sola respuesta: páginas del módulo (árbol), funciones de cada página con el
 * nombre del estado en que aplican (origen) y al que llevan (destino), y las funciones asignadas a cada perfil.
 *
 * El nombre del estado sale del catálogo de la tabla de la página (conf__tablas_estados_registros por id genérico),
 * si no de la PK de ese catálogo en la misma tabla (Matriz Legal guarda la PK) y si no del estado genérico.
 * Origen 0 = no depende del estado de un registro (alta o función de la página).
 *
 * @param int[] $perfil_ids empresa_perfil_id de los perfiles a mostrar (solo se usan los del módulo)
 */
function epfMatriz(mysqli $conexion, int $modulo_id, array $perfil_ids): array
{
    $paginas = [];
    $stmt = mysqli_prepare($conexion,
        "SELECT p.pagina_id, p.pagina AS pagina_nombre, p.url AS ruta, p.padre_id, p.orden, p.pagina_descripcion,
                COALESCE(i.icono_clase, '') AS icono
           FROM conf__paginas p
           LEFT JOIN conf__iconos i ON i.icono_id = p.icono_id
          WHERE p.modulo_id = ? AND p.tabla_estado_registro_id = 1
          ORDER BY p.padre_id, p.orden, p.pagina_id");
    mysqli_stmt_bind_param($stmt, 'i', $modulo_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($fila = mysqli_fetch_assoc($res)) {
        $fila['pagina_id'] = (int)$fila['pagina_id'];
        $fila['padre_id'] = (int)$fila['padre_id'];
        $fila['orden'] = (int)$fila['orden'];
        $fila['funciones'] = [];
        $paginas[$fila['pagina_id']] = $fila;
    }
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($conexion,
        "SELECT pf.pagina_funcion_id, pf.pagina_id, pf.nombre_funcion, pf.accion_js, COALESCE(pf.descripcion, '') AS descripcion,
                pf.tabla_estado_registro_origen_id AS origen_id, pf.tabla_estado_registro_destino_id AS destino_id,
                COALESCE(pf.orden, 0) AS orden,
                COALESCE(i.icono_clase, '') AS icono, COALESCE(c.color_clase, '') AS color,
                COALESCE(to1.tabla_estado_registro, tp1.tabla_estado_registro, eo.estado_registro) AS origen_nombre,
                COALESCE(to1.orden, tp1.orden, eo.orden_estandar, pf.tabla_estado_registro_origen_id) AS origen_orden,
                COALESCE(td1.tabla_estado_registro, tq1.tabla_estado_registro, ed.estado_registro) AS destino_nombre
           FROM conf__paginas_funciones pf
           JOIN conf__paginas p ON p.pagina_id = pf.pagina_id
           LEFT JOIN conf__iconos i ON i.icono_id = pf.icono_id
           LEFT JOIN conf__colores c ON c.color_id = pf.color_id
           LEFT JOIN conf__tablas_estados_registros to1
                  ON to1.tabla_id = p.tabla_id AND to1.estado_registro_id = pf.tabla_estado_registro_origen_id
           LEFT JOIN conf__tablas_estados_registros tp1
                  ON tp1.tabla_id = p.tabla_id AND tp1.tabla_estado_registro_id = pf.tabla_estado_registro_origen_id
           LEFT JOIN conf__estados_registros eo ON eo.estado_registro_id = pf.tabla_estado_registro_origen_id
           LEFT JOIN conf__tablas_estados_registros td1
                  ON td1.tabla_id = p.tabla_id AND td1.estado_registro_id = pf.tabla_estado_registro_destino_id
           LEFT JOIN conf__tablas_estados_registros tq1
                  ON tq1.tabla_id = p.tabla_id AND tq1.tabla_estado_registro_id = pf.tabla_estado_registro_destino_id
           LEFT JOIN conf__estados_registros ed ON ed.estado_registro_id = pf.tabla_estado_registro_destino_id
          WHERE p.modulo_id = ? AND p.tabla_estado_registro_id = 1 AND pf.tabla_estado_registro_id = 1
          ORDER BY pf.pagina_id, pf.orden, pf.pagina_funcion_id");
    mysqli_stmt_bind_param($stmt, 'i', $modulo_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $vistos = [];
    while ($f = mysqli_fetch_assoc($res)) {
        $pid = (int)$f['pagina_id'];
        $id = (int)$f['pagina_funcion_id'];
        // Un JOIN de catálogo con filas repetidas no debe duplicar la función
        if (!isset($paginas[$pid]) || isset($vistos[$id])) {
            continue;
        }
        $vistos[$id] = true;
        foreach (['pagina_funcion_id', 'pagina_id', 'origen_id', 'destino_id', 'orden', 'origen_orden'] as $k) {
            $f[$k] = (int)$f[$k];
        }
        $paginas[$pid]['funciones'][] = $f;
    }
    mysqli_stmt_close($stmt);

    $asignaciones = [];
    $perfil_ids = array_values(array_unique(array_filter(array_map('intval', $perfil_ids))));
    if ($perfil_ids) {
        $marcas = implode(',', array_fill(0, count($perfil_ids), '?'));
        $stmt = mysqli_prepare($conexion,
            "SELECT epf.empresa_perfil_id, epf.pagina_funcion_id
               FROM conf__empresas_perfiles_funciones epf
               JOIN conf__empresas_perfiles ep ON ep.empresa_perfil_id = epf.empresa_perfil_id AND ep.modulo_id = ?
              WHERE epf.asignado = 1 AND epf.empresa_perfil_id IN ($marcas)");
        $tipos = 'i' . str_repeat('i', count($perfil_ids));
        $params = array_merge([$modulo_id], $perfil_ids);
        mysqli_stmt_bind_param($stmt, $tipos, ...$params);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        foreach ($perfil_ids as $p) {
            $asignaciones[$p] = [];
        }
        while ($a = mysqli_fetch_assoc($res)) {
            $asignaciones[(int)$a['empresa_perfil_id']][] = (int)$a['pagina_funcion_id'];
        }
        mysqli_stmt_close($stmt);
    }

    return ['paginas' => array_values($paginas), 'asignaciones' => $asignaciones];
}

/**
 * Guarda los cambios de la pantalla en una transacción: $cambios = [empresa_perfil_id => ['asignar' => [ids], 'quitar' => [ids]]].
 * Asignar = fila con asignado = 1 (la crea si falta); quitar = borra la fila (core/permisos.php solo lee asignado = 1).
 * Solo funciones de páginas del módulo del perfil; la empresa sale del perfil, no del request.
 */
function epfGuardarCambios(mysqli $conexion, array $cambios): array
{
    $asignadas = 0;
    $quitadas = 0;
    mysqli_begin_transaction($conexion);
    try {
        $stPerfil = mysqli_prepare($conexion, "SELECT empresa_id, modulo_id FROM conf__empresas_perfiles WHERE empresa_perfil_id = ?");
        $stValida = mysqli_prepare($conexion,
            "SELECT 1 FROM conf__paginas_funciones pf JOIN conf__paginas p ON p.pagina_id = pf.pagina_id
              WHERE pf.pagina_funcion_id = ? AND p.modulo_id = ?");
        $stUpd = mysqli_prepare($conexion,
            "UPDATE conf__empresas_perfiles_funciones SET asignado = 1, fecha_asignacion = NOW()
              WHERE empresa_perfil_id = ? AND pagina_funcion_id = ?");
        $stExiste = mysqli_prepare($conexion,
            "SELECT 1 FROM conf__empresas_perfiles_funciones WHERE empresa_perfil_id = ? AND pagina_funcion_id = ?");
        $stIns = mysqli_prepare($conexion,
            "INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado) VALUES (?, ?, ?, 1)");
        $stDel = mysqli_prepare($conexion,
            "DELETE FROM conf__empresas_perfiles_funciones WHERE empresa_perfil_id = ? AND pagina_funcion_id = ?");

        foreach ($cambios as $perfil_id => $c) {
            $perfil_id = (int)$perfil_id;
            mysqli_stmt_bind_param($stPerfil, 'i', $perfil_id);
            mysqli_stmt_execute($stPerfil);
            $perfil = mysqli_fetch_assoc(mysqli_stmt_get_result($stPerfil));
            if (!$perfil) {
                throw new RuntimeException("El perfil $perfil_id no existe.");
            }
            $empresa_id = (int)$perfil['empresa_id'];
            $modulo_id = (int)$perfil['modulo_id'];

            foreach (array_unique(array_map('intval', (array)($c['asignar'] ?? []))) as $fid) {
                mysqli_stmt_bind_param($stValida, 'ii', $fid, $modulo_id);
                mysqli_stmt_execute($stValida);
                if (!mysqli_fetch_row(mysqli_stmt_get_result($stValida))) {
                    throw new RuntimeException("La función $fid no es del módulo del perfil.");
                }
                mysqli_stmt_bind_param($stExiste, 'ii', $perfil_id, $fid);
                mysqli_stmt_execute($stExiste);
                if (mysqli_fetch_row(mysqli_stmt_get_result($stExiste))) {
                    mysqli_stmt_bind_param($stUpd, 'ii', $perfil_id, $fid);
                    mysqli_stmt_execute($stUpd);
                } else {
                    mysqli_stmt_bind_param($stIns, 'iii', $empresa_id, $perfil_id, $fid);
                    mysqli_stmt_execute($stIns);
                }
                $asignadas++;
            }
            foreach (array_unique(array_map('intval', (array)($c['quitar'] ?? []))) as $fid) {
                mysqli_stmt_bind_param($stDel, 'ii', $perfil_id, $fid);
                mysqli_stmt_execute($stDel);
                $quitadas += mysqli_stmt_affected_rows($stDel) > 0 ? 1 : 0;
            }
        }
        mysqli_commit($conexion);
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        error_log('=== epfGuardarCambios: ' . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
    return ['success' => true, 'data' => ['asignadas' => $asignadas, 'quitadas' => $quitadas],
            'message' => "Guardado: $asignadas asignadas, $quitadas quitadas."];
}

function obtenerEmpresaIdPorPerfil($conexion, $empresa_perfil_id)
{
    $empresa_perfil_id = intval($empresa_perfil_id);

    $sql = "SELECT empresa_id FROM conf__empresas_perfiles WHERE empresa_perfil_id = $empresa_perfil_id";
    $res = mysqli_query($conexion, $sql);

    if ($res && mysqli_num_rows($res) > 0) {
        $row = mysqli_fetch_assoc($res);
        return $row['empresa_id'];
    }

    return 0;
}
?>