# Contratos de honorarios con liquidación mensual (módulo 4, Estudio Contable)

Estado: **diseño v1, sin implementar**. Fecha: 2026-10-15. Autor: disenador-proceso, a pedido de Pablo.
Scripts: `_local/sql/mantenimiento/20261015_estudio_contratos_0{1..7}_*.sql` (§9). Las decisiones abiertas están en §11 y hay que confirmarlas **antes** de correr los scripts. La más urgente es la de los bloques de ids (D-01).

---

## 0. Qué se reemplaza y qué se superpone

| Hoy | Qué pasa |
|---|---|
| `estudio__clientes_abonos` (tabla 407) | Se reemplaza por **contrato + líneas**. Hay migración (script 06). Local: 0 filas. Producción: no tiene ninguna tabla `estudio__*`. |
| `estudio__honorarios_novedades` (408) | Se reemplaza por líneas `EVENTUAL` del contrato (una línea para un solo período) y por líneas manuales en la liquidación en Borrador. Las novedades sin facturar se migran como líneas EVENTUAL (script 06). |
| `estudio__honorarios_conceptos` (404) | **Queda.** Es el catálogo de conceptos (concepto → `producto_id`; el producto da la alícuota de IVA y la cuenta de ventas). `tipo_cargo` deja de usarse: la modalidad ahora es de cada línea del contrato. |
| `estudio__honorarios_tarifarios` + `_detalles` (405/406) | **Quedan, como lista de precios del estudio**: precarga los valores de una línea nueva. Los detalles suman `profesional_categoria_id` (tarifa por hora según categoría) e `indice_id` (serie del valor del módulo), y `unidad_medida` pasa a ser un código: `MONEDA` / `HORA` / `MODULO`. El contrato **copia** los valores al aprobarse: si cambia el tarifario, los contratos vigentes no cambian. Para cambiarlos hace falta una adenda (§5). |
| Página 405 `honorarios_conceptos*` ("Tarifarios y Honorarios") | Queda, como "Conceptos y Tarifarios". Se le saca la solapa de abonos. Al tocarla, corregir sus combos: `condiciones_pago` sin filtro de empresa, y productos y PV con SQL concatenado. |
| Página 406 `honorarios_facturacion*` ("Facturación de Abonos") | **Se da de baja** (script 07, que se corre después de desplegar el código nuevo). La reemplaza la 422. Su modelo emite facturas directo en estado 5, con tipo 1/6, sucursal 1, moneda 1, numeración MAX+1, IVA 21 % fijo, sin ARCA ni asiento, y la entidad "rápida" sin condición fiscal (sale Factura B a un RI). Nada de eso se reutiliza. |
| `dashboard_estudio.php` | Suma `estudio__clientes_abonos.importe_base`. Hay que pasarlo a "contratos vigentes / liquidaciones del mes". |

**Duplicación:** no hay otro proceso de contratos ni de facturación recurrente en el sistema. La única superposición es con abonos/novedades, que es justamente lo que se reemplaza. Mini-gestión (`estudio__minigestion_comprobantes`) y tareas (`estudio__tareas_tiempos`) se usan como **fuentes** de la liquidación, sin duplicarlas.

---

## 1. Hallazgos del relevamiento que condicionan el diseño

1. **`vfValidarComprobantesAnterioresConCae` está declarada DENTRO de `vfEjecutarTransicionEstado`** (`modules/gestion/ventas_facturas_model.php` línea 1544, profundidad de llaves 2, lo mismo en `gestion_normal`). PHP la declara recién cuando se ejecuta esa línea. Lo verifiqué con PHP 8.1 local:
   - si se llama dos veces a `vfEjecutarTransicionEstado(... 'confirma' ...)` en el mismo request, la segunda da *Fatal: Cannot redeclare*. Eso hace imposible confirmar facturas en lote;
   - en un request nuevo, "Aprobada" con ARCA (`vfArcaAutorizarFactura`, línea 2623) la llama **antes** de que exista: *Call to undefined function*. Es un `Error`, no una `Exception`, así que el `try` no lo atrapa. **Hoy la aprobación ARCA de ventas_facturas falla siempre que la factura se haya confirmado en un request anterior**, o sea, siempre. Esto es independiente de este diseño y conviene corregirlo ya: sacar la función al nivel superior del archivo, en los dos módulos.
2. `guardarVentaFactura()` y `vfEjecutarTransicionEstado()` abren y cierran **su propia transacción**. Si se llaman dentro de otra, `mysqli_begin_transaction` hace commit implícito de la exterior. Además `guardarVentaFactura` tiene un `return` (tipo inválido) después del `begin` sin rollback, y las dos leen `$_SESSION['usuario_id']`.
3. ARCA (`vfArcaDatosFactura` y `arca_wsfe_solicitar_cae`) manda siempre `Concepto = 1` (productos) y no envía `FchServDesde` / `FchServHasta` / `FchVtoPago`. Los honorarios son **servicios** (concepto 2), y ARCA exige esas tres fechas. Además ARCA hoy solo autoriza comprobantes en pesos (`MonId PES`).
4. No hay historial de cotizaciones: solo `gestion__monedas.cotizacion_actual`, por empresa. La empresa 2 no tiene USD dado de alta.
5. No hay módulo de sueldos ni importación de F931. Lo único con empleados por período es `sindi__ddjj` / `sindi__ddjj_empleados` (módulo 6), pero vive en el **tenant del sindicato**, con el empleador como entidad del sindicato. No es una fuente del cliente del estudio.
6. No hay índices (IPC/CER) ni valor de módulo CPCE/FACPCE en ninguna tabla.
7. No hay categorías de profesional (socio/senior/junior). `estudio__tareas_tiempos` tiene `usuario_id` y `minutos`, y el cliente sale de `estudio__tareas.cliente_id`.
8. `estudio__clientes` no tiene `entidad_id`. Los procesos del módulo graban `entidad_id = 0`, y la facturación vieja crea la entidad por CUIT sin condición fiscal ni sucursal.
9. `conf__perfiles_funciones` y `conf__empresas_perfiles_funciones` **no tienen UNIQUE**: el `INSERT IGNORE` que usan otros scripts no evita duplicados. Acá se usa `WHERE NOT EXISTS`.
10. `conf__empresas_modulos`: módulo 4 activo en la empresa 3 e **inactivo (0) en la 2** en la base local.
11. `gestion__comprobantes.modulo` queda en `''` para gestión y en `'sindicatos'` para el 6. La cuenta corriente filtra por los tipos de `gestion__comprobantes_tipos_cancelaciones`, así que un tipo interno no cancelable no la afecta.
12. `vinculacionesVerificarPermiso()` (`core/empresas_vinculaciones_model.php`) existe pero nadie la llama. La liquidación pasa a ser su **primer consumidor**: leer comprobantes de la empresa del cliente es acceso entre tenants.

---

## 2. Modelo de datos

Dialecto: prefijo `estudio__`, PK `bigint unsigned`, `empresa_id int unsigned` (como en el resto del módulo), períodos `int unsigned` AAAAMM (como `estudio__minigestion_comprobantes.periodo_fiscal`), importes `decimal(18,2)`, valores de índice y tipo de cambio `decimal(18,6)`, utf8mb4_unicode_ci, sin JSON. `tabla_estado_registro_id` guarda el **id genérico** de `conf__estados_registros`.

### 2.1 Bloque de ids propuesto (D-01, a confirmar)

| Tabla de config | Propuesto | Por qué |
|---|---|---|
| `conf__tablas` | **450–469** (usa 450–460) | El 401–422 del módulo está lleno. 423–436 es conf/gestion y va a seguir creciendo desde el 437, así que se deja libre 437–449. 450–500 está libre en local y en el volcado de producción. |
| `conf__paginas` | **421–425** | Dentro del bloque del estudio (400–440). Son hijas del menú 420 "Honorarios y Facturación", que hoy solo tiene la 405 y la 406. |
| `conf__paginas_funciones` | **4001–4199** (usa 4001–4126) | El estudio tiene 3287–3334 y 3397–3400, mezclados con gestion (3335–3422). 3001–3999 es de ML "compartido". 4000–4999 está vacío. El módulo 5 copia solo funciones del módulo 2 (+5000), así que no choca. |
| `conf__tablas_estados_registros` | **2247–2299** (usa 2247–2280) | Continúa el bloque del estudio (2213–2246). Sindicatos arranca en 3001. |

Hay que agregar las cuatro filas a la tabla de bloques del CLAUDE.md.

### 2.2 Tablas nuevas

| tabla_id | Tabla | Tipo | Rol |
|---|---|---|---|
| 450 | `estudio__honorarios_contratos` | 3 Proceso Largo | Cabecera del contrato. Lleva los 8 campos. |
| 451 | `estudio__honorarios_contratos_lineas` | 1 | Líneas por concepto, **versionadas** (`version_desde` / `version_hasta`). |
| 452 | `estudio__honorarios_contratos_adendas` | 2 Proceso Corto | Una fila por versión del contrato: ALTA, MODIFICACION, AJUSTE_INDICE, RENOVACION, SUSPENSION, REANUDACION, FINALIZACION. |
| 453 | `estudio__honorarios_contratos_lineas_escalas` | 1 | Tramos de volumen y tarifas por hora según categoría, congelados en la línea. |
| 454 | `estudio__honorarios_liquidaciones` | 3 Proceso Largo | Liquidación interna por contrato y período. Lleva los 8 campos y tiene espejo en `gestion__comprobantes`. |
| 455 | `estudio__honorarios_liquidaciones_detalles` | 1 | Foto de cada valor usado. |
| 456 | `estudio__indices` | 1 | Series por empresa: IPC, CER, índice propio, valor del módulo CPCE/FACPCE. |
| 457 | `estudio__indices_valores` | 1 | Valor por período. |
| 458 | `estudio__profesionales_categorias` | 1 | SOCIO / SENIOR / JUNIOR (por empresa, editable). |
| 459 | `estudio__profesionales` | 1 | Usuario → categoría, con vigencia. |
| 460 | `estudio__clientes_legajos_periodos` | 1 | Legajos del cliente por período, materializados con su origen. |

