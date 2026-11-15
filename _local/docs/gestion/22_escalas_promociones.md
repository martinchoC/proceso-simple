# Escalas por cantidad, promociones y descuento del vendedor (paso 4)

Estado: **propuesta, a aprobar**. Sigue a los docs 19 a 21 (presentaciones, canales). Rama `distribuidora`.
Fecha: 2026-11-15.

## Pedido (Prompt 4)

Motor de escalas por cantidad y promociones con vigencia, **evaluadas al vender** (no se escriben en las listas).
Precedencia: precio de la presentación → escala o descuento general del cliente (el mayor, sin acumular, con tope)
→ descuento manual del vendedor con tope y aprobación. Cada línea guarda qué regla se aplicó. Una sola función
`precio_resolver(...)` en `core/` que después usen el ERP, el carrito y la app.

## Lo que hay hoy (base copia de producción y código, solo lectura)

| Tema | Hoy |
|---|---|
| Descuento en las líneas de venta | `descuento_general_pct` / `descuento_general` en pedidos, remitos y facturas: es el descuento general del cliente (`cliente_descuento_general` de su condición), que la pantalla copia a cada línea y de ahí salen neto, IVA, asiento y ARCA. Se puede editar en pantalla |
| Descuento por ítem | `descuento_item_pct` / `descuento_item` existen **solo en pedidos**, en 0 en las 489 líneas; ningún código de ventas los usa (sí facturas de proveedores) |
| Reglas de listas | `gestion__listas_precios_reglas` tiene `es_promocion` y `permite_acumulacion`, que **nadie lee** (doc 18, hallazgo 2). Esas reglas calculan el precio de lista (se materializan); no sirven para "evaluar al vender" |
| Aprobaciones | `core/aprobaciones.php` + `gestion__aprobaciones_reglas` / `_topes` / `_registro`, por **importe** y para documentos `ENUM('OBLIGACION','OP')`; 0 reglas cargadas en producción |
| Estados del pedido | Borrador (3) → Confirmar (5) → En proceso / Entrega parcial; **no hay "Pendiente de aprobación"** |
| Categorías de producto | Existen (`gestion__productos_categorias`), pero Casa Lucho tiene **una sola en uso**; marcas solo en el módulo 2 |
| Precio | La pantalla manda el precio y el servidor no lo controla contra la lista (doc 20, decisión 6) |

## Propuesta

### 1. Reglas: una tabla para escalas y promociones

`gestion__precios_reglas_venta` (nueva): una fila por regla, con vigencia y alcance.

| Campo | Para qué |
|---|---|
| `tipo` | `ESCALA` (descuento por cantidad) o `PROMOCION` (descuento por fecha) |
| `nombre`, `prioridad` | Lo que se muestra; desempate |
| Alcance: `producto_id`, `producto_categoria_id`, `lista_precio_id`, `canal_venta_id`, `sucursal_id` | NULL = todos. Al menos producto o categoría (o "todos los productos" explícito) |
| `presentacion_id` | NULL = cualquier presentación de ese producto; con valor, solo esa (p. ej. promo en la caja) |
| `f_desde`, `f_hasta` | Vigencia (a la fecha del comprobante) |
| `descuento_pct` | Para PROMOCION |
| Tramos (tabla hija `gestion__precios_reglas_venta_tramos`): `cantidad_desde` (en unidad base), `descuento_pct` | Para ESCALA: "desde 24 u 5 %, desde 120 u 8 %" |
| `tabla_estado_registro_id` | 1 activa / 2 inactiva (nada se borra) |

La cantidad de la escala se cuenta **por producto en todo el comprobante** (suma en unidad base de sus líneas en
cajas y sueltas, decisión 5 del doc 20), no por línea.

Fuera de este paso (propuesta): NxM ("lleva 3 paga 2"), combos y regalos. Cambian líneas y stock, no solo el
precio, y conviene verlos con el carrito.

### 2. Precedencia (sin acumular)

Para cada línea:
1. **Precio base**: el de la presentación o el unitario de la lista del cliente (pasos 2 y 3; no cambia).
2. **Descuento automático** = el **mayor** entre: descuento general del cliente, escala que alcanza la cantidad y
   promoción vigente. Ninguno se suma a otro. Empate: promoción > escala > cliente.
3. **Tope automático** por lista (`gestion__listas_precios.descuento_tope_pct`, NULL = sin tope): el automático no
   pasa de ahí. Así cada canal tiene su techo.
4. **Descuento manual del vendedor**: se suma encima del automático (sobre el neto), hasta su tope.

Lo que viaja a las columnas de siempre es el total: `descuento_general_pct` = 1 − (1 − automático) × (1 − manual).
Neto, IVA, asientos, ARCA, remitos y facturas siguen igual: **no se toca ningún cálculo existente**.

### 3. Qué regla se aplicó (cada línea)

Columnas nuevas, NULL por defecto, en las tres tablas de líneas, escritas con un UPDATE aparte (regla 8), como las
de presentación: `precio_regla_tipo` (CLIENTE / ESCALA / PROMOCION / NINGUNA), `precio_regla_id`,
`descuento_auto_pct`, `descuento_manual_pct`, `descuento_aprobado_por` (usuario), `descuento_aprobado_en`.
El remito y la factura que vienen de un pedido las **heredan** (no se recalcula: lo pactado en el pedido manda,
aunque la entrega sea parcial y la cantidad entregada no alcance el tramo).

