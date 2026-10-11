# gestion_multipyme — Contexto para Claude Code

ERP multi-tenant en PHP/MySQL/AdminLTE para pymes argentinas, desarrollado por Developsam SAS.
Este documento existe para que Claude Code entienda la arquitectura ANTES de generar o modificar código.
No es un manual de usuario: es la guía de convenciones que el código no explica por sí solo.

## Stack

- PHP puro (sin framework) + MySQL/MariaDB (mysqli, prepared statements)
- AdminLTE + Bootstrap 5 + DataTables + SweetAlert2
- AJAX (jQuery) para toda interacción con backend, sin recargas de página
- Sin ORM: todas las queries son SQL directo en los archivos `_model.php`

## Arquitectura jerárquica

```
Empresas ──< Empresas_Modulos >── Módulos ──< Páginas (árbol vía padre_id) ──< Páginas_Funciones (botones/acciones)
Usuarios ──< Usuarios_Perfiles >── Perfiles ──< Perfiles_Funciones (qué función de qué página puede ejecutar)
                                            └─< Empresas_Perfiles ──< Empresas_Perfiles_Funciones (override por empresa)
```

- **Empresa**: cada empresa/cliente del sistema (multi-tenant). Casi toda tabla de negocio tiene `empresa_id`. Empresas actuales: 1 = CONFIGURACION, 2 = Casa Lucho (titular), 3 = CEC Patagones; solo en la base local, 6 = Developsam SAS (demo del módulo 5 `gestion_normal`, configuración clonada de la 2, usuarios jgili / gblanco). En `gestion` y `gestion_normal` ya no hay fallbacks a la empresa 2: ver `core/empresa_config.php`.
- **Módulo**: agrupación de páginas. `conf__modulos.modulo_id`; la carpeta sale de `modulo_url` y el prefijo de tablas de `base_datos`:

| id | Módulo | Carpeta | Prefijo tablas | Empresas |
|---|---|---|---|---|
| 1 | Manejo de Módulos y Usuarios | `modules/conf` | `conf__` | 1, 2 |
| 2 | Gestión de Empresas | `modules/gestion` | `gestion__` | 2, 3 |
| 3 | Matriz Legal | `modules/ml` | `ml__` | 2, 3 |
| 4 | Estudio Contable | `modules/estudio_contable` | `estudio__` (+ escribe en `gestion__`) | 2 |
- **Página**: una pantalla del sistema. Vive en `conf__paginas`, con árbol vía `padre_id` y orden vía `orden`.
- **Función**: una acción posible dentro de una página (Agregar, Editar, Inhabilitar, Confirmar, etc.), vive en `conf__paginas_funciones`.
- **Perfil**: rol de acceso. Un usuario tiene un perfil por empresa (`conf__usuarios_perfiles`); el perfil determina qué funciones de qué páginas puede ejecutar (`conf__perfiles_funciones`, con posible override específico en `conf__empresas_perfiles_funciones`).

## El motor de estados (lo más importante de todo)

Cada tabla de negocio tiene una columna `tabla_estado_registro_id` (NO `estado_id` directo). **Pese al nombre, guarda el id GENÉRICO de `conf__estados_registros.estado_registro_id`** (1 Activo, 2 Inactivo, 3 Borrador, 4 Pend. Aprobación, 5 Confirmado, 6 Eliminado, 12 Facturado, 13 Pend. Facturación, 15 Fact. Parcial, 38 Anulado, etc.), no la PK de `conf__tablas_estados_registros`. Es lo que muestran los datos y la decisión 2 de `docs/matriz-legal/03-plan-de-modulos.md`.

`conf__tablas_estados_registros` es el catálogo por tabla: para cada `(tabla_id, estado_registro_id)` da la etiqueta propia de esa tabla (`tabla_estado_registro`, p. ej. "Pendiente de ARCA" para el 4 en ventas_facturas), `orden`, `color_id`, `es_inicial` y `es_final`. El estado inicial de una tabla de proceso se resuelve con `es_inicial = 1` (patrón `vfEstadoInicial()` en `ventas_facturas_model.php` / `obtenerEstadoInicialCobranza()`), y para mostrar el nombre se hace `JOIN conf__estados_registros er ON er.estado_registro_id = x.tabla_estado_registro_id` (o se cruza `ter.tabla_id = N AND ter.estado_registro_id = x.tabla_estado_registro_id` para la etiqueta propia).

