-- =====================================================================
-- Tesorería - script 02: SIEMBRA POR EMPRESA (subgrupos, tipos de
-- comprobante, reglas de cancelación y marca de agente de retención)
-- 2026-10-03 · generado por disenador-modelo-datos · NO EJECUTADO
-- Revisión 4 (2026-10-03). Especificación funcional vigente:
--   docs/matriz-legal/08_proc_tesoreria_v3_6.docx (decisiones D1-D31).
-- =====================================================================
-- ORDEN DE EJECUCIÓN (tres archivos con el mismo prefijo 20261003_tesoreria_):
--   01_ddl.sql                 UNA VEZ por base (estructura + datos globales).
--   02_siembra_empresa.sql     ESTE: una vez POR EMPRESA que use tesorería.
--   03_datos_iniciales.sql     por empresa, después de este.
--
-- REQUISITOS
--   - Base seleccionada con USE (no hay USE en el script).
--   - 20261003_tesoreria_01_ddl.sql ya ejecutado en esta base (se verifica
--     al inicio: tablas registradas en conf__tablas, tipos de impuesto de
--     retención y columna es_liquidable).
--   - Producción: 20260927_sync_local_a_produccion.sql ya ejecutado (crea
--     gestion__comprobantes_subgrupos.origen_asiento; se verifica).
--   - La empresa debe tener: grupo de comprobantes 'Caja/Bancos'
--     (gestion__comprobantes_grupos) y tipo de asiento 'AUT'
--     (gestion__cont_tipos_asientos, D27). Se verifica y, si falta, el
--     script se corta con un mensaje (no los inventa).
--   - Privilegios: SELECT, INSERT, UPDATE, CREATE ROUTINE y ALTER ROUTINE
--     (crea y borra los procedimientos helper _tes_*).
--
-- PARÁMETROS (editar el bloque "PARÁMETROS" de abajo antes de correr)
--   @tes_empresa_id          empresa a sembrar (conf__empresas.empresa_id).
--                            Viene en NULL a propósito: si no se edita, el
--                            script se corta en el primer control.
--   @tes_grupo_caja_nombre   grupo de comprobantes de tesorería ('Caja/Bancos').
--   @tes_tipo_asiento_codigo tipo de asiento de los subgrupos (D27: 'AUT').
--   @tes_jurisdiccion_iibb   jurisdicción de la retención de IIBB
--                            practicada ('ARBA' para Casa Lucho).
--
-- IDEMPOTENCIA (D30: correr una vez por empresa; repetirlo para la misma
-- empresa no duplica nada):
--   - Ningún id fijo: empresa, grupo, tipo de asiento, tablas, estados
--     genéricos (ACTIVO / INACTIVO por codigo_estandar), tipos de comprobante
--     e impuestos se resuelven por código o nombre.
--   - Subgrupo: se busca por (empresa, grupo, nombre); si existe no se crea
--     otro (solo se completan tabla_id = 0, origen_asiento 'NINGUNO' y
--     tipo_asiento_id NULL, sin pisar otra configuración).
--   - Tipo de comprobante: se busca por (empresa, codigo); si existe solo se
--     le asigna el subgrupo cuando lo tiene en 0.
--   - Vínculo tabla-subgrupo, reglas de cancelación y filas de agente de
--     retención: INSERT ... WHERE NOT EXISTS (+ UNIQUE donde lo hay).
--   - Si el usuario RENOMBRA un subgrupo después, volver a correr crea uno
--     nuevo con el nombre original: no renombrar antes de terminar la
--     siembra de todas las empresas, o ajustar los nombres acá.
--   - Los datos son DML sobre tablas InnoDB y van dentro de una transacción
--     explícita (§1-§3): si algo falla, ROLLBACK manual y volver a correr.
--     Los helpers (CREATE/DROP PROCEDURE) quedan fuera de la transacción.
--
-- DECISIONES DE ESTA SIEMBRA
--   - D27: todos los subgrupos de tesorería con origen_asiento = 'AUTO' y
--     tipo_asiento_id = AUT de la empresa (resuelto por codigo). Hoy ningún
--     código lee gestion__comprobantes_subgrupos.tipo_asiento_id ni
--     gestion__cont_tipos_asientos.modulo_origen (AUT tiene 'ventas'): no se
--     toca modulo_origen. Ver informe de la revisión 4.
--   - D28: tipo CHP_RECH (rechazo de cheque propio) en el subgrupo
--     'Operaciones con Cheques' (tabla gestion__cheques_operaciones), signo
--     '+', impacta ctacte y contabilidad; regla OP -> CHP_RECH.
--   - D21: filas de agente de retención INACTIVAS (el contador las activa).
--
-- Qué hace:
--   0) Controles previos (asserts) y resolución de ids por código.
--   1) Subgrupos y tipos de comprobante de tesorería.
--   2) Reglas de cancelación (gestion__comprobantes_tipos_cancelaciones).
--   3) Marca de agente de retención (filas inactivas).
--   V) Verificación (solo lectura).
-- NO genera conf__paginas ni conf__paginas_funciones (orquestador-dev) ni
-- habilita tipos en puntos de venta (eso es el 03, D29).
-- =====================================================================

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

