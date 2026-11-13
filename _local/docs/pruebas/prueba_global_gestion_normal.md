# Prueba global de Gestión Normal (módulo 5)

Versión 0.4 — 2026-10-19 — diseño ejecutado (resultados en §9; diagnóstico y ABM de monedas / cuentas de tesorería en §10).

Objetivo: probar `modules/gestion_normal` de punta a punta, desde el **alta de una empresa nueva** hasta
**20 operaciones por empresa con retenciones recibidas** (IVA, Ganancias, IIBB en cobranzas), en dos empresas
nuevas: una de **SERVICIOS** y una de **BIENES**. Las operaciones se ordenan en **niveles de complejidad**: un nivel se
ejecuta solo cuando el anterior pasó (o sus fallas quedaron anotadas como conocidas), así cada falla se aísla.

## 1. Vehículo de ejecución

- El simulador de `_local/tests/simulacion` (llama a los `_ajax.php` reales con `modulo='gestion_normal'`).
  Corre sobre la copia `gestion_multipyme_sim`; al final, opcionalmente, una corrida con `--base-local` para
  revisar las dos empresas en pantalla (con `mysqldump` previo).
- Grupo nuevo `global_normal` en `run.py`, un archivo por nivel (se pueden correr sueltos con `--sin-clonar`):

| Archivo | Nivel |
|---|---|
| `casos/20_global_n0_alta_empresas.py` | N0 — alta y puesta en marcha |
| `casos/21_global_n1_basico.py` | N1 — OP01–OP04 |
| `casos/22_global_n2_retenciones.py` | N2 — OP05–OP09 |
| `casos/23_global_n3_circuitos.py` | N3 — OP10–OP13 |
| `casos/24_global_n4_correcciones.py` | N4 — OP14–OP17 |
| `casos/25_global_n5_fiscal.py` | N5 — OP18–OP20 + cierre |

  Los ids que crea N0 (empresas, sucursal, PV, caja, banco, clientes, productos) se guardan en
  `salidas/ctx_global_normal.json` y los leen los niveles siguientes (como `ctx_compras.json` de 01 → 02).
- Las fechas de las operaciones van en **un mes cerrado** (el mes anterior a la corrida) para que N5 pueda liquidar
  IVA y cerrar períodos sin depender del día; OP con período de cómputo distinto usan el mes corriente.

### Ajustes previos al simulador (prerrequisito)

1. `stock()` y `marcas()` / `verificar_consistencia()` de `run.py` tienen `empresa_id=2` fijo: pasarlos a todas las
   empresas (clave `(empresa, boca, producto)`), si no la consistencia global no ve las empresas nuevas.
2. Helper `brecha(texto, detalle)`: registra en el resumen algo que **no se puede hacer desde la aplicación** (sin
   contar como falla) y un `siembra_sql()` que lo completa por SQL para poder seguir. El resumen lista las brechas
   aparte: son hallazgos del diseño de producto, no bugs.
3. Helper `verificar_operacion(op)`: el bloque de chequeos estándar de la §4, para no repetirlo en las 40 operaciones.

## 2. Las dos empresas

| | Empresa S (servicios) | Empresa B (bienes) |
|---|---|---|
| Razón social | `SIM Servicios Patagonia SRL` | `SIM Distribuidora Valle SA` |
| CUIT | 30-71xxxxxx-x válido (dígito verificador) | ídem, otro |
| Condición | Responsable Inscripto | Responsable Inscripto |
| `tipo_operacion` | SERVICIOS | BIENES |
| IIBB | Local Neuquén | Convenio Multilateral, sede Río Negro, altas en Neuquén y Buenos Aires |
| Copia de configuración desde | empresa 6 (Developsam, servicios) | empresa 2 (Casa Lucho, bienes) |
| Qué vende | 4 servicios sin stock (abono, hora técnica, implementación, soporte) | 4 productos con stock controlado + 1 servicio (flete) |
| Clientes | 6: RI agente de retención IVA/Gan/IIBB NQN, RI agente solo IIBB, monotributo, exento, consumidor final, RI de otra provincia (ARBA) | ídem, más un cliente CM con sucursal en Río Negro |

