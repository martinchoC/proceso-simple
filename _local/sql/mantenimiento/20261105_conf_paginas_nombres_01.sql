-- =====================================================================================================
-- Módulo 1 (conf): nombres claros en el menú lateral y en las migas de pan
-- -----------------------------------------------------------------------------------------------------
-- Las páginas de conf pasaron al estilo ui-simple (encabezado con migas "Inicio / Sección / Página",
-- core/ui.php). El desplegable de la sección muestra los nombres de conf__paginas, que eran abreviaturas
-- ("Emp.Perf.Funciones", "Mod.Perfiles Accesos"). Se renombran igual que el título de cada página.
-- Solo cambia el texto (conf__paginas.pagina); ids, urls y permisos quedan igual. Idempotente.
-- MariaDB 10.4 y MySQL 8.
-- =====================================================================================================

UPDATE conf__paginas p
JOIN (
    SELECT  1 AS pagina_id, 'Empresas y accesos'          AS nombre UNION ALL
    SELECT 21, 'Estructura del sistema'                   UNION ALL
    SELECT 22, 'Otros'                                    UNION ALL
    SELECT 27, 'Empresas'                                 UNION ALL
    SELECT 15, 'Módulos'                                  UNION ALL
    SELECT 19, 'Perfiles base'                            UNION ALL
    SELECT 25, 'Funciones de perfiles base'               UNION ALL
    SELECT 60, 'Módulos por empresa'                      UNION ALL
    SELECT 61, 'Perfiles de empresa'                      UNION ALL
    SELECT 62, 'Funciones por perfil'                     UNION ALL
    SELECT 18, 'Usuarios'                                 UNION ALL
    SELECT 26, 'Usuarios por perfil'                      UNION ALL
    SELECT 13, 'Estados de registro'                      UNION ALL
    SELECT 23, 'Tipos de tabla'                           UNION ALL
    SELECT 10, 'Tablas'                                   UNION ALL
    SELECT 14, 'Estados por tabla'                        UNION ALL
    SELECT  2, 'Páginas'                                  UNION ALL
    SELECT 24, 'Funciones de páginas'                     UNION ALL
    SELECT 59, 'Tipos de funciones'                       UNION ALL
    SELECT 28, 'Imágenes'                                 UNION ALL
    SELECT 16, 'Íconos'
) n ON n.pagina_id = p.pagina_id
SET p.pagina = n.nombre
WHERE p.modulo_id = 1;