SET @tes_old_sql_mode = @@SESSION.sql_mode;
-- Válido en MariaDB 10.4 y MySQL 8.0 (sin NO_AUTO_CREATE_USER). Si el script
-- se corta a mitad: SET SESSION sql_mode = @tes_old_sql_mode;
SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- =====================================================================
-- PARÁMETROS — EDITAR ANTES DE CORRER
-- =====================================================================
SET @tes_empresa_id          = NULL;          -- <<< EDITAR (p. ej. 2 = Casa Lucho)
SET @tes_grupo_caja_nombre   = 'Caja/Bancos';
SET @tes_tipo_asiento_codigo = 'AUT';         -- D27
SET @tes_jurisdiccion_iibb   = 'ARBA';        -- jurisdiccion_codigo de la retención IIBB practicada

-- =====================================================================
-- Helpers temporales (se eliminan al final)
-- =====================================================================
DROP PROCEDURE IF EXISTS `_tes_assert`;
DROP PROCEDURE IF EXISTS `_tes_subgrupo`;
DROP PROCEDURE IF EXISTS `_tes_tipo`;

DELIMITER $$

CREATE PROCEDURE `_tes_assert`(IN p_ok INT, IN p_msg VARCHAR(255) CHARACTER SET utf8mb4)
BEGIN
    IF p_ok IS NULL OR p_ok = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = p_msg;
    END IF;
END$$

-- Busca el subgrupo por (empresa, grupo, nombre); si no existe lo crea con
-- origen_asiento = 'AUTO' y tipo_asiento_id = p_tipo_asiento_id (D27). Si
-- existe: completa tabla_id (solo si está en 0), pasa origen_asiento de
-- 'NINGUNO' a 'AUTO' y completa tipo_asiento_id si es NULL (no pisa otra
-- configuración). Vincula la tabla operativa en
-- conf__tablas_comprobantes_subgrupos. Deja el id en @tes_subgrupo_id.
CREATE PROCEDURE `_tes_subgrupo`(
    IN p_empresa_id INT,
    IN p_grupo_id INT,
    IN p_nombre VARCHAR(100) CHARACTER SET utf8mb4,
    IN p_codigo VARCHAR(10) CHARACTER SET utf8mb4,
    IN p_orden INT,
    IN p_tabla VARCHAR(50) CHARACTER SET utf8mb4,
    IN p_tipo_asiento_id INT,
    IN p_estado_activo INT)