Cambios a tablas existentes (todos condicionales y reanudables; en producción no existen las tablas `estudio__*` y se saltean):

- `estudio__clientes` + `entidad_id int unsigned NULL` (índice). Es el vínculo con `gestion__entidades` (§6.1).
- `estudio__tareas_tiempos` + `liquidacion_id bigint unsigned NULL` (índice). Marca las horas ya facturadas.
- `estudio__honorarios_tarifarios_detalles` + `profesional_categoria_id int unsigned NOT NULL DEFAULT 0` + `indice_id int unsigned NULL`. La UNIQUE `(tarifario_id, concepto_id)` pasa a `(tarifario_id, concepto_id, profesional_categoria_id)`.
- `gestion__ventas_facturas` + `arca_concepto tinyint NOT NULL DEFAULT 1` + `f_servicio_desde DATE NULL` + `f_servicio_hasta DATE NULL`. Es la tabla del módulo 2/5 (script 05; corre también en producción).

### 2.3 `estudio__honorarios_contratos` (450)

| Columna | Tipo | Nota |
|---|---|---|
| contrato_id | bigint unsigned PK AI | |
| **empresa_id** | int unsigned | estudio (tenant) |
| **sucursal_id** | int unsigned | sucursal del PV de facturación (`gestion__puntos_venta.sucursal_id`) |
| **comprobante_tipo_id** | int unsigned | tipo interno `E_CTR` de la empresa (`emp_comprobante_tipo_id($c,$e,'E_CTR')`) |
| **comprobante_pv** | int unsigned | **id** del PV con que se numera el contrato (el mismo de facturación al darlo de alta) |
| **comprobante_nro** | bigint unsigned DEFAULT 0 | 0 en Borrador; se numera al aprobar (`vfObtenerProximoNumero`, numerador empresa+PV+E_CTR) |
| comprobante_nro_unico | GENERATED `NULLIF(comprobante_nro,0)` STORED | para la UNIQUE |
| **comprobante_id** | bigint unsigned NULL | **queda NULL**: el contrato no tiene espejo en `gestion__comprobantes` (D-05) |
| **entidad_id** | int unsigned | entidad del cliente en la empresa del estudio (§6.1). Se exige > 0 para pasar de Borrador. |
| **f_emision** | date | fecha del contrato |
| cliente_id | bigint unsigned | `estudio__clientes` |
| contrato_version | smallint unsigned DEFAULT 0 | última versión **aplicada** (0 = nunca aprobado) |
| f_inicio / f_fin | date / date NULL | vigencia (`f_fin` NULL = indeterminado) |
| renovacion_automatica | tinyint(1) | |
| renovacion_meses | smallint unsigned DEFAULT 12 | |
| preaviso_dias | smallint unsigned DEFAULT 30 | aviso de vencimiento y de ajuste |
| modalidad_facturacion | enum('MES_VENCIDO','MES_ADELANTADO') | |
| dia_emision | tinyint unsigned DEFAULT 1 | 1..28 |
| punto_venta_id | int unsigned | PV de las **facturas** (puede cambiar por adenda) |
| condicion_pago_id | int unsigned | `gestion__condiciones_pago` (por empresa) |
| moneda_id | int unsigned | moneda del contrato (ARS o USD de la empresa) |
| tc_fuente | enum('COTIZACION_MONEDA','MANUAL') | de dónde sale el TC del día (§4.8) |
| minimo_mensual / maximo_mensual | decimal(18,2) NULL | en la moneda del contrato |
| concepto_ajuste_id | bigint unsigned NULL | concepto con que se factura la diferencia al mínimo; obligatorio si hay mínimo |
| indice_id | bigint unsigned NULL | serie de ajuste |
| ajuste_frecuencia_meses | tinyint unsigned DEFAULT 0 | 0 sin ajuste; 3, 4, 6, 12 |
| ajuste_periodo_base | int unsigned NULL | AAAAMM base del último ajuste aplicado |
| ajuste_proximo_periodo | int unsigned NULL | AAAAMM del próximo ajuste (**materializado**) |
| ajuste_rezago_meses | tinyint unsigned DEFAULT 1 | cuántos meses antes se toma el índice (publicación) |
| ajuste_tope_pct | decimal(7,4) NULL | tope opcional por ajuste |
| documento_archivo / documento_nombre | varchar(255) NULL | contrato firmado (`uploads/estudio/contratos/{empresa}/`) |
| f_firma | date NULL | |
| ultimo_periodo_liquidado | int unsigned NULL | AAAAMM del último período con liquidación aprobada (**materializado**) |
| f_suspension / f_baja | date NULL | |
| motivo_estado | varchar(255) NULL | motivo de suspensión, finalización o anulación |
| observaciones | text NULL | |
| legacy_ref | varchar(50) NULL | idempotencia de la migración (`ABONOS:{cliente_id}`) |
| tabla_estado_registro_id | int unsigned DEFAULT 3 | |
| usuario_creacion_id / fecha_creacion / usuario_modificacion_id / fecha_modificacion | | |

Índices: `UNIQUE uk_hc_numeracion (empresa_id, comprobante_tipo_id, comprobante_pv, comprobante_nro_unico)`, `UNIQUE uk_hc_legacy (empresa_id, legacy_ref)`, `idx_hc_cliente (empresa_id, cliente_id, tabla_estado_registro_id)`, `idx_hc_estado (empresa_id, tabla_estado_registro_id)`, `idx_hc_ajuste (empresa_id, ajuste_proximo_periodo)`.

### 2.4 `estudio__honorarios_contratos_lineas` (451)

Una sola tabla ancha con columnas por modalidad (el mismo criterio que tenía `clientes_abonos`). Las columnas que no aplican a la modalidad quedan en NULL o en 0.

| Columna | Tipo | Uso |
|---|---|---|
| contrato_linea_id | bigint unsigned PK | |
| empresa_id, contrato_id | | |
| version_desde | smallint unsigned | versión en que entra la línea (Borrador = 1; adenda N = N) |
| version_hasta | smallint unsigned NULL | última versión en que vale (NULL = vigente) |
| linea_origen_id | bigint unsigned NULL | línea de la versión anterior que esta reemplaza (trazabilidad del ajuste) |
| concepto_id | bigint unsigned | `estudio__honorarios_conceptos` → `producto_id` |
| descripcion | varchar(255) | texto de la factura |
| modalidad | enum('FIJO','VOLUMEN','PORCENTAJE','LEGAJO','HORA','MODULOS','EVENTUAL') | |
| importe | decimal(18,2) | FIJO / EVENTUAL: importe. LEGAJO: precio por legajo excedente. |
| frecuencia_meses | tinyint unsigned DEFAULT 1 | FIJO: 1 mensual, 12 anual (balance, DDJJ Ganancias), etc. |
| periodo_desde / periodo_hasta | int unsigned NULL | primer y último período en que se factura. Con frecuencia > 1, `periodo_desde` es el ancla. EVENTUAL: desde = hasta. |
| prorratea | tinyint(1) DEFAULT 1 | FIJO mensual: prorratea alta/baja/suspensión. Frecuencia > 1 y EVENTUAL: 0. |
| fuente_operaciones | enum('VENTAS','COMPRAS','AMBAS') NULL | VOLUMEN y PORCENTAJE (en PORCENTAJE solo VENTAS) |
| forma_escala | enum('PROGRESIVA','TRAMO_ALCANZADO','MONTO_TRAMO') NULL | VOLUMEN |
| porcentaje | decimal(9,4) NULL | PORCENTAJE |
| base_calculo | enum('PERIODO','PROMEDIO_MOVIL') NULL | PORCENTAJE |
| meses_promedio | tinyint unsigned NULL | PORCENTAJE promedio móvil |
| cantidad_incluida | decimal(12,2) DEFAULT 0 | LEGAJO: legajos incluidos. HORA: horas incluidas. |
| cantidad_modulos | decimal(12,4) NULL | MODULOS |
| indice_id | bigint unsigned NULL | MODULOS: serie del valor del módulo |
| piso / tope | decimal(18,2) NULL | PORCENTAJE (y opcionalmente cualquier línea variable) |
| ajustable | tinyint(1) DEFAULT 1 | se ajusta por índice (MODULOS: 0, porque ya se indexa) |
| orden | smallint unsigned | |
| legacy_ref | varchar(50) NULL | migración |
| tabla_estado_registro_id | int unsigned DEFAULT 1 | 1 / 2 |

Índices: `idx_hcl_contrato (contrato_id, version_desde, version_hasta)`, `UNIQUE uk_hcl_legacy (empresa_id, legacy_ref)`.

**Versión vigente para un período P** = la adenda **aplicada (5)** con mayor `version` cuyo `periodo_vigencia_desde ≤ P`. Las líneas de esa versión son las que cumplen `version_desde ≤ V AND (version_hasta IS NULL OR version_hasta ≥ V) AND tabla_estado_registro_id = 1`.

### 2.5 `estudio__honorarios_contratos_lineas_escalas` (453)

`escala_id`, `empresa_id`, `contrato_linea_id`, `tipo enum('TRAMO','CATEGORIA')`, `cantidad_desde decimal(12,2)`, `cantidad_hasta decimal(12,2) NULL` (NULL = sin tope), `profesional_categoria_id int unsigned DEFAULT 0`, `importe decimal(18,2)`, `orden`, `tabla_estado_registro_id`.
VOLUMEN: tramos (0–50 importe 0 = incluido; 51–200 $X; 201–NULL $Y). HORA: una fila CATEGORIA por categoría con su tarifa. Una escala nunca se edita en una línea aplicada: el ajuste crea una línea nueva con escalas nuevas.

