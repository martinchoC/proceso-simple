# Presentaciones en órdenes de compra y facturas de proveedor

Estado: **plan a aprobar** (2026-11-15). Rama `distribuidora`. Es la otra mitad de la etapa 2 del doc 17: las ventas
ya usan presentaciones (doc 20); las compras siguen en unidades.

## Pedido

"Vamos directo a presentaciones en compras" (2026-11-15). Para la distribuidora: al proveedor se le compra en cajas,
packs o bultos ("10 cajas x 12 a $ 9.600 la caja"), y el stock y el costo se siguen llevando por unidad.

## Lo que hay hoy (base local y código, solo lectura)

| Comprobante | Tabla de líneas | Quién la escribe | Notas |
|---|---|---|---|
| Orden de compra | `gestion__ordenes_compra_detalle` | `insertarDetallesOrden` (alta y edición: la edición borra y vuelve a insertar las líneas) | `cantidad decimal(12,4)`, `precio_unitario decimal(12,6)`; `cantidad_recibida` la recalcula `ocRecalcularRecepcion` desde las facturas |
| Factura / nota de proveedor | `gestion__facturas_proveedores_detalle` | `insertarDetallesFactura` (pantalla, factura desde OC con `ordenes_compra_detalle_id`, lectura de archivos / IA) y `fpImportarComprobantesArca` (sin producto) | **`cantidad` y `precio_unitario` con 2 decimales**; el servidor calcula `neto = cantidad × precio_unitario` antes de redondear; `precio_unitario_neto` (2 decimales) **es el costo** que leen `registrarStockFacturaProveedor` / `actualizarCostosProducto` y la actualización de precios por compra |

- Stock y costo por unidad base; el stock entra con la factura (`registrarStockFacturaProveedor`).
- Las presentaciones ya tienen `aplica_compra` y `es_compra_defecto` desde la etapa 1 (doc 17). La empresa de prueba
  1000 tiene 123 presentaciones, todas de compra.
- Las pantallas arman **una línea por producto** (`ordenes_compra.js` busca la línea por `producto_id`).
- La lectura de facturas (`core/facturas_lectura/`) encuentra el producto por `gestion__productos_proveedores.codigo_proveedor`;
  la cantidad que lee es la del proveedor (si factura cajas, lee cajas).
- Los archivos de compras son **iguales en los dos módulos** (`ordenes_compra*`, `facturas_proveedores*`).
- Datos locales: 100 líneas de OC y 151 de factura de proveedor.

## Propuesta

Mismo modelo que ventas (doc 20), detrás de un indicador nuevo `conf__empresas.usa_compra_presentaciones` (apagado
por defecto; necesita también `usa_presentaciones`). Apagado, nada cambia.

1. **La línea sigue en unidad base; la presentación es una foto opcional.** "10 Caja x 12 a $ 9.600" se guarda como
   120 unidades a $ 800 (lo de siempre) más cinco columnas nuevas, NULL en toda línea por unidad:
   `presentacion_id`, `presentacion_nombre`, `presentacion_factor`, `presentacion_cantidad`, `presentacion_precio`
   (precio de la caja del proveedor, antes de descuentos). Recepción, stock, IVA, asiento, cuenta corriente y
   pendientes de la OC siguen sin tocarse porque todo sigue en unidades.
2. **Dónde se elige**:
   - OC y factura cargadas a mano: en la línea, un selector "Unidad / Caja x 12 / …" con las presentaciones de
     compra del producto (`aplica_compra`), con la de compra por defecto preseleccionada; la cantidad y el precio
     se cargan en esa presentación. El mismo producto puede ir en dos líneas (cajas y sueltas).
   - Factura desde una OC: hereda la presentación de la línea de la OC.
   - Notas de crédito / débito de proveedor: son la misma pantalla y la misma tabla, entran igual.
3. **Validaciones en el servidor** (con el indicador): la presentación es del producto, está activa y es de compra;
   `cantidad = presentacion_cantidad × factor` (lo recalcula el servidor). En compras **no** se exigen mínimos ni
   múltiplos (son de venta) y se aceptan cantidades con decimales (lo que facturó el proveedor).
4. **Costo con precisión**: hoy el costo sale de `precio_unitario_neto` con 2 decimales (una caja de $ 1.000 x 12
   da $ 83,33 por unidad). En las líneas **con presentación**, el costo unitario se calcula desde los importes de
   la línea (neto ÷ unidades, 4 decimales), no desde la columna redondeada. Las líneas sin presentación siguen
   exactamente como hoy (no se cambia el tipo de ninguna columna).
5. **Código**: `core/compras_presentaciones.php` (`cpre_*`) con habilitación, presentaciones de compra para los
   buscadores, normalización / validación de la línea, herencia OC → factura, grabación de la foto con un UPDATE
   aparte por línea (regla 8) y líneas para imprimir; `assets/js/compras-presentaciones.js` para el selector y los
   totales. En los modelos (2 y 5, idénticos): `insertarDetallesOrden` e `insertarDetallesFactura` devuelven los ids
   de línea y, con el indicador, llaman a `cpre_*`; `registrarStockFacturaProveedor`, el ajuste del costo del punto 4.
   Impresión de la OC y de la factura: "10 Caja x 12 × $ 9.600 (120 u)".
6. **Migración** (solo agregado, reanudable): `20261122_compra_presentaciones_01.sql`: el indicador y las cinco
   columnas en `gestion__ordenes_compra_detalle` y `gestion__facturas_proveedores_detalle`.

## Pasos

| Paso | Qué | Prueba |
|---|---|---|
| 8-1 | Migración + `core/compras_presentaciones.php` + interruptor en Empresas | API sobre la copia y tolerancia sin columnas |
| 8-2 | Orden de compra (buscador, línea, grabado, edición, impresión) | comparación antes / después + caso nuevo del simulador |
| 8-3 | Factura de proveedor (a mano, desde OC, notas) + costo del punto 4 + impresión | comparación antes / después + caso del simulador (stock y costo) |
| 8-4 | (si se aprueba la decisión 4) Presentación del proveedor y lectura de facturas | caso del simulador |

Comparación antes / después con el indicador apagado: casos de compras **01, 05, 08, 11 y 13** (más los de ventas
03, 04, 06, 09 y 19b, porque el costo alimenta los precios de venta) y una foto de las líneas de OC y factura de
proveedor que generan (todas las columnas, sin ids ni fechas).

## Decisiones a aprobar

1. **Indicador propio** `usa_compra_presentaciones` (separado del de ventas: una empresa puede comprar en cajas y
   vender por unidad, o al revés). **Recomendado.**
2. **Mismo modelo que ventas**: línea en unidades + foto de la presentación. **Recomendado.**
3. **Costo desde los importes** en las líneas con presentación (punto 4), sin tocar las demás. **Recomendado.**
4. **Presentación del proveedor**: columna opcional `presentacion_id` en `gestion__productos_proveedores` ("este
   proveedor lo vende en Caja x 12"), para que la OC, la factura y la **lectura de facturas** usen esa presentación
   por defecto cuando el código del proveedor es el de la caja. **Recomendado como paso 8-4**, después de probar
   8-2 y 8-3; si no, la lectura sigue cargando en unidades y se corrige a mano.
5. **Fuera de este paso**: lista de precios del proveedor por presentación (`proveedor_precios`), presupuestos /
   pedidos de compra (`gestion__compras_pedidos`, hoy sin uso) y la importación de ARCA (sin productos).
   **Recomendado.**
