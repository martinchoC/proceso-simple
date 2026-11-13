# Módulo 6 — Sindicatos: revisión de lo desarrollado contra la especificación

**2026-10-12.** Revisa `modules/sindicatos/`, `core/accesos_empleadores.php`, `core/secretos.php`, `cron/sindicatos_*.php`, `_local/sql/mantenimiento/20261011_sindicatos_01..07` y la configuración del módulo 6 en la base local, contra `01_especificacion_modulo_sindicatos.md` v0.4.

Leyenda: ✅ hecho según la especificación · 🟡 parcial · ❌ falta · 🐞 error que hay que corregir.

## 1. Resumen

La estructura está completa para las fases 1 a 4: 38 tablas `sindi__` (bloque 501–539 de `conf__tablas`), 22 páginas (1000–1045), 13 páginas con el patrón de 4 archivos, núcleos de DDJJ, intereses, volantes, cobros, actas, planes y fiscalización, portal de autoalta y 4 crons. La regla de media jornada, el interés al emitir el volante, la reversión total por rectificativa y el cifrado de datos de acceso **están implementados en su forma**.

Los problemas están en la **integración con el núcleo del ERP** (§3): los comprobantes se escriben con ids fijos de otra empresa, los cobros no pasan por tesorería y los saldos se pisan a mano. Con eso, la cuenta corriente, la caja y el balance del sindicato en `gestion_normal` **no van a cerrar**. Esos puntos van antes que cualquier pantalla nueva.

## 2. Cobertura por fase

| Fase | Estado | Detalle |
|---|---|---|
| F1 Base | 🟡 | Tablas, catálogos, parámetros, medios de pago, empleadores, estudios, personas y portal (registro, activación y recupero) hechos. Falta la bandeja de vinculación estudio ↔ empleador del lado del empleador (página 1011 solo interna). **Sucursales de empleadores agregadas en esta revisión** (§5). |
| F2 DDJJ | 🟡 | Cálculo único con media jornada parametrizada ✅, confirmación con comprobantes y asientos 🐞 (§3), reversión total 🐞 (§3.4). **Falta la página 1021 Importación F.931 / planilla**: está en el menú, pero `ddjj_importar.php` no existe (404); `sindi_importadores.php` existe sin pantalla. |
| F3 Cobro | 🐞 | Interés por tramos al emitir ✅ (`sindi_intereses_core_model.php:113`, saldo × tasa mensual × días / 30). Los drivers son **simulaciones**: `publicarDeuda` devuelve éxito sin llamar a Red Link ni a Interbanking (`sindi_pagos_drivers.php:50`, `:211`) y no usan `secreto_descifrar`. El cobro no mueve tesorería (§3.3). |
| F4 Fiscalización | 🟡 | Actas, planes, tablero y crons de mora, apertura de período, publicación y rendiciones hechos. Mismos problemas de comprobantes que F2. Falta `sindicatos_mail_ddjj.php`. |
| F5 Afiliados | ❌ | Solo existen `sindi__afiliaciones_tipos` y `sindi__afiliaciones` (las de actividad las mantiene la DDJJ). Faltan parentescos, vigencias por edad, excepciones con documento, documentos, solicitudes y las páginas 1050, 1051 y 1054. |
| F6 Migración | ❌ | No hay `_local/tools/migracion_aecbb1/`. |
| Pruebas | ❌ | No hay grupo `_local/tests/simulacion/casos/10_sindicatos.py`. |

## 3. Integración con el núcleo del ERP (prioridad 1)

### 3.1 Ids fijos en los comprobantes 🐞

Todos los `INSERT INTO gestion__comprobantes` del módulo usan **`sucursal_id = 301`**, que es una sucursal de la **empresa 3**, y `comprobante_pv = 1`:
`sindi_ddjj_core_model.php:630`, `:692` · `sindi_cobros_core_model.php:231`, `:272` · `sindi_actas_core_model.php:307` · `sindi_planes_core_model.php:285`.
En la empresa 7 (AEC), los comprobantes quedarían con la sucursal de otra empresa. Hay que resolver la sucursal y el PV de la empresa (`core/empresa_config.php`; sumar `emp_sucursal_id()` si no existe) y usar `emp_numero_comprobante()` para mostrar.

