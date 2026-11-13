-- Estudio contable: "Importar Mis Comprobantes" deja de ser una página (419) y pasa a ser el botón "Importar de ARCA"
-- de Mini-Gestión (417), que atiende a los mismos clientes (Fiscal / Contable).
--  1) Las funciones "Importar comprobantes" (3557, importar_arca) y "Confirmar compras" (3558, confirmar_compras) pasan
--     de la página 419 a la 417: los perfiles que las tenían las conservan (las asignaciones son por función).
--  2) Se inactivan la página 419 y su función "Acceder" (3556). Revertir: volver la 3557 / 3558 a la 419 y estado 1.
-- Requiere 20261106_estudio_importar_arca_01.sql. Reanudable.
SET NAMES utf8mb4;

UPDATE conf__paginas_funciones SET pagina_id = 417, orden = 4
WHERE pagina_funcion_id = 3557 AND pagina_id = 419
  AND NOT EXISTS (SELECT 1 FROM (SELECT pagina_funcion_id FROM conf__paginas_funciones
                                 WHERE pagina_id = 417 AND accion_js = 'importar_arca') x);

UPDATE conf__paginas_funciones SET pagina_id = 417, orden = 5
WHERE pagina_funcion_id = 3558 AND pagina_id = 419
  AND NOT EXISTS (SELECT 1 FROM (SELECT pagina_funcion_id FROM conf__paginas_funciones
                                 WHERE pagina_id = 417 AND accion_js = 'confirmar_compras') x);

UPDATE conf__paginas_funciones SET tabla_estado_registro_id = 2 WHERE pagina_id = 419 AND tabla_estado_registro_id = 1;
UPDATE conf__paginas SET tabla_estado_registro_id = 2 WHERE pagina_id = 419 AND modulo_id = 4 AND tabla_estado_registro_id = 1;
