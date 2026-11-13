# Importación de "Mis Comprobantes" (ARCA) para clientes FISCAL

Diseño, 2026-11-06. **Implementado el mismo día** (ver §8): página 419 `importar_arca*`, lector común
`assets/js/arca-mis-comprobantes.js`, motores de Gestión Normal sin cambios. Las secciones 3–5 son el diseño original;
donde difieren, vale §8.

## 1. Objetivo

Para un cliente de nivel **FISCAL** (empresa SIMPLIFICADA vinculada al estudio, sin perfiles), el estudio sube
"Mis Comprobantes Emitidos" y "Mis Comprobantes Recibidos" de ARCA. Los comprobantes quedan como **facturas de venta
y de compra de la empresa del cliente** (no en `estudio__minigestion_*`, que es heredada), así el Libro IVA, la
Posición de IVA, Control Fiscal y la mini-gestión en modo ERP salen de los mismos datos que usa cualquier empresa.

## 2. Qué se reutiliza

| Pieza | Dónde está | Qué hace | Limitación para el estudio |
|---|---|---|---|
| Ventas (Emitidos) | `gestion_normal/ventas_facturas_arca_import_model.php` (`vfiAnalizarArchivo`, `vfiImportar`) | Analiza y graba **Confirmado** con CAE, espejo y asiento. Duplicado = tipo + PV + número. | Rechaza rangos, moneda extranjera y filas con Otros Tributos. Exige PV con ese código y tipo habilitado en el PV. |
| Compras (Recibidos) | `gestion_normal/facturas_proveedores_model.php` (`fpArcaNormalizarFila`, `fpImportarComprobantesArca`) | Analiza, crea proveedores, graba en el **estado inicial** (Borrador). Duplicado = proveedor + tipo + PV + número, también dentro del archivo. Valida que el receptor sea la empresa. | Otros Tributos entra con aviso ("cargue las percepciones antes de confirmar"). Rechaza rangos y moneda extranjera. Exige sucursal y acceso del usuario a esa sucursal. |
| Lectura del archivo | En el **navegador**: `vfiLeerArchivoArca` (`ventas_facturas.js`) y `mapearColumnasArca` (`facturas_proveedores.js`) | Lee CSV / XLSX y manda filas ya mapeadas. | Dos lectores distintos en JS. |
| Lector de planillas | `core/bancos/planilla_lector.php` (`bco_planilla_leer`) | CSV, XLSX y XLS en el servidor, sin PhpSpreadsheet. | — |

Medición con los fixtures (5 clientes, ago–oct 2026). Esto es lo que los importadores actuales dejarían afuera o
con aviso:

| | Filas | Rango | Moneda extranjera | Otros Tributos |
|---|---|---|---|---|
| Emitidos | 307 | 1 | 1 | 1 (rechazada) |
| Recibidos | 148 | 0 | 0 | **61 (41 %, quedan en Borrador con aviso)** |

La conclusión es que en ventas el motor actual sirve casi completo. En compras, el trabajo real del estudio está en las percepciones.

## 3. Flujo

1. **Elegir cliente y período** (mini-gestión, cliente FISCAL en modo ERP). Solo clientes con empresa vinculada y
   vinculación vigente **de escritura** en ventas / compras (`vinculacionesVerificarPermiso(..., true)`).
2. **Subir archivos**: uno o varios (Emitidos y / o Recibidos, CSV o XLSX, varios meses). Se guardan en
   `uploads/estudio_arca/<estudio>/<cliente>/` (.htaccess deny), con hash SHA-256.
3. **Leer en el servidor** con un lector común nuevo, `core/arca_mis_comprobantes.php`
   (`arca_mc_leer($ruta)`). Usa `bco_planilla_leer`, detecta el sentido por los encabezados ("Denominación Receptor"
   o "Denominación Emisor"), saltea la fila de título del XLSX, normaliza la coma decimal y devuelve las mismas filas
   que hoy arma el JS. Es la oportunidad de unificar los dos lectores JS en uno solo del servidor, para gestion,
   gestion_normal y el estudio.
4. **Validar el archivo completo** antes de mirar las filas:
   - CUIT del archivo = CUIT del cliente. Sale del título del XLSX o del nombre del archivo; en Recibidos, también de la columna del receptor. Si no coincide se rechaza el archivo entero.
   - Período: las filas fuera del período elegido se informan y no se importan.
   - Período cerrado: si el IVA del mes está liquidado o el período fiscal está cerrado (`pfExigirDocumentoAbierto`),
     ese mes se rechaza con un mensaje claro.
5. **Previsualizar** con `vfiAnalizarArchivo` / `fpArcaNormalizarFila`, sin grabar. Cada fila queda como listo, duplicado,
   aviso o error, con su motivo. Se muestran totales por mes (cantidad, neto, IVA y otros tributos) para controlar contra
   el Libro IVA de ARCA. Si el mismo CAE aparece en dos clientes del estudio, se avisa (cruces).
