<?php
require __DIR__ . '/../db.php';

echo "=== ROLES EN gestion__roles_entidades ===\n";
$res = $conn->query("SELECT * FROM gestion__roles_entidades ORDER BY rol_entidad_id");
while ($r = $res->fetch_assoc()) {
    echo "ID: {$r['rol_entidad_id']} | Código: {$r['rol_entidad_codigo']} | Nombre: {$r['rol_entidad_nombre']} | Desc: {$r['rol_entidad_descripcion']}\n";
}

echo "\n=== CONTEO gestion__entidades_roles ===\n";
$res = $conn->query("SELECT r.rol_entidad_codigo, r.rol_entidad_nombre, count(er.entidad_rol_id) as total
                     FROM gestion__roles_entidades r
                     LEFT JOIN gestion__entidades_roles er ON r.rol_entidad_id = er.rol_entidad_id
                     GROUP BY r.rol_entidad_id");
while ($r = $res->fetch_assoc()) {
    echo "{$r['rol_entidad_codigo']} ({$r['rol_entidad_nombre']}): {$r['total']}\n";
}

echo "\n=== ENTIDADES EXISTENTES ===\n";
$res = $conn->query("SELECT 
    COUNT(*) as total_entidades,
    SUM(es_cliente = 1) as con_es_cliente,
    SUM(es_proveedor = 1) as con_es_proveedor,
    SUM(es_cliente = 1 AND es_proveedor = 1) as ambos
FROM gestion__entidades");
print_r($res->fetch_assoc());
