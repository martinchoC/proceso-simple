# Simulador de circuitos

Prueba los circuitos del ERP de punta a punta llamando a los `_ajax.php` **reales** (guard, ajax, modelo y SQL),
como lo hace el navegador, sobre una **copia** de la base. Lo usa el agente `tester-circuitos`.

- La copia se llama `gestion_multipyme_sim` y se recrea en cada corrida a partir de `gestion_multipyme`
  (`mysqldump` con rutinas y triggers). **Nunca escribe en la base real**: `run_ajax.php` fija `DB_NAME`
  a la copia antes de que `db.php` cargue `.env`, y se niega a correr contra `gestion_multipyme`.
- La sesión simulada es la del usuario 1 (superadmin) en la empresa 2 (Casa Lucho): no prueba permisos por perfil.
- ARCA: la base local hoy tiene `arca_habilitado = 1` (homologación) en la empresa 2. Sin deshabilitarlo en la copia,
  los casos de facturas fiscales fallan en la validación de CAE previo (y "aprobada" pediría CAE de verdad). Correr con
  `--scripts` de un SQL con `UPDATE gestion__empresas_parametros SET arca_habilitado = 0 WHERE empresa_id = 2;`.

## Uso

Desde la raíz del repo (requiere Python 3 y XAMPP; rutas configurables con `SIM_PHP`, `SIM_MYSQL`, `SIM_MYSQLDUMP`):

```
python _local/tests/simulacion/run.py                         # todo (clona la base primero)
python _local/tests/simulacion/run.py circuitos               # grupo: circuitos | notas | consumidor_final
python _local/tests/simulacion/run.py 05_notas_credito_proveedores
python _local/tests/simulacion/run.py --scripts _local/sql/mantenimiento/AAAAMMDD_tema_01_paso.sql   # prueba un script antes de producción
python _local/tests/simulacion/run.py sindicatos --scripts x.sql   # grupo + scripts: el grupo ANTES de --scripts (si va después, lo toma como otro script)
python _local/tests/simulacion/run.py --sin-clonar notas      # reutiliza la copia (rápido, menos aislado)
python _local/tests/simulacion/run.py --listar
python _local/tests/simulacion/run.py --base-local circuitos  # sobre la base LOCAL real, para ver el resultado en pantalla (backup antes)
```

`--base-local` es la única forma de escribir en `gestion_multipyme`: no clona, corre los casos sobre la base local y
deja los documentos para revisarlos en las pantallas (observaciones "SIM"). Hacer un `mysqldump` antes. El agente
`tester-circuitos` no lo usa.

Sale con código 1 si hay fallas. El detalle queda en `salidas/resultados.json` (no versionado): `{chequeos: [...], brechas: [...]}`.
Una **brecha** (`brecha(texto, detalle)` de `simlib.py`) es algo que la aplicación no permite hacer (p. ej. un maestro sin página):
el caso lo completa por SQL y sigue; el resumen las lista aparte y no cuentan como falla.

## Casos

