<?php
require_once __DIR__ . '/../../db.php';

$paginas = [
    [414, 'Conceptos de Mini-Gestión', 'minigestion_conceptos.php', 83, 'Catálogo de bienes vs servicios sin stock con imputación contable', 400, 14, 0],
    [415, 'Recibos y Cobranzas', 'minigestion_recibos.php', 83, 'Emisión de recibos deduciendo retenciones con imputación contable', 400, 15, 0],
    [416, 'Plan de Cuentas Contable', 'contabilidad_plan.php', 83, 'Estructura jerárquica del plan de cuentas por empresa', 400, 16, 0]
];

foreach ($paginas as $p) {
    $st = mysqli_prepare($conn, "INSERT INTO `conf__paginas` (`pagina_id`, `modulo_id`, `pagina`, `url`, `icono_id`, `pagina_descripcion`, `padre_id`, `orden`, `tabla_id`, `pagina_patron_id`, `tabla_estado_registro_id`, `es_acceso_directo`)
                                 VALUES (?, 4, ?, ?, ?, ?, ?, ?, ?, 1, 1, 0)
                                 ON DUPLICATE KEY UPDATE `pagina` = VALUES(`pagina`), `url` = VALUES(`url`), `icono_id` = VALUES(`icono_id`), `padre_id` = VALUES(`padre_id`), `orden` = VALUES(`orden`)");
    mysqli_stmt_bind_param($st, "issisiii", $p[0], $p[1], $p[2], $p[3], $p[4], $p[5], $p[6], $p[7]);
    mysqli_stmt_execute($st);
    mysqli_stmt_close($st);

    foreach ([1, 401, 402, 403] as $perfil_id) {
        mysqli_query($conn, "INSERT IGNORE INTO `conf___perfiles_paginas` (`perfil_id`, `pagina_id`, `estado_registro_id`) VALUES ($perfil_id, {$p[0]}, 1)");
    }
}

echo "Páginas 414, 415 y 416 registradas con éxito en conf__paginas y conf___perfiles_paginas!\n";
