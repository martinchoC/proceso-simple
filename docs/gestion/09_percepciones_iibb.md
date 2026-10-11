# Percepciones de Ingresos Brutos practicadas (agente de percepción)

Estado: **implementado** (v2 aprobada) — 2026-10-10. Ver §11 para lo que quedó distinto o pendiente. Alcance: módulos `gestion` y `gestion_normal` (hoy idénticos en ventas e impuestos).

## 1. Objetivo

Una empresa puede ser agente de percepción de IIBB en una o varias provincias (hoy Casa Lucho: Río Negro). Al emitir factura / ND / NC de venta, el sistema calcula la percepción de cada provincia en la que la empresa es agente, la suma al total, la informa a ARCA, la imprime y la contabiliza. Después permite importar el padrón, exportar la DDJJ y liquidarla (I_LIQ → I_PAG).

Nada de lo que es propio de una provincia va en el código: alícuotas, situaciones, leyendas, códigos de régimen y vigencias son configuración. Los formatos de archivo (padrón y DDJJ) son "drivers" con código (`SIRCIP`, `SIRCAR_D1`, ...), que se reutilizan entre provincias porque los formatos son de la Comisión Arbitral, no de cada provincia.

## 2. Normativa de Río Negro relevada (2026-10-10)

Hay **dos regímenes**. Uno reemplaza al otro en una fecha que todavía no está definida.

### 2.1 Régimen vigente hoy: Res. ART 985/2018, Anexo VII, + DDJJ en SIRCAR

- **Ámbito:** la operación es en Río Negro cuando los bienes "se recepcionen o sitúen en sedes, depósitos, establecimientos, locales… dentro de la Provincia". O sea, el **lugar de entrega**. No corresponde percibir si la recepción es en un transporte para envío exclusivo fuera de la provincia.
- **Sin padrón:** la alícuota sale de la condición del cliente frente a IIBB de Río Negro:

  | Situación del cliente | Alícuota |
  |---|---|
  | IIBB Directo (local Río Negro) | 1 % |
  | Convenio Multilateral con alta en Río Negro | 1 % |
  | Régimen Simplificado Río Negro | 0,05 % |
  | No inscripto en Río Negro | 0 %, **pero se informa** en la DDJJ ("régimen especial de percepción a tasa 0 %") |

- **Excluidos de percepción** (art. 8): local inscripto solo en otra jurisdicción, CM sin Río Negro, exentos. **Bienes de uso** (art. 9): sin percepción, si el adquirente declara ese destino y se consigna en la factura.
- **Base:** precio neto, sin IVA y sin devoluciones, bonificaciones ni descuentos.
- **No hay montos mínimos** en el Anexo VII.
- **DDJJ** mensual en **SIRCAR** (Comisión Arbitral), jurisdicción 916, **Diseño N° 1 de percepciones**. Es un TXT (zip opcional) separado por comas, con un renglón por operación:

  | # | Campo | Formato |
  |---|---|---|
  | 1 | Nº de renglón (único en el archivo) | Num(5) |
  | 2 | Tipo de comprobante: 1 Factura, 2 ND, 3 Recibo, 4 Nota de venta contado, 5 Factura exportación, 6 ND exterior, 7 Liquidación, 20 Otros débito, 102 NC, 106 NC exterior, 120 Otros crédito | Num(3) |
  | 3 | Letra (A, B, C, E, M; Z si no hay) | Char(1) |
  | 4 | Nº de comprobante con punto de venta | Num(12), p. ej. `000023431222` |
  | 5 | CUIT del cliente | Num(11) |
  | 6 | Fecha de percepción | dd/mm/aaaa |
  | 7 | Monto sujeto a percepción | 999999999.99 |
  | 8 | Alícuota | 999.99 |
  | 9 | Monto percibido (7 × 8 / 100) | 999999999.99 |
  | 10 | Tipo de régimen de percepción (tabla de Río Negro) | Num(3) |
  | 11 | Jurisdicción (916) | Num(3) |

  ⚠️ **El código de régimen (campo 10) de Río Negro no es público.** La tabla está en sircar.gov.ar → "Ayuda", y solo la ven los agentes con clave fiscal. Va como dato de configuración (`codigo_regimen_ddjj`).

### 2.2 Régimen futuro: Res. ART 407/2025 + SIRCIP (Comisión Arbitral, RG CA 9/2025)

- Río Negro **adhirió a SIRCIP**, el sistema unificado de percepciones de la Comisión Arbitral. Se aplica desde que SIRCIP esté operativo para Río Negro; hasta entonces sigue el Anexo VII de la 985/2018 (art. 13).
- La vigencia de SIRCIP se postergó **al 1/12/2026** (Disposición CA 7/2026). Las jurisdicciones que arrancan se fijan por disposición de Presidencia. La Disposición 3/2026 incluía a Río Negro en el primer grupo; la 7/2026 lo deja a una disposición futura que no encontré. **Hay que confirmarlo antes de diciembre.**
- **Alícuotas:**

  | Situación | Alícuota |
  |---|---|
  | Cliente en el padrón | Alícuota del padrón (puede ser 0; exentos, excluidos y con certificado de no percepción vienen en 0) |
  | No está en padrón y la operación es en un establecimiento domiciliado en Río Negro (excepto consumidor final) | **2 %** |
  | Inscripto en otra jurisdicción o CM **sin alta en Río Negro**, operación en un establecimiento de Río Negro | **1 %**, discriminada aparte con la leyenda **"Percepción por falta de alta en Río Negro"** |
  | Bienes de uso o insumo para fabricarlos, declarado y consignado en la factura | Sin percepción, pero se declara con alícuota 0 |

