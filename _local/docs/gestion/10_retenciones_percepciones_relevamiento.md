# Retenciones y percepciones (Ganancias, IVA, IIBB): relevamiento, brechas y plan

Estado: relevamiento, brechas y plan del 2026-10-15. Base relevada: `gestion_multipyme` local + código de
`modules/gestion` (el módulo 5 `gestion_normal` es el mismo código).

**Decisiones (Pablo, 2026-10-16):**
- Casa Lucho (empresa 2) es agente de retención de IVA, Ganancias, Seguridad Social (SUSS), IIBB Buenos Aires e IIBB Neuquén.
- Los regímenes son los generales, cargados por el usuario (no se siembran valores), en un **catálogo global**.
- Las retenciones y percepciones, practicadas y sufridas, se guardan en la base:
  - las practicadas se ingresan al organismo que corresponda;
  - las sufridas se usan en la liquidación de cada impuesto.
- No se usan todavía archivos de padrones ni conciliaciones para cotejar las sufridas.

**Avance:**
- Etapa 1 hecha el 2026-10-16. Script `20261016_retenciones_practicadas_02_registro.sql`, que también da de alta la config inactiva de IIBB Neuquén de la empresa 2. Caso 6 del grupo `02_pagos` del simulador.
- Numeración del certificado por impuesto **y jurisdicción** (pedido 2026-10-16): script `20261016_retenciones_practicadas_03_numeracion_jurisdiccion.sql`.
- Etapa 2 hecha el 2026-10-16. Scripts `_04_regimenes` y `_05_pagina_regimenes`:
  - catálogo global de regímenes con escala (página 124 / 624, solo superadmin);
  - condición, régimen habitual y certificados de exclusión del proveedor (Historial de situación fiscal);
  - mínimo por pago o acumulado mensual, descontando lo ya retenido en el mes.
  - Prueba en el grupo `17_retenciones_regimenes` del simulador (44 chequeos), que también cubre los casos 6 y 7 del briefing (empresa no agente y dos empresas en paralelo).
  - Supuesto a validar con el contador: el acumulado mensual suma todos los pagos al proveedor en el mes, no solo los de un régimen (un régimen habitual por proveedor e impuesto).
- Etapa 6 (bloqueos y cuadre) hecha el 2026-10-16. Script `20261016_cierres_fiscales_01_estructura.sql`, página 125 / 625 "Cierres Fiscales" (Tesorería), motor `periodos_fiscales_core_model.php`:
  - cierre por empresa + obligación (IVA, IIBB, Ganancias, percepción IIBB efectuada, retenciones practicadas) + jurisdicción + período, con historial auditable (reabrir pide motivo, nada se borra);
  - mientras está cerrado no se confirma ni se anula lo que lo afecta: ventas fiscales (IVA e IIBB de cualquier jurisdicción, por ser base imponible) y sus percepciones IIBB, compras fiscales (IVA) y sus percepciones sufridas (IIBB de la jurisdicción, IVA, Ganancias), cobranzas por sus retenciones sufridas, OP por sus retenciones practicadas. Una cobranza o una OP sin retenciones no se bloquea;
  - la liquidación de IVA cierra su período sola y lo reabre al anularse. La de percepciones IIBB **no** cierra sola, porque puede ser quincenal: el período se cierra a mano al presentar la DDJJ (cada percepción declarada ya está protegida por su `liquidacion_comprobante_id`);
  - cuadre: cada percepción / retención contra lo contabilizado en su cuenta por comprobante, resumen por impuesto y jurisdicción, por cuenta (saldo inicial, comprobantes, liquidaciones y otros, saldo final) y detalle de diferencias exportable (sin cuenta, sin asiento, importe distinto);
  - limitación del cuadre: toma la cuenta que la configuración tiene **hoy**. Si se cambió la cuenta de una config, los comprobantes anteriores al cambio aparecen como diferencia (están en la cuenta vieja);
  - prueba en el grupo `18_cierres_fiscales` del simulador (60 chequeos).
  - Fuera del bloqueo por ahora: las facturas que crea la facturación de abonos del módulo 4 (estudio contable) y las importaciones que confirman directo.