Los clientes que retienen son los que en la vida real son agentes (grandes empresas, organismos): la retención
la decide el cliente, el sistema solo la registra; los importes de la prueba se fijan a mano (p. ej. IVA 10,5 % del
IVA facturado, Ganancias 2 % del neto, IIBB 3 % / 1,5 % del neto) y cada chequeo compara contra ese número.

## 3. Nivel 0 — alta y puesta en marcha (sin operaciones)

Por la aplicación, en este orden (cada paso con su chequeo):

| Paso | Página / endpoint | Chequeo |
|---|---|---|
| 0.1 | Empresas (`conf/empresas_ajax.php` `agregar`) | fila en `conf__empresas` con `tipo_operacion`; rechazos: sin CUIT, sin localidad |
| 0.2 | Empresas – Módulos (`conf/empresas_modulos_ajax.php` `agregar` módulo 5 + `copiar_perfiles`) | rechazo si se intenta además el módulo 2 (excluyentes); perfiles 501–508 copiados; en S **sin** funciones de páginas `requiere_bienes`, en B con ellas |
| 0.3 | Usuario no superadmin con perfil Administrador en la empresa (por SQL: no es lo que se prueba) | `ajax_guard`: 200 en su empresa, 403 en la otra empresa nueva |
| 0.4 | Autorización de copia (`empresas_autorizaciones_copia_ajax.php` `guardar`, sesión en la empresa origen) | fila vigente con los `permite_*` |
| 0.5 | Asistente de inicio (`empresa_inicio_ajax.php`): `obtener_diagnostico` → `previsualizar_copia` → `ejecutar_copia` → `guardar_tipo_operacion` → `crear_sucursal_rapida` → `asignar_accesos_globales` | diagnóstico antes/después; plan de cuentas, conceptos, roles, tipos (subgrupos remapeados), condiciones de pago, listas y parámetros copiados con ids de la empresa nueva (`arca_habilitado = 0`); sucursal + depósito + PV 0001 |
| 0.6 | Completar lo que la copia no trae, **por su página si existe**: alícuotas IVA (565), medios de pago incl. `RET_IVA` / `RET_GAN` / `RET_IIBB` (620), config de impuestos de retención sufrida por provincia con su cuenta (573), PV ↔ tipos de comprobante (570), tipos de producto (596), inscripciones IIBB en Control Fiscal (625, solo B) | cada maestro existe con `empresa_id` de la empresa nueva y cuentas de su propio plan |
| 0.7 | Lo que no tiene página en el módulo 5 (hoy: **monedas**, **cuentas de tesorería** caja/banco, **tipos de asiento**, numeradores, cancelaciones): se registra como `brecha()` y se siembra por SQL | — |
| 0.8 | Maestros de negocio: clientes (`clientes_ajax.php`, con condición fiscal e IIBB), proveedores, productos / servicios (`productos_ajax.php`), lista de precios | B: los productos con stock; S: tipo servicio sin stock |
| 0.9 | Stock inicial (solo B): factura de proveedor confirmada con 4 productos | stock = cantidades, costo cargado, asiento |

Criterios de salida de N0:
- El diagnóstico del asistente no marca nada pendiente.
- **Aislamiento**: ninguna fila de las empresas nuevas apunta a un id de otra empresa (cuentas contables de
  medios, conceptos, config de impuestos, tesorería, PV, tipos de comprobante, listas, subgrupos).
- Menú: con el perfil Administrador, S no ve páginas `requiere_bienes` y B sí.

## 4. Chequeos estándar de cada operación (`verificar_operacion`)

1. Respuesta del ajax OK; borrador → confirmado con el `accion_js` de `conf__paginas_funciones`.
2. Número correlativo sin huecos por PV + tipo; espejo en `gestion__comprobantes` (estado, número, total, signo).
3. Saldo (`importe_pendiente`) y estado derivado 5 / 39 / 40 de cada factura tocada; imputaciones activas = lo cobrado.
4. Tesorería: solo los medios de dinero mueven caja/banco/valores; **las retenciones no mueven tesorería**.
5. Asiento balanceado, todas sus cuentas de la empresa (`emp_validar_cuentas_asiento`): retención IVA y Ganancias
   al Debe de la cuenta del medio, IIBB al Debe de la cuenta de su provincia en la config; Haber Deudores por ventas.
