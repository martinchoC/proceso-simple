<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    require 'db.php';
    echo "1. Permisos...\n";
    $perfiles = [1, 2];
    foreach ($perfiles as $perf) {
        $checkPerf = mysqli_query($conn, "SELECT perfil_id FROM conf___perfiles_paginas WHERE pagina_id = 111 AND perfil_id = $perf");
        if (mysqli_num_rows($checkPerf) === 0) {
            mysqli_query($conn, "INSERT INTO conf___perfiles_paginas (perfil_id, pagina_id, estado_registro_id) VALUES ($perf, 111, 1)");
            echo "OK: Permiso asignado a perfil $perf para pagina 111\n";
        }
    }

    echo "2. Seed inicial...\n";
    $checkParam = mysqli_query($conn, "SELECT empresa_parametro_id FROM gestion__empresas_parametros WHERE empresa_id = 2");
    if (mysqli_num_rows($checkParam) === 0) {
        $seedSql = "INSERT INTO gestion__empresas_parametros 
                    (empresa_id, condicion_fiscal_id, ingresos_brutos_nro, inicio_actividades, leyenda_fiscal, f_desde, f_hasta, observaciones, tabla_estado_registro_id)
                    VALUES 
                    (2, 1, '30541512590', '1980-01-01', 'IVA Responsable Inscripto', '2020-01-01', NULL, 'Configuración inicial vigente', 1)";
        mysqli_query($conn, $seedSql);
        echo "OK: Seed insertado para empresa_id = 2\n";
    } else {
        echo "INFO: Seed ya existia para empresa_id = 2\n";
    }

    echo "3. Update conf__empresas...\n";
    mysqli_query($conn, "UPDATE conf__empresas SET condicion_fiscal_id = 1 WHERE empresa_id = 2");
    echo "OK: conf__empresas.condicion_fiscal_id = 1 para empresa_id = 2\n";

    echo "=== MIGRACION FINALIZADA COMPLETAMENTE ===\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
