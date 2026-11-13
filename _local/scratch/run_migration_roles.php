<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../db.php';

$sqlFile = __DIR__ . '/../sql/mantenimiento/20261013_entidades_roles_sincronizacion_01.sql';
$sqlContent = file_get_contents($sqlFile);

if (!$sqlContent) {
    die("No se pudo leer el archivo SQL: $sqlFile\n");
}

echo "Ejecutando script de migración...\n";

if ($conn->multi_query($sqlContent)) {
    do {
        if ($res = $conn->store_result()) {
            $res->free();
        }
    } while ($conn->more_results() && $conn->next_result());
}

if ($conn->errno) {
    echo "ERROR al ejecutar migración: " . $conn->error . "\n";
} else {
    echo "Migración completada con éxito.\n";
}

// Verificación post-ejecución
echo "\n=== CONTEO POST MIGRACIÓN ===\n";
$res = $conn->query("SELECT r.rol_entidad_id, r.rol_entidad_codigo, r.rol_entidad_nombre, count(er.entidad_rol_id) as total
                     FROM gestion__roles_entidades r
                     LEFT JOIN gestion__entidades_roles er ON r.rol_entidad_id = er.rol_entidad_id AND er.f_baja IS NULL
                     GROUP BY r.rol_entidad_id");
while ($r = $res->fetch_assoc()) {
    echo "ID {$r['rol_entidad_id']} | {$r['rol_entidad_codigo']} ({$r['rol_entidad_nombre']}): {$r['total']}\n";
}

$resTot = $conn->query("SELECT COUNT(*) as total_filas FROM gestion__entidades_roles");
$tot = $resTot->fetch_assoc();
echo "\nTotal registros en gestion__entidades_roles: {$tot['total_filas']}\n";