Tesorería se migró al id genérico el 2026-10-07 (`sql/mantenimiento/20261007_tesoreria_04_estados_genericos.sql`; reemplaza lo que dice la spec v3.11 en §1/§381). Los ids genéricos 25 y 28–40 los usa el código como constantes fijas: ese script verifica que existan con esos ids. Estados derivados del saldo en `gestion__comprobantes`: 39 Cancelado parcial / 40 Cancelado total, asignados solo por `recalcularEstadoDestino()` sobre comprobantes en 5/39/40 (los cancelables son `CONFIRMADO` o `CANC_PARCIAL`); `syncComprobante()` no los revierte. El registro de origen acompaña ese cambio (`tesoreriaSincronizarEstadoOrigen()`) solo si el estado figura en `conf__tablas_estados_registros` de su tabla (hoy: ventas_facturas; para sumar otra tabla, cargar 39/40 en su catálogo y sus funciones/permisos desde esos estados, como `20261007_tesoreria_05_estado_origen.sql`). Asientos anulados = 2.

⚠️ Excepciones vigentes, no copiar: Matriz Legal (`modules/ml/`) guarda la PK de `conf__tablas_estados_registros` (2001–2200) y la cruza por PK, al revés de la decisión 2 de su propio plan. El módulo `estudio_contable` carga en `conf__tablas_estados_registros` filas con `estado_registro_id` ordinales (1..8) que no coinciden con el significado genérico (p. ej. en tabla 414 "Presentado" = 6 = Eliminado genérico).

**Los botones de acción NO están hardcodeados.** Se calculan así:
1. `conf__paginas_funciones` define, para cada página, qué funciones existen y su transición: `tabla_estado_registro_origen_id → tabla_estado_registro_destino_id` (también ids genéricos; `origen = 0` = función de creación, la que devuelve `obtener_boton_agregar`).
2. Dado el estado actual de un registro, el backend filtra las funciones cuyo `origen_id` coincide, y esas son las que se muestran como botones.
3. El frontend ejecuta la acción vía `accion_js` (string como `'agregar'`, `'editar'`, `'inhabilitar'`, `'confirmar'`) contra el endpoint genérico `ejecutar_accion` del `_ajax.php`.

Dos "tipos de tabla" (`conf__tablas_tipos`) marcan la complejidad esperada:
- **Diccionario** (tipo 1): CRUD simple, solo Activo/Inactivo. Ejemplo: `depositos`.
- **Proceso Corto/Largo** (tipo 2/3): workflow con Borrador → Pendiente de Aprobación → Confirmado → Eliminado (y variantes: Pendiente de Recepción → Recibido, etc). Ejemplo: `facturas_proveedores`, `ventas_facturas`, `ordenes_compra`.

Al crear una página nueva: primero definir su fila en `conf__tablas` (con el `tabla_tipo_id` correcto), después sus estados en `conf__tablas_estados_registros`, después sus funciones en `conf__paginas_funciones`, y por último los permisos en `conf__perfiles_funciones` **y** `conf__empresas_perfiles_funciones` (esta última es la única fuente que lee `core/permisos.php`; una página sin filas ahí queda denegada para todos salvo el superadmin `USUARIOS_ACCESO_TOTAL = [1]`). Sin esto, el motor de botones no tiene nada que mostrar. Todo alta de este tipo va en un script versionado en `sql/mantenimiento/AAAAMMDD_<tema>_NN_<paso>.sql`, no en scripts sueltos de `scratch/`.

Bloques de ids usados por módulo (respetarlos para que local y producción coincidan):

| Tabla | conf/gestion | ml (3) | estudio (4) |
|---|---|---|---|
| `conf__tablas` | 1–109, 270–289 (tesorería), 423–434 | 201–269 | 401–422 |
| `conf__paginas` | 1–116, 364, 441 (módulo 5: misma página + 500) | 300–399 | 400–440 |
| `conf__paginas_funciones` | < 3000, 3276–3286, 3335+ (módulo 5: + 5000, salvo choques: ver el script) | 3001–3999 (compartido en la práctica) | 3291+ |
| `conf__tablas_estados_registros` | < 2000, 2111–2194 | 2001–2200 | 2213+ |
| `conf__estados_registros` | 1–15, 28–38 | 16–27 | — |

## Estructura de archivos por página

Cada pantalla tiene 4 archivos, todos con el mismo nombre base:

| Archivo | Rol |
|---|---|
| `pagina.php` | Vista HTML. Incluye `header1.php`/`footer1.php`. Define `$modudo_idx` (⚠️ typo intencional y consistente en todo el proyecto — NO corregir a `$modulo_idx`) y `$pagina_idx` con el id de `conf__paginas`. |
| `pagina_ajax.php` (o `pagina-ajax.php`, hay ambas convenciones en el código viejo — para código nuevo usar `pagina_ajax.php`) | Dispatcher por `$_GET['accion']` / `$_POST['accion']`. Siempre responde `Content-Type: application/json`. |
| `pagina_model.php` | Toda la lógica de negocio y SQL. Funciones con `mysqli_prepare` + bind + `mysqli_stmt_execute`. Escrituras dentro de `mysqli_begin_transaction` / `commit` / `rollback` con try/catch. |
| `pagina.js` | jQuery + DataTables + SweetAlert2. Sin frameworks de componentes. |

