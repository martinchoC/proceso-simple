# Módulo 6 — Sindicatos: especificación técnica de integración

**Versión 0.2, 2026-10-11. Borrador para discusión.**
Origen: relevamiento del sistema legacy `aecbb1/` (DDJJ de la AECBB y gestión de afiliados) y de sus bases `develops_aecbb1` / `develops_comun` (volcados `.sql` e importación en el MariaDB local, que además trae `sindi__intereses_tipos` y `sindi__obras_sociales`).

| Versión | Cambios |
|---|---|
| 0.1 | Primer borrador. |
| 0.2 | Decisiones de Pablo (§14): la rectificativa **revierte la DDJJ anterior completa** (como el legacy); los **intereses se calculan al emitir el volante** (como el legacy); la regla de media jornada pasa a **parámetros generales del sindicato** y se elimina la lógica duplicada. **Ninguna credencial ni dato de conexión en el código**: todo va en los parámetros del sindicato o en los medios de pago (§7.3.1). Se documentan las tablas de tasas y de obras sociales. |
| 0.3 | D1 resuelta (un comprobante por concepto). D6 resuelta: para la AECBB, solo afiliaciones, con sus distintos tipos y el grupo familiar (§2.6, §5.6); beneficios, salones y personal propio, a pedido. |

Este documento tiene tres partes:

1. Qué hace hoy el sistema legacy (§1–§3).
2. Cómo se acopla como módulo 6 de `gestion_multipyme`, con el sindicato usando además el módulo 5 `gestion_normal` como su back office (§4–§11).
3. Migración, fases y decisiones pendientes (§12–§14).

---

## 1. Qué es `aecbb1`

Es una aplicación PHP procedural (sin framework, `mysqli` con SQL concatenado, HTML generado en el mismo archivo, FPDF para los PDF). Tiene **dos módulos** sobre una sola base por sindicato (`develops_aecbb1`), más una base común (`develops_comun`) con localidades, provincias, periodicidades y la configuración del cron.

| Módulo legacy | Carpeta | Prefijo | Para quién |
|---|---|---|---|
| 6 "DDJJ - AECBB" | `declas/` (~200 archivos) | `sindi__` | Empleadores, estudios contables, inspectores y administración del sindicato |
| 7 "Gestión de Afiliados" | `gestion/` (~100 archivos) | `sindi__` / `gestion__` | Personal interno del sindicato (afiliados, beneficios, salones, RRHH propio, mesa de entrada) |
| 1 Configuración | `conf/` | `conf__` | Usuarios, módulos, páginas y accesos (un esquema propio, distinto del nuestro) |

Volumen de la base de la AECBB (Asociación de Empleados de Comercio de Bahía Blanca):

| Entidad | Filas |
|---|---|
| Empleadores (`sindi__sociedades`) / sucursales | 732 / 557 |
| Usuarios (`conf__usuarios`) | 751 |
| Personas (`sindi__personas`) / relaciones laborales / relaciones sindicales | 5.011 / 4.983 / 4.174 |
| DDJJ / renglones de empleados / cálculo por concepto | 1.578 / 11.530 / 23.063 |
| Movimientos de cuenta corriente (`sindi__trans`) | 3.784 |
| Volantes de pago / detalles / intereses | 1.335 / 2.164 / 2.144 |
| Deudas publicadas en Red Link / Interbanking | 1.338 / 1.249 |
| Recibos / archivos de rendición | 445 / 616 |
| Líneas contables (`sindi__contabilidad`) | 6.888 |
| Matriz empleador × período (`sindi__sociedades_cat_ddjjs`) | 35.173 |
| Actas de inspección / planes de pago | 47 / 5 |

Las tablas de beneficios, salones, licencias, documentos y mesa de entrada están **vacías** en esta base: en la AECBB se usa sobre todo el circuito de DDJJ y cobranza. El módulo de afiliados tuvo uso real en otra instalación (CEC Pico, ver `sindi__l_entidades`).

## 2. Procesos del sistema legacy

### 2.1 Usuarios y accesos

Tipos de usuario (`conf__usuarios_tipos`): 1 Administrador, **2 Usuario Sociedad (empleador, externo)**, 3 Inspectores, 4 Administración del sindicato, **5 Estudio (externo)**, 14 Administrador del sindicato; los tipos 6–13 son del módulo de afiliados. El usuario de un empleador o de un estudio es su **CUIT**. El acceso a cada página se da por opción (`sindi__accesos_usuarios_new`), con plantilla por tipo de usuario (`sindi__accesos_usuarios_tipos`) y delegación (`accesos_delego.php`).

- **Autoalta del empleador** (`index.php`, operación "Enviar"): formulario público con captcha. Crea `sindi__sociedades` y `conf__usuarios` (usuario = CUIT), genera una clave temporal que manda por mail y asigna los accesos del tipo 2. Avisa por mail a los usuarios de tipo 4.
- **Recupero de clave** ("Solicitar"): genera una clave nueva y la manda por mail.
- **Estudios contables** (`estudios*.php`): la administración da de alta el estudio y, al confirmarlo, se crea su usuario (CUIT). El estudio pide vincularse a un empleador (`re_sociedades_estudios`, operación `solicita`, estado 5), el empleador **acepta** (estado 10) o **rechaza** (100), con mail en cada paso. Con la vinculación aceptada, el estudio ve y presenta las DDJJ de ese empleador: `ddjjs_p.php` filtra por `usuario_tipo_id` 2 (su propia sociedad) o 5 (las sociedades vinculadas a su estudio).

### 2.2 Declaración jurada mensual

Cabecera `sindi__ddjjs`: empleador, período (`anio`, `mes`, `periodo = AAAAMM`), **secuencia** (0 = original, 1–9 = rectificativas), **tipo** (1 presentada por el empleador, 2 determinada de oficio) y **forma de ingreso** (0 carga inicial, 1 manual, 2 por mail).

1. **Alta** (`grabar`): solo puede haber una DDJJ en borrador por empleador. Si ya hay una confirmada del mismo período, la nueva es la siguiente rectificativa. El vencimiento sale de `sindi__vtos_trans` (período × **último dígito del CUIT**). La nómina inicial se arma con las relaciones laborales vigentes en el período, o copiando otra DDJJ.
2. **Carga de la nómina**, por cuatro vías:
   - Manual, empleado por empleado (`graba_empleado` / `actualiza_empleado`): CUIL, categoría del CCT, `rem_1` (remunerativo), `rem_2` (no remunerativo), `rem_3`, media jornada, jubilado, afiliado, obra social, novedad (licencias) y motivo de baja.
   - **Archivo de ARCA / SICOSS (F.931)** (`adjuntar_afip`, `adjuntar_afip1`): ancho fijo. CUIL en 0–11, apellido en 11–41, jubilado en 46, obra social en 61, bruto en 212 y no remunerativo en 405.
   - Planilla Excel/CSV (`adjuntar`, `importar_lote.php`, este último en lote para varios empleadores).
   - Mail (`ddjj_genera_auto.php`): lee una casilla POP3 y procesa los adjuntos.
   La carga además mantiene `re_laborales` (altas y bajas) y `re_sindicales` (afiliación declarada por el empleador, con el `ddjj_id` de origen).
3. **Cálculo** (se repite tal cual en cada operación de `ddjjs_p.php` y en `importar_lote.php`). Para cada concepto activo de `sindi__ddjj_items_tipos` y cada empleado:
   - base = `rem_1 + rem_2`;
   - si el concepto es **solo para afiliados** (`afiliado = 1`, Art. 101 Cuota Sindical) y el empleado no tiene relación sindical vigente en el período, no aplica;
   - si aplica a afiliados, **piso por escala salarial**: base = máx(base, (básico + rem1 + rem2) de `sindi__empleados_categorias_escalas` para la categoría y el período). En junio y diciembre se suma el **SAC proporcional** a los meses trabajados en el semestre;
   - **media jornada**: el código tiene dos variantes (una lee la marca del renglón, la otra una variable del formulario) y un recargo `media_jornada` por concepto. En la práctica **ninguna cambia el resultado**: la condición compara un decimal del concepto con `"on"` y nunca se cumple, ambas ramas calculan el piso completo y el recargo vale 0 en los dos conceptos. Hoy, entonces, un empleado de media jornada (2.509 renglones marcados) tiene el **mismo piso que uno de jornada completa**. En el módulo nuevo esto pasa a ser un parámetro (§5.1);
   - aporte = base × porcentaje / 100 (+ valor fijo).
   El resultado queda en `ddjj_empleados_calculo` (empleado × concepto), se suma en `ddjj_items` (concepto: base, cantidad de empleados, importe) y el total se copia a `ddjjs.importe`. Conceptos vigentes en la AECBB (CCT 130/75): **Art. 100 Fondo Social 2 %** (todos) y **Art. 101 Cuota Sindical 2 %** (solo afiliados).
