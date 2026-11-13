<?php
/**
 * Conciliación Bancaria (página 128 / 628): extracto contra los movimientos de tesorería
 * (tabla 447 gestion__bancos_conciliaciones, origen MOVIMIENTOS, estados 5 Conciliada / 38 Deshecha).
 * La lógica está en core/bancos/bancos_conciliacion_service.php (compartida con gestion_normal).
 * Los recibos que se generan desde el extracto (core/bancos/bancos_cobranzas_service.php) usan el modelo de
 * cobranzas de este mismo módulo (su página se resuelve por url con bco_pagina_hermana).
 */
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/cobranzas_model.php';
require_once __DIR__ . '/../../core/bancos/bancos_cobranzas_service.php';
$conexion = $conn;
