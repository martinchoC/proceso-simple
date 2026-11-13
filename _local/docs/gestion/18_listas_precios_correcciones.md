# Listas de precios: revisión del cálculo y correcciones

Prerrequisito para cargar el catálogo de la distribuidora. Fase A (reconocimiento), 2026-11-13, rama `distribuidora`.
Las líneas citadas son de `modules/gestion_normal/`. `listas_precios_model.php`, `listas_precios_ajax.php` y
`precios_core_model.php` son **idénticos** en `modules/gestion/` (solo cambia el módulo 2 / 5 en el guard): toda
corrección se hace igual en los dos.

## Resumen

- **Riesgo real hoy:** en la base local, Casa Lucho (empresa 2) tiene 5.345 precios vigentes en la lista 1 "General"
  (origen COSTO), de los cuales 5.122 son manuales o importados (`es_manual = 1`). Solo 296 de los 6.098 productos
  activos tienen costo cargado. Apretar "Recalcular" en esa lista borra los 5.345 precios (y las 742 filas
  inactivas) y deja unos 296 precios calculados. Lo mismo para la lista 3 "Mostrador" (base: la 1).
- Hay **dos motores de cálculo distintos** que escriben en la misma tabla: el botón "Recalcular"
  (`recalcularPreciosLista`) y la actualización por costo de proveedor (`precios_core_model.php`). Dan resultados
  distintos para la misma lista (ver hallazgo A1).

## (a) Hallazgos

### 1. El recálculo borra todo y pisa manuales / importados — CONFIRMADO

- `listas_precios_model.php:1357-1359`: `DELETE` de **todas** las filas de la lista (cualquier estado y cualquier
  `es_manual`), después de copiarlas al historial (`:1341-1354`, también todas, incluidas las inactivas, en cada
  recálculo).
- `:1476-1501`: reinserta solo los productos calculados con `es_manual = 0` (`:1493`), `precio_manual = NULL`,
  `f_desde = hoy`.
- Los manuales los graban `actualizarPrecioManual` (`:1537-1565`), el ABM de "Listas de precios por producto"
  (`listas_precios_productos_model.php:289`, `:347`) y la importación desde Excel (`:544-546`), todos con
  `es_manual = 1`. El recálculo no los mira.
- `listas_precios_ajax.php:301-309` (`recalcular_precios`) no valida el origen de la lista y **tampoco exige
  permiso** (no llama a `permisos_exigir_funcion`; sí lo hacen `ejecutar_accion`, `agregar`, `editar` en `:68-108`).
  `listas_precios.js:536-547` pide confirmación pero tampoco mira el origen.
- Además, `recalcularPreciosLista` **ignora `lista_precio_origen_id`**: usa `lista_base_id` si está cargado
  (`:1372`) y si no el costo, sea cual sea el origen. Una lista MANUAL (3) se recalcula desde el costo.

### 2. Reglas encadenadas por prioridad; solo se guarda la última; promoción y acumulación sin leer — CONFIRMADO, con agregados

- `:1287-1293` trae las reglas vigentes `ORDER BY prioridad ASC`; `:1397-1463` aplica **todas** las que coinciden,
  cada una sobre el precio que dejó la anterior (se componen).
- `:1448` guarda `ultima_regla_id`; `:1455` suma los porcentajes (`porcentaje_total += valor`), que **no** es el
  porcentaje real cuando se componen (+10 % y +10 % da ×1,21 y guarda 20 %). El mismo total va a
  `porcentaje_general_aplicado` y a `porcentaje_regla_aplicado` (`:1488-1492`).
- `es_promocion` y `permite_acumulacion` no se leen en ningún lado. En `precios_core_model.php:211-218` está
  escrito como decisión ("no se consideren").
- Agregados:
  - Los criterios de una regla se evalúan con **O**, no con **Y** (`:1400-1437`): una regla cargada con producto X
    y categoría Y se aplica a todos los productos de la categoría Y.
  - Una regla con `entidad_id` **nunca** se aplica sola en el recálculo (la excluye la condición de regla general,
    `:1443`, y no hay criterio por entidad); y si además tiene producto o categoría, se aplica a todos sin mirar la
    entidad. El ABM ofrece cualquier entidad (`obtenerEntidades`, `:1183-1206`, clientes y proveedores mezclados).
    En el otro motor `entidad_id` significa **proveedor** (`precios_core_model.php:280`).
  - Reglas con la misma prioridad (la empresa 1000 tiene 10 reglas por categoría, todas con prioridad 100) se
    aplican en el orden que devuelva MySQL; hoy no importa porque cada producto cae en una sola categoría.

### 3. PRECIO_FIJO (tipo 3) se suma como importe — CONFIRMADO

