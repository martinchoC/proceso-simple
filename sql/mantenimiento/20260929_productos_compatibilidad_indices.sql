-- Índices para consultas de gestion__productos_compatibilidad por
-- marca / modelo / submodelo.
--
-- Síntoma: en submodelos.php, pestañas Dashboard / Jerárquico, el resumen
-- de productos por marca → modelo → submodelo tardaba ~5-7 s. El único
-- índice secundario de la tabla es `producto_id`
-- (empresa_id, producto_id, marca_id, modelo_id, submodelo_id), que no sirve
-- para buscar por submodelo/modelo/marca: por cada uno de los ~1.000
-- submodelos se recorrían todas las compatibilidades de la empresa.
--
-- Los tres índices son "cubrientes" para esas consultas (incluyen estado y
-- producto_id), así MariaDB resuelve el conteo sin leer la fila completa.
-- También benefician los filtros por marca/modelo/submodelo de
-- listas_precios_productos, productos_proveedores y proveedor_precios.
--
-- Idempotente: ADD INDEX IF NOT EXISTS (MariaDB >= 10.1.4).

ALTER TABLE gestion__productos_compatibilidad
    ADD INDEX IF NOT EXISTS idx_compat_submodelo (empresa_id, submodelo_id, tabla_estado_registro_id, producto_id),
    ADD INDEX IF NOT EXISTS idx_compat_modelo (empresa_id, modelo_id, tabla_estado_registro_id, submodelo_id, producto_id),
    ADD INDEX IF NOT EXISTS idx_compat_marca (empresa_id, marca_id, tabla_estado_registro_id, producto_id);

-- Tablas maestras: hoy solo tienen índice por el padre, sin empresa_id.
ALTER TABLE gestion__submodelos
    ADD INDEX IF NOT EXISTS idx_submodelos_empresa_modelo (empresa_id, modelo_id);

ALTER TABLE gestion__modelos
    ADD INDEX IF NOT EXISTS idx_modelos_empresa_marca (empresa_id, marca_id);

ALTER TABLE gestion__marcas
    ADD INDEX IF NOT EXISTS idx_marcas_empresa (empresa_id);