- **Padrón:** se aplica a los clientes que figuran "independientemente del lugar de entrega" (art. 2).
- **Base:** total sin IVA discriminado, bonificaciones, devoluciones, descuentos, impuestos internos ni otras percepciones.
- **Notas de crédito:** se puede anular la percepción **dentro del mes calendario**, compensándola solo contra percepciones a pagar.
- **No hay montos mínimos.**
- **Padrón SIRCIP:** mensual, se descarga del Portal Federal Tributario días antes de cada mes. Es un ZIP con un TXT separado por comas:

  | # | Campo | Formato |
  |---|---|---|
  | 1 | Período | aaaamm |
  | 2 | CUIT | Num(11) |
  | 3 | Razón social | Alfa(70) |
  | 4 | Jurisdicción sede | Num(3) |
  | 5 | **CRC** del contribuyente para el período (10–99; **se informa en la DDJJ**: SIRCIP valida que se usó el padrón correcto) | Num(2) |
  | 6 | Letra de alícuota: A 0,00 · B 0,01 · C 0,05 · D 0,10 · E 0,20 · F 0,30 · G 0,40 · H 0,50 · I 0,60 · J 0,70 · K 0,80 · L 1,00 · M 1,20 · N 1,40 · O 1,50 · P 1,60 · Q 1,80 · R 2,00 · S 2,50 · T 3,00 · U 3,50 · V 4,00 · W 4,50 · X 5,00 % | Alfa(1) |
  | 7 | Situación por jurisdicción: 25 dígitos que se leen de derecha a izquierda, descartando el primero, así las posiciones 1–24 son las jurisdicciones; cada dígito es un código 1–5 | Num(25) |

  Ejemplo: `202603,30100100106,Empresa de prueba,904,34,B,5225355222512555552512420`

- **DDJJ SIRCIP:** un ZIP con un único TXT separado por comas:

  | # | Campo | Formato |
  |---|---|---|
  | 1 | CUIT | Num(11) |
  | 2 | CRC del padrón del período | Num(2) |
  | 3 | Fecha de percepción | dd/mm/aaaa |
  | 4 | Tipo de régimen (por ahora solo 1 = General) | Num(3) |
  | 5 | Tipo de registro: 1 Percepción, 2 Informativo, 3 Excluido, 4 No inscripto, 5 Sobretasa | Num(2) |
  | 6 | Código de operación exceptuada (por ahora 0) | Num(2) |
  | 7 | Jurisdicción donde se realizó la operación (entrega o prestación) | Num(3) |
  | 8 | Tipo de comprobante (misma tabla que SIRCAR) | Num(3) |
  | 9 | Letra | Char(1) |
  | 10 | Punto de venta | Num(5) |
  | 11 | Número | Num(8) |
  | 12 | Monto sujeto | Num(13,2), con punto |
  | 13 | Alícuota | Num(3,2) |
  | 14 | Monto percibido o devuelto | Num(10,2) |
  | 15 | Comprobante original (solo anulaciones/devoluciones): tipo + letra + PV + número | Alfa(17) |
  | 16 | CRC del padrón de devoluciones (no se usa por ahora) | Num(2) |
  | 17 | ABM | A / M / B |

  Ejemplo: `30100100106,34,03/03/2026,1,1,0,904,1,A,2,3431222,12342.03,2.00,246.84,,,A`

  El campo 7 (situación por jurisdicción) tiene la tabla de códigos 1–5 en una planilla pública de la Comisión Arbitral, y hay un **padrón simulado para pruebas**. Hay que bajar las dos cosas antes de escribir el parser.

### 2.3 Lo que no se encontró o hay que confirmar con el contador o la ART

1. Código de régimen SIRCAR (campo 10) de Río Negro para percepciones: tabla de sircar.gov.ar → Ayuda (con la clave fiscal del agente).
2. Si las modificatorias de la 985/2018 posteriores a 2019 cambiaron las alícuotas del Anexo VII. Lo relevado es el texto original de 2018. Por eso todo es configuración con vigencia.
3. Fecha en que Río Negro entra en SIRCIP (disposición de Presidencia de la Comisión Arbitral).
4. Interpretación de la 407/2025 en "operación efectuada en un establecimiento domiciliado en Río Negro" para los clientes sin padrón: si es el establecimiento **del vendedor** o el **lugar de entrega**. El art. 2 dice "independientemente del lugar de entrega". Si Casa Lucho emite desde Carmen de Patagones (Buenos Aires), con la primera lectura no aplicaría el 2 % / 1 % a quien no figura en el padrón. Queda como opción de configuración (`criterio_territorial`), pero la decisión es del contador.
5. Número de agente de Casa Lucho en Río Negro (formato `107xxxxxx` según los anexos de la 985/2018).

## 3. Qué se reutiliza (ya existe)

| Pieza | Uso |
|---|---|
| `gestion__impuestos_tipos` id 6 "Percepción IIBB Efectuadas" | Tipo de todas las percepciones IIBB practicadas (una config por provincia, no un tipo por provincia). |
| `gestion__empresas_impuestos_config` (página 73) | Una fila **por empresa × provincia × régimen vigente**. Para Río Negro: una fila 985/2018 abierta hoy, y al entrar SIRCIP se cierra (`f_hasta`) y se abre la fila 407/2025. |
| `_config_subgrupos` | En qué comprobantes se percibe (FA / ND / NC de venta). |
| `_config_operaciones` | Excepciones por tipo de producto (p. ej. bienes de uso fijos). |
| `gestion__comprobantes_impuestos` | Percepción de cada comprobante. |
| `gestion__entidades_condiciones_iibb` | Condición IIBB del cliente. |
| `obligaciones_rendiciones` (109) | I_LIQ / I_PAG. |

