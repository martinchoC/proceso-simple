# Canales: carrito del cliente, aplicación del vendedor y mostrador (etapa 4)

Estado: **implementado** (2026-11-15; ver §12). Aprobado con las recomendaciones de §9. Respuestas: el ERP de producción y el carrito
corren en el **mismo servidor** → decisión 1 = **opción A**; base de pruebas `gestion_multipyme_sim` (prendido) y
`ps_gestion_casalucho` local (sin la estructura); hay respaldo. Hechos: 9-0 (carrito `df9f190`, línea base
actualizada) y 9-1 (`7b5da52`, migración `20261123_canales_carrito_app_01.sql` + `pven_empresa_usa` + interruptores).
Rama `distribuidora` en `casalucho` y en `casalucho-carrito`. Sigue a los docs 17 a 27.

## 0. Antes de empezar: la carpeta del carrito cambió durante el reconocimiento

`casalucho-carrito` no estaba en git. A las 20:22 se creó el repositorio con la línea base (`main`, commit
`850377e`, sin `.env` / `.env.bak` / logs) y la rama `distribuidora`. **A las 20:22–20:23 alguien copió una versión
más nueva encima**: 46 archivos modificados y 13 nuevos (recuperación de contraseña con correo, exportación a Excel,
márgenes de reventa, `static/`, copias de los diagnósticos en `bin/`). La carpeta no cambió más desde las 20:23.
Este documento describe **la versión actual (la copiada)**, no la del commit `850377e`. Hay que decidir si la versión
actual pasa a ser la línea base (ver decisión 0).

## 1. Hallazgos: lo que se sabía del carrito, confirmado o corregido

Rutas de `casalucho-carrito/` salvo aclaración.