6. Línea de retención en `gestion__cobranzas_medios`: impuesto del medio, jurisdicción (solo IIBB, solo
   provincias), certificado, estado del certificado, `retencion_periodo_computo`.
7. Stock (solo B, operaciones con mercadería): movimiento en el libro y saldo materializado.
8. Cuenta corriente del cliente (`clientes_ctacte_ajax.php`) = suma de saldos de sus comprobantes.

## 5. Las 20 operaciones

Mismas 20 en las dos empresas; la columna "S / B" dice qué cambia. Importes base: factura de 100.000 + IVA.

### N1 — básico, sin y con una retención (OP01–OP04)

| OP | Operación | S / B | Qué prueba |
|---|---|---|---|
| 01 | Factura A cta. cte. + recibo en efectivo por el total, sin retenciones | S: servicio; B: producto (baja stock) | circuito base: numeración, espejo, saldo 0 → 40, caja, asientos |
| 02 | Factura A + recibo: transferencia + **retención IVA** con certificado | | retención única: no mueve banco, Debe a la cuenta del medio, factura en 40 |
| 03 | Factura A + recibo: efectivo + **retención Ganancias** | | ídem con Ganancias |
| 04 | Factura A + recibo: efectivo + **retención IIBB provincia local** (S: Neuquén; B: Río Negro) | | IIBB con jurisdicción y cuenta de la provincia |

### N2 — retenciones combinadas (OP05–OP09)

| OP | Operación | S / B | Qué prueba |
|---|---|---|---|
| 05 | Un recibo con IVA + Ganancias + IIBB local + transferencia | | cuatro medios, cada retención a su cuenta |
| 06 | IIBB de **otra provincia** (ARBA) | S: brecha esperada si la empresa no tiene config de ARBA → rechazo con mensaje claro; B: CM con alta en ARBA, va a su cuenta | config por jurisdicción |
| 07 | Retención con **certificado PENDIENTE** (sin número) | | se guarda PENDIENTE, período de cómputo = mes del recibo |
| 08 | **Cobro parcial** con retención (factura de 121.000, cobra 60.000 + ret. IVA 2.100) | | factura en 39 con saldo exacto; segundo recibo que completa → 40 |
| 09 | Un recibo que cancela **tres facturas** con una retención de Ganancias global | B: una de las facturas viene de remito (N3 la usa) | imputación a varias facturas; la retención forma parte del total imputado |

### N3 — por otras páginas y otros medios (OP10–OP13)

| OP | Operación | S / B | Qué prueba |
|---|---|---|---|
| 10 | **Cobro desde la factura** (`ventas_facturas_ajax.php` cobro, `cobro_maestros`) con retención IIBB | | el atajo de la factura registra igual que el recibo |
| 11 | Recibo con **cheque de terceros** + retención IVA; el cheque se deposita en Movimientos Internos | | Cartera de Cheques, luego banco; la retención nunca toca valores |
| 12 | **NC parcial** sobre una factura y recibo con retención por el saldo neto | S: NC por bonificación; B: NC por devolución (vuelve stock) | saldo neto de NC; retención sobre lo realmente cobrado |
| 13 | S: factura a **monotributista / exento** (B o C según relación fiscal) con retención IIBB; B: **pedido → remito parcial → factura** desde remito, cobrada con retenciones | | relación fiscal (letra), sin doble stock en B |

### N4 — rechazos y correcciones (OP14–OP17)