### Acciones estándar que todo `_ajax.php` implementa
`listar`, `obtener_boton_agregar`, `ejecutar_accion` (transición de estado genérica), `agregar`, `editar`, `obtener`, `obtener_estados` — más acciones específicas del dominio de esa página.

### Convención de respuesta
Todo devuelve JSON. Dos formatos conviven en el código actual:
- `{resultado: bool, ...}` — típico en `agregar`/`editar` (`_model.php` con `agregarX`/`editarX`)
- `{success: bool, error/message: string, ...}` — típico en acciones de estado y errores

Para código nuevo, preferir `{success: bool, data: ..., message: string}` de forma consistente (es el estándar que se está migrando en el resto del ERP).

### Seguridad del `_ajax.php` (obligatorio)
Todo `_ajax.php` arranca con el guard central `core/ajax_guard.php`, como primera instrucción (después de `declare(strict_types=1)` si lo hay):
```php
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['pagina_idx' => 402]);   // $ctx['usuario_id'], $ctx['empresa_id']
```
- Exige sesión de `login.php` (si no, 401 JSON; `footer1.php` redirige al login en los 401 de jQuery).
- Resuelve la empresa de `empresa_idx` (u otros parámetros con `'params'`), si no viene de `$_SESSION['empresa_id']`, **sin default**, y valida que el usuario tenga perfil en ella (403). Si el parámetro no vino, lo completa en `$_GET/$_POST/$_REQUEST`, así el viejo `$_GET['empresa_idx'] ?? 2` toma la empresa de la sesión.
- Opciones: `'pagina_idx'` (acceso a esa página), `'modulo_id' + 'paginas_url'` (acceso a alguna de las páginas con esas urls; si ninguna está configurada, solo superadmin), `'empresa' => false`, `'solo_superadmin' => true`.
- Usar `$ctx['empresa_id']` / `$ctx['usuario_id']`; nunca `$_SESSION['usuario_id'] ?? 1` ni empresa por default.
- `$pagina_idx` fijo en el archivo, nunca del request. Si una página no puede fijarlo, usar `pagina_id_por_url()` de `core/permisos.php` (ver `gestion/clientes.php`).
- Estado actual: todos los `_ajax.php` de `gestion/` y `conf/` validan sesión + empresa + página. Los que se usan desde varias pantallas listan todas en `paginas_url` (p. ej. `ventas_remitos_ajax.php` → pedidos, nuevos pedidos, remitos y pendientes de facturación): **si una pantalla empieza a llamar a un endpoint de otra, agregarla a ese `paginas_url`**, si no sus usuarios reciben 403. Las copias legacy de `ml/` y `*/ajax/*.php` son solo superadmin; `ml/` y parte de `estudio_contable/` validan página con su propio código.
- Las páginas legacy fuera del menú (`depositos.php`, `comprobantes.php`, `compras_pedidos.php`, `pedidos_clientes.php`, `impuestos_jurisdicciones.php`, `funciones_contexto.php`, `pedidos_compra.php`) no fijan `$pagina_idx`, así que `header1.php` las abre para cualquier usuario logueado; sus endpoints, al no tener página configurada, quedaron solo para superadmin.

En el XAMPP local `php.ini` tiene `session.auto_start=1`, así que `$_SESSION` existe aunque el archivo no llame `session_start()`; no asumir lo mismo en producción (el guard llama `session_start()`).

### Accesos por sucursal (`core/accesos_sucursales.php`)
Capa de autorización adicional a la de páginas: `AccesosSucursalesHelper` resuelve qué sucursales / bocas / puntos de venta / tipos de comprobante puede usar un usuario según `gestion__perfiles_sucursales` (por `empresa_perfil_id`; `sucursal_id NULL` = toda la empresa) más `gestion__usuarios_sucursales_excepciones` (`PERMITE` suma, `DENIEGA` gana siempre, con vigencia `fecha_desde/hasta`). Sin filas = sin acceso (salvo `USUARIOS_ACCESO_TOTAL`). Se administra en `gestion/accesos_perfiles.php` (página 441). Uso en modelos:
- Listados: `$sql .= AccesosSucursalesHelper::filtroSqlSucursal($conexion, $uid, $empresa_id, 'alias.sucursal_id')`.
- Combos: `filtrarSucursales()` / `filtrarBocas()` / `filtrarPuntosVenta()` (si no hay `usuario_id` en sesión devuelven la lista completa).
- Escrituras: `exigirAccesoSucursal()` / `exigirAccesoPuntoVenta()` (lanzan `RuntimeException`).
Ya está aplicado en ventas (pedidos, remitos, facturas), compras (OC, facturas de proveedores), stock_ajustes, sucursales_ubicaciones y cobranzas. El código lo incluye con `if (file_exists(...))`.