- Etapa 5 (Libro IVA y reportes) hecha el 2026-10-16. Script `20261016_cierres_fiscales_02_registro_inscripciones.sql`; la página 125 / 625 pasa a llamarse **Control Fiscal**:
  - Libro IVA Ventas y Compras en IVA - Posición Mensual (`ivaLibro`): columnas dinámicas de neto e IVA por alícuota y una por percepción / impuesto adicional y jurisdicción, importes firmados, columna de control (total contra líneas y percepciones) y CSV por libro. No gravado y exento salen de las líneas, igual que la posición;
  - registro común de los cuatro circuitos (solapa "Retenciones y percepciones" de Control Fiscal, `cfRegistro`): filtros por rango de períodos, circuito, impuesto, jurisdicción y tercero (nombre o CUIT); resumen por circuito / impuesto / jurisdicción (con lo ya declarado) y por tercero; CSV;
  - inscripciones IIBB de la empresa (`gestion__empresas_iibb_inscripciones`, solapa "Inscripciones IIBB"): provincia, régimen (local / CM / exento), número, sede, coeficiente CM y vigencia. Con inscripciones cargadas, el cierre de Ingresos Brutos solo se permite en jurisdicciones inscriptas en el período. No se sembraron: las carga el usuario;
  - prueba: casos C9 a C11 del grupo `18_cierres_fiscales` (92 chequeos en total).
  - Hallazgo: la columna de control del libro marca unos centavos en algunas Facturas B (redondeo línea contra total). En la base local también se vio una compra vieja con 378.000 de no gravado en la cabecera que no está en sus líneas ni en el total (la posición y el libro usan las líneas, así que no la cuentan).
- Etapa 3 (liquidación de retenciones practicadas) hecha el 2026-10-16 **sin exportación**: el formato del archivo para cada organismo se define más adelante (hoy se presenta a mano desde un reporte; el registro de Control Fiscal da los datos). Script `20261016_cierres_fiscales_03_liquidacion_retenciones.sql`, modelo `retenciones_liquidacion_model.php`, solapa "Liquidación de retenciones" de Control Fiscal:
  - por impuesto (Ganancias, IVA, IIBB por jurisdicción, SUSS) y mes: retenciones confirmadas pendientes, liquidaciones hechas y si el período está cerrado;
  - liquidar genera la I_LIQ (obligación impositiva al organismo elegido, con la cuenta de la config del agente como pasivo y sin asiento: la retención ya está en esa cuenta desde la OP; el pago I_PAG la cancela contra el banco) y marca cada retención con `comp_liquidacion_id`, así la OP ya no se puede anular. Puede haber más de una liquidación por mes;
  - se anula mientras la I_LIQ no tenga pagos (las retenciones vuelven a quedar pendientes); con el período cerrado no se liquida ni se anula;
  - prueba: caso C12 del grupo `18_cierres_fiscales` (110 chequeos en total).
- Etapa 4 (certificados de retenciones sufridas) hecha el 2026-10-16. Script `20261016_retenciones_sufridas_02_certificados.sql`:
  - en la cobranza el número de certificado ya no es obligatorio: sin número la retención queda con el certificado PENDIENTE;
  - nuevos datos en `gestion__cobranzas_medios`: estado (PENDIENTE / RECIBIDO / CONCILIADO), fecha del certificado, régimen (texto), período de cómputo y archivo adjunto (`uploads/retenciones/<empresa>/`, sin acceso directo: se ve por `ver_certificado`);
  - **período de cómputo**: por defecto el mes del recibo (decisión tomada porque la pregunta 9 quedó sin respuesta; se cambia por retención). La Posición de IVA y los cierres usan ese período; el cuadre contable sigue por la fecha del recibo;
  - se completan o corrigen desde el registro de Control Fiscal (lápiz en cada retención sufrida, función "Actualizar certificado"), con filtro de certificados pendientes / recibidos / conciliados y CSV con esos datos. CONCILIADO se marca a mano: la conciliación contra ARCA / provincias queda para cuando se definan los archivos;
  - prueba: caso C13 del grupo `18_cierres_fiscales` y caso 2 del grupo 15 (la retención sin certificado ahora se acepta como pendiente). La subida del archivo no está cubierta por el simulador (no envía archivos).
