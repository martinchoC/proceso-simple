-- =============================================================================
--  Desinstalación del storefront
--
--  DESTRUCTIVO: borra carritos, pedidos del ecommerce y auditoría de accesos.
--  NO toca el ERP ni el sistema de usuarios/perfiles: los pedidos ya creados en
--  gestion__ventas_pedidos y gestion__comprobantes permanecen.
--
--  Hacer backup antes (hPanel → Copias de seguridad).
-- =============================================================================

DROP TABLE IF EXISTS `ecom__carritos_items`;
DROP TABLE IF EXISTS `ecom__carritos`;
DROP TABLE IF EXISTS `ecom__pedidos`;
DROP TABLE IF EXISTS `ecom__login_intentos`;
DROP TABLE IF EXISTS `ecom__usuarios_entidades`;

-- Desactivar el módulo en lugar de borrarlo conserva la trazabilidad de los
-- perfiles que lo tuvieron asignado.
UPDATE `conf__modulos` SET `tabla_estado_registro_id` = 2 WHERE `modulo` = 'Ecommerce B2B';

-- Opcional: quitar la columna de códigos de función.
-- Sólo si NINGUNA otra aplicación la está usando.
-- ALTER TABLE `conf__paginas_funciones`
--     DROP INDEX `uk_paginas_funciones_codigo`,
--     DROP COLUMN `codigo_funcion`;

-- Los índices agregados por la migración pueden quedar: mejoran el ERP y no
-- afectan su funcionamiento.
