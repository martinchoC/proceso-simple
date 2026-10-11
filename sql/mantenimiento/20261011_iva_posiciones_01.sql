-- =====================================================================================================
-- Dashboard de IVA y posición mensual - script 01: ESTRUCTURA, CUENTAS, PÁGINAS, FUNCIONES Y PERMISOS
-- -----------------------------------------------------------------------------------------------------
-- Página "IVA - Posición Mensual" (menú Tesorería): IVA Ventas, IVA Compras, percepciones y retenciones por
-- mes, y la liquidación de la posición del período:
--   saldo = débito fiscal - crédito fiscal - percepciones IVA sufridas - retenciones IVA sufridas - saldo a favor anterior
--   > 0: genera la I_LIQ (obligación impositiva 7 "IVA - Posición mensual", se paga desde Obligaciones y Rendiciones)
--   <= 0: queda como saldo a favor para el período siguiente (sin comprobante).
--   Asiento de liquidación (en los dos casos): Debe IVA débito fiscal; Haber IVA crédito fiscal (por alícuota),
--   percepciones y retenciones de IVA, saldo a favor anterior; contrapartida IVA a pagar / IVA saldo a favor.
--
--   1) gestion__iva_posiciones (una vigente (5) por empresa y período; 38 = anulada)
--   2) conf__tablas 435 (Proceso Corto) y estados 224 Liquidada (5) / 225 Anulada (38)
--   3) cuenta 1.1.03.10 "IVA Saldo a Favor" (hermana de 1.1.03.06 Retenciones de IVA) y concepto contable
--      IVA_SALDO_FAVOR, por cada empresa con ese plan (Casa Lucho y la demo clonada); el resto la configura en
--      Tesorería > Conceptos Contables
--   4) conf__paginas 117 (módulo 2, padre 100) y 617 (módulo 5, padre 600): iva_posiciones.php
--   5) conf__paginas_funciones: 3372 / 9042 Liquidar (0 -> 5), 3373 / 9043 Anular (5 -> 38)
--      (8372 / 8373 ya ocupados: el módulo 5 usa 9042 / 9043)
--   6) permisos: copiados de "Liquidar" de Percepciones IIBB (3370 / 8370)
-- Idempotente. Corta si los ids están ocupados por otra cosa. MariaDB 10.4 y MySQL 8.
-- =====================================================================================================

CREATE TABLE IF NOT EXISTS gestion__iva_posiciones (
    iva_posicion_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    empresa_id INT NOT NULL,
    periodo CHAR(7) NOT NULL COMMENT 'AAAA-MM',
    debito_fiscal DECIMAL(18,2) NOT NULL DEFAULT 0,
    credito_fiscal DECIMAL(18,2) NOT NULL DEFAULT 0,
    percepciones_iva DECIMAL(18,2) NOT NULL DEFAULT 0,
    retenciones_iva DECIMAL(18,2) NOT NULL DEFAULT 0,
    saldo_favor_anterior DECIMAL(18,2) NOT NULL DEFAULT 0,
    saldo_a_pagar DECIMAL(18,2) NOT NULL DEFAULT 0,
    saldo_a_favor DECIMAL(18,2) NOT NULL DEFAULT 0,
    comprobante_id INT NULL COMMENT 'I_LIQ generada cuando da a pagar',
    cont_asiento_id INT UNSIGNED NULL,
    observaciones VARCHAR(255) NULL,
    motivo_anulacion VARCHAR(255) NULL,
    tabla_estado_registro_id SMALLINT NOT NULL DEFAULT 5,
    usuario_id INT NULL,
    usuario_modificacion_id INT NULL,
    fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (iva_posicion_id),
    KEY idx_iva_pos_empresa_periodo (empresa_id, periodo, tabla_estado_registro_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Posición mensual de IVA liquidada';

DROP PROCEDURE IF EXISTS `_iva01_assert`;
DELIMITER $$
CREATE PROCEDURE `_iva01_assert`(IN p_ok INT, IN p_msg VARCHAR(255) CHARACTER SET utf8mb4)
BEGIN
    IF p_ok IS NULL OR p_ok = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = p_msg;
    END IF;
END$$
DELIMITER ;

CALL `_iva01_assert`((SELECT COUNT(*) FROM conf__tablas WHERE tabla_id = 435 AND tabla_nombre <> 'gestion__iva_posiciones') = 0,
                     'conf__tablas: el id 435 está ocupado por otra tabla');
CALL `_iva01_assert`((SELECT COUNT(*) FROM conf__tablas_estados_registros WHERE tabla_estado_registro_id IN (224, 225) AND tabla_id <> 435) = 0,
                     'conf__tablas_estados_registros: los ids 224 / 225 están ocupados');
CALL `_iva01_assert`((SELECT COUNT(*) FROM conf__paginas WHERE pagina_id IN (117, 617) AND url <> 'iva_posiciones.php') = 0,
                     'conf__paginas: los ids 117 / 617 están ocupados por otra página');
CALL `_iva01_assert`((SELECT COUNT(*) FROM conf__paginas_funciones
                      WHERE (pagina_funcion_id IN (3372, 3373) AND pagina_id <> 117)
                         OR (pagina_funcion_id IN (9042, 9043) AND pagina_id <> 617)) = 0,
                     'conf__paginas_funciones: los ids 3372 / 3373 / 9042 / 9043 están ocupados');
CALL `_iva01_assert`((SELECT COUNT(*) FROM conf__paginas WHERE pagina_id IN (100, 600)) = 2,
                     'conf__paginas: falta el menú Tesorería (100 / 600)');

-- 2) Tabla y estados
INSERT INTO conf__tablas (tabla_id, tabla_nombre, tabla_descripcion, modulo_id, tabla_tipo_id, tabla_patron_id, tabla_estado_registro_id)
SELECT 435, 'gestion__iva_posiciones', 'Posición mensual de IVA liquidada', 2, 2, 1, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM conf__tablas WHERE tabla_id = 435);

