# 13 — Obligaciones de pago unificadas

Versión 2 — 2026-10-30, con las decisiones de Pablo (sección 10). Módulos 2 y 5 (gestion / gestion_normal), código común en `core/`.

## 0. Implementación (2026-10-30)

Implementado en los dos módulos y aplicado en la base local. Lo que cambió respecto del diseño de abajo:

- **Tabla**: no se creó una tabla nueva. `gestion__obligaciones_impositivas` se renombró a `gestion__obligaciones_pago` (misma `tabla_id` 286, PK `obligacion_id`), con columnas nuevas (`obligacion_tipo_id`, `origen`, `origen_registro_id`, `importacion_id`, `referencia`, `cont_cuenta_debe_id`, `aprobaciones_requeridas`). `cont_cuenta_id` sigue siendo la cuenta del pasivo. Estados genéricos 3 / 4 / 5 / 39 / 40 / 6 / 38 (la "Pagada" 35 vieja pasó a 39 / 40).
- **Páginas**: 135 / 635 Obligaciones de Pago, 136 / 636 Tipos de Obligación y 137 / 637 Reglas de Aprobación. La 109 / 609 quedó como "Rendiciones de Gastos", con un aviso donde estaban las liquidaciones impositivas.
- **Aprobaciones**: por defecto no hay reglas, así que nada requiere aprobación (ni obligaciones ni OP). Se configuran en Reglas de Aprobación.
- **Anticipos**: la cuenta del anticipo es la misma que la de la deuda que va a cancelar (la del proveedor, o Sueldos a Pagar en el anticipo de sueldo). Por eso aplicar el anticipo no genera asiento. La OP cancela la solicitud (SOL_ANT) con `importe_origen = 0` (`origen_sin_consumo` en `tesoreriaRegistrarImputacion`), y la OP conserva su saldo a favor en todos los cálculos que suman `importe_origen` (cuenta corriente, consulta de comprobantes). La aplicación se hace desde Obligaciones de Pago > "Aplicar anticipos".
- **Tipos del sistema** (IMP_IVA, IMP_PER_IIBB, IMP_RET) sin rol de entidad: los generadores aceptan proveedores como organismo, igual que antes.
- **Pendiente**: los anticipos a rendir (RG_ANT) y los reembolsos (RG_REEM) siguen moviendo tesorería desde Rendiciones de Gastos (el punto de "Lo que deja de existir" de la sección 8 no se hizo). Tampoco se hizo el descuento de anticipos desde el formulario del sueldo ni los widgets del dashboard.
- **Arreglos de paso**: `tesoreriaRegistrarImputacion` grababa `importe_origen` como entero (perdía los centavos que suma la cuenta corriente). `pagos_ajax.php` tomaba `pagina_idx` del request (106 por defecto también en gestion_normal) y ahora lo tiene fijo. Las OP ahora cancelan las facturas B, C y M y las ND B y C de proveedor (faltaban las reglas).
- Scripts `20261030_obligaciones_pago_01_estructura.sql`, `_02_configuracion.sql`, `_03_paginas.sql`. Código en `core/aprobaciones.php` y `core/obligaciones/`. Simulador: `28_obligaciones_pago.py`.

## 1. Objetivo

Que **todo lo que la empresa tiene que pagar se pague desde Órdenes de Pago** (`pagos.php`, páginas 106 / 606), y que lo que no es una factura de compra se registre como **obligación de pago**, clasificada por **tipo de obligación**, sin importar en qué pantalla se generó:

- **Obligaciones varias manuales**: una pantalla única donde se cargan, una por una o importadas de un CSV / Excel, sueldos, anticipos de sueldo, anticipos a proveedores, cargas sociales, alquileres, servicios sin factura, etc. Con aprobación configurable.
- **Obligaciones generadas por el sistema**: liquidación de IVA, liquidación de percepciones IIBB, liquidación de retenciones practicadas y, a futuro, la liquidación de sueldos. Cada pantalla genera su obligación con un tipo específico.

Pagos lista, por entidad, todo lo pendiente (facturas de compra + obligaciones) y la OP lo cancela con la misma mecánica de imputaciones, medios, tesorería y asiento que hoy.

## 2. Situación actual

