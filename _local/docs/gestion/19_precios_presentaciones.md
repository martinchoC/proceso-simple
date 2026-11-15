# Precios por presentación, mínimo / múltiplo y disponibilidad

Distribuidora, paso 1 (modelo de datos). Reconocimiento y propuesta, 2026-11-14, rama `distribuidora`.
Sigue a `17_productos_presentaciones.md` (etapa 1: presentaciones en el ABM) y a `18_listas_precios_correcciones.md`.
**Solo diseño: no hay código ni migración todavía.** Esperando aprobación.

## Política (decidida por Pablo)

- Un producto con presentaciones; stock y costo en unidad base. La caja es una presentación con **precio propio en
  la lista**, no un descuento.
- Cada línea de pedido se vende en **una sola presentación**; el sistema no mezcla precios por su cuenta.
- **Mínimo y múltiplo por presentación.** Disponibilidad de una presentación = floor(stock base / factor).
- El **canal** (minorista / mayorista) es un atributo del cliente: sale de su lista de precios vigente en
  `gestion__entidades_condiciones_clientes` (confirmado 2026-11-14). La sucursal solo restringe qué canales vende.
  El mayorista compra bultos; el minorista, unidad y bulto.
- **Depósito** de una sucursal = sus bocas con `es_deposito = 1`; puede haber varias y se suma el stock de todas
  (confirmado 2026-11-14).
- El carrito se integra con `casalucho-carrito` (tablas `ecom__`): lo que necesite sale de `core/`.