INSERT INTO conf__tablas_estados_registros
    (tabla_estado_registro_id, tabla_id, estado_registro_id, tabla_estado_registro, color_id, es_inicial, es_final, orden, aplica_stock)
SELECT v.id, 435, v.estado, v.nombre, v.color, v.inicial, v.final, v.orden, 0
FROM (SELECT 224 AS id, 5 AS estado, 'Liquidada' AS nombre, 3 AS color, 1 AS inicial, 0 AS final, 10 AS orden
      UNION ALL SELECT 225, 38, 'Anulada', 2, 0, 1, 90) v
WHERE NOT EXISTS (SELECT 1 FROM conf__tablas_estados_registros x WHERE x.tabla_estado_registro_id = v.id);

-- 3) Cuenta y concepto IVA Saldo a Favor
INSERT INTO gestion__cont_cuentas
    (empresa_id, comprobante_id, codigo, nombre, naturaleza, cuenta_padre_id, nivel, orden, es_imputable, es_caja_banco, tabla_estado_registro_id)
SELECT r.empresa_id, 0, '1.1.03.10', 'IVA Saldo a Favor', 'D', r.cuenta_padre_id, r.nivel, 10, 1, 0, 1
FROM gestion__cont_cuentas r
WHERE r.codigo = '1.1.03.06' AND r.tabla_estado_registro_id = 1
  AND NOT EXISTS (SELECT 1 FROM gestion__cont_cuentas x WHERE x.empresa_id = r.empresa_id AND x.codigo = '1.1.03.10');

INSERT INTO gestion__empresas_cuentas_conceptos (empresa_id, concepto, cont_cuenta_id, tabla_estado_registro_id)
SELECT c.empresa_id, 'IVA_SALDO_FAVOR', c.cont_cuenta_id, 1
FROM gestion__cont_cuentas c
WHERE c.codigo = '1.1.03.10' AND c.nombre = 'IVA Saldo a Favor' AND c.tabla_estado_registro_id = 1
  AND NOT EXISTS (SELECT 1 FROM gestion__empresas_cuentas_conceptos x WHERE x.empresa_id = c.empresa_id AND x.concepto = 'IVA_SALDO_FAVOR');

-- 4) Páginas
INSERT INTO conf__paginas
    (pagina_id, modulo_id, pagina, url, icono_id, pagina_descripcion, padre_id, orden, tabla_id,
     pagina_patron_id, tabla_estado_registro_id, es_acceso_directo, orden_acceso_directo)
