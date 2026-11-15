# Distribución: camiones, zonas, armado y hojas de ruta (paso 5b)

Estado: **plan a aprobar** (2026-11-15).

## Pedido (Prompt 5, parte b)

ABM de camiones y zonas, estado "Confirmado para distribución" sobre el motor de estados, depósito / sucursal de
despacho en el pedido, pantalla de armado nocturno con control de capacidad (peso y volumen de las presentaciones),
hoja de ruta con orden manual (sortablejs ya está local), lista de carga y rendición básica. Sin mapas ni
optimización de rutas.

## Lo que hay hoy (base local y código, solo lectura)

- **Estados del pedido** (tabla 82): 3 Borrador → 5 "Confirmado - Pend. Revisión" → 9 En preparación → 10 / 11
  Entrega parcial / total (por remitos) → 12 Facturado; 6 Eliminado. "Nuevos Pedidos de Clientes" (91, la pantalla de
  preparación) pasa el pedido de 5 a 9 al abrirlo. No hay ningún estado de distribución; el catálogo genérico llega
  hasta el 41.
- **Depósito** = boca con `es_deposito = 1` (`gestion__bocas`, con su sucursal). El pedido tiene sucursal y punto de
  venta, pero no depósito: el remito lo toma del punto de venta.
- **Peso y volumen**: las presentaciones tienen `peso_bruto_kg` y `largo_cm` / `ancho_cm` / `alto_cm`;
  `gestion__productos.peso` (decimal 6,2, sin unidad documentada) existe pero está vacío. En la empresa de prueba
  1000: 100 de 123 presentaciones con peso, 23 con medidas, ningún producto con peso. **El control de capacidad va a
  ser parcial hasta que se carguen los datos**: el armado tiene que mostrar qué falta.
- No hay camiones, zonas, hojas de ruta ni repartos. `sortablejs-1.15.0` está local (lo usa Productos).
- El vendedor del pedido (5a) ya existe y sirve para agrupar y para la rendición.

## Propuesta

Indicador `conf__empresas.usa_distribucion` (apagado por defecto). Sin él: el estado nuevo y sus funciones existen en
la configuración, pero **los botones no se muestran** (los filtra el modelo de pedidos), las columnas nuevas no se
leen ni se escriben y las páginas nuevas no aparecen en el menú de nadie (no tienen permisos fuera de los perfiles de
administración, y la página avisa si la empresa no usa distribución).

### 1. Zonas

`gestion__distribucion_zonas` (empresa, nombre, código, días de reparto lun–sáb, orden, estado) y la zona de cada
cliente en `gestion__distribucion_zonas_clientes` (empresa, cliente, sucursal de entrega opcional: NULL = todas, zona;
con vigencia, como la cartera). **Tabla propia en vez de columna en el cliente**: así no se toca el ABM de Clientes /
Entidades (~5.000 líneas) y la zona se asigna en masa desde la página de distribución.

### 2. Camiones

`gestion__distribucion_camiones` (empresa, nombre, patente, capacidad en kg y en m³, depósito habitual, chofer
habitual —texto o entidad con rol EMP—, estado).

### 3. Estado "Confirmado para distribución" y depósito de despacho

- Estado genérico nuevo **42 "Para distribución"** (`conf__estados_registros`, id explícito), en el catálogo de la
  tabla 82 con la etiqueta "Confirmado para distribución" (orden entre 5 y 9; id de `conf__tablas_estados_registros`
  272), y funciones en las páginas 55 / 91 (y 555 / 591): **5 → 42 "Liberar para distribución"** y **42 → 5 "Volver a
  revisión"**. La preparación (91) abre también los de 42 y los pasa a 9, como hoy con los de 5.
- `gestion__ventas_pedidos.boca_despacho_id` (NULL): depósito desde el que sale. Al liberar, si está vacío, se completa
  con el depósito principal de la sucursal del pedido; se cambia desde el armado.

### 4. Armado nocturno (página "Armado de reparto")

Filtros: fecha de reparto, depósito, zona. A la izquierda, los pedidos en 42 sin hoja, agrupados por zona, con peso,
volumen y aviso si les falta el dato. A la derecha, las hojas del día por camión con barras de capacidad (kg y m³).
Se asigna con un botón o arrastrando; superar la capacidad **avisa y pide confirmar** (no bloquea: el dato suele ser
incompleto).

Peso y volumen de una línea: con presentación, `presentacion_cantidad × peso_bruto_kg` y `× largo × ancho × alto`;
sin presentación, `cantidad × gestion__productos.peso` (si lo tiene) y volumen sin dato. Se calcula al armar (no se
guarda en el pedido) y se guarda una **foto** en la hoja al confirmarla.

