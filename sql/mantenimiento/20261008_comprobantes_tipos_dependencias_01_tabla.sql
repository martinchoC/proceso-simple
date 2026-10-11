-- =====================================================================
-- Dependencias entre tipos de comprobante - script 01: TABLA + DATOS
-- 2026-10-08
--
-- Un tipo "dependiente" (NC, ND) solo puede emitirse sobre un comprobante previo de alguno
-- de sus tipos "padre" posibles; no puede ser el punto de partida de un circuito.
-- Tipo raíz = el que no tiene ninguna fila activa como comprobante_tipo_id acá.
--
-- Uso actual: el combo de tipos al facturar desde un remito (ventas_remitos.php y
-- ventas_remitos_pendientes_facturacion.php) pide solo_raiz=1 a vfTiposPorPuntoVenta(), y
-- guardar/editar factura rechaza un tipo dependiente si alguna línea viene de un remito.
--
-- No es lo mismo que gestion__comprobantes_tipos_cancelaciones (qué tipo cancela saldo de
-- cuál en tesorería): el RCX cancela FA pero no "depende" de ella.
--
-- El padre puede ser a su vez dependiente (NC sobre ND, ND sobre NC): la regla es un solo
-- salto por comprobante, no que los padres sean siempre raíz.
--
-- Re-ejecutable: CREATE TABLE IF NOT EXISTS + INSERT IGNORE sobre la clave única.
-- Los datos se cargan por codigo (no por id) para no depender de los ids de cada base.
-- =====================================================================

CREATE TABLE IF NOT EXISTS `gestion__comprobantes_tipos_dependencias` (
  `comprobante_tipo_dependencia_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` smallint(5) unsigned NOT NULL DEFAULT 0,
  `comprobante_tipo_id` int(10) unsigned NOT NULL COMMENT 'Tipo dependiente (NC, ND)',
  `comprobante_tipo_padre_id` int(10) unsigned NOT NULL COMMENT 'Tipo previo del que puede depender (FA, ND, NC)',
  `tabla_estado_registro_id` smallint(6) NOT NULL DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_modificacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_modificacion_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`comprobante_tipo_dependencia_id`),
  UNIQUE KEY `uk_ctd_empresa_tipo_padre` (`empresa_id`,`comprobante_tipo_id`,`comprobante_tipo_padre_id`),
  KEY `ix_ctd_tipo` (`comprobante_tipo_id`),
  KEY `ix_ctd_padre` (`comprobante_tipo_padre_id`),
  CONSTRAINT `fk_ctd_comprobante_tipo_id` FOREIGN KEY (`comprobante_tipo_id`) REFERENCES `gestion__comprobantes_tipos` (`comprobante_tipo_id`),
  CONSTRAINT `fk_ctd_comprobante_tipo_padre_id` FOREIGN KEY (`comprobante_tipo_padre_id`) REFERENCES `gestion__comprobantes_tipos` (`comprobante_tipo_id`),
  CONSTRAINT `ck_ctd_no_autorreferencia` CHECK (`comprobante_tipo_id` <> `comprobante_tipo_padre_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ventas (A, B, X) y compras A. Misma letra entre hijo y padre.
-- Compras X (C_NCX / C_NDX) queda sin cargar: no existe un tipo C_FX.
INSERT IGNORE INTO gestion__comprobantes_tipos_dependencias
       (empresa_id, comprobante_tipo_id, comprobante_tipo_padre_id)
SELECT d.empresa_id, d.comprobante_tipo_id, p.comprobante_tipo_id
FROM (          SELECT 'V_NCA'   AS hijo, 'V_FA'    AS padre
      UNION ALL SELECT 'V_NCA',   'V_NDA'
      UNION ALL SELECT 'V_NDA',   'V_FA'
      UNION ALL SELECT 'V_NDA',   'V_NCA'
      UNION ALL SELECT 'V_NCA_B', 'V_FB'
      UNION ALL SELECT 'V_NCA_B', 'V_NDA_B'
      UNION ALL SELECT 'V_NDA_B', 'V_FB'
      UNION ALL SELECT 'V_NDA_B', 'V_NCA_B'
      UNION ALL SELECT 'V_NCX',   'V_FX'
      UNION ALL SELECT 'V_NCX',   'V_NDX'
      UNION ALL SELECT 'V_NDX',   'V_FX'
      UNION ALL SELECT 'V_NDX',   'V_NCX'
      UNION ALL SELECT 'C_NCA',   'C_FA'
      UNION ALL SELECT 'C_NCA',   'C_NDA'
      UNION ALL SELECT 'C_NDA',   'C_FA'
      UNION ALL SELECT 'C_NDA',   'C_NCA') m
JOIN gestion__comprobantes_tipos d ON d.codigo COLLATE utf8mb4_unicode_ci = m.hijo  AND d.empresa_id = 2
JOIN gestion__comprobantes_tipos p ON p.codigo COLLATE utf8mb4_unicode_ci = m.padre AND p.empresa_id = d.empresa_id;

-- Verificación: deben quedar 16 filas para la empresa 2.
SELECT ct.codigo AS dependiente, cp.codigo AS padre
FROM gestion__comprobantes_tipos_dependencias ctd
JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = ctd.comprobante_tipo_id
JOIN gestion__comprobantes_tipos cp ON cp.comprobante_tipo_id = ctd.comprobante_tipo_padre_id
WHERE ctd.empresa_id = 2
ORDER BY ct.codigo, cp.codigo;