| Qué | Dónde se genera | Dónde se paga | Problema |
|---|---|---|---|
| Facturas de compra | Facturas de Proveedores | OP (`pagos.php`) | — |
| Liquidación impositiva (I_LIQ) | IVA Posición Mensual, Percepciones IIBB, Control Fiscal (retenciones), Obligaciones y Rendiciones (manual) | **I_PAG** en Obligaciones y Rendiciones | Segundo circuito de pago: un solo medio (fijo `medio_pago_id = 3`), sin cheques, sin retenciones, sin varias obligaciones por pago; numeración por `MAX + 1` |
| Anticipo a rendir (RG_ANT) | Obligaciones y Rendiciones | Al crearlo (egreso directo) | Sale plata sin OP ni aprobación |
| Reembolso de rendición (RG_REEM) | Obligaciones y Rendiciones | Al crearlo (egreso directo) | Ídem |
| Sueldos | — | — | No existe ningún comprobante |
| Anticipo a proveedor | OP sin imputar (el saldo queda en la OP, regla D6) | OP | Sin solicitud ni aprobación; **el saldo de la OP no se puede aplicar después**: ninguna regla de `gestion__comprobantes_tipos_cancelaciones` tiene a la OP como origen de una aplicación posterior |

Otros datos relevantes:

- `pagos.php` solo ofrece entidades con rol PRO o CLI (`obtenerProveedoresPago`). El rol EMP (empleado) y el ORG (organismo) existen en `gestion__roles_entidades` y tienen cuenta contable por rol (`ent_cuenta_rol`).
- La OP lista lo que puede cancelar con `tesoreriaObtenerComprobantesCancelables($empresa, 'OP', $entidad)`: comprobantes confirmados o cancelados en parte, con saldo, cuyo tipo es destino de una regla OP → tipo.
- El asiento de la OP tiene **una sola cuenta al Debe**: la del proveedor (o Deudores por ventas si es una devolución a un cliente).
- La OP no tiene aprobación: Borrador → Confirmada.
- Las anulaciones de liquidaciones (IVA, retenciones) controlan "tiene pagos" contando imputaciones confirmadas sobre la I_LIQ, sin mirar quién la imputó: siguen funcionando si la imputación la hace una OP en vez de una I_PAG.
- `recalcularEstadoDestino` ya es genérico (estado por saldo: 5 / 39 / 40) y `tesoreriaSincronizarEstadoOrigen` lo copia a la tabla de origen si esos estados están en su catálogo.
- `core/bancos/planilla_lector.php` (`bco_planilla_leer`) ya lee CSV, XLSX y XLS sin librerías externas: se reutiliza para importar obligaciones.
- **Hallazgo aparte**: en las empresas 2, 3 y 6 la regla OP → tipo incluye C_FA / C_NDA / C_NDX pero **no C_FB, C_FC, C_FM, C_NDB, C_NDC ni C_RB**. Una factura B o C de proveedor no aparece para pagar en `pagos.php`. Se corrige en el mismo script de reglas (8, punto 2).

## 3. Idea central

1. **Una obligación de pago es un comprobante con saldo** en `gestion__comprobantes` (espejo, como todo), ligado a una entidad (empleado, organismo, proveedor), con vencimiento y con la **cuenta contable que la cancela** guardada en el propio documento.
2. **El tipo de obligación** (catálogo por empresa) define rol de entidad, comprobante, cuentas, si se carga a mano y si es un anticipo.
3. **Una sola tabla** para todas las obligaciones, generadas en cualquier lugar. Las pantallas específicas llaman a una única función `oblRegistrar()`.
4. **Las aprobaciones son configurables** en un único lugar, para obligaciones (por tipo e importe) y para la OP (por defecto, sin aprobación).
5. **Pagos es el único lugar donde se cancelan.** I_PAG deja de usarse para pagar. Los egresos directos de RG_ANT / RG_REEM pasan a ser obligaciones que se pagan con OP.
6. **Contabilidad simple**: una obligación es una deuda por su importe. El sueldo es el neto a pagar, sin bruto ni aportes (lo ajusta el contador si quiere).

## 4. Modelo

### 4.1 Tipos de obligación — `gestion__obligaciones_tipos` (por empresa)

| Campo | Uso |
|---|---|
| `obligacion_tipo_id`, `empresa_id`, `codigo`, `nombre` | Código único por empresa (se resuelve por código, nunca por id); el código es el que usa la importación |
| `clase` | `OBLIGACION` (deuda: se devenga y se paga) / `ANTICIPO` (adelanto: el pago queda como saldo a favor a aplicar) |
| `rol_entidad_codigo` | EMP / PRO / ORG / NULL = cualquiera. Filtra el combo de entidades y valida la importación |
| `comprobante_tipo_id` | Comprobante que genera (ver 4.3) |
| `cuenta_concepto_debe` | Concepto de `gestion__empresas_cuentas_conceptos` para el Debe del devengamiento (gasto). NULL en los anticipos: usan la cuenta del rol de la entidad |
| `cuenta_concepto_pasivo` | Concepto del pasivo a pagar. NULL = la cuenta del rol de la entidad |
| `permite_manual` | 1 = se carga e importa en la pantalla de obligaciones; 0 = solo la genera el sistema |
| `origen_sistema` | NULL o el generador dueño del tipo: `IVA_POSICION`, `PERCEPCIONES_IIBB`, `RETENCIONES_LIQ`, `SUELDOS`… |
| `impuesto_tipo_id` | Para los tipos impositivos (lo que hoy guarda `gestion__obligaciones_impositivas.impuesto_tipo_id`) |
| `tabla_estado_registro_id` | 1 / 2 |

