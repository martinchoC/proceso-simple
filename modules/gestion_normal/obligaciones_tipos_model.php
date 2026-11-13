<?php
/**
 * Tipos de Obligación (página 636, tabla 449 gestion__obligaciones_tipos, diccionario).
 * La lógica está en core/obligaciones/ (compartida entre gestion y gestion_normal); este archivo carga la tesorería
 * del módulo, que el servicio usa para asientos e imputaciones. Diseño: _local/docs/gestion/13_obligaciones_de_pago.md
 */
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/tesoreria_core_model.php';
require_once __DIR__ . '/../../core/obligaciones/obligaciones_service.php';
$conexion = $conn;
