# Marca comercial del producto

Estado: **plan a aprobar** (2026-11-15).

## Pedido

"En gestion_normal, a los productos deberíamos agregar marca, ¿no?" (2026-11-15). Para la distribuidora: la marca
del fabricante (Arcor, Molto, La Serenísima) para buscar, filtrar, hacer promociones por marca y, más adelante,
mostrarla en el carrito.

## Lo que hay hoy (base local y código, solo lectura)

- `gestion__marcas` / `gestion__modelos` / `gestion__submodelos` son la **marca del vehículo** de la compatibilidad
  de autopartes del módulo 2 (`gestion__productos_compatibilidad.marca_id`); `gestion__listas_precios_reglas.marca_id`
  también es de vehículo. El módulo 5 no las tiene a propósito (CLAUDE.md).
- `gestion__productos` no tiene marca en ningún módulo.
- Antecedente a copiar: las presentaciones (doc 17). El ABM de productos (2 y 5) manda un campo extra, `core/` lo
  valida antes y lo graba **después** del alta / edición del producto, sin tocar sus INSERT / UPDATE
  (`prpr_procesar_formulario` / `prpr_guardar_formulario`), y la sección del formulario es una plantilla común.

## Propuesta

Indicador `conf__empresas.usa_marcas_comerciales` (apagado por defecto).

1. **Tabla nueva** `gestion__productos_marcas` (empresa, nombre, estado; nombre único activo por empresa) y columna
   opcional `gestion__productos.marca_comercial_id` (NULL). No se usa `gestion__marcas`.
2. **ABM de marcas en una ventana dentro de Productos** (botón "Marcas", como "Canales" en Listas de precios): alta,
   edición, activar / inactivar; sin página nueva ni configuración de permisos (usa las funciones agregar / editar de
   Productos).
3. **Producto** (módulos 2 y 5, y la consulta del 5): selector "Marca" en el formulario (plantilla común), grabado
   aparte con `core/productos_marcas.php` (`pmar_*`), y la marca en la búsqueda del listado (como el código de barras
   de las presentaciones).
4. **Escalas y promociones** (doc 22): `gestion__precios_reglas_venta.marca_comercial_id` en el alcance de la regla
   ("10 % en toda la marca X"), en la página y en `precio_resolver`.
5. **Asignación en masa**: en la ventana de marcas, "asignar esta marca a los productos que contienen …" (búsqueda
   por nombre / código, con vista previa), para no editar producto por producto.

Orden: 6-1 migración + `core/productos_marcas.php` + pruebas; 6-2 ventana de marcas y campo en productos (2 y 5);
6-3 escalas y promociones por marca. Comparación antes / después en los pasos que tocan productos y precios.

## Qué no cambia

Con el indicador apagado: nada. La compatibilidad de vehículos (marca / modelo / submodelo del módulo 2) sigue igual.

## Decisiones a aprobar

1. **Tabla nueva** (recomendado) o reutilizar `gestion__marcas` (mezcla marcas de autos y de productos).
2. **ABM en una ventana dentro de Productos** (recomendado) o página propia en el menú.
3. **Alcance**: ABM + producto + búsqueda + escalas y promociones por marca (recomendado ahora); filtros por marca en
   listas de precios, reportes y carrito, después.
4. **Marca opcional** en el producto (recomendado) u obligatoria con el indicador prendido.
5. **Asignación en masa por búsqueda** (recomendado) o importación desde planilla.