| # | Lo que se sabía | Resultado |
|---|---|---|
| 1 | App aparte (`ecommerce_app`, PHP + PDO, `src/`) sobre la misma base del ERP; rutas en `config/routes.php`; login por CUIL / CUIT (`ECOM_ENTIDAD_DOC_COLUMNA`) con permisos `ecom.*` | **Confirmado.** Rutas: `ecommerce_app/config/routes.php:17-51` (toda ruta de negocio con `auth` + `permiso:ecom.*`, las que modifican con `csrf`). Columna del documento: `.env.example:51` y `config/app.php:55-59` (lista blanca cuil / cuit). Permisos en modo `tienda` por defecto (`config/app.php:65`): el login ya decide; el modo `erp` mira `conf__paginas_funciones`. Base compartida: `.env.example:11` ("Es la MISMA base que usa el sistema"). |
| 2 | Lee tablas del ERP y escribe el pedido en `gestion__comprobantes`, `gestion__ventas_pedidos`, `_detalles` y `gestion__comprobantes_numeradores`; `registrarOrigenStorefront` inserta en `ecom__pedidos` aunque `LEEME.txt` dice que no se usan | **Confirmado, y la contradicción ya estaba resuelta en la versión actual.** Escrituras: `src/Repositories/PedidoRepository.php:96-123` (numerador con `ON DUPLICATE KEY` + `FOR UPDATE`), `:126` (comprobante), `:149` (pedido), `:180` (detalle), `:198-214` (`ecom__pedidos`, con try/catch: si falla, sigue) y `:216` (`ecom__pedidos_margenes`). El `LEEME.txt` **viejo** (commit `850377e`, líneas 10 y 17-20) decía que el carrito vivía en la sesión y que las `ecom__*` "ya no se usan"; el código, también el viejo, ya grababa en `ecom__carritos`. El `LEEME.txt` actual (`database/LEEME.txt:32-45`) lo corrige: las seis `ecom__*` son **obligatorias**. |
| 3 | Precio con la subconsulta `PRECIO_VIGENTE`; no conoce presentaciones, canales, escalas, promociones, marcas, vendedores, origen ni indicadores; descuento solo el general del cliente | **Confirmado.** `src/Repositories/ProductoRepository.php:20-30` (`precio_final > 0` de la lista, vigente hoy); descuento general en `src/Services/CarritoService.php:113` y `PedidoService.php:180-200`. No lee ningún `usa_*` (búsqueda en todo el código). **A verificar en 7a**: el carrito toma `precio_final` como **neto** y le suma el IVA (`src/Domain/LineaCarrito.php:31-68`); hay que confirmar que es el mismo criterio del ERP para esa lista. |
| 4 | Lista del cliente: entidad → condiciones → defecto del `.env` (orden inverso al doc 21) | **Confirmado** (`src/Services/PrecioService.php:40-42`). **Además**: `PrecioRepository.php:45` une condiciones con `ecc.entidad_id = e.empresa_id` (compara un id de entidad con uno de empresa): si una entidad tiene el mismo número que la empresa, toma condiciones ajenas. Con el indicador, todo esto se reemplaza por `cvta_lista_cliente`. |
| 5 | Pedido en estado 5; sin vendedor ni origen; sucursal de `sucursalCompraPrincipal` o de la configuración | **Confirmado** (`PedidoService.php:87`, `:89-144`, `:202-223`). Además: numera y confirma **en el mismo paso** (el ERP lo hace en la transición `confirmar_pedido`), no pasa por `vdis_antes_de_transicion` ni por `prv_aprobar_pendientes`, el espejo en `gestion__comprobantes` va con `signo = 1` fijo (`PedidoRepository.php:139`) y no usa `syncComprobante`. |
| 6 | El carrito vive en la sesión PHP | **Corregido**: vive en la base (`ecom__carritos` / `ecom__carritos_items`, `src/Repositories/CarritoRepository.php:29-90`), sin precios (se valorizan en cada lectura). También el margen de reventa del cliente (`ecom__carritos.margen_porcentaje`). |
| 7 | Catálogo con filtros y datos de autopartes | **Confirmado**: `ProductoRepository.php:210-345` (marcas, modelos, submodelos y compatibilidades de `gestion__productos_compatibilidad`), rutas `/catalogo/modelos` y `/catalogo/submodelos` (`routes.php:31-32`) y el detalle del pedido (`PedidoService.php:298-312`). |
| 8 | Diagnósticos y `.bk` / `.bak` en la raíz: ¿los protege el `.htaccess`? | **No.** El `.htaccess` de la raíz manda al `index.php` solo lo que **no** es un archivo (`.htaccess:31-32`) y su `FilesMatch` bloquea `.env`, `.log`, `.sql`, `.md` y archivos ocultos (`:46-65`), **no `.php` ni `.bk`**. Entonces `dbtest.php`, `diag.php`, `diagnostico.php`, `permisos.php` y `assets/js/app.js.bk*` se sirven directo. `diag.php` no pide clave (muestra rutas del servidor); `dbtest.php` pide los primeros 16 de `APP_KEY`; `diagnostico.php`, `APP_KEY` completa; `permisos.php` solo controla la clave si `APP_KEY` tiene 16 o más caracteres (`permisos.php:39`). La carpeta `ecommerce_app/` sí está bloqueada (`.htaccess:26` y su propio `.htaccess`). No se cambió nada. |

Otros hallazgos:
- **Ids fijos**: si el `.env` no los trae, el carrito usa 1 para tipo de comprobante, moneda y punto de venta
  (`config/app.php:16-17, 47-50`), contra la regla del ERP de resolver por empresa + código.
- **Una empresa por instalación** (`ECOM_EMPRESA_ID`, `config/app.php:41`).
- **Base por dominio**: con `desarrollo.` en el host y `DB_NAME=ps_gestion_casalucho`, usa
  `ps_gestion_casalucho_desarrollo` (`config/app.php:31-34`).
- Base local `ps_gestion_casalucho` (la del carrito en producción, con el mismo nombre) **no tiene** la estructura de la
  distribuidora (ni `origen` / `vendedor_entidad_id` en pedidos, ni `gestion__vendedores_usuarios`):
  `ps_gestion_casalucho_desarrollo` y `gestion_multipyme` sí.

