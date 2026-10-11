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
python tests/simulacion/run.py                         # todo (clona la base primero)
python tests/simulacion/run.py circuitos               # grupo: circuitos | notas | consumidor_final
python tests/simulacion/run.py 05_notas_credito_proveedores
python tests/simulacion/run.py --scripts sql/mantenimiento/AAAAMMDD_tema_01_paso.sql   # prueba un script antes de producción
python tests/simulacion/run.py --sin-clonar notas      # reutiliza la copia (rápido, menos aislado)
python tests/simulacion/run.py --listar
python tests/simulacion/run.py --base-local circuitos  # sobre la base LOCAL real, para ver el resultado en pantalla (backup antes)
```

`--base-local` es la única forma de escribir en `gestion_multipyme`: no clona, corre los casos sobre la base local y
deja los documentos para revisarlos en las pantallas (observaciones "SIM"). Hacer un `mysqldump` antes. El agente
`tester-circuitos` no lo usa.

Sale con código 1 si hay fallas. El detalle queda en `salidas/resultados.json` (no versionado).

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

Al final de cada corrida, `run.py` verifica la **consistencia global** de todo lo generado: stock materializado
contra el libro de movimientos, saldo de comprobantes contra imputaciones, estado 5/39/40 coherente con el
saldo, asientos balanceados y con fecha/descripción, tesorería contra el libro, espejo de remitos y signo de
facturas/notas.

## Cómo escribir un caso

Un archivo `casos/NN_tema.py` con `from simlib import *` y bloques `caso('Caso N - ...')` + `check(cond, texto, detalle)`:

1. **Crear por el `_ajax.php`**, con los mismos parámetros que manda la pantalla (leer el `case` del ajax y el JS).
   `ajax(archivo, params, 'GET'|'POST')` devuelve el JSON; `ok_resp(r)` / `err(r)` interpretan las dos convenciones
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