BEGIN
    DECLARE v_tabla_id INT;
    DECLARE v_subgrupo_id INT;
    DECLARE v_msg VARCHAR(255);

    SET v_tabla_id = (SELECT tabla_id FROM conf__tablas
                      WHERE tabla_nombre = p_tabla COLLATE utf8mb4_unicode_ci
                      ORDER BY tabla_id LIMIT 1);
    IF v_tabla_id IS NULL THEN
        SET v_msg = CONCAT('Tabla no registrada en conf__tablas (correr el 01): ', p_tabla);
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
    END IF;

    SET v_subgrupo_id = (SELECT comprobante_subgrupo_id FROM gestion__comprobantes_subgrupos
                         WHERE empresa_id = p_empresa_id
                           AND comprobante_grupo_id = p_grupo_id
                           AND comprobante_subgrupo = p_nombre COLLATE utf8mb4_unicode_ci
                         ORDER BY comprobante_subgrupo_id LIMIT 1);

    IF v_subgrupo_id IS NULL THEN
        INSERT INTO gestion__comprobantes_subgrupos
            (empresa_id, comprobante_grupo_id, tabla_id, comprobante_subgrupo, codigo, comentario,
             orden, tabla_estado_registro_id, tipo_asiento_id, origen_asiento)
        VALUES (p_empresa_id, p_grupo_id, v_tabla_id, p_nombre, p_codigo, 'Tesoreria',
                p_orden, p_estado_activo, p_tipo_asiento_id, 'AUTO');
        SET v_subgrupo_id = LAST_INSERT_ID();
    ELSE
        UPDATE gestion__comprobantes_subgrupos
           SET tabla_id = IF(tabla_id = 0, v_tabla_id, tabla_id),
               origen_asiento = IF(origen_asiento = 'NINGUNO', 'AUTO', origen_asiento),
               tipo_asiento_id = IFNULL(tipo_asiento_id, p_tipo_asiento_id)
         WHERE comprobante_subgrupo_id = v_subgrupo_id;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM conf__tablas_comprobantes_subgrupos
                   WHERE tabla_id = v_tabla_id AND empresa_id = p_empresa_id
                     AND comprobante_subgrupo_id = v_subgrupo_id) THEN
        INSERT INTO conf__tablas_comprobantes_subgrupos
            (tabla_id, empresa_id, comprobante_subgrupo_id, tabla_estado_registro_id)
        VALUES (v_tabla_id, p_empresa_id, v_subgrupo_id, p_estado_activo);
    END IF;

    SET @tes_subgrupo_id = v_subgrupo_id;
END$$

-- Alta de tipo de comprobante por (empresa, codigo). Si ya existe (RCX, OP,
-- TB, DB) solo le asigna el subgrupo cuando lo tiene en 0; no pisa nada más
-- (tampoco el signo: D3, los signos de RCX y OP son los del tipo existente).
CREATE PROCEDURE `_tes_tipo`(
    IN p_empresa_id INT,
    IN p_grupo_id INT,
    IN p_subgrupo_id INT,
    IN p_codigo VARCHAR(10) CHARACTER SET utf8mb4,
    IN p_nombre VARCHAR(100) CHARACTER SET utf8mb4,
    IN p_signo VARCHAR(3) CHARACTER SET utf8mb4,
    IN p_impacta_contabilidad TINYINT,
    IN p_impacta_ctacte TINYINT,
    IN p_orden INT,
    IN p_estado_activo INT)
BEGIN
    IF EXISTS (SELECT 1 FROM gestion__comprobantes_tipos
               WHERE empresa_id = p_empresa_id AND codigo = p_codigo COLLATE utf8mb4_unicode_ci) THEN
        UPDATE gestion__comprobantes_tipos
           SET comprobante_subgrupo_id = p_subgrupo_id
         WHERE empresa_id = p_empresa_id
           AND codigo = p_codigo COLLATE utf8mb4_unicode_ci
           AND comprobante_subgrupo_id = 0;
    ELSE
        INSERT INTO gestion__comprobantes_tipos
            (empresa_id, comprobante_grupo_id, comprobante_subgrupo_id, comprobante_fiscal_id,
             impacta_stock, impacta_contabilidad, impacta_ctacte, comprobante_tipo, codigo, letra,
             signo, comentario, orden, tabla_estado_registro_id)
        VALUES (p_empresa_id, p_grupo_id, p_subgrupo_id, 0,
                0, p_impacta_contabilidad, p_impacta_ctacte, p_nombre, p_codigo, NULL,
                p_signo, p_nombre, p_orden, p_estado_activo);
    END IF;
END$$

DELIMITER ;

-- =====================================================================
-- 0) CONTROLES PREVIOS Y RESOLUCIÓN DE IDS POR CÓDIGO
-- =====================================================================
CALL `_tes_assert`(DATABASE() IS NOT NULL, 'Seleccionar la base (USE ...) antes de correr el script');

CALL `_tes_assert`(@tes_empresa_id IS NOT NULL,
    'Editar el bloque PARAMETROS: @tes_empresa_id esta en NULL');

CALL `_tes_assert`(
    (SELECT COUNT(*) FROM conf__empresas WHERE empresa_id = @tes_empresa_id) = 1,
    'La empresa @tes_empresa_id no existe en conf__empresas');