4. **Confirmación** (`confirma`, estado 5):
   - genera un código de verificación de 4 caracteres;
   - genera **un movimiento de cuenta corriente por concepto** (`sindi__trans`, tipo 3 DDJJ, con `trans_pend` = saldo) y su asiento doble en `sindi__contabilidad`;
   - si es una rectificativa, **revierte la DDJJ anterior completa** (movimientos y asientos negativos) y deja la nueva completa;
   - cierra las afiliaciones de los empleados dados de baja en el período y avisa por mail al sindicato de las altas sindicales que trajo la DDJJ.
5. **Eliminación** (estado 100): borra las `re_sindicales` y `re_laborales` que había creado esa DDJJ.

Hay **DDJJ de oficio** (`ddjj_tipo_id = 2`, `ddjj_genera_*.php`): el sindicato determina la deuda de un empleador que no presentó, tomando como base la última nómina conocida o la escala.

### 2.3 Volante de pago

`volantes*.php`, `ddjj_genera_vp.php`, `volante_print.php`. El volante agrupa deudas pendientes (`trans_pend > 0`) y calcula, **en el momento de emitirse**, los **intereses resarcitorios** a **dos fechas de pago** (`f_pago` / `importe` y `f_pago1` / `importe1`). El empleador paga en las bocas habilitadas dentro de uno de esos vencimientos, así que el importe que cobra el canal ya incluye el interés.

Las tasas están en `sindi__intereses_tipos` (38 tramos, clase 1, confirmados en estado 5, desde 1991 hasta 2030): `porcentaje` es una **tasa mensual** con vigencia `f_desde`–`f_hasta`. El interés es **simple, por tramo**: para cada tramo que se superpone con el período entre el vencimiento de la deuda y la fecha de pago, saldo × porcentaje × días del tramo / 100 / 30, redondeado a 2 decimales. Los tramos usados quedan en `volantes_detalles_intereses`. Luego:

- guarda el detalle (`volantes_detalles`, `volantes_detalles_intereses`) y un movimiento `VOLA`;
- **publica la deuda en los canales de cobro**: Red Link (SOAP `AltaDeudasServices`, `funciones/soap_link.php`, con certificados de la red) e Interbanking (REST con OAuth2 `client_credentials`, `declas/interbanking/InterbankingAPI.php`: `publishOrModifyDebt`, `deleteDebt`, `getSettlements`). Las respuestas quedan en `sindi__pagos_link_detalle*`. En el legacy, las direcciones, el ente y los datos de acceso de cada canal están en el código; en el módulo nuevo van en el medio de pago (§7.3.1);
- imprime el PDF con un **código de barras Code128** para el Banco Nación: `869955555` + sociedad (10) + volante (10) + fecha AAAAMMDD + importe en centavos (10).

Hay volantes de afiliados (cuota de afiliados voluntarios o jubilados, `volantes_afiliados*.php`) y volantes por cuota de plan o de acta.

### 2.4 Cobranza y rendición

- **Archivos de rendición**: el banco o la red rinden en un archivo que se baja por SFTP (`declas/sftp/`: Red Link y Banco Provincia, con phpseclib), por la API de Interbanking (`getSettlements`), o se sube a mano (`recibos_importar_txt.php`). El formato es **posicional y parametrizado por medio de pago** en `sindi__pagos_tipos` (pares `*_pos` / `*_tam` para ente, sucursal, fecha de depósito y de rendición, importe, código de barras, cheque, número y vencimiento de la transacción…). El archivo queda guardado completo en `sindi__pagos_archivos` y línea por línea.
- **Recibo**: por cada línea, un `sindi__recibos` con su detalle (deuda cancelada, más los intereses calculados a la fecha de pago) y sus valores (`sindi__recibos_valores`: efectivo, transferencia, cheque, echeq, Interbanking, Link, código de barras Nación). Genera un movimiento `RECI`, baja el `trans_pend` de las deudas y registra el asiento. También hay recibos manuales (`recibos.php`) y automáticos (`recibos_auto.php`).
- **Cuenta corriente** por empleador y por afiliado, resumen y envío del resumen por mail (`cuentas_corrientes_resumen*.php`).

### 2.5 Fiscalización

- **Categoría de mora** del empleador (`sindi__sociedades_categorias`): Puntual (< 15 días), Mora Temprana (15–30), Moderada (31–60), Prolongada (61–120), Crónica (> 120), con historia en `sociedades_categorias_h`.
- **Matriz empleador × período** (`sociedades_cat_ddjjs`, 35 mil filas): marca si presentó, con qué vencimiento y si computa. Alimenta el **panel de alertas** (`panel_alertas_ddjj.php`, ~3.000 líneas), la lista de DDJJ pendientes, el seguimiento por empleador y el control de empleados que aparecían en DDJJ anteriores y faltan en la actual (`ddjj_empleados_out`).
- **Actas de inspección** (`actas*.php`): de tipo Normal, Libre Deuda o Complementaria. Recorren un rango de períodos y, por concepto, comparan lo declarado, lo determinado y lo pagado, y calculan el saldo y los intereses. El acta se cancela de contado (`confirma_contado`) o con un plan (`generar_plan_acta`). Tiene responsable, fecha de envío al inspector y un vencimiento de 72 h que vigila `actas_cron.php`.
- **Planes de pago** (`planes*.php`): tipos Moratoria 2024 y Acuerdo Permanente, con máximo de cuotas, anticipo mínimo, tasa, periodicidad y sistema. Cada cuota es un movimiento de cuenta corriente y se paga con su volante.
- **Facturas y notas emitidas** (`fact_emitidas`: ND, NC y facturas, por ejemplo por intereses) y recibidas.

### 2.6 Afiliados y servicios (módulo legacy 7)

- Padrón de personas y **afiliaciones**. Conviven cuatro orígenes:
  - **Por relación laboral, declarada por el empleador** en la DDJJ (`sindi__re_sindicales`, 3.987 vigentes y 186 dadas de baja): es la que determina si un renglón paga la Cuota Sindical (Art. 101). Se abre con la DDJJ que la declara y se cierra con la baja laboral del período.
  - **Voluntaria** (tipo 1, manual): la pide la persona con una solicitud (`re_sindicales_solicitudes`: datos personales, empleador, fechas de solicitud, envío, confirmación y afiliación). Puede pagar su cuota directamente con volantes de afiliado.
  - **Jubilados** (tipo 2, manual): por solicitud, sin empleador; paga su cuota directamente.
  - **Familiar** (tipo 3, automática, grupo 2): un familiar a cargo de un titular afiliado (`persona_id_titular`), con su parentesco. Queda vigente mientras el titular lo esté.
  Hay **grupos** (1 Afiliados, 2 Familiares), parentescos con su inverso (`re_familiares_tipos`: Cónyuge, Hijo/Padre, Madre, Padre, Suegro/a, Otros) y vínculos persona × familiar con vigencia (`re_familiares`). Hay **vigencias por edad** según el parentesco (`afiliaciones_vigencias`: hoy, un hijo de 21 a 25 años) con **excepciones** que se habilitan presentando un tipo de documento (`afiliaciones_vigencias_excep` → rubro de documento 10). `f_vigencia` marca hasta cuándo rige cada afiliación. Además: carnet, impresión de la ficha familiar y bajas con motivo.
