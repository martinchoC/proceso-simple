# Ficha de producto completa en gestion_normal

Estado: **implementado** (2026-11-15; plan aprobado el mismo día). Ver "Implementación" al final.

## Pedido

"En gestion_normal/productos.php no veo imagen, ubicaciones, proveedores (para la relación código nuestro con código
del proveedor) y precios" (2026-11-15). La ficha del producto del módulo 2 tiene esas solapas y la del 5 no.

## Lo que hay hoy (base local y código, solo lectura)

La ficha del 5 (`modules/gestion_normal/productos.php` + `productos.js`) es un modal sin solapas: Identificación,
Impuestos, Atributos físicos e Imputaciones (más presentaciones y marca, con sus indicadores). El 2 tiene, además de
Información, las solapas Compatibilidad (no va en el 5, CLAUDE.md), Imágenes, Ubicaciones, Proveedores y Precios.

Las **tablas son las mismas** en los dos módulos (base compartida), así que no hace falta estructura nueva para los
datos:

| Solapa | Tablas | En el 2 | En el 5 hoy |
|---|---|---|---|
| Proveedores | `gestion__productos_proveedores` (entidad, `codigo_proveedor`, `es_principal`) + `gestion__productos.proveedores_busqueda` | acciones `*_proveedor_producto` de `productos_ajax.php` → `productos_model.php` (`agregarProveedorProducto`, `editar…`, `eliminar…`, `actualizarPrincipalProveedorProducto`; principal con `core/productos_proveedores.php`, `pp_*`) | página aparte **Productos - Proveedores** (595), vínculos de a uno; su modelo choca de nombres con `productos_model.php` (`obtenerBotonAgregar`, `obtenerFuncionesPagina`), no se puede incluir |
| Precios | `gestion__listas_precios_productos` | la solapa ya usa `listas_precios_productos_ajax.php` (`obtener_precios_producto`, `obtener_listas`, `obtener`, `agregar`, `editar`) | **el mismo endpoint ya existe en el 5** y ya acepta llamadas desde `productos.php` / `productos_consulta.php` y la función "editar" de Productos para grabar: falta solo la pantalla |
| Ubicaciones | `gestion__productos_ubicaciones` → `gestion__sucursales_ubicaciones` (sucursal, boca, sección, estantería, estante, posición) | acciones `*_ubicacion*` (`agregarUbicacionProducto`, `editarUbicacionProducto`, `eliminarUbicacionProducto`, `crearUbicacionSucursal`, combos de sección / estantería / estante / posición), filtradas por las sucursales del usuario (`AccesosSucursalesHelper`) | **Depósitos - Ubicaciones** (538) arma las ubicaciones de la sucursal, pero no las asigna al producto |
| Imágenes | `gestion__productos_imagenes` → `conf__imagenes` (la imagen va en la base) | acciones `*_imagen_producto` (`subirImagenProducto` con `core/imagenes.php`, principal, orden, descripción) | no hay; `get_imagen.php` del 5 ya sirve las imágenes (la usa Lista de Precios) |

La lógica del 2 está en `modules/gestion/productos_model.php` (3.600 líneas) y el JS dentro de `productos.php`
(4.500 líneas).

## Propuesta

Indicador `conf__empresas.usa_ficha_producto_completa` (apagado por defecto; interruptor en Configuración >
Empresas). Apagado, la ficha del 5 queda exactamente como hoy.

1. **Solapas en la ficha del 5** (y en su consulta, solo lectura): Información (lo de hoy), Proveedores, Precios,
   Ubicaciones, Imágenes. Se habilitan al guardar el producto (en el alta, primero se graba la información), igual
   que en el 2.
2. **Código común, no en el módulo** (regla 5):
   - `core/productos_ficha/` — `proveedores.php` (`pfpv_*`), `ubicaciones.php` (`pfub_*`), `imagenes.php` (`pfim_*`) y
     `acciones.php` (`pfic_ajax_acciones`, como `pmar_ajax_acciones`: responde las acciones `ficha_*` y corta). Las
     reglas se toman del 2 (mismas validaciones, mismo `pp_*` para el principal, mismo recálculo de
     `proveedores_busqueda`, mismo `core/imagenes.php`), con nombres propios para no chocar.
   - `templates/productos/ficha_solapas.php` (las solapas) y `assets/js/productos-ficha.js` (global `ProdFicha`).
   - El 5 solo agrega: el `include` de la plantilla, el `<script>`, `ProdFicha.cargar(id)` / `limpiar()` en
     `productos.js`, y en `productos_ajax.php` la llamada a `pfic_ajax_acciones`. La consulta (`productos_consulta_ajax.php`)
     admite solo las acciones `ficha_*` de lectura.
   - **Precios no lleva backend nuevo**: la solapa usa `listas_precios_productos_ajax.php` del 5 tal como está.