-- El 01 ya corrió: las 19 tablas de tesorería registradas en conf__tablas,
-- los 4 tipos de impuesto de retención practicada y la columna es_liquidable.
CALL `_tes_assert`(
    (SELECT COUNT(DISTINCT tabla_nombre) FROM conf__tablas
     WHERE tabla_nombre IN ('conf__bancos', 'gestion__tesoreria_cuentas', 'gestion__medios_pago',
                            'gestion__categorias_gasto', 'gestion__comprobantes_tipos_cancelaciones',
                            'gestion__cobranzas', 'gestion__pagos', 'gestion__pagos_impuestos',
                            'gestion__transferencias', 'gestion__movimientos_caja',
                            'gestion__cheques_operaciones', 'gestion__rendiciones_anticipos',
                            'gestion__rendiciones_reembolsos', 'gestion__comprobantes_imputaciones',
                            'gestion__tesoreria_movimientos', 'gestion__rendiciones_items',
                            'gestion__obligaciones_impositivas', 'gestion__rendiciones',
                            'gestion__cheques')) = 19,
    'Falta correr 20261003_tesoreria_01_ddl.sql (tablas de tesoreria no registradas en conf__tablas)');

CALL `_tes_assert`(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__impuestos_tipos'
       AND COLUMN_NAME = 'es_liquidable') = 1
    AND
    (SELECT COUNT(*) FROM gestion__impuestos_tipos
     WHERE impuesto_tipo IN ('Retencion Ganancias practicada', 'Retencion IVA practicada',
                             'Retencion IIBB practicada', 'Retencion SUSS practicada')) = 4,
    'Falta correr 20261003_tesoreria_01_ddl.sql (impuestos de retencion practicada / es_liquidable)');

-- Producción: la columna la crea 20260927_sync_local_a_produccion.sql.
CALL `_tes_assert`(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__comprobantes_subgrupos'
       AND COLUMN_NAME = 'origen_asiento') = 1,
    'Falta gestion__comprobantes_subgrupos.origen_asiento: correr antes 20260927_sync_local_a_produccion.sql');

-- Estados GENÉRICOS (conf__estados_registros): la convención del código es
-- guardar en tabla_estado_registro_id el id genérico (ACTIVO / INACTIVO en
-- diccionarios), ver facturas_proveedores_model.php:363.
SET @tes_er_activo   = (SELECT estado_registro_id FROM conf__estados_registros
                        WHERE codigo_estandar = 'ACTIVO' ORDER BY estado_registro_id LIMIT 1);
SET @tes_er_inactivo = (SELECT estado_registro_id FROM conf__estados_registros
                        WHERE codigo_estandar = 'INACTIVO' ORDER BY estado_registro_id LIMIT 1);
CALL `_tes_assert`(@tes_er_activo IS NOT NULL AND @tes_er_inactivo IS NOT NULL,
    'No se resolvieron los estados genericos ACTIVO / INACTIVO en conf__estados_registros');

SET @tes_grp_caja = (SELECT comprobante_grupo_id FROM gestion__comprobantes_grupos
                     WHERE empresa_id = @tes_empresa_id
                       AND comprobante_grupo = @tes_grupo_caja_nombre COLLATE utf8mb4_unicode_ci
                     ORDER BY comprobante_grupo_id LIMIT 1);
CALL `_tes_assert`(@tes_grp_caja IS NOT NULL,
    'No existe el grupo de comprobantes de tesoreria (@tes_grupo_caja_nombre) para la empresa');

-- D27: tipo de asiento AUT de la empresa (no se crea uno de tesorería).
SET @tes_tipo_asiento_id = (SELECT cont_tipo_asiento_id FROM gestion__cont_tipos_asientos
                            WHERE empresa_id = @tes_empresa_id
                              AND codigo = @tes_tipo_asiento_codigo COLLATE utf8mb4_unicode_ci
                            ORDER BY cont_tipo_asiento_id LIMIT 1);
CALL `_tes_assert`(@tes_tipo_asiento_id IS NOT NULL,
    'D27: falta el tipo de asiento AUT de la empresa en gestion__cont_tipos_asientos (darlo de alta y volver a correr)');

-- Jurisdicciones de la marca de agente de retención (§3).
CALL `_tes_assert`(
    (SELECT COUNT(DISTINCT jurisdiccion_codigo) FROM gestion__jurisdicciones
     WHERE jurisdiccion_codigo IN ('AFIP', @tes_jurisdiccion_iibb)) = 2,
    'No existen las jurisdicciones AFIP y/o @tes_jurisdiccion_iibb en gestion__jurisdicciones');

