# Revisión del proceso de Tesorería (08_proc_tesoreria_1.docx v2.0)

Fecha: 2026-10-03 · Revisado contra `casalucho_local.sql`, `casalucho_produccion.sql` y el código vigente
(`ventas_facturas_model.php`, `facturas_proveedores_model.php`). Incorpora la revisión de convenciones
(`revisor-convenciones`) y de casos límite (`revisor-casos-borde`).

## 1. Veredicto

El **concepto** es correcto y coherente con el sistema: todo documento de tesorería es un comprobante en
`gestion__comprobantes`, y la cancelación se hace con una tabla universal de imputaciones (que ya existe).
El **proceso** tiene huecos funcionales (anticipos, cobros a cuenta, cheques rechazados, anulación de
destinos, concurrencia) y el **modelo de tablas** no está alineado con el esquema real ni con el motor de
estados. No se puede implementar tal como está; con los ajustes de §3 sí.

## 2. Hallazgos

### 2.1 Críticos (rompen saldos o la integración con el código existente)

| # | Sección doc | Problema | Corrección |
|---|---|---|---|
| C1 | §2.4, RT05 | El doc crea el comprobante recién al confirmar (`comprobante_id NULL` en Borrador). El patrón vigente (`syncComprobante()`) lo crea desde Borrador y lo vincula por `tabla_origen_id + registro_origen_id` (UNIQUE `idx_origen`); `ventas_facturas.comprobante_id` es NOT NULL. Además, sin comprobante no hay dónde guardar las imputaciones "editables en Borrador" (§3.1). | Seguir `syncComprobante()`: comprobante desde Borrador, su estado espeja al de la operativa. |
| C2 | §2.3, §8.1, §8.2, RT07 | `gestion__comprobantes.tabla_estado_registro_id` guarda el estado **de la tabla origen** (id distinto por tabla). `c.tabla_estado_registro_id = :confirmado` no funciona con un único valor. | JOIN a `conf__tablas_estados_registros` y filtrar por `estado_registro_id` genérico (CONFIRMADO = 5). |
| C3 | §2.3, §4.3 | Signos: `total * signo − SUM(imputado)` rompe con NC (signo −1); §8.2 no usa signo. Columnas inexistentes (`total`, `comprobante_tipo_codigo`, `cancela_con_*`); `signo` es enum `'+','-','+/-'` en tipos. | `importe_imputado` siempre positivo; saldo = `importe_total − SUM`. El signo solo se usa para ctacte/contabilidad. "Qué cancela a qué" en una tabla de reglas, no en flags. |
| C4 | RT04, RT15 | Sobre-imputación concurrente: dos cobranzas (otra sucursal) imputan la misma factura; solo se bloquea la fila operativa propia. | `SELECT … FROM gestion__comprobantes WHERE comprobante_id IN (destinos) ORDER BY comprobante_id FOR UPDATE`, recalcular saldo y rechazar si `importe > saldo`. Validar misma empresa/entidad/tipo cancelable. |
| C5 | RT06 | `gestion__comprobantes.importe_pendiente` ya existe y lo escriben 4 modelos (= `importe_total`, nunca se descuenta). Dos fuentes de verdad; `syncComprobante()` además lo pisa en cada re-sync. | Mantener `importe_pendiente` como **caché** actualizada bajo el lock de C4 al confirmar/anular imputaciones; `syncComprobante()` no debe pisarla una vez que hay imputaciones. Query SUM = conciliación. |
| C6 | RT08, §3.3 | No está cubierta la anulación del **destino** (anular V_FA o I_LIQ ya imputados): el dinero queda aplicado a un documento anulado. | Bloquear la anulación del destino si tiene imputaciones vigentes (primero se desimputan). La imputación pasa a tener estado propio (vigente/anulada) y fecha. |
| C7 | §3.2, RT08 | Cheques: RECHAZADO solo existe desde EN_CARTERA (en la realidad llega tras DEPOSITADO/ENDOSADO). Anular un T_COBR con cheque ya depositado/endosado deja huérfanos. | Transiciones DEPOSITADO→RECHAZADO (débito bancario + ND al cliente) y ENDOSADO→RECHAZADO (ND del proveedor + ND al cliente). Solo se anula una cobranza si sus cheques siguen EN_CARTERA. |
| C8 | §6.3, §6.4 | Anticipos de rendición: `gestion__rendiciones_anticipos` no está definida y el egreso sale sin comprobante. RG_REEM imputa `total_a_reembolsar` pero RG_REND vale `total_aprobado` → el RG_REND **nunca** llega a saldo 0. | Anticipo = comprobante **RG_ANT** (deudor el empleado). Al aprobar, RG_ANT se imputa al RG_REND; RG_REEM imputa solo el remanente. Si el empleado debe devolver: cobranza sobre el RG_ANT. |
| C9 | §1, §4.8 | Transferencias, depósitos de cheque y movimientos manuales "sin comprobante" pero con asiento; `cont_asientos.comprobante_id` es NOT NULL. Contradice el principio del propio doc. | Todos generan comprobante (reusar TB, DB existentes; agregar movimiento manual). |