Además, el tipo de comprobante por concepto está fijo en el código: `ART101 → DCS / RCS`, cualquier otro → `DFS / RFS` (`sindi_ddjj_core_model.php:613`, `:678`). Con un tercer concepto o con otro convenio genera el tipo equivocado. La especificación pide `sindi__conceptos.comprobante_tipo_id`, que `conceptos_model.php` todavía no guarda.

### 3.2 Numeración y espejo 🐞

- Los números salen de `SELECT MAX(comprobante_nro)+1` sin bloqueo (`sindi_ddjj_core_model.php:620`, `:683`; `sindi_cobros_core_model.php:222`, `:263`; `sindi_volantes_core_model.php:154` para `volante_id`). Dos confirmaciones simultáneas chocan con `uk_comp_numeracion`, o duplican el número del volante.
- Se inserta directo en `gestion__comprobantes` en lugar de pasar por el mecanismo de espejo (`syncComprobante` o una variante propia), así que cada núcleo repite las 20 columnas a mano y con valores distintos.
- La reversión usa `tabla_origen_id = 43` (`sindi_ddjj_core_model.php:630`), que no es una tabla del módulo. Debe ser la de `sindi__ddjj_conceptos` (524), con su propio registro.

### 3.3 Cobro fuera de tesorería 🐞

`sindi_cobros_core_model.php` no llama a `tesoreriaRegistrarMovimiento`, `tesoreriaRegistrarImputacion` ni `recalcularEstadoDestino` (ninguna aparición en todo el módulo):

- **El dinero no entra a ninguna cuenta de tesorería**: solo hay asiento. La caja y los bancos del sindicato en `gestion_normal` no ven los cobros.
- La cuenta del asiento sale del concepto `CAJA_CENTRAL` (`:139`), no de `sindi__medios_pago.tesoreria_cuenta_codigo` como pide §7.3.1.
- Pone `importe_pendiente = 0` en cada deuda del volante (`:308`) aunque el pago sea parcial, e inserta las imputaciones con SQL concatenado (`:311`, `:321`) sin el estado ni los locks de tesorería.
- Marca el volante como 40 Cancelado total siempre (`:329`).
- No controla el vencimiento en que se pagó (importe 1 o importe 2) ni la diferencia contra lo rendido, que es el paso 1 de §7.3.2.

### 3.4 Rectificativa 🐞

La reversión total está, pero:
- pone el saldo de la DDJJ anterior en 0 (`sindi_ddjj_core_model.php:663`) en lugar de imputar la reversión. Si la anterior tenía pagos, **el saldo a favor se pierde** y no se aplica a la nueva (§7.1, pasos 2 y 3);
- no anula ni despublica los volantes vigentes que incluían la DDJJ anterior.

### 3.5 SQL concatenado

Además de lo de §3.3, hay `UPDATE`/`INSERT` con variables interpoladas en `sindi_ddjj_core_model.php:663`, `:704`, en el `UPDATE sindi__ddjj ... rectificadaId` y en `sindi_cobros_core_model.php:329` y siguientes. Son enteros casteados, así que no es inyectable hoy, pero rompe la convención (prepared statements siempre).

## 4. Seguridad y convenciones

