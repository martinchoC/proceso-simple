# Pantalla de mostrador minorista

Estado: **implementado** (2026-11-16, commit `47aa3c2`; plan aprobado el mismo día con las 5 recomendaciones). Rama `distribuidora`. Prompt 9 de la etapa 4 (doc 28): solo el plan.

## Pedido

Una pantalla rápida para la sucursal que vende minorista (la otra es solo mayorista): lector de código de barras,
unidades y cajas, cobro y factura en el momento, reutilizando `core/`. Mismo precio que el ERP para el mismo cliente.

## Cómo se factura hoy a consumidor final (verificado)

- Es una **factura de venta directa sin cliente** (página Facturas de venta 56 / 556, `ventas_facturas_model.php` del
  módulo): `entidad_id = 0` = consumidor final; la lista es la **Mostrador** (código `MOST`, empresa 2: lista 3) por
  `vfObtenerCondicionCliente` / `cvta_lista_cliente` (doc 21: sin cliente, MOST).
- Con canales de venta, `cvta_exigir_venta_sucursal` exige que la sucursal venda el canal de MOST: la sucursal solo
  mayorista rechaza la venta de mostrador (doc 21).
- Presentaciones (`vpre_*`, tipo `factura`) y reglas de precio (`prv_*`, con el tope del usuario exigido en la factura
  directa) ya corren en la factura.
- Cobro: al **aprobar** (`aprobada`), una factura sin cliente pide los medios de contado (`cobro_contado_info` +
  `medios_contado`: efectivo, transferencia, cheque; sin medios, efectivo) y genera el cobro (casos 30 y 30b del
  simulador). Con ARCA habilitado, "Aprobada" pide el CAE y recién ahí numera.
- Impresión: `templates/impresion/factura_ticket80.php` (ticket 80 mm) y `recibo_ticket80.php` ya existen.
- A verificar en el paso 1: que la factura directa (sin remito) baje el stock en el tipo de comprobante que use el
  mostrador.

## Propuesta

1. **Página nueva del ERP "Mostrador"** (no una app aparte: el cajero es usuario del ERP, con su sucursal y sus
   accesos): pantalla completa, foco siempre en el campo del lector (los lectores USB / Bluetooth escriben como
   teclado + Enter; con la cámara, `BarcodeDetector` como en la app del vendedor).
2. **Escaneo**: el código de barras de una presentación agrega 1 caja; el del producto, 1 unidad (si la lista MOST
   vende la unidad); búsqueda por nombre / código como respaldo. Cajas y unidades en líneas separadas; + / − y
   cantidad editable.
3. **Precios y líneas con el núcleo**: extender `pven_ofertas` / `pven_valorizar` a "consumidor final o cliente" y a
   las fórmulas de la línea de factura (hoy `pven_*` arma líneas de pedido). Cliente opcional: por defecto consumidor
   final (MOST); se puede buscar un cliente (su lista y condiciones).
4. **Cobrar**: total a pagar, medios (efectivo con vuelto, transferencia, tarjeta si existe el medio, cheque) y
   "Cobrar y facturar": alta de la factura con `guardarVentaFactura`, `confirma` y `aprobada` con `medios_contado`,
   todo por las funciones del modelo de facturas del módulo (las mismas de la pantalla). Con ARCA, espera el CAE y
   avisa si queda Pendiente de ARCA. Imprime el ticket 80 mm.
5. **Código**: lógica en `core/ventas_mostrador/`, vista en `templates/mostrador/`, JS en `assets/js/mostrador.js`;
   en `modules/gestion*/` solo `mostrador.php` y `mostrador_ajax.php` finos (fijan página y módulo), como Libros
   societarios o el Asistente IA. Esto toca `modules/`: los dos archivos van iguales en el 2 y el 5 con los ids de
   cada módulo.
6. **Migración** (solo agregado): indicador `conf__empresas.usa_mostrador` (apagado por defecto); página 150 / 650
   bajo Ventas, funciones `ver` / `facturar` con ids del bloque (3620– / 8620–), permisos para los perfiles de
   Ventas y Administrador (`conf__perfiles_funciones` y `conf__empresas_perfiles_funciones`), `requiere_bienes = 1`.
7. **Pruebas**: caso del simulador (escaneo de caja y unidad, mínimos, escala / promoción, cobro con vuelto y dos
   medios, factura aprobada, stock, sucursal mayorista que rechaza, apagado: la página no se ve) y la comparación
   antes / después de ventas (03, 04, 06, 09, 19b) si se toca código compartido de facturas.

## Decisiones a aprobar

1. **Página del ERP** (no app aparte ni modo de la app del vendedor). Recomendado.
2. **Cliente opcional** (consumidor final por defecto). Recomendado.
3. **Factura en el momento con cobro de contado** al confirmar (no pedido ni remito). Recomendado.
4. **Medios**: los de contado que ya existen (efectivo, transferencia, cheque) + tarjeta solo si la empresa tiene ese
   medio de cobro; sin integración con posnet. Recomendado.
5. **Sin modo sin conexión ni caja / arqueo propio** en esta etapa (el cobro va a la cuenta de tesorería del medio,
   como hoy). Recomendado.

## Pasos (un commit cada uno)

| Paso | Qué |
|---|---|
| 10-1 | Migración + indicador + página / funciones / permisos |
| 10-2 | `pven_*` para consumidor final y líneas de factura (núcleo) + prueba |
| 10-3 | Pantalla: lector, cajas / unidades, totales |
| 10-4 | Cobro y factura (confirma + aprobada con medios), ticket, ARCA |
| 10-5 | Caso del simulador y cierre del documento |

## Implementado

- `20261124_mostrador_01.sql`: `usa_mostrador`, página 150 / 650 bajo Ventas, funciones 3620 / 3621 y 8620 / 8621
  (consultar, facturar), permisos a los perfiles base Administrador y a los perfiles que agregan facturas de venta.
  Aplicado solo en la copia del simulador.
- `core/ventas_mostrador/mostrador_ajax.php` (vmos_*), `templates/mostrador/mostrador_vista.php`, `assets/js/mostrador.js`;
  en `modules/gestion*/` solo `mostrador.php` y `mostrador_ajax.php`. `pven_ofertas` / `pven_valorizar` aceptan
  consumidor final (`pven_cliente_o_cf`: lista Mostrador). La impresión de la factura admite la página del mostrador.
  Interruptor "Usa el mostrador" en Empresas.
- La factura es una factura de venta común (página 56 / 556): `guardarVentaFactura` + `confirma` + `aprobada` con los
  medios de contado (consumidor final; el vuelto sale del efectivo) o a la cuenta corriente del cliente.
- Verificado: la factura directa del mostrador baja el stock (caso 46: −2 u y −12 u de una caja).
- Pruebas: caso 46 (apagado, lista Mostrador, código de barras de la caja, medios que no cubren, factura aprobada y
  numerada, stock, caja de tesorería, cliente, permisos y CSRF) 16/16; ventas 03-19b iguales.
- No probado en el navegador. Sin caja / arqueo propio ni modo sin conexión (decisión 5).
