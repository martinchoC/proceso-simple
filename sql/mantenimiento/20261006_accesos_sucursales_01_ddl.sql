-- =====================================================================
-- Accesos por perfil a Sucursal / Boca / Punto de Venta / Tipo de comprobante
-- ETAPA 1 — Esquema + semilla
-- Fecha: 2026-10-06
--
-- Reglas de negocio acordadas:
--   1. Granularidad: sucursal > boca > punto de venta > tipo de comprobante.
--   2. Sin niveles: si tiene acceso, consulta y opera.
--   3. Todos los perfiles del módulo Gestión se restringen (incluido Cliente Mayorista).
--   4. Denegado por defecto: sin fila en gestion__perfiles_sucursales = sin acceso.
--      Una fila con sucursal_id NULL habilita TODA la empresa.
--   5. Implementación por etapas: este script NO cambia el comportamiento de
--      ninguna pantalla todavía (nadie consulta estas tablas hasta la etapa 3).
--
-- Las tablas anteriores (vacías, sin referencias entrantes) se renombran con
-- sufijo _bak20261006 en lugar de borrarse. Eliminar a mano cuando se valide.
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1) Resguardo de las versiones anteriores
-- ---------------------------------------------------------------------
RENAME TABLE `gestion__perfiles_sucursales`             TO `gestion__perfiles_sucursales_bak20261006`;
RENAME TABLE `gestion__usuarios_sucursales_excepciones` TO `gestion__usuarios_sucursales_excepciones_bak20261006`;

