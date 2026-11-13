-- =====================================================================================================
-- Conciliación bancaria - script 06: CONCEPTO DE COBRANZAS
-- -----------------------------------------------------------------------------------------------------
-- Requiere 20261028_conciliacion_bancaria_01..05.
-- En Conciliación Bancaria, una línea de crédito cuya referencia es el CUIT de un cliente ofrece "Generar recibo":
-- crea la cobranza (transferencia a la cuenta del extracto) y, si se confirma, concilia la línea con su movimiento.
-- A la línea se le asigna el concepto de cobranzas de la empresa: el concepto (movimiento propio) marcado con
-- es_cobranza = 1 en Conceptos Bancarios. Uno solo por empresa (lo mantiene bco_concepto_guardar).
-- Siembra: en las empresas que todavía no tienen uno, se marca el concepto TRANSFERENCIA (lo crea el script 02).
-- No hace falta ninguna función nueva: generar el recibo exige las funciones agregar / confirmar de Cobranzas
-- (105 / 605) y conciliar, la función agregar de Conciliación Bancaria (128 / 628).
-- Idempotente sin procedimientos (PREPARE condicional). MariaDB 10.4 y MySQL 8.
-- =====================================================================================================

SET NAMES utf8mb4;

SET @sql = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__bancos_conceptos'
               AND COLUMN_NAME = 'es_cobranza') = 0,
    "ALTER TABLE gestion__bancos_conceptos ADD COLUMN es_cobranza TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Concepto que se asigna a las líneas de las que se genera un recibo de cobranza' AFTER jurisdiccion_id",
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

UPDATE gestion__bancos_conceptos k
JOIN (SELECT empresa_id FROM gestion__bancos_conceptos GROUP BY empresa_id HAVING SUM(es_cobranza) = 0) sin ON sin.empresa_id = k.empresa_id
SET k.es_cobranza = 1
WHERE k.codigo = 'TRANSFERENCIA' AND k.naturaleza = 'MOVIMIENTO_PROPIO' AND k.tabla_estado_registro_id = 1;

-- Control: el concepto de cobranzas de cada empresa
SELECT k.empresa_id, k.concepto_id, k.codigo, k.concepto FROM gestion__bancos_conceptos k WHERE k.es_cobranza = 1 ORDER BY k.empresa_id;
