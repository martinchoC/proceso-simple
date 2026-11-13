# 12 · Conciliación bancaria — proceso v0.2

Estado: diseño funcional cerrado con Pablo (2026-10-28) e **implementado en local** el mismo día (rama `conciliacion-bancaria`; scripts 01/02/03 aplicados solo en la base local). Volumen inicial: ~100 movimientos/mes por cuenta.

**Diferencias entre lo implementado y este borrador** (el detalle técnico está en CLAUDE.md, "Conciliación bancaria"):
- §4: sin PhpSpreadsheet. Se usa un lector propio (`core/bancos/planilla_lector.php`: CSV, XLSX, XLS BIFF8). Los archivos van a `uploads/extractos_bancarios/<empresa>/` con `.htaccess` deny, no fuera del webroot. La importación también descarta como duplicada una línea con igual fecha e importe que otra ya importada (el banco reordena el día y cambia el saldo).
- §6: un movimiento de tesorería conciliado que se anula (recibo, OP, C_RB) deshace su conciliación en la misma transacción (`tesoreriaAnularMovimiento`). Un lote con líneas conciliadas, que lo estuvieron o que otro lote superpuesto tomó como propias, no se anula.
- §7: no es una única transacción, porque las funciones de facturas de proveedor confirman la suya. Se serializa con `GET_LOCK` por cuenta, se revalida todo con lock antes de conciliar y, si falla, se compensa (borrador → eliminado, confirmado → anulado); si la compensación falla, se avisa al usuario con el id. Exige además los permisos de Facturas de Proveedores (agregar / aprobar / confirmar; anular para deshacer). Cuentas en otra moneda: bloqueadas. Créditos del banco (C8) y SIRCREB: excluidos hasta la respuesta del contador.
- §9.5 resuelto: `gestion__tesoreria_cuentas.proveedor_entidad_id` (rol PRO), elegido en Gastos Bancarios.

## 1. Alcance
- Importar el extracto del banco (Excel/CSV) con un **formato configurable por banco**.
- Clasificar cada línea con **conceptos** y **reglas** de texto por banco.
- Conciliar el extracto **contra lo ya registrado en tesorería** (`gestion__tesoreria_movimientos`); el extracto nunca crea movimientos propios.
- Los **gastos que origina el banco** se contabilizan con el botón "Generar comprobante": un `C_RB` (Resumen bancario, ARCA 039) + OP automática `DEB_CTA` — circuito que ya existe (scripts `20261011_debito_en_cuenta_01/02`, `fpGenerarOrdenPagoAutomatica`).
- Fuera de alcance (E3): API bancaria, conciliación de cupones de tarjeta.

## 2. Decisiones confirmadas
| # | Decisión |
|---|---|
| C1 | Comisiones y gastos se cargan como comprobante de proveedor para que el IVA y las percepciones entren al Libro IVA Compras. |
| C2 | Se genera **desde la conciliación**, con botón, no al importar: permite reasignar conceptos mal clasificados antes. |
| C3 | Cada concepto de gasto tiene `producto_id` (servicio sin stock); la cuenta contable sale del producto. IVA y percepciones **no** son productos: van como impuestos del comprobante. |
| C4 | Ley 25.413 es otro producto no gravado (`BCO_LEY25413`) dentro del mismo comprobante. |
| C5 | Fecha de emisión = fecha del último movimiento del grupo; **se bloquea una selección que cruce de mes**. ~~Un comprobante por banco/cuenta por mes~~: desde el 2026-10-30 puede haber varios en el mes (las líneas que quedaron pendientes van en otro, AAAAMM02...). |
| C6 | El banco no envía facturas: el **resumen es el respaldo del crédito fiscal** (a confirmar con el contador, ver §9). El comprobante es definitivo, no provisorio. |
| C7 | El botón solo toma conceptos `GASTO_BANCARIO`. Cheques, depósitos y transferencias (`MOVIMIENTO_PROPIO`) solo se concilian contra tesorería. |
| C8 | Los créditos que origina el banco (intereses a favor, reintegros) generan una **nota de crédito de proveedor** en el mismo botón. |
| C9 | Formato de archivo configurable por banco, versionado por vigencia. |