## 4. Cambios de modelo de datos

### 4.1 `gestion__empresas_impuestos_config` — columnas nuevas (nullable, sirven para cualquier agente)

| Columna | Uso |
|---|---|
| `numero_agente` | varchar(30): Nº de agente en la provincia (DDJJ e impresión). |
| `criterio_territorial` | varchar(20): `ENTREGA` (localidad de la **sucursal de la entidad**) o `ESTABLECIMIENTO` (sucursal de la empresa que emite). |
| `importe_minimo_percepcion` | decimal(18,2) default 0. Hoy 0 para Río Negro; existe para otras provincias. |
| `arca_tributo_id` | Código de `FEParamGetTiposTributos`. |
| `formato_padron` | varchar(20): `SIRCIP`, o NULL si el régimen no tiene padrón. |
| `formato_ddjj` | varchar(20): `SIRCAR_D1`, `SIRCIP`. |
| `codigo_jurisdiccion_cm` | char(3): 916. Va en los dos formatos de DDJJ. |
| `codigo_regimen_ddjj` | varchar(3): campo 10 de SIRCAR / campo 4 de SIRCIP. |

Lo que ya existía se usa así: `base_calculo` (`NETO_GRAVADO` es la base de las dos normas), `minimo_imponible`, `cont_cuenta_id` (**obligatoria**, sin cuenta no confirma), `f_desde`/`f_hasta` (cambio de régimen).

### 4.2 `gestion__empresas_impuestos_config_situaciones` (nueva)

Alícuota según la situación del cliente. Reemplaza las reglas fijas de cada norma:

```
config_situacion_id   int PK
empresa_impuesto_config_id int NOT NULL
situacion             varchar(30) NOT NULL   -- ver tabla
requiere_territorio   tinyint(1)  NOT NULL   -- 1 = solo si la operación es en la provincia
alicuota              decimal(10,4) NOT NULL
tipo_registro_ddjj    tinyint NULL           -- SIRCIP campo 5 (1..5)
declarar_si_cero      tinyint(1) NOT NULL    -- 0 % que igual va a la DDJJ
leyenda               varchar(120) NULL      -- texto en la factura
tabla_estado_registro_id smallint NOT NULL
UNIQUE (empresa_impuesto_config_id, situacion)
```

| `situacion` | 985/2018 (hoy) | 407/2025 (SIRCIP) |
|---|---|---|
| `PADRON` | — | alícuota del padrón, territorio 0, registro 1 |
| `INSCRIPTO_JURISDICCION` (directo o CM con alta) | 1 %, territorio 1 | (vienen en padrón) |
| `SIMPLIFICADO_JURISDICCION` | 0,05 %, territorio 1 | (padrón) |
| `INSCRIPTO_OTRA_JURISDICCION` (sin alta en la provincia) | excluido (no percibe ni declara) | 1 %, territorio 1, registro 4, leyenda "Percepción por falta de alta en Río Negro" |
| `NO_INSCRIPTO` | 0 %, territorio 1, `declarar_si_cero` | 2 %, territorio 1, registro 1 |
| `EXENTO` | excluido | (padrón 0) |
| `BIEN_DE_USO` | excluido | 0 %, `declarar_si_cero` |

### 4.3 Situación del cliente frente a IIBB por jurisdicción

`gestion__entidades_condiciones_iibb` tiene el régimen y una sola sede. No alcanza para "CM con alta en Río Negro" ni para distinguir el simplificado.
- Nueva **`gestion__entidades_condiciones_iibb_jurisdicciones`** (`entidad_condicion_iibb_id`, `jurisdiccion_sede_id`, `f_desde`, `f_hasta`): jurisdicciones de alta de un CM. Para un local, la sede.
- Nuevo régimen `SIMPLIFICADO` en `gestion__regimenes_iibb` (hoy "Local / Simplificado" están juntos).
- Se carga en la pestaña fiscal de clientes (`clientes*` y `entidades*`).

### 4.4 Padrón

- **`gestion__padrones_iibb`**: `empresa_id`, `jurisdiccion_id`, `padron_importacion_id`, `periodo` (aaaamm), `cuit` char(11), `alicuota_percepcion` decimal(10,4), `letra_alicuota` char(1), `crc` tinyint, `situacion_jurisdiccion` tinyint (dígito del campo 7 de la provincia), `jurisdiccion_sede_cm` char(3). UNIQUE `(empresa_id, jurisdiccion_id, periodo, cuit)`. Se busca por CUIT, así un cliente dado de alta después de importar igual se encuentra.
- **`gestion__padrones_iibb_importaciones`**: log de cada archivo (empresa, jurisdicción, período, archivo, hash, filas leídas/cargadas/rechazadas, usuario, fecha). Reimportar un período lo reemplaza.

### 4.5 Exclusiones y bienes de uso

- **`gestion__entidades_iibb_exclusiones`**: certificado de no percepción (`entidad_id`, `jurisdiccion_id`, `porcentaje`, `numero_certificado`, vigencia). Hace falta en el régimen 985. En SIRCIP los certificados ya vienen en el padrón con alícuota 0.
- **Bien de uso**: un campo nuevo en `gestion__ventas_facturas`, `destino_bien_uso` tinyint, más un checkbox en la factura ("El cliente declara destino bien de uso / insumo"). Se imprime la leyenda en el comprobante, como exigen las dos normas.

### 4.6 `gestion__comprobantes_impuestos` — columnas nuevas

`jurisdiccion_id`, `impuesto_tipo_id`, `situacion`, `tipo_registro_ddjj`, `padron_crc`, `padron_iibb_id`, `liquidacion_comprobante_id`. Índice `(impuesto_tipo_id, jurisdiccion_id, liquidacion_comprobante_id)`. Se guardan las percepciones de 0 % que hay que declarar (`declarar_si_cero`).

