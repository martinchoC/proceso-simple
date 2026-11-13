# 12 · Conciliación bancaria — plan de ejecución en VSCode (Claude Code)

## Estado al 2026-10-28 (rama `conciliacion-bancaria`, sin commitear)
Pasos 0–6 ejecutados en local. Simulador `26_conciliacion_bancaria.py`: 134 chequeos OK en gestion y gestion_normal.
Desvíos y decisiones respecto del plan:
- **Paso 1**: son 8 tablas, no 9 (el modelo de §3 enumera 8). Ajustes del `revisor-sql`: `SET NAMES`, FK de `regla_id` y de `tesoreria_movimientos.conciliacion_id`, siembra desde conceptos ITEM. Tipos corregidos contra la base: `factura_proveedor_id` INT con signo, `impuesto_tipo_id` SMALLINT UNSIGNED.
- **Paso 2**: PhpSpreadsheet **no se instaló** (la descarga de Composer / paquetes externos quedó bloqueada en la sesión). Se escribió un lector propio sin dependencias (`core/bancos/planilla_lector.php`: CSV, XLSX por ZipArchive, XLS BIFF8) que lee bien la muestra; si se decide usar PhpSpreadsheet, se reemplaza solo `bco_planilla_leer()`.
- **Paso 3**: criterios de aceptación OK (`_local/tests/bancos/test_extracto_parser.php`, 48 controles).
- **Paso 4**: script `_03_paginas` hecho a mano (no con el agente): páginas 128–133 / 628–633, funciones 3450–3481 / 8450–8481.
- **Paso 5**: la lógica quedó en `core/bancos/` (compartida por los dos módulos). Proveedor del banco = `gestion__tesoreria_cuentas.proveedor_entidad_id` (§9.5). La generación de gastos no es una única transacción (ver CLAUDE.md, "Conciliación bancaria"). SIRCREB y los créditos del banco (C8) se muestran excluidos hasta la respuesta del contador.
- **Paso 6**: revisores corridos; manual en `_local/docs/manuales/`. No se probó en el navegador (las vistas se renderizan sin errores en CLI y los JS pasan `node --check`).

Leer primero `12_conciliacion_bancaria.md` (diseño y hallazgos del extracto). Scripts: `_local/sql/mantenimiento/20261028_conciliacion_bancaria_01_estructura.sql` y `_02_conceptos_iniciales.sql` (sin ejecutar). Cada paso termina con una verificación; no pasar al siguiente si falla.

## Paso 0 · Preparación (10 min)
1. Rama nueva: `git checkout -b conciliacion-bancaria`.
2. Backup de la base local (`mysqldump gestion_multipyme > _local/tests/simulacion/salidas/backup_gestion_multipyme_antes_conciliacion.sql`).
3. Copiar el extracto de muestra a `_local/tests/fixtures/extracto_muestra.xls`. **Contiene CUIT y nombres reales**: anonimizarlo antes de commitearlo, o agregarlo a `.gitignore`.
4. Verificar en el dump (`_local/sql/casalucho_local.sql`) lo que el DDL deja sin FK: tipo de `gestion__productos.producto_id`, de `gestion__facturas_proveedores.factura_proveedor_id` y el catálogo de impuestos. Ajustar el script 01 si difieren.

## Paso 1 · Base de datos (local)
1. Correr `01_estructura.sql` y luego `02_conceptos_iniciales.sql` en la base local.
2. Verificación: 9 tablas `gestion__bancos_*`, columna `gestion__tesoreria_movimientos.conciliacion_id`, conceptos sembrados en Casa Lucho (empresa 2). Volver a correrlos: tiene que ser idempotente.
3. Pasar por el agente `revisor-sql` (MariaDB 10.4 y MySQL 8).