### 2.6 `estudio__honorarios_contratos_adendas` (452)

| Columna | Tipo | Nota |
|---|---|---|
| adenda_id | bigint unsigned PK | |
| empresa_id, contrato_id | | |
| version | smallint unsigned | UNIQUE `(contrato_id, version)` |
| tipo | enum('ALTA','MODIFICACION','AJUSTE_INDICE','RENOVACION','SUSPENSION','REANUDACION','FINALIZACION') | |
| periodo_vigencia_desde | int unsigned | AAAAMM desde el que rige |
| f_efecto | date NULL | fecha exacta: suspensión, reanudación, baja (para prorratear) |
| foto de cabecera | | `minimo_mensual`, `maximo_mensual`, `moneda_id`, `modalidad_facturacion`, `dia_emision`, `punto_venta_id`, `condicion_pago_id`, `f_fin` de esa versión |
| indice_id, indice_periodo_base, indice_valor_base, indice_periodo_ajuste, indice_valor_ajuste | | foto del ajuste |
| coeficiente | decimal(12,6) NULL | `valor_ajuste / valor_base` (con tope si hay) |
| resumen | text NULL | texto para el cliente (antes → después por línea) |
| f_notificacion | datetime NULL | **obligatoria** para aplicar MODIFICACION / AJUSTE_INDICE |
| notificacion_medio | varchar(50) NULL | EMAIL / IMPRESO / PORTAL |
| notificacion_usuario_id | int unsigned NULL | |
| documento_archivo / documento_nombre / f_firma | | adenda firmada (opcional) |
| motivo | varchar(255) NULL | |
| tabla_estado_registro_id | int unsigned DEFAULT 3 | |
| auditoría | | |

### 2.7 `estudio__honorarios_liquidaciones` (454)

| Columna | Tipo | Nota |
|---|---|---|
| liquidacion_id | bigint unsigned PK | |
| **empresa_id** | | |
| **sucursal_id** | | la del PV de facturación |
| **comprobante_tipo_id** | | interno `E_LIQ` |
| **comprobante_pv** | | id del PV de facturación |
| **comprobante_nro** | DEFAULT 0 | se numera al aprobar (numerador empresa+PV+E_LIQ) |
| comprobante_nro_unico | GENERATED | |
| **comprobante_id** | bigint unsigned NULL | espejo en `gestion__comprobantes` (D-05) |
| **entidad_id** | | entidad del cliente |
| **f_emision** | date | fecha prevista de la factura: `dia_emision` del período de emisión |
| contrato_id, cliente_id | | |
| contrato_version | smallint unsigned | versión usada |
| tipo_liquidacion | enum('MENSUAL','CIERRE') | CIERRE: devolución por baja a mitad de mes en MES_ADELANTADO (§4.6) |
| periodo_servicio | int unsigned | AAAAMM de los servicios facturados |
| periodo_emision | int unsigned | AAAAMM en que se factura |
| dias_servicio / dias_periodo | tinyint unsigned | |
| factor_prorrateo | decimal(9,6) | |
| moneda_id | | moneda del contrato |
| tipo_cambio | decimal(18,6) DEFAULT 1 | foto del TC (§4.8) |
| f_tipo_cambio | date NULL | |
| subtotal_lineas | decimal(18,2) | en moneda del contrato |
| ajuste_minimo / ajuste_maximo | decimal(18,2) | diferencia al mínimo (+) / recorte por tope (−) |
| importe_neto | decimal(18,2) | en moneda del contrato |
| importe_neto_local | decimal(18,2) | en moneda base (la que se factura) |
| requiere_revision | tinyint(1) | 1 si alguna base salió de fallback o tuvo un aviso |
| avisos | text NULL | uno por renglón |
| liq_activa | GENERATED `CASE WHEN estado IN (6,38) THEN NULL ELSE 1 END` STORED | |
| venta_factura_id | int unsigned NULL | factura generada. **UNIQUE**. |
| aprobado_usuario_id / f_aprobacion | | |
| facturado_usuario_id / f_facturacion | | |
| error_facturacion | varchar(255) NULL | último error de facturación o ARCA |
| observaciones | text NULL | |
| tabla_estado_registro_id | int unsigned DEFAULT 3 | |
| auditoría | | |

Índices: **`UNIQUE uk_hli_periodo (empresa_id, contrato_id, periodo_servicio, tipo_liquidacion, liq_activa)`**: una sola liquidación viva por contrato, período y tipo; las anuladas o eliminadas no cuentan. `UNIQUE uk_hli_numeracion (...)`, `UNIQUE uk_hli_factura (venta_factura_id)`, `idx_hli_emision (empresa_id, periodo_emision, tabla_estado_registro_id)`, `idx_hli_cliente (empresa_id, cliente_id)`.

### 2.8 `estudio__honorarios_liquidaciones_detalles` (455)

| Columna | Tipo | Nota |
|---|---|---|
| liquidacion_detalle_id | bigint unsigned PK | |
| empresa_id, liquidacion_id | | |
| contrato_linea_id | bigint unsigned NULL | NULL = renglón manual o de ajuste al mínimo |
| concepto_id, producto_id | | foto |
| descripcion | varchar(255) | |
| modalidad | enum(... 'AJUSTE_MINIMO','MANUAL') | |
| periodo_base | int unsigned | período de los datos medidos (puede ser ≠ periodo_servicio) |
| fuente | enum('CONTRATO','ERP','MINIGESTION','LEGAJOS_MANUAL','LEGAJOS_SINDI','LEGAJOS_CLIENTE','TAREAS','INDICE','MANUAL') | |
| cantidad_medida | decimal(18,4) | comprobantes contados, legajos, horas, módulos, base de facturación |
| cantidad_incluida | decimal(18,4) | |
| cantidad_facturable | decimal(18,4) | |
| tramo_desde / tramo_hasta | decimal(12,2) NULL | renglón de un tramo |
| profesional_categoria_id | int unsigned NULL | renglón de horas por categoría |
| base_importe | decimal(18,2) NULL | PORCENTAJE: base (ventas netas o promedio) |
| base_detalle | varchar(255) NULL | PORCENTAJE promedio móvil: `202607:1200000.00;202608:...` |
| indice_id / indice_periodo / indice_valor | | MODULOS: valor del módulo usado |
| precio_unitario | decimal(18,6) | |
| importe_calculado | decimal(18,2) | antes de prorrateo y tope |
| factor_prorrateo | decimal(9,6) | |
| ajuste_tope | decimal(18,2) | parte del recorte por máximo que le toca a este renglón (≤ 0) |
| importe_final | decimal(18,2) | moneda del contrato |
| importe_final_local | decimal(18,2) | × TC, redondeado por renglón |
| iva_alicuota_id / iva_porcentaje | | foto de la alícuota del producto |
| editado_manual | tinyint(1) | |
| motivo_edicion | varchar(255) NULL | obligatorio si `editado_manual = 1` |
| orden | | |
| tabla_estado_registro_id | DEFAULT 1 | |

### 2.9 Índices, profesionales y legajos

- `estudio__indices` (456): `indice_id`, `empresa_id`, `codigo varchar(20)` (UNIQUE por empresa: `IPC`, `CER`, `PROPIO_1`, `MOD_CPCE_RN`...), `nombre`, `tipo enum('INDICE','MODULO')`, `moneda_id NULL` (MODULO: en qué moneda está el valor), `fuente varchar(150)`, estado 1/2.
- `estudio__indices_valores` (457): `indice_valor_id`, `empresa_id`, `indice_id`, `periodo int unsigned`, `valor decimal(18,6)`, `origen enum('MANUAL','IMPORTACION')`, `usuario_id`, `fecha_carga`. **UNIQUE `(indice_id, periodo)`**. Los valores no se editan si algún ajuste aplicado o alguna liquidación aprobada los usó. La validación es en código: busca la foto en adendas y detalles.
- `estudio__profesionales_categorias` (458): `profesional_categoria_id`, `empresa_id`, `codigo` (SOCIO/SENIOR/JUNIOR), `nombre`, `orden`, estado. UNIQUE `(empresa_id, codigo)`. El script 04 siembra las tres por empresa con módulo 4.
- `estudio__profesionales` (459): `profesional_id`, `empresa_id`, `usuario_id`, `profesional_categoria_id`, `f_desde`, `f_hasta NULL`, estado. UNIQUE `(empresa_id, usuario_id, f_desde)`. La categoría de una hora es la vigente en `f_registro`.
- `estudio__clientes_legajos_periodos` (460): `cliente_legajo_periodo_id`, `empresa_id`, `cliente_id`, `periodo`, `cantidad int unsigned`, `origen enum('MANUAL','F931','SINDI_DDJJ','CLIENTE_CANTIDAD')`, `referencia_id NULL` (ddjj_id / importación), `usuario_id`, `fecha_carga`. **UNIQUE `(empresa_id, cliente_id, periodo)`**. Es la base materializada de la modalidad LEGAJO: la liquidación siempre lee de acá (§4.3).

---

## 3. Estados, funciones y permisos

### 3.1 Elección de estados genéricos

