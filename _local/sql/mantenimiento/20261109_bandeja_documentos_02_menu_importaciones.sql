-- Nueva sección de menú: Importación de Comprobantes (módulo 2 página 145, módulo 5 página 645).
-- Mueve la Bandeja de Documentos (páginas 141 y 641) fuera de Compras a su propio grupo de menú.
-- Reanudable e idempotente.

SET NAMES utf8mb4;

-- 1. Icono para Bandeja / Captura
INSERT INTO conf__iconos (icono_id, icono_nombre, icono_clase, tabla_estado_registro_id)
SELECT 147, 'Bandeja / Captura', 'bi bi-inbox-fill', 1
WHERE NOT EXISTS (SELECT 1 FROM conf__iconos WHERE icono_id = 147);

-- 2. Páginas contenedor (padre_id = 0)
INSERT INTO conf__paginas (pagina_id, modulo_id, pagina, url, icono_id, pagina_descripcion, padre_id, orden,
       tabla_id, requiere_bienes, pagina_patron_id, tabla_estado_registro_id, es_acceso_directo)
SELECT v.id, v.modulo, 'Importación de Comprobantes', '#', 147,
       'Captura de comprobantes (facturas, tickets, extractos) desde archivos, fotos, ARCA o enlaces externos',
       0, v.orden, NULL, 0, 1, 1, 0
FROM (
              SELECT 145 AS id, 2 AS modulo, 280 AS orden
    UNION ALL SELECT 645 AS id, 5 AS modulo, 320 AS orden
) v
WHERE NOT EXISTS (SELECT 1 FROM conf__paginas p WHERE p.pagina_id = v.id);

-- Si ya existía, asegurar título e icono
UPDATE conf__paginas
SET pagina = 'Importación de Comprobantes',
    icono_id = 147,
    padre_id = 0,
    url = '#'
WHERE pagina_id IN (145, 645);

-- 3. Mover la Bandeja de Documentos bajo su nuevo padre
UPDATE conf__paginas
SET padre_id = 145, orden = 10, icono_id = 147
WHERE pagina_id = 141;

UPDATE conf__paginas
SET padre_id = 645, orden = 10, icono_id = 147
WHERE pagina_id = 641;