- Etapa 7 (percepción de IVA practicada) hecha el 2026-10-16. Script `20261016_percepcion_iva_01.sql` (impuesto 22 "Percepción IVA Efectuada" en el catálogo global; **no se crea ninguna config ni alícuota**):
  - el motor de percepciones quedó por impuesto: `perCalcularPracticadas` = IIBB por jurisdicción (`perIibbCalcular`) + IVA (`perIvaCalcular`); guardar, anular, asiento, ARCA (`<Tributos>` con el código de tributo de la config) e impresión cubren los dos;
  - situación del cliente = su condición frente al IVA (responsable inscripto, no categorizado, monotributista, exento, consumidor final identificado); la alícuota de cada una se carga en la solapa Agente de Percepción de la config (situación sin activar = no se percibe; sin alícuota = aviso y no percibe). Base, mínimos, subgrupos de comprobante y cuenta salen de la config, igual que en IIBB;
  - certificado de exclusión del cliente: en el bloque de percepciones del cliente, eligiendo "IVA - AFIP Nación" (se guarda con `impuesto_tipo_id = 22`);
  - la NC asociada revierte lo percibido en la factura;
  - se cierra por período en Control Fiscal y se liquida en la solapa Liquidaciones (misma I_LIQ que las retenciones, marcando `liquidacion_comprobante_id`; la factura declarada ya no se anula);
  - prueba: grupo `19_percepcion_iva` con una config de prueba que solo existe en la copia del simulador.
  - Para usarlo, la empresa que sea agente da de alta la config del impuesto 22 en Impuestos Adic. - Conf con jurisdicción AFIP Nación, cuenta, subgrupos, código de tributo de ARCA y alícuotas, todo a confirmar con el contador.
- Pendiente: la exportación de retenciones y percepciones practicadas (SICORE / SIRE / ARBA / Neuquén) cuando se defina el formato, y la conciliación de certificados de retenciones sufridas contra ARCA / provincias.

---

## 1. Relevamiento: lo que existe hoy

### 1.1 Catálogos y configuración