## 2. Despliegue (decide la arquitectura)

Lo que dice el código:
- El carrito está pensado para Hostinger, en `public_html/<carpeta>/` con `ecommerce_app/` adentro o fuera de
  `public_html` (`index.php:21-25`, `.htaccess:2-4`: "https://procesosimple.com/casalucho-carrito/").
- Usa **la misma base** que el ERP (`.env.example:11`). La base de producción del ERP es `ps_gestion_casalucho`
  (CLAUDE.md), la misma que espera el carrito (`config/app.php:32`).
- Conecta por PDO con sus propias credenciales del `.env`; el ERP, por mysqli con las suyas (`.env` de la raíz).

**Lo que no puedo saber desde el código (pregunta 1):** ¿el ERP de producción corre en el mismo hosting / servidor
que el carrito (por ejemplo, `procesosimple.com/casalucho/` y `procesosimple.com/casalucho-carrito/`)? Si es así,
el carrito puede incluir el código del ERP desde el disco (opción A). Si están en servidores distintos, hace falta la
API (opción B).

## 3. Cómo se crea hoy un pedido en el ERP y qué está en `core/`

`modules/gestion/ventas_pedidos_model.php` (igual en el 5):

| Paso | Dónde | Qué hace |
|---|---|---|
| Alta | `agregarPedidoVenta` (:548) — **módulo** | presentaciones `vpre_preparar_lineas` (core), sucursal / canal `cvta_exigir_venta_sucursal` (core), escalas / promos / tope `prv_preparar_lineas` (core), vendedor y origen `vcar_resolver_pedido` (core), accesos por sucursal (core), INSERT de la cabecera en el **estado inicial de la página** (Borrador) con número 0, espejo con `syncComprobante` (**módulo**, dos copias), detalle con `insertarDetallesPedido` (**módulo**, :1928) que llama a `vpre_guardar_foto` y `prv_guardar_linea` (core), y `vcar_guardar_pedido` (core) |
| Confirmar | `ejecutarTransicionEstado` (:247) — **módulo** | `vdis_antes_de_transicion` (core), en `confirmar_pedido` `prv_aprobar_pendientes` (core, tope de quien confirma) y número con `obtenerProximoNumeroComprobante` (**módulo**, :182, mismo `gestion__comprobantes_numeradores` que el carrito), estado y espejo |
| Lista del cliente | `obtenerListaPrecioVigenteCliente` (:1443) — **módulo** | condiciones del cliente, completadas por `cvta_completar_condicion` (core) |

**Lo que falta en `core/` para crear un pedido desde afuera** (el centro del diseño): la orquestación. Las reglas ya
son compartidas (`vpre_*`, `cvta_*`, `prv_*`, `vcar_*`, `vdis_*`), pero el alta, el detalle, la numeración, el espejo
y la confirmación están en el modelo de cada módulo, atados a `$_SESSION` y a la página. Propuesta: **extraer** a
`core/pedidos_venta/` un servicio `pven_*` (crear en Borrador, valorizar, confirmar) y que los dos modelos lo llamen,
con comparación antes / después; no copiarlo.

**Qué graba el ERP y el carrito no:** foto de presentación (`presentacion_*`), regla de precio (`precio_regla_*`,
descuentos automático / manual y aprobación), `vendedor_entidad_id`, `origen`, `boca_despacho_id` (distribución),
estado inicial Borrador y numeración al confirmar, espejo por `syncComprobante`. **Validaciones que el carrito no
corre:** canal habilitado en la sucursal (`cvta_exigir_venta_sucursal`), presentación vendible con precio, mínimos y
múltiplos (`vpre_*`), tope de descuento (`prv_*`), cartera (`vcar_*`), reglas de distribución (`vdis_*`).

## 4. Arquitectura de integración

