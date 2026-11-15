# Presentaciones en pedidos, remitos y facturas (paso 2 de la venta por presentación)

Estado: **aprobado el 2026-11-14 (las 6 propuestas)**, **implementado el 2026-11-15** (ver "Implementado"). Sigue al doc 19 (precios por presentación, ya implementado).
Rama `distribuidora`. Fecha: 2026-11-14.

## Política que se respeta (decidida)

- Stock y costo en unidad base. La caja es una presentación con precio propio en la lista; no es un descuento.
- Cada línea se vende en una sola presentación; el sistema no mezcla precios solo.
- Mínimo y múltiplo por presentación; disponibilidad = floor(stock base / factor), solo stock físico.
- Todo detrás de `usa_venta_presentaciones` (y `usa_presentaciones`): apagado, nada cambia.

## Lo que hay hoy (reconocimiento)

| Comprobante | Tabla de líneas | Quién la escribe | Precio |
|---|---|---|---|
| Pedido | `gestion__ventas_pedidos_detalles` | `insertarDetallesPedido` (alta, copia) y `actualizarDetallesPedido` (edición; UPDATE / INSERT / DELETE por id para no romper el vínculo con los remitos), `actualizarCantidadDetallePedido` (Gestión de pedidos) | el que manda el navegador; el buscador (`buscarProductosPorCliente`) lo trae de la lista vigente del cliente |
| Remito | `gestion__ventas_remitos_detalles` | `insertarDetallesRemito` | de una línea de pedido: **lo pisa el servidor** con el del pedido y suma `cantidad_entregada`; sin pedido, el del navegador |
| Factura | `gestion__ventas_facturas_detalles` | `vfInsertarDetallesFactura` (vía `guardarVentaFactura` / `vfEditarVentaFactura`; también el Asistente IA y la facturación de abonos del estudio) | de un remito, el del remito; sin remito, el del navegador |

- Todo está en **unidad base**: `cantidad` y `precio_unitario*` por unidad, y de ahí salen importes, IVA, el
  pendiente de entrega (`cantidad - cantidad_entregada`), el pendiente de facturar (`cantidad_facturada`) y el
  stock (`sincronizarStockRemitoVenta` / `vfSincronizarStockFactura` → `stockSincronizarComprobante`, por
  producto y cantidad).
- Precisión: precio unitario `decimal(18,6)` en las tres tablas; en facturas `precio_unitario_neto` es
  `decimal(18,2)` (solo informativo: el importe se guarda aparte).
- El JS del pedido junta por producto (`detalles.find(d => d.producto_id == ...)`): hoy hay una línea por producto.
- Copias 2 / 5: pedidos, facturas, gestión de pedidos y todos los JS son iguales (0 diferencias);
  `ventas_remitos_model.php` difiere en 126 líneas (auditoría en el 2): ese se integra a mano.
- Datos de la copia de producción: 489 líneas de pedido, 428 de remito, 2 de factura.

## Propuesta

### 1. La línea sigue en unidad base; la presentación es una "foto" opcional

Una línea vendida como "2 Caja x 12 a $ 550" se guarda como **24 unidades a $ 45,833333** (lo de siempre)
**más** cinco columnas nuevas, NULL en toda línea por unidad:

| Columna | Ejemplo | Para qué |
|---|---|---|
| `presentacion_id` | 7 | qué presentación se vendió |
| `presentacion_nombre` | Caja x 12 | foto del nombre (para imprimir aunque después se renombre) |
| `presentacion_factor` | 12 | foto del factor |
| `presentacion_cantidad` | 2 | cantidad en cajas |
| `presentacion_precio` | 550 | precio de la caja entera, antes del descuento del cliente |

Por qué así y no guardar "2 cajas" en `cantidad`: **todo lo que viene después sigue andando sin tocarlo**
(entregas parciales, pendientes, facturación de remitos, stock, asientos, IVA, ARCA, cuenta corriente, informes,
el Asistente IA). La alternativa obliga a convertir en cada uno de esos lugares.