6. **Importar** las filas listas. La importación es **idempotente**: la clave de ventas es CUIT del cliente + tipo ARCA + PV + número, y la de compras es CUIT del cliente + CUIT del emisor + tipo + PV + número. Reimportar el mismo archivo informa todo como "ya cargado".
   - Ventas: Confirmado con CAE (como hoy).
   - Compras sin Otros Tributos: se confirman (propuesta; hoy quedan en Borrador). Con Otros Tributos quedan en Borrador
     hasta asignar las percepciones (paso 7).
7. **Percepciones pendientes**: una grilla con los Borradores que tienen Otros Tributos. Para cada uno se reparte el importe en impuesto (IVA / IIBB / otros) y jurisdicción. Se propone la jurisdicción de la sede del cliente y, si el cliente está en Convenio Multilateral, se elige. Recién ahí se confirma. ARCA suma IVA + IIBB en una sola columna (caso Agro), así que el reparto no se puede automatizar.
8. **Reporte**: un lote por importación, con las cantidades por estado y un CSV de errores. Las filas con error se pueden corregir a mano en la empresa o volver a subir.

## 4. Lo que hay que cambiar en los motores compartidos

- **Contexto "estudio"**: hoy los dos motores validan el acceso del usuario a la página y a la sucursal de la empresa
  (`AccesosSucursalesHelper::exigirAccesoSucursal`). Un cliente FISCAL no tiene perfiles, así que el usuario del estudio
  no tiene acceso. Propuesta: un parámetro explícito `['contexto' => 'ESTUDIO', 'estudio_id' => N]`, que solo
  puede pasar el `_ajax.php` del estudio después de verificar la vinculación de escritura y la función nueva. Con ese
  parámetro se saltea solo el control de sucursal; todo lo demás se valida igual. No usar un usuario genérico.
- **Infraestructura mínima de comprobantes**: un cliente FISCAL hoy no tiene sucursal ni PV, y los dos motores los
  exigen. El importador la crea sola la primera vez:
  - Sucursal y boca: `inicioCrearSucursalRapida`.
  - Un PV por cada código que aparezca en Emitidos, en modo manual (sin ARCA), con los tipos del archivo habilitados en `gestion__puntos_venta_comprobantes`.
  - Un concepto genérico "Venta según ARCA" sin stock, como `vfiProductosConcepto`.
  Esto cambia la definición de FISCAL a "sin perfiles", pero con la infraestructura de comprobantes.
- **Asientos**: el motor de ventas genera el asiento, y eso necesita plan de cuentas y conceptos contables. Ver pregunta 1.
- **Casos que hoy se rechazan** (para una segunda etapa, o carga manual con aviso):
  - Rango de números (factura diaria B a consumidor final): proponemos un comprobante con el número "desde" y el rango en las observaciones; la clave de duplicado es el "desde".
  - Moneda extranjera (Factura E): entra con moneda + tipo de cambio de la fila. Requiere la moneda en la empresa (`emp_moneda_id`).
  - Ventas con Otros Tributos (percepción practicada): Borrador con el mismo reparto del paso 7.

## 5. Permisos y tablas

- Función nueva en la página 417 (mini-gestión), `importar_arca`, id 3556 (siguiente libre del compartido). Se usa
  `permisos_exigir_accion` y CSRF, como `lectura_*` de facturas de proveedores.
- `estudio__arca_importaciones` (lote):
  - empresa_id (estudio), cliente_id, empresa_cliente_id
  - sentido (EMITIDOS / RECIBIDOS), período desde / hasta
  - archivo y su hash SHA-256 (si el hash ya se importó, se avisa)
  - cantidades: listos, importados, duplicados, con aviso, errores
  - usuario_id, fecha y detalle de errores (texto)
- No hace falta tabla de mapeo: la clave natural ya está en las facturas de la empresa del cliente (tipo + PV + número; en compras, más el proveedor).

## 6. Pruebas

- Caso nuevo del simulador con los 5 clientes de los fixtures, importando los 3 meses de los dos sentidos. Se compara contra `esperado.json`: cantidades, ventas, neto, débito, crédito computable y otros tributos por mes. Después se reimporta para comprobar la idempotencia y se controla que los 8 cruces de CAE cuadren.
- Después, con los CSV reales que vas a pasar: mismo control contra el Libro IVA de ARCA del período.

## 7. Preguntas abiertas

1. **Asientos en FISCAL.** Hay dos opciones:
   - (a) Exigir plantilla con plan de cuentas al crear la empresa FISCAL. El motor genera asientos que nadie mira, pero sin tocar el motor.
   - (b) Un parámetro de empresa "sin contabilidad" que hace que los motores no generen asientos.

   Recomiendo (a): es más simple y deja la puerta abierta a pasar el cliente a CONTABLE sin rehacer nada.