### 4.7 Corrección de datos

`gestion__jurisdicciones.provincia_id` está mal salvo en ARBA y AGIP (Río Negro tiene 7, en `conf__provincias` es 24). Se corrige por nombre.

### 4.8 Configuración

`conf__tablas` 432–434 (padrones, exclusiones, situaciones). Páginas **115 Padrones IIBB** y **116 Percepciones IIBB practicadas**. Las situaciones se editan dentro de la página 73, como hoy los subgrupos y las operaciones. Scripts: `sql/mantenimiento/20261010_percepciones_iibb_01_estructura.sql`, `_02_paginas.sql` y `_03_config_rio_negro.sql` (este último solo con datos para la empresa 2: config 985/2018 abierta + situaciones; la fila SIRCIP se carga cuando se confirme la fecha).

## 5. Cálculo (servicio único `gestion/percepciones_iibb_model.php`)

`perIibbCalcular($conexion, $empresa_id, $cabecera, $detalles)`. Por cada config con `impuesto_tipo_id = 6`, vigente a `f_emision` y cuyo subgrupo incluya el del comprobante:

1. Consumidor final, o sin CUIT → no percibe.
2. **Territorio**: con `ENTREGA`, la provincia de la **localidad de la sucursal de la entidad** (`entidad_sucursal_id` → `gestion__entidades_sucursales.localidad_id` → `conf__localidades.provincia_id`). Si la factura no tiene sucursal o la sucursal no tiene localidad, **no se puede confirmar** mientras la empresa sea agente: sale un error explícito. Hoy las 481 sucursales tienen localidad. Con `ESTABLECIMIENTO`, la provincia de la sucursal de la empresa que emite.
3. **Situación**, en este orden:
   1. `destino_bien_uso` → `BIEN_DE_USO`.
   2. Si `formato_padron` no es NULL y el CUIT está en el padrón del período de `f_emision` → `PADRON`.
   3. Si no, según la condición IIBB vigente del cliente: `EXENTO`, `INSCRIPTO_JURISDICCION` (sede o jurisdicción de alta = la provincia), `SIMPLIFICADO_JURISDICCION`, `INSCRIPTO_OTRA_JURISDICCION`, o `NO_INSCRIPTO` si no tiene condición IIBB.
   4. Si la situación no está configurada → excluido. Si `requiere_territorio = 1` y la operación no es en la provincia → no percibe.
4. **Certificado de exclusión** vigente: alícuota × (1 − porcentaje/100).
5. **Base**: neto gravado de las líneas, menos descuento general. Sin IVA, internos ni otras percepciones.
6. Mínimos (`minimo_imponible`, `importe_minimo_percepcion`), y después redondeo a 2 decimales.
7. Resultado: una fila por provincia, con la situación, la alícuota, el CRC, el tipo de registro y la leyenda. Incluye las de 0 % con `declarar_si_cero`.

**Notas de crédito:** con comprobante asociado se revierte con la alícuota, situación y CRC de la factura original, sobre la base de la NC, sin superar lo percibido. En SIRCIP solo se puede anular dentro del mes calendario de la NC: si la factura original es de un mes anterior, la percepción de la NC se marca como **no compensable** y se avisa, porque el contador decide si va por devolución. Sin asociado, se calcula como factura. Las ND se calculan como factura.

## 6. Circuito en ventas_facturas

| Momento | Qué pasa |
|---|---|
| Borrador (alta / edición) | Calcula, muestra en el pie y graba `gestion__comprobantes_impuestos` + `importe_otros_impuestos`. El total la incluye. |
| Confirmar | Recalcula con el padrón del período de `f_emision`. Si cambia, graba lo nuevo y avisa. |
| Aprobada ARCA | `<Tributos>` con una línea por provincia con importe > 0 e `ImpTrib` = suma. Validación: neto + IVA + exento + no gravado + tributos = total. |
| Asiento | Deudores por el total. Haber en la `cont_cuenta_id` de cada provincia. En las NC, el asiento invertido. |
| Volver a Borrador / Eliminar / Anular | Las filas siguen el estado del comprobante. Si ya fueron declaradas (`liquidacion_comprobante_id`), se bloquea. |
| Impresión (A4 y ticket) | Línea por percepción con su leyenda ("Percepción IIBB Río Negro 1,00 %" / "Percepción por falta de alta en Río Negro"), "Agente de percepción IIBB Río Negro Nº …" y, si corresponde, "Destino declarado: bien de uso". |

Pedidos y remitos no llevan percepción.

## 7. Padrones (página 115)

- Se sube el ZIP o el TXT, se elige la provincia (define el `formato_padron`, hoy solo `SIRCIP`) y el período sale del archivo.
- Lectura en streaming, inserts en lotes de 1.000 y transacción por importación. Solo se guardan las filas de la jurisdicción de la config: la alícuota de la letra, el CRC y el dígito del campo 7 de esa provincia.
- Muestra un resumen con filas leídas, cargadas y rechazadas. Permite consultar un CUIT, y hay un listado de clientes de la empresa con su alícuota del período.
- Mientras Río Negro siga con la 985/2018, la página queda sin uso para Casa Lucho (ese régimen no tiene padrón).

## 8. DDJJ y liquidación (página 116)

