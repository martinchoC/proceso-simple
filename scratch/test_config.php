<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../core/empresa_config.php';

$conexion = $conn;
echo "Subgrupo DDJJ: " . emp_subgrupo_id($conexion, 3, 'DDJJ') . PHP_EOL;
echo "Tipo DFS: " . emp_comprobante_tipo_id($conexion, 3, 'DFS') . PHP_EOL;
echo "Tipo DCS: " . emp_comprobante_tipo_id($conexion, 3, 'DCS') . PHP_EOL;
echo "Cuenta SINDI_DEUDORES_APORTES: " . emp_cuenta_concepto_id($conexion, 3, 'SINDI_DEUDORES_APORTES') . PHP_EOL;
echo "Cuenta SINDI_APORTE_ART100: " . emp_cuenta_concepto_id($conexion, 3, 'SINDI_APORTE_ART100') . PHP_EOL;
echo "Cuenta SINDI_APORTE_ART101: " . emp_cuenta_concepto_id($conexion, 3, 'SINDI_APORTE_ART101') . PHP_EOL;
echo "Tipo Asiento AUT: " . emp_tipo_asiento_id($conexion, 3, 'AUT') . PHP_EOL;
echo "Moneda Base: " . emp_moneda_base_id($conexion, 3) . PHP_EOL;