| Elemento | Dónde | Notas |
|---|---|---|
| Tipos de impuesto | `gestion__impuestos_tipos` (página 72), catálogo **global** | Flags `es_retencion`, `es_percepcion`, `aplica_compra`, `aplica_venta`. Sufridas: 1 Perc. IIBB, 2 Perc. IVA, 3 Perc. Ganancias, 4 Ret. IIBB, 5 Ret. IVA, 21 Ret. Ganancias. Practicadas: 6 Perc. IIBB, 14 Ret. Ganancias, 15 Ret. IVA, 16 Ret. IIBB, 17 Ret. SUSS. **No hay tipo para percepción de IVA practicada.** |
| Jurisdicciones | `gestion__jurisdicciones` (página 74), global | 1 AFIP Nación, las 24 provincias (tipo 2), 11 SIRTAC y 12 SIRCREB (tipo 4). Con `provincia_id`. |
| Régimen IIBB de la empresa | `conf__empresas.iibb_regimen_id` (LOCAL / CONVENIO_MULTILATERAL / EXENTO) + `jurisdiccion_sede_id` | Una sola sede. **No hay inscripciones de la empresa por jurisdicción ni coeficientes de CM.** Dato a revisar: la empresa 2 figura LOCAL con sede Buenos Aires, pero es agente de percepción en Río Negro. |
| Configuración por impuesto de la empresa | `gestion__empresas_impuestos_config` (página 73 / 573), por empresa | Una fila por impuesto × jurisdicción con cuenta contable, vigencia `f_desde/f_hasta`, base, alícuota, mínimos, fuente de alícuota (padrón / manual), N° de agente, datos de DDJJ y tributo ARCA. Se usa para los cuatro circuitos. |
| Alícuota por situación del cliente (percepción) | `gestion__empresas_impuestos_config_situaciones` | Padrón, inscripto en la jurisdicción, simplificado, inscripto en otra, no inscripto, exento, bien de uso. |
| Padrones IIBB | `gestion__padrones_iibb` + `_importaciones` (página 115) | Por empresa × jurisdicción × CUIT con vigencia. Importador por driver (`percepciones_iibb_drivers.php`), hoy solo **SIRCIP** (percepción). Columna `alicuota_retencion` agregada el 2026-10-15, **sin importador que la cargue**. `gestion__entidades_impuestos_padron` quedó sin uso. |
| Condición del tercero | `gestion__entidades_condiciones_fiscales` (IVA, con vigencia); `gestion__entidades_condiciones_iibb` + `_jurisdicciones` (IIBB: régimen, sede, altas de CM) | **No hay condición frente a Ganancias** (inscripto / no inscripto / exento) ni régimen habitual del proveedor. |
| Exclusiones | `gestion__entidades_iibb_exclusiones` | Por empresa × cliente × jurisdicción, % y certificado con vigencia. Solo la usa la **percepción** IIBB. 0 filas. |

### 1.2 Circuito 1: retenciones sufridas (cobranzas)

- Se cargan como **medio de cobro de tipo RETENCION** en `gestion__cobranzas_medios`: impuesto, jurisdicción (obligatoria en IIBB), N° de certificado (obligatorio y único por cliente + impuesto + jurisdicción), base y alícuota (`cobValidarRetenciones`).
- La factura se cancela por el total (efectivo + retenciones). La retención no mueve tesorería.
- Asiento: IVA y Ganancias a la cuenta del medio; IIBB a la cuenta de su jurisdicción en `gestion__empresas_impuestos_config` (`cobCuentaRetencion`).
- La retención de IVA sufrida entra a la **posición mensual de IVA** (página 117).
- Simulador: grupo `15_retenciones_cobranzas` (74 chequeos, todos OK).
- `gestion__cobranzas_retenciones` es una estructura paralela del módulo Estudio Contable (página 415). 0 filas en `gestion`.

### 1.3 Circuito 2: percepciones sufridas (facturas de proveedor)

- Las percepciones van en la cabecera de la factura de proveedor (`gestion__comprobantes_impuestos`, impuestos 1/2/3 con jurisdicción), no en las líneas de detalle. Por eso no entran al costo del producto.
- Suman a `otros_impuestos` y al total a pagar.
- Asiento a la cuenta de la configuración (impuesto × jurisdicción); hay una por provincia (script `20261011_percepciones_recibidas_02_provincias.sql`).
- La percepción de IVA entra a la posición de IVA.
- Simulador: grupo `11_percepciones_compras`.

### 1.4 Circuito 3: agente de retención (órdenes de pago)

- **Implementado el 2026-10-15** (`retenciones_practicadas_core_model.php`, solapa "Agente de Retención" de la página 73, botón "Calcular Retenciones" en `pagos.php`).
  - La empresa es agente si tiene una configuración activa y vigente.
  - Base: neto gravado / neto / IVA / total, proporcional a lo imputado.
  - Alícuota genérica o de padrón.
  - Mínimo de base y de retención **por pago**.
  - En IIBB: jurisdicción obligatoria y exentos excluidos.
  - Certificado único (lo **tipea el usuario**).
  - Pasivo a la cuenta de la configuración.
