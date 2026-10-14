<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../db.php';

$sqlFile = __DIR__ . '/../sql/mantenimiento/20261013_entidades_roles_cuentas_contables_01.sql';
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

// Verificaciones
echo "\n=== VERIFICACIÓN gestion__empresas_roles_cuentas ===\n";
$res = $conn->query("SELECT erc.*, r.rol_entidad_codigo, r.rol_entidad_nombre, cc.codigo, cc.nombre as cuenta_nombre
                     FROM gestion__empresas_roles_cuentas erc
                     JOIN gestion__roles_entidades r ON erc.rol_entidad_id = r.rol_entidad_id
                     LEFT JOIN gestion__cont_cuentas cc ON erc.cont_cuenta_id = cc.cont_cuenta_id
                     ORDER BY erc.empresa_id, erc.rol_entidad_id");
while ($r = $res->fetch_assoc()) {
    echo "Empresa {$r['empresa_id']} | Rol {$r['rol_entidad_codigo']} ({$r['rol_entidad_nombre']}) -> Cuenta {$r['cont_cuenta_id']} ({$r['codigo']} - {$r['cuenta_nombre']})\n";
}

echo "\n=== CUENTAS EN gestion__entidades_roles (migradas) ===\n";
$res = $conn->query("SELECT r.rol_entidad_codigo, count(*) as total, count(er.cont_cuenta_id) as con_cuenta
                     FROM gestion__entidades_roles er
                     JOIN gestion__roles_entidades r ON er.rol_entidad_id = r.rol_entidad_id
                     GROUP BY r.rol_entidad_id");
while ($r = $res->fetch_assoc()) {
    echo "Rol {$r['rol_entidad_codigo']}: {$r['con_cuenta']} de {$r['total']} tienen cont_cuenta_id\n";
}
