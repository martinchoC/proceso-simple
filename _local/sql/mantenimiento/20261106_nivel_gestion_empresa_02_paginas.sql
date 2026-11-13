-- Nivel de gestión: páginas que una empresa SIMPLIFICADA (cliente de estudio contable) no necesita.
-- PROPUESTA pendiente de aprobación: NO EJECUTAR hasta el OK.
-- Requiere 20261106_nivel_gestion_empresa_01.sql (que ya marcó todas las de bienes: stock, pedidos, remitos, bocas,
-- ubicaciones, costos, listas de proveedores).
-- Criterio: la empresa simplificada lleva comprobantes (ventas y compras), cuentas corrientes, cobranzas / pagos,
-- obligaciones, IVA, IIBB, contabilidad e impuestos. Se ocultan la operación comercial avanzada, la tesorería
-- bancaria (extractos y conciliación), las aprobaciones y la configuración técnica del sistema.
-- Por url, en los dos módulos de gestión (2 y 5), para no depender de los ids "+500".
-- Solo afecta perfiles que se creen o hereden después; para los ya creados: perfiles_quitar_funciones_simplificada().
-- Reanudable.
SET NAMES utf8mb4;

UPDATE conf__paginas
SET requiere_gestion_completa = 1
WHERE modulo_id IN (2, 5)
  AND requiere_gestion_completa = 0
  AND url IN (
      -- Ventas
      'asistente_ia.php',                -- Asistente IA (pedidos / remitos / altas por chat)
      'listas_precios_productos.php',    -- Lista de precios por producto
      'listas_precios.php',              -- Listas de precios: seteo
      'productos_consulta.php',          -- Consulta de productos (stock / precios)
      -- Compras
      'ordenes_compra.php',              -- Órdenes de compra y recepciones
      -- Tesorería bancaria y operación financiera
      'tesoreria_dashboard.php',         -- Dashboard financiero
      'cheques_cartera.php',             -- Cartera de cheques
      'tesoreria_movimientos.php',       -- Movimientos internos / transferencias
      'bancos_importar.php',             -- Extractos bancarios
      'bancos_conciliacion.php',         -- Conciliación bancaria
      'bancos_gastos.php',               -- Gastos bancarios desde el extracto
      'bancos_formatos.php',
      'bancos_conceptos.php',
      'bancos_reglas.php',
      'obligaciones_rendiciones.php',    -- Rendiciones de gastos / anticipos
      'aprobaciones_reglas.php',         -- Reglas de aprobación (doble firma)
      -- Configuración técnica del sistema (no es de la empresa)
      'paginas.php',
      'paginas_funciones.php',
      'tablas.php',
      'tablas_estados.php'
  );

SELECT modulo_id, COUNT(*) AS paginas_solo_completa
FROM conf__paginas WHERE modulo_id IN (2, 5) AND requiere_gestion_completa = 1
GROUP BY modulo_id;
