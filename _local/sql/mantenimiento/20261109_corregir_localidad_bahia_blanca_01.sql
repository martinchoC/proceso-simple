-- Corrección de localidad_id de Bahía Blanca en empresas y sucursales.
-- Localidad 26985 correspondía a Bahía Blanca, Santiago del Estero (CP 3749).
-- La localidad correcta de Bahía Blanca, Buenos Aires (CP 8000) es 16353 (provincia_id = 10).
-- Mar del Plata corresponde a 20900 (CP 7600, provincia_id = 10).

SET NAMES utf8mb4;

UPDATE conf__empresas 
SET localidad_id = 16353 
WHERE localidad_id = 26985;

UPDATE gestion__sucursales 
SET localidad_id = 16353 
WHERE localidad_id = 26985;

UPDATE gestion__sucursales 
SET localidad_id = 20900 
WHERE sucursal_nombre LIKE '%Mar del Plata%' AND (localidad_id IS NULL OR localidad_id = 1);

