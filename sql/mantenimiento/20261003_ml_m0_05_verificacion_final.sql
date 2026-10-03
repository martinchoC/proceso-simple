-- =====================================================================
-- Matriz legal - M0 (instalación del módulo 3), script 05: VERIFICACIÓN FINAL
-- 2026-10-03
-- =====================================================================
-- Qué hace (docs/matriz-legal/08-diseno-m0.md, sección 6; "Controles posteriores"):
--   Script de solo lectura que valida de punta a punta la instalación de M0:
--   1) 69 tablas ml__ creadas, motor InnoDB, collation utf8mb4_unicode_ci.
--   2) 0 columnas de texto con collation ajena a utf8mb4_unicode_ci.
--   3) 0 tablas remanentes del prototipo viejo ml__.
--   4) 149 FKs activas (99 entre ml__ y 50 hacia conf__).
--   5) 29 restricciones CHECK y 2 columnas generadas STORED.
--   6) Columna `es_final` presente en conf__tablas_estados_registros.
--   7) 69 tablas registradas en conf__tablas (53 tipo 1, 7 tipo 2, 9 tipo 3).
--   8) 0 tablas registradas en conf__tablas que no existan físicamente.
--   9) 12 estados genéricos en conf__estados_registros (16 a 27) y menor
--      valor_estandar preservado en 1 (Activo de gestión).
--  10) 110 estados de tabla en conf__tablas_estados_registros (2001 a 2110)
--      con exactamente un es_inicial = 1 por tabla con estados.
--  11) 8 perfiles base en conf__perfiles (301 a 308).
--  12) 12 asignaciones en conf__empresas_perfiles (8 en empresa 2, 4 en empresa 3).
--
-- Devuelve un resumen detallado y una fila final con "OK para seguir" o
-- la lista de inconsistencias encontradas.
--
-- ---------------------------------------------------------------------
-- CÓMO SE CORRE
-- ---------------------------------------------------------------------
--   mysql -u <USUARIO> -p --default-character-set=utf8mb4 <BASE> < 20261003_ml_m0_05_verificacion_final.sql
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- 1) Recolección de métricas
-- ---------------------------------------------------------------------

-- Físicas: tablas ml__
SET @cnt_tablas_ml = (
    SELECT COUNT(*) FROM information_schema.tables
     WHERE table_schema = DATABASE() AND table_name LIKE 'ml\_\_%'
);
SET @cnt_tablas_innodb_utf8mb4 = (
    SELECT COUNT(*) FROM information_schema.tables
     WHERE table_schema = DATABASE() AND table_name LIKE 'ml\_\_%'
       AND engine = 'InnoDB' AND table_collation = 'utf8mb4_unicode_ci'
);
SET @cnt_cols_otra_collation = (
    SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE() AND table_name LIKE 'ml\_\_%'
       AND collation_name IS NOT NULL AND collation_name <> 'utf8mb4_unicode_ci'
);

-- Tablas del prototipo que no deben existir
SET @cnt_tablas_prototipo = (
    SELECT COUNT(*) FROM information_schema.tables
     WHERE table_schema = DATABASE()
       AND table_name IN (
           'ml__ambitos_organizacionales', 'ml__jurisdicciones', 'ml__tareas',
           'ml__tareas_ciclos', 'ml__tareas_dependencias', 'ml__tareas_evidencias',
           'ml__roles_organizacionales', 'ml__normas_ambitos_aplicacion',
           'ml__normas_procedimientos', 'ml__obligaciones_raci',
           'ml__procedimientos_obligaciones'
       )
);

-- Restricciones FK
SET @cnt_fks_total = (
    SELECT COUNT(*) FROM information_schema.referential_constraints
     WHERE constraint_schema = DATABASE() AND table_name LIKE 'ml\_\_%'
);
SET @cnt_fks_ml = (
    SELECT COUNT(*) FROM information_schema.referential_constraints
     WHERE constraint_schema = DATABASE() AND table_name LIKE 'ml\_\_%'
       AND referenced_table_name LIKE 'ml\_\_%'
);
SET @cnt_fks_conf = (
    SELECT COUNT(*) FROM information_schema.referential_constraints
     WHERE constraint_schema = DATABASE() AND table_name LIKE 'ml\_\_%'
       AND referenced_table_name IN ('conf__empresas', 'conf__usuarios', 'conf__empresas_perfiles')
);

-- CHECK y columnas generadas STORED
SET @cnt_checks = (
    SELECT COUNT(*) FROM information_schema.table_constraints
     WHERE constraint_schema = DATABASE() AND table_name LIKE 'ml\_\_%'
       AND constraint_type = 'CHECK'
);
SET @cnt_columnas_generadas = (
    SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE()
       AND ((table_name = 'ml__territorios_pertenencias' AND column_name = 'principal_abierta_id')
         OR (table_name = 'ml__entidades_empresas' AND column_name = 'titular_abierta_id'))
       AND IFNULL(generation_expression, '') <> ''
);

