<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../core/permisos.php';

$usuario_id = 1; // pablo
$empresa_id = 2; // Casa Lucho
$modulo_id = 4;  // Estudio Contable

echo "=== 1. USUARIO_OBTENER_EMPRESAS_MODULOS ===\n";
$em = usuario_obtener_empresas_modulos($conn, $usuario_id);
echo json_encode($em, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

echo "=== 2. PAGINAS PERMITIDAS ===\n";
$pp = usuario_obtener_paginas_permitidas($conn, $usuario_id, $empresa_id);
echo "Total permitidas: " . count($pp) . "\n";

echo "=== 3. CONSULTA SIDEBAR (padre_id = 0) ===\n";
$sql = "SELECT conf__paginas.*, conf__iconos.icono_clase 
        FROM conf__paginas 
        LEFT JOIN conf__iconos ON conf__paginas.icono_id = conf__iconos.icono_id
        WHERE conf__paginas.modulo_id = $modulo_id 
        AND conf__paginas.tabla_estado_registro_id = 1 
        AND conf__paginas.padre_id = 0 
        ORDER BY conf__paginas.orden";
$res = mysqli_query($conn, $sql);
echo "Filas padre_id = 0: " . mysqli_num_rows($res) . "\n";
while ($row = mysqli_fetch_assoc($res)) {
    echo "Padre ID: {$row['pagina_id']} | Pagina: {$row['pagina']} | Icono: {$row['icono_clase']}\n";
    $sub_sql = "SELECT conf__paginas.*, conf__iconos.icono_clase
                FROM conf__paginas
                LEFT JOIN conf__iconos ON conf__paginas.icono_id = conf__iconos.icono_id
                WHERE conf__paginas.padre_id = " . $row['pagina_id'] . "
                AND conf__paginas.tabla_estado_registro_id = 1
                ORDER BY conf__paginas.orden";
    $sub_res = mysqli_query($conn, $sub_sql);
    echo "  Hijos: " . mysqli_num_rows($sub_res) . "\n";
    while ($sub = mysqli_fetch_assoc($sub_res)) {
        echo "    Sub ID: {$sub['pagina_id']} | {$sub['pagina']} | URL: {$sub['url']} | Icono: {$sub['icono_clase']}\n";
    }
}