| | A. El carrito incluye `core/` del ERP por una ruta configurable | B. API interna JSON del ERP con token | C. Copia de `core/` dentro del carrito |
|---|---|---|---|
| Un solo código | Sí (el mismo archivo) | Sí (corre en el ERP) | No: dos copias que se desfasan |
| Requisito | Mismo servidor (ruta en disco legible) | Ninguno | Script de sincronización |
| Esfuerzo | Medio: puente PDO → mysqli (el `core/` usa mysqli: el carrito abre una conexión mysqli con sus mismas credenciales, solo para estas llamadas) | Alto: endpoints, token por empresa, límites de uso, errores, y el carrito pasa a ser cliente HTTP | Medio, y deuda permanente |
| Riesgo | El carrito queda atado a la versión del ERP del mismo servidor (es lo que queremos: un maestro); un error de `core/` afecta a los dos | Latencia y caída del ERP = carrito sin precios; superficie de ataque nueva | Precios distintos entre canales: lo que se quiere evitar |
| Indicadores apagados | El carrito no incluye nada: sigue su camino actual | El carrito no llama: igual | Igual |

**Recomendación: A si están en el mismo servidor; si no, B** (y el servicio de `core/` es el mismo en los dos casos:
B sería una capa fina encima). Con el indicador prendido, el carrito deja de escribir tablas del ERP: llama a
`pven_*` (que usa `cvta_*`, `lpre_*`, `vpre_*`, `prv_*`, `vcar_*`, `vdis_*`). Con el indicador apagado o sin la ruta
configurada, el carrito sigue exactamente como hoy.

Ruta: `ERP_CORE_PATH` en el `.env` del carrito (vacía = sin integración). El carrito verifica que exista
`core/pedidos_venta/` y la versión del contrato antes de usarlo.

## 5. Aplicación del vendedor

| | (a) Modo dentro del carrito | (b) Carpeta nueva en `casalucho` (`app_vendedor/`) | (c) Otra aplicación |
|---|---|---|---|
| Acceso | El carrito autentica clientes por CUIT; habría que sumar usuarios del ERP | **Login del ERP** (los vendedores ya son usuarios ligados por `gestion__vendedores_usuarios`) | Un tercer login |
| Código | Puente a `core/` como el carrito | `core/` directo, `ajax_guard`, mismo `.env` | Puente a `core/` |
| Sincronización 2 ↔ 5 | — | Ninguna: no va en `modules/` | — |

**Recomendación: (b)** `casalucho/app_vendedor/` (fuera de `modules/`, así no entra en la sincronización del 2 y el
5): mobile first, `manifest.webmanifest` + íconos locales, sin service worker (sin modo sin conexión en esta etapa),
escáner con `BarcodeDetector` del navegador y **ingreso manual de respaldo** (sin librería nueva; si hiciera falta una,
se muestra antes). Acceso: sesión del ERP + `usa_app_vendedor` + `vcar_vendedor_de_usuario()` (quien no es vendedor
activo no entra) + `vcar_exigir_cliente()` en cada lectura y grabación. Endpoints propios con `ajax_guard` y CSRF.

**Pedido en preparación = pedido real en Borrador (estado 3), origen APP** (recomendado): sobrevive al cierre de
sesión, se ve en el ERP y no necesita tabla de carritos. Uno por cliente: al elegir un cliente con un Borrador APP del
mismo vendedor, se retoma. "Confirmar" pasa por la misma transición del ERP (`prv_aprobar_pendientes`: si el
descuento supera su tope, queda en Borrador "pendiente de aprobación").

## 6. Qué cambia para quien hoy usa el carrito

Con los indicadores apagados (o la ruta vacía): **nada**. Se prueba con la foto de lo que graba un pedido (las
cuatro tablas + `ecom__pedidos` / `_margenes`) antes y después, con el mismo carrito.