- La retención se guarda como línea de medio de pago en `gestion__pagos_medios`.
- **Choque con el diseño de tesorería:** la decisión D32 (`20261003_tesoreria_01_ddl.sql`) ya había creado `gestion__pagos_retenciones` y `gestion__retenciones_numeradores`. Esas tablas prevén:
  - régimen;
  - certificado numerado al confirmar, correlativo por empresa e impuesto;
  - `comp_liquidacion_id`, la I_LIQ que la liquida.

  Están vacías y sin código porque en su momento "Casa Lucho no practica retenciones". Lo de hoy no las usa.
- Pago al fisco: **Obligaciones y Rendiciones** (página 109) registra la liquidación (I_LIQ) y su pago (I_PAG, `gestion__pagos_impuestos`).

### 1.5 Circuito 4: agente de percepción (facturas emitidas)

- **Percepción IIBB completa:**
  - motor `perIibbCalcular` (situación del cliente, padrón, territorio, exclusiones, mínimos);
  - al guardar avisa y al confirmar es estricta;
  - **NC con comprobante asociado revierte lo percibido** en el original;
  - va a ARCA en `<Tributos>` con `arca_tributo_id` de la configuración (a confirmar);
  - se imprime con la leyenda;
  - DDJJ SIRCAR Diseño 1 / SIRCIP y liquidación I_LIQ (página 116).
  - Diseño en `_local/docs/gestion/09_percepciones_iibb.md`.
- **Percepción de IVA practicada: no existe** (ni tipo de impuesto ni motor).
- Factura electrónica: web service propio (`arca/arca_lib.php`, WSAA + WSFEv1), solo si `arca_habilitado = 1`.

### 1.6 Libros, reportes y controles

| Elemento | Estado |
|---|---|
| Libro IVA Ventas / Compras | Dentro de la posición mensual de IVA (página 117, `ivaComprobantesPeriodo`). Tiene neto, IVA, no gravado, exento y **una sola columna "otros"**: no separa percepciones por tipo ni por jurisdicción. IVA por alícuota en un resumen aparte. |
| Reportes de retenciones / percepciones sufridas | Solo los totales del período de IVA en la posición. **No hay reporte por impuesto / jurisdicción / tercero / período ni de certificados pendientes.** |
| Reporte de retenciones practicadas | No existe. |
| Reporte de percepciones practicadas | Página 116 (IIBB), por jurisdicción, con DDJJ. |
| Bloqueo por período | Solo IVA: no se puede anular una factura de proveedor de un período con IVA liquidado (`ivaPeriodoLiquidado`). No se bloquean ventas, cobranzas ni OP, ni hay cierre por IIBB. |
| Cuadre cuentas contra comprobantes | No hay control. El simulador verifica asientos balanceados y saldos, no el cuadre por impuesto / jurisdicción. |
| Alertas (certificado faltante, tercero sin condición) | No existen. El certificado sufrido es obligatorio al guardar, así que "sin certificado" hoy no puede pasar. |

---

## 2. Brechas

Riesgo: **A** = puede generar un importe o asiento incorrecto o una DDJJ mal presentada; **M** = falta de control o de reporte; **B** = comodidad.

### Circuito 1: retenciones sufridas

| Requerimiento | ¿Existe? | Dónde | Qué falta | Riesgo |
|---|---|---|---|---|
| Importe − retenciones = neto cobrado; factura cancelada por el total | Sí | cobranzas | — | — |
| Impuesto, jurisdicción, base, alícuota, importe, certificado, cliente | Sí | `gestion__cobranzas_medios` | — | — |
| Régimen | No | — | Columna régimen / código | M |
| Fecha del certificado | No | — | Hoy se toma la fecha del recibo | M |
| Adjuntar el certificado | No | — | Archivo (uploads) o referencia | B |
| Estado recibido / pendiente / conciliado | No | — | Estado del certificado + conciliación con ARCA / jurisdicción (formato a definir) | M |
| Imputación al período fiscal | Parcial | IVA: posición mensual | Ganancias e IIBB sin período de cómputo | M |
| Asiento a crédito por impuesto (IIBB por jurisdicción) | Sí | `cobCuentaRetencion` | — | — |
| Reportes por impuesto / jurisdicción / cliente / período; pendientes | No | — | Reporte nuevo | M |