Los pagos parciales se admiten siempre: el usuario decide cuánto imputa en cada OP, como con las facturas.

Tipos iniciales sugeridos (se siembran por empresa y los copia el asistente de inicio):

| Código | Clase | Rol | Manual | Debe al devengar | Pasivo |
|---|---|---|---|---|---|
| `SUELDO` | OBLIGACION | EMP | sí | SUELDOS_JORNALES | SUELDOS_A_PAGAR |
| `SAC` | OBLIGACION | EMP | sí | SUELDOS_JORNALES | SUELDOS_A_PAGAR |
| `ANT_SUELDO` | ANTICIPO | EMP | sí | — (cuenta del rol EMP / EMPLEADOS) | — |
| `ANT_RENDIR` | ANTICIPO | EMP | sí | — (cuenta del rol EMP / EMPLEADOS) | — (reemplaza RG_ANT) |
| `REEMBOLSO` | OBLIGACION | EMP | no (rendición) | — | cuenta del rol EMP / EMPLEADOS |
| `ANT_PROV` | ANTICIPO | PRO | sí | — (**cuenta del proveedor**, como hoy) | — |
| `CARGAS_SOC` | OBLIGACION | ORG | sí | CARGAS_SOCIALES | CARGAS_SOC_A_PAGAR |
| `SINDICATO` | OBLIGACION | cualquiera | sí | CARGAS_SOCIALES | CARGAS_SOC_A_PAGAR |
| `IMP_IVA` | OBLIGACION | ORG | no | (lo arma la posición) | IVA_A_PAGAR |
| `IMP_PER_IIBB` | OBLIGACION | ORG | no | — | cuenta de la config |
| `IMP_RET` | OBLIGACION | ORG | no | — | cuenta de la config |
| `IMP_VARIOS` | OBLIGACION | ORG | sí | GASTOS_GENERALES | IMPUESTOS_A_PAGAR (DDJJ, tasas, planes de pago) |
| `VARIOS` | OBLIGACION | cualquiera | sí | GASTOS_GENERALES | OTRAS_DEUDAS |

Conceptos contables nuevos: SUELDOS_JORNALES, SUELDOS_A_PAGAR, CARGAS_SOCIALES, CARGAS_SOC_A_PAGAR, IMPUESTOS_A_PAGAR, OTRAS_DEUDAS. Sin el concepto cargado, error explícito (como el resto de `emp_cuenta_concepto_id`). La cuenta de un rol de entidad sale de `ent_cuenta_rol()` y, si no tiene, del concepto del rol (PROVEEDORES, EMPLEADOS).

Página nueva **Tipos de Obligación** (ABM de diccionario, bajo Tesorería): solo los tipos `permite_manual` se pueden crear; los de sistema se editan (nombre, cuentas) pero no se borran.

### 4.2 La obligación — `gestion__obligaciones_pago`

Reemplaza a `gestion__obligaciones_impositivas`, con sus filas migradas. Cabecera única, sin detalle:

`obligacion_id`, `empresa_id`, `sucursal_id`, `obligacion_tipo_id`, `comprobante_id` (espejo), `comprobante_tipo_id` / `_pv` / `_nro`, `entidad_id`, `f_emision`, `f_contabilidad`, `f_vto`, `periodo` (AAAA-MM, opcional), `descripcion`, `referencia` (n° DDJJ / VEP / recibo de sueldo / contrato), `moneda_id`, `tipo_cambio`, `importe_total`, `cont_cuenta_debe_id`, `cont_cuenta_pasivo_id` (se resuelven al confirmar y quedan fijas), `origen` (MANUAL / IMPORTACION / IVA_POSICION / … / SUELDOS), `origen_registro_id` (id en la tabla del generador, p. ej. `iva_posicion_id`), `importacion_id` (4.6), `aprobaciones_requeridas` (lo que fijó la regla al pedir la aprobación), `tabla_estado_registro_id`, `motivo_anulacion`, auditoría.

