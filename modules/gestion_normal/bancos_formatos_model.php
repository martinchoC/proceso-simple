<?php
/**
 * Formatos Bancarios (página 131 / 631, tabla 442 gestion__bancos_formatos + _formatos_columnas, diccionario Activo / Inactivo).
 * La lógica está en core/bancos/bancos_config_service.php (compartida con gestion_normal).
 * Scripts: _local/sql/mantenimiento/20261028_conciliacion_bancaria_01/02/03_*.sql
 */
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/bancos/bancos_config_service.php';
$conexion = $conn;