- **Beneficios**: ayudas en productos con stock propio (`b_productos`, `b_almacenes`, `b_stock`, rendiciones), traumatología, servicios, **salones y alquileres** (de verano y de salón, con horarios).
- **RRHH del propio sindicato** (`l_*`): empleados, contratos, departamentos, posiciones, horarios y licencias con formularios y un cron.
- Mesa de entrada por sectores, repositorio de documentos y tableros (cumpleaños, afiliados, empleados por rubro, inconsistencias).

### 2.7 Contabilidad legacy

Libro simple `sindi__contabilidad` con un **importe con signo** por línea, cuenta numérica de un plan propio de 20 cuentas (`sindi__cuentas_contables`) y mapeo al plan del contador (`sindi__cuentas_contables_map`, para exportar). Cada concepto, tipo de movimiento, medio de pago y tipo de valor lleva su cuenta fija.

## 3. Diagnóstico técnico del legacy

Esto condiciona la migración: **no se porta código; se reescribe sobre nuestros patrones** y se reutilizan solo las reglas de negocio de §2.

| # | Hallazgo | Consecuencia |
|---|---|---|
| 1 | Datos de conexión de las integraciones (casilla de mail, Red Link, Interbanking, SFTP de las rendiciones, Google Drive, base de datos) escritos en el código. | En el módulo nuevo, **ningún dato de conexión va en el código**: todo se carga por sindicato en sus parámetros o en sus medios de pago (§7.3.1). |
| 2 | SQL concatenado con `$_POST` / `$_GET` en todo el sistema, incluido el login. | Inyección SQL generalizada. |
| 3 | Claves con `crypt($clave, substr($clave,0,2))` (DES: solo cuentan los primeros 8 caracteres, y la sal es la propia clave). La clave temporal viaja por mail en texto plano. | Hay que forzar el cambio de clave al migrar a `password_hash`. |
| 4 | Ids generados con `MAX(id)+1` y prefijo por año (`ddjj_id = 3AA000000 + n`, `volante_id`, `recibo_id`), sin transacciones. | Carreras de concurrencia y escrituras parciales si algo falla. |
| 5 | El cálculo de la DDJJ está **copiado unas 8 veces** (en cada operación de `ddjjs_p.php`, en `importar_lote.php` y en `ddjj_genera_*.php`), con variantes en la media jornada (§2.2), además de copias `*_old`, `*_AAAAMMDD` y "un/dos vencimientos". | Una sola implementación (`sindi_ddjj_core_model`) con la regla de media jornada parametrizada, validada contra datos reales (§12). |
| 6 | Estados propios (1 activo/borrador, 5 confirmado, 10 aceptado, 100 baja), fechas `'0000-00-00'`, base `latin1`. | Se mapean a los ids genéricos y a `NULL` (§12). |
| 7 | Una base por sindicato: la tabla `sindi__empresa` tiene dos filas, pero no hay `empresa_id` en las tablas de negocio. | Multi-tenant de verdad requiere agregar `empresa_id` en todas las tablas. |
| 8 | `sindi__intereses_tipos` (38 tramos) y `sindi__obras_sociales` (411, con código RNOS como id) no están en el volcado `.sql`, pero sí en la base importada en el MariaDB local. Son MyISAM / `latin1`, con fechas `'0000-00-00'`. | Se migran desde la base local (§12). |

## 4. Arquitectura propuesta

```
conf__empresas (el sindicato es una empresa del ERP)
   ├── Módulo 5 gestion_normal  → back office: proveedores, compras, pagos, tesorería, contabilidad,
   │                              ventas/facturación ARCA, stock (beneficios en productos)
   └── Módulo 6 sindicatos      → padrón de empleadores y trabajadores, DDJJ, volantes, canales de cobro,
                                  rendiciones, fiscalización, actas, planes, afiliados y beneficios,
                                  portal de empleadores y estudios
```

Principios:

1. **El sindicato es una empresa más** (`conf__empresas`), con los módulos 5 y 6 habilitados en `conf__empresas_modulos`. Varios sindicatos conviven en la misma base (AECBB, CEC Pico, CEC Patagones…). Toda tabla `sindi__` lleva `empresa_id` y todo modelo filtra por `$ctx['empresa_id']`.
2. **El módulo 6 no tiene contabilidad, tesorería ni cuenta corriente propias.** Escribe en el núcleo del ERP como ya lo hace `estudio_contable` con las facturas:
   - las deudas (DDJJ por concepto, intereses, cuotas de plan, actas) son **comprobantes** con su espejo en `gestion__comprobantes`, con `importe_pendiente` y `signo`;
   - los cobros son **cobranzas** del módulo de tesorería (`tesoreriaRegistrarMovimiento`, `tesoreriaRegistrarImputacion`, `recalcularEstadoDestino`);
   - los asientos salen de `tesoreriaGenerarAsiento` y las cuentas se resuelven por concepto en `gestion__empresas_cuentas_conceptos`, validadas con `emp_validar_cuentas_asiento()`.
   Así, el sindicato ve la cuenta corriente, la caja, los bancos y el balance en `gestion_normal` sin conciliar dos sistemas.
3. **Empleadores y estudios son `gestion__entidades`** de la empresa del sindicato, con un rol nuevo en `gestion__entidades_roles` (EMPLEADOR, ESTUDIO). Los datos propios del gremio van en tablas de extensión `sindi__empleadores` y `sindi__estudios`, enlazadas por `entidad_id`. Así se reutilizan CUIT, domicilios, sucursales (`gestion__entidades_sucursales`), condición fiscal y cuenta corriente.
4. **Los trabajadores no son entidades comerciales**: viven en `sindi__personas` (CUIL único por empresa). Solo el afiliado que paga directamente (voluntario o jubilado) se enlaza a una entidad con `entidad_id`, que se crea cuando hace falta.
5. **Portal externo dentro del mismo sistema**: los usuarios de empleadores y de estudios son `conf__usuarios` con un perfil externo en la empresa del sindicato, y se agrega una capa de **acceso por empleador** análoga a la de sucursales (§8).
6. **Canales de cobro como drivers** (como `percepciones_iibb_drivers.php`): cada canal (Banco Nación por código de barras, Red Link, Interbanking, Banco Provincia) implementa la misma interfaz y escribe filas normalizadas; el motor de cobro no conoce el canal (§7).

Estructura de archivos, con el patrón de 4 archivos por página:

```
modules/sindicatos/
  sindi_ddjj_core_model.php        cálculo, confirmación, rectificativa, oficio (única implementación)
  sindi_intereses_core_model.php   intereses resarcitorios por tramos de tasa y fecha de pago
  sindi_volantes_core_model.php    armado del volante y del código de barras, publicación en canales
  sindi_cobros_core_model.php      rendición conciliada → cobranza + imputación + ND de intereses
  sindi_pagos_drivers.php          interfaz + drivers BNA_BARRAS, RED_LINK, INTERBANKING, BAPRO, MANUAL
                                   (toda su configuración sale de sindi__medios_pago)
  sindi_importadores.php           F.931/SICOSS, planilla, lote (formato → filas normalizadas)
  ddjj.php / _ajax / _model / .js  … (una carpeta plana como gestion/, ver §9)
  portal/registro.php              autoalta pública (sin sesión)
cron/sindicatos_*.php              CLI: publicar deudas, bajar rendiciones, leer mails, recalcular mora,
                                   vencimiento de actas
```

## 5. Modelo de datos

Convenciones: `utf8mb4_unicode_ci`, `empresa_id` en todas las tablas, `tabla_estado_registro_id` con el **id genérico**, fechas `NULL` en lugar de `'0000-00-00'`, montos en `DECIMAL(18,2)`, vigencias con `f_desde` / `f_hasta`, y `legacy_id` en las tablas que se migran. Los ids de las tablas nuevas de `conf__tablas` van en el bloque **500–599** (libre).

### 5.1 Configuración del gremio

