<?php
/**
 * Reglas de Aprobación (página 137, tablas 451 / 452 gestion__aprobaciones_reglas y _topes).
 * La lógica está en core/obligaciones/ (compartida entre gestion y gestion_normal); este archivo carga la tesorería
 * del módulo, que el servicio usa para asientos e imputaciones. Diseño: _local/docs/gestion/13_obligaciones_de_pago.md
 */
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/tesoreria_core_model.php';
require_once __DIR__ . '/../../core/obligaciones/obligaciones_service.php';
$conexion = $conn;
