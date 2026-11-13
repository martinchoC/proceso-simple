-- =====================================================================================================
-- Facturas de Proveedores (páginas 57 / 557): botones que se distinguan
-- -----------------------------------------------------------------------------------------------------
-- "Pasar a Aprobación", "Confirmar" y "Visualizar" usaban el mismo ícono (46 check), y "Anular" el tacho
-- de "Eliminar". Queda:
--   Pasar a Aprobación -> ícono nuevo "Enviar a aprobación" (bi-send-fill), color btn-primary (1)
--   Confirmar          -> sigue con el check (46), btn-success
--   Visualizar         -> lupa (37 bi-search), btn-light (7), igual en todos los estados (como en remitos)
--   Anular             -> x (45 bi-x-circle-fill), como el Anular de cobranzas / pagos
--   Imprimir           -> mismo color en todos los estados (8 btn-dark)
-- El ícono nuevo se busca por clase (id 146 en la base local; si está ocupado toma el siguiente libre).
-- Idempotente. MariaDB 10.4 y MySQL 8.
-- =====================================================================================================

START TRANSACTION;

INSERT INTO conf__iconos (icono_id, icono_nombre, icono_clase, tabla_estado_registro_id)
SELECT GREATEST(146, (SELECT MAX(i.icono_id) + 1 FROM conf__iconos i)), 'Enviar a aprobación', 'bi bi-send-fill', 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM conf__iconos WHERE icono_clase = 'bi bi-send-fill');

SET @icono_enviar := (SELECT icono_id FROM conf__iconos WHERE icono_clase = 'bi bi-send-fill' ORDER BY icono_id LIMIT 1);

UPDATE conf__paginas_funciones SET icono_id = @icono_enviar, color_id = 1
WHERE pagina_id IN (57, 557) AND accion_js = 'aprobar';

UPDATE conf__paginas_funciones SET icono_id = 37, color_id = 7
WHERE pagina_id IN (57, 557) AND accion_js = 'visualizar';

UPDATE conf__paginas_funciones SET icono_id = 45, color_id = 4
WHERE pagina_id IN (57, 557) AND accion_js = 'anular';

UPDATE conf__paginas_funciones SET icono_id = 36, color_id = 8
WHERE pagina_id IN (57, 557) AND accion_js = 'imprimir';

COMMIT;

-- Control (solo lectura)
SELECT f.pagina_id, f.pagina_funcion_id, f.nombre_funcion, f.tabla_estado_registro_origen_id AS origen, i.icono_clase, c.color_clase
FROM conf__paginas_funciones f
LEFT JOIN conf__iconos i ON i.icono_id = f.icono_id
LEFT JOIN conf__colores c ON c.color_id = f.color_id
WHERE f.pagina_id IN (57, 557) AND f.tabla_estado_registro_id = 1
ORDER BY f.pagina_id, f.tabla_estado_registro_origen_id, f.orden;