-- ---------------------------------------------------------------------
-- 2) Tabla transversal de accesos por perfil
--    Nivel del acceso = la columna más específica no NULL:
--      sucursal_id NULL                  -> toda la empresa
--      sucursal_id                       -> toda la sucursal (todas sus bocas/PV/tipos)
--      sucursal_id + boca_id             -> toda la boca
--      ... + punto_venta_id              -> ese PV (todos sus tipos habilitados)
--      ... + comprobante_tipo_id         -> solo ese tipo en ese PV
--    La coherencia de la cadena (boca pertenece a sucursal, PV a boca, tipo
--    habilitado en PV, todo de la misma empresa) la valida el _model.php.
-- ---------------------------------------------------------------------
CREATE TABLE `gestion__perfiles_sucursales` (
  `perfil_sucursal_id`       INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  `empresa_id`               SMALLINT UNSIGNED NOT NULL,
  `empresa_perfil_id`        INT(11) UNSIGNED  NOT NULL,
  `sucursal_id`              SMALLINT UNSIGNED NULL DEFAULT NULL COMMENT 'NULL = todas las sucursales de la empresa',
  `boca_id`                  INT UNSIGNED      NULL DEFAULT NULL COMMENT 'NULL = todas las bocas de la sucursal',
  `punto_venta_id`           SMALLINT UNSIGNED NULL DEFAULT NULL COMMENT 'NULL = todos los PV del nivel superior',
  `comprobante_tipo_id`      INT UNSIGNED      NULL DEFAULT NULL COMMENT 'NULL = todos los tipos habilitados en el PV',
  `es_predeterminado`        TINYINT(1)        NOT NULL DEFAULT 0 COMMENT 'Se preselecciona en los combos',
  -- Claves normalizadas: en un UNIQUE, NULL <> NULL y permitiría duplicados
  `k_sucursal`               SMALLINT UNSIGNED AS (IFNULL(`sucursal_id`, 0)) PERSISTENT,
  `k_boca`                   INT UNSIGNED      AS (IFNULL(`boca_id`, 0)) PERSISTENT,
  `k_punto_venta`            SMALLINT UNSIGNED AS (IFNULL(`punto_venta_id`, 0)) PERSISTENT,
  `k_comprobante_tipo`       INT UNSIGNED      AS (IFNULL(`comprobante_tipo_id`, 0)) PERSISTENT,
  `tabla_estado_registro_id` SMALLINT(4)       NOT NULL DEFAULT 1,
  `usuario_creacion_id`      INT NULL DEFAULT NULL,
  `fecha_creacion`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `usuario_actualizacion_id` INT NULL DEFAULT NULL,
  `fecha_actualizacion`      DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`perfil_sucursal_id`),
  UNIQUE KEY `uk_ps_grant` (`empresa_perfil_id`, `k_sucursal`, `k_boca`, `k_punto_venta`, `k_comprobante_tipo`),
  KEY `idx_ps_empresa_perfil` (`empresa_id`, `empresa_perfil_id`, `tabla_estado_registro_id`),
  KEY `idx_ps_sucursal` (`sucursal_id`),
  KEY `idx_ps_boca` (`boca_id`),
  KEY `idx_ps_pv` (`punto_venta_id`),
  KEY `idx_ps_tipo` (`comprobante_tipo_id`),
  CONSTRAINT `fk_ps_empresa_perfil` FOREIGN KEY (`empresa_perfil_id`)   REFERENCES `conf__empresas_perfiles` (`empresa_perfil_id`),
  CONSTRAINT `fk_ps_sucursal`       FOREIGN KEY (`sucursal_id`)         REFERENCES `gestion__sucursales` (`sucursal_id`),
  CONSTRAINT `fk_ps_boca`           FOREIGN KEY (`boca_id`)             REFERENCES `gestion__bocas` (`boca_id`),
  CONSTRAINT `fk_ps_pv`             FOREIGN KEY (`punto_venta_id`)      REFERENCES `gestion__puntos_venta` (`punto_venta_id`),
  CONSTRAINT `fk_ps_tipo`           FOREIGN KEY (`comprobante_tipo_id`) REFERENCES `gestion__comprobantes_tipos` (`comprobante_tipo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Accesos por perfil a sucursal/boca/PV/tipo. Sin fila = sin acceso.';

-- ---------------------------------------------------------------------
-- 3) Excepciones puntuales por usuario (PERMITE suma, DENIEGA siempre gana)
-- ---------------------------------------------------------------------
CREATE TABLE `gestion__usuarios_sucursales_excepciones` (
  `usuario_excepcion_id`     INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  `empresa_id`               SMALLINT UNSIGNED NOT NULL,
  `usuario_id`               INT(11)           NOT NULL,
  `sucursal_id`              SMALLINT UNSIGNED NULL DEFAULT NULL COMMENT 'NULL = todas las sucursales de la empresa',
  `boca_id`                  INT UNSIGNED      NULL DEFAULT NULL,
  `punto_venta_id`           SMALLINT UNSIGNED NULL DEFAULT NULL,
  `comprobante_tipo_id`      INT UNSIGNED      NULL DEFAULT NULL,
  `tipo_excepcion`           ENUM('PERMITE','DENIEGA') NOT NULL,
  `fecha_desde`              DATE NOT NULL,
  `fecha_hasta`              DATE NULL DEFAULT NULL COMMENT 'NULL = sin vencimiento',
  `motivo`                   VARCHAR(255) NULL DEFAULT NULL,
  `tabla_estado_registro_id` SMALLINT(4) NOT NULL DEFAULT 1,
  `usuario_creacion_id`      INT NULL DEFAULT NULL,
  `fecha_creacion`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `usuario_actualizacion_id` INT NULL DEFAULT NULL,
  `fecha_actualizacion`      DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`usuario_excepcion_id`),
  KEY `idx_use_usuario_vigencia` (`empresa_id`, `usuario_id`, `fecha_desde`, `fecha_hasta`),
  KEY `idx_use_sucursal` (`sucursal_id`),
  KEY `idx_use_boca` (`boca_id`),
  KEY `idx_use_pv` (`punto_venta_id`),
  KEY `idx_use_tipo` (`comprobante_tipo_id`),
  CONSTRAINT `ck_use_vigencia` CHECK (`fecha_hasta` IS NULL OR `fecha_hasta` >= `fecha_desde`),
  CONSTRAINT `fk_use_sucursal` FOREIGN KEY (`sucursal_id`)         REFERENCES `gestion__sucursales` (`sucursal_id`),
  CONSTRAINT `fk_use_boca`     FOREIGN KEY (`boca_id`)             REFERENCES `gestion__bocas` (`boca_id`),
  CONSTRAINT `fk_use_pv`       FOREIGN KEY (`punto_venta_id`)      REFERENCES `gestion__puntos_venta` (`punto_venta_id`),
  CONSTRAINT `fk_use_tipo`     FOREIGN KEY (`comprobante_tipo_id`) REFERENCES `gestion__comprobantes_tipos` (`comprobante_tipo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Excepciones por usuario sobre accesos de sucursal. DENIEGA prevalece.';

-- ---------------------------------------------------------------------
-- 4) Semilla: los perfiles "Administrador" del módulo Gestión (perfil base 3)
--    reciben acceso a toda la empresa (sucursal_id NULL), para que al activar
--    la restricción en la etapa 3 no queden bloqueados. El resto de los
--    perfiles (Administrativo 1, Pedidos, Cliente Mayorista, ...) quedan SIN
--    acceso hasta que se los configure desde la pantalla de la etapa 2.
-- ---------------------------------------------------------------------
INSERT INTO `gestion__perfiles_sucursales` (`empresa_id`, `empresa_perfil_id`, `usuario_creacion_id`)
SELECT ep.empresa_id, ep.empresa_perfil_id, 1
FROM conf__empresas_perfiles ep
WHERE ep.modulo_id = 2
  AND ep.perfil_id_base = 3
  AND ep.tabla_estado_registro_id = 1
  AND NOT EXISTS (
      SELECT 1 FROM gestion__perfiles_sucursales ps
      WHERE ps.empresa_perfil_id = ep.empresa_perfil_id AND ps.k_sucursal = 0
        AND ps.k_boca = 0 AND ps.k_punto_venta = 0 AND ps.k_comprobante_tipo = 0
  );

-- Verificación
SELECT ps.perfil_sucursal_id, ps.empresa_id, ep.empresa_perfil_nombre,
       IFNULL(ps.sucursal_id, 'TODAS') AS sucursal
FROM gestion__perfiles_sucursales ps
INNER JOIN conf__empresas_perfiles ep ON ep.empresa_perfil_id = ps.empresa_perfil_id;