`conf__tablas`: fila nueva del bloque conf/gestion. La tabla 286 queda como histórico de I_LIQ.

### 4.3 Comprobantes

Se agrupan por **clase**, no un tipo por obligación, para no multiplicar numeradores ni reglas de cancelación:

| Código | Subgrupo (Caja/Bancos) | Para | Regla OP → |
|---|---|---|---|
| `I_LIQ` (existente) | Liquidaciones Impositivas | tipos IMP_* | **nueva** |
| `OBL` (nuevo) | Obligaciones de Pago | SUELDO, SAC, CARGAS_SOC, SINDICATO, REEMBOLSO, VARIOS | **nueva** |
| `SOL_ANT` (nuevo) | Solicitudes de Anticipo | ANT_SUELDO, ANT_RENDIR, ANT_PROV | **nueva** |

El tipo de obligación es el que agrupa y filtra en pantalla; el comprobante solo define numeración y reglas. Numeración con `gestion__comprobantes_numeradores` + `FOR UPDATE`, como la OP.

### 4.4 Estados de la obligación

```
Borrador (3) ──confirmar──> Confirmado (5)                       si ninguna regla de aprobación aplica
Borrador (3) ──confirmar──> Pend. Aprobación (4) ──aprobar (1 o 2 veces)──> Confirmado (5)
                                   └──rechazar──> Borrador (3)
Confirmado (5) ──(OP)──> Cancelado parcial (39) ──(OP)──> Cancelado total (40)   ← solo recalcularEstadoDestino()
Borrador / Pend. Aprobación ──eliminar──> Eliminado (6)
Confirmado sin imputaciones ──anular──> Anulado (38)
```

- Una sola acción "Confirmar" para el usuario: el motor de aprobaciones (sección 5) decide si queda en 5 o pasa a 4.
- **Al quedar en 5**: número, espejo con `importe_pendiente = importe_total`, asiento de devengamiento (Debe cuenta del tipo / Haber pasivo), `pfExigirDocumentoAbierto` si el tipo es impositivo y `cont_periodo_exigir_abierto`.
- **Clase ANTICIPO**: al quedar en 5 **no** hay asiento (todavía no salió plata ni hay deuda); queda con saldo = importe autorizado.
- Las generadas por el sistema nacen en 5 sin pasar por el motor, como hoy la I_LIQ: la liquidación es el acto deliberado.

### 4.5 Pantalla Obligaciones de Pago

La página 109 / 609 (hoy "Obligaciones y rendiciones") pasa a llamarse **Obligaciones de Pago**:

- Listado de todas las obligaciones (todas las clases y orígenes) con filtros por tipo, entidad, estado, vencimiento y período, y KPIs: a vencer en 7 días, vencidas, pendientes de aprobación.
- Alta / edición de los tipos `permite_manual`, Confirmar, Aprobar / Rechazar (solapa o filtro "Pendientes de mi aprobación", solo lo que el usuario puede aprobar), Anular, Eliminar.
- Botón **Importar** (4.6).
- Botón "Pagar" en la fila: abre `pagos.php` con la entidad y la obligación preseleccionadas.
- Las generadas por el sistema se ven pero se anulan desde su pantalla de origen.
- Las rendiciones quedan en su propia solapa.

### 4.6 Importación desde CSV / Excel

Para cargar en lote los sueldos de un sistema externo, las cargas sociales, alquileres, etc.

- **Lector**: `bco_planilla_leer()` de `core/bancos/planilla_lector.php` (CSV, XLSX, XLS). Si se mueve a un lugar común (`core/planillas/`), los dos usos lo incluyen de ahí.
- **Plantilla descargable** con una fila de ejemplo. Columnas (encabezado obligatorio, en cualquier orden):

| Columna | Obligatoria | Uso |
|---|---|---|
| `tipo` | sí, salvo que se elija un tipo fijo para todo el archivo | Código del tipo de obligación (`SUELDO`, `ANT_SUELDO`, …), solo tipos `permite_manual` |
| `cuit` o `entidad_codigo` | sí | Identifica la entidad de la empresa (CUIT/CUIL sin guiones o código interno) |
| `importe` | sí | > 0, en la moneda base salvo columna `moneda` |
| `f_emision` | no (hoy) | |
| `f_vto` | no (= f_emision) | |
| `periodo` | no | AAAA-MM |
| `descripcion`, `referencia` | no | |
| `sucursal` | no (la del usuario por defecto) | Código de sucursal, validado contra los accesos del usuario |