## 3. Modelo de datos (script `20261028_conciliacion_bancaria_01_estructura.sql`)
`gestion__bancos_formatos` + `_formatos_columnas` (mapeo por filas, sin JSON) · `gestion__bancos_conceptos` · `gestion__bancos_reglas` · `gestion__bancos_importaciones` · `gestion__bancos_extracto` · `gestion__bancos_conciliaciones` + `_conciliaciones_items` (N:M) · columna nueva `gestion__tesoreria_movimientos.conciliacion_id` (ya existen `conciliado` y `f_conciliacion`).

Estados de la línea del extracto: `PENDIENTE` (sin concepto) → `CLASIFICADA` → `CONCILIADA`; `IGNORADA` para lo que el usuario descarta. Reasignar concepto se permite mientras no esté CONCILIADA.

## 4. Importación (dos pasos, una petición AJAX cada uno)
1. **Previsualizar**: sube el archivo, parsea con el formato elegido, devuelve primeras filas, totales y el control `saldo_inicial + Σ importes = saldo_final`. No persiste nada. Es también la pantalla "Probar formato".
2. **Confirmar**: transacción única; INSERT multi-fila; hashes existentes precargados en bloque (sin N+1); aplica reglas y deja cada línea en CLASIFICADA o PENDIENTE.
- Duplicados: `UNIQUE (empresa, cuenta, archivo_hash)` para el archivo y `UNIQUE (empresa, cuenta, hash_mov)` para la línea. `hash_mov` incluye fecha, descripción, importe, saldo y un **ordinal de ocurrencia**, para no descartar dos movimientos legítimos idénticos.
- Seguridad: `ajax_guard` con `pagina_idx` fijo, empresa de `$ctx`, CSRF en las escrituras, archivo guardado fuera del webroot, solo `.xls`, `.xlsx` y `.csv`, tope ~5 MB, sin macros, prepared statements con tipos verificados, JSON limpio (sin warnings antes de `json_encode`), descripciones escapadas al mostrar.
- PhpSpreadsheet: no hay gestor de paquetes en el proyecto; la librería se agrega local con versión fija (regla de assets) o se lee el xlsx con ZipArchive + XML. A decidir.

## 5. Clasificación
Reglas por banco ordenadas por `prioridad`; gana la primera que coincide (`CONTIENE` / `EMPIEZA` / `REGEX`, con `aplica_a` DEBITO/CREDITO/AMBOS). Regex validada y acotada en PHP. Sin regla → PENDIENTE (no entra a ningún flujo hasta clasificarla).

## 6. Conciliación contra tesorería (movimientos propios)
Candidatos: `tesoreria_cuenta_id` igual, `conciliado = 0`, dirección igual al signo del extracto (INGRESO ↔ crédito, EGRESO ↔ débito).
1. Importe exacto + referencia (n° de cheque/transferencia) + ventana ±N días → conciliación automática.
2. Importe exacto + un único candidato en la ventana → sugerida, confirma el usuario.
3. Agrupación N:1 (depósito de varios cheques): suma de movimientos = línea del banco; búsqueda acotada por fecha y cantidad.
4. Resto → manual, con sugerencias y diferencia en vivo.
Una conciliación escribe cabecera + items, marca `extracto.estado = CONCILIADA`, `conciliacion_id`, y `tesoreria_movimientos.conciliado = 1`, `f_conciliacion`, `conciliacion_id`, todo desde PHP en una transacción, con locks en orden ascendente de id. "Deshacer" revierte todo (estado 38, motivo obligatorio).