### Circuito 2: percepciones sufridas

| Requerimiento | ¿Existe? | Dónde | Qué falta | Riesgo |
|---|---|---|---|---|
| Discriminar neto, no gravado / exento, IVA por alícuota, percepciones IVA / IIBB por jurisdicción / Ganancias | Sí | facturas de proveedor + `gestion__comprobantes_impuestos` | — | — |
| Total con percepciones, costo sin ellas | Sí (por diseño) | percepciones en la cabecera | Verificar con el caso de prueba 2 | — |
| Libro IVA Compras con columnas por percepción y jurisdicción | No | posición IVA: columna "otros" | Columnas por tipo y jurisdicción | A |
| Asiento a activo por impuesto / jurisdicción | Sí | — | — | — |
| Reportes por impuesto / jurisdicción / proveedor / período | Parcial | totales de IVA en la posición | Reporte nuevo | M |

### Circuito 3: agente de retención

| Requerimiento | ¿Existe? | Dónde | Qué falta | Riesgo |
|---|---|---|---|---|
| Empresa: impuestos y jurisdicciones como agente, desde qué fecha | Sí | página 73, solapa Agente de Retención | — | — |
| Proveedor: condición frente a cada impuesto | Parcial | IVA e IIBB sí | Condición frente a Ganancias; régimen habitual | A |
| Proveedor: certificados de exclusión con vigencia | No para retención | `gestion__entidades_iibb_exclusiones` es solo IIBB percepción | Generalizar por impuesto y sentido (retención / percepción) | A |
| Proveedor: alícuota por padrón (IIBB) | Parcial | `gestion__padrones_iibb.alicuota_retencion` | Importador del padrón de retención | M |
| Tabla de regímenes con vigencia (concepto, alícuota inscripto / no inscripto, mínimo, por pago o acumulado mensual, base) | No | hoy un solo juego de parámetros por impuesto | Tabla de regímenes (+ escala de Ganancias si corresponde) | A |
| Cálculo con acumulado del mes, mínimos, retenciones previas, excepciones | Parcial | `retPracCalcular` | Acumulado mensual y retenciones ya practicadas en el mes | A |
| Mostrar el cálculo antes de confirmar; ajuste manual con justificación | Parcial | se muestra; el importe se puede editar | Guardar el calculado, el ajustado y el motivo | M |
| OP: facturas − retenciones = neto | Sí | medios de la OP | — | — |
| Certificado correlativo por impuesto, imprimible / PDF | No | hoy lo tipea el usuario | Usar `gestion__retenciones_numeradores` (D32) + impresión | A |
| Pasivo por impuesto / jurisdicción | Sí | cuenta de la configuración | — | — |
| Presentación y pago (régimen de información, ingreso) | Parcial | pago vía I_LIQ / I_PAG | Liquidación de retenciones del período (`comp_liquidacion_id`) y exportación (formatos a confirmar) | A |
| Anulación con trazabilidad | Parcial | la anulación de la OP revierte el asiento | La retención y su certificado deben quedar anulados, no reutilizables | M |
| Auditoría (quién, cuándo, base, alícuota, régimen, origen) | Parcial | en `gestion__pagos_medios` sin régimen ni ajuste | Se resuelve con `gestion__pagos_retenciones` | M |

### Circuito 4: agente de percepción