Esto corrige lo que dice el doc 17 en la etapa 2 ("precio por defecto unitario × factor", "mínimo y múltiplo en el
producto"): manda la política de arriba.

## Lo que hay hoy (reconocimiento)

| Tema | Estado | Dónde |
|---|---|---|
| Presentaciones | Tabla 462 `gestion__productos_presentaciones`: nombre, `factor`, `codigo_barras`, peso y medidas, `aplica_compra` / `aplica_venta`, `es_compra_defecto` / `es_venta_defecto`, orden, estado 1 / 2. Sin precio, sin mínimo ni múltiplo. | `core/productos_presentaciones.php` (`prpr_*`), `templates/productos/presentaciones_seccion.php`, `assets/js/productos-presentaciones.js` |
| Indicador | `conf__empresas.usa_presentaciones`. Lectura tolerante en `prpr_empresa_usa()` (tabla + columna, si falta alguna = apagado). Local: solo la empresa 1000. **La copia de producción tiene la columna pero no la tabla**: el caso de la regla 9 ya pasa en la práctica. | `core/productos_presentaciones.php:38-59` |
| Precios de lista | `gestion__listas_precios_productos`: una fila vigente por (lista, producto), garantizado solo por código (no hay clave única). La leen 9 archivos: recálculo, los dos motores, importación, pedidos, remitos, facturas, exportación offline y el asistente IA. | ver doc 18 |
| Lista del cliente | `obtenerListaPrecioVigenteCliente()` lee `gestion__entidades_condiciones_clientes` (lista, condición de pago, descuento general, límite de crédito, `entidad_cliente_tipo_id`). | `ventas_pedidos_model.php:1347` |
| Tipo de cliente | `gestion__entidades_clientes_tipos` (Distribuidor, Minorista, Comercio, Gobierno, Otro; catálogo global, con `acceso_web`). En Casa Lucho casi todos son Distribuidor con la lista General. **No es el canal** (lo decidido es la lista), pero hay que evitar que se confundan en pantalla. | |
| Stock | `gestion__stock` por (sucursal, boca, producto), en unidad base. Solo stock físico: **el pedido no reserva**; mueven stock los remitos (`stockSincronizarComprobante`). | `stock_core_model.php` |

## Propuesta

### 1. Indicador nuevo: `conf__empresas.usa_venta_presentaciones`

Separado de `usa_presentaciones` (que hoy solo habilita cargarlas en el ABM): una empresa puede tener las
presentaciones cargadas sin venderlas todavía, y prender la venta cambia pedidos, remitos y facturas. La venta
por presentación exige los dos prendidos. Lectura tolerante, igual que `prpr_empresa_usa()` (columna ausente =
apagado), en `core/`.

### 2. Precio por presentación: tabla nueva, no filas nuevas en la tabla de precios

`gestion__listas_precios_presentaciones`:

| Columna | Uso |
|---|---|
| `lista_precio_presentacion_id` | PK |
| `empresa_id`, `lista_precio_id`, `presentacion_id` | |
| `producto_id` | redundante (sale de la presentación), para filtrar sin JOIN |
| `precio_final` | precio de **la presentación entera** (la caja de 12, no el unitario) |
| `f_desde`, `f_hasta` | vigencia, mismo criterio que los precios de producto |
| `observaciones`, `creado_por`, `creado_en`, `actualizado_en` | |
| `tabla_estado_registro_id` | 1 vigente / 2 reemplazada (al cambiar el precio se desactiva la fila y se inserta otra, como la importación) |

Índice por (empresa, lista, presentación, estado). Historial: las filas en estado 2 **son** el historial (no hace
falta una tabla aparte).

Por qué una tabla aparte y no una columna `presentacion_id` en `gestion__listas_precios_productos`: esa tabla
supone una fila vigente por (lista, producto) y la leen 9 archivos con `LIMIT 1` o validando duplicados. Con filas de
presentación en la misma tabla, el recálculo las borraría, el buscador de pedidos podría tomar el precio de la caja
como unitario y el alta manual rechazaría el duplicado. Con una tabla aparte, **ninguna consulta existente cambia**
(regla 8) y lo nuevo solo corre con el indicador prendido.

Sin precio en la lista = esa presentación **no se ofrece** en esa lista (no se calcula unitario × factor por su
cuenta). En pantalla se puede ofrecer un botón "proponer precio" (unitario × factor, editable) como ayuda; no se
graba solo.

### 3. Qué presentaciones vende cada canal

El canal es la lista. Para "el mayorista compra bultos": columna nueva `gestion__listas_precios.vende_unidad_base`
(1 por defecto = como hoy). Con 0, la lista no ofrece la unidad suelta aunque tenga precio unitario (que se sigue
usando como base de cálculo y para el costo).

### 4. Mínimo y múltiplo por presentación

En `gestion__productos_presentaciones`: `venta_minimo` y `venta_multiplo` (decimal(18,4), NULL = sin restricción),
en cantidad **de esa presentación** (mínimo 2 cajas, de a 1 caja). Para la unidad base, que no es una fila de
presentaciones: las mismas dos columnas en `gestion__productos` (NULL = sin restricción).

La validación (pedido, carrito) es una función de `core/`: cantidad ≥ mínimo y múltiplo exacto. Los datos se
cargan en la sección de presentaciones del ABM; `prpr_validar` / `prpr_guardar` leen y graban esas columnas solo si
existen (regla 8).

### 5. Disponibilidad

Función de `core/`: stock base de la sucursal = suma de `gestion__stock.cantidad` de sus bocas con
`es_deposito = 1`; disponibilidad de la presentación = floor(stock base / factor). Sin reservas: como hoy, el pedido
no compromete stock. Si más adelante se quiere restar lo pedido y no entregado, es otra fase.

### 6. Código nuevo (todo en `core/`, `templates/` y `assets/js/`)

- `core/listas_precios_presentaciones.php` (`lpre_*`): indicador, precio vigente de una presentación para una lista,
  presentaciones vendibles para un cliente (lista vigente → precio por presentación + unidad base si
  `vende_unidad_base`), validación de mínimo / múltiplo, disponibilidad por sucursal, alta / cambio de precio.
  Es lo que van a usar después el pedido del ERP, la app de vendedores y el carrito.
- Carga de precios: sección en la ficha del producto (precios por lista de cada presentación) y una importación
  desde planilla (código de producto o código de barras de la presentación + lista + precio), con simulación antes
  de grabar como en el recálculo.
- En los módulos: solo incluir la sección y llamar a `core/` si el indicador está prendido. Antes de tocar
  `modules/` aviso qué archivos y cómo se sincronizan 2 y 5.

### 7. Migración (un script, solo agregado, reanudable)

`_local/sql/mantenimiento/AAAAMMDD_venta_presentaciones_01.sql`: la columna de `conf__empresas`, la tabla de
precios (id de `conf__tablas` del bloque de gestion), `vende_unidad_base` en listas, `venta_minimo` /
`venta_multiplo` en presentaciones y en productos. Todo con valor por defecto o NULL; chequeos en
`information_schema`. Sin páginas nuevas en este paso (la carga va en pantallas existentes).

## Qué no cambia

Con el indicador apagado: nada. Ninguna consulta existente agrega columnas. Los pedidos, remitos y facturas
siguen igual hasta el paso 2 (presentaciones en las líneas), que se diseña aparte y lleva su prueba de comparación.

## Decisiones que necesito

1. **Indicador:** ¿nuevo `usa_venta_presentaciones` (propuesto) o reusar `usa_presentaciones`?
2. **Precio de la presentación:** ¿solo manual / importado (propuesto para empezar), o también calculado por
   reglas (por ejemplo, unitario × factor − x %)? Calculado implica sumarlo al recálculo y a los motores del doc 18.
3. **Sin precio en la lista:** ¿la presentación no se ofrece (propuesto) o se ofrece a unitario × factor?
4. **Mayorista sin unidad suelta:** ¿por lista con `vende_unidad_base` (propuesto) o por otro criterio?
5. **Mínimo / múltiplo de la unidad base:** ¿en el producto (propuesto) o no hace falta?
6. **Disponibilidad:** ¿solo stock físico (propuesto) o restar lo pedido y no entregado desde ya?

## Implementado (2026-11-14)

Las 6 propuestas se aceptaron tal cual.

| Parte | Dónde | Commit |
|---|---|---|
| Migración | `_local/sql/mantenimiento/20261114_venta_presentaciones_01.sql` | `d3d5780` |
| API (`lpre_*`) | `core/listas_precios_presentaciones.php` | `500c259` |
| Interruptor "Vende por presentación" | Configuración > Empresas (`modules/conf/empresas*`); solo aparece con la columna; exige "Usa presentaciones" | `860df94` |
| Ficha del producto | `core/productos_presentaciones.php`, `templates/productos/presentaciones_seccion.php`, `assets/js/productos-presentaciones.js`; en los módulos 2 y 5 solo la acción `obtener_presentaciones` (`prpr_respuesta_presentaciones`) | (este) |

**Ficha del producto, con la empresa vendiendo por presentación.**

- La sección Presentaciones suma mínimo y "de a" para la unidad suelta (`gestion__productos`) y para cada presentación.
- Cada presentación suma también una fila de precios, uno por lista activa. Debajo de cada precio se ve cuánto sale la unidad dentro de la presentación y el precio de la unidad suelta en esa lista. Si la lista no vende la unidad suelta, lo dice.
- Un precio vacío o en 0 quita el precio vigente: la presentación deja de ofrecerse en esa lista.

El JSON `presentaciones` pasa a ser `{filas, unidad}`. `prpr_procesar_formulario` acepta los dos formatos y, si la empresa no vende por presentación, ignora lo nuevo.

**Orden de grabado.** Las listas de los precios se validan antes de guardar el producto. Después:

1. `prpr_guardar` graba las presentaciones, con mínimo y múltiplo en su misma transacción.
2. Se graba el mínimo y múltiplo de la unidad.
3. `lpre_guardar_precios` graba los precios, en su propia transacción.

Si falla el paso 1, el usuario recibe un aviso de que el producto quedó guardado y lo demás no.

**Pendiente.**

- "Vende la unidad suelta" en Listas de precios.
- Importación de precios por presentación desde planilla.
- El paso 2: presentaciones en pedidos, remitos y facturas.