## 7. Botón "Generar comprobante" (gastos bancarios)
Entrada: cuenta + mes. Toma todas las líneas `GASTO_BANCARIO` CLASIFICADAS no conciliadas (el usuario puede excluir alguna).
Validaciones (PHP, antes de tocar nada): mismo mes (C5); todas con concepto; cada `ITEM` con producto y cuenta resueltos; `PERCEPCION_*` con `impuesto_tipo_id`; proveedor del banco resuelto (entidad con rol `BAN`/`PRO` vinculada a la cuenta); **control de IVA**: Σ IVA del extracto vs Σ(base gravada × alícuota del producto) con tolerancia de centavos; si no cierra, alerta y no genera.
Ejecución (una transacción):
1. `SELECT … FOR UPDATE` de las líneas (evita doble clic / dos usuarios).
2. Alta del `C_RB` por el **modelo existente** de facturas de proveedor (`agregarFacturaProveedor`), con `tesoreria_cuenta_debito_id` = la cuenta; ítems agrupados por producto; las líneas No gravado/Exento van con la base en `no_gravado`/`exento` (`insertarDetallesFactura`); IVA y percepciones como impuestos adicionales.
3. Confirmación por `ejecutarTransicionEstado`, que dispara `fpPostConfirmacion` → OP automática `DEB_CTA` (incluye `pfExigirDocumentoAbierto`: respeta los períodos fiscales cerrados).
4. Conciliación `origen = GASTOS` con `comprobante_id`; líneas a CONCILIADA.
5. Si hay créditos del banco: segundo comprobante, nota de crédito de proveedor (signo `-`), asociada al `C_RB`.
Rollback total ante cualquier error. No se escribe SQL contable propio: se reutiliza el circuito existente.
Deshacer: `fpAnularComprobanteConfirmado` (anula OP, asiento, imputaciones); no permitido si el período de IVA está liquidado o cerrado. Las líneas vuelven a CLASIFICADA.
Idempotencia: cada línea guarda `conciliacion_id`; una línea conciliada no entra de nuevo.
Aviso previo: si el extracto importado no llega a fin de mes, se avisa antes de generar para no partir el mes en dos comprobantes.

## 8. Pantallas
Importar · Conciliar (dos grillas: extracto pendiente y tesorería pendiente, con totales y diferencia) · Gastos bancarios (botón) · Lotes · ABM de Formatos, Conceptos y Reglas. Patrón de 4 archivos + registro en `conf__tablas` / `conf__paginas_funciones`. Dashboard de caja y bancos: sumar saldo según banco, saldo contable y pendiente de conciliar.

## 9. Pendientes y riesgos
1. **Contador**: confirmar que el resumen bancario (cód. 039) sirve de respaldo del crédito fiscal de IVA y de las percepciones, y el código del Libro IVA Digital.
2. Verificar en el dump: tipo de `gestion__productos.producto_id`, de `factura_proveedor_id` y el catálogo de impuestos (`impuesto_tipo_id` de percepciones IVA/IIBB sufridas) antes de ejecutar y para agregar las FK.
3. Archivos reales (anonimizados) de Nación, Provincia y Galicia para cargar los formatos y las reglas; sin ellos los parsers son supuestos.
4. Productos de gastos adicionales (sellados, seguros): se siembran como `BCO_*` nuevos siguiendo `debito_en_cuenta_02`.
5. Proveedor "banco" por cuenta: definir dónde se vincula la cuenta de tesorería con la entidad proveedora (campo nuevo en la cuenta o tabla de vínculo).
6. Entender el tratamiento de intereses a favor y reintegros con el contador (C8).
7. Script 03: `conf__tablas`, páginas, funciones y permisos; casos del simulador `_local/tests/simulacion` para importación, conciliación, generación y deshacer.