| # | Hallazgo | Dónde |
|---|---|---|
| 1 🐞 | **Cuatro `_ajax.php` sin control de acceso**: cargan `ajax_guard.php` pero no llaman `ajax_guard()`, y toman la empresa con `$_SESSION['empresa_id'] ?? 3` y el usuario con `?? 1` (superadmin). Sin sesión, operan sobre la empresa 3 como superadmin. | `actas_ajax.php:20`, `planes_ajax.php:20`, `ddjj_oficio_ajax.php:20`, `fiscalizacion_tablero_ajax.php:20` |
| 2 🐞 | Perfiles "internos" fijos por id (`[1, 2, 601, 602, 603]`). En otra empresa o con otros perfiles, un usuario interno no ve ningún empleador o, al revés, un perfil 2 de otro módulo ve todos. Debería salir de una función de página ("Ver todos los empleadores") o de `sindi__parametros`. | `core/accesos_empleadores.php:53` |
| 3 🟡 | La autoalta asigna el perfil por `perfil_id_base = 604` fijo. | `portal/registro_ajax.php:185` |
| 4 🟡 | Rol de la entidad fijo por id (5 y 6) con `INSERT IGNORE` sobre una tabla sin clave única: duplicaba el rol en cada guardado. **Corregido** en esta revisión (`sindi_asegurar_rol_entidad`, por código). | empleadores, estudios, portal |
| 5 🟡 | Funciones fuera del bloque acordado (9001–9999): 3402 y 3403 (dentro del rango de `conf/gestion` 3335+, riesgo de choque con producción) y 10020, 10030, 10031, 10045. | `conf__paginas_funciones` |
| 6 🟡 | Las páginas 1020, 1030, 1031 y 1045 solo tienen la función "Acceder": no hay transiciones en el motor de estados. Los botones de la DDJJ (confirmar, rectificar, anular) están fijos en `ddjj.js:454` y siguientes. | `conf__paginas_funciones`, `ddjj.js` |
| 7 🟡 | El código de barras usa el prefijo de la AECBB como valor por defecto y no el de `sindi__medios_pago.barras_prefijo`. | `sindi_volantes_core_model.php:82`, `:159` |
| 8 🟡 | La página 1040 (nodo de menú) tiene `tabla_id = 508` y la 1045 no tiene tabla. | `conf__paginas` |

## 5. Cambios hechos en esta revisión: sucursales de empleadores

Pedido: que los empleadores tengan sucursales y que al dar de alta uno nuevo se cree automáticamente la sucursal central número 1.

| Archivo | Cambio |
|---|---|
| `_local/sql/mantenimiento/20261012_sindicatos_08_sucursales_empleadores.sql` | Agrega `gestion__entidades_sucursales.sucursal_numero` (NULL para clientes y proveedores, que no lo usan) y la clave única `(entidad_id, sucursal_numero)`. Numera las sucursales existentes de empleadores y estudios (Casa Central primero) y crea la Casa Central de los empleadores que no tenían. Es reanudable y se corrió dos veces en la base local. |
| `sindi_base_model.php` | `sindi_guardar_sucursal_central()`: crea la N° 1 "Casa Central" o actualiza sus datos de contacto. `sindi_asegurar_rol_entidad()`: rol por código, sin duplicar. |
| `empleadores_model.php` / `_ajax.php` | El alta usa los dos helpers. Antes, cada guardado agregaba otra "Casa Central" (el `ON DUPLICATE KEY` nunca se disparaba porque la tabla no tenía clave única). El listado ahora toma los datos de contacto de la N° 1: con varias sucursales activas, cada empleador salía repetido. Funciones nuevas: listar, alta y edición (número siguiente, con el empleador bloqueado), habilitar e inhabilitar (la N° 1 no se puede inhabilitar), búsqueda de localidades por nombre o CP. |
| `empleadores.php` / `.js` | Columna "Sucursales" en el listado y sección de sucursales en la ficha del empleador, con su propio modal. |
| `portal/registro_ajax.php`, `estudios_model.php` | La autoalta y el alta de estudios usan los mismos helpers (tenían el mismo duplicado). |

Prueba por consola contra la base local (alta, segundo guardado, sucursal 2, intento de inhabilitar la Central, aislamiento entre empresas, listado y rol): todo OK, con los datos de prueba borrados al final. Respaldo previo de las tablas tocadas en `_local/tests/simulacion/salidas/backup_entidades_sucursales_20261012.sql`.

**Pendiente para que la sucursal sirva en el circuito**: registrar en cada renglón de la DDJJ la sucursal donde trabaja el empleado (`sindi__ddjj_empleados.sucursal_id`, por defecto la N° 1) y heredarla en la relación laboral. Es lo que permite a fiscalización y a las actas trabajar por establecimiento, como hacía el legacy con `sindi__sociedades_sucursales`. Está en la especificación (§5.2 y §5.3) y no se implementó todavía.

## 6. Orden de corrección propuesto