Con `usa_carrito_integrado` prendido: lista del cliente por `cvta_lista_cliente`; presentaciones con su precio,
mínimos y múltiplos, disponibilidad (solo se muestra), etiqueta de escala o promoción; el pedido se crea con
`pven_*` (origen CARRITO, vendedor de la cartera a la fecha, presentación y regla en cada línea) y se confirma por la
misma transición del ERP; los productos sin precio en la lista no se ofrecen. Login, sesión, margen de reventa,
recuperación de contraseña y exportación a Excel quedan como están.

## 7. Migración propuesta (solo agregado)

`_local/sql/mantenimiento/2026MMDD_canales_carrito_app_01.sql`:
- `conf__empresas.usa_carrito_integrado` TINYINT NOT NULL DEFAULT 0.
- `conf__empresas.usa_app_vendedor` TINYINT NOT NULL DEFAULT 0.
- Nada más con la opción A (`origen` CARRITO / APP ya existe: `core/vendedores.php:19`). Con B, además una tabla de
  tokens por empresa (hash SHA-256, vencimiento, revocable).
- Ninguna tabla `ecom__*` se toca.

## 8. Plan de pruebas

- **Comparación ERP con todo apagado** al extraer `pven_*`: casos 03, 04, 06, 09, 19b + foto de líneas, contra una
  corrida del código anterior sobre el mismo maestro (worktree del commit previo, como en el doc 27).
- **Mismo pedido por los tres caminos**: el mismo carrito cargado en el ERP, por `pven_*` (carrito) y por la app da
  el mismo pedido columna por columna (sin ids ni fechas). `probar_ped_ext.php` apagado, prendido y contra una base
  sin la estructura (`ps_gestion_casalucho` local sirve: no la tiene).
- **Carrito apagado**: foto de las cuatro tablas + `ecom__*` antes / después con el mismo carrito; `tests/` del carrito.
- **Carrito prendido**: canal minorista y mayorista, mínimos y múltiplos, escala que cambia al sumar cantidad,
  promoción, producto sin precio, precio / descuento / cantidad manipulados desde el navegador (se ignoran), pedido de
  otro cliente (rechazado), sucursal que no vende el canal.
- **App**: solo su cartera, cliente ajeno rechazado, descuento dentro y fuera del tope, dos borradores, retomar,
  escaneo y búsqueda, mismo pedido que el ERP; apagada no se abre.
- Casos nuevos del simulador (41 en adelante).

## 9. Decisiones a aprobar (con la recomendación)

0. **Línea base del carrito**: commitear la versión actual (la copiada a las 20:23) como segundo commit de `main`
   ("línea base actualizada") y rebasar `distribuidora` sobre ella. **Recomendado**, si confirmás que esa copia es la
   versión buena.
1. **Arquitectura**: A (incluir `core/` por `ERP_CORE_PATH`) si el ERP y el carrito están en el mismo servidor; si no,
   B. Depende de la pregunta 1.
2. **App del vendedor**: (b) `casalucho/app_vendedor/`, con el login del ERP.
3. **Pedido en preparación** = Borrador real (3), origen APP. Recomendado.
4. **Lista del cliente en el carrito** = `cvta_lista_cliente` (solo con el indicador). Recomendado (además corrige el
   cruce de `PrecioRepository.php:45`).
5. **Qué ve el cliente**: precios por presentación, mínimos y múltiplos, disponibilidad (solo mostrar), etiqueta de
   escala / promoción e historial de pedidos (ya existe). **Saldo y facturas: no entran ahora** (etapa 5; necesitan
   definir qué se muestra de la cuenta corriente).
6. **Pedido mínimo en monto: no entra** en esta etapa (sin estructura nueva); si hace falta, un parámetro por empresa
   en Parámetros de Empresa en la etapa 5.
7. **Estado al confirmar desde el carrito: 5**, como hoy, pero por la transición del ERP (numeración y validaciones
   iguales). Si una regla deja una línea pendiente de aprobación (no debería: el cliente no tiene descuento manual),
   queda en Borrador y se avisa.