-- Columna es_final en conf__tablas_estados_registros
SET @cnt_col_es_final = (
    SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE()
       AND table_name = 'conf__tablas_estados_registros'
       AND column_name = 'es_final'
);

-- Configuración: conf__tablas
SET @cnt_tablas_conf_ml = (
    SELECT COUNT(*) FROM conf__tablas
     WHERE modulo_id = 3 AND tabla_id BETWEEN 201 AND 269
);
SET @cnt_tablas_tipo_1 = (
    SELECT COUNT(*) FROM conf__tablas
     WHERE modulo_id = 3 AND tabla_id BETWEEN 201 AND 269 AND tabla_tipo_id = 1
);
SET @cnt_tablas_tipo_2 = (
    SELECT COUNT(*) FROM conf__tablas
     WHERE modulo_id = 3 AND tabla_id BETWEEN 201 AND 269 AND tabla_tipo_id = 2
);
SET @cnt_tablas_tipo_3 = (
    SELECT COUNT(*) FROM conf__tablas
     WHERE modulo_id = 3 AND tabla_id BETWEEN 201 AND 269 AND tabla_tipo_id = 3
);
SET @cnt_tablas_conf_sin_fisica = (
    SELECT COUNT(*) FROM conf__tablas ct
      LEFT JOIN information_schema.tables t
        ON t.table_schema = DATABASE() AND t.table_name = ct.tabla_nombre
     WHERE ct.modulo_id = 3 AND t.table_name IS NULL
);

-- Configuración: conf__estados_registros
SET @cnt_estados_genericos_nuevos = (
    SELECT COUNT(*) FROM conf__estados_registros
     WHERE estado_registro_id BETWEEN 16 AND 27
);
SET @val_min_estado_estandar = (
    SELECT valor_estandar FROM conf__estados_registros
     WHERE valor_estandar IS NOT NULL
     ORDER BY valor_estandar ASC LIMIT 1
);

-- Configuración: conf__tablas_estados_registros
SET @cnt_estados_tabla_ml = (
    SELECT COUNT(*) FROM conf__tablas_estados_registros
     WHERE tabla_estado_registro_id BETWEEN 2001 AND 2110
);
SET @cnt_tablas_anomalia_inicial = (
    SELECT COUNT(*) FROM (
        SELECT tabla_id, SUM(es_inicial) AS iniciales
          FROM conf__tablas_estados_registros
         WHERE tabla_id BETWEEN 201 AND 269
         GROUP BY tabla_id
        HAVING iniciales <> 1
    ) sub
);

-- Perfiles: conf__perfiles y conf__empresas_perfiles
SET @cnt_perfiles_base = (
    SELECT COUNT(*) FROM conf__perfiles
     WHERE modulo_id = 3 AND perfil_id BETWEEN 301 AND 308
);
SET @cnt_emp_perfiles_e2 = (
    SELECT COUNT(*) FROM conf__empresas_perfiles
     WHERE modulo_id = 3 AND empresa_id = 2 AND empresa_perfil_id BETWEEN 301 AND 308
);
SET @cnt_emp_perfiles_e3 = (
    SELECT COUNT(*) FROM conf__empresas_perfiles
     WHERE modulo_id = 3 AND empresa_id = 3 AND empresa_perfil_id BETWEEN 309 AND 312
);