## Entorno de desarrollo

No hay gestor de paquetes ni build step: sin `composer.json`, sin `package.json`, sin bundler. El proyecto se sirve directo desde la raíz con Apache/XAMPP. No hay `npm test` ni `phpunit`. Lo que hay es el **simulador de circuitos** `tests/simulacion/` (Python + PHP CLI): clona `gestion_multipyme` en `gestion_multipyme_sim` y llama a los `_ajax.php` reales con sesión simulada (superadmin, empresa 2), chequea efectos (estados, espejos en `gestion__comprobantes`, stock, saldos, imputaciones, caja, asientos) y la consistencia global. `python tests/simulacion/run.py [grupo|caso] [--scripts sql/mantenimiento/x.sql]`; nunca escribe en la base real. Ver `tests/simulacion/README.md`; lo opera el agente `tester-circuitos`.

Base de datos: `gestion_multipyme` en local (MariaDB 10.4.22); producción es `ps_gestion_casalucho` en otro servidor (MySQL 8.0.46). Todo script SQL tiene que correr en los dos motores: sin `ADD INDEX IF NOT EXISTS`, sin columnas `JSON`, sin `DEFAULT` en `TEXT`, charset `utf8mb4` / `utf8mb4_unicode_ci`, y el DDL no es transaccional (los scripts deben poder reanudarse). Volcados de referencia en la raíz: `casalucho_local.sql` (el más actual), `casalucho_desarrollo.sql`, `casalucho_produccion.sql`. **Conviven varios puntos de conexión** porque el proyecto migró de convención más de una vez:
- El vigente para páginas nuevas: `config.php` (raíz) → `db.php` (raíz), que lee `.env` (raíz, es PHP con `define()`, no formato `KEY=VALUE`). Es el que usa `templates/adminlte/header1.php` y por lo tanto toda página que lo incluya.
- `conexion.php` por módulo (p. ej. `modules/gestion/conexion.php`) con credenciales hardcodeadas — están en `.gitignore` (`modules/**/conexion.php`), o sea no versionados y pueden no existir en un checkout limpio.
- `boostrap.php` (raíz) apunta a `config/app.php` y `config/db.php`, que **no existen** (el directorio `config/` está vacío) — es código muerto, no usar como referencia.

Autenticación vigente: `$_SESSION['logueado']` seteado en `login.php` (login por usuario/password con `password_verify`), verificado en `templates/adminlte/header1.php` (redirige a `login.php` si falta). Existe un mecanismo alternativo/antiguo en `core/auth.php` (`validar_sesion($sid)`, tabla `conf__usuarios_sesiones`) usado solo por layouts viejos (`modules/gestion/layout/*.php`, `modules/ml/layout/*.php`, `modules/dashboard/index.php`, vía `core/plantilla.php`) — no es el flujo activo, no replicarlo en páginas nuevas.

## Código muerto / legacy — no usar como plantilla

El repo tiene varias capas de scaffolding abandonado conviviendo con el código activo. Al buscar un patrón de referencia, evitar:
- `modules/conf/backup/**` — copias de respaldo manuales de versiones anteriores.
- Archivos `* copy.php` (p. ej. `paginas_controlador copy.php`, `modules/ml/modulos_model copy.php`).
- `starter.php` — plantilla de ejemplo de AdminLTE sin relación con el sistema.
- `modules/gestion/layout/*`, `modules/ml/layout/*`, `core/plantilla.php` — layout AdminLTE 3 (Bootstrap 4, `data-widget=`) previo al layout activo (`templates/adminlte/header1.php`/`footer1.php`, AdminLTE 4 + Bootstrap 5, `data-lte-toggle=`).
- `scratch/` — ~190 scripts de diagnóstico y migraciones ad hoc (`check_*`, `desc_*`, `migrate_*`, `test_*`). Sirven para saber cómo se creó algo, no como patrón ni como forma de aplicar cambios de esquema.
- `modules/gestion/empresas*` es copia de `modules/conf/empresas*` (ajax y model idénticos) y `gestion/clientes*` ≈ `gestion/proveedores*` (≈ `entidades*`, ~5.000 líneas con el JS inline): un cambio en uno suele tener que replicarse en el otro.
- Tablas `xxx_*` y `*_bak2026*` del volcado — respaldos/descartadas.
- `test.php` (raíz) — contiene credenciales de base de datos de producción hardcodeadas y está commiteado al repo. Marcado aquí para que no se use como ejemplo de conexión; considerar rotar esa credencial y eliminar el archivo.

## Multi-tenant