1. **Seguridad**: `ajax_guard()` en los cuatro `_ajax.php` (§4.1) y perfiles internos sin ids fijos (§4.2).
2. **Comprobantes**: sucursal, PV y tipo por concepto resueltos por empresa y código; numeración con bloqueo; un único helper de espejo para el módulo (§3.1, §3.2).
3. **Cobro por tesorería**: movimiento en la cuenta del medio de pago, imputaciones con `tesoreriaRegistrarImputacion` y estados con `recalcularEstadoDestino`; control del vencimiento pagado (§3.3).
4. **Rectificativa**: imputar la reversión, trasladar el saldo a favor y anular los volantes vigentes (§3.4).
5. Grupo 10 del simulador cubriendo 2 a 4, antes de seguir con pantallas.
6. Página 1021 (importación), funciones del motor de estados para 1020/1030/1031 con ids dentro del bloque, sucursal en la nómina de la DDJJ.
7. Drivers reales (con `secreto_descifrar`), F5 afiliaciones y F6 migración.

## 7. Correcciones aplicadas (2026-10-12, segunda parte)

| Punto | Estado | Qué se hizo |
|---|---|---|
| §4.1 `_ajax.php` sin control | ✅ | `actas`, `planes`, `ddjj_oficio` y `fiscalizacion_tablero` llaman `ajax_guard()` (sin sesión responden 401), toman empresa y usuario de `$ctx` y exigen acceso al `empleador_id` pedido. |
| Acceso a actas y planes de otra empresa (nuevo) | ✅ | Las funciones de núcleo de actas y planes reciben solo el id. Los dos `_ajax.php` verifican que el acta o el plan pedido sea de la empresa y de un empleador accesible (`sindi_exigir_registro_empresa()`). |
| §4.2 perfiles internos fijos | ✅ | Acceso total = función **"Ver todos los empleadores"** (16100, página 1010), asignada a los perfiles 601/602/603 y a los administradores 1/2 de las empresas con el módulo 6 (script 09). Probado: interno ve todo, Empleador solo el suyo, perfil de otro módulo nada. |
| §4.5 bloque de funciones | ✅ | Todas las funciones del módulo pasan a **16001–16999** con sus permisos (script 09). El bloque 9001–9999 choca con el módulo 5. Nombres de la página 1010 corregidos. |
| §3.1 / §3.2 comprobantes | ✅ | `sindi_comprobantes_core.php`: sucursal y PV de la empresa, numeración con `gestion__comprobantes_numeradores` bloqueado, uno por origen (una confirmación repetida no duplica), estado 5 con saldo. La usan DDJJ, reversiones, ND de intereses, recibos, actas, cuotas y refinanciación. Tipo y cuenta de recurso por concepto (`sindi__conceptos`, script 10 y pantalla de Conceptos). |
| §3.3 cobro | ✅ | Ingreso en la cuenta de tesorería del medio de pago, asiento contra su cuenta contable, imputaciones con `tesoreriaRegistrarImputacion` por vencimiento de la deuda, volante 39/40 según saldo. Controla duplicados, volante anulado o vencido, pago después del 2.º vencimiento e importe distinto (quedan para revisión, sin mover dinero). El mismo archivo no se registra dos veces. |
| §3.4 rectificativa | ✅ | La reversión se imputa contra el saldo de la anterior; lo pagado queda como saldo a favor y se aplica a la nueva del mismo concepto. Los volantes vigentes de la anterior pasan a Anulado y el cron los despublica. La DDJJ se bloquea al confirmar. |
| Plan de pagos (nuevo) | ✅ | Crédito RPL por el capital, que tiene que coincidir con el saldo de las deudas elegidas (script 11 crea el tipo), cuotas como deudas nuevas y asiento del interés de financiación. La anulación, sin cobros en las cuotas, devuelve el saldo a las deudas de origen. |
| Actas (nuevo) | ✅ | Comprobante por el helper. La anulación no procede si el acta tiene cobros o plan, y revierte el asiento. |
| Volantes | ✅ | Deudas leídas con el empleador bloqueado (dos volantes no toman la misma deuda), sin `MAX+1`, prefijo de barras del medio Banco Nación. |
| §3.5 SQL concatenado | ✅ | Reemplazado en DDJJ, cobros y volantes. |