| Requerimiento | ¿Existe? | Dónde | Qué falta | Riesgo |
|---|---|---|---|---|
| Empresa / cliente / padrón / exclusiones / parámetros con vigencia (IIBB) | Sí | página 73 + 115 + percepciones de clientes | — | — |
| Cálculo automático, discriminado, suma al total | Sí (IIBB) | `perIibbCalcular` | — | — |
| `<Tributos>` en ARCA con el código correcto | Sí (IIBB) | `arca_tributo_id` | Validar el código contra `FEParamGetTiposTributos` | A |
| NC / ND: revertir o ajustar proporcionalmente | Sí (NC con asociado) | — | ND: confirmar el criterio | M |
| Percepción de **IVA** practicada | No | — | Tipo de impuesto + motor (generalizar el de IIBB) | A, solo si alguna empresa es agente |
| Pasivo y reportes | Sí (IIBB) | página 116 | — | — |

### Transversal

| Requerimiento | ¿Existe? | Qué falta | Riesgo |
|---|---|---|---|
| Maestro de 24 jurisdicciones + CM | Sí | — | — |
| Empresa: CM o local y en qué jurisdicciones está inscripta | Parcial | Inscripciones por jurisdicción (y coeficientes CM si hace falta) | M |
| Reportes agrupables por jurisdicción | Parcial | Reporte común de los cuatro circuitos | M |
| Importación de padrones por jurisdicción | Parcial (SIRCIP percepción) | Formato del de retención: **a definir con vos** | M |
| Cuadre libro IVA contra contabilidad; saldos de cuentas contra comprobantes | No | Reporte de control | M |
| Alerta de tercero sin condición impositiva | No | Aviso en el cálculo | M |
| Bloqueo de períodos declarados | Parcial (solo anular factura de proveedor con IVA liquidado) | Bloqueo general por impuesto / jurisdicción / período | A |

---

## 3. Plan de cambios propuesto (espera aprobación)

Cada etapa es un script `_local/sql/mantenimiento/20261016_retenciones_percepciones_NN_<paso>.sql`, reanudable y compatible con MariaDB 10.4 / MySQL 8, más su código replicado en `gestion_normal` y casos en el simulador. Ninguna borra datos: las columnas nuevas son NULL o tienen default, y lo histórico sigue leyéndose como hoy.

**Etapa 1. Retenciones practicadas sobre la estructura D32** (circuito 3; corrige el desvío de hoy)
- Al confirmar la OP se crea una fila de `gestion__pagos_retenciones` por línea RETENCION. Lleva régimen, base, alícuota, importe **calculado y aplicado**, motivo del ajuste, configuración y certificado.
- El certificado se numera con `gestion__retenciones_numeradores`, bajo `FOR UPDATE`. Hay que agregar jurisdicción al numerador si la numeración es por impuesto y jurisdicción (**a confirmar**).
- La línea del medio queda como está, para el cuadre de la OP.
- Al anular la OP, la retención queda anulada con su número (no se reutiliza).
- Impresión del certificado (HTML/PDF, mismo esquema que `templates/impresion/`).
- Reversible: las filas nuevas se pueden borrar sin tocar las OP.

**Etapa 2. Regímenes y condición del proveedor** (circuito 3)
- Tabla de regímenes con vigencia: impuesto, código, concepto, alícuota inscripto / no inscripto, mínimo no sujeto, tipo de mínimo (por pago / acumulado mensual), base y escala (Ganancias, en tabla hija).
- **Los valores los carga el contador**: el script solo crea la estructura.
- Condición del proveedor frente a Ganancias y régimen habitual, con vigencia.
- Exclusiones generalizadas: `gestion__entidades_iibb_exclusiones` + `impuesto_tipo_id` + `sentido` (RETENCION / PERCEPCION). Lo existente queda como IIBB / PERCEPCION.
- Cálculo con el acumulado mensual del proveedor y las retenciones ya practicadas en el mes.
- Alerta si el proveedor no tiene condición definida.

**Etapa 3. Liquidación y exportación de retenciones practicadas** (circuito 3)
- Liquidación del período por impuesto / jurisdicción, que llena `comp_liquidacion_id` y genera la I_LIQ, igual que la página 116.
- Exportación: drivers como los de percepción (por ejemplo SICORE / SIRCAR retenciones). **Formatos a confirmar antes de programar.**