| Archivo | Circuito |
|---|---|
| `01_compras.py` | OC → factura de proveedor parcial y total (estado 41/8), factura sin OC, vincular producto al proveedor |
| `02_pagos.py` | OP total y parcial, anulación (usa el contexto de 01) |
| `03_ventas.py` | Pedido → remito parcial → factura desde remito (sin doble stock), facturas directas, recibos, cobro desde la factura y anulación |
| `04_notas_credito_ventas.py` | NC asociada (aplicación automática + recibo en $0), NC con saldo, oferta al confirmar factura, devolución por OP al cliente, contado a consumidor final |
| `05_notas_credito_proveedores.py` | Ídem del lado de compras (OP en $0, devolución por recibo del proveedor), NC/ND X |
| `06_consumidor_final.py` | NC a consumidor final con devolución en efectivo; factura de proveedor que no puede generar asiento |
| `08_precios_compra.py` | "Armar lista desde precios" de Lista Precios Proveedores; opciones de Parámetros de Empresa que hacen que la factura de proveedor confirmada cargue/pise la lista del proveedor y recalcule las listas de venta |
| `09_percepciones_iibb.py` | Percepciones IIBB practicadas (Río Negro): cálculo por situación del cliente y lugar de entrega, NC que revierte, certificado de exclusión, sin sucursal, padrón SIRCIP (dígitos del campo 7), DDJJ SIRCAR y liquidación I_LIQ. Deshabilita ARCA en la copia |
| `10_ventas_rio_negro.py` | 10 ventas a clientes de Río Negro: inscriptos local o CM con alta (1 %), simplificado (0,05 %), no inscripto (0 %) y NC que revierte, más la DDJJ del período. Carga condición fiscal e IIBB a 8 clientes, activa la config de Río Negro y deshabilita ARCA mientras corre (después lo restituye). Pensado también para `--base-local` |
| `11_percepciones_compras.py` | Percepciones recibidas de IVA, Ganancias e IIBB en facturas de proveedores, en `gestion` (empresa 2) y `gestion_normal` (empresa 6): las ofrece la pantalla, se guardan en `gestion__comprobantes_impuestos`, suman al total y al saldo, van al Debe de la cuenta de cada config; edición en Borrador, rechazos (config de otra empresa o de ventas, negativo, factura confirmada) y NC que revierte. IIBB de varias provincias en la misma factura, cada una a su cuenta (empresa 2). Aplica en la copia `20261011_percepciones_recibidas_01_subgrupo_fc.sql` y `_02_provincias.sql` |
| `12_debito_en_cuenta.py` | Débito directo en cuenta bancaria, en `gestion` y `gestion_normal`: Resumen bancario (cód. ARCA 039) con comisiones 21 %, intereses 10,5 %, impuesto ley 25.413 no gravado y percepción de IVA, que al confirmar se cancela con una OP automática (medio DEB_CTA) desde el banco; factura con débito automático opcional; rechazos (caja, banco de otra empresa, NC). Aplica en la copia los scripts `20261011_percepciones_recibidas_01/02` y `20261011_debito_en_cuenta_01/02` |
| `13_anular_compras.py` | Anular comprobantes de proveedor confirmados (5 -> 38), en `gestion` y `gestion_normal`: revierte stock, asiento y saldo; anula la OP automática del débito en cuenta y devuelve el banco; una OP manual queda con saldo a favor; anular una NC aplicada devuelve el saldo a la factura y anular la factura deja la NC con saldo a favor. Aplica `20261011_facturas_proveedores_anular_01` |
| `15_retenciones_cobranzas.py` | Retenciones sufridas (IVA, Ganancias, IIBB) en recibos de cobranza, en `gestion` y `gestion_normal`: maestros (solo retenciones sufridas, solo provincias, medios con su impuesto), rechazos (IIBB sin provincia, sin certificado, impuesto distinto del medio, certificado repetido), asiento con IVA / Ganancias a la cuenta del medio e IIBB a la cuenta de su provincia, sin movimiento de tesorería; anular libera el certificado. Aplica `20261012_retenciones_sufridas_01_cuentas_iibb` |
| `16_sindicatos.py` (grupo `sindicatos`) | Módulo 6, sobre la empresa 7 (AEC) con la configuración de `20261012_sindicatos_12_alta_empresa_sindicato.sql`, módulo `sindicatos`: alta de empleador con Casa Central N° 1 y sucursales; DDJJ confirmada (comprobantes por concepto con PV, sucursal y cuentas propias); volante con intereses fijados al emitir y deuda bloqueada; rendición Banco Nación con ingreso en tesorería, ND de intereses e imputaciones; pagos repetido, con importe distinto y fuera de término (no mueven dinero); rectificativa sobre DDJJ pagada (reversión total + saldo a favor) y con volante vigente (se anula); plan de pagos (RPL + cuotas) y su anulación; acta y su anulación; seguridad (acta de otra empresa, perfil Empleador sin acceso a actas ni a Medios de Cobro, sin sesión); importación de nómina CSV / F.931 (página 1021); sucursal del empleado en la nómina; botones del motor por estado y permiso y funciones exigidas en el servidor; relaciones laborales; afiliaciones (actividad desde la DDJJ, solicitudes, grupo familiar con vigencias por edad y certificado, vencimiento por el cron con `run_cron.php`, cuota directa cobrada con volante de afiliado por la rendición del banco, carnet imprimible con QR firmado y su verificación, y baja en cascada). La rendición se carga por SQL y se procesa por el ajax. Los archivos se suben con `_files` (`run_ajax.php` arma `$_FILES`) |
| `19b_pedidos_remitos_eliminacion.py` | Eliminación de remitos y pedidos (empresa 2): un remito en Borrador ya descuenta `cantidad_entregada` del pedido; eliminarlo o editarlo lo devuelve, el estado del pedido (5 / 10 / 11) sale solo de los remitos confirmados, una entrega confirmada no se elimina, y los pedidos en Borrador o eliminados no se ofrecen ni se aceptan en un remito |
| `20_` a `25_global_*.py` (grupo `global_normal`) | Prueba global de Gestión Normal (diseño en `_local/docs/pruebas/prueba_global_gestion_normal.md`): alta de dos empresas nuevas (servicios y bienes) por las pantallas de conf y el asistente de inicio, maestros por sus páginas (lo que no tiene página va como brecha), y 20 operaciones por empresa en cinco niveles con retenciones sufridas (IVA, Ganancias, IIBB por provincia), cobro desde la factura, cheque y su depósito, NC, pedido → remito → factura, rechazos y escrituras cruzadas entre empresas, anulación, certificados en Control Fiscal, posición de IVA, cierre de IIBB y cuadre. Ayudas en `globlib.py`; ids en `salidas/ctx_global_<módulo>.json`. El grupo `global_gestion` corre los mismos casos sobre el módulo 2 (`modules/gestion`, perfiles base 201–208, páginas = las del 5 − 500 vía `pg()`); `run.py` fija `SIM_GLOBAL_MODULO=gestion` antes de cargar `globlib`, por eso los dos grupos van en corridas separadas. Aplicar con `--scripts` los scripts `20261019_*.sql` si la base local no los tiene (desde el 2026-10-19 los tiene) |
| `26_conciliacion_bancaria.py` | Conciliación bancaria (páginas 128–133 / 628–633), en `gestion` (empresa 2) y `gestion_normal` (empresa 6; `SIM_BANCOS_MODULOS=gestion` corre uno solo): formato con prueba sobre el extracto real (`_local/tests/fixtures/extracto_muestra.xls`, fuera de git: sin él el caso es una brecha), reglas, importación en dos pasos (n° de cuenta, archivo repetido, anular el lote y reimportar), sugerencias segura / probable / agrupada N:1, conciliar automáticamente, deshacer, clasificar / ignorar, gastos del banco (bloqueos de proveedor e IVA, Resumen bancario C_RB + OP por débito en cuenta conciliado, varios comprobantes por mes con la fecha del último movimiento, deshacer que lo anula, IVA solo el declarado en el extracto), período de IVA cerrado y CSRF. Los movimientos de tesorería a conciliar se cargan por SQL. `run_ajax.php` acepta `content_b64` en `_files` y manda un token CSRF válido salvo `_sin_csrf` |
| `14_iva_posiciones.py` | IVA - Posición Mensual (páginas 117 / 617): agregados del mes, liquidación con saldo a favor (asiento sin comprobante) y a pagar (I_LIQ + asiento), arrastre del saldo, bloqueos (doble liquidación, anular con liquidación posterior, anular una compra de un período liquidado), anulación y reliquidación, libro IVA en CSV. Aplica `20261011_iva_posiciones_01` |
| `27_cierre_periodos_contables.py` | Cierre de Períodos Contables (páginas 134 / 634), en `gestion` (empresa 2) y `gestion_normal` (empresa 6): mes de cierre del ejercicio por Parámetros de Empresa (junio) y el ejercicio que define (rango, actual, irregular al cambiar el cierre, cerrar todo el ejercicio de julio a junio), cerrar un mes con aviso de asientos en borrador, rechazos (repetido, futuro, reabrir sin motivo o de otra empresa), bloqueo de los asientos manuales del mes (agregar, editar, mover al mes, línea, eliminar, confirmar) y de `tesoreriaGenerarAsiento` (llamado directo por PHP CLI sobre la copia), reapertura con historial y cierre del año completo. Aplica `20261028_cont_periodos_cierres_01_estructura` y `20261030_..._02_mes_cierre_ejercicio` |
| `31_iibb_liquidacion.py` | Liquidación mensual de Ingresos Brutos (Control Fiscal, página 125, empresa 2): inscripciones CM Buenos Aires 0,6 / Río Negro 0,4 y actividades (principal, mínimo, rechazos) por la pantalla, venta del mes que sube la base, propuesta en la actividad principal, liquidación BA con la base repartida (I_LIQ `IMP_IIBB`, asiento con los conceptos `IIBB_*`, cierre `IIBB_POSICION`), bloqueos (doble liquidación, reabrir a mano, venta del período), anulación y RN por el mínimo. Aplica `20261111_iibb_liquidacion_mensual_01` y pone al día el numerador de la Factura A en la copia |
| `28_obligaciones_pago.py` | Obligaciones de pago unificadas (páginas 135–137 / 635–637, empresa 2, `gestion`; pensado también para `--base-local`): empleados y organismos (rol EMP / ORG, alta por SQL), OP a un proveedor con retención IIBB Buenos Aires, dos sueldos cargados y pagados (OP individual y pago en lote), liquidación de IVA del mes (si da a favor, carga antes una venta Factura A con ARCA deshabilitado y lo restituye) y su OP a ARCA, liquidación de percepciones IIBB Río Negro y de retenciones IIBB Buenos Aires con sus OP: cada obligación pasa a 40 y la OP debita la cuenta de la obligación. Solo en la copia: reglas de aprobación (desde importe, doble, autoaprobación, rechazo), OP con aprobación (no mueve fondos hasta aprobarla), anticipo de sueldo (saldo a favor de la OP aplicado al sueldo) e importación CSV con previsualización y deshacer. Aplica nada: requiere los scripts `20261030_obligaciones_pago_0{1,2,3}` en la base |
| `29_ajustes_stock.py` | Ajustes de inventario (`stock_ajustes.php`, página 77, empresa 2, `gestion`): 10 ajustes por `stock_ajustes_ajax.php` — sobrante, faltante, conteo físico con diferencia + y −, tres líneas en un ajuste, costo promedio / último costo, doble confirmación (se rechaza), anulación que revierte con egreso compensatorio, faltante mayor al stock (se rechaza sin dejar nada a medias) y edición en borrador — más un caso extra (tipo que suma con diferencia negativa). Descubre tipos AST, estados, funciones y boca de la base; lo que falta (`impacta_stock` / `stock_signo` del tipo, `aplica_stock` del estado, flags de la boca, `controla_stock` del producto) lo configura por SQL **solo en la copia** y lo informa como brecha. Los chequeos 10b (editar un ajuste ya confirmado) y 11 apuntan a huecos posibles del modelo: si fallan son hallazgos |

