<?php
/**
 * Cierre de Períodos Contables (gestion__cont_periodos_cierres, tabla 448: 5 Cerrado / 38 Reabierto).
 * La lógica y el bloqueo de los asientos están en core/cont_periodos.php (compartido con gestion_normal y
 * estudio_contable); acá solo los botones del motor de la página.
 * Script: _local/sql/mantenimiento/20261028_cont_periodos_cierres_01_estructura.sql
 */
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/cont_periodos.php';
$conexion = $conn;

/** Funciones de la página para un estado (0 = Cerrar período, 5 = Reabrir). */
function cpcBotones($conexion, $pagina_id, $estado_id)
{
    return cont_periodo_filas($conexion,
        "SELECT pf.nombre_funcion, pf.accion_js, pf.descripcion, i.icono_clase, c.color_clase, c.bg_clase, c.text_clase
         FROM conf__paginas_funciones pf
         LEFT JOIN conf__iconos i ON i.icono_id = pf.icono_id
         LEFT JOIN conf__colores c ON c.color_id = pf.color_id
         WHERE pf.pagina_id = ? AND pf.tabla_estado_registro_origen_id = ? AND pf.tabla_estado_registro_id = 1
         ORDER BY pf.orden", 'ii', intval($pagina_id), intval($estado_id));
}

/** Botones que el usuario puede usar, por estado de origen. */
function cpcBotonesUsuario($conexion, $usuario_id, $empresa_id, $pagina_id)
{
    $res = [];
    foreach ([0, CONT_PERIODO_CERRADO] as $estado) {
        $res[$estado] = array_values(array_filter(cpcBotones($conexion, $pagina_id, $estado), function ($b) use ($conexion, $usuario_id, $empresa_id, $pagina_id) {
            return usuario_puede_funcion($conexion, intval($usuario_id), intval($empresa_id), intval($pagina_id), $b['accion_js']);
        }));
    }
    return $res;
}