## Paso 2 · PhpSpreadsheet local (sin Composer en el proyecto)
1. Ver el PHP de **local y de producción**: `php -v`. Con PHP ≥ 8.1 usar `^2.3` (o la última estable); con 7.4–8.0, `^1.29`. Elegir la versión más nueva que soporten **los dos** servidores.
2. En una carpeta temporal fuera del proyecto: `composer require phpoffice/phpspreadsheet:<versión fija>` y `composer check-platform-reqs` (extensiones: mbstring, xml, zip, gd, iconv, fileinfo, libxml, SimpleXML, XMLReader/Writer).
3. Copiar `vendor/` completo a `core/lib/phpspreadsheet/vendor/` y cargar solo con `require_once '.../vendor/autoload.php'` desde el servicio de bancos. Versionar la carpeta (producción no tiene Composer) y anotar la versión fija en `CLAUDE.md`.
4. Lectura de archivos: `IOFactory::identify()` + reader con `setReadDataOnly(true)`; aceptar `.xls`, `.xlsx`, `.csv`; nada de macros ni enlaces externos.
5. Verificación: script de prueba en `_local/tests/` que lee `extracto_muestra.xls` y devuelve 49 filas (el archivo no se pudo probar con PhpSpreadsheet desde el entorno de diseño; sí se validó con otra librería).

## Paso 3 · Parser y reglas (sin pantallas todavía)
Archivo nuevo `core/bancos/extracto_parser.php` (y `extracto_reglas.php`): formato → filas normalizadas → clasificación. Criterios de aceptación con la muestra:
- 49 movimientos, de 02-oct-2026 a 21-oct-2026; fechas `d-M-Y` en español.
- Orden invertido a cronológico; **cadena de saldos válida en 48/48 pares**; saldo inicial 499.268,35, saldo final 90.321,62, Σ importes −408.946,73.
- Número de cuenta `6229-69485/0` leído del encabezado y comparado con la cuenta elegida.
- Clasificación (con las reglas de §10 del documento): 4 COMISION, 23 LEY25413, 8 SIRCREB y 14 movimientos propios; ninguna línea sin concepto.
- Hash de línea estable: importar dos veces el mismo archivo no duplica nada.

## Paso 4 · Registro de páginas y permisos (script 03)
Agente `disenador-proceso`: `conf__tablas`, `conf__tablas_estados_registros`, `conf__paginas`, `conf__paginas_funciones` y perfiles. Páginas: Formatos bancarios, Conceptos bancarios, Reglas, Importar extracto, Conciliación bancaria, Gastos bancarios. Usar `depositos` como plantilla de ABM simple.

## Paso 5 · Pantallas, en este orden
1. ABM Formatos / Conceptos / Reglas (patrón de 4 archivos, `ajax_guard`, `empresa_id` siempre del contexto).
2. Importar extracto: previsualizar → confirmar.
3. Conciliación contra tesorería (automática, sugerida, manual, deshacer).
4. Gastos bancarios: botón "Generar comprobante" (C_RB + OP por el circuito existente; NC para créditos).

## Paso 6 · Pruebas y revisión
- Casos nuevos en `_local/tests/simulacion/casos/` (importación, duplicados, conciliación N:M, generación, deshacer, mes cruzado, período de IVA liquidado) con el agente `tester-circuitos`.
- Revisores: `revisor-sql`, `revisor-ajax`, `revisor-convenciones`, `revisor-casos-borde`.
- Actualizar `CLAUDE.md` (sección Tesorería) y `manual-usuario`.

## Antes de habilitar el botón de gastos (no bloquea los pasos 0–5.3)
Respuestas del contador:
1. ¿Las comisiones del extracto (`COMISION DATANET`, `COMISION TRANSFERENCIA/GIRO`) ya incluyen IVA? Si sí, `UPDATE … SET iva_incluido = 1`. Si el banco factura el IVA aparte, ¿dónde se ve?
2. Tratamiento de **SIRCREB** (retención de IIBB sobre créditos) y de la **Ley 25.413** (débito y crédito).
3. Que el resumen bancario (cód. 039) sirve de respaldo del crédito fiscal y el código del Libro IVA Digital.
4. Cómo se registra el **fondo común de inversión**.

## Prompt sugerido para Claude Code (pegar tal cual)
```
Lee CLAUDE.md, _local/docs/gestion/12_conciliacion_bancaria.md y
_local/docs/gestion/12_conciliacion_bancaria_plan_ejecucion.md.
Trabajá en la rama conciliacion-bancaria. Ejecutá SOLO el Paso N del plan
(indicá cuál) y detenete al terminar su verificación. No ejecutes scripts SQL
contra producción. No inventes patrones: seguí CLAUDE.md y preguntá ante la duda.
Mostrame el resultado de la verificación antes de pasar al paso siguiente.
```