| OP | Operación | Qué prueba |
|---|---|---|
| 14 | Rechazos al guardar: certificado repetido para el mismo cliente, IIBB sin provincia, jurisdicción no provincial, medio con impuesto distinto, retención de una empresa en otra (medio de la otra empresa) | nada queda a medias; mensajes que explican el motivo; aislamiento |
| 15 | **Anular** el recibo de OP05 | revierte asiento, factura vuelve a 5 con saldo, caja/banco restituidos, el certificado queda libre y se vuelve a cargar en un recibo nuevo |
| 16 | Completar el certificado PENDIENTE de OP07 en **Control Fiscal** (`cfActualizarCertificado`): número, fecha, régimen, archivo → RECIBIDO → CONCILIADO | solo recibos confirmados; rechazo sobre recibo anulado |
| 17 | Retención con **período de cómputo** distinto del recibo (mes siguiente) | la Posición de IVA la toma en el mes de cómputo, no en el de emisión |

### N5 — fiscal y cierre (OP18–OP20)

| OP | Operación | Qué prueba |
|---|---|---|
| 18 | **Posición de IVA** del mes (página 617): débito de las ventas, crédito de la compra (B: la de N0; S: una factura de proveedor de servicios), retenciones IVA sufridas del mes (sin la de OP17); liquidación → asiento e I_LIQ o saldo a favor | importes de la posición = suma de los comprobantes y retenciones de la prueba |
| 19 | **Cierre IIBB** del período en Control Fiscal y luego intentar anular un recibo con retención IIBB de ese mes | `pfExigirDocumentoAbierto` bloquea; reabrir con motivo (estado 38) habilita |
| 20 | **Cuadre** de Control Fiscal (`pfCuadre`) y registro de retenciones (`cfRegistro`) | cada retención sufrida contra lo contabilizado por cuenta: diferencia 0; el registro lista exactamente las retenciones vigentes de la prueba |

## 6. Cierre de la corrida

- Consistencia global del simulador (stock, saldos, estados, asientos, tesorería, espejos), ya extendida a las
  empresas nuevas.
- Por empresa: suma de retenciones sufridas por impuesto/provincia = saldo de su cuenta contable = registro de
  Control Fiscal; cta. cte. de cada cliente = suma de saldos; Mayor de Deudores por ventas = cta. cte. total.
- Aislamiento final: las empresas 2 y 6 (origen de la copia) no cambiaron (conteos y saldos antes/después).
- Informe: tabla nivel × empresa × OP con OK / FALLA / BRECHA y la lista de bugs corregidos durante la corrida.

## 7. Cómo se ejecuta

1. Ajustes del simulador (§1) y N0. Correr, corregir, volver a correr hasta que N0 quede en verde (o con brechas).
2. N1 → N5, de a un nivel (`run.py global_normal` corre todo; `--sin-clonar 22_global_n2_retenciones` itera rápido).
   Un bug de código se corrige en `gestion_normal` y, si no es de compatibilidad, se replica en `gestion`
   (regla de `.agents/rules/gestion_normal_sync.md`).
3. Corrida limpia completa desde la clonación.
4. Opcional: `--base-local` para ver las dos empresas en pantalla.

## 8. Decisiones (tomadas el 2026-10-19)

1. **Origen de la configuración**: copia con el asistente desde 6 (S) y 2 (B); el resto por sus páginas.
2. **Brechas**: se reportan aparte y se completan por SQL; no cuentan como falla.
3. **Usuario**: las 20 operaciones como superadmin; un Administrador por empresa solo para el aislamiento (N0.3) y las
   escrituras cruzadas (OP14).

## 9. Resultados de la ejecución (2026-10-19)

Corrida limpia desde la clonación: `python _local/tests/simulacion/run.py global_normal --scripts _local/sql/mantenimiento/20261019_productos_tipos_codigo_por_empresa_01.sql`
→ **849 chequeos, 2 fallas, 8 brechas**; consistencia global en verde (stock, saldos, estados 5/39/40, 74 asientos
balanceados, tesorería, espejos); empresas 2 y 6 sin cambios.

### Bugs encontrados y corregidos