### 5. Hoja de ruta

`gestion__distribucion_hojas` (empresa, número correlativo, fecha de reparto, camión, chofer, depósito, estado:
3 Borrador / 5 Confirmada / 18 En curso / 21 Rendida / 38 Anulada, totales kg / m³) y
`gestion__distribucion_hojas_pedidos` (hoja, pedido, **orden** —sortablejs—, peso y volumen de la foto, resultado de
la rendición). Un pedido está en una sola hoja no anulada. Estado propio de la hoja manejado por su servicio
(como Libros societarios), no por el motor de botones: las acciones se controlan con las funciones de la página.

Impresiones (plantillas en `templates/impresion/`): **hoja de ruta** (orden, cliente, dirección de entrega del
pedido, teléfono, condición de pago, importe, observaciones, vendedor, casilleros para firma y cobro) y **lista de
carga** (totales por producto y presentación de todos los pedidos de la hoja, para cargar el camión).

### 6. Rendición básica

Por pedido de la hoja: **Entregado / Entregado parcial / Rechazado / No visitado**, motivo, importe cobrado y medio
(texto). La hoja pasa a Rendida cuando todos tienen resultado. Los rechazados y no visitados vuelven a 42 para otro
reparto. **La rendición registra, no mueve nada**: no hace remitos, ni stock, ni recibos (decisión 4).

### 7. Páginas, código e ids

- Página **"Distribución - Configuración"** (148 / 648, bajo Ventas): Zonas, Zonas de clientes, Camiones.
- Página **"Armado y hojas de ruta"** (149 / 649): Armado, Hojas, Rendición.
- Funciones 3605+ / 8605+ (consultar, agregar, editar, inhabilitar en las dos; en la segunda además confirmar y
  rendir). Tablas 471–475. Permisos: perfiles de administración y los que tienen la preparación de pedidos (91).
- Lógica en `core/distribucion/`, vista en `templates/distribucion/`, JS en `assets/js/distribucion*.js`; los
  archivos de cada módulo solo fijan la página.
- En el código compartido de pedidos solo: filtrar los botones de 42 sin el indicador, completar el depósito al
  liberar, y que la preparación acepte 42 → 9. Comparación antes / después.

### 8. Orden de trabajo (un commit y una prueba cada uno)

5b-1. Migración (indicador, tablas, columna del pedido) + estado 42 y funciones por script + `core/distribucion/`
      (zonas, camiones, cálculo de peso / volumen) + pruebas en la copia y contra una base sin la estructura.
5b-2. Página de configuración (zonas, zonas de clientes, camiones). Interruptor en Empresas.
5b-3. Pedidos: liberar / volver, depósito de despacho, preparación desde 42. Comparación antes / después.
5b-4. Armado y hojas de ruta (con sortablejs), impresión de hoja y lista de carga.
5b-5. Rendición.
Caso de simulación nuevo (37).

## Qué no cambia

Con el indicador apagado: nada. Precios, stock, remitos, facturas, cobranzas, asientos y ARCA no se tocan; los
remitos siguen saliendo como hoy (desde el pedido o la preparación).

## Decisiones a aprobar

1. **"Confirmado para distribución" como paso nuevo después de 5** (5 → 42 → 9, recomendado: 5 sigue siendo la
   revisión comercial) o que, con el indicador, confirmar el pedido lo deje directamente en 42.
2. **Zona en tabla propia por cliente / sucursal de entrega** (recomendado) o columna nueva en
   `gestion__entidades_sucursales` cargada desde la ficha del cliente (toca el ABM de Clientes).
3. **Capacidad: avisar y pedir confirmar** (recomendado) o bloquear al superarla.
4. **Rendición que solo registra** (recomendado ahora) o que además genere los remitos de la hoja (en Borrador,
   con `agregarRemitoVenta`) y / o los recibos de lo cobrado. Generar remitos desde la hoja se puede sumar después
   como 5b-6.
5. **Peso de un producto sin presentación**: tomar `gestion__productos.peso` como kg por unidad (recomendado; hoy está
   vacío) o agregar campos nuevos de peso / volumen por unidad en el producto.
6. **Dos páginas** (configuración y armado, recomendado) o una sola con todas las solapas.

## Pendiente de 5a para la rendición y el reparto

El vendedor del pedido (5a) aparece en la hoja de ruta y en el armado como dato; no se arma por vendedor.