8. **Vendedor y origen**: vendedor = el de la cartera del cliente a la fecha (`vcar_resolver_pedido`), origen CARRITO.
9. **Autopartes**: con el indicador, ocultar modelos / submodelos / compatibilidad si la empresa no tiene filas en
   `gestion__productos_compatibilidad`; el filtro de marca pasa a la marca comercial si `usa_marcas_comerciales`.
   Nada se borra.
10. **Diagnósticos y `ecom__*`** (solo informar): sacar de producción `dbtest.php`, `diag.php`, `diagnostico.php`,
    `permisos.php`, sus copias en `bin/` y los `.bk` / `.bak`, o bloquearlos en el `.htaccess`; `diag.php` hoy no
    pide clave. Las `ecom__*` se siguen usando: no se borran.

## 10. Orden de trabajo (un commit por paso)

| Paso | Qué | Repositorio |
|---|---|---|
| 9-0 | Línea base actualizada del carrito (si se aprueba la decisión 0) | carrito |
| 9-1 | Migración + lectura tolerante de los dos indicadores + interruptores en Empresas | casalucho |
| 9-2 | Extraer `core/pedidos_venta/` (`pven_*`: crear, valorizar, confirmar) y que los modelos 2 y 5 lo usen; comparación con todo apagado | casalucho |
| 9-3 | Servicio de ofertas para un cliente (`pven_ofertas`: lista, presentaciones con precio, mínimos, disponibilidad, etiquetas, marca) + `probar_ped_ext.php` | casalucho |
| 9-4 | Carrito: puente a `core/` (`ERP_CORE_PATH`, conexión mysqli), sin cambios de comportamiento con el indicador apagado; foto antes / después | carrito |
| 9-5 | Carrito: catálogo y carrito con presentaciones, mínimos, etiquetas y lista unificada | carrito |
| 9-6 | Carrito: confirmar por `pven_*` (origen CARRITO, vendedor) + casos de simulación | carrito + casalucho |
| 9-7 | App del vendedor: acceso, clientes de la cartera, catálogo, Borrador APP | casalucho |
| 9-8 | App: escáner, descuento del vendedor y aprobación, entrega estimada, historial y cuenta (solo lectura) | casalucho |

## 11. Preguntas abiertas

1. ¿El ERP de producción y el carrito están en el mismo servidor (decide A o B)? ¿En qué URL corre el ERP?
2. ¿La versión del carrito copiada a las 20:23 es la buena (la de producción)? (Decisión 0.)
3. Base de pruebas de la etapa: propongo `gestion_multipyme_sim` (clon del maestro, con la estructura) para lo
   prendido y la copia local `ps_gestion_casalucho` para "sin la estructura". ¿Hay una copia de producción más nueva
   que quieras usar? ¿Hay respaldo de la base local y de las dos carpetas?

## 12. Implementado (2026-11-15)

| Paso | ERP (`casalucho`) | Carrito (`casalucho-carrito`) |
|---|---|---|
| 9-0 línea base del carrito | — | `850377e` (repositorio), `df9f190` (versión copiada a las 20:23) |
| 9-1 indicadores | `7b5da52`: `20261123_canales_carrito_app_01.sql`, `pven_empresa_usa`, interruptores en Empresas | — |
| 9-2 pedido de venta en core | `a10439c`: `core/pedidos_venta/pedidos_service.php` (pven_*), modelos 2 y 5 = envoltorios | — |
| 9-3 ofertas y pedidos externos | `4c225ce`: `core/pedidos_venta/externo.php` | — |
| 9-4 puente | `8eb6406` (ejecutor de pruebas) | `60c7b88`: `ERP_CORE_PATH`, `src/Support/ErpCore.php` |
| 9-5 / 9-6 carrito integrado | `c585c36`: `20261123_canales_carrito_app_02_carrito_presentaciones.sql`, caso 42 | `9983ad5`: `CarritoIntegrado`, catálogo, carrito y confirmación |
| 9-7 / 9-8 app del vendedor | `3b85d14`: `app_vendedor/`, `core/pedidos_venta/app_vendedor.php`, `assets/js` y `assets/css` `app-vendedor.*` | — |

