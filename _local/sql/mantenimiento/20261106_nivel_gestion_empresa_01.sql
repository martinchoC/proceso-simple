-- Nivel de gestión de la empresa (COMPLETA / SIMPLIFICADA) y páginas que solo existen en la gestión completa.
-- Misma lógica que tipo_operacion / requiere_bienes (core/perfiles_predefinidos.php): al crear los perfiles de una empresa
-- SIMPLIFICADA no se copian las funciones de las páginas con requiere_gestion_completa = 1 (se ocultan del menú).
-- Reanudable y compatible con MySQL 8 (que no tiene ADD COLUMN IF NOT EXISTS).
SET NAMES utf8mb4;

SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conf__empresas' AND COLUMN_NAME = 'nivel_gestion');
SET @sql := IF(@existe = 0,
    'ALTER TABLE conf__empresas ADD COLUMN nivel_gestion ENUM(''COMPLETA'',''SIMPLIFICADA'') NOT NULL DEFAULT ''COMPLETA'' COMMENT ''SIMPLIFICADA = cliente de estudio contable: solo comprobantes, IVA, contabilidad e impuestos'' AFTER tipo_operacion',
    'SELECT ''conf__empresas.nivel_gestion ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conf__paginas' AND COLUMN_NAME = 'requiere_gestion_completa');
SET @sql := IF(@existe = 0,
    'ALTER TABLE conf__paginas ADD COLUMN requiere_gestion_completa TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''1 = la página no se ofrece a empresas de gestión simplificada'' AFTER requiere_bienes',
    'SELECT ''conf__paginas.requiere_gestion_completa ya existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Punto de partida: lo de bienes (pedidos, remitos, stock, costos) tampoco lo usa una empresa simplificada.
UPDATE `conf__paginas` SET `requiere_gestion_completa` = 1 WHERE `requiere_bienes` = 1 AND `requiere_gestion_completa` = 0;

-- Completar a mano con el resto de páginas que una empresa simplificada no necesita (producción, presupuestos,
-- CRM, etc.). Para ver el listado:  SELECT pagina_id, pagina, requiere_bienes, requiere_gestion_completa FROM conf__paginas ORDER BY pagina;
-- Ejemplo:  UPDATE conf__paginas SET requiere_gestion_completa = 1 WHERE pagina_id IN (...);