**Prueba de punta a punta** sobre `gestion_multipyme_sim` (copia recreada; empresa 3 con una cuenta bancaria y su cuenta de tesorería de prueba). Circuito: DDJJ, volante, cobro en el primer vencimiento con ND de intereses, pago rendido dos veces, rectificativa +20 % sobre DDJJ pagada, plan sobre la diferencia y anulación del plan. Resultado: 26/26 controles OK, con asientos balanceados en cada paso.

**Configuración pendiente de la empresa 7 (AEC)** para poder operar: no tiene plan de cuentas, tipos de asiento, moneda, punto de venta, tipos de comprobante sindicales ni cuentas de tesorería. Sus conceptos `SINDI_*` apuntaban a cuentas de la empresa 3 y el script 10 los borró. Mientras falte esa configuración, el módulo responde con un error explícito que dice qué falta.

**Pendiente**: grupo 10 del simulador (la prueba anterior sirve de base), página 1021, transiciones del motor de estados para 1020/1030/1031, sucursal en la nómina, drivers reales, F5 y F6. Los 74 comprobantes de prueba que el código anterior dejó en la empresa 3 (estado 1, sucursal 301) quedan como estaban.

**Scripts para producción, en orden**: `20261011_sindicatos_01` a `07`, y después `20261012_sindicatos_08` (sucursales), `09` (bloque de funciones y permiso), `10` (tipos y cuentas por concepto) y `11` (tipo RPL). Todos son reanudables.

## 8. Configuración de la AEC, permisos del portal y simulador (2026-10-12, tercera parte)

| Tema | Qué se hizo |
|---|---|
| Alta contable de la empresa sindicato | Script `20261012_sindicatos_12_alta_empresa_sindicato.sql`, parametrizado con `@emp` (7 en local). Crea, sin ids fijos y solo lo que falte: moneda ARS; boca y punto de venta de emisión sindical; tipos de asiento MAN y AUT; **plan de cuentas de asociación sindical** (61 cuentas, 41 imputables: caja y bancos con una cuenta por canal de recaudación, créditos por aportes y cuotas, bienes de uso, deudas comerciales, sociales y fiscales, fondo social, recursos por aportes, cuota sindical, actas, intereses, multas, y gastos generales y de acción gremial); 15 conceptos contables (`SINDI_*` y los generales de tesorería; sin IVA ni mercaderías, porque el sindicato es exento); grupo, subgrupo y los 10 tipos sindicales habilitados en el PV; 6 cuentas de tesorería (Caja Sede, Banco Nación recaudadora, Banco Provincia, recaudación Red Link e Interbanking, valores a depositar) asignadas a los medios de cobro; tipos y cuenta de recurso de los conceptos ART100/ART101. Corrido dos veces en la base local. |
| Permisos de los perfiles externos 🐞 | Los scripts 02/04 habían dado a Empleador (604), Estudio (605) y Delegado (606) **todas** las funciones: configuración del sindicato (incluida Medios de Cobro, con los datos de acceso a los bancos), Personas, actas, planes, DDJJ de oficio, rendiciones, fiscalización y alta e inhabilitación de empleadores. Script `13` los deja en lo que define la §8.4: Empleadores (solo Editar), vínculos de estudios, DDJJ, importación, volantes y cuenta corriente. Corrige además los nombres de las funciones de la página 1011. |
| Listados de actas y planes | Filtran por los empleadores a los que el usuario tiene acceso (segunda barrera, además del permiso de página). |
| Simulador | Grupo `sindicatos` (`_local/tests/simulacion/casos/16_sindicatos.py`, 10 casos y 49 controles más la consistencia global) sobre la empresa 7: **57/57 OK**. Regresión del grupo `circuitos` (compras, pagos, ventas, cobranzas): **102/102 OK**, incluida la columna nueva de sucursales. |

**Scripts para producción, en orden**: `20261011_sindicatos_01` a `07`, `20261012_sindicatos_08` a `11`, `12` (cambiar `@emp` por el id de la AEC en producción) y `13`.

## 9. Motor de estados, importación y sucursal en la nómina (2026-10-12, cuarta parte)