2. ¿Las compras sin Otros Tributos se confirman al importar o quedan todas en Borrador para revisión?
3. Rangos y moneda extranjera: ¿entran en la primera etapa o quedan como error con carga manual?
4. ¿La pantalla va dentro de mini-gestión (recomendado, es donde se trabaja el cliente) o es una página propia?
5. Bienes de uso en compras (el caso de la compra de neumáticos de $ 4,7 M): ¿se marcan para que el contador los revise o se ignora el tema en FISCAL?

## 8. Lo implementado (2026-11-06)

- Página 419 "Importar Mis Comprobantes" (`importar_arca.php` / `_ajax.php` / `_model.php` / `.js`), funciones 3556 Acceder, 3557 `importar_arca`, 3558 `confirmar_compras` (script `20261106_estudio_importar_arca_01.sql`).
- Lectura en el navegador con `ArcaMC` (`assets/js/arca-mis-comprobantes.js`): Emitidos en el formato de `vfiAnalizarFila`, Recibidos en el de `fpArcaNormalizarFila`; CSV leído como texto (`;`, coma decimal, BOM). `facturas_proveedores.js` de gestion y gestion_normal ahora usan este lector. La lectura JS de Emitidos de `ventas_facturas.js` (`vfiLeerArchivoArca`) no existe: el modal de ventas_facturas.php quedó sin JS (pendiente).
- Motores sin cambios: `vfiAnalizarArchivo` / `vfiImportar` y `fpPrevisualizarImportacionArca` / `fpImportarComprobantesArca` de **gestion_normal** (los clientes son empresas del módulo 5), con `empresa_id` = la del cliente.
- Antes de cada llamada: `vinculacionesVerificarPermiso(cliente, estudio, ventas|compras, true)` y `AccesosSucursalesHelper::accesoDelegadoPorVinculacion()` (acceso a las sucursales solo en memoria y en ese request: el estudio no tiene perfil en una empresa FISCAL). CUIT del archivo = CUIT del cliente.
- Prerrequisitos (`ia_diagnostico` / `ia_preparar`): alícuotas y tipos por copia autorizada desde una plantilla (plan de cuentas + datos básicos + tipos); alícuota gravada faltante con las cuentas de la de 21 %; sucursal; PV con el código del archivo (RCEL) y sus tipos habilitados; concepto de venta ARCA-VTA. El asistente de alta ahora copia también `datos_basicos` (antes las empresas nuevas quedaban sin alícuotas).
- Compras: condición de pago = la de cuenta corriente con menos días. Confirmación masiva (aprobar + confirmar con `ejecutarTransicionEstado`) solo de las que no tienen Otros Tributos o los tienen detallados.
- Prueba (copia `gestion_multipyme_e2e`, CUIT 30719136482): Emitidos 60/60 confirmados con CAE, espejo y asiento, totales iguales al archivo, redondeo de B absorbido en el IVA, reimportación 60 duplicados; Recibidos 109 importados (2 Facturas A de $ 0 rechazadas), 37 tiques A con Otros Tributos y residual de combustible en Borrador, reimportación 109 duplicados; confirmación masiva 72 confirmadas con asiento, 1 omitida por Otros Tributos.
- Pendiente de OK: concepto por defecto "Otros tributos sin clasificar" (§9).

## 9. Propuesta: "Otros tributos sin clasificar" (esperando OK)

ARCA informa una sola columna "Otros Tributos" sin decir impuesto ni provincia. En la prueba, 37 de 111 compras la traen.
Hoy quedan en Borrador hasta que alguien carga el detalle de percepciones a mano. Propuesta:

1. Tipo de impuesto global nuevo **"Otros tributos sin clasificar"** en `gestion__impuestos_tipos`, con `aplica_compra = 1`, sin
   retención y sin cómputo en IVA ni IIBB.
2. En la empresa del cliente, una configuración de ese impuesto (`gestion__empresas_impuestos_config`) que tome la cuenta de un concepto contable
   nuevo `OTROS_TRIBUTOS_A_CLASIFICAR` (cuenta transitoria de activo), que se crea al preparar la empresa.
3. Opción de importación de compras: cargar el importe de Otros Tributos como una línea de `gestion__comprobantes_impuestos`
   con esa configuración. La compra queda "lista" y se puede confirmar en lote. El Libro IVA la muestra en su propia
   columna y la Posición de IVA **no** la computa.
4. Reclasificación posterior (antes de liquidar IVA / IIBB): pasar la línea a Percepción IVA / IIBB (con jurisdicción) /
   Ganancias. En el cuadre de Control Fiscal, un saldo en la cuenta transitoria aparece como pendiente.

Sin el OK, las compras con Otros Tributos siguen en Borrador y la confirmación masiva las omite.
