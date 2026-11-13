-- =====================================================================
-- Carga inicial de gestion__entidades_condiciones_iibb según la localidad
-- de la entidad (gestion__entidades.localidad_id).
--
-- Camino: entidad.localidad_id → conf__localidades.provincia_id
--         → gestion__iibb_jurisdicciones_sedes (misma provincia_id;
--           equivale a la jurisdicción provincial de gestion__jurisdicciones).
--
-- - Régimen: 1 Local (Directo), número de inscripción = CUIT de la entidad
--   (también en las condiciones ya cargadas sin número).
-- - Vigencia: desde 2000-01-01, abierta, para que valga también para los
--   comprobantes anteriores a hoy.
-- - Solo entidades no eliminadas, sin ninguna condición IIBB cargada
--   (se puede volver a correr: no duplica).
-- - Las entidades sin localidad o con un localidad_id que no existe en
--   conf__localidades quedan sin cargar (ver la consulta del final).
-- =====================================================================

INSERT INTO gestion__entidades_condiciones_iibb
       (entidad_id, regimen_iibb_id, numero_inscripcion, jurisdiccion_sede_id,
        f_desde, f_hasta, observaciones, tabla_estado_registro_id)
SELECT e.entidad_id, 1, IF(e.cuit > 0, CAST(e.cuit AS CHAR), NULL), s.jurisdiccion_sede_id,
       '2000-01-01', NULL, 'Carga inicial según la localidad de la entidad', 1
  FROM gestion__entidades e
  JOIN conf__localidades l
    ON l.localidad_id = e.localidad_id
  JOIN gestion__iibb_jurisdicciones_sedes s
    ON s.provincia_id = l.provincia_id
   AND s.tabla_estado_registro_id = 1
 WHERE e.tabla_estado_registro_id <> 6
   AND NOT EXISTS (SELECT 1
                     FROM gestion__entidades_condiciones_iibb c
                    WHERE c.entidad_id = e.entidad_id);

-- N° de inscripción = CUIT en las condiciones que no lo tienen
UPDATE gestion__entidades_condiciones_iibb c
  JOIN gestion__entidades e ON e.entidad_id = c.entidad_id
   SET c.numero_inscripcion = CAST(e.cuit AS CHAR)
 WHERE (c.numero_inscripcion IS NULL OR TRIM(c.numero_inscripcion) = '')
   AND e.cuit > 0;

-- Entidades que no se pudieron resolver (para corregirles la localidad)
SELECT e.empresa_id, e.entidad_id, e.entidad_nombre, e.localidad_id
  FROM gestion__entidades e
  LEFT JOIN conf__localidades l ON l.localidad_id = e.localidad_id
 WHERE e.tabla_estado_registro_id <> 6
   AND l.localidad_id IS NULL
 ORDER BY e.localidad_id, e.entidad_nombre;
