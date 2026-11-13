<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require 'db.php';

echo "=== MIGRACION FINAL: REGISTRO PAGINA 111 Y ASIGNACIONES ===\n";

// 1. conf__paginas
$checkPag = mysqli_query($conn, "SELECT pagina_id FROM conf__paginas WHERE pagina_id = 111 OR url = 'empresas_parametros.php'");
if (mysqli_num_rows($checkPag) === 0) {
    mysqli_query($conn, "INSERT INTO conf__paginas (pagina_id, modulo_id, pagina, url, icono_id, padre_id, orden, tabla_id, tabla_estado_registro_id) 
                         VALUES (111, 2, 'Parámetros de Empresa', 'empresas_parametros.php', 37, 37, 405, 429, 1)");
    echo "OK: Pagina 111 insertada en conf__paginas\n";
} else {
    echo "INFO: Pagina 111 ya existe\n";
}

// 2. conf__paginas_funciones
$funciones = [
    ['Visualizar', 37, 1, 1, 'visualizar', 0, 1, 1],
    ['Guardar Parámetros', 42, 3, 1, 'guardar', 0, 1, 2],
    ['Editar', 40, 1, 1, 'editar', 1, 1, 10],
    ['Inhabilitar', 45, 4, 1, 'inactivar', 1, 2, 20],
    ['Habilitar', 46, 3, 1, 'habilitar', 2, 1, 100]
];

foreach ($funciones as $f) {
    $nombre = $f[0];
    $icono = $f[1];
    $color = $f[2];
    $estandar = $f[3];
    $accionJs = $f[4];
    $origen = $f[5];
    $destino = $f[6];
    $orden = $f[7];

    $check = mysqli_query($conn, "SELECT pagina_funcion_id FROM conf__paginas_funciones WHERE pagina_id = 111 AND nombre_funcion = '$nombre'");
    if (mysqli_num_rows($check) === 0) {
        $sql = "INSERT INTO conf__paginas_funciones 
                (pagina_id, icono_id, color_id, funcion_estandar_id, nombre_funcion, accion_js, descripcion, tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, orden, tabla_estado_registro_id)
                VALUES 
                (111, $icono, $color, $estandar, '$nombre', '$accionJs', '', $origen, $destino, $orden, 1)";
        mysqli_query($conn, $sql);
        echo "OK: Funcion '$nombre' agregada para pagina 111\n";
    }
}

// 3. Asignar permisos a perfiles 1 y 2
$perfiles = [1, 2];
foreach ($perfiles as $perf) {
    $checkPerf = mysqli_query($conn, "SELECT perfil_id FROM conf___perfiles_paginas WHERE pagina_id = 111 AND perfil_id = $perf");
    if (mysqli_num_rows($checkPerf) === 0) {
        mysqli_query($conn, "INSERT INTO conf___perfiles_paginas (perfil_id, pagina_id, tabla_estado_registro_id) VALUES ($perf, 111, 1)");
        echo "OK: Permiso asignado a perfil $perf para pagina 111\n";
    }
}

// 4. Seed inicial para empresa_id = 2 si no tiene
$checkParam = mysqli_query($conn, "SELECT empresa_parametro_id FROM gestion__empresas_parametros WHERE empresa_id = 2");
if (mysqli_num_rows($checkParam) === 0) {
    $seedSql = "INSERT INTO gestion__empresas_parametros 
                (empresa_id, condicion_fiscal_id, ingresos_brutos_nro, inicio_actividades, leyenda_fiscal, f_desde, f_hasta, observaciones, tabla_estado_registro_id)
                VALUES 
                (2, 1, '30541512590', '1980-01-01', 'IVA Responsable Inscripto', '2020-01-01', NULL, 'Configuración inicial vigente', 1)";
    mysqli_query($conn, $seedSql);
    echo "OK: Seed inicial insertado para empresa_id = 2\n";
}

// 5. Actualizar conf__empresas.condicion_fiscal_id para empresa_id = 2
mysqli_query($conn, "UPDATE conf__empresas SET condicion_fiscal_id = 1 WHERE empresa_id = 2");
echo "OK: conf__empresas.condicion_fiscal_id = 1 para empresa_id = 2\n";

echo "=== MIGRACION COMPLETADA CON EXITO ===\n";
