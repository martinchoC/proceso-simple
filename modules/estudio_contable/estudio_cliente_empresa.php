<?php
/**
 * Clientes del estudio con empresa en este ERP y acceso a esa empresa por área.
 * - El propio estudio puede ser su cliente (empresa_vinculada_id = empresa del estudio): se usa sin vinculación.
 * - Otra empresa: vinculación vigente que ella le otorgó al estudio (conf__empresas_vinculaciones, origen = cliente,
 *   destino = estudio) con permiso del área; con escritura para lo que modifica.
 * Lo usan Cierres Contables (413) y Contabilidad del Cliente (423).
 */

require_once __DIR__ . '/../../core/empresas_vinculaciones_model.php';

/** Clientes activos con empresa en este ERP (origen LOCAL), con propio / lectura / escritura del área. */
function estudio_clientes_con_empresa(mysqli $c, int $estudio_id, string $area): array
{
    $st = mysqli_prepare($c, "SELECT cl.cliente_id, cl.razon_social, cl.cuit, cl.nivel_servicio, cl.mes_cierre_ejercicio,
                                     cl.empresa_vinculada_id, e.empresa AS empresa_nombre
                              FROM estudio__clientes cl
                              JOIN conf__empresas e ON e.empresa_id = cl.empresa_vinculada_id
                              WHERE cl.empresa_id = ? AND cl.tabla_estado_registro_id = 1 AND cl.empresa_vinculada_id > 0
                                AND cl.origen_datos = 'LOCAL'
                              ORDER BY cl.razon_social");
    mysqli_stmt_bind_param($st, "i", $estudio_id);
    mysqli_stmt_execute($st);
    $rows = mysqli_fetch_all(mysqli_stmt_get_result($st), MYSQLI_ASSOC);
    mysqli_stmt_close($st);
    foreach ($rows as &$r) {
        $erp = (int) $r['empresa_vinculada_id'];
        $r['empresa_vinculada_id'] = $erp;
        $r['propio'] = $erp === $estudio_id;
        $r['lectura'] = $r['propio'] || vinculacionesVerificarPermiso($c, $erp, $estudio_id, $area, false);
        $r['escritura'] = $r['propio'] || vinculacionesVerificarPermiso($c, $erp, $estudio_id, $area, true);
    }
    unset($r);
    return $rows;
}

/** Empresa del cliente con acceso al área; RuntimeException con el motivo si no corresponde. */
function estudio_cliente_empresa(mysqli $c, int $estudio_id, int $cliente_id, string $area, bool $escritura): array
{
    $st = mysqli_prepare($c, "SELECT cliente_id, razon_social, mes_cierre_ejercicio, empresa_vinculada_id, origen_datos
                              FROM estudio__clientes WHERE empresa_id = ? AND cliente_id = ? AND tabla_estado_registro_id = 1");
    mysqli_stmt_bind_param($st, "ii", $estudio_id, $cliente_id);
    mysqli_stmt_execute($st);
    $cl = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
    mysqli_stmt_close($st);
    if (!$cl) {
        throw new RuntimeException('Elegí un cliente.');
    }
    $erp = (int) ($cl['empresa_vinculada_id'] ?? 0);
    if ($erp <= 0 || $cl['origen_datos'] !== 'LOCAL') {
        throw new RuntimeException('El cliente no tiene su empresa en este ERP.');
    }
    if ($erp !== $estudio_id && !vinculacionesVerificarPermiso($c, $erp, $estudio_id, $area, $escritura)) {
        throw new RuntimeException("La empresa del cliente no le otorgó al estudio permiso vigente de {$area}"
            . ($escritura ? ' con escritura' : '') . '. Se registra en Vinculaciones entre empresas.');
    }
    return ['cliente_id' => (int) $cl['cliente_id'], 'razon_social' => $cl['razon_social'],
            'mes_cierre_padron' => (int) $cl['mes_cierre_ejercicio'], 'empresa_id' => $erp];
}