- **Dos pasos**: (1) subir y previsualizar, con cada fila validada (entidad inexistente o inactiva, entidad sin el rol del tipo, tipo inexistente o no manual, importe inválido, sucursal sin acceso, **posible duplicado** = mismo tipo + entidad + período + importe, o misma referencia), con total por tipo; (2) confirmar la importación de las filas válidas. Las filas con error no se importan y se pueden bajar en CSV para corregir.
- **Qué genera**: obligaciones con `origen = IMPORTACION` en Borrador, o con la opción "Confirmar al importar" (pasan por el motor de aprobaciones como si se confirmaran una por una).
- **No crea entidades**: si falta un empleado, se da de alta en Entidades y se reimporta. Si la entidad existe pero no tiene el rol, la previsualización ofrece "asignar el rol" (`actualizarRolIndividualEntidad`) antes de confirmar.
- **Registro**: `gestion__obligaciones_importaciones` (archivo guardado en `uploads/obligaciones/<empresa>/` con `.htaccess` deny, usuario, fecha, filas leídas / importadas / con error, estado). Desde ahí se ven las obligaciones de esa importación y se puede **deshacer** la importación mientras ninguna esté confirmada.
- Escrituras con CSRF.

## 5. Motor de aprobaciones (obligaciones y OP)

Código único en `core/aprobaciones.php`, configurable en la página nueva **Reglas de Aprobación** (bajo Tesorería, solo perfiles de administración). Sirve para dos documentos: `OBLIGACION` y `OP`.

### 5.1 Configuración

**`gestion__aprobaciones_reglas`** (por empresa):

| Campo | Uso |
|---|---|
| `documento` | `OBLIGACION` / `OP` |
| `obligacion_tipo_id` | Solo para OBLIGACION: NULL = todos los tipos; con valor gana sobre la general |
| `requiere_aprobacion` | 0 / 1 |
| `importe_desde` | Requiere aprobación si el importe es ≥ a este valor (0 = siempre) |
| `doble_aprobacion_desde` | NULL = una sola; con valor, desde ese importe hacen falta dos aprobaciones de **usuarios distintos** |
| `f_desde` / `f_hasta`, `tabla_estado_registro_id` | Vigencia (nada se borra) |

**`gestion__aprobaciones_topes`** (por empresa): qué perfil puede aprobar y hasta cuánto.

| Campo | Uso |
|---|---|
| `documento`, `obligacion_tipo_id` (NULL = todos) | Alcance |
| `empresa_perfil_id` | Perfil |
| `importe_maximo` | NULL = sin tope |
| vigencia, estado | |

Un usuario puede aprobar si tiene la función "Aprobar" de la página (permiso, como siempre) **y** algún perfil suyo en la empresa tiene un tope que cubre el importe. Sin filas de topes, alcanza con la función "Aprobar".

**`gestion__aprobaciones_registro`**: cada aprobación o rechazo (documento, id, usuario, perfil usado, importe, nivel 1 / 2, fecha, motivo). Es la auditoría y lo que cuenta las aprobaciones.

Valores por defecto al sembrar:
- `OBLIGACION`, todos los tipos: requiere aprobación desde $0, una sola aprobación. Topes: Administrador sin tope.
- `OP`: **sin aprobación** (`requiere_aprobacion = 0`). Se activa desde la misma pantalla.

### 5.2 Funcionamiento

- `aprob_evaluar($conn, $empresa_id, $documento, $tipo_id, $importe, $fecha)` → `['requiere' => bool, 'aprobaciones' => 0|1|2]`. Se llama al confirmar. Si requiere, el documento pasa a 4 y guarda `aprobaciones_requeridas`.
- `aprob_puede($conn, $usuario_id, $empresa_id, $documento, $tipo_id, $importe)`: para mostrar u ocultar el botón y para validar en el servidor.
- `aprob_aprobar(...)`: registra, controla que el usuario no haya aprobado ya ese documento ni sea quien lo cargó (configurable: flag `permite_autoaprobar` en la regla, por defecto 0), y cuando se completan las requeridas, confirma el documento (lo que haría "Confirmar" sin aprobación).
- `aprob_rechazar(...)`: vuelve a Borrador con motivo y borra lógicamente las aprobaciones previas.
- Si el importe cambia después de una aprobación (edición en Borrador), las aprobaciones anteriores no cuentan.

### 5.3 OP con aprobación

Cuando la regla `OP` está activa:

```
Borrador (3) ──confirmar──> Pend. Aprobación (4) ──aprobar──> Confirmada (5)
                                   └──rechazar──> Borrador (3)
```