Casi toda función de `_model.php` recibe `$empresa_idx` y lo usa en el `WHERE` de cada query. **Nunca omitir el filtro de empresa** — es el único aislamiento entre clientes del sistema. `header1.php` resuelve la empresa activa desde `$_GET['empresa_id']`, si no viene desde `$_SESSION['empresa_id']`, y si tampoco usa la empresa 2 o la primera disponible; la guarda en `$_SESSION['empresa_id']` / `$_SESSION['modulo_id']` y valida contra `conf__empresas_perfiles` + `conf__usuarios_perfiles` que el usuario tenga acceso. Después corta si el usuario no tiene permiso sobre `$pagina_idx` (solo si `$pagina_idx > 0`: una página que toma `$pagina_idx` de `$_GET`, como `clientes.php` y `proveedores.php`, queda sin control si se pasa `pagina_id=0`). El sidebar arma los links como `url?pagina_id=N&empresa_id=E&modulo_id=M`.

Excepción deliberada al aislamiento: `estudio_contable` (tenant del estudio = empresa 2) lee datos de otra empresa cuando `estudio__clientes.empresa_vinculada_id > 0` (modo "ERP" de mini-gestión y consola de auditoría); `estudio__vinculaciones` está pensada para registrar ese consentimiento, pero hoy el código no la consulta.

## Dominios transversales

### Comprobante universal
Todo documento con número (factura de venta, de proveedor, remito, pedido, recibo RCX, OP, etc.) tiene una fila espejo en `gestion__comprobantes`, ligada por `(tabla_origen_id = conf__tablas.tabla_id, registro_origen_id)` (UNIQUE). La crea/actualiza `syncComprobante($conexion, $data, $tabla_origen_id)` (hay dos copias con el mismo nombre: `ventas_facturas_model.php` y `facturas_proveedores_model.php`, no se pueden incluir juntas) o variantes propias (`syncComprobanteCobranza`). Lleva `importe_pendiente` (saldo, caché que mantiene tesorería) y `signo`. Numeración: `comprobante_nro = 0` en Borrador y se numera al confirmar; `uk_comp_numeracion` impide duplicados (la numeración de proveedores es por entidad, tabla 84).
Tipos de comprobante: `gestion__comprobantes_grupos` → `_subgrupos` (tienen `tabla_id`, `tipo_asiento_id`, `origen_asiento`) → `_tipos` (`comprobante_fiscal_id`, `letra`, `signo`, `impacta_stock/contabilidad/ctacte`). Los tipos que ofrece una página se resuelven `pagina → conf__paginas.tabla_id → subgrupos con ese tabla_id → tipos`, intersectado con `gestion__puntos_venta_comprobantes` del punto de venta (ver `vfTiposPorPuntoVenta`).

### Fiscal
- Condición fiscal del emisor: hay dos fuentes que conviven — `conf__empresas.condicion_fiscal_id` / `iibb_regimen_id` / `jurisdiccion_sede_id` (la que usa `vfObtenerRelacionFiscal`) y `gestion__empresas_parametros` (página 111, con vigencia `f_desde/f_hasta`, una sola abierta, además PV / lista / condición de pago por defecto). `conf/empresas_model.php` intenta mantenerlas sincronizadas.
- Receptor: `gestion__entidades_condiciones_fiscales` vigente a hoy; sin entidad = Consumidor Final (condición 2).
- `gestion__comprobantes_relacion_fiscal` (página 110): matriz emisor × receptor → `comprobante_fiscal_id` (A/B/C…) y `discrimina_iva`.
- IIBB: `gestion__iibb_regimenes`, `gestion__iibb_jurisdicciones_sedes`, `gestion__jurisdicciones`, `gestion__entidades_condiciones_iibb` (+ `_jurisdicciones`: altas de un CM), padrón de percepción en `gestion__padrones_iibb` (`gestion__entidades_impuestos_padron` quedó sin uso).
- **Percepciones IIBB practicadas** (empresa agente; diseño y normativa de Río Negro en `docs/gestion/09_percepciones_iibb.md`): una fila de `gestion__empresas_impuestos_config` por empresa × jurisdicción × régimen (`impuesto_tipo_id = 6`, vigencia por `f_desde/f_hasta`, datos del agente en la solapa "Agente de Percepción" de la página 73) + alícuota por situación del cliente en `gestion__empresas_impuestos_config_situaciones`. Cálculo único en `gestion/percepciones_iibb_core_model.php` (`perIibbCalcular`): lo usa ventas_facturas al guardar (aviso) y al confirmar (estricto), graba en `gestion__comprobantes_impuestos` (con `jurisdiccion_id`, `situacion`, CRC del padrón y `liquidacion_comprobante_id`) y suma `importe_otros_impuestos`; va al asiento (cuenta de la config), a ARCA (`<Tributos>`, `arca_tributo_id`) y a la impresión. Lugar de la operación = localidad de la sucursal de la entidad. Solo comprobantes fiscales con cliente con CUIT. Padrones en la página 115, DDJJ (SIRCAR Diseño 1 / SIRCIP) y liquidación I_LIQ en la 116. Las fuentes de alícuota y los formatos de DDJJ van por driver (`percepciones_iibb_drivers.php`, docs §12): cada fuente (archivo, servicio web, carga manual) escribe filas normalizadas con vigencia y `situacion` en `gestion__padrones_iibb`, y el motor solo lee esas filas; sumar una provincia es sumar un driver, sin tocar el motor. Río Negro (empresa 2) está configurada inactiva hasta completar N° de agente, código de régimen y tributo ARCA; en la base local quedó activa por las ventas simuladas del 2026-10-11.