SELECT v.pagina_id, v.modulo_id, 'IVA - Posición Mensual', 'iva_posiciones.php', 70,
       'IVA Ventas, IVA Compras, percepciones y retenciones por mes; liquidación de la posición mensual de IVA',
       v.padre_id, 53, 435, 1, 1, 0, NULL
FROM (SELECT 117 AS pagina_id, 2 AS modulo_id, 100 AS padre_id UNION ALL SELECT 617, 5, 600) v
WHERE NOT EXISTS (SELECT 1 FROM conf__paginas p WHERE p.pagina_id = v.pagina_id);

-- 5) Funciones
INSERT INTO conf__paginas_funciones
    (pagina_funcion_id, pagina_id, icono_id, color_id, funcion_estandar_id, nombre_funcion, accion_js,
     descripcion, tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, orden, tabla_estado_registro_id)
SELECT v.id, v.pagina_id, v.icono_id, v.color_id, 1, v.nombre, v.accion_js, v.descripcion, v.origen, v.destino, v.orden, 1
FROM (          SELECT 3372 AS id, 117 AS pagina_id, 42 AS icono_id, 3 AS color_id, 'Liquidar' AS nombre, 'liquidar' AS accion_js,
                       'Liquidar la posición de IVA del período (I_LIQ si da a pagar)' AS descripcion, 0 AS origen, 5 AS destino, 1 AS orden
      UNION ALL SELECT 3373, 117, 45, 4, 'Anular', 'anular', 'Anular la liquidación del período (si la I_LIQ no está pagada)', 5, 38, 2
      UNION ALL SELECT 9042, 617, 42, 3, 'Liquidar', 'liquidar', 'Liquidar la posición de IVA del período (I_LIQ si da a pagar)', 0, 5, 1
      UNION ALL SELECT 9043, 617, 45, 4, 'Anular', 'anular', 'Anular la liquidación del período (si la I_LIQ no está pagada)', 5, 38, 2) v
WHERE NOT EXISTS (SELECT 1 FROM conf__paginas_funciones x WHERE x.pagina_funcion_id = v.id);

-- 6) Permisos: los de "Liquidar" de Percepciones IIBB (3370 módulo 2 / 8370 módulo 5)
INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT p.perfil_id, n.id, 1
FROM (SELECT 3372 AS id, 3370 AS origen UNION ALL SELECT 3373, 3370 UNION ALL SELECT 9042, 8370 UNION ALL SELECT 9043, 8370) n
JOIN conf__perfiles_funciones p ON p.pagina_funcion_id = n.origen AND p.asignado = 1
WHERE NOT EXISTS (SELECT 1 FROM conf__perfiles_funciones x WHERE x.perfil_id = p.perfil_id AND x.pagina_funcion_id = n.id);

INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado)
SELECT DISTINCT p.empresa_id, p.empresa_perfil_id, n.id, 1
FROM (SELECT 3372 AS id, 3370 AS origen UNION ALL SELECT 3373, 3370 UNION ALL SELECT 9042, 8370 UNION ALL SELECT 9043, 8370) n
JOIN conf__empresas_perfiles_funciones p ON p.pagina_funcion_id = n.origen AND p.asignado = 1
WHERE NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones x
                  WHERE x.empresa_perfil_id = p.empresa_perfil_id AND x.pagina_funcion_id = n.id);

DROP PROCEDURE IF EXISTS `_iva01_assert`;

-- Control (solo lectura)
SELECT p.pagina_id, p.modulo_id, p.pagina, p.url, p.padre_id,
       (SELECT COUNT(*) FROM conf__paginas_funciones f WHERE f.pagina_id = p.pagina_id) AS funciones,
       (SELECT COUNT(DISTINCT e.empresa_perfil_id) FROM conf__empresas_perfiles_funciones e
          JOIN conf__paginas_funciones f ON f.pagina_funcion_id = e.pagina_funcion_id
         WHERE f.pagina_id = p.pagina_id AND e.asignado = 1) AS perfiles_con_acceso,
       (SELECT COUNT(*) FROM gestion__empresas_cuentas_conceptos c WHERE c.concepto = 'IVA_SALDO_FAVOR') AS empresas_con_saldo_favor
FROM conf__paginas p WHERE p.pagina_id IN (117, 617);