| Estado del proceso | Genérico | Por qué |
|---|---|---|
| Borrador | **3** BORRADOR | Estándar de proceso. |
| Pend. Aprobación | **4** PEND_APROB | Estándar. |
| **Vigente** | **5** CONFIRMADO (etiqueta "Vigente") | Es una tabla de proceso (tipo 3), y en estas tablas el 1 Activo es el estado de diccionario. Al pasar 4→5 el documento se numera, igual que cualquier comprobante. Hay precedente en el mismo módulo: normas (415) usa 5 = "Vigente / Impactada". Como el contrato no tiene espejo en `gestion__comprobantes`, los derivados 39/40 de tesorería no lo tocan nunca. |
| **Suspendido** | **16** EN_ESPERA | Mismo criterio que "Suspendido" del cliente (2215, script `20261015_estudio_estados_01_genericos.sql`). |
| **Finalizado** | **21** CUMPLIDO | Precedente del módulo: tareas "Finalizada" = 21, licencias "Finalizada" = 21. El 2 Inactivo queda para los diccionarios y para el activo "dado de baja". Una rescisión anticipada también termina en 21, con `motivo_estado`. |
| Eliminado (Borrador descartado) | **6** ELIMINADO | Como ventas_facturas 3→6. |
| Anulado (Vigente cargado por error, sin liquidaciones vivas) | **38** ANULADO | |
| Liquidación aprobada | **30** APROBADO | Significa exactamente eso, y no se confunde con el 5 del contrato. |
| Liquidación facturada | **12** FACTURADO | |
| Adenda aplicada | **5** CONFIRMADO ("Aplicada") | |
| Adenda rechazada | **25** RECHAZADO | |

No hace falta ningún estado genérico nuevo.

### 3.2 `conf__tablas_estados_registros` (ids 2247–2280)

| id | tabla | genérico | etiqueta | inicial | final | orden |
|---|---|---|---|---|---|---|
| 2247 | 450 | 3 | Borrador | 1 | 0 | 1 |
| 2248 | 450 | 4 | Pendiente de Aprobación | 0 | 0 | 2 |
| 2249 | 450 | 5 | Vigente | 0 | 0 | 3 |
| 2250 | 450 | 16 | Suspendido | 0 | 0 | 4 |
| 2251 | 450 | 21 | Finalizado | 0 | 1 | 5 |
| 2252 | 450 | 6 | Eliminado | 0 | 1 | 6 |
| 2253 | 450 | 38 | Anulado | 0 | 1 | 7 |
| 2254 | 452 | 3 | Borrador | 1 | 0 | 1 |
| 2255 | 452 | 4 | Pendiente de Aprobación | 0 | 0 | 2 |
| 2256 | 452 | 5 | Aplicada | 0 | 1 | 3 |
| 2257 | 452 | 25 | Rechazada | 0 | 1 | 4 |
| 2258 | 452 | 6 | Eliminada | 0 | 1 | 5 |
| 2259 | 454 | 3 | Borrador | 1 | 0 | 1 |
| 2260 | 454 | 4 | En Revisión | 0 | 0 | 2 |
| 2261 | 454 | 30 | Aprobada | 0 | 0 | 3 |
| 2262 | 454 | 12 | Facturada | 0 | 0 | 4 |
| 2263 | 454 | 6 | Eliminada | 0 | 1 | 5 |
| 2264 | 454 | 38 | Anulada | 0 | 1 | 6 |
| 2265–2280 | 451, 453, 455, 456, 457, 458, 459, 460 | 1 / 2 | Activo / Inactivo | 1 / 0 | 0 / 1 | |

### 3.3 Páginas (4 archivos cada una, `modules/estudio_contable/`)

| pagina_id | Nombre | url | tabla_id | padre | orden |
|---|---|---|---|---|---|
| 421 | Contratos de Honorarios | `honorarios_contratos.php` | 450 | 420 | 3 |
| 422 | Liquidaciones de Honorarios | `honorarios_liquidaciones.php` | 454 | 420 | 4 |
| 423 | Adendas y Ajustes | `honorarios_adendas.php` | 452 | 420 | 5 |
| 424 | Índices y Valor de Módulo | `honorarios_indices.php` | 456 | 420 | 6 |
| 425 | Profesionales y Categorías | `honorarios_profesionales.php` | 459 | 420 | 7 |

Guard: `ajax_guard(['pagina_idx' => 42x])`. `honorarios_liquidaciones_ajax.php` llama a `ventas_facturas_model.php` desde el modelo y no al `_ajax` de ventas, así que no hace falta tocar el `paginas_url` de `ventas_facturas_ajax.php`. Las bases de legajos se cargan desde la 422 (solapa "Bases del período"). La solapa de adendas del contrato (421) llama a `honorarios_adendas_ajax.php`, que entonces declara `'modulo_id' => 4, 'paginas_url' => ['honorarios_adendas.php','honorarios_contratos.php']`.

### 3.4 Funciones (`conf__paginas_funciones`, origen → destino en ids genéricos)

**421 Contratos (4001–4023)**

| id | Nombre | accion_js | origen → destino |
|---|---|---|---|
| 4001 | Agregar | agregar | 0 → 3 |
| 4002 | Editar | editar | 3 → 3 |
| 4003 | Adjuntar documento | adjuntar | 3 → 3 |
| 4004 | Enviar a aprobación | enviar_aprobacion | 3 → 4 |
| 4005 | Eliminar | eliminar | 3 → 6 |
| 4006 | Visualizar | visualizar | 3 → 3 |
| 4007 | Aprobar | aprobar | 4 → 5 |
| 4008 | Volver a Borrador | borrador | 4 → 3 |
| 4009 | Visualizar | visualizar | 4 → 4 |
| 4010 | Nueva adenda | adenda | 5 → 5 |
| 4011 | Adjuntar documento | adjuntar | 5 → 5 |
| 4012 | Renovar | renovar | 5 → 5 |
| 4013 | Suspender | suspender | 5 → 16 |
| 4014 | Finalizar | finalizar | 5 → 21 |
| 4015 | Anular | anular | 5 → 38 |
| 4016 | Visualizar | visualizar | 5 → 5 |
| 4017 | Imprimir | imprimir | 5 → 5 |
| 4018 | Reanudar | reanudar | 16 → 5 |
| 4019 | Finalizar | finalizar | 16 → 21 |
| 4020 | Visualizar | visualizar | 16 → 16 |
| 4021 | Visualizar | visualizar | 21 → 21 |
| 4022 | Imprimir | imprimir | 21 → 21 |
| 4023 | Visualizar | visualizar | 38 → 38 |

**422 Liquidaciones (4041–4060)**

| id | Nombre | accion_js | origen → destino |
|---|---|---|---|
| 4041 | Generar liquidaciones del período | generar | 0 → 3 |
| 4042 | Bases del período (legajos) | bases | 0 → 0 |
| 4043 | Editar (renglones manuales / override) | editar | 3 → 3 |
| 4044 | Recalcular | recalcular | 3 → 3 |
| 4045 | Enviar a revisión | enviar_revision | 3 → 4 |
| 4046 | Eliminar | eliminar | 3 → 6 |
| 4047 | Visualizar | visualizar | 3 → 3 |
| 4048 | Aprobar (lote) | aprobar | 4 → 30 |
| 4049 | Devolver a Borrador | borrador | 4 → 3 |
| 4050 | Visualizar | visualizar | 4 → 4 |
| 4051 | Facturar (lote) | facturar | 30 → 12 |
| 4052 | Volver a revisión | desaprobar | 30 → 4 |
| 4053 | Anular | anular | 30 → 38 |
| 4054 | Visualizar | visualizar | 30 → 30 |
| 4055 | Imprimir | imprimir | 30 → 30 |
| 4056 | Autorizar en ARCA | autorizar_arca | 12 → 12 |
| 4057 | Revertir facturación | revertir_facturacion | 12 → 30 |
| 4058 | Visualizar | visualizar | 12 → 12 |
| 4059 | Imprimir | imprimir | 12 → 12 |
| 4060 | Visualizar | visualizar | 38 → 38 |

**423 Adendas (4081–4094)**

| id | Nombre | accion_js | origen → destino |
|---|---|---|---|
| 4081 | Proponer ajustes por índice (lote) | proponer_ajustes | 0 → 3 |
| 4082 | Editar | editar | 3 → 3 |
| 4083 | Enviar a aprobación | enviar_aprobacion | 3 → 4 |
| 4084 | Eliminar | eliminar | 3 → 6 |
| 4085 | Visualizar | visualizar | 3 → 3 |
| 4086 | Notificar al cliente | notificar | 4 → 4 |
| 4087 | Aplicar | aprobar | 4 → 5 |
| 4088 | Rechazar | rechazar | 4 → 25 |
| 4089 | Volver a Borrador | borrador | 4 → 3 |
| 4090 | Visualizar | visualizar | 4 → 4 |
| 4091 | Adjuntar adenda firmada | adjuntar | 5 → 5 |
| 4092 | Visualizar | visualizar | 5 → 5 |
| 4093 | Imprimir | imprimir | 5 → 5 |
| 4094 | Visualizar | visualizar | 25 → 25 |

**424 Índices (4101–4107)**: Agregar `agregar` 0→1 · Editar `editar` 1→1 · Cargar valores `valores` 1→1 · Importar valores `importar` 1→1 · Inactivar `inactivar` 1→2 · Activar `activar` 2→1 · Visualizar `visualizar` 1→1.

**425 Profesionales (4121–4126)**: Agregar `agregar` 0→1 · Editar `editar` 1→1 · Categorías `categorias` 0→0 · Inactivar `inactivar` 1→2 · Activar `activar` 2→1 · Visualizar `visualizar` 1→1.

### 3.5 Diagramas de transición

