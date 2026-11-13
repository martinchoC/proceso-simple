<?php
// Confirma una factura de venta en Borrador (3) con un CAE que ARCA ya otorgó fuera del sistema
// (comprobante emitido con otro facturador), sin volver a pedirlo. Hace lo mismo que Confirmar + Aprobada
// salvo las validaciones de ARCA para pedir un CAE nuevo (fecha ±5 días, correlatividad): período fiscal
// abierto, percepciones, y vfArcaGuardarAutorizacion (número, CAE, Confirmado, comprobante, stock y asiento)
// + vfPostConfirmacion.
// Uso: php 20261024_registrar_cae_existente.php <empresa_id> <usuario_id> <venta_factura_id> <nro> <cae> <vto_cae AAAAMMDD> <pto_vta_fiscal>
if (PHP_SAPI !== 'cli') exit(1);
[, $empresa_id, $usuario_id, $id, $nro, $cae, $vto, $pto_vta] = array_pad($argv, 8, null);
$empresa_id = intval($empresa_id);
$id = intval($id);

session_start();
$_SESSION['logueado'] = true;
$_SESSION['usuario_id'] = intval($usuario_id);
$_SESSION['empresa_id'] = $empresa_id;

chdir(__DIR__ . '/../../modules/gestion_normal');
require_once __DIR__ . '/../../modules/gestion_normal/ventas_facturas_model.php';
$pagina_id = intval(mysqli_fetch_row(mysqli_query($conexion,
    "SELECT pagina_id FROM conf__paginas WHERE modulo_id = 5 AND url = 'ventas_facturas.php' LIMIT 1"))[0] ?? 0);

function salir($r) { echo json_encode($r, JSON_UNESCAPED_UNICODE); exit(empty($r['success']) ? 1 : 0); }

$factura = vfObtenerVentaFacturaPorId($conexion, $id, $empresa_id, $pagina_id);
if (!$factura) salir(['success' => false, 'error' => 'Factura no encontrada']);
if (intval($factura['tabla_estado_registro_id']) !== 3 || trim((string)$factura['CAE']) !== '') {
    salir(['success' => false, 'error' => 'La factura no está en Borrador sin CAE']);
}
try {
    pfExigirDocumentoAbierto($conexion, $empresa_id, 'VENTA', $id, 'confirmar el comprobante');
    mysqli_begin_transaction($conexion);
    vfPercepcionesRecalcular($conexion, $empresa_id, $id, $pagina_id, true);
    mysqli_commit($conexion);
} catch (Exception $e) {
    mysqli_rollback($conexion);
    salir(['success' => false, 'error' => $e->getMessage()]);
}
$factura = vfObtenerVentaFacturaPorId($conexion, $id, $empresa_id, $pagina_id);
$r = vfArcaGuardarAutorizacion($conexion, $factura,
    ['CbteNro' => intval($nro), 'CAE' => $cae, 'CAEFchVto' => $vto, 'conciliado' => true],
    ['pto_vta' => intval($pto_vta)], $empresa_id, $pagina_id);
if (!empty($r['success'])) {
    mysqli_begin_transaction($conexion);
    try {
        vfPostConfirmacion($conexion, $id, $empresa_id, intval($usuario_id));
        mysqli_commit($conexion);
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        $r['message'] .= ' ATENCIÓN post-confirmación: ' . $e->getMessage();
    }
}
salir($r);
