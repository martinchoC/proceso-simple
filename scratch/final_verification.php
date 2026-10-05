<?php
require_once __DIR__ . '/../db.php';

function q($sql) {
    global $conn;
    $res = mysqli_query($conn, $sql);
    if (!$res) return ["error" => mysqli_error($conn)];
    $rows = [];
    while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
    return $rows;
}

echo "=== 1. MODULO 4 REGISTRADO ===\n";
print_r(q("SELECT * FROM conf__modulos WHERE modulo_id = 4"));

echo "=== 2. TABLAS REGISTRADAS EN conf__tablas ===\n";
$tablas = q("SELECT tabla_id, tabla_nombre, modulo_id, tabla_tipo_id FROM conf__tablas WHERE tabla_id BETWEEN 401 AND 425");
echo "Total registradas: " . count($tablas) . "\n";
foreach ($tablas as $t) {
    echo " - [{$t['tabla_id']}] {$t['tabla_nombre']} (modulo: {$t['modulo_id']}, tipo: {$t['tabla_tipo_id']})\n";
}

echo "=== 3. ESTADOS DE TABLAS REGISTRADOS ===\n";
$estados = q("SELECT COUNT(*) as c FROM conf__tablas_estados_registros WHERE tabla_id BETWEEN 401 AND 425");
echo "Total estados para tablas 401..425: " . $estados[0]['c'] . "\n";

echo "=== 4. ARBOL DE PAGINAS conf__paginas ===\n";
$pags = q("SELECT pagina_id, pagina, url, icono_id, padre_id, orden, tabla_id FROM conf__paginas WHERE modulo_id = 4 ORDER BY orden");
foreach ($pags as $p) {
    echo " - ID: {$p['pagina_id']} | Padre: {$p['padre_id']} | {$p['pagina']} | URL: {$p['url']} | Icono: {$p['icono_id']} | Tabla: {$p['tabla_id']}\n";
}

echo "=== 5. PERMISOS Y EMPRESA ===\n";
$em = q("SELECT * FROM conf__empresas_modulos WHERE modulo_id = 4");
print_r($em);

$perf = q("SELECT * FROM conf__perfiles WHERE modulo_id = 4");
print_r($perf);

$perf_pag = q("SELECT perfil_id, COUNT(*) as paginas_asignadas FROM conf___perfiles_paginas WHERE pagina_id >= 400 GROUP BY perfil_id");
print_r($perf_pag);

echo "=== 6. VERIFICACION DE TABLAS FISICAS EN BD ===\n";
$tables = [
    'estudio__clientes_tipos', 'estudio__clientes', 'estudio__vinculaciones',
    'estudio__honorarios_conceptos', 'estudio__honorarios_tarifarios', 'estudio__honorarios_tarifarios_detalles',
    'estudio__clientes_abonos', 'estudio__honorarios_novedades', 'estudio__impuestos_obligaciones',
    'estudio__clientes_jurisdicciones', 'estudio__tasas_municipales', 'estudio__clientes_obligaciones',
    'estudio__calendario_vencimientos', 'estudio__vencimientos_generados', 'estudio__normas',
    'estudio__normas_condiciones', 'estudio__normas_clientes_impacto', 'estudio__tareas_plantillas',
    'estudio__tareas', 'estudio__tareas_tiempos', 'estudio__licencias',
    'estudio__licencias_delegaciones', 'gestion__activos_fijos', 'gestion__activos_fijos_amortizaciones',
    'gestion__sync_cola'
];
$ok = 0;
foreach ($tables as $t) {
    $res = mysqli_query($conn, "SHOW TABLES LIKE '$t'");
    if (mysqli_num_rows($res) > 0) $ok++;
}
echo "Tablas físicas existentes y operativas: $ok / " . count($tables) . "\n";
