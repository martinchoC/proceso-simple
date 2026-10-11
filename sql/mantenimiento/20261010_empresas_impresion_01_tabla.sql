-- =====================================================================
-- Configuración de impresión de comprobantes por empresa - script 01: TABLA
-- 2026-10-10
--
-- gestion__empresas_impresion: cómo se imprime cada grupo de comprobantes de una empresa.
-- Se administra en la solapa "Impresión" de Parámetros de Empresa (página 111) y la leen
-- core/impresion.php + templates/impresion/*.php (hoy: ventas_facturas_print.php).
--
--   comprobante_subgrupo_id = 0  -> configuración general de la empresa (todos los comprobantes)
--   comprobante_subgrupo_id > 0  -> pisa la general para ese subgrupo (p. ej. 1 Facturación)
-- Sin fila activa se usan los valores por defecto de impresion_config_defecto() (A4, 1 copia).
-- No se versiona por vigencia (no es un dato fiscal): se edita en el lugar.
--
-- Reanudable: CREATE TABLE IF NOT EXISTS. Ambos motores (MariaDB 10.4 local, MySQL 8.0 producción).
-- No es una página nueva: no requiere conf__tablas / funciones / permisos.
-- =====================================================================

CREATE TABLE IF NOT EXISTS `gestion__empresas_impresion` (
    `empresa_impresion_id`      INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    `empresa_id`                SMALLINT UNSIGNED NOT NULL,
    `comprobante_subgrupo_id`   INT UNSIGNED      NOT NULL DEFAULT 0 COMMENT '0 = todos los comprobantes de la empresa',
    `formato`                   VARCHAR(10)       NOT NULL DEFAULT 'A4' COMMENT 'A4 | TICKET80',
    `copias_defecto`            TINYINT UNSIGNED  NOT NULL DEFAULT 1 COMMENT '1 a 3 (original, duplicado, triplicado)',
    `mostrar_logo`              TINYINT(1)        NOT NULL DEFAULT 1,
    `mostrar_codigo`            TINYINT(1)        NOT NULL DEFAULT 1 COMMENT 'columna de código de producto',
    `mostrar_bonificacion`      TINYINT(1)        NOT NULL DEFAULT 1 COMMENT 'columna % de bonificación',
    `mostrar_sucursal_cliente`  TINYINT(1)        NOT NULL DEFAULT 1 COMMENT 'sucursal / lugar de entrega del cliente',
    `texto_pie`                 VARCHAR(1000)     NULL DEFAULT NULL COMMENT 'texto libre al pie (datos bancarios, garantía, etc.)',
    `color_acento`              VARCHAR(7)        NULL DEFAULT NULL COMMENT '#RRGGBB para encabezados de tabla; NULL = gris',
    `usuario_id`                BIGINT UNSIGNED   NULL DEFAULT NULL,
    `tabla_estado_registro_id`  SMALLINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1 Activo / 2 Inactivo (genérico)',
    `created_at`                DATETIME          NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`                DATETIME          NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`empresa_impresion_id`),
    UNIQUE KEY `uk_empresa_impresion` (`empresa_id`, `comprobante_subgrupo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Configuración de impresión de comprobantes por empresa y subgrupo';

-- Resumen (solo lectura)
SELECT empresa_id, comprobante_subgrupo_id, formato, copias_defecto, tabla_estado_registro_id
FROM gestion__empresas_impresion
ORDER BY empresa_id, comprobante_subgrupo_id;