- `:1452-1461`: tipo 1 es porcentaje; cualquier otro tipo (2 IMPORTE y **3 PRECIO_FIJO**) entra por el `else` y
  suma `valor_ajuste` al precio. Catálogo: `gestion__listas_precios_reglas_valores_tipos` (3 = "Reemplaza el precio
  calculado por un precio fijo").
- El otro motor ignora el tipo por completo: trata `valor_ajuste` siempre como porcentaje
  (`precios_core_model.php:352`, `:593`), también para tipo 2 y 3.
- Datos locales: todas las reglas son tipo 1. Corregirlo no cambia ningún precio actual.

### 4. Lista base sin filtro de estado ni vigencia, sin orden — CONFIRMADO

- `:1373-1378`: `SELECT precio_final ... WHERE lista_precio_id = base AND producto_id = ? LIMIT 1`, sin
  `tabla_estado_registro_id`, sin `f_desde` / `f_hasta`, sin `ORDER BY`. La importación (`listas_precios_productos_model.php:541`)
  y el otro motor (`precios_core_model.php:584`) dejan la fila anterior en estado 2 e insertan una nueva, así que
  la base puede devolver un precio viejo. En local, la lista 1 tiene 742 filas inactivas.
- Agregado: si el producto no está en la lista base, toma el **costo** como base (`:1384-1386`) y le aplica las
  reglas de la lista derivada como si fuera de origen COSTO, sin avisar.

### 5. Los productos sin costo no entran a la lista — CONFIRMADO, con precisión

- `:1303-1317`: `LEFT JOIN gestion__productos_costos` con `(pc.costo_actual IS NOT NULL OR pc.costo_actual > 0)`.
  Queda afuera el producto **sin fila de costo**; el que tiene costo 0 entra con base 0. No filtra el estado de la
  fila de costo.
- El filtro se aplica también a las listas que parten de **otra lista**: un producto con precio en la lista base
  pero sin costo no entra a la derivada. Combinado con el hallazgo 1, recalcular "Mostrador" la reduce a ~296
  productos.
- `gestion__productos_costos` tiene clave única (empresa, producto): no hay filas duplicadas por el `JOIN`.
- No filtra `aplica_venta` ni `es_servicio`.

### 6. La vigencia de las reglas solo se evalúa al recalcular; no hay cron — CONFIRMADO

- `:1291-1292` (recálculo) y `precios_core_model.php:240-241` (por costo) filtran vigencia en el momento en que
  corren. El precio escrito queda con `f_desde = hoy` y `f_hasta = NULL`: cuando la regla vence, el precio no vuelve
  solo.
- `cron/` tiene solo `facturas_proveedores_lectura.php` y los cinco de sindicatos.

### 7. Cómo se propaga `requiere_recalculo` — INVESTIGADO

- Lo marca **solo el usuario**, con la casilla del formulario de la lista (`listas_precios.php:185`,
  `listas_precios.js:1336`, `listas_precios_ajax.php:95` / `:118`, `listas_precios_model.php:529` / `:626`).
- Lo apaga el recálculo (`:1514-1516`). Se muestra como columna en la grilla (`listas_precios.js:155`).
- **Nadie más lo marca** (ni el cambio de costo, ni el alta o edición de una regla, ni el recálculo de la lista
  base) y **nadie lo consume** (no hay cron ni aviso). Hoy es una nota manual. En local está en 1 en las listas 1,
  3 y 10.

### 8. En pedidos, un producto fuera de la lista del cliente sale con precio 0 — CONFIRMADO

- `ventas_pedidos_model.php:1551-1615` (`buscarProductosPorCliente`, el buscador del pedido): lista todos los
  productos activos y el precio sale de `COALESCE((subconsulta de la lista vigente), 0)` (`:1586-1592`). El
  comentario `:1556-1558` lo declara como intencional. Cliente sin lista: todo a 0.
- La lista del cliente sale de `gestion__entidades_condiciones_clientes` (`obtenerListaPrecioVigenteCliente`,
  `:1347-1372`). La subconsulta sí filtra estado y vigencia y ordena por `f_desde DESC`.
- Hay otros dos caminos con criterio distinto: `obtenerProductosPorCliente` (`:1388-1430`) devuelve solo los
  productos de la lista, y `obtenerUltimoPrecioProducto` (`:1620-1660`), si no está en la lista, cae al último
  precio usado con ese cliente.

### Hallazgos adicionales

- **A1. Dos motores.** `precios_core_model.php` (`calcularPreciosDeberianSerPorLista`, `:315-371`) se usa al
  actualizar precios de proveedor y costos (`proveedor_precios_model.php:1448`, `:1541`, `:1749`, `:1778`;
  `facturas_proveedores_model.php:4225`, `:4248`). Aplica **una sola** regla por especificidad (producto >
  categoría > proveedor > general, `:271-288`), siempre como porcentaje, respeta el origen MANUAL, redondea a 2
  decimales y versiona (estado 2 + fila nueva). El recálculo compone, distingue tipo 1 / otros, ignora el origen,
  no redondea y borra. La misma lista da precios distintos según qué se ejecutó último.
- **A2. El otro motor también pisa manuales.** `actualizarPrecioVentaDesdeRegla` (`precios_core_model.php:538-619`)
  no mira `es_manual`: si el calculado difiere en 0,99 o más, desactiva la fila manual e inserta una calculada.
  Una actualización de precios de proveedor pisa los precios importados de los productos que toca.
- **A3. Id fijo.** `precios_core_model.php:637-695`: tratamiento especial de `lista_precio_id = 3` ("Mostrador" de
  Casa Lucho) con constante fija. Contra la regla "nunca ids de configuración fijos". Hoy solo afecta a la empresa
  2 (los ids son globales). Fuera de alcance; anotado.
- **A4. Errores parciales.** Cada `INSERT` del recálculo va con `mysqli_query` y, si falla, se anota y sigue
  (`:1503-1510`). Con PHP 8.1 o superior (mysqli lanza excepciones por defecto) un error revierte todo; con PHP
  anterior se confirma un recálculo a medias. Depende de la versión de producción (pendiente de confirmar).
- **A5. Estado inicial.** El recálculo inserta con `obtenerEstadoInicial()` (`:141-156`: el estado de menor
  `valor_estandar`, hoy el 1). Funciona, pero no es el criterio del motor de estados.

## (b) Diseño de corrección mínimo

Todo dentro de las funciones existentes, en los dos módulos, sin columnas ni tablas nuevas.

| # | Corrección | Dónde |
|---|---|---|
| C1 | **No recalcular listas MANUAL**: rechazar con mensaje claro si `lista_precio_origen_id = 3`, en el modelo y en el ajax. En el JS, no ofrecer el botón para esas listas (el servidor valida igual). | `recalcularPreciosLista`, `listas_precios_ajax.php:301`, `listas_precios.js` |
| C2 | **Respetar manuales e importados**: antes de recalcular, leer los `producto_id` con fila vigente `es_manual = 1` de la lista; esos productos no se calculan ni se tocan. El `DELETE` y la copia al historial pasan a ser solo de `es_manual = 0`. | `recalcularPreciosLista` `:1341-1359`, `:1370` |
| C3 | **PRECIO_FIJO**: tipo 3 reemplaza el precio (`precio = valor`; el importe aplicado es la diferencia). Tipo 2 sigue sumando. Las reglas de mayor prioridad que vengan después se siguen componiendo sobre ese precio (no cambia la semántica de acumulación). | `:1452-1461` |
| C4 | **Lista base determinística**: agregar `tabla_estado_registro_id = 1`, vigencia (`f_desde <= CURDATE()` y `f_hasta` nula o futura) y `ORDER BY f_desde DESC, lista_precio_producto_id DESC`, igual que el buscador de pedidos. | `:1373-1378` |
| C5 | **Permiso del recálculo**: exigir `editar` de la página (`permisos_exigir_funcion(..., 'editar', true)`), como `agregar` / `editar`. | `listas_precios_ajax.php:301` |
| C6 | **Modo simulación** (opcional, simple): parámetro `$simular`. Calcula todo igual y, en vez de escribir, devuelve el resumen (nuevos, cambian, iguales, quitados, manuales preservados) y las primeras N diferencias. Acción `simular_recalculo` en el ajax y un botón "Ver qué cambia" antes de confirmar. | `recalcularPreciosLista`, ajax, JS |

Fuera de esta fase (decisiones de negocio pendientes; qué haría falta):

- **Acumulación y promociones (hallazgo 2):** definir si se aplica la mejor regla, todas o solo las que
  `permite_acumulacion`; si `es_promocion` va separada del precio de lista (con vigencia propia y vuelta atrás
  automática); si los criterios son Y en vez de O; qué significa `entidad_id` (cliente o proveedor). Guardar todas
  las reglas aplicadas a un precio necesita una tabla de detalle (migración aparte). Lo razonable es **unificar los
  dos motores** en `core/` con una sola semántica.
- **Productos sin costo (hallazgo 5):** que una lista derivada tome los productos de su lista base aunque no tengan
  costo. Cambia resultados (agrega precios), por eso no va en esta fase.
- **Otro motor pisando manuales (A2):** el mismo criterio de C2 en `actualizarPrecioVentaDesdeRegla` (no tocar
  `es_manual = 1`). Cambia lo que hace hoy "Actualizar costo y lista de precios": decidir si un precio importado
  debe seguir al costo o no.
- **Vigencia y `requiere_recalculo` (6 y 7):** marcar `requiere_recalculo = 1` al crear, editar o cambiar el estado
  de una regla y al recalcular la lista base; y un cron opcional que recalcule las listas marcadas o con reglas que
  empiezan o vencen hoy. Sin cambios de estructura, pero es comportamiento nuevo: después de esta fase.
- **Precio 0 en pedidos (8):** devolver además `en_lista` (0/1) y que el pedido avise "sin precio en la lista del
  cliente" en vez de cargar 0 en silencio. Es código compartido de pedidos (2 y 5) y entra con la fase de canales.
  Si se quiere guardar la regla aplicada en cada línea de pedido, va como migración aparte.
- **A3** (id fijo de "Mostrador"): reemplazarlo por una marca en la lista. Migración aparte.

## (c) Qué cambia y a quién afecta

| Corrección | Cambio visible | A quién |
|---|---|---|
| C1 | "Recalcular" en una lista MANUAL deja de hacer algo (antes la rellenaba desde el costo y borraba sus precios). | Empresas con listas de origen MANUAL (local: 6 lista 10, 1000 listas 11 y 12). |
| C2 | Los precios manuales e importados sobreviven al recálculo; los calculados se recalculan igual que antes. La grilla muestra la mezcla. | Toda lista con `es_manual = 1` (local: Casa Lucho listas 1 y 3). Es el arreglo principal. |
| C3 | Una regla PRECIO_FIJO fija el precio en vez de sumarse. | Solo quien tenga reglas tipo 3 (local: ninguna). |
| C4 | La lista derivada toma el precio vigente de la base, no uno viejo al azar. | Listas OTRA_LISTA cuya base tenga filas inactivas o vencidas (local: Mostrador ← General). |
| C5 | Un perfil sin `editar` en la página ya no puede recalcular. | Perfiles con acceso de solo lectura a Listas de precios. |
| C6 | Nada cambia si no se usa. | — |

Para una lista sin manuales, cuya base no tenga filas inactivas y sin reglas tipo 3, el resultado tiene que ser
idéntico al de hoy.

## (d) Plan de la prueba de comparación (Fase B)

- **Base:** una base local de pruebas restaurada desde `_local/sql/casalucho_produccion.sql` (la crea Pablo; el
  nombre lo indica él). El script se niega a correr si el nombre de la base es el del maestro
  (`gestion_multipyme`) o no contiene `prueba`.
- **Script:** `_local/tests/listas_precios/foto_precios.php` (CLI, solo desarrollo):
  1. La primera vez guarda una copia de `gestion__listas_precios`, `gestion__listas_precios_productos` y
     `gestion__listas_precios_productos_historial` en tablas `zz_lp_*` **de la base de pruebas**; en cada corrida
     posterior las restaura desde ahí, así cada corrida parte del mismo estado sin volver a restaurar el dump.
  2. Saca la foto "antes" (estado guardado) y después recalcula todas las listas activas de cada empresa con la
     función real del modelo (`modules/gestion_normal/listas_precios_model.php`), primero las de origen COSTO y
     después las derivadas, en orden de dependencia.
  3. Guarda la foto "después" en `_local/tests/listas_precios/salidas/<etiqueta>.csv` (ignorado por git): empresa,
     lista, producto, `precio_final` (redondeado a 2), `es_manual`, `lista_precio_regla_id`,
     `porcentaje_regla_aplicado`, estado. Sin ids de fila ni fechas, que cambian en cada corrida.
  4. Con `--comparar=<etiqueta>` muestra el diff contra otra foto: altas, bajas y cambios por lista, con totales.
- **Corridas:** línea base con el código actual (`base`), y una corrida por cada corrección (`c1`, `c2`...), cada
  una comparada contra la base.
- **Resultado esperado:**
  - Listas sin manuales (local: empresa 1000 lista 19, "Mayorista", la de control): diff vacío en todas las
    correcciones.
  - C1: las listas MANUAL quedan como estaban antes del recálculo (en la base, el recálculo las había rellenado).
  - C2: en Casa Lucho listas 1 y 3, las filas `es_manual = 1` aparecen en "después" (en la base desaparecían); las
    calculadas, iguales.
  - C4: cambios solo en productos de Mostrador cuya fila vigente en General difiere de la que tomaba el `LIMIT 1`.
  - Cualquier otra diferencia: freno y lo explico antes de seguir.
- El otro motor (`precios_core_model.php`) no se toca en esta fase y no entra en la prueba.

## Pendiente antes de la Fase B

- Aprobación de C1–C6 (y si C5 y C6 van o no).
- Nombre de la base de pruebas, creada por Pablo desde `casalucho_produccion.sql`.
- Versión de PHP de producción (afecta A4).
