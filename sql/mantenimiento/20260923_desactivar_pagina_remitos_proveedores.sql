-- El item de menu "Remitos de Proveedores" (pagina_id=58, hijo de Compras) apunta
-- a proveedores_remitos.php, que no existe en modules/gestion/. Se desactiva
-- del sidebar hasta que la pagina se implemente. Mismo caso que pagina_id=89
-- (Presupuestos, ver 20260923_desactivar_pagina_presupuestos.sql).
UPDATE conf__paginas
SET tabla_estado_registro_id = 2
WHERE pagina_id = 58;
