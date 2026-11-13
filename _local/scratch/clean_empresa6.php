<?php
require_once __DIR__ . '/../../db.php';

echo "=== INICIANDO LIMPIEZA DE GESTIÓN PARA EMPRESA_ID = 6 ===\n";

mysqli_begin_transaction($conn);

try {
    // 1. Detalles de facturas de venta
    $q1 = mysqli_query($conn, "DELETE FROM gestion__ventas_facturas_detalles WHERE venta_factura_id IN (SELECT venta_factura_id FROM gestion__ventas_facturas WHERE empresa_id = 6)");
    echo "1. ventas_facturas_detalles eliminados: " . mysqli_affected_rows($conn) . "\n";

    // 2. Facturas de venta
    $q2 = mysqli_query($conn, "DELETE FROM gestion__ventas_facturas WHERE empresa_id = 6");
    echo "2. ventas_facturas eliminadas: " . mysqli_affected_rows($conn) . "\n";

    // 3. Medios de cobro
    $q3 = mysqli_query($conn, "DELETE FROM gestion__cobranzas_medios WHERE cobranza_id IN (SELECT cobranza_id FROM gestion__cobranzas WHERE empresa_id = 6)");
    echo "3. cobranzas_medios eliminados: " . mysqli_affected_rows($conn) . "\n";

    // 4. Cobranzas
    $q4 = mysqli_query($conn, "DELETE FROM gestion__cobranzas WHERE empresa_id = 6");
    echo "4. cobranzas eliminadas: " . mysqli_affected_rows($conn) . "\n";

    // 5. Imputaciones
    $q5 = mysqli_query($conn, "DELETE FROM gestion__comprobantes_imputaciones WHERE empresa_id = 6");
    echo "5. comprobantes_imputaciones eliminadas: " . mysqli_affected_rows($conn) . "\n";

    // 6. Tesorería movimientos
    $q6 = mysqli_query($conn, "DELETE FROM gestion__tesoreria_movimientos WHERE empresa_id = 6");
    echo "6. tesoreria_movimientos eliminados: " . mysqli_affected_rows($conn) . "\n";

    // 7. Detalles de asientos contables
    $q7 = mysqli_query($conn, "DELETE FROM gestion__cont_asientos_detalles WHERE cont_asiento_id IN (SELECT cont_asiento_id FROM gestion__cont_asientos WHERE empresa_id = 6)");
    echo "7. cont_asientos_detalles eliminados: " . mysqli_affected_rows($conn) . "\n";

    // 8. Asientos contables
    $q8 = mysqli_query($conn, "DELETE FROM gestion__cont_asientos WHERE empresa_id = 6");
    echo "8. cont_asientos eliminados: " . mysqli_affected_rows($conn) . "\n";

    // 9. Comprobantes
    $q9 = mysqli_query($conn, "DELETE FROM gestion__comprobantes WHERE empresa_id = 6");
    echo "9. comprobantes eliminados: " . mysqli_affected_rows($conn) . "\n";

    // 10. Facturas proveedores y detalles
    $q10 = mysqli_query($conn, "DELETE FROM gestion__facturas_proveedores_detalle WHERE factura_proveedor_id IN (SELECT factura_proveedor_id FROM gestion__facturas_proveedores WHERE empresa_id = 6)");
    echo "10. facturas_proveedores_detalle eliminados: " . mysqli_affected_rows($conn) . "\n";
    $q11 = mysqli_query($conn, "DELETE FROM gestion__facturas_proveedores WHERE empresa_id = 6");
    echo "11. facturas_proveedores eliminadas: " . mysqli_affected_rows($conn) . "\n";

    // 12. Condiciones de clientes/proveedores de entidades empresa 6
    $q12 = mysqli_query($conn, "DELETE FROM gestion__entidades_condiciones_clientes WHERE entidad_id IN (SELECT entidad_id FROM gestion__entidades WHERE empresa_id = 6)");
    echo "12. entidades_condiciones_clientes eliminadas: " . mysqli_affected_rows($conn) . "\n";
    $q13 = mysqli_query($conn, "DELETE FROM gestion__entidades_condiciones_proveedores WHERE entidad_id IN (SELECT entidad_id FROM gestion__entidades WHERE empresa_id = 6)");
    echo "13. entidades_condiciones_proveedores eliminadas: " . mysqli_affected_rows($conn) . "\n";

    // 13. Sucursales de entidades
    $q14 = mysqli_query($conn, "DELETE FROM gestion__entidades_sucursales WHERE empresa_id = 6");
    echo "14. entidades_sucursales eliminadas: " . mysqli_affected_rows($conn) . "\n";

    // 14. Entidades empresa 6
    $q15 = mysqli_query($conn, "DELETE FROM gestion__entidades WHERE empresa_id = 6");
    echo "15. entidades eliminadas: " . mysqli_affected_rows($conn) . "\n";

    // 15. Listas de precios productos y productos empresa 6
    $q16 = mysqli_query($conn, "DELETE FROM gestion__listas_precios_productos WHERE empresa_id = 6");
    echo "16. listas_precios_productos eliminados: " . mysqli_affected_rows($conn) . "\n";
    $q17 = mysqli_query($conn, "DELETE FROM gestion__productos WHERE empresa_id = 6");
    echo "17. productos eliminados: " . mysqli_affected_rows($conn) . "\n";

    // 16. Numeradores reset
    $q18 = mysqli_query($conn, "DELETE FROM gestion__comprobantes_numeradores WHERE empresa_id = 6");
    echo "18. comprobantes_numeradores eliminados: " . mysqli_affected_rows($conn) . "\n";

    mysqli_commit($conn);
    echo "=== LIMPIEZA COMPLETADA CON ÉXITO ===\n";
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo "ERROR en la limpieza: " . $e->getMessage() . "\n";
}