```
CONTRATO (450)
 [agregar] → 3 Borrador ──enviar_aprobacion──► 4 Pend. Aprobación ──aprobar──► 5 Vigente
     │  ▲                                   │                                   │ │ │ │
     │  └──────────────borrador─────────────┘                                   │ │ │ └─adenda/renovar/adjuntar (5→5)
     └─eliminar─► 6 Eliminado                       5 ──suspender──► 16 Suspendido ──reanudar──► 5
                                                    5 ──finalizar──► 21 Finalizado ◄──finalizar── 16
                                                    5 ──anular────► 38 Anulado (sin liquidaciones vivas)

ADENDA (452)
 [proponer_ajustes | adenda desde 421] → 3 Borrador ──enviar_aprobacion──► 4 Pend. Aprobación
     3 ──eliminar──► 6                     4 ──notificar (4→4, registra f_notificacion)
                                           4 ──aprobar──► 5 Aplicada   (exige f_notificacion en MODIFICACION/AJUSTE)
                                           4 ──rechazar─► 25 Rechazada
                                           4 ──borrador─► 3

LIQUIDACIÓN (454)
 [generar] → 3 Borrador ──enviar_revision──► 4 En Revisión ──aprobar (lote, numera E_LIQ)──► 30 Aprobada
   3 ──recalcular/editar (3→3)              4 ──borrador──► 3                         │   │
   3 ──eliminar──► 6                                                                   │   ├─desaprobar──► 4
                                                                                       │   └─anular──► 38
                        30 ──facturar (lote: crea factura en ventas_facturas)──► 12 Facturada
                        12 ──autorizar_arca (12→12, reintenta CAE)
                        12 ──revertir_facturacion──► 30  (solo si la factura está en 3, se elimina, o en 6)
```

Efectos laterales de las transiciones del contrato (en la misma transacción):
- `aprobar` 4→5: valida entidad > 0, al menos una línea, `concepto_ajuste_id` si hay mínimo, una sola línea HORA entre los contratos vigentes del cliente y, si D-07 lo confirma, el documento firmado. Numera con E_CTR. Crea la adenda **v1 ALTA ya aplicada (5)** con la foto de la cabecera, pone `contrato_version = 1` y calcula `ajuste_proximo_periodo = ajuste_periodo_base + frecuencia`.
- `suspender` 5→16: pide `f_suspension` y motivo y crea la adenda SUSPENSION aplicada (`f_efecto`). `reanudar`: lo mismo con REANUDACION. Esas adendas no versionan líneas: copian la versión y sirven para el prorrateo.
- `finalizar`: pide `f_baja` (≥ fin del último período liquidado en MES_VENCIDO) y crea la adenda FINALIZACION. En MES_ADELANTADO, si el período de la baja ya está facturado, genera la liquidación CIERRE (§4.6).
- `anular` 5→38: solo si no hay liquidaciones en 3/4/30/12.
- `adenda` (5→5): crea una adenda MODIFICACION en Borrador con `version = contrato_version + 1` y copia las líneas vigentes como líneas nuevas con `version_desde = N+1`, que el usuario edita. Las líneas de la versión anterior no se tocan hasta aplicar.
- `renovar` (5→5): crea una adenda RENOVACION (nueva `f_fin`), sin cambio de importes.

### 3.6 Permisos

Se asignan en **`conf__perfiles_funciones`** (perfiles base 401/402/403) **y** en **`conf__empresas_perfiles_funciones`** (cada `conf__empresas_perfiles` del módulo 4 según su `perfil_id_base`; "Acceso total" tiene base 401). Script 03.

| Perfil | Funciones |
|---|---|
| 401 Administrador Estudio Contable (+ "Acceso total") | todas (4001–4126) |
| 402 Contador / Liquidador | Contratos: 4001–4006, 4009–4011, 4016, 4017, 4020–4023. Liquidaciones: 4041–4047, 4050, 4054, 4055, 4058–4060. Adendas: 4081–4086, 4090, 4092–4094. Índices: 4101–4104, 4107. Profesionales: 4126. **No** aprueba, factura, anula, suspende ni finaliza. |
| 403 Auditor Fiscal | solo visualizar e imprimir (4006, 4009, 4016, 4017, 4020–4023, 4047, 4050, 4054, 4055, 4058–4060, 4085, 4090, 4092–4094, 4107, 4126) |

Facturar no exige permiso sobre la página 56/556 (el modelo se llama directo), pero ver, cobrar o anular la factura sí, porque eso se hace en ventas_facturas. Además, en la escritura se exige acceso a la sucursal y al PV (`AccesosSucursalesHelper::exigirAccesoSucursal` / `exigirAccesoPuntoVenta`) del usuario que factura.

---

## 4. Algoritmo de liquidación

### 4.1 Generación ("Generar liquidaciones del período", 0→3)

Entrada: `periodo_emision E`. Para cada contrato de la empresa en 5 o 16 (y en 21 si `f_baja` cae dentro del período a liquidar):

1. `P = E − 1` si es MES_VENCIDO, `P = E` si es MES_ADELANTADO (con la modalidad de la versión vigente).
2. Se saltea si ya hay una liquidación viva `(contrato, P, MENSUAL)`: lo garantiza la UNIQUE, y el INSERT que choca se informa como "ya liquidado".
3. Se saltea con aviso si `P` es anterior al mes de `f_inicio`, posterior a `f_fin` (y no hay renovación: ver 4.9) o posterior a `f_baja`.
4. `V` = versión vigente para P (§2.4). Se calcula cada línea (§4.3) y después el prorrateo, el mínimo y el máximo (§4.4–4.5), el TC (§4.8) y los totales.
5. Se inserta la cabecera (estado 3, `comprobante_nro = 0`, `f_emision = DATE(E, dia_emision)`) con los detalles, y su espejo en `gestion__comprobantes` (§6.4).

Cada contrato va en su propia transacción. Un error en uno no frena el lote: se devuelve `{success, data: {generadas, salteadas:[{contrato, motivo}], errores:[...]}, message}`.

`recalcular` (3→3) borra los detalles no manuales y repite los pasos 4 y 5 sobre la misma cabecera. Los renglones `editado_manual = 1` / `MANUAL` se conservan.

### 4.2 Fuentes de datos (todas filtradas por empresa)

| Dato | Cliente en el ERP (`empresa_vinculada_id > 0`) | Cliente sin ERP |
|---|---|---|
| Acceso | **`vinculacionesVerificarPermiso($c, $empresa_vinculada, $empresa_estudio, 'ventas'|'compras', false)`**. Sin vínculo vigente: la línea queda con `cantidad_medida = 0`, `requiere_revision = 1` y el aviso "sin autorización de acceso a los datos de la empresa X". | — |
| Comprobantes de venta del período | `gestion__comprobantes c JOIN gestion__comprobantes_tipos ct` con `c.empresa_id = vinculada`, `c.tabla_origen_id = 80`, `ct.comprobante_fiscal_id > 0`, `c.tabla_estado_registro_id IN (5,39,40)`, `c.f_emision` dentro de P | `estudio__minigestion_comprobantes` con `empresa_id = estudio`, `cliente_id`, `tipo_operacion = 'VENTA'`, `periodo_fiscal = P`, `tabla_estado_registro_id = 1` |
| Comprobantes de compra | igual, con `tabla_origen_id = 84` y `c.f_contabilidad` dentro de P (mismo criterio que la Posición IVA) | `tipo_operacion = 'COMPRA'` |
| Ventas netas | `SUM(CASE ct.signo WHEN '-' THEN -1 ELSE 1 END × (c.importe_neto + c.importe_no_gravado + c.importe_exento) × c.tipo_cambio)` | `SUM(signo del tipo × (neto_gravado + no_gravado_exento))` |
| Legajos | `estudio__clientes_legajos_periodos` (§4.3 LEGAJO) | igual |
| Horas | `estudio__tareas_tiempos t JOIN estudio__tareas ta ON ta.tarea_id = t.tarea_id` con `t.empresa_id = estudio`, `ta.cliente_id`, `t.liquidacion_id IS NULL` y `t.f_registro ≤ fin de P` (incluye horas atrasadas, que se informan aparte) | igual |
| Valor de índice o módulo | `estudio__indices_valores` del período pedido; si falta, el último `periodo ≤` pedido, con aviso | igual |

### 4.3 Por modalidad (importes en la moneda del contrato)

- **FIJO**: se emite si `frecuencia = 1` o si `(P − periodo_desde)` en meses es múltiplo de `frecuencia`, y siempre dentro de `[periodo_desde, periodo_hasta]`. `importe_calculado = importe`. Prorratea solo si `prorratea = 1`, o sea las mensuales.
- **EVENTUAL**: igual que FIJO con desde = hasta. No prorratea y no entra en mínimo ni máximo.
- **VOLUMEN**: `n` = cantidad de comprobantes según `fuente_operaciones`, del período base (§4.6). Con los tramos ordenados:
  - `PROGRESIVA` (por defecto, el ejemplo pedido): cada comprobante se cobra al precio de su tramo. Ejemplo con 0–50 a $0, 51–200 a $X y 201+ a $Y: `max(0,min(n,200)−50)·X + max(0,n−200)·Y`. Se graba un renglón de detalle por tramo con cantidad > 0.
  - `TRAMO_ALCANZADO`: todos los comprobantes al precio del tramo en que cae `n`.
  - `MONTO_TRAMO`: el importe del tramo alcanzado como monto fijo.
- **PORCENTAJE**: la base es la venta neta del período base (`PERIODO`) o el promedio de los últimos `meses_promedio` períodos que terminan en el período base (`PROMEDIO_MOVIL`; los meses usados quedan en `base_detalle`). `importe = base × porcentaje / 100`, acotado a `[piso, tope]`. Si la base es negativa (NC > ventas), queda en 0 con aviso.
- **LEGAJO**: `legajos` = fila de `estudio__clientes_legajos_periodos` del período base. Si no existe, en este orden:
  - (a) si D-03 habilita la fuente sindical y hay vínculo, se lee `sindi__ddjj` (estado 28, mayor `secuencia`) del empleador con el CUIT del cliente y se **materializa** con origen `SINDI_DDJJ`;
  - (b) si no, se materializa `estudio__clientes.cantidad_empleados` con origen `CLIENTE_CANTIDAD` y `requiere_revision = 1`.
  `importe = max(0, legajos − cantidad_incluida) × importe`.
  **Hoy no existe F931 ni sueldos**: la fuente real es la carga manual del período (función 4042) o el fallback (b).