| Tema | Qué se hizo |
|---|---|
| Botones de DDJJ, volantes y rendiciones | Script `14`: funciones del motor por estado de origen (DDJJ: Nueva, Editar, Presentar, Anular, Constancia, Rectificar, Ver; volantes: Emitir, Ver/imprimir, Anular; rendiciones: Subir, Ver líneas, Procesar), asignadas a quien tenía "Acceder" en cada página. Quita además los estados duplicados de las tablas 528 y 532. Las tres pantallas muestran los botones que arma el servidor en lugar de los fijos del JavaScript. |
| Permisos por función 🐞 | `sindi_obtener_botones_acciones` mostraba todas las funciones de la página a cualquier usuario con acceso. Ahora filtra por las funciones del perfil (`sindi_acciones_permitidas`), y las acciones de estado se exigen en el servidor (`sindi_exigir_funcion` en confirmar, anular, rectificar, importar, emitir, procesar...). `sindi_ejecutar_transicion` busca la función del estado actual (una acción puede tener una fila por estado de origen). Cada `_ajax.php` fija el contexto con `sindi_contexto($ctx)`. |
| Página 1021 Importación | Script `15` (página bajo el menú de aportes, estados Procesada / Con errores, funciones Importar y Ver detalle) y `ddjj_importar.php` + `_ajax` + `_model` + `.js`: F.931 / SICOSS o planilla CSV, crea o actualiza el borrador de la DDJJ del período, guarda el archivo con nombre aleatorio en `uploads/sindicatos/<empresa>/importaciones/` (la carpeta niega el acceso web), y registra filas leídas, aceptadas, rechazadas y el log. Solo `.txt` / `.csv` de hasta 5 MB. |
| CUIL en la nómina 🐞 | El borrador manual descartaba **en silencio** los renglones con CUIL de largo incorrecto y no validaba el dígito verificador (el importador sí). Ahora rechaza el guardado con el CUIL y el nombre del renglón. |
| Sucursal en la nómina | Script `16`: `sindi__ddjj_empleados.sucursal_id` y `sindi__relaciones_laborales.sucursal_id`, existentes en la Casa Central. El renglón valida que la sucursal sea del empleador y, si no viene, usa la del último renglón presentado o la Casa Central; la rectificativa la conserva. Combo de sucursales en el modal del empleado y columna en la grilla. |
| Pérdida de datos al editar la nómina 🐞 | Al abrir una DDJJ o editar un renglón en pantalla se perdían obra social, novedad, motivo y fecha de baja (y jubilado): al guardar quedaban vacíos. Además la fecha de baja se grababa con tipo entero. Corregido. |
| Variable de módulo | Siete páginas definían `$modulo_idx`; pasan a `$modudo_idx` como el resto del proyecto (`header1.php` aceptaba las dos). |
| Simulador | `run_ajax.php` admite archivos (`_files`) para probar subidas. Grupo `sindicatos`: **76/76 OK**. |

**Datos locales**: 14 renglones de nómina huérfanos (DDJJ 24 a 30, que ya no existen) de pruebas anteriores; no se borraron.

**Pendientes nuevos**: la DDJJ no mantiene `sindi__relaciones_laborales` (nadie la escribe; fiscalización la lee) ni las actas usan todavía la sucursal.

**Scripts para producción, en orden**: `20261011_sindicatos_01` a `07`; `20261012_sindicatos_08` a `11`; `12` (con `@emp` de la AEC); `13` a `16`.

## 10. Relaciones laborales (2026-10-12, quinta parte)

`sindi__relaciones_laborales` no la escribía nadie (solo la leían fiscalización, DDJJ de oficio y Personas). Ahora la mantiene la presentación de la DDJJ, dentro de su transacción (`sindi_actualizar_relaciones_laborales`):

| Situación | Efecto |
|---|---|
| Persona sin relación abierta con el empleador | Alta desde el primer día del período, con la DDJJ de alta y la sucursal del renglón. |
| Relación abierta | Si el período es anterior a su alta, el alta se adelanta. La sucursal se actualiza solo si no hay una DDJJ presentada posterior del empleador. |
| Renglón con baja (motivo o fecha) | Cierra la relación con la fecha del renglón o el último día del período, con el motivo y la DDJJ de baja. |
| Rectificativa | Antes de aplicar la nómina nueva, anula (38) las altas que hizo la rectificada de personas que ya no figuran y reabre las bajas que ella cerró. |
| Relación abierta de alguien que no figura en la DDJJ | No se toca: fiscalización lo detecta como omitido. |