- En 4 la OP **no** mueve tesorería, no numera, no imputa ni hace asiento: todo eso sigue pasando en `confirmarPago`, que se ejecuta recién al completarse la aprobación.
- Al pasar a 4 se valida lo mismo que al confirmar (saldos, medios, retenciones) para no aprobar algo que después falla, y se vuelve a validar al confirmar (los saldos pudieron cambiar).
- **Quedan fuera del circuito** (confirman directo): la OP automática de débito en cuenta (`fpGenerarOrdenPagoAutomatica`), la OP en $0 de aplicación de NC / anticipos y la de Gastos Bancarios.
- Funciones nuevas en la página 106 / 606: Aprobar, Rechazar (con los permisos de 8, punto 5). Hoy la página solo tiene "Acceder": hay que revisar si conviene pasar sus botones al motor de funciones por estado.

## 6. Pagos como único lugar de cancelación

### 6.1 Qué ofrece `pagos.php`

- Entidades: las que tienen rol PRO, CLI, EMP u ORG.
- Comprobantes pendientes: los de siempre (`tesoreriaObtenerComprobantesCancelables`), que con las reglas nuevas incluyen I_LIQ, OBL y SOL_ANT. Se agrega al resultado el **tipo de obligación** (nombre y clase) para mostrarlo y filtrar.
- Nueva solapa **"Pendientes de pago"**: todo lo cancelable de la empresa, de cualquier entidad, agrupado y filtrable por tipo de obligación, vencimiento y entidad. Desde ahí se elige una entidad y se arma la OP con sus pendientes ya marcados.
- **Pago en lote** (sueldos del mes, obligaciones importadas): se seleccionan varias obligaciones de distintas entidades y se genera una OP por entidad, con el mismo medio y la misma cuenta. Cada OP pasa por el motor de aprobaciones como cualquier otra.
- Pagos parciales: el usuario edita el importe a imputar, como hoy.

### 6.2 Asiento de la OP: Debe por imputación

Cambio central en `confirmarPago`. El Debe deja de ser una sola cuenta y se arma **una línea por cuenta de cancelación**, sumando lo imputado a cada destino:

| Destino imputado | Cuenta al Debe |
|---|---|
| Factura / ND de compra | Cuenta del rol PRO de la entidad o PROVEEDORES (como hoy) |
| NC de venta (devolución a cliente) | Cuenta del rol CLI o DEUDORES_VENTAS (como hoy) |
| Obligación (I_LIQ / OBL) | `cont_cuenta_pasivo_id` de la obligación |
| Solicitud de anticipo (SOL_ANT) | Cuenta del rol de la entidad (proveedor → su cuenta de proveedor; empleado → EMPLEADOS) |
| Saldo sin imputar (pago a cuenta) | Cuenta del rol de la entidad (como hoy) |

El Haber (medios, retenciones) no cambia. Así una misma OP puede pagar a ARCA el IVA y las retenciones, o a un empleado el sueldo y un reembolso.

### 6.3 Anticipos: la OP cancela la solicitud y deja saldo a favor

Una OP que imputa una SOL_ANT:

1. Cancela la solicitud (pasa a 40): ya no se puede volver a pagar.
2. Ese importe **no reduce el saldo de la OP**: queda en `importe_pendiente` de la OP como saldo a favor de la empresa frente a esa entidad (la regla D6 de hoy, ahora autorizada).
3. Asiento: Debe cuenta del rol de la entidad / Haber medio. En proveedores es la misma cuenta del proveedor, como un pago a cuenta de hoy.

Lo único especial es el cálculo de `saldo_op` en `confirmarPago`: excluye lo imputado a destinos de clase ANTICIPO.

### 6.4 Aplicación de anticipos (lo que hoy falta)

Una **OP con saldo** (anticipo autorizado o pago a cuenta de hoy) se aplica después a una deuda de la misma entidad, igual que una NC: imputación directa OP (origen) → factura / obligación (destino), documentada por una OP en $0 (el modelo es `tesoreriaAplicarNotaCredito` / `tesoreriaNotasCreditoDisponibles`).

- Reglas nuevas: OP → C_F* / C_ND* (proveedores) y OP → OBL (empleados: anticipo de sueldo contra el sueldo).
- Asiento de la aplicación: **proveedores, ninguno** (anticipo y factura en la misma cuenta del proveedor). Empleados: Debe Sueldos a pagar / Haber cuenta del empleado (EMPLEADOS), porque el sueldo está en otro pasivo.
- Dónde se aplica: en `pagos.php` (la OP de esa entidad muestra "Anticipos disponibles" como hoy muestra las NC) y al cargar una obligación SUELDO (el formulario muestra los anticipos pendientes del empleado y permite descontarlos). En la importación, opción "descontar anticipos pendientes" por empleado.