| Tabla | Tipo | Contenido |
|---|---|---|
| `sindi__parametros` | vigencia (una abierta) | **Parámetros generales del sindicato**: convenio por defecto, `portal_slug`, días de gracia, si exige aprobación del sindicato para las autoaltas, mails de aviso, **regla de media jornada** (abajo), datos del servidor de correo saliente y de la **casilla de ingreso de DDJJ por mail** (servidor, puerto, protocolo, usuario y clave cifrada, §7.3.1), carpeta de Google Drive si se usa. Se edita en la página 1001. |
| `sindi__convenios` | diccionario | CCT (p. ej. 130/75). Un sindicato puede tener varios. |
| `sindi__conceptos` | diccionario con vigencia | Reemplaza `ddjj_items_tipos`: `codigo`, descripción, `convenio_id`, `base` (REM_TOTAL / REMUNERATIVO / FIJO), `porcentaje`, `valor_fijo`, `solo_afiliados`, `aplica_piso_escala`, `incluye_sac`, **`comprobante_tipo_id`** (el tipo de comprobante que genera) y **`cuenta_concepto_codigo`** (concepto contable, nunca un id de cuenta). Desaparecen los campos `media_jornada_escala` y `media_jornada` del legacy. |
| `sindi__categorias` + `sindi__categorias_escalas` | diccionario + período | Categorías del CCT y escala salarial por período (básico, rem1, rem2), para el piso. |
| `sindi__vencimientos` | período | Período × último dígito del CUIT → `f_vto`, por tipo (DDJJ, aporte). |
| `sindi__tasas_interes` | vigencia | Tramos de tasa resarcitoria. Reemplaza `intereses_tipos`: `clase` (1 = resarcitorio), `tasa_mensual`, `f_desde`, `f_hasta`, sin superposición entre tramos de la misma clase. Al confirmar, se valida que no haya huecos. |
| `sindi__categorias_mora` | diccionario | Puntual…Crónica, con días desde/hasta y color. |
| `sindi__obras_sociales` | diccionario | `codigo_rnos` (el id legacy, que es el código del Registro Nacional de Obras Sociales y viene en el F.931), nombre, descripción, `f_alta`, `f_baja`. Puede ser común a todas las empresas (catálogo nacional) o por empresa: ver D10. |
| `sindi__novedades`, `sindi__bajas_motivos` | diccionario | Catálogos de la nómina. |

**Regla de media jornada** (`sindi__parametros.media_jornada_piso`), la única fuente del cálculo:

| Valor | Piso por escala para un renglón de media jornada |
|---|---|
| `COMPLETO` | El de la escala completa. **Es lo que hace hoy el legacy** y es el valor inicial al migrar la AECBB. |
| `PROPORCIONAL` | Escala × `media_jornada_factor` (parámetro, por defecto 0,50). |
| `SIN_PISO` | Se toma la remuneración declarada, sin piso. |

Si cambia, rige desde la vigencia nueva: las DDJJ ya presentadas no se recalculan, y los borradores se recalculan al guardar.

### 5.2 Padrón

| Tabla | Contenido |
|---|---|
| `sindi__empleadores` | `entidad_id` (UNIQUE por empresa), rubro, `convenio_id`, `categoria_mora_id` (caché que recalcula un cron), `f_alta_padron`, origen (AUTOALTA / SINDICATO / OFICIO / MIGRACION). |
| `sindi__estudios` | `entidad_id`, matrícula, contacto. |
| `sindi__estudios_empleadores` | Vinculación con workflow: `estudio_id`, `empleador_id`, `iniciado_por` (ESTUDIO / EMPLEADOR / SINDICATO), `f_solicitud`, `f_resolucion`, `f_baja`, estado (§6). |
| `sindi__personas` | CUIL (UNIQUE por empresa), apellido, nombre, documento, sexo, fecha de nacimiento, contacto, localidad, `entidad_id` opcional. |
| `sindi__relaciones_laborales` | Empleador × persona, `f_alta`, `f_baja`, `ddjj_alta_id`, `ddjj_baja_id`. |
| `sindi__afiliaciones` | Ver §5.6. |
| `sindi__empleadores_periodos` | Reemplaza `sociedades_cat_ddjjs`: empleador × período → `f_vto`, `ddjj_vigente_id`, situación (PENDIENTE / PRESENTADA / FUERA_DE_TERMINO / OFICIO / NO_COMPUTA), días de atraso. La **mantiene el núcleo de DDJJ** al confirmar o anular, y la abre un cron al inicio de cada período. |

### 5.3 DDJJ

| Tabla | Contenido |
|---|---|
| `sindi__ddjj` | Cabecera: `empleador_id`, `periodo` (AAAAMM), `secuencia`, `ddjj_rectificada_id`, `media_jornada_piso` aplicada (copia del parámetro, para reproducir el cálculo), tipo (EMPLEADOR / OFICIO), `origen_ingreso` (MANUAL / F931 / PLANILLA / LOTE / EMAIL / MIGRACION), `f_vto`, `total`, `codigo_verificacion` (≥ 8 caracteres, con `random_bytes`), `usuario_presenta_id`, `estudio_id` si presentó un estudio, `f_presentacion`, estado. UNIQUE (`empresa_id`, `empleador_id`, `periodo`, `secuencia`). |
| `sindi__ddjj_empleados` | Renglón: persona, categoría, `rem_1`, `rem_2`, `rem_3`, media jornada, jubilado, afiliado declarado, obra social, novedad, baja (motivo y fecha), localidad, observaciones. UNIQUE (`ddjj_id`, `persona_id`). |
| `sindi__ddjj_calculos` | Renglón × concepto: base declarada, piso por escala, base aplicada, porcentaje, valor fijo, importe. Se recalcula entero en cada cambio. |
| `sindi__ddjj_conceptos` | Totales por concepto (base, cantidad, importe) y **`comprobante_id`** del comprobante que generó al confirmarse. |
| `sindi__ddjj_importaciones` | Archivo (en `uploads/sindicatos/`), formato, filas leídas, aceptadas y rechazadas, y un log de errores por fila. |

### 5.4 Cobro

| Tabla | Contenido |
|---|---|
| `sindi__medios_pago` | Reemplaza `sindi__pagos_tipos`. Un medio de cobro del sindicato con **toda su configuración y sus datos de acceso** (§7.3.1). |
| `sindi__volantes` | Empleador o persona, `f_pago_1`, `importe_1`, `f_pago_2`, `importe_2`, `codigo_barras`, `numero` (numerado con el comprobante, tipo no contable), estado. Los importes se **fijan al emitir** y no se recalculan. |
| `sindi__volantes_detalles` | `comprobante_id` (la deuda), saldo al emitir, interés a la fecha 1 y a la fecha 2. |
| `sindi__volantes_detalles_intereses` | Tramos de tasa usados por detalle y por vencimiento (`tasa_interes_id`, desde, hasta, días, tasa, interés), como el legacy, para poder auditar el cálculo. |
| `sindi__volantes_canales` | Volante × medio de pago: id de deuda externo (CPE de Link, identificador de Interbanking), fechas de publicación y baja, último código y descripción de resultado. El log completo de cada llamada va a `gestion__integraciones_resultados`. |
| `sindi__rendiciones` | Canal, fecha, archivo o respuesta de la API (en `uploads/`), totales, estado (Recibido → Conciliado → Procesado). |
| `sindi__rendiciones_lineas` | Fila normalizada: identificador del volante, importe, fecha de pago, fecha de acreditación, sucursal, medio; resultado de conciliación (OK / DIFERENCIA / SIN_VOLANTE / DUPLICADA) y `cobranza_id`. |

### 5.5 Fiscalización

`sindi__actas` (+ `_periodos`, `_items`, con la misma semántica que el legacy: declarado, determinado, pagado, saldo, interés, ajustes con usuario y fecha), `sindi__planes_tipos`, `sindi__planes` y `sindi__planes_cuotas` (cada cuota es un comprobante).

### 5.6 Afiliaciones y grupo familiar

