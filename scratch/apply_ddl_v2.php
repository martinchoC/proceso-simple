<?php
require_once __DIR__ . '/../db.php';

mysqli_begin_transaction($conn);

try {
    // 1. DDL: Tablas nuevas
    // gestion__cont_parametros
    $sql1 = "CREATE TABLE IF NOT EXISTS `gestion__cont_parametros` (
        `parametro_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `empresa_id` INT UNSIGNED NOT NULL,
        `cuenta_deudores_ventas_id` BIGINT UNSIGNED NOT NULL,
        `cuenta_ventas_defecto_id` BIGINT UNSIGNED NOT NULL,
        `cuenta_iva_debito_id` BIGINT UNSIGNED NOT NULL,
        `cuenta_proveedores_id` BIGINT UNSIGNED NOT NULL,
        `cuenta_gastos_defecto_id` BIGINT UNSIGNED NOT NULL,
        `cuenta_iva_credito_id` BIGINT UNSIGNED NOT NULL,
        `cuenta_caja_defecto_id` BIGINT UNSIGNED NOT NULL,
        `cuenta_banco_defecto_id` BIGINT UNSIGNED NOT NULL,
        `cuenta_resultado_ejercicio_id` BIGINT UNSIGNED NOT NULL,
        `cuenta_resultados_acumulados_id` BIGINT UNSIGNED NOT NULL,
        `tabla_estado_registro_id` INT UNSIGNED NOT NULL DEFAULT 1,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `uk_empresa_param` (`empresa_id`),
        KEY `idx_estado` (`tabla_estado_registro_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    mysqli_query($conn, $sql1) or die("Error sql1: " . mysqli_error($conn));

    // gestion__cobranzas_retenciones
    $sql2 = "CREATE TABLE IF NOT EXISTS `gestion__cobranzas_retenciones` (
        `cobranza_retencion_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `empresa_id` INT UNSIGNED NOT NULL,
        `comprobante_id` BIGINT UNSIGNED NOT NULL,
        `tipo_retencion` ENUM('GANANCIAS', 'IVA', 'IIBB', 'SUSS', 'OTRA') NOT NULL,
        `jurisdiccion_id` INT UNSIGNED NULL,
        `nro_certificado` VARCHAR(50) NOT NULL,
        `f_retencion` DATE NOT NULL,
        `importe_base` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        `alicuota` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
        `importe_retenido` DECIMAL(14,2) NOT NULL,
        `cuenta_contable_id` BIGINT UNSIGNED NOT NULL,
        `tabla_estado_registro_id` INT UNSIGNED NOT NULL DEFAULT 1,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY `idx_empresa_comprobante` (`empresa_id`, `comprobante_id`),
        KEY `idx_tipo_jurisdiccion` (`tipo_retencion`, `jurisdiccion_id`),
        KEY `idx_estado` (`tabla_estado_registro_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    mysqli_query($conn, $sql2) or die("Error sql2: " . mysqli_error($conn));

    // gestion__cont_cierres_ejercicios
    $sql3 = "CREATE TABLE IF NOT EXISTS `gestion__cont_cierres_ejercicios` (
        `cierre_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `empresa_id` INT UNSIGNED NOT NULL,
        `anio_ejercicio` SMALLINT UNSIGNED NOT NULL,
        `f_inicio` DATE NOT NULL,
        `f_cierre` DATE NOT NULL,
        `asiento_refundicion_id` BIGINT UNSIGNED NOT NULL,
        `asiento_cierre_patrimonial_id` BIGINT UNSIGNED NOT NULL,
        `asiento_apertura_id` BIGINT UNSIGNED NULL,
        `resultado_neto_ejercicio` DECIMAL(16,2) NOT NULL,
        `usuario_cierre_id` INT UNSIGNED NOT NULL,
        `tabla_estado_registro_id` INT UNSIGNED NOT NULL DEFAULT 5,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uk_empresa_ejercicio` (`empresa_id`, `anio_ejercicio`),
        KEY `idx_estado` (`tabla_estado_registro_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    mysqli_query($conn, $sql3) or die("Error sql3: " . mysqli_error($conn));

    // 2. Metadatos conf__tablas
    $tablas = [
        [425, 'gestion__cont_parametros', 'Cuentas de enlace contables predeterminadas por empresa', 1],
        [426, 'gestion__cobranzas_retenciones', 'Certificados de retenciones impositivas sufridas en cobranzas', 2],
        [427, 'gestion__cont_cierres_ejercicios', 'Cierres de ejercicio anual y refundición de saldos', 3]
    ];
    foreach ($tablas as $t) {
        $st = mysqli_prepare($conn, "INSERT INTO `conf__tablas` (`tabla_id`, `tabla_nombre`, `tabla_descripcion`, `modulo_id`, `tabla_tipo_id`, `tabla_patron_id`, `tabla_estado_registro_id`)
                                     VALUES (?, ?, ?, 4, ?, 1, 1)
                                     ON DUPLICATE KEY UPDATE `tabla_nombre` = VALUES(`tabla_nombre`), `tabla_descripcion` = VALUES(`tabla_descripcion`), `tabla_tipo_id` = VALUES(`tabla_tipo_id`)");
        mysqli_stmt_bind_param($st, "issi", $t[0], $t[1], $t[2], $t[3]);
        mysqli_stmt_execute($st);
        mysqli_stmt_close($st);
    }

    // 3. Metadatos conf__tablas_estados_registros
    $estados = [
        [427, 3, 'Borrador / Pre-Cierre', 1, 1, 0, 1],
        [427, 5, 'Confirmado / Cerrado', 1, 0, 1, 2]
    ];
    foreach ($estados as $e) {
        $st = mysqli_prepare($conn, "INSERT INTO `conf__tablas_estados_registros` (`tabla_id`, `estado_registro_id`, `tabla_estado_registro`, `color_id`, `es_inicial`, `es_final`, `orden`)
                                     VALUES (?, ?, ?, 1, ?, ?, ?)
                                     ON DUPLICATE KEY UPDATE `tabla_estado_registro` = VALUES(`tabla_estado_registro`)");
        mysqli_stmt_bind_param($st, "iisiii", $e[0], $e[1], $e[2], $e[4], $e[5], $e[6]);
        mysqli_stmt_execute($st);
        mysqli_stmt_close($st);
    }

    // 4. Metadatos conf__paginas para las nuevas páginas contables (412 Parámetros, 413 Cierre)
    $paginas = [
        [412, 'Parámetros Contables', 'contabilidad_parametros.php', 83, 'Configuración de cuentas de enlace contable por empresa', 400, 12, 425],
        [413, 'Cierre de Ejercicio', 'contabilidad_cierre.php', 83, 'Cierres anuales con refundición de cuentas de resultado', 400, 13, 427]
    ];
    foreach ($paginas as $p) {
        $st = mysqli_prepare($conn, "INSERT INTO `conf__paginas` (`pagina_id`, `modulo_id`, `pagina`, `url`, `icono_id`, `pagina_descripcion`, `padre_id`, `orden`, `tabla_id`, `pagina_patron_id`, `tabla_estado_registro_id`, `es_acceso_directo`, `orden_acceso_directo`)
                                     VALUES (?, 4, ?, ?, ?, ?, ?, ?, ?, 1, 1, 0, NULL)
                                     ON DUPLICATE KEY UPDATE `pagina` = VALUES(`pagina`), `url` = VALUES(`url`), `icono_id` = VALUES(`icono_id`), `padre_id` = VALUES(`padre_id`), `orden` = VALUES(`orden`)");
        mysqli_stmt_bind_param($st, "issisiii", $p[0], $p[1], $p[2], $p[3], $p[4], $p[5], $p[6], $p[7]);
        mysqli_stmt_execute($st);
        mysqli_stmt_close($st);
    }

    // Asignar permisos para 412 y 413
    foreach ([412, 413] as $pid) {
        foreach ([1, 401, 402, 403] as $perfil_id) {
            mysqli_query($conn, "INSERT IGNORE INTO `conf___perfiles_paginas` (`perfil_id`, `pagina_id`, `estado_registro_id`) VALUES ($perfil_id, $pid, 1)");
        }
    }

    mysqli_commit($conn);
    echo "ESTRUCTURAS DDL Y METADATOS ACTUALIZADOS CON EXITO!\n";
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo "ERROR: " . $e->getMessage() . "\n";
}