Además, la DDJJ de oficio armaba la nómina con **todas** las relaciones del empleador, incluidas las terminadas, y su listado contaba como "empleados activos" relaciones dadas de baja y de cualquier empresa. Ahora ambas consideran solo relaciones abiertas de la empresa.

Simulador: caso 173 (7 controles). Grupo `sindicatos`: **83/83 OK**.

Las DDJJ presentadas antes de este cambio en la base local no generaron relaciones; en producción no hay datos previos del módulo, y la migración (F6) va a construir las relaciones desde `sindi__re_laborales` del sistema actual.

## 11. Fase 5: afiliaciones y grupo familiar (2026-10-12, sexta parte)

| Parte | Qué se hizo |
|---|---|
| Base de datos | Script `17`: parentescos (con su inverso), vigencias por edad con excepción por documento, documentos presentados, solicitudes, cuotas directas; `sindi__afiliaciones.ddjj_baja_id`; tipo de comprobante CAF, rol de entidad AFIL, concepto `SINDI_CUOTAS_AFILIADOS`; tablas 540-544, páginas 1050, 1051 y 1054, estados, funciones 16300-16344 y permisos (601/602 y administradores: todo; 603 inspectores: consulta; externos: nada). Siembra por empresa: 7 parentescos y la regla de hijos (hasta 21, hasta 25 con certificado de alumno regular válido 12 meses). |
| Corrección de datos 🐞 | La confirmación de DDJJ creaba **una afiliación de actividad nueva en cada presentación** (sin clave única: la persona 1 tenía 25) y siempre con el tipo de la **empresa 3**. El script remapea el tipo por código, anula las repetidas y numera las vigentes. |
| Núcleo | `sindi_afiliaciones_core_model.php`: número de afiliado de la persona (lo comparten sus afiliaciones titulares; familiares con sufijo /01, /02...), alta de titulares y familiares con control de parentesco, edad y vigencia, baja en cascada al grupo familiar, documentos que extienden la cobertura y reactivan una vencida, vencimientos con aviso a 30 días, afiliación de actividad desde la DDJJ (una por persona y empleador; baja por desafiliación o baja laboral; la rectificativa deshace lo de la anterior) y cuota directa mensual (comprobante CAF a la entidad del afiliado, con asiento, idempotente por período). |
| Páginas | 1050 Afiliados y Grupo Familiar (listado con filtros, ficha con familiares y documentos, alta directa, familiares, documento con archivo, baja); 1051 Solicitudes (Borrador → Pendiente → Aprobada / Rechazada; la aprobación crea persona y afiliación); 1054 Tipos, Parentescos y Vigencias (cuota de cada tipo, catálogo de parentescos y reglas de edad). |
| Proceso diario | `cron/sindicatos_afiliaciones.php`: vencimientos, lista de avisos y cuotas del mes. |
| Simulador | Caso 174 (17 controles) y `_local/tests/simulacion/run_cron.php` para correr un cron sobre la copia. Grupo `sindicatos`: **100/100 OK**. |

**Pendiente de la fase 5**: enviar por mail los avisos de vencimiento (hoy el proceso los lista) y solicitudes desde el portal. El cobro de las cuotas directas quedó resuelto en la sección 12 y el carnet en la 13.

## 12. Cobro de cuotas directas: volantes de afiliado (2026-10-14)