Redondeo: el importe de la línea se calcula desde la presentación (cantidad de cajas × precio de la caja) y se
manda armado, como ya hace el pedido; el unitario de 6 decimales solo puede mover centésimos de centavo. En el
remito, que recalcula `cantidad × unitario neto`, la diferencia es menor a $ 0,01 por línea hasta 20.000 unidades.

### 2. Dónde se elige la presentación

- **Pedido**: el buscador trae, además del producto, sus ofertas en la lista del cliente
  (`lpre_ofertas`: unidad suelta si la lista la vende, y cada presentación con precio; con la disponibilidad de
  la sucursal del pedido). En la línea, un selector "Unidad / Caja x 12 / Pack x 6"; la cantidad se carga en esa
  presentación. El mismo producto puede ir en dos líneas (cajas y unidades sueltas).
- **Remito desde pedido**: hereda la presentación de la línea del pedido (el servidor ya pisa el precio con el
  del pedido; también pisa la presentación). Remito sin pedido: igual que el pedido.
- **Factura desde remito**: hereda la del remito. Factura sin remito: igual que el pedido.

### 3. Validaciones en el servidor (solo con el indicador prendido)

- La presentación es del producto, está activa y se vende (`aplica_venta`).
- `cantidad` = `presentacion_cantidad × factor` (el servidor la recalcula; no confía en el navegador).
- Mínimo y múltiplo de la presentación (`lpre_validar_cantidad`); para líneas por unidad, los del producto.
- Lista que no vende la unidad suelta: no acepta líneas por unidad.
- El precio sigue como hoy (el del navegador, que lo trae de la lista): no se agrega control de precio.

### 4. Código

- `core/ventas_presentaciones.php` (`vpre_*`): habilitación (indicador + columnas), ofertas para los
  buscadores, normalización y validación de la línea, herencia pedido → remito → factura, y la grabación de las
  columnas nuevas con un UPDATE aparte por línea (regla 8: no se agregan columnas a los INSERT / UPDATE
  existentes).
- `assets/js/ventas-presentaciones.js`: selector de presentación en la línea, conversión y totales; lo llaman
  `ventas_pedidos.js`, `ventas_remitos.js`, `ventas_facturas.js` y `ventas_remitos_pendientes_facturacion.js`.
- En los modelos de los módulos (2 y 5), cambios chicos y condicionales:
  - `insertarDetallesPedido` / `actualizarDetallesPedido` / `insertarDetallesRemito` / `vfInsertarDetallesFactura`:
    devolver los ids de línea grabados y, si `vpre_activo`, llamar a `vpre_*` después de cada línea.
  - `actualizarCantidadDetallePedido`: si la línea tiene presentación, la cantidad nueva tiene que ser múltiplo
    del factor y se actualiza `presentacion_cantidad`.
  - Los tres buscadores: agregar `ofertas` a cada producto si `vpre_activo`.
- Impresiones (`templates/impresion/`, `*_print.php`): con presentación, mostrar "2 Caja x 12 × $ 550" en vez de
  "24 × $ 45,83" (en la factura hoy se imprimiría 45,83 × 24 = 1.099,92 contra un importe de 1.100,00).

### 5. Migración (un script, solo agregado, reanudable)

`20261115_venta_presentaciones_02_lineas.sql`: las cinco columnas, NULL por defecto, en
`gestion__ventas_pedidos_detalles`, `gestion__ventas_remitos_detalles` y `gestion__ventas_facturas_detalles`,
cada una con su chequeo en `information_schema`.

### 6. Orden de trabajo (cada uno con su commit y su prueba)

| Paso | Qué | Prueba |
|---|---|---|
| 2a | Migración + `core/ventas_presentaciones.php` | prueba de la API sobre la base de prueba y tolerancia sin columnas |
| 2b | Pedido (buscador, línea, grabado, Gestión de pedidos) | comparación antes / después + caso nuevo del simulador |
| 2c | Remito (herencia desde pedido y remito directo) | ídem |
| 2d | Factura (herencia desde remito y factura directa) | ídem |
| 2e | Impresiones de pedido, remito y factura | revisión visual |

