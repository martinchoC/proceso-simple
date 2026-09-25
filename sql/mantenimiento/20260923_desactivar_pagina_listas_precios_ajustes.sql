-- El item de menu "Listas de Precios - Ajustes" (pagina_id=54, hija de Seteo
-- Empresas) apunta a listas_precios_ajustes.php, que no existe en
-- modules/gestion/. Se desactiva del sidebar hasta que la pagina se
-- implemente. Mismo caso que pagina_id=58 y pagina_id=89.
UPDATE conf__paginas
SET tabla_estado_registro_id = 2
WHERE pagina_id = 54;