## 7. Cómo queda cada caso

| Caso | Se genera en | Comprobante | Asiento al confirmar | Se paga |
|---|---|---|---|---|
| Sueldos del mes | Obligaciones de Pago: importación del CSV / Excel del sistema de sueldos (tipo SUELDO, neto por empleado) o carga manual; opción de descontar anticipos | OBL | Sueldos y jornales / Sueldos a pagar | OP en lote, una por empleado |
| Sueldo (con módulo de sueldos, futuro) | Liquidación de sueldos → `oblRegistrar()` por empleado (SUELDO) + F.931 (CARGAS_SOC, ARCA) + sindicato | OBL | El de la liquidación (la llama con `sin_asiento`) | OP |
| Anticipo de sueldo | Obligaciones de Pago, ANT_SUELDO | SOL_ANT | — | OP → queda saldo → se descuenta del sueldo |
| Anticipo a rendir (viáticos) | Obligaciones de Pago, ANT_RENDIR | SOL_ANT | — | OP → la rendición (RG_REND) lo aplica |
| Reembolso de rendición | Rendición con saldo a favor del empleado → `oblRegistrar(REEMBOLSO)` | OBL | — (lo hace la rendición) | OP |
| Anticipo a proveedor | Obligaciones de Pago, ANT_PROV | SOL_ANT | — | OP (cuenta del proveedor) → saldo → se aplica a la factura sin asiento |
| Cargas sociales / sindicato | Obligaciones de Pago (manual o importación) | OBL | Cargas sociales / Cargas sociales a pagar | OP al organismo |
| IVA del período | IVA Posición Mensual | I_LIQ | El de la posición (como hoy) | OP a ARCA |
| Percepciones IIBB practicadas | Percepciones IIBB | I_LIQ | — (como hoy) | OP al organismo |
| Retenciones practicadas | Control Fiscal | I_LIQ | — (como hoy) | OP al organismo |
| DDJJ, tasa, plan de pagos | Obligaciones de Pago, IMP_VARIOS | I_LIQ | Gasto / Impuestos a pagar | OP |

## 8. Impacto

### Base

Scripts `AAAAMMDD_obligaciones_pago_NN_*.sql`, ids explícitos de los bloques, compatibles con MariaDB 10.4 y MySQL 8, reanudables:

1. Tablas `gestion__obligaciones_tipos`, `gestion__obligaciones_pago`, `gestion__obligaciones_importaciones`, `gestion__aprobaciones_reglas`, `gestion__aprobaciones_topes`, `gestion__aprobaciones_registro`; sus filas en `conf__tablas` y los estados en `conf__tablas_estados_registros` (obligaciones: 3, 4, 5, 6, 38, 39, 40; `gestion__pagos`: sumar el 4).
2. Comprobantes OBL y SOL_ANT (subgrupos en Caja/Bancos), reglas OP → I_LIQ / OBL / SOL_ANT, OP → C_F* / C_ND* (aplicación de anticipos) y el arreglo de OP → C_FB / C_FC / C_FM / C_NDB / C_NDC / C_RB. Regla I_PAG → I_LIQ a estado 2.
3. Conceptos contables nuevos, siembra de tipos y de reglas / topes por defecto por empresa (2, 3, 6), y sumar tipos y reglas a lo que copia el asistente de inicio (`inicioCopiarDatosBasicos`).
4. Migración de `gestion__obligaciones_impositivas` a `gestion__obligaciones_pago`: tipo impositivo deducido de `impuesto_tipo_id`, `origen` deducido de quién la creó (`gestion__iva_posiciones`, percepciones con `liquidacion_comprobante_id`, `gestion__pagos_retenciones.comp_liquidacion_id`) y `MANUAL` para el resto. `comprobante_id` no cambia: las imputaciones y los controles de "tiene pagos" siguen valiendo.
5. Páginas: 109 / 609 renombrada a Obligaciones de Pago, más dos nuevas por módulo (Tipos de Obligación, Reglas de Aprobación; módulo 5 = id + 500), con `ui-simple`. Funciones (Agregar, Editar, Confirmar, Aprobar, Rechazar, Anular, Eliminar, Importar) y, en 106 / 606, Aprobar y Rechazar. Permisos en `conf__perfiles_funciones` (perfiles base: Administrador todo; Compras y pagos y Tesorería cargar, importar y pagar; Aprobar solo Administrador; las dos páginas de configuración solo administración) **y** `conf__empresas_perfiles_funciones`.

### Código

Una sola fuente en `core/` (los `_model.php` de gestion y gestion_normal solo la incluyen, como `core/bancos/`):

