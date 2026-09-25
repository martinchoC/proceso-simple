-- El item de menu "Presupuestos" (pagina_id=89, hijo de Ventas) apunta a
-- ventas_presupuestos.php, que no existe en modules/gestion/. Se desactiva
-- del sidebar hasta que la pagina se implemente.
UPDATE conf__paginas
SET tabla_estado_registro_id = 2
WHERE pagina_id = 89;
