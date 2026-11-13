# Presentaciones de productos

Parte de la adecuación del ERP a distribuidoras (primer caso: distribuidora de productos comestibles).
Fecha: 2026-11-12.

## Decisión de arquitectura

No se crea un módulo `gestion_alimentos`. Las necesidades de una distribuidora (presentaciones, lotes y vencimientos,
conservación) se resuelven en **un solo código** (Gestión 2 y Gestión Normal 5) que se **activa por empresa**:

| Nivel | Qué define |
|---|---|
| Empresa (`conf__empresas.usa_*`, Configuración > Empresas) | Qué capacidades usa |
| Tipo de producto (etapas siguientes) | Valores por defecto (p. ej. lácteos: lote + vencimiento, refrigerado) |
| Producto | El dato o la excepción puntual |
| Páginas (`conf__paginas.requiere_*`, etapas siguientes) | Pantallas que solo ve quien usa la capacidad |

La lógica va en `core/`, la vista compartida en `templates/` y el JS en `assets/js/`; los módulos solo la incluyen
(como bancos, obligaciones o libros societarios), así no se suma trabajo de sincronización entre el 2 y el 5.

Motivos: mantener dos copias (2 y 5) ya cuesta (sincronización, desvíos, ids desplazados), una tercera lo multiplica;
lo propio de alimentos es poco frente a lo compartido (ventas, ARCA, tesorería, contabilidad); y lotes y presentaciones
también los usan otros rubros (limpieza, bebidas, farmacia).

## Etapa 1 — Presentaciones en el ABM (hecha, 2026-11-12)

- Script `_local/sql/mantenimiento/20261112_productos_presentaciones_01.sql`:
  - `conf__empresas.usa_presentaciones` (0/1, por defecto 0), interruptor "Usa presentaciones" en Configuración > Empresas.
  - `gestion__productos_presentaciones` (tabla 462): nombre, `factor` (unidades de la unidad de medida del producto),
    `codigo_barras` (EAN / DUN-14), peso bruto, largo / ancho / alto, `aplica_compra` / `aplica_venta`,
    `es_compra_defecto` / `es_venta_defecto` (una de cada por producto), orden, estado 1 / 2.
- La unidad base es el producto (factor 1, su `codigo_barras`); la tabla guarda solo las demás.
- Las presentaciones no se borran: la que se quita pasa a estado 2 (la etapa 2 va a referenciar `presentacion_id`).
- Código de barras único por empresa entre productos activos y presentaciones activas (se valida en
  `prpr_validar`; el de la unidad del producto todavía no se valida contra los demás productos).
- Servicio `core/productos_presentaciones.php` (`prpr_*`): `prpr_empresa_usa`, `prpr_listar`, `prpr_validar`,
  `prpr_guardar` (transacción propia, `FOR UPDATE` de las filas del producto), `prpr_procesar_formulario` /
  `prpr_guardar_formulario` (los dos pasos del guardado desde el ABM) y `prpr_sql_busqueda_barras`.
- Guardado desde el ABM: el formulario manda `presentaciones` (JSON). Se validan **antes** de guardar el producto (un
  error no deja el producto grabado) y se graban después, como los niveles de stock. Sin el campo, o vacío, no se tocan.
- Vista `templates/productos/presentaciones_seccion.php` + `assets/js/productos-presentaciones.js` (global `ProdPres`),
  incluidas en `productos.php` de los dos módulos y en `productos_consulta.php` del 5 (solo lectura) solo si la empresa
  usa presentaciones. Acción `obtener_presentaciones` en `productos_ajax.php` (2 y 5) y en la consulta del 5.
- El listado de productos (2 y 5) también busca por el código de barras de las presentaciones.
- De paso, `gestion_normal/productos_ajax.php` fija la página (540 / 598, antes la tomaba del request) y exige las
  funciones agregar / editar (`permisos_exigir_funcion`), como el módulo 2.
- Simulador: `_local/tests/simulacion/casos/32_productos_presentaciones.py`.

Pendiente de la etapa 1: la consulta de productos del módulo 2 (`gestion/productos_consulta.php`) no muestra la sección.

## Etapa 2 — Presentaciones en compras y ventas (a diseñar)

- Línea de OC, factura de proveedor, pedido, remito y factura de venta con `presentacion_id` + `cantidad_presentacion`;
  la cantidad en unidad base (`cantidad`) sigue siendo la que mueve stock y costos (= cantidad × factor).
- Al escanear un código de barras, si es de una presentación, la línea entra con esa presentación.
- Precio: por defecto precio unitario × factor; opcional precio propio por presentación en la lista.
- Mínimo y múltiplo de venta (`minimo_venta`, `multiplo_venta`, `permite_fraccionar`) en el producto.
- Impresión del comprobante: "2 Caja x 12 (24 u)".
- Revisar: ventas_facturas_model tiene su propia copia de la búsqueda de productos.

## Etapas siguientes (propuestas en la revisión del ABM)

1. Datos del producto para alimentos: marca (en el 5), fabricante, RNPA / RNE, origen, sin TACC, alérgenos, sellos;
   conservación (seco / refrigerado / congelado, rango de temperatura); reemplazar en el 5 los atributos de autopartes
   (lado, material, color, garantía) por los de logística.
2. Lotes y vencimientos con FEFO: `controla_lote` / `controla_vencimiento` / vida útil por tipo de producto,
   `gestion__stock_lotes`, `lote_id` en `gestion__stock_movimientos`, días mínimos de recepción y de despacho,
   bloqueo de lotes, trazabilidad para retiro de producto. Atraviesa compras, remitos, ajustes y devoluciones.
3. Listado de productos con stock real y próximo vencimiento; alta masiva desde planilla o lista del proveedor.