### 4. Tope y aprobación del descuento manual

- Tope por perfil: % máximo de descuento manual que puede dar cada perfil (Ventas, Vendedor, Administrador...).
- **Sin estado nuevo**: el vendedor graba el pedido en Borrador con el descuento que quiera; **confirmarlo** exige
  que quien confirma tenga tope suficiente. Si no alcanza, el pedido queda en Borrador con el aviso "descuento
  pendiente de aprobación" y lo confirma un supervisor con tope mayor. Queda registrado quién y cuándo.
- Remito y factura directos (sin pedido): se controla al grabar.

Dónde guardar los topes (decisión 3): las tablas de aprobaciones existentes tienen `documento` como
`ENUM('OBLIGACION','OP')` y trabajan por importe; usarlas exige **modificar ese ENUM** (es un cambio de una columna
existente, no un agregado) y adaptar `core/aprobaciones.php`. Propongo una tabla nueva
`gestion__ventas_descuentos_topes` (empresa, perfil, tope %, vigencia) con la misma forma, sin tocar lo existente.

### 5. `precio_resolver` (core/precios_venta.php)

```
precio_resolver(mysqli $c, int $empresa_id, array $ctx, array $lineas): array
  $ctx    = entidad_id, lista_precio_id (si no, cvta_lista_cliente), sucursal_id, fecha, usuario_id
  $lineas = [producto_id, presentacion_id, cantidad_base, precio_unitario_lista, descuento_manual_pct]
  → por línea: precio base, candidatos (cliente / escalas / promociones con su %), regla ganadora, automático,
    tope, manual, total, requiere_aprobacion (bool) y el motivo
```
Sin el indicador devuelve el descuento general del cliente tal como hoy (no consulta reglas). La usan: los
buscadores (precio sugerido y "8 % desde 24 u"), las pantallas al cambiar cantidades (acción AJAX para recalcular
el pedido entero) y el servidor al grabar (calcula el automático y deja registrada la regla). El carrito y la app
la llaman igual.

**El servidor recalcula el automático** (no confía en el % de la pantalla, a diferencia del precio): si el total
que manda la pantalla es mayor que el automático, la diferencia es descuento manual y pasa por el tope.

### 6. Pantallas

- **Página nueva "Escalas y promociones"** (módulos 2 y 5, bajo Ventas, página 146 / 646): listado con filtros,
  alta / edición con tramos, activar / inactivar, vista previa ("qué descuento daría a este cliente con N u").
  Lógica en `core/`, vista en `templates/`, JS en `assets/js/`; los archivos de cada módulo solo fijan la página.
  Necesita su script de configuración (tabla, funciones, permisos) con ids explícitos de su bloque.
- **Listas de precios**: campo "Tope de descuento automático".
- **Topes de descuento por perfil**: una solapa o ventana en la misma página nueva.
- **Pedido, remito, factura**: al cambiar cantidades se recalcula; en la línea, un distintivo con la regla
  ("Escala 8 %", "Promo verano"); columna de descuento manual; aviso "requiere aprobación".
- **Impresión**: sin cambios (sale el total de descuento como hoy).

### 7. Orden de trabajo (un commit y una prueba cada uno)

4a. Migración + `core/precios_venta.php` (`prv_*` + `precio_resolver`) + pruebas en la copia y contra una base sin la
    estructura.
4b. Página "Escalas y promociones" (y topes por perfil), con su script de configuración. Tope en Listas.
4c. Pedidos: recalcular al cambiar cantidades, grabar la regla, tope al confirmar. Comparación antes / después.
4d. Remitos y facturas: herencia desde el pedido y control en los directos. Comparación antes / después.
Caso de simulación nuevo (35). Indicador `conf__empresas.usa_reglas_precio_venta`, apagado por defecto.

## Qué no cambia

Con el indicador apagado: nada (el descuento general del cliente sigue como hoy, las columnas nuevas quedan en
NULL). Precio de lista, presentaciones, canales, stock, asientos y ARCA no se tocan. Las reglas de listas
(`es_promocion`, `permite_acumulacion`) siguen sin uso: no se mezclan con esto.

## Decisiones a aprobar

1. **Alcance de las reglas**: producto (y presentación) + lista / canal / sucursal; categoría disponible pero hoy
   sin datos (Casa Lucho tiene una sola categoría). ¿Está bien, o necesitan otra agrupación (marca, proveedor)?
2. **Cantidad de la escala**: por producto sumando todo el comprobante (recomendado) o por línea.
3. **Topes del vendedor**: tabla nueva propia (recomendado) o ampliar el ENUM de las aprobaciones existentes.
4. **Aprobación**: sin estado nuevo, el que confirma necesita tope suficiente (recomendado), o estado nuevo
   "Pendiente de aprobación" en pedidos (más configuración de estados y funciones).
5. **Entregas parciales**: el remito y la factura heredan el descuento del pedido aunque no alcancen el tramo
   (recomendado) o se recalcula con lo entregado.
6. **NxM, combos y regalos**: fuera de este paso (recomendado) o dentro.
7. **Pantalla**: página nueva "Escalas y promociones" (recomendado) o ventanas dentro de Listas de precios (como
   Canales: sin configuración de página, pero la pantalla de listas se carga más).