-- =====================================================================
-- 1) SUBGRUPOS Y TIPOS DE COMPROBANTE
-- =====================================================================
-- Todo bajo el grupo de tesorería ('Caja/Bancos') de la empresa, un
-- subgrupo por tabla operativa (subgrupos.tabla_id es lo que usa
-- vfTiposPorPuntoVenta() para filtrar los tipos por página, y
-- conf__tablas_comprobantes_subgrupos vincula tabla y subgrupo).
-- Se reutilizan los subgrupos existentes 'Recibos de Cobranza',
-- 'Ordenes de Pago' y 'Transferencias' (en la empresa 2 sus códigos PCH/PEF
-- parecen cruzados, ver revisión §2.3 y Word v3.6 §9.1 fila 3: NO se tocan
-- acá, a confirmar por el usuario).
-- Todos los subgrupos de tesorería quedan con origen_asiento = 'AUTO' y
-- tipo_asiento_id = AUT de la empresa (D27).
START TRANSACTION;

-- Cobranzas: RCX (existente en la empresa 2, hoy sin subgrupo)
CALL `_tes_subgrupo`(@tes_empresa_id, @tes_grp_caja, 'Recibos de Cobranza', 'RCB', 5, 'gestion__cobranzas', @tes_tipo_asiento_id, @tes_er_activo);
CALL `_tes_tipo`(@tes_empresa_id, @tes_grp_caja, @tes_subgrupo_id, 'RCX', 'Recibo de Cobro', '+', 1, 1, 1, @tes_er_activo);

-- Pagos: OP (existente en la empresa 2, ya en 'Ordenes de Pago')
CALL `_tes_subgrupo`(@tes_empresa_id, @tes_grp_caja, 'Ordenes de Pago', 'OPG', 10, 'gestion__pagos', @tes_tipo_asiento_id, @tes_er_activo);
CALL `_tes_tipo`(@tes_empresa_id, @tes_grp_caja, @tes_subgrupo_id, 'OP', 'Orden de Pago', '-', 1, 1, 1, @tes_er_activo);

-- Transferencias: TB (existente en la empresa 2, hoy sin subgrupo)
CALL `_tes_subgrupo`(@tes_empresa_id, @tes_grp_caja, 'Transferencias', 'TRF', 3, 'gestion__transferencias', @tes_tipo_asiento_id, @tes_er_activo);
CALL `_tes_tipo`(@tes_empresa_id, @tes_grp_caja, @tes_subgrupo_id, 'TB', 'Transferencia', '+/-', 1, 0, 1, @tes_er_activo);

-- Movimientos de caja: MC (nuevo)
CALL `_tes_subgrupo`(@tes_empresa_id, @tes_grp_caja, 'Movimientos de Caja', 'MCJ', 20, 'gestion__movimientos_caja', @tes_tipo_asiento_id, @tes_er_activo);
CALL `_tes_tipo`(@tes_empresa_id, @tes_grp_caja, @tes_subgrupo_id, 'MC', 'Movimiento de Caja', '+/-', 1, 0, 400, @tes_er_activo);