- Filtros: provincia y período. Listado de las percepciones de comprobantes confirmados, con las NC en negativo y las de 0 % declarables. Totales por situación.
- **Exportar** según `formato_ddjj` de la config vigente en el período:
  - `SIRCAR_D1`: TXT con renglón correlativo, Nº de comprobante de 12 dígitos (PV + número), régimen = `codigo_regimen_ddjj` y jurisdicción = `codigo_jurisdiccion_cm`.
  - `SIRCIP`: ZIP con un TXT, CRC del padrón, tipo de registro de la situación, comprobante original en las NC y ABM `A`.
  - Tipo de comprobante: 1 FA, 2 ND, 102 NC (por el grupo/signo del tipo). Letra del tipo.
- **Liquidar**: I_LIQ con `registrarObligacionImpositiva` (impuesto 6, período, importe neto, cuenta de la config) y marca de `liquidacion_comprobante_id`. El pago se hace en la página 109. Una percepción tardía queda para el período siguiente.

## 9. Pendientes

Bloquean solo la parte indicada:

| # | Pendiente | Bloquea |
|---|---|---|
| 1 | Código de régimen SIRCAR de Río Negro (campo 10) | Exportación `SIRCAR_D1` (se deja como dato de config) |
| 2 | Alícuotas vigentes del Anexo VII (¿hubo modificatorias desde 2018?) | Carga de la config `_03` (no el código) |
| 3 | Fecha de SIRCIP para Río Negro | Alta de la config 407/2025 |
| 4 | Lectura de "establecimiento domiciliado en Río Negro" (art. 4/5 de la 407) | Valor de `criterio_territorial` en la config SIRCIP |
| 5 | Planilla de códigos del campo 7 y padrón simulado de SIRCIP | Pruebas del parser SIRCIP |
| 6 | Nº de agente de Casa Lucho | Impresión / DDJJ (dato de config) |
| 7 | `arca_tributo_id` (verificar con `FEParamGetTiposTributos`) | Envío a ARCA con percepción |
| 8 | ARCA no autoriza todavía NC/ND (faltan `CbtesAsoc`) | Que la reversión en NC llegue a ARCA |

## 10. Observaciones fuera de alcance

- `obligaciones_rendiciones_model.php` usa el id fijo `COMP_TIPO_I_LIQ = 46` (empresa 2; en la 6 es 128) y concatena `$empresa_id` en el SQL. Hay que corregirlo antes de usarlo desde la página 116.
- `gestion__entidades_impuestos_padron` (vacía, sin uso) queda deprecada.

## Fuentes

- Res. ART Río Negro 985/2018 (texto original con anexos): https://agencia.rionegro.gov.ar/download/archivos/00010026.pdf
- Res. ART Río Negro 407/2025 (adhesión a SIRCIP): https://www.aacs.org.ar/doc/circulares/administrativas/23547adjunto.pdf
- Diseños de registros SIRCIP (padrón y DDJJ): https://www.ca.gob.ar/descargas/sircip/registros/Diseno_de_Registros_del_Sistema_SIRCIP.pdf
- Preguntas frecuentes SIRCIP: https://www.ca.gob.ar/index.php/preguntas-frecuentes/sistemas/sircip
- SIRCAR, diseños de registro (Río Negro = Diseño N° 1): https://www.ca.gob.ar/descargas/sircar/instructivos_tablas/Anexo_Registros.pdf
- Postergación SIRCIP al 1/12/2026: https://siap.blogdelcontador.com.ar/novedades/ingresos-brutos-postergan-hasta-diciembre-la-entrada-en-vigencia-del-sircip/
- Disposición CA 3/2026: https://www.ca.gob.ar/descargas/sircip/disposiciones/Disposicion_n_3.2026_sircip_aplaza_vigencia.pdf

## 11. Implementación (2026-10-10)

**Scripts** (correr en orden, reanudables, ambos motores): `sql/mantenimiento/20261010_percepciones_iibb_01_estructura.sql`, `_02_paginas.sql` (páginas 115/116 y 615/616, funciones 3367–3370 / 8367–8370: 3362–3366 y 8362–8366 ya estaban ocupados), `_03_config_rio_negro.sql` (empresa 2, **inactiva**; crea la cuenta 2.1.04.06).

**Código** (idéntico en `gestion` y `gestion_normal`, salvo ids de módulo/página):

| Archivo | Qué hace |
|---|---|
| `percepciones_iibb_core_model.php` | Cálculo (`perIibbCalcular`), grabado y anulación de percepciones del comprobante |
| `ventas_facturas_model.php` / `_ajax.php` / `.js` / `.php` | Recalcula al guardar (sin frenar: aviso) y al confirmar/aprobar (estricto); asiento; `<Tributos>` a ARCA; vista previa `calcular_percepciones`; tilde "bien de uso" |
| `arca/arca_lib.php` | `<Tributos>` + `ImpTrib`; `arca_wsfe_tipos_tributos` (botón "Tipos de tributo" en Integraciones ARCA) |
| `ventas_facturas_print.php`, `templates/impresion/factura_a4.php` / `_ticket80.php` | Línea por percepción con su leyenda, "Agente de Percepción … N°", destino bien de uso; la transparencia fiscal ya no suma la percepción provincial a "otros impuestos nacionales" |
| `empresas_impuestos_config_agente_model.php` / `.js` | Solapa "Agente de Percepción" de la página 73 |
| `padrones_iibb*` (115) | Importación SIRCIP (ZIP/TXT, streaming, lotes de 1.000, reemplaza el período) y consulta |
| `percepciones_iibb*` (116) | Listado, exportación SIRCAR_D1 / SIRCIP, liquidación I_LIQ |
| `percepciones_iibb_clientes_*` | En "Historial de Situación Fiscal" de clientes / entidades: jurisdicciones de alta (CM) y certificados |
| `obligaciones_rendiciones_model.php` | Tipos I_LIQ / I_PAG / RG_* por código y empresa (antes ids fijos 46–50 de la empresa 2, la empresa 6 no podía liquidar); `cuenta_debe_id` y `en_transaccion` opcionales |

