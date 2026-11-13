-- Estudio contable: la gestión propia del estudio (sus recibos y cobranzas con retenciones, su plan de cuentas y sus
-- productos / conceptos facturables) se lleva en el módulo de Gestión (2 / 5), no en páginas duplicadas del estudio.
-- Se inactivan (estado 2) las páginas y sus funciones; para un usuario común una página es accesible si tiene funciones
-- activas asignadas (core/permisos.php), así que sin las funciones quedan cerradas también por URL y por AJAX.
--   415 Recibos y Cobranzas (Con Retenciones)  -> Gestión > Tesorería > Cobranzas (105 / 605)
--   416 Plan de Cuentas Contable                -> Gestión > Contabilidad > Plan de Cuentas (68 / 568)
--   414 Conceptos de Mini-Gestión (gestion__productos) -> Gestión > Productos (40 / 540)
-- Quedan en el estudio porque Gestión no las tiene: 413 Cierre de Ejercicio, 410 Activos Fijos y 412 Parámetros
-- Contables (gestion__cont_parametros, que usan esas dos).
-- No se borran permisos ni datos. Revertir: volver a 1 el estado de esas páginas y funciones.
-- Reanudable.
SET NAMES utf8mb4;

UPDATE conf__paginas_funciones SET tabla_estado_registro_id = 2
WHERE pagina_id IN (414, 415, 416) AND tabla_estado_registro_id = 1;

UPDATE conf__paginas SET tabla_estado_registro_id = 2
WHERE pagina_id IN (414, 415, 416) AND modulo_id = 4 AND tabla_estado_registro_id = 1;

-- 414 había pasado a "Gestión de Clientes" (20261107_estudio_gestion_clientes_01.sql en la base local): vuelve a su carpeta
UPDATE conf__paginas SET padre_id = 400, orden = 14 WHERE pagina_id = 414 AND padre_id = 421;