## 10. Hallazgos del extracto de muestra (`consultaMovimientos_2.xls`, 2026-10-28)
Archivo `.xls` (BIFF, generado por JasperReports), hoja `report`, 49 movimientos del 02 al 21-oct-2026, cuenta `6229-69485/0` en el encabezado.
- **Estructura**: datos en las columnas B–E (Fecha, Descripción, Importe, Saldo), encabezado en la fila 6, datos desde la fila 8, y 2 filas de aviso al pie (la lectura corta en la primera fila sin fecha válida). Sin columna de referencia ni fecha valor.
- **Fecha como texto en español** (`21-oct-2026`): el formato necesita `idioma_fecha = ES` y `formato_fecha = d-M-Y`.
- **Orden**: lo más nuevo primero (`orden_archivo = DESC`). El saldo es **posterior** al movimiento. Verificado: `saldo(i) = saldo(i+1) + importe(i)` en 48 de 48 pares → el control de importación es la **cadena de saldos línea a línea** (detecta filas faltantes o duplicadas), no solo el total. Saldo inicial 499.268,35 y final 90.321,62; Σ importes = −408.946,73.
- **Importe con signo** (`modo_importe = SIGNO`): negativo = débito.
- **Número de cuenta** en el encabezado → se valida contra `gestion__tesoreria_cuentas.numero` para evitar importar el archivo en la cuenta equivocada (`cuenta_fila/columna/regex`).
- **CUIT dentro de la descripción** (transferencias `TRANSF DE … (30541512590)`, `CR.TRAN. 30526358054…`): `referencia_regex` lo extrae y permite conciliar el crédito contra la cobranza del cliente/entidad con ese CUIT e igual importe.
- **Composición**: 35 de 49 líneas son cargos del banco: Ley 25.413 débito/crédito (23 líneas, −31.854,87), SIRCREB (8, −38.131,40), comisiones (4, −1.874,45). Las otras 14 son movimientos propios (transferencias, DEBIN, pago de servicios, fondo común de inversión).
- **No hay líneas de IVA ni de percepciones**: las comisiones vienen en un único importe (p. ej. `COMISION DATANET −144,13`). Se agregó `iva_incluido` en el concepto: si el contador confirma que ya incluyen IVA, neto = importe / 1,21 y el IVA se calcula por diferencia. **Sin esa confirmación no se activa.** *(2026-10-30, reemplazado: el IVA es solo el que el extracto declara en líneas de IVA; base gravada = IVA / alícuota y el resto no gravado. Sin líneas de IVA no hubo IVA liquidado y todo va no gravado. `iva_incluido` quedó sin uso y sin control contra la alícuota.)*
- **SIRCREB no es una percepción facturada por el banco**: es una retención de IIBB sobre créditos (régimen de recaudación). Se agregó el rol `RETENCION_IIBB`; cómo se contabiliza (crédito de IIBB a favor, jurisdicción, cuadre en Control Fiscal) lo define el contador antes de habilitar el botón para ese concepto. *(2026-10-30: definido — SIRCREB entra al C_RB como percepción IIBB recibida de la provincia elegida en el concepto; percepciones de IVA y Ganancias, nacionales; las de IIBB, por provincia. Script `_05_percepciones_jurisdiccion`.)*
- **Ley 25.413 ocurre en dos variantes** (`IMPUESTO DEBITO` / `IMPUESTO CREDITO`): un solo concepto y producto `BCO_LEY25413` con dos reglas; en el comprobante se agrupa por producto en una línea. El tratamiento fiscal como pago a cuenta es otra consulta para el contador.
- Movimientos que no son gasto y necesitan regla propia: `DB.DEBIN`, `P.SERV` (pago de servicios, se concilia contra la OP de tesorería), `FONDO COMUN DE INVERSION` (suscripción y rescate: ver con el contador si va a una cuenta de tesorería tipo VALORES/inversión).

### Formato y reglas propuestos para este banco (cargar desde el ABM)
Formato: tipo_archivo = **XLS** (PhpSpreadsheet lee ambos; ya incluido en el ENUM), hoja `report`, `fila_inicio = 8`, `formato_fecha = d-M-Y`, `idioma_fecha = ES`, `orden_archivo = DESC`, `saldo_posterior = 1`, `modo_importe = SIGNO`, columnas FECHA=1, DESCRIPCION=2, IMPORTE=3, SALDO=4 (0 = A), `cuenta_fila = 5`, `cuenta_regex = Cuenta:\s*([0-9\-/]+)`, `referencia_regex = \(?(\d{11})\)?`.
| Prioridad | Coincidencia | Patrón | Aplica a | Concepto |
|---|---|---|---|---|
| 10 | EMPIEZA | `COMISION` | DEBITO | COMISION |
| 20 | REGEX | `^IMPUESTO (DEBITO|CREDITO)\s*-\s*LEY 25413` | DEBITO | LEY25413 |
| 30 | CONTIENE | `SIRCREB` | DEBITO | SIRCREB |
| 40 | EMPIEZA | `DB.DEBIN` | DEBITO | DEBIN |
| 50 | EMPIEZA | `P.SERV` | DEBITO | PAGO_SERVICIOS |
| 60 | CONTIENE | `FONDO COMUN DE INVERSION` | AMBOS | FCI |
| 70 | EMPIEZA | `TRANSF` / `CR.TRAN.` | AMBOS | TRANSFERENCIA |