**Decisiones tomadas al implementar**
- Comprobantes no fiscales (X) no llevan percepción aunque compartan subgrupo con A/B.
- Campo 7 de SIRCIP (planilla oficial de la Comisión Arbitral): 1 = adherida con alta → alícuota del padrón; 2 = adherida sin alta → situación `INSCRIPTO_OTRA_JURISDICCION` (1 % "falta de alta" en Río Negro, con el CRC del padrón); 3 = sin sobre alícuota → no se percibe; 4/5 = jurisdicción no adherida → se ignora el padrón. Solo se importan los CUIT con dígito 1–3. Verificado con el padrón simulado oficial.
- La liquidación pide organismo (entidad proveedora) y **sucursal** (la I_LIQ la exige por FK). Asiento de la I_LIQ: Debe y Haber la cuenta a depositar (no crea gasto; el I_PAG cancela el pasivo).
- Una percepción ya declarada bloquea editar, volver a calcular o anular su comprobante.
- En SIRCIP las filas sin padrón van con CRC vacío (el diseño no dice qué poner cuando el cliente no está en el padrón): **confirmar con la Comisión Arbitral** antes de la primera presentación.

**No implementado / pendiente**
- Excepciones por tipo de producto de `_config_operaciones` para percepciones (la tabla sigue usándose solo en compras).
- Facturación de abonos de `estudio_contable` (crea facturas confirmadas sin pasar por ventas_facturas): no calcula percepciones.
- Pendientes de datos de §9 (código de régimen SIRCAR, N° de agente, tributo ARCA, fecha SIRCIP, lectura de la 407).
- Fuentes de alícuota distintas de SIRCIP (padrón propio, servicio web, carga manual): diseño en §12.

**Pruebas**: `tests/simulacion/casos/09_percepciones_iibb.py` (46 chequeos) + suite completa 264/264 con ARCA deshabilitado en la copia.

## 12. Etapa 2: fuentes de alícuota genéricas (diseño, 2026-10-11 — no implementado)

### 12.1 Problema

Cada provincia informa la alícuota de percepción de un CUIT por una de cuatro vías: **padrón del Convenio** (SIRCIP), **padrón propio** de la provincia (archivo con su diseño), **servicio web / API** (consulta por CUIT) o **consulta web individual** (la persona mira el sitio de la provincia). Hoy solo existe la primera, y quedó atada a SIRCIP en cuatro lugares:

| Lugar | Qué está atado |
|---|---|
| `padImportar` (`padrones_iibb_model.php`) | Rechaza todo formato que no sea `SIRCIP` y graba el literal `'SIRCIP'` en la importación. |
| `perIibbCalcular` (`percepciones_iibb_core_model.php`) | Interpreta el dígito del campo 7 de SIRCIP (`situacion_jurisdiccion` 1/2/3/4-5). |
| `gestion__padrones_iibb` | Clave por `periodo` (aaaamm): los padrones con vigencia desde/hasta, las consultas puntuales y las cargas manuales no entran. |
| `perExportar` (`percepciones_iibb_model.php`) | `if/elseif` por `SIRCAR_D1` / `SIRCIP`. |

### 12.2 Idea

Las cuatro vías contestan la misma pregunta: **CUIT + jurisdicción + fecha → alícuota y situación**. Cada vía escribe una respuesta **normalizada** en un único depósito (`gestion__padrones_iibb`, con vigencia y fuente), y el motor lee solo de ahí. Lo propio de cada fuente (leer el archivo, traducir sus códigos, llamar al servicio) vive en un **driver**. El motor y las situaciones (§4.2) no cambian.

```
 Archivo SIRCIP ─┐
 Archivo propio ─┼─ driver ─► fila normalizada ─► gestion__padrones_iibb ─► perIibbCalcular ─► situaciones (§4.2)
 Servicio web ───┤             (cuit, vigencia,     (fuente, f_desde/f_hasta)
 Carga manual ───┘              alícuota, situación)
```

### 12.3 Fila normalizada

Todo driver entrega filas con esta forma:

| Campo | Contenido |
|---|---|
| `cuit` | 11 dígitos |
| `razon_social` | Opcional |
| `f_desde` / `f_hasta` | Vigencia. Un padrón mensual se carga del día 1 al último día del período. |
| `alicuota` | % a aplicar si la situación es `PADRON` |
| `situacion` | `PADRON` (alícuota del padrón), `INSCRIPTO_OTRA_JURISDICCION` (falta de alta: alícuota de la situación de la config), `NO_PERCIBIR` (figura en el padrón, sin percepción) |
| `codigo_origen` | Código crudo de la fuente (letra SIRCIP, grupo de un padrón propio), para la DDJJ y la auditoría |
| `crc`, `jurisdiccion_sede_cm` | Datos para la DDJJ, si la fuente los trae |

Las filas que la fuente marca como "no aplica" (p. ej. dígitos 4/5 de SIRCIP) **no se guardan**: el motor sigue por la condición del cliente, como hoy.

### 12.4 Cambios de modelo de datos

El DDL se muestra plano; el script real (`sql/mantenimiento/AAAAMMDD_percepciones_iibb_04_fuentes.sql`) lo envuelve en el procedimiento `_col` de `_01_estructura.sql` para que sea reanudable.

**`gestion__padrones_iibb`.** Lo que existe se migra. `periodo`, `letra_alicuota` y `situacion_jurisdiccion` quedan nullable durante una versión y se borran cuando ningún código los lea.