- **HORA**: las horas sin liquidar del cliente (§4.2) se agrupan por la categoría vigente del usuario en `f_registro` (`estudio__profesionales`). Las horas incluidas se descuentan **en orden cronológico** (D-08). El excedente de cada categoría se multiplica por la tarifa CATEGORIA de la escala de la línea. Si un usuario no tiene categoría o la categoría no tiene tarifa, sus horas quedan a $0 con `requiere_revision`. Se graba un renglón por categoría. Al **aprobar** la liquidación se marcan los tiempos usados (`liquidacion_id`); al anularla o al volver a 4/3 se desmarcan.
- **MODULOS**: `importe = cantidad_modulos × valor(indice_id, P)`. El valor es el del período P o, si falta, el último cargado, con aviso. Si el módulo está en otra moneda, se convierte con el TC de la liquidación.

`importe_calculado` se redondea a 2 decimales por renglón.

### 4.4 Prorrateo de alta, baja y suspensión

`dias_periodo` = días del mes de P. Los `dias_servicio` son los días de P dentro de `[max(f_inicio, 1° de P), min(f_baja ó f_fin, último de P)]`, menos los días suspendidos (intervalos SUSPENSION → REANUDACION de las adendas aplicadas). `factor_prorrateo = dias_servicio / dias_periodo` (6 decimales).
Se aplica a: FIJO con `prorratea = 1`, al mínimo y al máximo. **No** se aplica a las modalidades medidas (VOLUMEN, PORCENTAJE, LEGAJO, HORA): esas ya miden lo que pasó en el período. MODULOS: se prorratea solo si la línea tiene `prorratea = 1`.

### 4.5 Mínimo y máximo mensual

Se aplican sobre la suma "mensual" `S` = renglones de la liquidación excepto EVENTUAL y FIJO con `frecuencia > 1` (esos son honorarios extra, no abono). Con `min' = minimo × factor` y `max' = maximo × factor`:
- Si `S < min'`: renglón `AJUSTE_MINIMO` con `importe = min' − S` y el concepto `concepto_ajuste_id` (su producto da el IVA).
- Si `S > max'`: el excedente `S − max'` se **reparte proporcionalmente** entre los renglones mensuales (`ajuste_tope` ≤ 0) y el resto de redondeo va al renglón mayor. Así no quedan renglones negativos en la factura y el IVA por alícuota sale bien.

### 4.6 Mes vencido y mes adelantado

- **MES_VENCIDO**: se emite en E = P+1. Todas las líneas usan los datos de P.
- **MES_ADELANTADO**: se emite en E = P. Las líneas fijas (FIJO, MODULOS, EVENTUAL) cobran P por adelantado. Las **medidas** (VOLUMEN, PORCENTAJE, LEGAJO, HORA) no se pueden medir por adelantado y cobran **P−1** (`periodo_base = P−1`). En la primera liquidación del contrato las medidas no se emiten (no hay mes anterior bajo contrato), y la liquidación CIERRE del final las recupera (ver abajo).
- **Baja a mitad de mes en MES_ADELANTADO**: P ya se facturó completo. `finalizar` genera la liquidación `CIERRE` del período de la baja con:
  - (a) los renglones fijos prorrateados en negativo `−(1 − factor) × importe`;
  - (b) las medidas del último mes.
  Si el neto da negativo **no se factura automáticamente**: queda Aprobada con aviso "emitir NC en Facturación asociada a la factura del período", porque la NC necesita comprobante asociado y ARCA todavía no la soporta (D-09). Si da positivo, se factura como cualquier otra.

### 4.7 Ajuste periódico por índice y adenda ("nada se ajusta en silencio")

"Proponer ajustes por índice" (423, 0→3) recibe el período E y toma cada contrato vigente con `indice_id`, `ajuste_frecuencia_meses > 0` y `ajuste_proximo_periodo ≤ E`. Para cada uno:

1. `pb = ajuste_periodo_base − rezago`, `pa = ajuste_proximo_periodo − rezago`. Si falta `valor(pb)` o `valor(pa)`, no se propone y queda en la lista "falta el índice de MM/AAAA".
2. `coef = valor(pa) / valor(pb)`. Si hay `ajuste_tope_pct`, el coeficiente se limita a `1 + tope/100`. Si `coef ≤ 1`, no se ajusta: se registra la adenda igual, con coeficiente 1 y "sin variación", para que quede trazado.
3. Se crea la adenda AJUSTE_INDICE en **Borrador** con `version = contrato_version + 1`, `periodo_vigencia_desde = ajuste_proximo_periodo`, foto del índice (`indice_*`, `coeficiente`) y cabecera. A cada línea vigente con `ajustable = 1` se le hace una línea nueva `version_desde = N+1` con `importe`, `piso`, `tope` y escalas × coef, redondeadas a 2 decimales y con `linea_origen_id`. Las no ajustables se copian igual. El mínimo y el máximo de la foto también se multiplican.
4. Se arma `resumen` (antes → después por línea, coeficiente, índice y períodos) para la notificación.

Después: Enviar a aprobación (3→4) → **Notificar al cliente** (4→4: imprime o manda el resumen y registra `f_notificacion`, medio y usuario) → **Aplicar** (4→5). Aplicar valida que haya `f_notificacion`, que `periodo_vigencia_desde > ultimo_periodo_liquidado` (si no, hay que hacerlo retroactivo: D-10) y que no haya otra adenda aplicada con la misma versión. En la misma transacción:
- `version_hasta = N` en las líneas reemplazadas;
- en el contrato: `contrato_version = N+1`, cabecera actualizada con la foto, `ajuste_periodo_base = ajuste_proximo_periodo` y `ajuste_proximo_periodo += frecuencia`;
- las liquidaciones en 3 de períodos ≥ `periodo_vigencia_desde` se recalculan. Si alguna está en 4, la aplicación se rechaza con "devuelva a Borrador la liquidación X". Si hay una en 30/12, ya lo frenó la validación anterior.

Una MODIFICACION manual (desde 421) sigue el mismo circuito y también exige la notificación.

### 4.8 Moneda y tipo de cambio

- La moneda del contrato es la base (ARS) o una moneda de la empresa con código USD (`emp_moneda_por_codigo($c,$e,'USD')`). El script **no** da de alta el USD: lo hace el usuario en Monedas.
- TC: `tc_fuente = COTIZACION_MONEDA` toma `gestion__monedas.cotizacion_actual` de esa moneda y empresa. En Borrador es **estimado** (se muestra como tal) y se vuelve a tomar al **aprobar**: esa es la foto (`tipo_cambio`, `f_tipo_cambio`). `MANUAL` = el revisor lo carga en la liquidación antes de aprobar. Moneda base: TC 1.
- Factura: **en pesos** (ARCA solo autoriza PES). Cada renglón va a `importe_final × TC`, redondeado por renglón, y las observaciones de la factura dicen "Honorarios USD n,nn a TC x,xxxx del dd/mm/aaaa". Facturar en USD con `MonId DOL` sería otro cambio en ARCA (D-04).
- No hay historial de cotizaciones: la única foto es la de la liquidación. Si se quiere "el TC BNA del día de emisión" auditable, hace falta una tabla `gestion__monedas_cotizaciones` (D-04).

### 4.9 Renovación y vencimiento

Al generar, si `f_fin < 1° de P`:
- con `renovacion_automatica = 1`: se crea y **aplica** sola una adenda RENOVACION (`f_fin += renovacion_meses`, mismos importes) y la liquidación lleva el aviso "contrato renovado automáticamente, notificar". Como no cambia el precio, no es un ajuste silencioso. La notificación queda pendiente en la adenda;
- sin renovación automática: no se liquida, y el aviso es "contrato vencido: renovar o finalizar".

El aviso de preaviso (`preaviso_dias`) aparece en el listado de contratos.

---

## 5. Concurrencia y no-doble-facturación

1. **UNIQUE `uk_hli_periodo`**: una sola liquidación viva por contrato, período y tipo, aunque dos usuarios generen al mismo tiempo. El segundo INSERT falla con 1062 y se informa como "ya liquidado".
2. Cada transición usa **UPDATE condicional**: `... SET estado = destino WHERE liquidacion_id = ? AND empresa_id = ? AND tabla_estado_registro_id = origen`. Si `affected_rows ≠ 1`, se lanza "la liquidación cambió de estado".
3. Facturar: `SELECT ... FOR UPDATE` de la liquidación (estado 30 y `venta_factura_id IS NULL`) **dentro de la misma transacción** que crea la factura (§6.3). Después, `UPDATE ... SET venta_factura_id = ?, estado = 12 WHERE ... AND estado = 30 AND venta_factura_id IS NULL`. **UNIQUE `uk_hli_factura`** impide que dos liquidaciones apunten a la misma factura.
4. Locks en orden `liquidacion_id ASC` dentro de un lote. Los lotes se procesan de a una liquidación por transacción: un error no deja el resto a medias.
5. Horas: `UPDATE estudio__tareas_tiempos SET liquidacion_id = ? WHERE tiempo_id IN (...) AND liquidacion_id IS NULL` al aprobar. Si alguna fila ya estaba marcada (otra liquidación aprobada en paralelo), se hace rollback.
6. ARCA: después del commit, una factura a la vez, en orden `punto_venta_id, comprobante_tipo_id, venta_factura_id`. Ya existe el `GET_LOCK` por PV+tipo de `vfArcaAutorizarFactura`.
7. `ultimo_periodo_liquidado` del contrato se actualiza al aprobar (`GREATEST`) y se recalcula al anular o desaprobar (`MAX` de las aprobadas o facturadas).

---

## 6. Integración con gestion (lo que hay que construir o refactorizar)

### 6.1 Cliente del estudio ↔ entidad