**Etapa 4. Retenciones sufridas: certificado y período** (circuito 1)
- En `gestion__cobranzas_medios`: régimen, fecha del certificado, archivo adjunto, estado (RECIBIDO / PENDIENTE / CONCILIADO) y período de cómputo.
- Reporte por impuesto / jurisdicción / cliente / período y de certificados pendientes.
- Conciliación contra ARCA / jurisdicción: **formato de importación a definir con vos.**

**Etapa 5. Libro IVA y reportes de percepciones sufridas** (circuito 2)
- Libro IVA Ventas y Compras con columnas por tipo de percepción y jurisdicción (dinámicas según lo que haya en el período) y exportación.
- Reporte común de los cuatro circuitos por impuesto / jurisdicción / tercero / período.

**Etapa 6. Controles y bloqueos** (transversal)
- Reporte de cuadre: saldo de cada cuenta de retención / percepción contra la suma de comprobantes, por impuesto y jurisdicción.
- Bloqueo general de altas, ediciones y anulaciones en períodos liquidados (IVA, liquidaciones de IIBB y de retenciones).
- Inscripciones de la empresa por jurisdicción.

**Etapa 7. Percepción de IVA practicada** (circuito 4). Solo si alguna empresa es agente: se generaliza el motor de IIBB por impuesto.

**Etapa 8. Casos de prueba** de la sección 6 en el simulador, con los valores del contador. Los casos 6 y 7 (empresa no agente y dos empresas en paralelo) se pueden hacer ya con las empresas 2 y 6.

Orden sugerido por riesgo: 1 → 2 → 6 (bloqueos) → 5 → 3 → 4 → 7.

---

## 4. Supuestos y puntos a validar con el contador

Nada de esto está cargado ni hardcodeado. Hoy los valores son campos en blanco o que el usuario carga.

1. Regímenes de retención de Ganancias realmente usados (conceptos, alícuotas inscripto / no inscripto, mínimos, escala) y si el mínimo es por pago o acumulado mensual.
2. Regímenes de retención de IVA usados (si la empresa es agente) y su base (¿IVA facturado?).
3. Jurisdicciones de IIBB donde la empresa es agente de retención y de percepción, N° de agente, alícuotas genéricas y por padrón, mínimos.
4. Numeración de certificados: ¿por impuesto, o por impuesto y jurisdicción? ¿Formato del número?
5. Formatos de presentación: SICORE, SIRCAR retenciones / otros por provincia, y versión vigente de cada uno.
6. Códigos de tributo de ARCA para percepción IIBB por jurisdicción (validar con `FEParamGetTiposTributos`).
7. Régimen IIBB de cada empresa (Casa Lucho figura LOCAL con sede Buenos Aires pero percibe en Río Negro) y, si es CM, en qué jurisdicciones tiene alta.
8. Criterio de redondeo. Hoy se redondea cada retención / percepción a 2 decimales sobre la base ya redondeada, por línea. ¿Se acepta como criterio único?
9. Período de cómputo de una retención sufrida: ¿fecha del certificado o del recibo?

## 5. Preguntas para Pablo

1. ¿Qué empresas son hoy agentes de retención y / o percepción, en qué impuestos y jurisdicciones?
2. ¿Qué regímenes se usan realmente hoy? (Si no lo sabés, lo dejamos para el contador: el plan no lo necesita hasta cargar valores.)
3. Catálogo de regímenes: ¿global (uno para todo el sistema, lo administra el superadmin, con override por empresa) o por empresa? Recomiendo global: los regímenes son normativos y nacionales.
4. ¿Aprobás pasar las retenciones practicadas a `gestion__pagos_retenciones` (D32), dejando la línea del medio solo para el cuadre de la OP?
5. Formato de importación del padrón de retenciones y de la conciliación de certificados: ¿qué archivos bajan hoy (ARBA, "Mis Retenciones" de ARCA, otros)?
