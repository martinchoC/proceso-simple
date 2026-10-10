<?php
require_once __DIR__ . '/../db.php';

mysqli_begin_transaction($conn);

try {
    // 1. Crear tabla estudio__minigestion_comprobantes
    $sql1 = "CREATE TABLE IF NOT EXISTS `estudio__minigestion_comprobantes` (
        `comprobante_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `empresa_id` INT UNSIGNED NOT NULL COMMENT 'Tenant del estudio contable',
        `cliente_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK estudio__clientes.cliente_id',
        `tipo_operacion` ENUM('VENTA', 'COMPRA') NOT NULL,
        `f_emision` DATE NOT NULL,
        `periodo_fiscal` INT UNSIGNED NOT NULL COMMENT 'YYYYMM',
        `comprobante_tipo_id` INT UNSIGNED NOT NULL DEFAULT 4 COMMENT 'FK gestion__comprobantes_tipos',
        `comprobante_letra` VARCHAR(2) NOT NULL DEFAULT 'A',
        `punto_venta` INT UNSIGNED NOT NULL DEFAULT 1,
        `comprobante_nro` BIGINT UNSIGNED NOT NULL DEFAULT 1,
        `cuit_contraparte` VARCHAR(13) NOT NULL,
        `razon_social_contraparte` VARCHAR(150) NOT NULL,
        `neto_gravado` DECIMAL(16,2) NOT NULL DEFAULT 0.00,
        `alicuota_iva` DECIMAL(5,2) NOT NULL DEFAULT 21.00,
        `importe_iva` DECIMAL(16,2) NOT NULL DEFAULT 0.00,
        `no_gravado_exento` DECIMAL(16,2) NOT NULL DEFAULT 0.00,
        `percepcion_iibb` DECIMAL(16,2) NOT NULL DEFAULT 0.00,
        `percepcion_iva` DECIMAL(16,2) NOT NULL DEFAULT 0.00,
        `importe_total` DECIMAL(16,2) NOT NULL DEFAULT 0.00,
        `observaciones` VARCHAR(255) NULL,
        `tabla_estado_registro_id` INT UNSIGNED NOT NULL DEFAULT 1,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY `idx_empresa_cliente_op` (`empresa_id`, `cliente_id`, `tipo_operacion`, `periodo_fiscal`),
        KEY `idx_periodo` (`empresa_id`, `periodo_fiscal`),
        KEY `idx_estado` (`tabla_estado_registro_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    mysqli_query($conn, $sql1) or die("Error sql1: " . mysqli_error($conn));

    // 2. Registrar en conf__tablas
    $sql2 = "INSERT INTO `conf__tablas` (`tabla_id`, `tabla_nombre`, `tabla_descripcion`, `modulo_id`, `tabla_tipo_id`, `tabla_patron_id`, `tabla_estado_registro_id`)
             VALUES (426, 'estudio__minigestion_comprobantes', 'Comprobantes de compra y venta de mini-gestion para clientes del estudio', 4, 2, 1, 1)
             ON DUPLICATE KEY UPDATE `tabla_nombre` = VALUES(`tabla_nombre`), `tabla_descripcion` = VALUES(`tabla_descripcion`);";
    mysqli_query($conn, $sql2) or die("Error sql2: " . mysqli_error($conn));

    // 3. Registrar estados en conf__tablas_estados_registros
    $sql3 = "INSERT INTO `conf__tablas_estados_registros` 
             (`tabla_id`, `estado_registro_id`, `tabla_estado_registro`, `color_id`, `es_inicial`, `es_final`, `orden`)
             VALUES 
             (426, 1, 'Activo / Registrado', 1, 1, 0, 1),
             (426, 2, 'Anulado', 4, 0, 1, 2)
             ON DUPLICATE KEY UPDATE `tabla_estado_registro` = VALUES(`tabla_estado_registro`);";
    mysqli_query($conn, $sql3) or die("Error sql3: " . mysqli_error($conn));

    // 4. Registrar página en conf__paginas (ID: 412)
    $sql4 = "INSERT INTO `conf__paginas` 
             (`pagina_id`, `modulo_id`, `pagina`, `url`, `icono_id`, `pagina_descripcion`, `padre_id`, `orden`, `tabla_id`, `pagina_patron_id`, `tabla_estado_registro_id`, `es_acceso_directo`, `orden_acceso_directo`)
             VALUES 
             (412, 4, 'Mini-Gestión de Clientes', 'minigestion.php', 83, 'Mini-gestion de comprobantes para clientes externos y visualizacion/auditoria para empresas ERP', 400, 4, 426, 1, 1, 1, 2)
             ON DUPLICATE KEY UPDATE `pagina` = VALUES(`pagina`), `url` = VALUES(`url`), `icono_id` = VALUES(`icono_id`), `padre_id` = VALUES(`padre_id`), `orden` = VALUES(`orden`), `es_acceso_directo` = VALUES(`es_acceso_directo`);";
    mysqli_query($conn, $sql4) or die("Error sql4: " . mysqli_error($conn));

    // 5. Registrar funciones en conf__paginas_funciones
    $chk_pf = mysqli_query($conn, "SELECT COUNT(*) FROM conf__paginas_funciones WHERE pagina_id = 412");
    $r_pf = mysqli_fetch_row($chk_pf);
    if ($r_pf[0] == 0) {
        $sql5 = "INSERT INTO `conf__paginas_funciones` 
                 (`pagina_id`, `icono_id`, `color_id`, `funcion_estandar_id`, `nombre_funcion`, `accion_js`, `descripcion`, `tabla_estado_registro_origen_id`, `tabla_estado_registro_destino_id`, `orden`, `tabla_estado_registro_id`)
                 VALUES 
                 (412, 42, 1, 1, 'Acceso / Ver', 'ver', 'Ver comprobantes de mini-gestion', 0, 0, 1, 1),
                 (412, 42, 1, 1, 'Agregar', 'agregar', 'Cargar nuevo comprobante de venta o compra', 0, 0, 2, 1),
                 (412, 42, 1, 1, 'Editar', 'editar', 'Modificar comprobante de mini-gestion', 0, 0, 3, 1),
                 (412, 42, 1, 1, 'Inhabilitar / Eliminar', 'inhabilitar', 'Anular comprobante de mini-gestion', 0, 0, 4, 1);";
        mysqli_query($conn, $sql5) or die("Error sql5: " . mysqli_error($conn));
    }

    // 6. Asignar página a perfiles
    $sql6 = "INSERT IGNORE INTO `conf___perfiles_paginas` (`perfil_id`, `pagina_id`, `estado_registro_id`) VALUES 
             (1, 412, 1),
             (401, 412, 1),
             (402, 412, 1),
             (403, 412, 1);";
    mysqli_query($conn, $sql6) or die("Error sql6: " . mysqli_error($conn));

    // 7. Asignar funciones a perfiles
    $pf_res = mysqli_query($conn, "SELECT pagina_funcion_id FROM conf__paginas_funciones WHERE pagina_id = 412");
    while ($pf_row = mysqli_fetch_assoc($pf_res)) {
        $pf_id = $pf_row['pagina_funcion_id'];
        foreach ([1, 401, 402, 403] as $pid) {
            mysqli_query($conn, "INSERT IGNORE INTO `conf__perfiles_funciones` (`perfil_id`, `pagina_funcion_id`, `asignado`) VALUES ($pid, $pf_id, 1)");
        }
    }

    mysqli_commit($conn);
    echo "MIGRACION MINIGESTION COMPLETADA CON EXITO!\n";
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo "ERROR EN MIGRACION: " . $e->getMessage() . "\n";
}