Una función nueva, `estudio_cliente_entidad($c, $empresa_id, $cliente_id, $usuario_id): int`, en un archivo nuevo `modules/estudio_contable/honorarios_core_model.php`. Corre dentro de la transacción del llamador:
1. Si `estudio__clientes.entidad_id > 0` y esa entidad es de la misma empresa, la devuelve.
2. Si no, busca `gestion__entidades` por `empresa_id` + `cuit` (bigint, sin guiones). Si la encuentra, pone `es_cliente = 1`.
3. Si no existe, la crea (`entidad_nombre = razon_social`, `es_cliente = 1`, `fecha_alta`, `usuario_alta`) con su **condición fiscal** (`gestion__entidades_condiciones_fiscales`: `condicion_iva_id` del cliente, `f_desde` = hoy) y su **sucursal N° 1 "Casa Central"** (`gestion__entidades_sucursales`, la convención de sindicatos).
4. Si la entidad existe pero no tiene condición fiscal vigente, se la carga. Sin eso `vfObtenerRelacionFiscal` la toma como Consumidor Final y emite B a un RI, que es el bug actual.
5. Graba `estudio__clientes.entidad_id`.

Se llama al **enviar a aprobación** el contrato y al facturar (por si cambió el CUIT). Hay que lockear la fila del cliente (`FOR UPDATE`) para no crear la entidad dos veces.

### 6.2 Refactor de `ventas_facturas_model.php` (módulos 2 **y** 5, sincronizar a mano: ver `.agents/rules/gestion_normal_sync.md`)

| # | Cambio | Para qué |
|---|---|---|
| R1 | Sacar `vfValidarComprobantesAnterioresConCae` al nivel superior del archivo | Bug actual (§1.1). Sin esto no se puede confirmar más de una factura por request, y ARCA "Aprobada" falla. |
| R2 | `vfInsertarFactura($c, $empresa_id, $pagina_id, $data, $usuario_id): int`: el cuerpo de `guardarVentaFactura` **sin** begin/commit/rollback, lanzando `Exception`, con usuario explícito. `guardarVentaFactura` queda como envoltorio (begin + vfInsertarFactura + commit, catch rollback). Corrige el `return` sin rollback. | Crear la factura dentro de la transacción del estudio, junto con el lock de la liquidación |
| R3 | `vfAplicarTransicion($c, array $factura, $accion_js, $empresa_id, $pagina_id, $usuario_id): array`: el cuerpo del `try` de `vfEjecutarTransicionEstado` (camino no ARCA y `eliminar`) sin transacción. `vfEjecutarTransicionEstado` queda como envoltorio. | Confirmar la factura en la misma transacción (opción D-06) y eliminar la factura en Borrador al revertir. |
| R4 | `$_SESSION['usuario_id'] ?? 0` → parámetro `$usuario_id` (con default de sesión en los envoltorios) | Desde el estudio el usuario es `$ctx['usuario_id']`. |
| R5 | `vfPaginaFacturasEmpresa($c, $empresa_id): int`: `pagina_id` de `ventas_facturas.php` en un módulo 2 o 5 activo de la empresa (`conf__empresas_modulos` estado 1), o excepción "la empresa no tiene el módulo de Gestión". | `vfTiposPorPuntoVenta`, `vfEstadoInicial` y la búsqueda de funciones dependen de la página. No se fija 56 ni 556. |
| R6 | Concepto ARCA de servicios: columnas `arca_concepto`, `f_servicio_desde`, `f_servicio_hasta` (script 05). `vfInsertarFactura` las acepta en `$data`; `vfArcaDatosFactura` manda `concepto = arca_concepto` y, si es 2 o 3, `FchServDesde`, `FchServHasta` y `FchVtoPago` (= `f_vto` o `f_emision`). `arca_wsfe_solicitar_cae` (`arca/arca_lib.php`) agrega esos tres tags cuando vienen. La impresión muestra "Período facturado". | Los honorarios son concepto 2: hoy ARCA los recibiría como productos. |
| R7 | `syncComprobante` no cambia. | |

Funciones que se reutilizan **tal cual**: `vfObtenerRelacionFiscal`, `vfTiposPorPuntoVenta`, `vfEstadoInicial`, `vfCalcularDetallesFactura` (la llama `vfInsertarFactura`), `vfObtenerProximoNumero`, `vfArcaHabilitado`, `vfComprobanteFiscalArca`, `vfArcaAutorizarFactura` (después de R1), `vfGenerarAsientoContableFactura` (la llaman R3 / ARCA), `vfPercepcionesRecalcular`, `syncComprobante`, `obtenerTablaOrigenPorPagina`.

### 6.3 Facturar una liquidación (30 → 12)

`estudio_liq_facturar($c, $empresa_id, $usuario_id, $liquidacion_id, bool $confirmar): array`. Va en `honorarios_liquidaciones_model.php`, que hace `require_once` de `../gestion/ventas_facturas_model.php` (como hoy `honorarios_facturacion_model.php`):

```
BEGIN
  liq = SELECT ... FROM estudio__honorarios_liquidaciones WHERE id=? AND empresa_id=? FOR UPDATE
  exigir liq.estado = 30 AND liq.venta_factura_id IS NULL AND liq.importe_neto_local > 0
  contrato = versión liq.contrato_version (PV, condición de pago)
  AccesosSucursalesHelper::exigirAccesoSucursal(c, empresa, sucursal del PV, usuario)
  AccesosSucursalesHelper::exigirAccesoPuntoVenta(c, empresa, pv, usuario)
  entidad = estudio_cliente_entidad(...)
  pagina_vf = vfPaginaFacturasEmpresa(c, empresa)                       (R5)
  tipos = vfTiposPorPuntoVenta(c, empresa, pv, pagina_vf, entidad, true)
  tipo  = el fiscal con es_sugerido = 1; si el PV no tiene fiscales, el único no fiscal; si no, error "configure tipos en el PV"
  detalles: un renglón por detalle con importe_final_local ≠ 0, agrupados por (producto_id, iva_alicuota_id, descripcion):
            producto_id, cantidad 1, precio_unitario = importe_final_local, iva_alicuota_id e iva_porcentaje del
            PRODUCTO (gestion__productos.iva_alicuota_id → gestion__impuestos__iva_alicuotas.porcentaje; si el tipo no es
            fiscal, vfCalcularDetallesFactura lo deja en 0)
  data: sucursal_id, punto_venta_id, comprobante_tipo_id, entidad_id, condicion_pago_id,
        f_emision = hoy si |liq.f_emision − hoy| > VF_ARCA_DIAS_FECHA, si no liq.f_emision
        f_vto según la condición de pago, moneda = emp_moneda_base_id, tipo_cambio 1,
        arca_concepto = 2, f_servicio_desde/hasta = período de servicio (CIERRE: el tramo devuelto)
        observaciones = "Honorarios {MM/AAAA} - Contrato N° {E_CTR} - Liquidación N° {E_LIQ}" (+ leyenda de TC si USD)
  factura_id = vfInsertarFactura(c, empresa, pagina_vf, data, usuario)    (R2: estado inicial de la página = 3)
  UPDATE liquidacion SET venta_factura_id = factura_id, estado = 12, facturado_* WHERE id=? AND estado=30 AND venta_factura_id IS NULL
      → affected = 1 o excepción
  estudio_liq_sync_comprobante(...)                                       (espejo de la liquidación pasa a 12)
  si confirmar: vfAplicarTransicion(c, factura, 'confirma', empresa, pagina_vf, usuario)   (R3; fiscal + ARCA → queda en 4)
COMMIT  (catch: ROLLBACK, guardar error_facturacion en una transacción aparte)
si confirmar y es fiscal con ARCA habilitado: vfArcaAutorizarFactura(...) fuera de la transacción (R1)
     → error: la liquidación queda 12 con error_facturacion; se reintenta con 4056 "Autorizar en ARCA"
```

`revertir_facturacion` (12→30), en una transacción: con la factura en 3, `vfAplicarTransicion(..., 'eliminar')` y desvincular; con la factura en 6, solo desvincular; con la factura en 4/5/39/40, error "anule la factura con una nota de crédito en Facturación".

### 6.4 Espejo de la liquidación en `gestion__comprobantes`

`estudio_liq_sync_comprobante()` llama a `syncComprobante($c, $data, 454)` con `comprobante_tipo_id = E_LIQ`, `comprobante_pv` = id del PV, `entidad_id`, importes en moneda base y `tabla_estado_registro_id` = el estado de la liquidación. Después, en la misma transacción: `UPDATE gestion__comprobantes SET modulo = 'estudio', importe_pendiente = 0 WHERE comprobante_id = ?`. Es un comprobante interno: no se cancela ni va a la cuenta corriente (el tipo no está en `gestion__comprobantes_tipos_cancelaciones`) ni a IVA (`comprobante_fiscal_id = 0`). Al pasar a 12 queda con el número interno, y la factura real tiene su propio espejo (tabla 80).

### 6.5 Tipos de comprobante internos (script 04)

Para cada empresa con módulo 4 en `conf__empresas_modulos`, por código y nunca por id: grupo `EST` "Estudio Contable", subgrupos `HCTR` (tabla 450) y `HLIQ` (tabla 454) con `origen_asiento = 'NINGUNO'`, y tipos `E_CTR` "Contrato de Honorarios" y `E_LIQ` "Liquidación de Honorarios" con `comprobante_fiscal_id = 0`, `impacta_* = 0` y `signo '+'`. Se resuelven con `emp_comprobante_tipo_id($c, $e, 'E_CTR')`. La numeración usa `gestion__comprobantes_numeradores`, que no requiere habilitar el tipo en el PV. **El asistente de inicio de empresa (`inicioMapearSubgruposComprobante`) los copia por código**: verificar que copie los subgrupos con `tabla_id` 450/454.

---

## 7. Páginas: comportamiento de la UI (sin código)

