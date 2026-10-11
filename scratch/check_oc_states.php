<?php
require_once __DIR__ . '/../db.php';
$r = $conn->query("SELECT er.estado_registro_id, er.nombre_estado, er.codigo_estandar, c.color_clase, c.bg_clase, c.text_clase, COUNT(oc.orden_compra_id) as cant
                   FROM conf__estados_registros er
                   LEFT JOIN conf__colores c ON er.color_id = c.color_id
                   LEFT JOIN gestion__ordenes_compra oc ON oc.tabla_estado_registro_id = er.estado_registro_id
                   WHERE er.tabla_id = 71 OR er.estado_registro_id IN (SELECT DISTINCT tabla_estado_registro_id FROM gestion__ordenes_compra)
                   GROUP BY er.estado_registro_id");
while ($row = $r->fetch_assoc()) {
    echo "ID: {$row['estado_registro_id']} | Nombre: {$row['nombre_estado']} | Cod: {$row['codigo_estandar']} | Cant: {$row['cant']}\n";
}

$r2 = $conn->query("SELECT DISTINCT er.estado_registro_id, er.nombre_estado, er.codigo_estandar, COUNT(*) as c 
                    FROM gestion__ordenes_compra oc
                    LEFT JOIN conf__estados_registros er ON oc.tabla_estado_registro_id = er.estado_registro_id
                    GROUP BY oc.tabla_estado_registro_id");
echo "\nEn ordenes_compra reales:\n";
while ($row = $r2->fetch_assoc()) {
    echo "ID: {$row['estado_registro_id']} | Nombre: {$row['nombre_estado']} | Cod: {$row['codigo_estandar']} | Cant: {$row['c']}\n";
}