| Tabla | Tipo | Contenido |
|---|---|---|
| `sindi__afiliaciones_tipos` | diccionario | `codigo` (ACTIVIDAD, VOLUNTARIA, JUBILADO, FAMILIAR…, configurable por sindicato), `grupo` (TITULAR / FAMILIAR), `alta` (AUTOMATICA / SOLICITUD), `requiere_empleador`, `paga_cuota_directa` (genera volantes de afiliado), `concepto_cuota_id` y `importe_cuota` si paga directo, `requiere_titular` (familiar). |
| `sindi__parentescos` | diccionario | Cónyuge, Hijo, Padre, Madre, Suegro/a, Otros, con `parentesco_inverso_id`. |
| `sindi__afiliaciones_vigencias` | regla con vigencia | Tipo × parentesco → edad mínima y máxima (p. ej. hijo hasta 21; de 21 a 25, solo con excepción). |
| `sindi__afiliaciones_vigencias_excepciones` | regla | Vigencia → tipo de documento que la habilita (p. ej. certificado de alumno regular) y meses de validez del documento. |
| `sindi__afiliaciones` | proceso | `persona_id`, `afiliacion_tipo_id`, `titular_afiliacion_id` (para familiares, la afiliación del titular), `parentesco_id`, `empleador_id` (si el tipo lo requiere), `f_alta`, `f_baja`, `f_vigencia` (calculada: edad límite, vencimiento del documento de excepción o el fin de la del titular), `baja_motivo_id`, origen (DDJJ / SOLICITUD / MIGRACION) + `origen_id` (la `ddjj_id` o la `solicitud_id`), `numero_afiliado`, estado. Unifica `re_sindicales` y `afiliados` del legacy. |
| `sindi__afiliaciones_documentos` | detalle | Documentos presentados para una excepción (tipo, archivo en `uploads_privados/`, fecha de presentación y de vencimiento). |
| `sindi__afiliaciones_solicitudes` | proceso | Solicitud con los datos personales (o la persona si ya existe), el tipo pedido, el empleador, el titular y el parentesco si es familiar, el canal (INTERNO / PORTAL) y las fechas de envío y resolución. Al aprobarse crea la afiliación y, si corresponde, las de los familiares cargados en la misma solicitud. |

Reglas:

- **ACTIVIDAD** (la declarada por el empleador) la mantiene el núcleo de la DDJJ: al confirmar, abre las que la DDJJ declara y cierra las de los empleados dados de baja en el período (motivo = baja laboral). Una persona puede tenerla con varios empleadores a la vez. Es la única que usa el cálculo de la Cuota Sindical (vigente en el período = alta ≤ fin del período y sin baja o con baja posterior).
- **Familiares**: se cargan sobre un titular con afiliación vigente. Su `f_vigencia` es la menor entre la edad límite, el vencimiento del documento de excepción y la baja del titular. La baja del titular **arrastra** la de sus familiares con el mismo motivo, y el reingreso del titular no los reactiva solos.
- **Vencimiento por edad o documento**: un cron diario (`sindicatos_mora.php` suma este paso) da de baja las afiliaciones con `f_vigencia` vencida y avisa 30 días antes, para que la familia presente el documento.
- **Cuota directa** (voluntarios y jubilados): genera mensualmente el comprobante de cuota del afiliado (entidad del afiliado, creada al afiliarse) y se paga con volante de afiliado por los mismos medios de pago.
- El número de afiliado es único por empresa y lo comparte el grupo familiar con un sufijo (p. ej. 1234/01).

### 5.7 Beneficios y salones (a pedido, fuera del alcance de la AECBB)

`sindi__beneficios_tipos`, `sindi__beneficios` (persona, tipo, importe, coseguro y, si entrega productos, el `stock_ajuste_id` del módulo 5), `sindi__salones` y `sindi__salones_reservas` (la seña o el alquiler se facturan con `ventas_facturas` del módulo 5).

## 6. Estados (motor de botones)

Con ids genéricos de `conf__estados_registros`; no hace falta crear estados nuevos:

| Tabla | Flujo |
|---|---|
| `sindi__ddjj` | 3 Borrador → **28 Presentado** (con comprobantes) → 38 Anulado. Cuando se presenta una rectificativa, la anterior pasa a 2 Inactivo (etiqueta propia "Rectificada") con sus comprobantes revertidos (§7.1). Las de oficio: 3 → 28, y se rectifican igual cuando el empleador presenta la suya. |
| `sindi__estudios_empleadores` | 4 Pendiente de aprobación → 1 Activo / 25 Rechazado; 1 → 2 Inactivo (baja). |
| `sindi__volantes` | 3 Borrador → **31 Emitido** (publicado en los canales) → 39 Cancelado parcial / 40 Cancelado total / 20 Vencido (cron) / 38 Anulado (despublica). |
| `sindi__rendiciones` | 8 Recibido → 5 Confirmado (conciliado) → 35 Pagado (cobranzas generadas); 27 Con error. |
| `sindi__actas` | 3 Borrador → 18 En curso → 5 Confirmado → 40 Cancelado total, o derivada a un plan. |
| `sindi__planes` | 3 → 5 Confirmado → 39 / 40 (por saldo de cuotas). |
| `sindi__afiliaciones_solicitudes` | 3 → 4 → 30 Aprobado / 25 Rechazado. |
| `sindi__afiliaciones` | 1 Activo → 2 Inactivo (baja con motivo), 20 Vencido (por edad o documento, automático). |
| Diccionarios | 1 / 2. |

Las transiciones van en `conf__paginas_funciones` (bloque **9001–9999**, libre) y las etiquetas propias en `conf__tablas_estados_registros` (bloque **3001–3300**, libre). Todo en `sql/mantenimiento/AAAAMMDD_sindicatos_NN_*.sql`.

## 7. Integración con el núcleo del ERP

### 7.1 Comprobantes

Grupo nuevo "Sindical" en `gestion__comprobantes_grupos`, con subgrupos y tipos **por empresa** (resueltos con `emp_subgrupo_id(...)`, nunca por id):

| Subgrupo / tipo | Signo | Ctacte | Contab. | Origen |
|---|---|---|---|---|
| DDJJ – un tipo por concepto (DFS Fondo Social, DCS Cuota Sindical…) | +1 | sí | sí | `sindi__ddjj_conceptos` |
| Reversión de DDJJ rectificada – un tipo por concepto | −1 | sí | sí | `sindi__ddjj_conceptos` de la DDJJ anterior |
| ND Intereses resarcitorios | +1 | sí | sí | cobro del volante (§7.3) |
| Acta determinada | +1 | sí | sí | `sindi__actas_items` |
| Cuota de plan | +1 | sí | no (reclasifica) | `sindi__planes_cuotas` |
| Volante de pago | 0 | **no** | no | `sindi__volantes` (solo numeración) |

- **Un comprobante por concepto** de la DDJJ, como en el legacy: cada concepto tiene su cuenta y su destino (el Fondo Social y la Cuota Sindical pueden ser fondos distintos) y la cobranza se imputa por concepto. `(tabla_origen_id, registro_origen_id) = (tabla de sindi__ddjj_conceptos, ddjj_concepto_id)` respeta el UNIQUE.
- **La rectificativa revierte la DDJJ anterior completa** (como el legacy, decisión D2). Al confirmarla, en una sola transacción y por concepto:
  1. se emite una **Reversión** (signo −1) por el **importe total** del concepto de la DDJJ anterior, con su asiento inverso;
  2. la reversión se imputa contra el comprobante anterior hasta cubrir su saldo pendiente. Si el anterior ya tenía pagos, el remanente queda como **saldo a favor** del empleador en ese concepto;
  3. se emite el comprobante de la rectificativa por el **importe total**, con el vencimiento del período (`sindi__vencimientos`, el mismo que tenía la original), y se le imputa el saldo a favor del paso 2 si lo hay.
  Los pagos ya hechos no se tocan: siguen imputados a la DDJJ original, y la cuenta corriente muestra original, reversión y rectificativa. La anterior pasa a "Rectificada". Si la anterior estaba incluida en un volante vigente, ese volante se **anula y se despublica** de los canales antes de confirmar.