| # | Dónde | Problema | Corrección |
|---|---|---|---|
| 1 | `cheques_cartera_model.php` (gestion y gestion_normal) | Depositar un cheque de terceros **reemplazaba el asiento del recibo** (Deudores, retenciones, valores) por el del depósito: `tesoreriaGenerarAsiento` hace upsert por `comprobante_id` y se le pasaba el del recibo. Lo mismo al debitar un cheque propio diferido (pisaba el asiento de la OP). Lo detectó el cuadre de Control Fiscal (OP11 → OP20). | El depósito y el débito generan su propio asiento (`sin_comprobante`). |
| 2 | ídem | Movimientos de tesorería de cheques con `medio_pago_id` fijo (2 / 3, de la empresa 2). | `emp_medio_pago_id()` por tipo `CHEQUE_TERCERO` / `CHEQUE_PROPIO`. |
| 3 | `empresas_impuestos_config_ajax.php` (ambos) | `agregar` / `editar` tomaban `empresa_id` del POST: un Administrador de una empresa daba de alta configuración de impuestos en otra (OP14). | Siempre la empresa del guard. |
| 4 | `empresas_parametros_ajax.php` (ambos) | La pantalla elige empresa (`empresa_id`) sin validar: un Administrador creaba vigencias de parámetros en otra empresa (OP14). | Exige acceso a la empresa pedida y a la página en ella. |
| 5 | `productos_tipos` | `producto_tipo_codigo` con UNIQUE global y validación sin empresa: una empresa nueva no podía crear PROD / SERV (N0.7). | Script `20261019_productos_tipos_codigo_por_empresa_01.sql` (UNIQUE por empresa) + validación por empresa (ambos módulos). |
| 6 | `gestion_normal`: `ventas_facturas_ajax.php`, `comprobantes_consulta_model.php`, `comprobantes_ui.js`, `paginas*_model.php`, `tablas*_model.php` | Restos del módulo 2: el cobro desde la factura validaba contra la página 56; la consulta de comprobantes linkeaba a páginas del módulo 2 (`&modulo_id=2`, sin acceso para un usuario solo del 5); las páginas de configuración listaban el módulo 2. | Módulo 5; y dos reglas nuevas en `sync_gestion_to_normal.py` (`modulo_id = 2` en SQL / links y `pagina_id_por_url($x, 2, …)`), que además protegen `arca_integraciones_ajax.php`, corregido a mano antes. |

### Pendiente (no corregido)

- El asistente tampoco copia alícuotas de IVA, medios de pago, tipos de asiento, tipos de producto, configuración de
  impuestos ni los tipos habilitados por PV: tienen página, pero hay que cargarlos uno por uno.
- Rechazo de un cheque depositado: egresa el banco sin asiento ni ND al cliente.
- `tesoreria_movimientos_model.php` y `obligaciones_rendiciones_model.php` usan `medio_pago_id` fijo (1 / 3).
- El espejo de pedidos en `gestion__comprobantes` no sigue el estado del pedido (pedido 10, espejo 5 con saldo);
  también en la base local.
- `agregarempresa` (`conf/empresas_model.php`) concatena valores en el SQL.

## 10. Diagnóstico del asistente y ABM de monedas / cuentas de tesorería (2026-10-19)

- **Diagnóstico** (`empresa_inicio_model.php`, `.php`, `.js`, ambos módulos): seis controles nuevos
  (`inicioMaestrosOperacion`): moneda base, alícuotas de IVA gravadas, tipo de asiento AUT, cajas con cuenta contable,
  medios de cobro y de pago con su cuenta (de tesorería o contable para las retenciones) y tipos de comprobante en
  cada punto de venta activo. Puntaje ponderado: 100 solo con todo cumplido; "lista para operar" solo con 100.
- **Monedas** (páginas 126 / 626) y **Cuentas de Tesorería** (127 / 627), menú Tesorería, script
  `20261019_monedas_tesoreria_cuentas_01_paginas.sql` (tabla 441, estados 234 / 235, funciones 3434–3443 / 8434–8443,
  permisos a Administrador y Tesorería). Validan en el servidor la función del usuario (`usuario_puede_funcion`,
  `core/permisos.php`).
- El nivel 0 los carga por esas páginas (dejaron de ser brechas) y prueba sus rechazos y el aislamiento.
- Corrida limpia con los dos scripts del 2026-10-19: **905 chequeos, 0 fallas**, 4 brechas (cancelaciones y
  dependencias de tipos en las dos empresas).

## 11. Relaciones entre tipos de comprobante (2026-10-19)

