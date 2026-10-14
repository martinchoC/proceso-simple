<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../db.php';

echo "Comprobantes tipos compra:\n";
$q = mysqli_query($conexion, "SELECT * FROM gestion__comprobantes_tipos LIMIT 1");
$row = mysqli_fetch_assoc($q);
print_r(array_keys($row));

echo "\nComprobantes tipos cancelaciones en empresa 2:\n";
echo "\nSubgrupos:\n";
$qs = mysqli_query($conexion, "SELECT * FROM gestion__comprobantes_subgrupos");
while ($r = mysqli_fetch_assoc($qs)) {
    echo "Subgrupo ID: {$r['comprobante_subgrupo_id']} | Cod: {$r['codigo']} | Nombre: {$r['nombre']}\n";
}

echo "\nTipos que impactan ctacte o tienen grupo compras / pagos:\n";
$qt = mysqli_query($conexion, "SELECT t.comprobante_tipo_id, t.codigo, t.comprobante_tipo, t.letra, t.signo, 
                                      g.codigo as grupo_cod, sg.codigo as subgrupo_cod
                               FROM gestion__comprobantes_tipos t
                               LEFT JOIN gestion__comprobantes_grupos g ON g.comprobante_grupo_id = t.comprobante_grupo_id
                               LEFT JOIN gestion__comprobantes_subgrupos sg ON sg.comprobante_subgrupo_id = t.comprobante_subgrupo_id
                               WHERE t.empresa_id = 2 AND t.tabla_estado_registro_id = 1
                               ORDER BY g.codigo, sg.codigo, t.codigo");
while ($r = mysqli_fetch_assoc($qt)) {
    echo "ID: {$r['comprobante_tipo_id']} | Grupo: {$r['grupo_cod']} | Subgrupo: {$r['subgrupo_cod']} | Cod: {$r['codigo']} | Signo: {$r['signo']} | Tipo: {$r['comprobante_tipo']}\n";
}
