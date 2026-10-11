-- =====================================================================
-- Tesorería - script 06: reparar asientos automáticos sin fecha ni descripción
-- 2026-10-08
--
-- Problema: tesoreriaGenerarAsiento() (tesoreria_core_model.php) tenía el
-- string de tipos del INSERT de la cabecera corrido una posición
-- ("iiiiisiisidiii" en vez de "iiiiiisiisidii"): f_asiento se grababa como
-- entero (0000-00-00) y descripcion como entero ('0'). Afectaba a todo asiento
-- de tesorería creado por primera vez (recibos, órdenes de pago, cheques,
-- movimientos, rendiciones). Esos asientos no aparecen en libros filtrados por
-- fecha. El código ya está corregido; esto repara los existentes.
-- Encontrado en la simulación de circuitos del 2026-10-08.
--
-- Solo toca asientos con comprobante (los de tesorería). Los asientos 10/15/17/20
-- del volcado local (mayo, comprobante_id = 0) tienen otro origen y no se tocan.
-- La fecha sale de f_contabilidad (o f_emision) del comprobante; la
-- descripción, de tipo + número + entidad del comprobante.
--
-- Reanudable: solo actualiza filas que siguen rotas.
-- =====================================================================

UPDATE gestion__cont_asientos a
JOIN gestion__comprobantes c ON c.comprobante_id = a.comprobante_id
SET a.f_asiento = COALESCE(c.f_contabilidad, c.f_emision),
    a.anio = YEAR(COALESCE(c.f_contabilidad, c.f_emision)),
    a.mes = MONTH(COALESCE(c.f_contabilidad, c.f_emision))
WHERE a.comprobante_id > 0
  AND a.f_asiento = '0000-00-00'
  AND COALESCE(c.f_contabilidad, c.f_emision) IS NOT NULL;

UPDATE gestion__cont_asientos a
JOIN gestion__comprobantes c ON c.comprobante_id = a.comprobante_id
LEFT JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = c.comprobante_tipo_id
LEFT JOIN gestion__entidades e ON e.entidad_id = c.entidad_id
SET a.descripcion = LEFT(CONCAT(COALESCE(ct.comprobante_tipo, 'Comprobante'), ' N° ',
                                LPAD(c.comprobante_nro, 8, '0'),
                                IF(e.entidad_nombre IS NULL, '', CONCAT(' - ', e.entidad_nombre))), 255)
WHERE a.comprobante_id > 0
  AND (a.descripcion = '0' OR a.descripcion IS NULL OR a.descripcion = '');

-- Verificación: no debe quedar ninguno con comprobante y fecha/descripcion rota
SELECT COUNT(*) AS asientos_rotos
FROM gestion__cont_asientos
WHERE comprobante_id > 0
  AND (f_asiento = '0000-00-00' OR descripcion = '0' OR descripcion IS NULL OR descripcion = '');