### 2.2 Altos

| # | Problema | Corrección |
|---|---|---|
| A1 | Los `*_detalles` (medios de pago) no indican **qué caja/cuenta** recibe o entrega el dinero. | FK a la cuenta de tesorería en cada línea de medio de pago. |
| A2 | RT02 (SUM imputaciones = total) impide cobros/pagos a cuenta y anticipos de clientes; UNIQUE(origen, destino) impide aplicar un remanente en una segunda vez. | SUM imputaciones ≤ total; el no aplicado queda como `importe_pendiente` del cancelador y se aplica después (imputación con fecha propia). Quitar el UNIQUE. |
| A3 | Nota de crédito como "medio de pago" con NC como destino → saldo −2X y rompe RT02. | Imputación directa NC → FA (origen = NC), fuera de los medios de pago. |
| A4 | `saldo_actual` de cajas/bancos sin regla de actualización atómica; transferencias con un solo `importe` entre monedas distintas. | `UPDATE … SET saldo_actual = saldo_actual ± ?` en la misma transacción; lock de ambas cuentas ordenadas por id; importe y tipo de cambio de origen/destino. |
| A5 | Estados derivados: anular un I_PAG/RG_REEM no devuelve el I_LIQ a PENDIENTE ni la rendición a APROBADA; VENCIDA se persiste pero nadie la asigna. | Una función `recalcularEstadoDestino()` llamada al confirmar/anular cualquier origen. VENCIDA se calcula al leer (no es estado). |
| A6 | Numeración: tesorería no tiene punto de venta; sin UNIQUE de número en comprobantes; varios modelos numeran sin `FOR UPDATE`. | PV por sucursal para tesorería, `FOR UPDATE` en `gestion__comprobantes_numeradores`, UNIQUE (empresa, tipo, pv, nro). |
| A7 | Multimoneda: imputaciones sin moneda ni tipo de cambio (V_FA en USD cobrada en ARS). | `importe_imputado` en moneda del destino + importe en moneda del origen + `tipo_cambio`; diferencia de cambio a asiento. |
| A8 | I_LIQ: `gestion__comprobantes.entidad_id` es NOT NULL; RT11 deja el organismo "opcional". `gestion__cuentas_corrientes` (RT11) no existe. | Organismos (AFIP, ARBA, AGIP, municipio) como entidades con rol nuevo. Ctacte = `impacta_ctacte` + imputaciones. |
| A9 | Contradicción rendiciones: §3.4 crea RG_REND al PRESENTAR y RECHAZADA "vuelve a Borrador"; §6.1/§6.3/RT12 lo crean al APROBAR. | Adoptar RT12: el comprobante RG_REND se confirma al aprobar. (Por C1 existe desde Borrador, sin impactos.) |

### 2.3 Convenciones del esquema

- **Nombres de tabla**: los diccionarios por empresa van con `gestion__` (`gestion__medios_pago`, `gestion__categorias_gasto`); `conf__bancos` solo si es catálogo global BCRA. La tabla de tipos es `gestion__comprobantes_tipos`.
- **Reutilizar lo existente**: `gestion__impuestos_tipos` + `gestion__empresas_impuestos_config` (no crear `conf__impuestos_tipos`); tipos RCX (24), OP (26), TB (28), DB (29), grupo 4 "Caja/Bancos" y sus subgrupos (los códigos de los subgrupos 12/13 parecen cruzados); `gestion__ventas_cobranzas` (vacía en local y producción → se reemplaza).
- **Estados**: ningún ENUM/`activo` de estado; todo por `tabla_estado_registro_id`. Faltan en `conf__estados_registros`: Anulado, Rechazado, Presentado, Aprobado, Reembolsado, Pagado, En cartera, Depositado, Endosado, Devuelto, Emitido, Debitado.
- **Rol de entidad**: agregar Empleado (EMP) y Organismo fiscal (ORG) en `gestion__roles_entidades`.
- **Campos estándar de proceso** (como `gestion__ventas_facturas`): `sucursal_id`, `comprobante_tipo_id`, `punto_venta_id`, `comprobante_nro`, `comprobante_id`, `entidad_id`, `f_emision`, `f_contabilidad`, `moneda_id`, `tipo_cambio`, `importe_total`, `observaciones`, `tabla_estado_registro_id` + auditoría.
- **Dialecto**: fechas `f_*`, descriptor con el nombre de la entidad (`medio_pago`, `caja`), PKs explícitas, FK contable `cont_cuenta_id`.
- **Tipos**: `comprobante_id` es bigint unsigned → imputaciones también (hoy int, no admite FK); importes decimal(18,2); utf8mb4_unicode_ci.
- **Asientos**: los subgrupos de tesorería están en `origen_asiento='NINGUNO'`; pasar a `'AUTO'` con `tipo_asiento_id`.
- **Log** (RT13): `gestion__logs.accion` no tiene acción de cambio de estado; usar UPDATE.