### Tesorería (páginas 100–109, tablas 270–289)
Diseño en `docs/gestion/08_proc_tesoreria_v3_11.docx` (decisiones D1–D36) y `sql/mantenimiento/20261003_tesoreria_0{1,2,3}_*.sql`. Conceptos: cuentas unificadas (`gestion__tesoreria_cuentas`: CAJA/BANCO/VALORES/CUPONES), libro inmutable `gestion__tesoreria_movimientos` (importe siempre > 0, dirección en `tipo_movimiento`, anulación por contramovimiento `reversa_de_movimiento_id`), imputaciones universales `gestion__comprobantes_imputaciones` (origen cancela destino, con estado propio y moneda/TC), reglas de qué tipo cancela a cuál en `gestion__comprobantes_tipos_cancelaciones`. Servicios centrales en `gestion/tesoreria_core_model.php`: `tesoreriaRegistrarMovimiento`, `tesoreriaRegistrarImputacion`, `recalcularEstadoDestino` (única fuente de estados derivados por saldo), `tesoreriaGenerarAsiento`, `tesoreriaResolverCuentaContable` (cuenta específica → `gestion__empresas_cuentas_conceptos` → excepción; nunca ids fijos). Locks en orden `comprobante_id ASC FOR UPDATE`. Retenciones practicadas (D32) no se usan en Casa Lucho.

### Precios de compra y venta
Costo en `gestion__productos_costos` (+ `_historial`), lista del proveedor en `gestion__productos_proveedores_precios` (una fila por vínculo `gestion__productos_proveedores`, histórico en `_historico`, `origen_carga` MANUAL/IMPORTACION/ESTIMADO/COMPRA), listas de venta en `gestion__listas_precios_productos` calculadas por `gestion__listas_precios_reglas` (lista origen COSTO o OTRA_LISTA encadenada; MANUAL no se recalcula). Costo neto de compra = precio de lista × (1 − descuento vigente de `gestion__entidades_condiciones_proveedores`). El motor compartido está en `gestion/precios_core_model.php` (lo usan `proveedor_precios_model.php` y `facturas_proveedores_model.php`); sus escrituras abren transacción propia, no llamarlas dentro de otra. La factura de proveedor confirmada siempre actualiza el costo; además, según `gestion__empresas_parametros.compra_actualiza_lista_proveedor` (0/1 solo sin precio/2 siempre) y `compra_actualiza_listas_venta` (0/1), carga la lista del proveedor y recalcula las de venta **después del commit** (`fpActualizarPreciosPorCompra`). Lista 3 "Mostrador" tiene un ajuste hardcodeado (`AJUSTE_HARDCODEADO_LISTA_PRECIO_ID`).

### Contabilidad
Asientos en `gestion__cont_asientos` + `_detalles` con **un solo importe con signo** (`importe_local` > 0 = Debe, < 0 = Haber; también `importe_ifrs` / `importe_impositivo`). Plan de cuentas `gestion__cont_cuentas` (jerárquico, `es_imputable`). Cuentas por defecto: hay tres mecanismos que se superponen — `gestion__productos_tipos` (por tipo de producto), `gestion__empresas_cuentas_conceptos` (tesorería, por concepto) y `gestion__cont_parametros` (enlace general, lo usa `estudio_contable`). Cierre de ejercicio en `gestion__cont_cierres_ejercicios` (refundición + cierre patrimonial, `estudio_contable/contabilidad_cierre_model.php`).