- La **cuota de plan** refinancia: el plan cancela los comprobantes de origen con una imputación (como en tesorería) y genera las cuotas. `gestion__comprobantes_tipos_cancelaciones` define qué tipo cancela a cuál.

### 7.2 Contabilidad

Conceptos nuevos en `gestion__empresas_cuentas_conceptos`, sembrados por empresa: `SINDI_DEUDORES_APORTES`, `SINDI_DEUDORES_AFILIADOS`, `SINDI_INTERESES_GANADOS`, `SINDI_MULTAS`, `SINDI_GASTOS_ADM`, `SINDI_AJUSTE_ACTAS`, más uno por concepto de DDJJ (`SINDI_APORTE_<codigo>`). Asientos tipo: confirmación de DDJJ (Deudores por aportes contra Recurso por concepto), reversión por rectificativa (el inverso), cobro (cuenta del medio de pago contra Deudores) e intereses (Deudores contra Intereses ganados). El mapeo al plan del contador (`sindi__cuentas_contables_map`) desaparece: el sindicato usa su propio plan en `gestion__cont_cuentas`.

### 7.3 Canales de cobro

```php
interface SindiCanalCobro {
    public function publicarDeuda(mysqli $c, int $empresa_id, array $volante): array;   // id externo
    public function anularDeuda(mysqli $c, int $empresa_id, array $volanteCanal): void;
    public function obtenerRendiciones(mysqli $c, int $empresa_id, string $fecha): array; // filas normalizadas
    public function parsearArchivo(string $contenido, array $layout): array;           // si rinde por archivo
}
```

Cada medio de pago de `sindi__medios_pago` (§7.3.1) indica qué driver usa: BNA_BARRAS, RED_LINK, INTERBANKING, BAPRO, o MANUAL para ventanilla y transferencia. El driver recibe la configuración del medio y nunca lee constantes propias.

#### 7.3.1 Parámetros del sindicato y medios de pago: ningún dato de conexión en el código

Regla del módulo: **en el código no hay direcciones de servicio, entes, usuarios, claves, tokens, certificados ni casillas de mail.** Lo que el legacy tiene escrito en el código pasa a cargarse por sindicato en dos pantallas:

| Dónde | Qué se carga |
|---|---|
| **Parámetros del sindicato** (página 1001, `sindi__parametros`) | Correo saliente (SMTP: servidor, puerto, seguridad, usuario y clave), **casilla de ingreso de DDJJ por mail** (IMAP/POP3: servidor, puerto, TLS, usuario y clave), Google Drive si se usa (archivo de la cuenta de servicio y carpeta), `portal_slug`, avisos y la regla de media jornada. |
| **Medios de pago** (página 1006, `sindi__medios_pago`) | Un registro por medio de cobro del sindicato (ver abajo). |

`sindi__medios_pago`:

| Grupo | Campos |
|---|---|
| Identificación | `empresa_id`, `codigo`, nombre, `driver`, `ambiente` (PRUEBA / PRODUCCION), `orden` en el volante, estado (1 / 2). |
| Imputación | `tesoreria_cuenta_codigo` (la cuenta de tesorería donde se acredita, resuelta por código), `medio_pago_codigo` del ERP (`emp_medio_pago_id`), entidad bancaria. |
| Publicación de deuda | `url_servicio`, `url_autenticacion`, `ente` / `acuerdo` / `convenio`, `client_id`, `usuario`, `clave` y `client_secret` (cifrados), certificado y clave privada (archivos subidos, cifrados), `verificar_ssl`, `publica_deuda` (sí/no), concepto por defecto. |
| Rendición | `rendicion_modo` (API / SFTP / ARCHIVO_MANUAL), host SFTP, puerto, usuario, clave o clave privada (cifradas), carpeta remota, patrón de nombre de archivo, `layout` posicional (los pares `*_pos` / `*_tam` de `sindi__pagos_tipos` pasan a filas de `sindi__medios_pago_layout`: campo, posición, largo, formato). |
| Código de barras | `barras_prefijo`, composición (campos y largos, el legacy usa prefijo + empleador 10 + volante 10 + fecha + importe 10), dígito verificador, simbología (Code128 / I2of5). |

Tratamiento de los datos de acceso:

- Se guardan **cifrados** (AES-256-GCM con `openssl_encrypt`, o `sodium_crypto_secretbox`) con una clave maestra que vive **solo en el `.env`** del servidor (`SINDI_CLAVE_CIFRADO`), nunca en la base ni en el repo. Un helper `core/secretos.php` (`secreto_cifrar` / `secreto_descifrar`) es el único que los toca y descifra solo dentro del driver, en el momento de usarlos.
- En pantalla son **de solo escritura**: se muestran como "cargada el dd/mm/aaaa por usuario", se pueden reemplazar o borrar, pero nunca se devuelven al navegador ni aparecen en `error_log` ni en `gestion__integraciones_resultados` (el log enmascara cabeceras `Authorization`, tokens y claves).
- Los certificados y claves privadas se suben por la página 1006 y se guardan cifrados fuera del webroot (`uploads_privados/sindicatos/<empresa>/`).
- Cambiar un dato de acceso o de producción exige una función propia ("Editar credenciales"), separada de "Editar", con su permiso en `conf__empresas_perfiles_funciones` y registro de auditoría (quién y cuándo, sin el valor).
- Botón **"Probar conexión"** por medio de pago (token, SFTP o casilla), que registra el resultado en `gestion__integraciones_resultados`.
- Los tokens OAuth obtenidos (Interbanking) se guardan cifrados con su vencimiento en `gestion__integraciones_autenticaciones` y se reutilizan mientras estén vigentes, como el TA de ARCA.

#### 7.3.2 Volante y cobro

- **El interés se calcula al emitir el volante** (decisión D3, como el legacy), con `sindi_intereses_core_model`: interés simple por tramos de `sindi__tasas_interes`, saldo × tasa mensual × días del tramo / 30, a las dos fechas de pago. Los importes `importe_1` / `importe_2` quedan fijos y son los que se publican en cada medio de pago y se codifican en las barras. El empleador paga en las bocas habilitadas dentro de un vencimiento; vencida la segunda fecha, el canal ya no lo acepta y el volante pasa a Vencido (cron) y se despublica. Para pagar, se emite un volante nuevo, que recalcula a sus nuevas fechas.
- **Cobro**: por cada línea de rendición conciliada, `sindi_cobros_core_model` abre una transacción y:
  1. identifica el volante y el vencimiento en que se pagó (fecha de pago ≤ `f_pago_1` → importe 1; ≤ `f_pago_2` → importe 2). Si el importe no coincide, la línea queda en DIFERENCIA para revisión manual;
  2. emite la **ND de intereses** por el interés **ya calculado** en el volante para ese vencimiento (no lo recalcula);
  3. crea la cobranza con su movimiento de tesorería en la cuenta del medio de pago;
  4. imputa contra las deudas del volante y su ND, en orden de vencimiento (`comprobante_id ASC FOR UPDATE`);
  5. deja `recalcularEstadoDestino()` a cargo de los estados; el volante pasa a Cancelado total.
  Es idempotente por (medio de pago, id de línea).
- **Cobro en ventanilla** (medio MANUAL): se cobra un volante vigente por su importe del vencimiento que corresponde a la fecha. Si se cobra una deuda sin volante, se emite un volante en el momento con fecha de pago = hoy, para que siga existiendo una sola forma de calcular el interés.

### 7.4 Lo que da el módulo 5 sin desarrollo nuevo

| Necesidad del sindicato | Página del módulo 5 |
|---|---|
| Proveedores, facturas recibidas, órdenes de pago | proveedores, facturas_proveedores, pagos |
| Caja, bancos, cheques, echeq | tesoreria_*, cheques_cartera |
| Cuenta corriente de empleadores (como clientes) | clientes_ctacte, comprobantes_consulta |
| Facturación (alquiler de salones, servicios), ARCA | ventas_facturas, arca_integraciones (en general el sindicato es IVA exento: comprobante C) |
| Plan de cuentas, asientos, mayores, balances | plan_cuentas, cont_asientos, cont__mayores, cont__balances |
| Stock de beneficios en productos | productos, stock_ajustes, depositos |
| Usuarios, perfiles, accesos | perfiles, perfiles_usuarios, accesos_perfiles |

