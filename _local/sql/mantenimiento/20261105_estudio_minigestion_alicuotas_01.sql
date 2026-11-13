-- Mini-gestión del estudio: IVA por alícuota y percepciones desglosadas (2026-11-05)
-- La cabecera estudio__minigestion_comprobantes conserva neto_gravado / importe_iva / percepcion_* como TOTALES
-- (los libros y el resumen siguen leyendo de ahí); el detalle vive en las dos tablas nuevas.
-- Idempotente: se puede correr más de una vez.

CREATE TABLE IF NOT EXISTS `estudio__minigestion_comprobantes_iva` (
  `iva_linea_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(10) unsigned NOT NULL,
  `comprobante_id` bigint(20) unsigned NOT NULL COMMENT 'FK estudio__minigestion_comprobantes',
  `alicuota` decimal(5,2) NOT NULL COMMENT '0, 2.5, 5, 10.5, 21, 27',
  `neto` decimal(16,2) NOT NULL DEFAULT 0.00,
  `iva` decimal(16,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`iva_linea_id`),
  UNIQUE KEY `uk_comprobante_alicuota` (`comprobante_id`,`alicuota`),
  KEY `idx_empresa` (`empresa_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `estudio__minigestion_comprobantes_percepciones` (
  `percepcion_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(10) unsigned NOT NULL,
  `comprobante_id` bigint(20) unsigned NOT NULL,
  `tipo` enum('IVA','IIBB','GANANCIAS','SUSS','MUNICIPAL','OTRA') COLLATE utf8mb4_unicode_ci NOT NULL,
  `jurisdiccion_id` int(10) unsigned DEFAULT NULL COMMENT 'FK gestion__jurisdicciones; solo IIBB/MUNICIPAL',
  `importe` decimal(16,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`percepcion_id`),
  KEY `idx_comprobante` (`comprobante_id`),
  KEY `idx_empresa_tipo` (`empresa_id`,`tipo`,`jurisdiccion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Índice para el control de duplicados (no UNIQUE: puede haber duplicados históricos y los anulados no cuentan)
SET @existe := (SELECT COUNT(*) FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND table_name = 'estudio__minigestion_comprobantes' AND index_name = 'idx_duplicado');
SET @ddl := IF(@existe = 0,
  'ALTER TABLE estudio__minigestion_comprobantes ADD KEY idx_duplicado (empresa_id, cliente_id, tipo_operacion, comprobante_tipo_id, punto_venta, comprobante_nro, cuit_contraparte)',
  'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- Backfill: cada comprobante ya cargado queda con una línea de IVA (la alícuota única que tenía)
INSERT INTO estudio__minigestion_comprobantes_iva (empresa_id, comprobante_id, alicuota, neto, iva)
SELECT c.empresa_id, c.comprobante_id, c.alicuota_iva, c.neto_gravado, c.importe_iva
FROM estudio__minigestion_comprobantes c
WHERE (c.neto_gravado <> 0 OR c.importe_iva <> 0)
  AND NOT EXISTS (SELECT 1 FROM estudio__minigestion_comprobantes_iva i WHERE i.comprobante_id = c.comprobante_id);

INSERT INTO estudio__minigestion_comprobantes_percepciones (empresa_id, comprobante_id, tipo, jurisdiccion_id, importe)
SELECT c.empresa_id, c.comprobante_id, 'IIBB', NULL, c.percepcion_iibb
FROM estudio__minigestion_comprobantes c
WHERE c.percepcion_iibb <> 0
  AND NOT EXISTS (SELECT 1 FROM estudio__minigestion_comprobantes_percepciones p WHERE p.comprobante_id = c.comprobante_id AND p.tipo = 'IIBB');

INSERT INTO estudio__minigestion_comprobantes_percepciones (empresa_id, comprobante_id, tipo, jurisdiccion_id, importe)
SELECT c.empresa_id, c.comprobante_id, 'IVA', NULL, c.percepcion_iva
FROM estudio__minigestion_comprobantes c
WHERE c.percepcion_iva <> 0
  AND NOT EXISTS (SELECT 1 FROM estudio__minigestion_comprobantes_percepciones p WHERE p.comprobante_id = c.comprobante_id AND p.tipo = 'IVA');