- Función **Relaciones** en Tipos de Comprobante (páginas 45 / 545, funciones 3444 / 8444, script
  `20261019_comprobantes_tipos_relaciones_01.sql`, modelo `comprobantes_tipos_relaciones_model.php`, vista
  `comprobantes_tipos_relaciones.js`): para cada tipo, qué tipos cancela (con "misma entidad") y de qué comprobante padre
  depende, con las relaciones inversas a la vista. Quitar deja la fila en estado 2; volver a marcar la reactiva.
  Rechaza el propio tipo, tipos de otra empresa y ciclos directos padre / hijo. Solo perfiles de administración.
- El asistente de inicio copia esas relaciones con los tipos (`inicioCopiarRelacionesTipos`, remapeo por código).
- Con esto no quedan brechas en el alta. Corrida limpia: **927 chequeos, 0 fallas, 0 brechas**.
- Los tres scripts del 2026-10-19 están aplicados en la base local (backup previo en
  `_local/tests/simulacion/salidas/backup_gestion_multipyme_antes_20261019_scripts.sql`); falta producción.

## 12. La misma prueba sobre el módulo 2 Gestión (2026-10-19)

Grupo `global_gestion` del simulador: los casos 20–25 sin cambios de lógica, parametrizados en `globlib.py` por `SIM_GLOBAL_MODULO` (`MOD_CARPETA`, `MOD_ID`, `MOD_OTRO_ID`, `PERFIL_BASE`, `pg()` traduce los ids de página del módulo 5 a los del 2). Las empresas se llaman igual con sufijo " G" y CUIT 30-71998001/002; copian la configuración de las mismas empresas origen (6 y 2), ya que las tablas `gestion__` son comunes a los dos módulos. N0.2 verifica módulo 2 asignado, el 5 rechazado por excluyente y perfiles base 201–208 según el tipo de operación.

Resultado: **927 chequeos, 0 fallas, 0 brechas** (S 311 y B 327 en las operaciones, el resto en el alta y la consistencia), igual que en el módulo 5, que se volvió a correr después de la parametrización con el mismo resultado. Salidas: `salidas/global_gestion_corrida1.txt` y `salidas/global_normal_corrida5.txt`.

**Con los roles de entidad (2026-10-19, `20261019_entidades_roles_01/02` ya aplicados en la base local: sin `es_cliente` / `es_proveedor` / `cont_cuenta_id_*`)**: N0.8 suma 3 chequeos por empresa (clientes con solo el rol CLI, proveedor con solo PRO, ninguna cuenta de rol de otra empresa). `global_normal` y `global_gestion`: **933 chequeos, 0 fallas** cada uno (`salidas/global_normal_corrida6.txt`, `salidas/global_gestion_corrida2.txt`). Casos 01–19: 930 / 4, las mismas fallas previas (percepción IIBB de la empresa 2 en 20 de 24 jurisdicciones, filtro por Baja en afiliaciones de sindicatos).

## 13. Eliminación de remitos y pedidos (2026-10-21)

Caso nuevo `casos/19b_pedidos_remitos_eliminacion.py` (empresa 2). Encontró y se corrigieron en `ventas_remitos_model.php` (gestion y gestion_normal, idénticos):
- Eliminar un remito en Borrador (3 → 6) no devolvía al pedido la `cantidad_entregada` que el Borrador ya había reservado (solo revertía CANCELADO / ANULADO): el pedido quedaba sin pendiente y no se podía volver a remitir.
- El estado del pedido se cruzaba con `conf__tablas_estados_registros` por PK (y `!= 137`) en los pendientes y al validar el alta del remito: se ofrecían, y se aceptaban, pedidos en Borrador o eliminados.
- El estado del pedido (11 / 10 / 5) ahora sale solo de remitos confirmados (pendiente de facturar, parcial o facturado), se recalcula con el remito ya en su estado nuevo y actualiza el espejo del pedido en `gestion__comprobantes`.

Resultado: caso 45 / 0; `global_normal` y `global_gestion` 933 / 0; casos 01–19b 967 / 4 (las mismas cuatro fallas previas).