- **421 Contratos**: listado con filtros (cliente, estado, vence en N días, próximo ajuste) y formulario master-detail: cabecera, líneas con un editor por modalidad (tramos, tarifas por categoría), precarga desde el tarifario vigente y solapas de Adendas y Documento. En Vigente el formulario es de solo lectura: todo cambio pasa por "Nueva adenda".
- **422 Liquidaciones**: selector de período de emisión, botón Generar, grilla con selección múltiple para las acciones de lote (Enviar a revisión, Aprobar, Facturar) y detalle con la foto completa: fuente, cantidades, tramos, horas por categoría, índice, TC y avisos. Las que tienen `requiere_revision = 1` aparecen marcadas y no se aprueban en lote sin confirmación explícita. Solapa "Bases del período": grilla de clientes empleadores × legajos del período (carga manual / pegar planilla).
- **423 Adendas**: bandeja de ajustes pendientes (contratos con `ajuste_proximo_periodo ≤ E`), Proponer, resumen imprimible para el cliente, Notificar, Aplicar.
- **424 Índices**: ABM de series + grilla de valores por período + importación CSV (período;valor).
- **425 Profesionales**: categorías (solapa) + usuario → categoría con vigencia.

Todas responden `{success, data, message}`.

---

## 8. Migración de abonos y novedades (script 06)

Solo corre donde existen `estudio__clientes_abonos` / `_novedades`. Hoy, en la base local, con 0 filas; en producción no hay tablas `estudio__*`. Es reanudable porque usa `legacy_ref`:
- Un **contrato en Borrador** por `(empresa, cliente)` con abonos activos (`legacy_ref = 'ABONOS:{cliente_id}'`):
  - PV, condición de pago y día de emisión del abono de menor id;
  - moneda base de la empresa;
  - modalidad `MES_ADELANTADO` (la facturación vieja emitía "Período X" con fecha del mes X), a revisar;
  - `f_inicio` = 1° del mes siguiente a `ultimo_periodo_facturado`, o el mes actual;
  - `ultimo_periodo_liquidado = MAX(ultimo_periodo_facturado)`;
  - `entidad_id` por CUIT si la entidad existe (si no, 0, y se resuelve al enviar a aprobación).
- Por abono:
  - línea FIJO (`importe_base`, `ABONO:{id}:FIJO`);
  - si `aplica_adicionales_automaticos = 1` y `precio_excedente_legajo > 0`, línea LEGAJO (`cantidad_incluida = tope_legajos_incluidos`);
  - si `precio_excedente_comprobante > 0`, línea VOLUMEN PROGRESIVA (fuente VENTAS) con tramos `[0, tope] = 0` y `[tope+1, ∞) = precio`.
- Novedades con `facturado = 0` y estado 1 de clientes con contrato migrado: línea EVENTUAL con `periodo_desde = periodo_hasta = periodo` (`NOVEDAD:{id}`). Las de clientes sin abono se **listan** y no se migran.
- Al final, conteos de control: abonos y contratos, líneas, novedades pendientes no migradas.

Los contratos migrados quedan en Borrador para revisión. No se aprueban solos.

---

## 9. Scripts (orden de ejecución)

| Script | Qué hace | Producción |
|---|---|---|
| `20261015_estudio_contratos_01_estructura.sql` | CREATE de las 11 tablas + ALTER condicionales de `estudio__clientes`, `estudio__tareas_tiempos`, `estudio__honorarios_tarifarios_detalles` | Crea las tablas nuevas; los ALTER se saltean porque no existen las tablas viejas |
| `..._02_configuracion.sql` | `conf__tablas` 450–460, estados 2247–2280, páginas 421–425, funciones 4001–4126 y verificación de choques de ids | Sí |
| `..._03_permisos.sql` | `conf__perfiles_funciones` + `conf__empresas_perfiles_funciones` | Sí (sin perfiles del módulo 4 no inserta nada) |
| `..._04_comprobantes_y_semillas.sql` | Grupo, subgrupos y tipos E_CTR / E_LIQ por empresa con módulo 4, y categorías SOCIO / SENIOR / JUNIOR | Sí (sin empresas con módulo 4 no inserta nada) |
| `..._05_ventas_facturas_servicios.sql` | `gestion__ventas_facturas`: `arca_concepto`, `f_servicio_desde`, `f_servicio_hasta` | **Sí: es tabla de gestión**. Correrlo junto con R6. |
| `..._06_migracion_abonos.sql` | §8 | Se saltea |
| `..._07_baja_abonos.sql` | Página 406 inactiva, sus funciones inactivas, tablas 407/408 inactivas en `conf__tablas`. **Correr después de desplegar el código** de las páginas 421–425. No borra datos. | Se saltea |

Todos usan `SET NAMES utf8mb4`, son reanudables, tienen ids explícitos y `WHERE NOT EXISTS`, no usan JSON ni `ADD INDEX IF NOT EXISTS`, y sus ALTER son condicionales vía `information_schema` + `PREPARE`.

---

## 10. Validación del diseño contra las convenciones

- Los 8 campos están en las dos tablas de proceso largo (contratos y liquidaciones). Las adendas (proceso corto) dependen del contrato y no los llevan: la numeración y la entidad son las del contrato.
- `tabla_estado_registro_id` guarda ids genéricos, cada uno con su significado. No hay enums de estado dentro de las tablas: los `enum` que hay son de modalidad o configuración, no de estado.
- Materialización en lugar de cálculo en caliente: `ultimo_periodo_liquidado`, `ajuste_proximo_periodo`, `contrato_version`, `estudio__clientes_legajos_periodos`, `tareas_tiempos.liquidacion_id`, los totales de la liquidación y la foto completa por detalle.
- No hay ids fijos de empresa: PV, moneda, tipos, alícuota y cuentas salen de `core/empresa_config.php`, de la relación fiscal o del producto. La página de facturas se resuelve con R5.
- El filtro `empresa_id` está en todas las tablas e índices. El acceso al ERP del cliente pasa por `vinculacionesVerificarPermiso`.
- Dialecto corto: `honorarios_contratos`, `_lineas`, `_escalas`, `_adendas`, `honorarios_liquidaciones`, `_detalles`; columnas `f_*`, `comprobante_*`.

---

## 11. Decisiones abiertas (a confirmar por el usuario)

| # | Decisión | Propuesta |
|---|---|---|
| **D-01** | **Bloques de ids** | `conf__tablas` 450–469, `conf__paginas` 421–425, `conf__paginas_funciones` 4001–4199, `conf__tablas_estados_registros` 2247–2299. Agregarlos a la tabla de bloques del CLAUDE.md. **No correr los scripts sin confirmarlo.** |
| D-02 | Vigente = 5 Confirmado y Finalizado = 21 Cumplido | §3.1. La alternativa es 1 Activo / 2 Inactivo. |
| D-03 | Fuente de legajos | Hoy: carga manual por período + fallback a `cantidad_empleados` con revisión. No hay F931 ni sueldos. Leer `sindi__ddjj` del tenant de un sindicato requiere definir quién consiente (el empleador es el dueño del dato, el sindicato es el custodio) y una vinculación sindicato → estudio con `permiso_sindicatos`. Propuesta: no habilitarlo en v1 y, más adelante, importar el F931 (SICOSS) que genera el propio estudio, con origen `F931`. |
| D-04 | Moneda extranjera | Facturar siempre en pesos, con TC de `cotizacion_actual` fotografiado al aprobar. Si hace falta TC histórico auditable (BNA del día), crear `gestion__monedas_cotizaciones` (es de gestión, no del estudio). Facturar en USD con ARCA es otro proyecto. |
| D-05 | Espejos en `gestion__comprobantes` | Liquidación **sí** (E_LIQ, `importe_pendiente = 0`, `modulo = 'estudio'`). Contrato **no** (no tiene importe, como `estudio__tareas`). Confirmar si el contrato también debe tenerlo. |
| D-06 | Facturar = crear en Borrador o crear y confirmar | Propuesta: un parámetro del lote "Facturar y confirmar" (por defecto sí). Con ARCA habilitado, la factura se autoriza enseguida y en orden. Sin confirmar, queda en Borrador para revisarla en Facturación. |
| D-07 | Documento firmado obligatorio para aprobar el contrato | Propuesta: sí (`documento_archivo` + `f_firma`). |
| D-08 | Horas incluidas: cómo se descuentan | Cronológico. Alternativas: primero las de menor tarifa (conviene al estudio) o las de mayor tarifa (conviene al cliente). |
| D-09 | Baja a mitad de mes en MES_ADELANTADO con saldo a favor del cliente | Liquidación CIERRE y NC manual en Facturación. La NC automática necesita comprobante asociado + `CbtesAsoc` en ARCA (pendiente según CLAUDE.md). |
| D-10 | Ajustes retroactivos | Propuesta v1: no permitidos (vigencia > último período liquidado). Alternativa: renglón "Diferencia por ajuste" en la próxima liquidación. |
| D-11 | Índices: por empresa o globales | Por empresa (regla de catálogos). IPC/CER se cargan a mano o por CSV: no hay servicio de INDEC/BCRA integrado. |
| D-12 | FIJO con frecuencia > 1 si el contrato cubre solo parte de ese mes | Se emite completo si el contrato está en servicio algún día del período. |
| D-13 | Página 405 | Queda como "Conceptos y Tarifarios", sin solapa de abonos. La 406 se da de baja (script 07). Las tablas 407/408 quedan inactivas y se borran en un script posterior, cuando producción tenga el módulo. |
| D-14 | Módulo de Gestión requerido | Facturar exige que la empresa del estudio tenga activo el módulo 2 o el 5 (R5). En la base local la empresa 2 tiene el módulo 4 **inactivo** y la 3 lo tiene activo. |
| D-15 | Bug de `vfValidarComprobantesAnterioresConCae` (§1.1) | Corregirlo ya en los módulos 2 y 5, aunque este proyecto se demore: hoy rompe la aprobación ARCA. |