Diferencias con el diseño:
- Ítems del carrito en presentación en una tabla nueva, `ecom__carritos_presentaciones`: `ecom__carritos_items` tiene
  UNIQUE (carrito, producto) y cambiarla no es "solo agregar". La línea en presentación viaja con la clave
  "producto-presentación" en el mismo `producto_id`, así `app.js` no cambia.
- La app del vendedor guarda, edita y confirma con las funciones del modelo de pedidos del módulo de la empresa
  (`agregarPedidoVenta`, `editarPedidoVenta`, `ejecutarTransicionEstado`: las mismas del ERP, con la sesión del
  vendedor) y arma los datos con `pven_datos_pedido`. Exige las funciones agregar / confirmar_pedido de la página de
  pedidos, como la pantalla.
- `vcar_resolver_pedido`: un usuario vendedor que manda origen APP queda APP (antes siempre VENDEDOR); el ERP no manda
  origen y sigue igual.
- Entrega estimada: el próximo día de reparto de la zona del cliente después de hoy (las zonas no tienen hora de
  corte). Cuenta del cliente: `ctacteResumen` del módulo (solo lectura).
- El carrito no muestra presentaciones en la ficha del producto (`/productos/{id}`), solo en el listado del catálogo.
- 2026-11-16 (Prompt 7b completo, carrito `f7c4551`): filtro por marca comercial en el catálogo y productos que se
  venden solo en presentación (sin precio por unidad) también listados; caso 45 (canal mayorista / minorista,
  múltiplos, escala que aparece al sumar, promoción, producto solo en caja, marca y pedido ajeno) 9/9.
- No se hizo la pantalla de mostrador (Prompt 9: opcional, a confirmar).

Pruebas (simulador, empresa 2, sobre una copia recién clonada):
- Con todo apagado, iguales a antes: ventas 03, 04, 06, 09, 19b (206 chequeos) y casos 33-37 y 39 prendidos (209),
  con la misma foto de líneas; el código movido en 9-2 es idéntico salvo el contexto. Carrito apagado: un pedido
  confirmado graba lo mismo que la línea base (`_local/tests/carrito/foto_carrito.py`), con `ERP_CORE_PATH` puesta.
- Caso 41 (servicio externo = pantalla del ERP columna por columna, apagado y prendido) 23/23; caso 42 (carrito real
  integrado: cajas y unidades, escala, mínimos, enteros, sin precio, canal de la sucursal, mismo pedido que el ERP)
  16/16; caso 43 (app: apagada, no vendedor, solo su cartera, borrador real que se retoma, tope del descuento,
  código de barras, CSRF, entrega estimada) 25/25. `probar_ped_ext.php` contra `ps_gestion_casalucho` (sin la
  estructura) 6/6; `tests/ErpCoreTest.php` del carrito con y sin ruta y sin la columna.
- La única falla es la preexistente de consistencia (2 asientos sin fecha / descripción).
- **No se revisó la consola del navegador** ni las pantallas a mano (carrito, catálogo con presentaciones, app).
- Prueba integral (caso 44, 2026-11-16): pedido mayorista por el carrito (cajas + escala), otro por la app y otro por el
  ERP (origen CARRITO / APP / ERP), los tres liberados para distribución en una hoja de ruta confirmada (lista de carga
  con las cajas en unidades), remito parcial del pedido del carrito (10 de 30 u y 1 de 2 cajas, hereda la caja) y
  factura desde el remito (hereda caja y escala; el stock lo baja solo el remito) 20/20.

### Revisión de seguridad (lo nuevo)
- Precio, descuentos, canal, mínimos y disponibilidad: siempre del servidor (carrito y app mandan producto,
  presentación y cantidad; probado con precio y descuento manipulados).