```sql
ALTER TABLE gestion__padrones_iibb
  MODIFY padron_importacion_id INT UNSIGNED NULL COMMENT 'NULL en WS y MANUAL',
  MODIFY periodo CHAR(6) COLLATE utf8mb4_unicode_ci NULL COMMENT 'Obsoleto: usar f_desde/f_hasta',
  ADD COLUMN fuente VARCHAR(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ARCHIVO' COMMENT 'ARCHIVO | WS | MANUAL',
  ADD COLUMN f_desde DATE NULL,
  ADD COLUMN f_hasta DATE NULL,
  ADD COLUMN situacion VARCHAR(30) COLLATE utf8mb4_unicode_ci NULL COMMENT 'PADRON | INSCRIPTO_OTRA_JURISDICCION | NO_PERCIBIR',
  ADD COLUMN codigo_origen VARCHAR(10) COLLATE utf8mb4_unicode_ci NULL COMMENT 'Código crudo de la fuente (letra, grupo)',
  ADD COLUMN referencia VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL COMMENT 'MANUAL: N° de constancia / archivo adjunto',
  ADD COLUMN integracion_resultado_id BIGINT UNSIGNED NULL COMMENT 'WS: log en gestion__integraciones_resultados',
  ADD COLUMN usuario_id INT NULL,
  ADD COLUMN created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN tabla_estado_registro_id SMALLINT NOT NULL DEFAULT 1;

-- Migración de lo importado con SIRCIP
UPDATE gestion__padrones_iibb
   SET f_desde = STR_TO_DATE(CONCAT(periodo, '01'), '%Y%m%d'),
       f_hasta = LAST_DAY(STR_TO_DATE(CONCAT(periodo, '01'), '%Y%m%d')),
       situacion = CASE situacion_jurisdiccion WHEN 2 THEN 'INSCRIPTO_OTRA_JURISDICCION' WHEN 3 THEN 'NO_PERCIBIR' ELSE 'PADRON' END,
       codigo_origen = letra_alicuota
 WHERE f_desde IS NULL;

ALTER TABLE gestion__padrones_iibb
  MODIFY f_desde DATE NOT NULL,
  MODIFY situacion VARCHAR(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  DROP INDEX uk_padron_cuit,
  ADD UNIQUE KEY uk_padron_cuit_vig (empresa_id, jurisdiccion_id, cuit, fuente, f_desde),
  ADD KEY idx_padron_busqueda (empresa_id, jurisdiccion_id, cuit, f_desde);
```

**`gestion__padrones_iibb_importaciones`.** `periodo` pasa a nullable y se agregan `f_desde` / `f_hasta`, para los padrones con vigencia libre. Reimportar reemplaza las filas `ARCHIVO` del mismo rango de vigencia, no las `MANUAL` ni las `WS`.

**`gestion__empresas_impuestos_config`:**

```sql
ALTER TABLE gestion__empresas_impuestos_config
  ADD COLUMN fuente_alicuota VARCHAR(20) COLLATE utf8mb4_unicode_ci NULL
      COMMENT 'NULL = sin padrón (solo condición del cliente) | ARCHIVO | WS | MANUAL',
  ADD COLUMN sin_dato_accion VARCHAR(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'CONDICION'
      COMMENT 'Si la fuente no responde: CONDICION | BLOQUEAR',
  ADD COLUMN integracion_empresa_id INT UNSIGNED NULL COMMENT 'WS: credenciales en gestion__integraciones_empresas',
  ADD COLUMN ws_vigencia_dias SMALLINT NULL COMMENT 'WS: vigencia del dato si el servicio no la informa';
```

`formato_padron` sigue nombrando el driver (`SIRCIP`, y los que se sumen) y `formato_ddjj` el exportador. Configuración de Río Negro: `fuente_alicuota = NULL` mientras siga la 985/2018 (no tiene padrón); `ARCHIVO` + `SIRCIP` cuando se abra la fila de la 407/2025.

**Situación nueva `SIN_PADRON`** en `gestion__empresas_impuestos_config_situaciones`. No lleva DDL: es un valor más. Hay dos casos que no hay que mezclar:
- **El CUIT no está en el padrón** (la fuente respondió). Si la config tiene la situación `SIN_PADRON`, se aplica su alícuota (para las normas que agravan a quien no figura). Si no la tiene, se sigue por la condición del cliente, como hoy.
- **La fuente no respondió** (WS caído, padrón del mes sin importar). Lo decide `sin_dato_accion`: `CONDICION` sigue por la condición del cliente y avisa; `BLOQUEAR` no deja confirmar.

### 12.5 Búsqueda en el motor

Reemplaza a `perIibbPadron` y al bloque del dígito en `perIibbCalcular`:

```sql
SELECT * FROM gestion__padrones_iibb
 WHERE empresa_id = ? AND jurisdiccion_id = ? AND cuit = ? AND tabla_estado_registro_id = 1
   AND f_desde <= ? AND (f_hasta IS NULL OR f_hasta >= ?)
 ORDER BY FIELD(fuente, 'MANUAL', 'WS', 'ARCHIVO'), f_desde DESC
 LIMIT 1
```

Una carga manual gana mientras está vigente. Por eso **exige `f_hasta`**: corrige un caso puntual sin tapar los padrones siguientes. Después el motor hace `switch` sobre `situacion`:
- `PADRON` usa la alícuota de la fila;
- `INSCRIPTO_OTRA_JURISDICCION` usa la de la situación de la config;
- `NO_PERCIBIR` no percibe.

El motor ya no conoce SIRCIP.

### 12.6 Drivers

Va un archivo nuevo, `percepciones_iibb_drivers.php` (en `gestion` y `gestion_normal`, como el resto), con un registro:

```php
function perIibbDriversPadron() {
    return [
        'SIRCIP' => [
            'tipo' => 'ARCHIVO',
            'leer' => 'padLeerSircip',      // ($fh, $cfg) => yield fila normalizada | string de error
        ],
        // Padrón propio de diseño simple: layout declarativo, sin código propio
        // 'XXX' => ['tipo' => 'ARCHIVO', 'layout' => ['separador' => ';', 'campos' => ['cuit' => 4, 'f_desde' => 2,
        //           'f_hasta' => 3, 'alicuota' => 8, 'codigo_origen' => 10], 'formato_fecha' => 'dmY'],
        //           'situacion' => 'padSituacionXxx'],
        // 'YYY' => ['tipo' => 'WS', 'consultar' => 'padConsultarYyy'],   // ($conexion, $cfg, $cuit, $fecha) => fila | null | Exception
    ];
}

function perIibbDriversDdjj() {
    return [
        'SIRCAR_D1' => 'perExportarSircarD1',   // ($filas, $cfg) => ['nombre' => ..., 'contenido' => ..., 'zip' => bool]
        'SIRCIP'    => 'perExportarSircip',
    ];
}
```

- **Padrón SIRCIP.** Es el `padParsearSircip` actual, que además traduce el campo 7 a `situacion` (hoy lo hace el motor). Sirve para toda provincia adherida, porque la posición de la jurisdicción ya es un parámetro.
- **Padrón propio.** La mayoría son archivos de ancho fijo o separados por `;`, con CUIT, vigencia, alícuota y un grupo o letra. Se describen con un `layout` que lee un lector genérico (`padLeerLayout`); solo los raros llevan función propia. Los layouts van **en código y no en tablas**, por tres razones:
  - un formato cambia solo cuando cambia la norma;
  - hay que probarlo con el archivo real;
  - así no hacen falta ids de `conf__tablas`, cuyo bloque 423–434 está completo.
- **Servicio web.** `consultar` devuelve una fila normalizada. Se guarda con:
  - `fuente = 'WS'`;
  - la vigencia que informe el servicio, o si no `ws_vigencia_dias`;
  - el log en `gestion__integraciones_resultados`, con `referencia_tipo = 'PADRON_IIBB'`.

  Las credenciales son por empresa y van en `gestion__integraciones_empresas`, como las de ARCA.
- **Carga manual (consulta web individual).** No lleva driver. Es un formulario con jurisdicción, situación, alícuota, vigencia (`f_hasta` obligatoria) y referencia o adjunto (`uploads/padrones/`). Está en dos lugares:
  - la página 115, en una solapa "Carga manual";
  - "Historial de Situación Fiscal" del cliente (`percepciones_iibb_clientes_*`).

  Está disponible para toda config, sea cual sea su fuente, porque también es el respaldo de las otras tres.

`padImportar` y `perExportar` pasan a buscar el driver en el registro, y fallan con "Formato no soportado" si no está.

### 12.7 Servicio web: cuándo se consulta

- **Al guardar el borrador** y en la vista previa (`calcular_percepciones`): si no hay fila vigente, se consulta y se guarda, fuera de transacción.
- **Al confirmar**: si sigue sin fila vigente, se consulta **antes** de `mysqli_begin_transaction`. Nunca dentro de la transacción, porque dejaría los locks de numeración y de comprobantes tomados mientras dura la llamada.
- Timeout corto (5 s). Si falla, se aplica `sin_dato_accion`; no se reintenta en el mismo request.

### 12.8 Impacto en lo existente

| Pieza | Cambio |
|---|---|
| `perIibbCalcular` | Búsqueda de §12.5, `switch` por `situacion`, situación `SIN_PADRON`, `sin_dato_accion`. Sin cambios en territorio, exclusiones, NC ni mínimos. |
| `gestion__comprobantes_impuestos` | Sin cambios: `padron_iibb_id` y `padron_crc` ya apuntan a la fila usada. `tipo_origen = 'padron'` para las tres fuentes. |
| DDJJ | Los exportadores reciben las mismas filas. SIRCIP sigue tomando el CRC de la fila del padrón. |
| Página 73 | En la solapa "Agente de Percepción": fuente, acción sin dato, integración y vigencia del WS. |
| Página 115 | Solapa "Carga manual" y listado por fuente. Función nueva en `conf__paginas_funciones` para la carga manual (ids a definir dentro del bloque). |
| Simulador | `09_percepciones_iibb.py` tiene que seguir dando 46/46 sin tocarlo: es la prueba de que el refactor no cambia el comportamiento. Un caso nuevo cubre la carga manual (gana sobre el padrón, vence), `SIN_PADRON`, `sin_dato_accion = BLOQUEAR` y un driver de WS de prueba que no sale a internet. |

### 12.9 Orden de implementación

1. **Refactor sin cambio de comportamiento:** DDL y migración de §12.4, registro de drivers, traducción del campo 7 dentro del driver SIRCIP, búsqueda nueva y exportadores por registro. Se da por cerrado cuando el caso 09 pasa sin cambios y la suite completa da OK.
2. **Carga manual:** cubre la consulta web individual y el respaldo de las demás fuentes.
3. **Padrón propio:** con la segunda provincia en la que la empresa sea agente, a partir de su archivo real (layout declarativo, más `SIN_PADRON` si su norma lo pide).
4. **Servicio web:** solo para una provincia que lo exija, o en la que el archivo no alcance.

### 12.10 A decidir

- Prioridad entre fuentes. Se propone MANUAL > WS > ARCHIVO, con la carga manual siempre con vencimiento.
- Si la carga manual exige adjuntar la constancia o alcanza con un número de referencia.
- Ids de la función de carga manual en `conf__paginas_funciones` (y su par del módulo 5).
- Sigue abierto, de §11: qué CRC va en SIRCIP para las filas que no figuran en el padrón.