## 3. Decisiones de diseño adoptadas (a confirmar por el usuario)

| # | Decisión | Alternativa descartada |
|---|---|---|
| D1 | Comprobante desde Borrador (patrón `syncComprobante`). | Crear al confirmar (doc). |
| D2 | `importe_pendiente` = caché bajo lock; query SUM = conciliación. | Calcular siempre con SUM (doc). |
| D3 | Reusar RCX/OP/TB/DB; agregar I_LIQ, I_PAG, RG_ANT, RG_REND, RG_REEM, MC (movimiento caja), ND interna por cheque rechazado. | Tipos paralelos T_COBR/T_PAG. |
| D4 | **Cuentas de tesorería unificadas**: `gestion__tesoreria_cuentas` (CAJA, BANCO, VALORES en cartera, CUPONES de tarjeta) + un único `gestion__tesoreria_movimientos`. Elimina la FK polimórfica de transferencias y modela cheques en cartera y cupones de tarjeta sin casos especiales. | Cajas y bancos en tablas separadas (doc). |
| D5 | Imputaciones con estado propio, fecha, moneda y tipo de cambio; sin UNIQUE(origen, destino). | Imputación sin estado (doc). |
| D6 | Cobros/pagos a cuenta permitidos (imputaciones ≤ total). | Balance exacto obligatorio (doc). |
| D7 | NC se aplica con imputación directa NC → FA. | NC como medio de pago (doc). |
| D8 | VENCIDA calculada; PAGADO/REEMBOLSADO derivados por `recalcularEstadoDestino()`. | Estados persistidos sin recálculo. |
| D9 | `gestion__ventas_cobranzas` (vacía) se reemplaza por `gestion__cobranzas`. | Mantener ambas. |

### Decisiones adicionales tomadas en el DDL

| # | Decisión |
|---|---|
| D10 | El UNIQUE de numeración usa una columna generada `NULLIF(comprobante_nro,0)`, porque los Borradores tienen nro 0. Agrega `numeracion_entidad_id` para facturas de proveedor, donde el número es del proveedor. |
| D11 | Las tablas operativas tienen `comprobante_id` NOT NULL **sin FK**, porque `syncComprobante()` inserta 0 y después actualiza. El vínculo 1:1 lo garantiza `idx_origen`. |
| D12 | `gestion__cheques_operaciones` (+ `_items`): un cheque genera varios comprobantes a lo largo de su vida (depósito, débito, rechazo, devolución). |
| D13 | Imputaciones con 3 estados: Borrador, Vigente (mapeado a CONFIRMADO) y Anulada. |
| D14 | Rendición "Aprobada" = estado genérico CONFIRMADO. APROBADO y RECHAZADO quedan para los ítems. |
| D15 | El libro `gestion__tesoreria_movimientos` es inmutable: anular inserta un contramovimiento (`reversa_de_movimiento_id`). |
| D16 | Se extiende `gestion__impuestos_tipos` (`es_liquidable`, `frecuencia`). El organismo sale de la entidad ORG. |
| D17 | I_LIQ e I_PAG con `impacta_ctacte = 1` (el doc decía 0). |
| D18 | Unicidad de cheques por cuenta librada, no por banco. El CUIT del librador es obligatorio en cheques de terceros. |

## 4. Próximo paso

El DDL propuesto está en `sql/mantenimiento/20261003_tesoreria_01_ddl.sql`, generado por `disenador-modelo-datos` y **no ejecutado**.
Es compatible con MariaDB 10.4 (local) y MySQL 8.0 (producción), y se puede volver a correr si se corta.

Antes de aplicarlo:
- Confirmar las decisiones D1–D18.
- Definir el `tipo_asiento_id` de tesorería (hoy NULL con un TODO).
- Descomentar el DROP de `gestion__ventas_cobranzas`.
- Verificar los códigos BCRA de la semilla de bancos.

Pendiente de código para `orquestador-dev`:
- `syncComprobante()` no debe pisar `importe_pendiente` cuando el comprobante ya tiene imputaciones.
- Implementar `recalcularEstadoDestino()`.
- Respetar el orden de locks de §R.3 del DDL.