### ARCA (facturación electrónica)
`arca/arca_lib.php`: WSAA (TA reutilizado mientras esté vigente) + WSFEv1 (`arca_wsfe_solicitar_cae`, `arca_wsfe_ultimo_autorizado`, etc.). Configuración por empresa/ambiente en `gestion__integraciones_empresas` + `conf__integraciones_ambientes`, log de cada llamada en `gestion__integraciones_resultados`. La usan la página de pruebas `gestion/arca_integraciones.php` (99) y ventas_facturas, esta última **solo si `gestion__empresas_parametros.arca_habilitado = 1`** en la vigencia abierta (switch en Parámetros de Empresa, página 111; `vfArcaHabilitado`). Sin habilitar, la factura fiscal se numera al confirmar y "Aprobada" es manual. Habilitado: una factura fiscal (tipo con `comprobante_fiscal_id > 0`) queda en 4 "Pendiente de ARCA" **sin número** al confirmarla; "Aprobada" (4→5) pide el CAE con la única integración WSFE activa de la empresa y recién con el CAE pasa a 5 con el número de ARCA (+ `CAE`/`f_vto_CAE`, comprobante y asiento). Serializa por PV fiscal + tipo con `GET_LOCK`; ante timeout, el próximo "Aprobada" concilia con `FECompConsultar` del último autorizado. "Volver a Borrador" (4→3) para las rechazadas. Solo Factura A/B/C en pesos (NC/ND necesitan `CbtesAsoc`, que la factura no guarda). Ver `vfArcaAutorizarFactura` y `sql/mantenimiento/20261007_ventas_facturas_arca_01_funciones.sql`. Las no fiscales (X) siguen como antes. Impresión: `gestion/ventas_facturas_print.php` (acción `imprimir`), formato ARCA con el logo de Parámetros de Empresa (`gestion__empresas_parametros.logo_archivo`, archivo en `uploads/empresas/`; una vigencia nueva lo hereda) y QR (RG 4291) + CAE solo si la factura fiscal tiene CAE. Formato por empresa en `gestion__empresas_impresion` (A4 / ticket 80 mm, copias, columnas, texto al pie, tipografía de `IMPRESION_FUENTES` vía Google Fonts; por subgrupo de comprobante con fallback a la general `comprobante_subgrupo_id = 0`), resuelto por `core/impresion.php`; las plantillas son compartidas entre `gestion` y `gestion_normal` en `templates/impresion/`. Spec: `Especificacion_Integraciones_ARCA_ERP.docx`.

### Módulo 3 — Matriz Legal (`modules/ml/`)
Documentado en `docs/matriz-legal/` (01 lógica = fuente de verdad, 02 casos C01–C36, 03 plan de módulos M0–M8, 04 DBML, 07 clasificación de tablas). Cadena: norma → obligación definida → obligación asignada (por herencia territorial/corporativa, `motor_herencia_service.php`) → ciclo (unidad auditable, no se edita: se corrige con ciclo complementario) → tareas con RACI. Servicios: `motor_herencia_service.php`, `ciclos_generador_service.php`, `ejecucion_seguimiento_service.php`, `cargas_masivas_service.php`. Fechas en `DATETIME` UTC con el huso del país de la entidad; toda relación con vigencia (nada se borra, se cierra con fecha). Sus 30 `_ajax.php` siguen el patrón seguro.

### Módulo 4 — Estudio Contable (`modules/estudio_contable/`)
Para un estudio contable que atiende clientes (`estudio__clientes`, con terminación de CUIT, jurisdicciones/CM05, tasas municipales). Páginas: padrón (402), tablero y generador de vencimientos (403/404, `estudio__calendario_vencimientos` → `estudio__vencimientos_generados`), honorarios y facturación de abonos en lote (405/406: crea `gestion__ventas_facturas` confirmadas + `syncComprobante`, sin CAE), normas con reglas de impacto (407), tareas RACI con time tracking (408), licencias con delegación automática de tareas (409), activos fijos y amortizaciones (410, `gestion__activos_fijos*`), consola de auditoría (411), parámetros contables (412), cierre de ejercicio (413), conceptos sin stock (414, sobre `gestion__productos`), recibos con retenciones sufridas (415, `gestion__cobranzas_retenciones`), plan de cuentas (416) y mini-gestión (`minigestion.php`, sin página propia: reutiliza `$pagina_idx = 412`). Mini-gestión trabaja en modo MANUAL (`estudio__minigestion_comprobantes`) o ERP (lee la empresa vinculada). Dos estilos conviven: funciones sueltas con `usuario_tiene_acceso_pagina` (clientes, vencimientos, honorarios, normas) y clases `XxxModel` cuyos `_ajax.php` no validan sesión (contabilidad_*, minigestion_*). Sus tablas se crearon con scripts de `scratch/`, no hay script en `sql/mantenimiento/`. Se superpone conceptualmente con Matriz Legal (normas, RACI, delegaciones, vencimientos) con tablas propias.

## Convenciones de código a respetar