- `core/aprobaciones.php`: motor de la sección 5.
- `core/obligaciones/obligaciones_service.php`: `oblRegistrar`, `oblConfirmar` (llama al motor), `oblAnular`, `oblTipos`, `oblCuentaCancelacion`, `oblAnticiposDisponibles`, `oblAplicarAnticipo`.
- `core/obligaciones/obligaciones_importacion.php`: previsualizar, confirmar y deshacer una importación (lector de planillas común).
- `core/obligaciones/obligaciones_ajax.php` común + JS `assets/js/obligaciones.js`; cada módulo fija su `pagina_idx`.
- `pagos_model.php` (los dos módulos; hoy son el mismo código, el sync no marca diferencias): entidades EMP / ORG, Debe por imputación (6.2), `saldo_op` sin los anticipos (6.3), aplicación de OP con saldo (6.4), estado 4 y aprobación (5.3), solapa Pendientes de pago y pago en lote. Es la parte con más riesgo: conviene moverla a `core/` en el mismo cambio.
- `tesoreria_core_model.php`: `tesoreriaObtenerComprobantesCancelables` devuelve el tipo y la clase de la obligación.
- `obligaciones_rendiciones_*`: sin pago propio (I_PAG); anticipo y reembolso a través de obligaciones.
- `iva_posiciones_model`, `percepciones_iibb_model`, `retenciones_liquidacion_model`: `registrarObligacionImpositiva()` pasa a ser un envoltorio de `oblRegistrar()` y le pasan el código de tipo. Los textos "se paga desde Obligaciones y Rendiciones" pasan a decir "se paga desde Órdenes de Pago".
- Dashboard: widgets "Obligaciones a vencer" y "Pendientes de mi aprobación".
- Simulador: caso nuevo con importación de sueldos y descuento de anticipo, obligación con doble aprobación y tope de perfil, OP con aprobación activada, IVA pagado por OP junto con retenciones, anticipo a proveedor aplicado a factura, factura B pagada por OP y anulación de una obligación con y sin pagos.

### Lo que deja de existir

- **I_PAG**: no se emiten más. El botón "Pagar" de la pantalla de obligaciones lleva a `pagos.php`. Las I_PAG históricas quedan como están.
- **Egreso directo de RG_ANT / RG_REEM**: el anticipo se pide como SOL_ANT (ANT_RENDIR) y se paga con OP; la rendición (RG_REND) aplica la OP con saldo como hoy aplica el RG_ANT; el reembolso genera una obligación REEMBOLSO. Los RG_ANT históricos con saldo siguen pudiendo rendirse.

## 9. Etapas sugeridas

1. Base (scripts 1–4) + `core/obligaciones` + Obligaciones de Pago (alta manual, sin aprobación) + reglas OP → obligaciones + Debe por imputación en la OP. Con esto ya se pagan sueldos e impuestos por OP.
2. Motor de aprobaciones (obligaciones y OP) + páginas Tipos de Obligación y Reglas de Aprobación.
3. Importación CSV / Excel + pago en lote + solapa Pendientes de pago.
4. Anticipos (SOL_ANT, saldo de la OP, aplicación) + retiro de I_PAG y de los egresos directos de RG_ANT / RG_REEM.

Cada etapa sale en los dos módulos a la vez.

## 10. Decisiones (Pablo, 2026-10-30)

| Tema | Decisión |
|---|---|
| Aprobación de obligaciones | Configurable: por tipo, importe desde el que se exige, doble aprobación desde un importe y tope por perfil (5.1) |
| Aprobación de la OP | Configurable en el mismo lugar; **por defecto, sin aprobación** (5.3) |
| Sueldos sin módulo | Carga de obligaciones desde CSV o Excel (4.6), además de la carga manual |
| Asiento del sueldo | Una obligación más: neto a pagar, sin bruto, aportes ni contribuciones |
| Anticipos a proveedores | Misma cuenta del proveedor; aplicarlos a la factura no genera asiento |
| Pagos parciales | Siempre permitidos; el importe lo define el usuario en la OP |
| Módulos | Los dos a la vez, código en `core/` |

| Sin regla de aprobación | La obligación se confirma al cargarla y **el mismo usuario que la crea la puede pagar** (aparece en pagos sin pasar por nadie más) |
| Con regla de aprobación | Quien la carga no la puede aprobar (`permite_autoaprobar = 0` por defecto en la regla) |
| OP fuera del circuito | La OP automática de débito en cuenta, la OP en $0 y la de Gastos Bancarios confirman directo |
| Importación | No crea entidades; solo ofrece asignar el rol si falta |
