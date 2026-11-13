<?php
/**
 * Reglas Bancarias (página 133 / 633, tabla 444 gestion__bancos_reglas, diccionario Activa / Inactiva).
 * La lógica está en core/bancos/bancos_config_service.php (compartida con gestion_normal).
 */
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/bancos/bancos_config_service.php';
$conexion = $conn;