- App: sesión del ERP + indicador + vendedor activo; cliente validado contra la cartera vigente en cada lectura y
  escritura; escrituras POST con CSRF y funciones de la página de pedidos; no edita un pedido que no sea su borrador
  APP. Sin costos ni márgenes en las respuestas.
- Carrito: rutas sin cambios (auth + permiso + csrf); los ítems en presentación se borran o cambian con `carrito_id`
  en el WHERE (sin IDOR); el cliente solo ve su carrito y sus pedidos (sin cambios).
- Sin secretos en los commits: `.env` y `.env.bak` del carrito fuera del repositorio (`.gitignore`); las pruebas arman
  un `.env` temporal fuera de las carpetas y lo borran.
- Hallazgos preexistentes, sin tocar: `dbtest.php`, `diag.php` (sin clave), `diagnostico.php`, `permisos.php` y los
  `.bk` del carrito se sirven por HTTP; `PrecioRepository.php:45` cruza entidad con empresa (sin la integración);
  ids fijos en 1 en `config/app.php` del carrito.
- Sin API ni límites de uso (opción A: no hay endpoint nuevo expuesto entre sistemas).

### Guía corta para el cliente (carrito)
Buscá el producto: cada presentación (caja, pack) aparece debajo con su precio, el mínimo y la disponibilidad; la
unidad suelta solo si tu lista la vende. Sumá cajas o unidades con + y −; si una escala o promoción aplica, el carrito
lo muestra y lo descuenta solo. Confirmá el pedido como siempre: queda confirmado y numerado.

### Guía corta para el vendedor (app)
Entrá al sistema con tu usuario y abrí `/app_vendedor/` en el celular (agregala a la pantalla de inicio). Elegí el
cliente de tu cartera, buscá por código, nombre o marca, o escaneá el código de barras (si el navegador no puede,
escribilo). "Guardar" deja el pedido en preparación (lo retomás cuando quieras, también desde otro equipo);
"Confirmar" lo confirma. Si tu descuento supera tu tope, queda guardado para que lo confirme alguien con tope.
Si se corta la conexión, lo cargado queda en el teléfono.

### Puesta en marcha
1. Migraciones, en orden, en cada base: `20261123_canales_carrito_app_01.sql` y `_02_carrito_presentaciones.sql`
   (requieren los scripts de la distribuidora 20261112 a 20261122).
2. Código del ERP (espejo) y del carrito (rama `distribuidora`).
3. Carrito: en su `.env`, `ERP_CORE_PATH=` la carpeta del ERP en el mismo servidor (la que contiene `core/`).
4. Por empresa, en Configuración > Empresas: "Usa el carrito integrado" y / o "Usa la aplicación del vendedor"
   (la app necesita además "Usa vendedores"; presentaciones, canales, escalas y distribución, según lo que se use).
5. Clientes del carrito: como hoy (usuario = CUIT de una entidad con rol cliente y acceso web). Vendedores: usuario
   del ERP ligado al vendedor (Vendedores y Carteras), con la cartera asignada y un perfil con agregar y
   confirmar_pedido en Pedidos Realizados; tope de descuento por perfil en Escalas y promociones.
6. Vuelta atrás: apagar los interruptores (o dejar vacía `ERP_CORE_PATH`): el carrito vuelve a su camino de siempre y
   la app no se abre. Las tablas y columnas nuevas pueden quedar. Código: revertir los commits de la tabla de arriba.

### Para la etapa 5
Modo sin conexión de la app (service worker, cola de pedidos); comisiones de vendedores; saldo y facturas en el
carrito; pedido mínimo en monto; presentaciones en la ficha del producto del carrito; pantalla de mostrador (si se
confirma); prueba integral carrito + app + ERP hasta distribución, remito parcial, factura y stock; revisión en el
navegador (consola) de carrito y app; sacar del servidor los diagnósticos del carrito.