**Prueba de comparación** (regla 6, código compartido): con el indicador apagado, los casos de ventas del
simulador (`03_ventas`, `04_notas_credito_ventas`, `06_consumidor_final`, y `23_global_n3_circuitos` en los dos
módulos) tienen que dar lo mismo antes y después, más una foto de las líneas que generan (todas las columnas
de hoy, sin ids) que tiene que ser idéntica. La corrida "antes" ya está hecha sobre el código de `7534c5b`.
Con el indicador prendido, un caso nuevo `33_ventas_presentaciones.py`: pedido con cajas y unidades, remito
parcial, factura del remito, stock descontado en unidades.

## Qué no cambia

Con el indicador apagado: nada (las columnas nuevas quedan en NULL y ninguna consulta existente las lee).
Con el indicador prendido, las líneas por unidad funcionan como hoy, salvo los mínimos / múltiplos y las listas
sin unidad suelta. Stock, costos, asientos, ARCA y cuenta corriente no se tocan en ningún caso.

## Fuera de este paso

Presupuestos, notas de crédito (hoy copian las líneas de la factura en unidades: siguen así), el carrito
(`casalucho-carrito`), descuentos por cantidad y promociones (paso 4).

## Decisiones (aprobadas el 2026-11-14: las 6 propuestas, tal cual)

1. **Representación**: ¿unidad base + foto de la presentación (propuesto) o guardar la cantidad en presentaciones?
2. **Entrega parcial en el remito** de una línea por caja: ¿solo cajas enteras (propuesto; el depósito no abre
   cajas) o también se puede abrir la caja y entregar unidades?
3. **Mínimo / múltiplo en remitos y facturas directos** (sin pedido): ¿se exigen igual que en el pedido
   (propuesto) o solo en el pedido?
4. **Disponibilidad en el buscador del pedido**: ¿solo se muestra (propuesto; el pedido no reserva) o también
   avisa / bloquea si no alcanza?
5. **Mismo producto en dos líneas** (cajas y sueltas): ¿se permite (propuesto) o se fuerza una línea por producto?
6. **Precio**: ¿se sigue confiando en el que trae la pantalla de la lista, como hoy (propuesto), o el servidor
   debe controlar que coincida con la lista?

## Implementado (2026-11-15)

| Paso | Commit | Qué |
|---|---|---|
| Migración | `3462b37` | `20261115_venta_presentaciones_02_lineas.sql` (5 columnas NULL en las tres tablas de líneas) y este documento |
| 2a | `37d2595` | `core/ventas_presentaciones.php` (`vpre_*`) |
| 2b | `ca6b40b` | Pedidos: buscador con ofertas, líneas en cajas, Gestión de pedidos; `assets/js/ventas-presentaciones.js` |
| 2c | `1dbc386` | Remitos: herencia desde el pedido (solo cajas enteras) y remito directo |
| 2d | `4793d93` | Facturas: herencia desde remito / pedido y factura directa |
| 2e | `ce08ddb` | Impresiones: `vpre_lineas_impresion()` antes de la plantilla (plantillas sin cambios) |

Pruebas: caso de simulación `33_ventas_presentaciones.py` (37 controles: pedido, remito, factura, stock e
impresiones con el indicador prendido) y comparación antes / después de los casos 03, 04, 06, 09 y 19b sin el
indicador (mismos controles y mismas líneas generadas, con y sin la migración).

Diferencias con el Prompt 2 original (por las decisiones de los docs 19 y 20): el indicador se llama
`usa_venta_presentaciones`; las columnas son `presentacion_*` (no `cantidad_presentacion`); mínimo y múltiplo van
por presentación (`venta_minimo` / `venta_multiplo`) y no hay `permite_fraccionar` (la presentación nunca se
fracciona; la unidad suelta sigue con los decimales de siempre); una presentación sin precio en la lista no se
ofrece (no se calcula unitario × factor).

Pendiente conocido: el modal de remito dentro de `ventas_pedidos.js` y `ventas_remitos_pendientes_facturacion.js`
siguen mostrando unidades (el servidor igual exige cajas enteras); notas de crédito y presupuestos, fuera de alcance.