Queda fuera por ahora el **RRHH propio** (`l_*`: empleados, licencias y contratos del personal del sindicato): no tiene datos en la AECBB y se solapa con `estudio_contable/licencias_gestion`. Se evalúa en la fase 5.

## 8. Portal de empleadores y estudios

### 8.1 Identidad y permisos

- Perfiles por empresa (sindicato): **Empleador**, **Estudio contable**, **Delegado**. Además, los internos: Administración, Inspector y Administrador. Los permisos van por `conf__empresas_perfiles_funciones`, como en todo el sistema, así que el sidebar de `header1.php` ya muestra solo las páginas del portal.
- Un estudio que atiende empleadores de varios sindicatos tiene perfil en cada empresa y cambia de sindicato con el selector de empresa que ya existe.

### 8.2 Acceso por empleador (`core/accesos_empleadores.php`)

Capa nueva, análoga a `AccesosSucursalesHelper`:

- Tabla `sindi__usuarios_empleadores`: `usuario_id`, `empresa_id`, `empleador_id`, rol (TITULAR / ESTUDIO / DELEGADO), vigencia y `estudio_empleador_id` de origen. La escribe el sistema, no un ABM: la crea la autoalta (TITULAR) o la aceptación de la vinculación (ESTUDIO), y la cierra la baja.
- Los perfiles internos (marcados en `sindi__parametros` o con una función "Ver todos los empleadores") y `USUARIOS_ACCESO_TOTAL` ven todo.
- API:
  - `AccesosEmpleadoresHelper::filtroSql($c, $uid, $empresa_id, 'd.empleador_id')`
  - `::filtrarEmpleadores()` para los combos
  - `::exigirAcceso($c, $uid, $empresa_id, $empleador_id)` (lanza `RuntimeException`)
  **Obligatorio en todo modelo del módulo 6 que lea o escriba por empleador.** Es la única barrera entre empleadores.

### 8.3 Autoalta y vinculación

- `modules/sindicatos/portal/registro.php` + `registro_ajax.php`: **sin sesión**, empresa resuelta por `?s=<portal_slug>` (nunca por id). Lleva captcha, límite de intentos por IP y CUIT, token CSRF y **validación del CUIT** (dígito verificador, y opcionalmente la constancia en el padrón de ARCA). Crea:
  - una solicitud de alta con un **link de verificación por mail** (token de un solo uso con vencimiento); al validarlo, el usuario elige su clave (`password_hash`);
  - la entidad con rol EMPLEADOR (o la reutiliza si el CUIT ya existe en la empresa), el usuario con perfil Empleador y la fila TITULAR.
  Si `sindi__parametros.aprobacion_autoalta = 1`, queda pendiente hasta que la administración la apruebe.
- Recupero de clave por token: **nunca se manda una clave por mail**.
- Vinculación de estudio: el estudio la pide por el CUIT del empleador (o el empleador invita al estudio) → mail → el otro la acepta o la rechaza en su bandeja → al aceptarse, se crea la fila ESTUDIO. El empleador puede revocarla cuando quiera. El sindicato puede crear vinculaciones directas (iniciado_por = SINDICATO), para los casos en que el empleador no usa el portal.
- `ajax_guard` sigue igual para las páginas con sesión. El registro usa un guard propio para lo público (`'empresa' => false` no alcanza: tiene que resolver el slug y limitar la frecuencia).

### 8.4 Qué ve el portal

Mis empleadores (el estudio, la lista; el empleador, el suyo), DDJJ (alta, nómina, importación de F.931 o planilla, presentación, constancia en PDF con código de verificación), cuenta corriente, volantes (generar, imprimir, ver el estado en cada canal), recibos, avisos (DDJJ pendientes y vencimientos) y vinculaciones.

### 8.5 Oportunidad con el módulo 4

Si el estudio también es cliente nuestro (`estudio_contable`), se puede vincular `estudio__clientes` con el empleador del sindicato y generar la DDJJ sindical desde la liquidación de sueldos del estudio. Queda fuera del alcance, pero el modelo (estudio = entidad, vinculación explícita) no lo impide.

## 9. Páginas

Bloque de `conf__paginas` **1001–1099** (libre; el módulo 5 ocupa hasta 941). Lista inicial:

| Id | Página | Tipo | Perfiles |
|---|---|---|---|
| 1001 | Parámetros del sindicato | diccionario / vigencia | Admin |
| 1002 | Convenios, categorías y escalas | diccionario | Admin |
| 1003 | Conceptos de aporte | diccionario | Admin |
| 1004 | Vencimientos por dígito | diccionario | Admin |
| 1005 | Tasas de interés | diccionario | Admin |
| 1006 | Medios de pago (configuración, datos de acceso cifrados, layout, probar conexión) | config | Admin |
| 1010 | Empleadores | ABM | Interno |
| 1011 | Estudios contables y vinculaciones | proceso corto | Interno, Estudio, Empleador |
| 1012 | Personas / trabajadores | ABM | Interno |
| 1020 | Declaraciones juradas | proceso largo master-detail | Todos |
| 1021 | Importaciones de DDJJ (F.931 / lote / mail) | proceso | Interno, Estudio, Empleador |
| 1022 | DDJJ de oficio | proceso | Interno |
| 1030 | Volantes de pago | proceso | Todos |
| 1031 | Rendiciones y conciliación | proceso | Interno |
| 1032 | Cuenta corriente de empleadores | consulta | Todos (con filtro) |
| 1040 | Tablero de fiscalización (alertas, mora, pendientes, faltantes) | consulta | Interno, Inspector |
| 1041 | Actas de inspección | proceso largo | Inspector, Admin |
| 1042 | Planes de pago | proceso largo | Admin |
| 1050 | Afiliados y grupo familiar (ficha del titular, familiares, documentos, carnet) | ABM con vigencias | Interno |
| 1051 | Solicitudes de afiliación | proceso | Interno (y Portal, si se habilita) |
| 1054 | Tipos de afiliación, parentescos y vigencias por edad | diccionario | Admin |
| 1052 | Beneficios | proceso | Interno |
| 1053 | Salones y reservas | proceso | Interno |

## 10. Procesos automáticos (cron, CLI)

Scripts de consola, sin sesión: fijan `empresa_id` por argumento y recorren las empresas con el módulo 6 habilitado. Escriben log y usan `GET_LOCK` para no correr dos veces a la vez.

| Script | Frecuencia | Hace |
|---|---|---|
| `sindicatos_abrir_periodo.php` | Mensual | Crea las filas de `sindi__empleadores_periodos` del período que abre. |
| `sindicatos_rendiciones.php` | Cada hora en horario bancario | Baja las rendiciones de cada canal (SFTP o API) y procesa las líneas conciliadas. |
| `sindicatos_publicar_deudas.php` | Cada 15 minutos | Reintenta las publicaciones fallidas y despublica los volantes vencidos o anulados. |
| `sindicatos_mail_ddjj.php` | Cada 15 minutos | Lee la casilla configurada en los parámetros del sindicato (IMAP con TLS) y crea importaciones pendientes de revisión, **sin confirmar solas**. |
| `sindicatos_mora.php` | Diario | Recalcula la categoría de mora, marca los volantes vencidos y avisa los vencimientos de actas (72 h). |

## 11. Seguridad y calidad (obligatorio)