-- Operaciones con cheques: DB (existente en la empresa 2, hoy sin subgrupo)
-- + ND_CHR (nuevo, ND interna NO fiscal al cliente por cheque rechazado/
-- devuelto; la cancela un RCX)
CALL `_tes_subgrupo`(@tes_empresa_id, @tes_grp_caja, 'Operaciones con Cheques', 'OCH', 25, 'gestion__cheques_operaciones', @tes_tipo_asiento_id, @tes_er_activo);
CALL `_tes_tipo`(@tes_empresa_id, @tes_grp_caja, @tes_subgrupo_id, 'DB', 'Deposito/Debito Bancario', '+/-', 1, 0, 1, @tes_er_activo);
CALL `_tes_tipo`(@tes_empresa_id, @tes_grp_caja, @tes_subgrupo_id, 'ND_CHR', 'Nota de Debito por Cheque Rechazado', '+', 1, 1, 410, @tes_er_activo);
-- D20/D25: devolución de endoso (ver 01 §7.8). Deuda reabierta con el proveedor; la cancela una OP futura.
CALL `_tes_tipo`(@tes_empresa_id, @tes_grp_caja, @tes_subgrupo_id, 'DEV_END', 'Devolucion de Endoso de Cheque', '+', 1, 1, 411, @tes_er_activo);
-- D28: rechazo de cheque PROPIO (Word v3.6 §2.1 y §4.7: "código sugerido,
-- a confirmar en el DDL"; se confirma CHP_RECH). Acreditación al proveedor:
-- signo '+', impacta ctacte y contabilidad, sin tesorería (RT10). La
-- cancela una OP futura (regla OP -> CHP_RECH, §2). Operación RECHAZO de
-- gestion__cheques_operaciones (ver 01 §7.8).
CALL `_tes_tipo`(@tes_empresa_id, @tes_grp_caja, @tes_subgrupo_id, 'CHP_RECH', 'Rechazo de Cheque Propio', '+', 1, 1, 412, @tes_er_activo);

-- Impuestos: I_LIQ / I_PAG (nuevos). impacta_ctacte = 1 (A8/D17: ctacte del
-- organismo = impacta_ctacte + imputaciones; el documento v2.0 decía 0).
CALL `_tes_subgrupo`(@tes_empresa_id, @tes_grp_caja, 'Liquidaciones Impositivas', 'ILQ', 30, 'gestion__obligaciones_impositivas', @tes_tipo_asiento_id, @tes_er_activo);
CALL `_tes_tipo`(@tes_empresa_id, @tes_grp_caja, @tes_subgrupo_id, 'I_LIQ', 'Liquidacion Impositiva (DDJJ)', '+', 1, 1, 420, @tes_er_activo);
CALL `_tes_subgrupo`(@tes_empresa_id, @tes_grp_caja, 'Pagos de Impuestos', 'IPG', 31, 'gestion__pagos_impuestos', @tes_tipo_asiento_id, @tes_er_activo);
CALL `_tes_tipo`(@tes_empresa_id, @tes_grp_caja, @tes_subgrupo_id, 'I_PAG', 'Pago de Impuesto', '-', 1, 1, 421, @tes_er_activo);

-- Rendiciones: RG_ANT / RG_REND / RG_REEM (nuevos). Signo por analogía con
-- compras: la deuda (RG_REND) '+', los que la cancelan (RG_ANT, RG_REEM) '-'.
CALL `_tes_subgrupo`(@tes_empresa_id, @tes_grp_caja, 'Anticipos a Rendir', 'RANT', 40, 'gestion__rendiciones_anticipos', @tes_tipo_asiento_id, @tes_er_activo);
CALL `_tes_tipo`(@tes_empresa_id, @tes_grp_caja, @tes_subgrupo_id, 'RG_ANT', 'Anticipo a Rendir', '-', 1, 1, 430, @tes_er_activo);
CALL `_tes_subgrupo`(@tes_empresa_id, @tes_grp_caja, 'Rendiciones de Gastos', 'REND', 41, 'gestion__rendiciones', @tes_tipo_asiento_id, @tes_er_activo);
CALL `_tes_tipo`(@tes_empresa_id, @tes_grp_caja, @tes_subgrupo_id, 'RG_REND', 'Rendicion de Gastos', '+', 1, 1, 431, @tes_er_activo);
CALL `_tes_subgrupo`(@tes_empresa_id, @tes_grp_caja, 'Reembolsos de Rendiciones', 'REEM', 42, 'gestion__rendiciones_reembolsos', @tes_tipo_asiento_id, @tes_er_activo);
CALL `_tes_tipo`(@tes_empresa_id, @tes_grp_caja, @tes_subgrupo_id, 'RG_REEM', 'Reembolso de Rendicion', '-', 1, 1, 432, @tes_er_activo);

-- =====================================================================
-- 2) REGLAS DE CANCELACIÓN (gestion__comprobantes_tipos_cancelaciones)
-- =====================================================================
-- Pares resueltos por código dentro de la empresa; los que no existan en la
-- empresa (p. ej. V_FX si no factura X) simplemente no se insertan.
--   RCX       -> facturas/ND de venta (A, B, X), ND_CHR, RG_ANT (devolución del empleado)
--   NC venta  -> FA/ND de su misma letra (D7)
--   OP        -> facturas/ND de compra, DEV_END (D20/D25) y CHP_RECH (D28)
--   NC compra -> FA/ND de compra de su letra
--   I_PAG -> I_LIQ ; RG_ANT -> RG_REND ; RG_REEM -> RG_REND
INSERT INTO gestion__comprobantes_tipos_cancelaciones
    (empresa_id, comprobante_tipo_origen_id, comprobante_tipo_destino_id, exige_misma_entidad, tabla_estado_registro_id)
