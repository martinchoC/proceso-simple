<?php
/**
 * Gastos Bancarios (página 130 / 630): botón "Generar comprobante" (Resumen bancario C_RB + OP automática por débito
 * en cuenta) y su historial (tabla 447 gestion__bancos_conciliaciones, origen GASTOS, estados 5 / 38).
 * La lógica está en core/bancos/bancos_gastos_service.php; el comprobante se emite con el modelo de facturas de
 * proveedor de este mismo módulo (su página se resuelve por url con bco_pagina_hermana).
 */
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/facturas_proveedores_model.php';
require_once __DIR__ . '/pagos_model.php';   // puntos de venta de la OP automática (pagoPuntosVentaOp)
require_once __DIR__ . '/../../core/bancos/bancos_gastos_service.php';
$conexion = $conn;
