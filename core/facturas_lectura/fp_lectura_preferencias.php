<?php
/**
 * Preferencias por proveedor de la carga de facturas desde archivo (sin dependencias: lo incluyen el motor de lectura
 * y Proveedores). gestion__entidades.compra_lectura_items (script 20261107_fp_lectura_items_proveedor_01.sql):
 * 1 = los ítems del comprobante se cargan producto por producto; 0 = una línea por alícuota (servicios: teléfono,
 * gas, luz), aunque la lectura traiga ítems.
 */

/** true si al proveedor se le cargan los ítems (también si la columna todavía no existe o la entidad no es de la empresa). */
function fplLecturaItemsProveedor($conexion, $empresa_id, $entidad_id)
{
    $e = intval($empresa_id);
    $id = intval($entidad_id);
    if ($id <= 0) return true;
    try {
        $stmt = mysqli_prepare($conexion, "SELECT compra_lectura_items FROM gestion__entidades WHERE entidad_id = ? AND empresa_id = ?");
        if (!$stmt) return true;
        mysqli_stmt_bind_param($stmt, "ii", $id, $e);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
    } catch (Throwable $ex) {   // columna sin crear (script pendiente)
        error_log('fplLecturaItemsProveedor: ' . $ex->getMessage());
        return true;
    }
    return !$row || intval($row['compra_lectura_items']) !== 0;
}

/** Guarda la preferencia. Solo entidades de la empresa; lanza RuntimeException si no la encuentra. */
function fplGuardarLecturaItemsProveedor($conexion, $empresa_id, $entidad_id, bool $leer_items)
{
    $e = intval($empresa_id);
    $id = intval($entidad_id);
    $v = $leer_items ? 1 : 0;
    $stmt = mysqli_prepare($conexion, "UPDATE gestion__entidades SET compra_lectura_items = ? WHERE entidad_id = ? AND empresa_id = ?");
    if (!$stmt) throw new RuntimeException('No se pudo guardar la preferencia del proveedor.');
    mysqli_stmt_bind_param($stmt, "iii", $v, $id, $e);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    $stmt = mysqli_prepare($conexion, "SELECT 1 FROM gestion__entidades WHERE entidad_id = ? AND empresa_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $id, $e);
    mysqli_stmt_execute($stmt);
    $existe = (bool)mysqli_fetch_row(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$existe) throw new RuntimeException('Proveedor no encontrado.');
    error_log("=== fplGuardarLecturaItemsProveedor: entidad $id empresa $e = $v ===");
}