SELECT @tes_empresa_id, o.comprobante_tipo_id, d.comprobante_tipo_id, 1, @tes_er_activo
FROM (
              SELECT 'RCX' AS cod_origen, 'V_FA' AS cod_destino
    UNION ALL SELECT 'RCX',     'V_NDA'
    UNION ALL SELECT 'RCX',     'V_FB'
    UNION ALL SELECT 'RCX',     'V_NDA_B'
    UNION ALL SELECT 'RCX',     'V_FX'
    UNION ALL SELECT 'RCX',     'V_NDX'
    UNION ALL SELECT 'RCX',     'ND_CHR'
    UNION ALL SELECT 'RCX',     'RG_ANT'
    UNION ALL SELECT 'V_NCA',   'V_FA'
    UNION ALL SELECT 'V_NCA',   'V_NDA'
    UNION ALL SELECT 'V_NCA_B', 'V_FB'
    UNION ALL SELECT 'V_NCA_B', 'V_NDA_B'
    UNION ALL SELECT 'V_NCX',   'V_FX'
    UNION ALL SELECT 'V_NCX',   'V_NDX'
    UNION ALL SELECT 'OP',      'C_FA'
    UNION ALL SELECT 'OP',      'C_NDA'
    UNION ALL SELECT 'OP',      'C_NDX'
    UNION ALL SELECT 'OP',      'DEV_END'
    UNION ALL SELECT 'OP',      'CHP_RECH'
    UNION ALL SELECT 'C_NCA',   'C_FA'
    UNION ALL SELECT 'C_NCA',   'C_NDA'
    UNION ALL SELECT 'C_NCX',   'C_NDX'
    UNION ALL SELECT 'I_PAG',   'I_LIQ'
    UNION ALL SELECT 'RG_ANT',  'RG_REND'
    UNION ALL SELECT 'RG_REEM', 'RG_REND'
) r
JOIN gestion__comprobantes_tipos o
  ON o.empresa_id = @tes_empresa_id AND o.codigo = r.cod_origen COLLATE utf8mb4_unicode_ci
JOIN gestion__comprobantes_tipos d
  ON d.empresa_id = @tes_empresa_id AND d.codigo = r.cod_destino COLLATE utf8mb4_unicode_ci
WHERE NOT EXISTS (
    SELECT 1 FROM gestion__comprobantes_tipos_cancelaciones x
    WHERE x.empresa_id = @tes_empresa_id
      AND x.comprobante_tipo_origen_id = o.comprobante_tipo_id
      AND x.comprobante_tipo_destino_id = d.comprobante_tipo_id
);

-- =====================================================================
-- 3) MARCA DE AGENTE DE RETENCIÓN POR EMPRESA (D21)
-- =====================================================================
-- Una FILA por impuesto de retención en gestion__empresas_impuestos_config
-- (no una columna nueva). Motivos:
--   - La tabla ya es por (empresa, impuesto_tipo, jurisdicción) y la
--     condición de agente es por impuesto (se puede ser agente de IIBB y no
--     de Ganancias): la existencia de una fila ACTIVA para un impuesto con
--     es_retencion = 1 AND aplica_compra = 1 ES la marca. Una columna
--     es_agente_retencion igual obligaría a tener la fila, así que no agrega
--     nada.
--   - La fila trae además lo que la retención necesita: cont_cuenta_id =
--     «Retenciones X a depositar» de la empresa (pasivo del asiento de la OP)
--     y jurisdiccion_id (IIBB: @tes_jurisdiccion_iibb).
--   - Columnas NOT NULL de cálculo con valores neutros: base_calculo
--     'MANUAL', alicuota 0, tipo_calculo 'manual' (D21: carga manual; la
--     alícuota real va en cada retención).
-- Las filas se siembran INACTIVAS (estado genérico INACTIVO, convención de
-- diccionario activo / inactivo, como en
-- gestion__empresas_impuestos_config_subgrupos): ser agente de retención es
-- un dato fiscal de la empresa que el usuario confirma activándolas y
-- cargando la cuenta (Word v3.6 §9.1 fila 8). f_desde = fecha de corrida
-- (solo en el alta). Idempotente por (empresa, impuesto_tipo).
INSERT INTO gestion__empresas_impuestos_config
    (empresa_id, impuesto_tipo_id, jurisdiccion_id, condicion_fiscal_id, cont_cuenta_id,
     base_calculo, alicuota, minimo_imponible, monto_fijo, aplica_siempre, prioridad,
     f_desde, f_hasta, tipo_calculo, tabla_estado_registro_id)