| Parte | Qué se hizo |
|---|---|
| Base de datos | Script `18`: `sindi__volantes.empleador_id` admite NULL (el volante de afiliado lleva `persona_id` y `tipo_volante = 'AFILIADO'`; la FK se mantiene), índice por persona y función "Emitir volante" (`volante`, 16309/16310) de la página 1050 para titulares activos o dados de baja, con los permisos de la página (1, 2, 601, 602; inspectores y externos no). |
| Volantes | `sindi_emitir_volante()` recibe empleador **o** persona (uno de los dos): con persona bloquea la fila de la persona y toma las deudas de su entidad (`sindi_obtener_deudas_pendientes_entidad`, que ahora usa también el de empleador); intereses, código de barras, canales y numeración son los mismos. Consulta y listado muestran el nombre y CUIL del afiliado; un volante sin empleador solo lo ven usuarios con acceso total a empleadores (los externos no lo listan ni lo abren por id). |
| Cobro | La rendición encuentra el volante por empleador o por persona y, si es de afiliado, cancela contra la cuenta del concepto `SINDI_DEUDORES_AFILIADOS` (la misma que debitó la cuota CAF) en lugar de deudores por aportes; si la empresa no tiene ese concepto, la línea queda en DIFERENCIA con el motivo. |
| Pantalla | Ficha del afiliado (página 1050): cuotas pendientes fuera de volantes vigentes, selección, vencimientos y "Emitir volante", más los últimos volantes del titular con su estado. El volante también aparece en Volantes (1030), desde donde se anula como cualquier otro. |
| Simulador | Caso 174 (+12 controles): la ficha ofrece la cuota, rechaza familiar y deudas de un empleador, volante AFILIADO sin empleador, la cuota no entra en dos volantes, listado general, usuario Empleador sin acceso, rendición del Banco Nación que cancela CAF y volante, ingreso en BNA_RECAUDA y asiento contra deudores por cuotas de afiliados. Grupo `sindicatos`: **112/112 OK**. |

## 13. Carnet imprimible del afiliado (2026-10-14)

| Parte | Qué se hizo |
|---|---|
| Base de datos | Script `20261014_sindicatos_19_carnet.sql`: función "Carnet" (`carnet`, 16311) de la página 1050 para afiliaciones activas, con los permisos de la página (1, 2, 601, 602; inspectores y externos no). |
| Impresión | `modules/sindicatos/afiliados_carnet.php`: tarjeta CR80 (85,6 x 54 mm) en hoja A4, frente y dorso uno al lado del otro con línea de corte. Frente: logo y nombre del sindicato (logo de Parámetros de Empresa, color de acento y tipografía de `gestion__empresas_impresion`), apellido y nombre, CUIL, tipo de afiliación y empleador (titular) o parentesco y titular (familiar), N° de afiliado, "afiliado desde" o "válida hasta" (vigencia del familiar) y QR. Dorso: texto al pie de la configuración de impresión (o una leyenda por defecto), firma, contacto del sindicato y el código. `grupo=1` imprime el titular y sus familiares activos; sin grupo, solo la afiliación pedida. Solo se emite para afiliaciones activas (y familiares con el titular activo). |
| QR | Lleva un código `empresa-afiliación-fecha de emisión-firma` (HMAC con `SINDI_CLAVE_CIFRADO` del `.env`), sin datos personales, dentro de una URL a `afiliados_carnet.php?verificar=`. La verificación requiere sesión y la función `ver` de la página 1050 (personal del sindicato): informa si la credencial es auténtica y el estado actual de la afiliación (vigente, baja, vencida, cobertura hasta). Un carnet de una afiliación dada de baja sigue siendo auténtico pero verifica como no vigente. |
| Núcleo compartido | `impresion_logo_empresa()` en `core/impresion.php` (logo de la empresa como data URI, con el mismo control de ruta que la factura). |
| Pantalla | Listado: botón "Carnet" del motor en las afiliaciones activas (imprime el grupo). Ficha: "Carnets del grupo" y un botón por familiar activo. |
| Simulador | Caso 174 (+8 controles): grupo completo, familiar solo, código firmado, verificación vigente, código adulterado, usuario Empleador sin acceso, verificación no vigente y rechazo de impresión después de la baja. Grupo `sindicatos`: **120/120 OK**. |

Una verificación pública (sin sesión, para prestadores) no se hizo a propósito: expondría datos de afiliados. Si se pide, mostrar solo vigente / no vigente y las iniciales.

**Scripts para producción, en orden**: `20261011_sindicatos_01` a `07`; `20261012_sindicatos_08` a `11`; `12` (con `@emp` de la AEC); `13` a `18`; `20261014_sindicatos_19`.