- `mysqli` puro, siempre prepared statements con bind por tipo (`"iis"`, etc.), nunca concatenar variables en el SQL.
- Debug vía `error_log()` con prefijos tipo `"=== INICIO editarDeposito ID: $id ==="`, no `var_dump`.
- Nunca ids fijos de configuración de una empresa (tipo de asiento, moneda, cuentas contables, subgrupos/tipos de comprobante, boca, medio de pago, punto de venta, tipo de producto, alícuota de IVA): se resuelven por `empresa_id` + código con `core/empresa_config.php` (`emp_tipo_asiento_id`, `emp_moneda_id` / `emp_moneda_base_id`, `emp_cuenta_id` / `emp_cuenta_concepto_id`, `emp_subgrupo_id`, `emp_boca_id`, `emp_medio_pago_id`, `emp_cuit`...). Cada empresa tiene su propio plan de cuentas: las cuentas por defecto nunca se buscan por id ni por código del plan, salen del concepto de la empresa en `gestion__empresas_cuentas_conceptos` (Tesorería > Conceptos Contables: DEUDORES_VENTAS, VENTAS, IVA_DEBITO, PROVEEDORES, VALORES_CARTERA, IVA_A_PAGAR, GASTOS_GENERALES, MERCADERIAS, EMPLEADOS, VALORES_A_PAGAR; siembra `sql/mantenimiento/20261010_cuentas_conceptos_01_siembra.sql`) y, si no está, de `gestion__cont_parametros`; si tampoco, error explícito. Todo asiento pasa por `emp_validar_cuentas_asiento()`: ninguna cuenta de otra empresa. El certificado de ARCA es de cada empresa: el CUIT de la integración tiene que ser el de `conf__empresas.documento_numero` y la página solo lista certificados emitidos para ese CUIT. Cada empresa tiene sus puntos de venta: en los comprobantes propios `comprobante_pv` guarda el **id** del PV, pero lo que se muestra es su `codigo_fiscal` (el de ARCA y la impresión): en PHP `emp_numero_comprobante()` / `emp_pv_numero()`, en SQL `emp_sql_pv_mostrar()` (en la tabla 84, facturas de proveedores, `comprobante_pv` es el PV del proveedor y se muestra tal cual). Nunca formatear `comprobante_pv` / `punto_venta_id` directo con `str_pad`/`padStart`. Todo catálogo por empresa (monedas, IVA, tipos de producto, categorías, condiciones de pago, listas, medios de pago) se consulta con `WHERE empresa_id`. En un `_ajax.php` la empresa es `$ctx['empresa_id']`, nunca un default `?? 2`.
- Helpers de asset ya existentes: `asset_local()` (assets propios) y `asset()` (assets AdminLTE/vendor). `url()` para construir rutas internas.
- Los estados "obtener nombre de estado" hacen `SHOW COLUMNS` defensivo sobre `conf__estados_registros` antes de armar el SELECT (por si la columna se llama `estado_registro`, `nombre_estado` o `descripcion` según versión de la tabla). Mantener esa compatibilidad al tocar ese código, no asumir un solo nombre de columna.
- CDNs externos (DataTables buttons, jszip, pdfmake, SweetAlert2) se cargan por `<script src>` directo en cada `pagina.php`, no hay bundler.

## Ejemplos de referencia ya en el repo

- **CRUD simple (Diccionario)**: `gestion/depositos.php` + `depositos_ajax.php` + `depositos_model.php` + `depositos.js`
- **Proceso Largo con master-detail**: `gestion/facturas_proveedores.php` + su `_model.php` (~2700 líneas: además del CRUD, maneja detalle de items, actualización de stock al confirmar, actualización de costos de producto, generación de asientos contables, cálculo de impuestos/IVA por jurisdicción, sincronización de comprobantes fiscales)
- **Motor de configuración (módulo `conf`)**: `paginas.php` + `paginas_ajax.php` + `paginas_model.php` — el ABM que administra el propio árbol de páginas/funciones descripto arriba
- **Proceso con estados genéricos ya migrado + accesos por sucursal + tesorería**: `gestion/cobranzas*`.
- **AJAX seguro**: `gestion/arca_integraciones_ajax.php`, `ml/*_ajax.php`.
- **Agentes del proyecto** en `.claude/agents/` (cadena `analista-procesos` → `arquitecto-bd` → `disenador-modelo-datos` → `orquestador-dev` para módulos GRANDES; `disenador-proceso` para el resto; revisores `revisor-sql`, `revisor-ajax`, `revisor-convenciones`, `revisor-casos-borde`; `tester-circuitos` (simulador de `tests/simulacion`: prueba, reporta y corrige); `doc-tecnica` y `manual-usuario`).

## Qué falta documentar (pendiente, no asumir)

- Reglas de negocio fiscales argentinas específicas (IVA, condiciones fiscales) más allá de lo que se ve en el schema y en la sección Fiscal.
- Convenciones de nomenclatura de branches/commits si las hay.

## Regla para Claude Code

Antes de generar una página nueva, replicar el patrón de 4 archivos + las 3 tablas de configuración (`conf__tablas`, `conf__tablas_estados_registros`, `conf__paginas_funciones`) usando `depositos` como plantilla si es CRUD simple, o `facturas_proveedores` si involucra workflow de estados y/o detalle. Ante cualquier duda sobre una convención no cubierta acá, preguntar antes de inventar un patrón nuevo.