SELECT @tes_empresa_id, it.impuesto_tipo_id,
       (SELECT j.jurisdiccion_id FROM gestion__jurisdicciones j
         WHERE j.jurisdiccion_codigo = v.jurisdiccion_codigo COLLATE utf8mb4_unicode_ci
         ORDER BY j.jurisdiccion_id LIMIT 1),
       NULL, NULL,
       'MANUAL', 0, 0, 0, 0, 1,
       CURDATE(), NULL, 'manual', @tes_er_inactivo
FROM (
              SELECT 'Retencion Ganancias practicada' AS impuesto_tipo, 'AFIP' AS jurisdiccion_codigo
    UNION ALL SELECT 'Retencion IVA practicada',  'AFIP'
    UNION ALL SELECT 'Retencion IIBB practicada', @tes_jurisdiccion_iibb
    UNION ALL SELECT 'Retencion SUSS practicada', 'AFIP'
) v
JOIN gestion__impuestos_tipos it ON it.impuesto_tipo = v.impuesto_tipo COLLATE utf8mb4_unicode_ci
WHERE NOT EXISTS (
    SELECT 1 FROM gestion__empresas_impuestos_config c
    WHERE c.empresa_id = @tes_empresa_id AND c.impuesto_tipo_id = it.impuesto_tipo_id
);

COMMIT;

-- =====================================================================
-- V) VERIFICACIÓN (solo lectura)
-- =====================================================================
-- Tipos de tesorería con su subgrupo, tabla y origen / tipo de asiento.
-- Deben salir 13 filas, todas con tabla_nombre, origen_asiento = 'AUTO' y
-- tipo_asiento = AUT (salvo subgrupos preexistentes con otra configuración).
SELECT ct.comprobante_tipo_id, ct.codigo, ct.comprobante_tipo, ct.signo,
       ct.impacta_contabilidad, ct.impacta_ctacte,
       sg.comprobante_subgrupo_id, sg.comprobante_subgrupo, sg.origen_asiento,
       sg.tipo_asiento_id, ta.codigo AS tipo_asiento,
       t.tabla_nombre
FROM gestion__comprobantes_tipos ct
LEFT JOIN gestion__comprobantes_subgrupos sg ON sg.comprobante_subgrupo_id = ct.comprobante_subgrupo_id
LEFT JOIN gestion__cont_tipos_asientos ta    ON ta.cont_tipo_asiento_id = sg.tipo_asiento_id
LEFT JOIN conf__tablas t                     ON t.tabla_id = sg.tabla_id
WHERE ct.empresa_id = @tes_empresa_id
  AND ct.codigo IN ('RCX', 'OP', 'TB', 'DB', 'MC', 'ND_CHR', 'DEV_END', 'CHP_RECH',
                    'I_LIQ', 'I_PAG', 'RG_ANT', 'RG_REND', 'RG_REEM')
ORDER BY ct.orden, ct.codigo;

-- Reglas de cancelación de la empresa (25 como máximo; menos si la empresa
-- no tiene algún tipo de venta/compra de la lista).
SELECT o.codigo AS origen, d.codigo AS destino, x.exige_misma_entidad, x.tabla_estado_registro_id
FROM gestion__comprobantes_tipos_cancelaciones x
JOIN gestion__comprobantes_tipos o ON o.comprobante_tipo_id = x.comprobante_tipo_origen_id
JOIN gestion__comprobantes_tipos d ON d.comprobante_tipo_id = x.comprobante_tipo_destino_id
WHERE x.empresa_id = @tes_empresa_id
ORDER BY o.codigo, d.codigo;

-- Retenciones practicadas (D21): tipos y marca de agente (filas inactivas).
SELECT it.impuesto_tipo_id, it.impuesto_tipo, it.es_retencion, it.aplica_compra, it.es_liquidable,
       c.empresa_impuesto_config_id, c.jurisdiccion_id, c.cont_cuenta_id, c.tabla_estado_registro_id AS config_estado
FROM gestion__impuestos_tipos it
LEFT JOIN gestion__empresas_impuestos_config c
       ON c.impuesto_tipo_id = it.impuesto_tipo_id AND c.empresa_id = @tes_empresa_id
WHERE it.es_retencion = 1
ORDER BY it.impuesto_tipo_id;

-- =====================================================================
-- Limpieza de helpers y restauración del sql_mode
-- =====================================================================
DROP PROCEDURE IF EXISTS `_tes_assert`;
DROP PROCEDURE IF EXISTS `_tes_subgrupo`;
DROP PROCEDURE IF EXISTS `_tes_tipo`;

SET SESSION sql_mode = @tes_old_sql_mode;