3. **El módulo 2 no se toca**: sigue con su lógica propia. Pasarlo a `core/productos_ficha/` sería un refactor que no
   está pedido; queda anotado como posible paso posterior (con comparación antes / después), solo si se aprueba.
   Mientras tanto la regla de negocio queda en dos lugares.
4. **Permisos**: ver = acceso a Productos (540) o a la consulta (598). Agregar / editar / quitar = función "editar"
   de Productos (540), como presentaciones y marca; precios, con la regla que ya tiene su endpoint. Ubicaciones: solo
   las de las sucursales del usuario (mismo `AccesosSucursalesHelper` del 2).
5. **Base**: solo el indicador (`20261121_ficha_producto_01.sql`, agrega la columna). Ninguna tabla nueva.

## Pasos

- **7-1** Script del indicador + interruptor en Empresas + solapas vacías detrás del indicador + **Proveedores**
  (código nuestro ↔ código del proveedor, principal). Caso del simulador `38_ficha_producto.py`.
- **7-2** **Precios** (solo pantalla sobre el endpoint existente).
- **7-3** **Ubicaciones** (asignar una existente; "Nueva ubicación" de la sucursal desde la ficha, como el 2).
- **7-4** **Imágenes** (subir, principal, orden, descripción, quitar; miniaturas por `get_imagen.php`).

Un commit por paso, cada uno con: `php -l`, la comparación del listado / ficha con el indicador apagado
(como en 6-2), el caso del simulador y la prueba contra una base sin la columna (regla 9).

## Riesgos

- Lógica repetida entre el 2 y `core/` mientras el 2 no se pase a `core/` (punto 3).
- Imágenes en la base (`conf__imagenes`): el tamaño de la base crece; se mantienen los mismos límites y la
  normalización (`img_normalizar`) del 2.
- `proveedores_busqueda` es una columna materializada: todo alta / baja / cambio de vínculo tiene que recalcularla
  (se usa la misma regla que `recalcularProveedoresBusqueda`), o la búsqueda del listado por código de proveedor
  queda vieja.
- La consola del navegador se revisa a mano en cada paso.

## Decisiones a aprobar

1. Indicador `usa_ficha_producto_completa`, apagado por defecto. **Recomendado** (regla 2), aunque los datos ya
   existan.
2. Lógica nueva en `core/productos_ficha/` para el 5, **sin tocar el 2**. **Recomendado.**
3. Orden 7-1 Proveedores → 7-2 Precios → 7-3 Ubicaciones → 7-4 Imágenes, **antes del 6-3** (marca en escalas y
   promociones). **Recomendado.**
4. En la consulta del 5, las solapas visibles en solo lectura. **Recomendado.**

## Implementación

Commits 7-1 `0454700` (indicador, interruptor, Proveedores), 7-2 `b869e38` (Precios), 7-3 `156add1` (Ubicaciones) y
7-4 (Imágenes). Script `20261121_ficha_producto_01.sql` (aplicado solo en la copia del simulador).

- `core/productos_ficha/`: `base.php` (pfic_*), `proveedores.php` (pfpv_*), `ubicaciones.php` (pfub_*), `imagenes.php`
  (pfim_*), `acciones.php` (`pfic_ajax_acciones`, acciones `ficha_*`; `pfic_acciones_lectura()` es la lista que admite
  la consulta).
- `templates/productos/ficha_solapas.php` (se incluye antes y después del `<form>` con `$pfic_parte`) y
  `assets/js/productos-ficha.js` (`ProdFicha`).
- Módulo 5: `productos.php`, `productos_consulta.php`, `productos.js` (con el indicador, el alta deja el producto
  abierto para seguir con las solapas), `productos_ajax.php`, `productos_consulta_ajax.php`. Precios usa
  `listas_precios_productos_ajax.php` sin cambios.

Diferencias con la solapa del módulo 2 (a propósito):
- Valida que producto, vínculo, ubicación e imagen sean de la empresa (el 2 no lo hace al asignar / quitar
  ubicaciones).
- Ubicaciones: solo las sucursales del usuario también en Productos (el 2 filtra solo en la consulta).
- Proveedores: el código del proveedor se graba sin escaparlo dos veces (el 2 aplica `mysqli_real_escape_string` y
  además lo pasa como parámetro, así que una comilla queda guardada como `'`).
- Imágenes: la primera queda como principal, al quitar la principal pasa a serlo la siguiente, y el orden se cambia
  con antes / después.

Pruebas: caso `38_ficha_producto.py` 47/47 (sobre una copia recién clonada con el script; la única falla de esa
corrida es la de consistencia preexistente de 2 asientos sin fecha); base sin la columna 4/4; interruptor de Empresas
4/4. La consola del navegador no se revisó.