Al final de cada corrida, `run.py` verifica la **consistencia global** de todo lo generado: stock materializado
contra el libro de movimientos, saldo de comprobantes contra imputaciones, estado 5/39/40 coherente con el
saldo, asientos balanceados y con fecha/descripción, tesorería contra el libro, espejo de remitos y signo de
facturas/notas.

## Cómo escribir un caso

Un archivo `casos/NN_tema.py` con `from simlib import *` y bloques `caso('Caso N - ...')` + `check(cond, texto, detalle)`:

1. **Crear por el `_ajax.php`**, con los mismos parámetros que manda la pantalla (leer el `case` del ajax y el JS).
   `ajax(archivo, params, 'GET'|'POST')` devuelve el JSON (`modulo='gestion_normal', empresa_id=6` para probar el módulo 5 con la sesión en esa empresa); `ok_resp(r)` / `err(r)` interpretan las dos convenciones
   de respuesta. Para compras/OC/pedidos, `linea()` y `totales()` arman los importes como el front.
2. **Transicionar** con `ejecutar_accion` y el `accion_js` configurado en `conf__paginas_funciones` de esa página.
3. **Chequear efectos**, no solo la respuesta: estado del registro y de su espejo en `gestion__comprobantes`,
   stock (`stock(producto, boca)`), saldos e imputaciones, caja/banco, asiento (cuenta y signo), y que lo que
   no debe moverse no se movió.
4. **Casos de rechazo**: verificar que se rechaza, que el mensaje explica el motivo y que no quedó nada a medias.
5. El `detalle` del `check` es lo que se lee cuando falla: poner el valor obtenido.

Datos de la empresa 2 que usan los casos: cliente RI 313, proveedor 5765, productos 1529/1878/3500
(con stock controlado), PV 1 (fiscal, recibos, remitos) y PV 3 (no fiscal), boca 1, caja 1 (sucursal 1), banco 3.
Si un dato cambia en la base, buscarlo en el caso (no hay ids ocultos en `simlib.py`).

Un caso que modifica configuración para provocar un error (p. ej. dejar un producto sin cuenta) lo hace **solo en la copia**
con `sql("UPDATE ...")`, y lo dice en un comentario.
