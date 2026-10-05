<?php
require_once __DIR__ . '/../db.php';

$data = [];

// 1. Modulos
$resMod = $conn->query("SELECT * FROM conf__modulos ORDER BY modulo_id");
$data['modulos'] = [];
while ($r = $resMod->fetch_assoc()) {
    $data['modulos'][] = $r;
}

// 2. Paginas
$resPag = $conn->query("
    SELECT p.*, m.modulo as modulo_nombre
    FROM conf__paginas p
    LEFT JOIN conf__modulos m ON p.modulo_id = m.modulo_id
    ORDER BY p.modulo_id, p.padre_id, p.orden
");
$data['paginas'] = [];
while ($r = $resPag->fetch_assoc()) {
    $data['paginas'][] = $r;
}

// 3. Tablas configuradas
$resTablas = $conn->query("SELECT * FROM conf__tablas");
$data['tablas_configuradas'] = [];
while ($r = $resTablas->fetch_assoc()) {
    $data['tablas_configuradas'][] = $r;
}

// 4. Estados registros generales
$resEst = $conn->query("SELECT * FROM conf__estados_registros");
$data['estados_registros'] = [];
while ($r = $resEst->fetch_assoc()) {
    $data['estados_registros'][] = $r;
}

// 5. Comprobantes tipos
$resCT = $conn->query("SELECT * FROM gestion__comprobantes_tipos ORDER BY comprobante_grupo_id, comprobante_subgrupo_id, codigo");
$data['comprobantes_tipos'] = [];
while ($r = $resCT->fetch_assoc()) {
    $data['comprobantes_tipos'][] = $r;
}

// 6. Comprobantes grupos
$resCG = $conn->query("SELECT * FROM gestion__comprobantes_grupos");
$data['comprobantes_grupos'] = [];
while ($r = $resCG->fetch_assoc()) {
    $data['comprobantes_grupos'][] = $r;
}

// 7. Comprobantes subgrupos
$resCS = $conn->query("SELECT * FROM gestion__comprobantes_subgrupos");
$data['comprobantes_subgrupos'] = [];
while ($r = $resCS->fetch_assoc()) {
    $data['comprobantes_subgrupos'][] = $r;
}

// 8. Funciones por pagina
$resFunc = $conn->query("SELECT * FROM conf__paginas_funciones");
$data['paginas_funciones'] = [];
while ($r = $resFunc->fetch_assoc()) {
    $data['paginas_funciones'][] = $r;
}

// 9. All tables list with columns summary
$resAllT = $conn->query("SHOW TABLES");
$data['all_tables'] = [];
while ($r = $resAllT->fetch_row()) {
    $tableName = $r[0];
    $colRes = $conn->query("SHOW COLUMNS FROM `$tableName`");
    $cols = [];
    while ($col = $colRes->fetch_assoc()) {
        $cols[] = [
            'name' => $col['Field'],
            'type' => $col['Type'],
            'null' => $col['Null'],
            'key' => $col['Key'],
            'default' => $col['Default']
        ];
    }
    $data['all_tables'][$tableName] = $cols;
}

file_put_contents(__DIR__ . '/system_info.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "SUCCESS. Saved " . strlen(json_encode($data)) . " bytes to system_info.json\n";