- Patrón de `CLAUDE.md`: `ajax_guard` con `pagina_idx`, prepared statements, transacciones en toda escritura y `error_log` con prefijos.
- Ningún id de configuración fijo: comprobantes, cuentas, medios de pago y cuentas de tesorería se resuelven por código y empresa con `core/empresa_config.php`.
- **Ningún dato de conexión en el código** (§7.3.1): direcciones, entes, usuarios, claves, tokens, certificados y casillas van en los parámetros del sindicato o en sus medios de pago, cifrados con la clave del `.env`. La revisión de código (`revisor-convenciones`) lo verifica con una búsqueda de URLs, `client_id`, `password`, `imap_open`, `ssh2`/`SFTP` y archivos `.pem` / `.json` fuera de `core/secretos.php` y los drivers.
- Archivos subidos (F.931, planillas, rendiciones) en `uploads/sindicatos/<empresa>/`, fuera de las rutas ejecutables, con validación de tipo y tamaño.
- Datos personales (CUIL, salarios, afiliación sindical, que es un **dato sensible** según la Ley 25.326): solo los ve quien tiene acceso al empleador o un perfil interno. Hay que auditar quién consulta las nóminas y no mandar nóminas por mail.
- Simulador: un grupo nuevo `tests/simulacion/casos/10_sindicatos.py` cubre la DDJJ original y la rectificativa con reversión total (con y sin pagos previos), el piso por escala con SAC y las tres reglas de media jornada, el volante con dos vencimientos (pago en el primero, en el segundo y fuera de término), la rendición con pago parcial y en exceso, la línea duplicada, el plan de pagos y el aislamiento entre empleadores (un estudio no puede leer un empleador sin vínculo).

## 12. Migración desde `develops_aecbb1`

Herramienta versionada `tools/migracion_aecbb1/` (PHP CLI, no `scratch/`). Es idempotente por `legacy_id`, toma una empresa destino como parámetro y tiene un modo `--simular` que corre sobre `gestion_multipyme_sim`.

1. **Catálogos**: conceptos (con sus cuentas mapeadas a conceptos contables), categorías y escalas (incluido `develops_comun`), vencimientos, tasas (`sindi__intereses_tipos`, desde la base local), obras sociales (`sindi__obras_sociales`, desde la base local), categorías de mora y bancos. Los **medios de pago** se migran solo con su configuración no secreta (nombre, driver, ente, layout de `sindi__pagos_tipos`, prefijo de barras); los datos de acceso **se cargan a mano** en la página 1006 y se prueban con "Probar conexión" antes del corte. La regla de media jornada arranca en `COMPLETO`, que reproduce el cálculo actual.
2. **Padrón**: sociedades → `gestion__entidades` (rol EMPLEADOR) + `sindi__empleadores`; sucursales; estudios y vinculaciones (estado 10 → 1, 5 → 4, 100 → 2/25); personas, relaciones laborales y afiliaciones: `re_sindicales` → tipo ACTIVIDAD (origen DDJJ), `afiliados` + `re_sindicales_solicitudes` → VOLUNTARIA / JUBILADO / FAMILIAR (origen SOLICITUD) con su titular, `re_familiares` → parentesco del familiar, `afiliaciones_vigencias` y sus excepciones → reglas por edad. Se convierte `latin1` → `utf8mb4` y `'0000-00-00'` → `NULL`, y el CUIT `double` → `VARCHAR(11)` validado.
3. **Usuarios**: los 751 `conf__usuarios` se recrean con perfil y fila de acceso por empleador, y con **clave inutilizable + mail de activación**: los hashes DES no se migran.
4. **DDJJ completas** (cabecera, empleados, cálculos, conceptos) en su estado final, para conservar el historial de rectificativas y la fiscalización.
5. **Saldos**: por cada `sindi__trans` con `trans_pend <> 0`, un comprobante con `importe_pendiente` = saldo y el vencimiento original; un **asiento de apertura** a la fecha de corte contra `SINDI_DEUDORES_APORTES`. Los recibos, volantes y la contabilidad anteriores al corte se conservan solo como consulta histórica (tablas `sindi__hist_*` de solo lectura); no se regeneran sus asientos.
6. **Matriz empleador × período**, a partir de `sociedades_cat_ddjjs`.
7. **Validación**: por empleador, saldo legacy = saldo en el ERP; por período, total de las DDJJ; cantidad de afiliados vigentes. Además, **el motor nuevo recalcula 100 DDJJ al azar** y tiene que dar lo mismo que `ddjj_items` (así se valida la regla de §2.2 frente a sus 8 copias).
8. **Corte**: el legacy pasa a solo lectura, se despublican y republican las deudas en los canales con los ids nuevos, y hay un período de convivencia en el que el legacy solo consulta.

## 13. Fases

| Fase | Alcance | Depende de |
|---|---|---|
| F1 Base | DDL de §5.1–5.2, scripts de `conf__*` (tablas 500+, páginas 1001+), `core/secretos.php` + `SINDI_CLAVE_CIFRADO` en el `.env`, parámetros del sindicato y medios de pago (páginas 1001 y 1006), catálogos, empleadores, estudios, personas, `accesos_empleadores`, portal (registro, login, vinculación). | — |
| F2 DDJJ | `sindi_ddjj_core_model`, carga manual, importadores F.931 y planilla, confirmación con comprobantes y asientos, rectificativa, constancia PDF, matriz de períodos. | F1 + subgrupos y conceptos contables sembrados |
| F3 Cobro | Intereses, volantes, drivers (BNA barras → Interbanking → Red Link → BAPRO), rendiciones, cobro automático, cuenta corriente en el portal. | F2 + cuentas de tesorería por canal |
| F4 Fiscalización | Tablero, mora, DDJJ de oficio, actas, planes, crons. | F3 |
| F5 Afiliados | Tipos de afiliación, grupo familiar, vigencias por edad y excepciones con documento, solicitudes, cuota directa con volantes de afiliado, carnet (§5.6). Beneficios, salones y personal propio solo a pedido. | F1 (la de actividad ya entra en F2 con la DDJJ) |
| F6 Migración | Herramienta de §12, ensayo en la base de simulación, corte de la AECBB. | F2–F4 |

Para el desarrollo, el tamaño justifica la **cadena completa de agentes** (`analista-procesos` → `arquitecto-bd` → `disenador-modelo-datos` → `orquestador-dev`) a partir de este documento, con `revisor-casos-borde` sobre §7.3 (concurrencia en el cobro) y `tester-circuitos` con el grupo 10.

## 14. Decisiones pendientes

| # | Decisión | Propuesta |
|---|---|---|
| D1 | ¿Un comprobante por concepto o uno por DDJJ con detalle? | **Resuelta**: un comprobante por concepto (§7.1). |
| D2 | ¿Rectificativa por diferencia o revirtiendo todo (como el legacy)? | **Resuelta**: se revierte la DDJJ anterior completa y se carga la nueva completa (§7.1). |
| D3 | ¿El interés se devenga al emitir el volante (legacy) o al cobrar? | **Resuelta**: al emitir el volante, a sus dos vencimientos; el empleador paga dentro de un vencimiento en las bocas habilitadas y el cobro usa ese importe fijo (§7.3.2). |
| D4 | ¿La autoalta del empleador requiere aprobación del sindicato? | Parametrizable; por defecto, verificación por mail sin aprobación (como hoy). |
| D5 | ¿La afiliación declarada en la DDJJ crea la afiliación automáticamente (legacy) o la deja pendiente de confirmar? | Automática con aviso (como hoy), revisable en la página 1051. |
| D6 | Alcance del módulo de afiliados (beneficios, salones, RRHH propio, mesa de entrada) para la AECBB. | **Resuelta**: solo afiliaciones, con todos sus tipos y el grupo familiar (§5.6); beneficios, salones y personal propio, a pedido. |
| D7 | ¿Se migra el historial de recibos y volantes como comprobantes o solo como consulta? | Solo como consulta, más saldos de apertura (§12.5). |
| D8 | ¿Quién publica en los canales: un cron o el usuario al emitir? | Al emitir (sincrónico, con reintento por cron). |
| D9 | Piso por escala para la media jornada (el legacy tiene dos variantes que en la práctica no cambian el resultado). | **Resuelta**: una sola implementación, regla en los parámetros generales del sindicato (`COMPLETO` / `PROPORCIONAL` / `SIN_PISO`, §5.1); la AECBB arranca en `COMPLETO`. Falta que el sindicato confirme si quiere cambiarla. |
| D10 | ¿Obras sociales como catálogo nacional común a todas las empresas o por sindicato? | Común (es el padrón RNOS que viene en el F.931), con alta por la administración del sistema. |