-- ---------------------------------------------------------------------
-- 2) Reporte de verificación detallado
-- ---------------------------------------------------------------------
SELECT '1. Tablas físicas ml__'                                  AS control, @cnt_tablas_ml                    AS actual, 69  AS esperado, IF(@cnt_tablas_ml = 69, 'OK', 'FALLA') AS estado
UNION ALL SELECT '2. Motor InnoDB y utf8mb4_unicode_ci',                    @cnt_tablas_innodb_utf8mb4,        69,  IF(@cnt_tablas_innodb_utf8mb4 = 69, 'OK', 'FALLA')
UNION ALL SELECT '3. Columnas con otra collation',                          @cnt_cols_otra_collation,          0,   IF(@cnt_cols_otra_collation = 0, 'OK', 'FALLA')
UNION ALL SELECT '4. Tablas huérfanas del prototipo',                       @cnt_tablas_prototipo,             0,   IF(@cnt_tablas_prototipo = 0, 'OK', 'FALLA')
UNION ALL SELECT '5. FKs totales de ml__',                                  @cnt_fks_total,                    149, IF(@cnt_fks_total = 149, 'OK', 'FALLA')
UNION ALL SELECT '6. FKs internas entre tablas ml__',                       @cnt_fks_ml,                       99,  IF(@cnt_fks_ml = 99, 'OK', 'FALLA')
UNION ALL SELECT '7. FKs hacia tablas del core conf__',                     @cnt_fks_conf,                     50,  IF(@cnt_fks_conf = 50, 'OK', 'FALLA')
UNION ALL SELECT '8. Restricciones CHECK en tablas ml__',                   @cnt_checks,                       29,  IF(@cnt_checks = 29, 'OK', 'FALLA')
UNION ALL SELECT '9. Columnas generadas STORED',                            @cnt_columnas_generadas,           2,   IF(@cnt_columnas_generadas = 2, 'OK', 'FALLA')
UNION ALL SELECT '10. Columna es_final en conf__tablas_estados_registros',  @cnt_col_es_final,                 1,   IF(@cnt_col_es_final = 1, 'OK', 'FALLA')
UNION ALL SELECT '11. conf__tablas registradas módulo 3',                   @cnt_tablas_conf_ml,               69,  IF(@cnt_tablas_conf_ml = 69, 'OK', 'FALLA')
UNION ALL SELECT '12. conf__tablas tipo 1 (Diccionario)',                   @cnt_tablas_tipo_1,                53,  IF(@cnt_tablas_tipo_1 = 53, 'OK', 'FALLA')
UNION ALL SELECT '13. conf__tablas tipo 2 (Proceso corto)',                 @cnt_tablas_tipo_2,                7,   IF(@cnt_tablas_tipo_2 = 7, 'OK', 'FALLA')
UNION ALL SELECT '14. conf__tablas tipo 3 (Proceso largo)',                 @cnt_tablas_tipo_3,                9,   IF(@cnt_tablas_tipo_3 = 9, 'OK', 'FALLA')
UNION ALL SELECT '15. conf__tablas sin tabla física existente',             @cnt_tablas_conf_sin_fisica,       0,   IF(@cnt_tablas_conf_sin_fisica = 0, 'OK', 'FALLA')
UNION ALL SELECT '16. Estados genéricos nuevos en conf__estados_registros', @cnt_estados_genericos_nuevos,    12,  IF(@cnt_estados_genericos_nuevos = 12, 'OK', 'FALLA')
UNION ALL SELECT '17. Mínimo valor_estandar de conf__estados_registros',    @val_min_estado_estandar,          1,   IF(@val_min_estado_estandar = 1, 'OK', 'FALLA')
UNION ALL SELECT '18. Estados de tabla en conf__tablas_estados_registros',  @cnt_estados_tabla_ml,             110, IF(@cnt_estados_tabla_ml = 110, 'OK', 'FALLA')
UNION ALL SELECT '19. Tablas sin exactamente 1 es_inicial',                 @cnt_tablas_anomalia_inicial,      0,   IF(@cnt_tablas_anomalia_inicial = 0, 'OK', 'FALLA')
UNION ALL SELECT '20. Perfiles base módulo 3 en conf__perfiles',            @cnt_perfiles_base,                8,   IF(@cnt_perfiles_base = 8, 'OK', 'FALLA')
UNION ALL SELECT '21. Perfiles de empresa en Empresa 2 (titular)',          @cnt_emp_perfiles_e2,              8,   IF(@cnt_emp_perfiles_e2 = 8, 'OK', 'FALLA')
UNION ALL SELECT '22. Perfiles de empresa en Empresa 3 (sucursal)',         @cnt_emp_perfiles_e3,              4,   IF(@cnt_emp_perfiles_e3 = 4, 'OK', 'FALLA');

-- ---------------------------------------------------------------------
-- 3) Dictamen final consolidado
-- ---------------------------------------------------------------------
SELECT CASE
    WHEN @cnt_tablas_ml = 69
     AND @cnt_tablas_innodb_utf8mb4 = 69
     AND @cnt_cols_otra_collation = 0
     AND @cnt_tablas_prototipo = 0
     AND @cnt_fks_total = 149
     AND @cnt_fks_ml = 99
     AND @cnt_fks_conf = 50
     AND @cnt_checks = 29
     AND @cnt_columnas_generadas = 2
     AND @cnt_col_es_final = 1
     AND @cnt_tablas_conf_ml = 69
     AND @cnt_tablas_tipo_1 = 53
     AND @cnt_tablas_tipo_2 = 7
     AND @cnt_tablas_tipo_3 = 9
     AND @cnt_tablas_conf_sin_fisica = 0
     AND @cnt_estados_genericos_nuevos = 12
     AND @val_min_estado_estandar = 1
     AND @cnt_estados_tabla_ml = 110
     AND @cnt_tablas_anomalia_inicial = 0
     AND @cnt_perfiles_base = 8
     AND @cnt_emp_perfiles_e2 = 8
     AND @cnt_emp_perfiles_e3 = 4
    THEN 'OK: M0 INSTALACION COMPLETA Y VERIFICADA (69 tablas, 149 FK, 12 estados genéricos, 110 estados de tabla, 8 perfiles, 12 perfiles empresa)'
    ELSE 'FALLA: Se detectaron discrepancias en M0. Revisar el detalle anterior.'
END AS dictamen_final;
