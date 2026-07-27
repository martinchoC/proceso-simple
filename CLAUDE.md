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

- **Empresa**: cada empresa/cliente del sistema (multi-tenant). Casi toda tabla de negocio tiene `empresa_id`.
- **Módulo**: agrupación de páginas (`conf`, `gestion`, `ml` = Matriz Legal). `conf__modulos.modulo_id`.
- **Página**: una pantalla del sistema. Vive en `conf__paginas`, con árbol vía `padre_id` y orden vía `orden`.
- **Función**: una acción posible dentro de una página (Agregar, Editar, Inhabilitar, Confirmar, etc.), vive en `conf__paginas_funciones`.
- **Perfil**: rol de acceso. Un usuario tiene un perfil por empresa (`conf__usuarios_perfiles`); el perfil determina qué funciones de qué páginas puede ejecutar (`conf__perfiles_funciones`, con posible override específico en `conf__empresas_perfiles_funciones`).

## El motor de estados (lo más importante de todo)

Cada tabla de negocio tiene una columna `tabla_estado_registro_id` (NO `estado_id` directo). Este id apunta a `conf__tablas_estados_registros`, que vincula una tabla física (`conf__tablas`) con un estado genérico (`conf__estados_registros`: Activo, Inactivo, Borrador, Pendiente de Aprobación, Confirmado, Eliminado, etc.).

**Los botones de acción NO están hardcodeados.** Se calculan así:
1. `conf__paginas_funciones` define, para cada página, qué funciones existen y su transición: `tabla_estado_registro_origen_id → tabla_estado_registro_destino_id`.
2. Dado el estado actual de un registro, el backend filtra las funciones cuyo `origen_id` coincide, y esas son las que se muestran como botones.
3. El frontend ejecuta la acción vía `accion_js` (string como `'agregar'`, `'editar'`, `'inhabilitar'`, `'confirmar'`) contra el endpoint genérico `ejecutar_accion` del `_ajax.php`.

Dos "tipos de tabla" (`conf__tablas_tipos`) marcan la complejidad esperada:
- **Diccionario** (tipo 1): CRUD simple, solo Activo/Inactivo. Ejemplo: `depositos`.
- **Proceso Corto/Largo** (tipo 2/3): workflow con Borrador → Pendiente de Aprobación → Confirmado → Eliminado (y variantes: Pendiente de Recepción → Recibido, etc). Ejemplo: `facturas_proveedores`, `ventas_facturas`, `ordenes_compra`.

Al crear una página nueva: primero definir su fila en `conf__tablas` (con el `tabla_tipo_id` correcto), después sus estados en `conf__tablas_estados_registros`, después sus funciones en `conf__paginas_funciones`. Sin esto, el motor de botones no tiene nada que mostrar.

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

## Entorno de desarrollo

No hay gestor de paquetes ni build step: sin `composer.json`, sin `package.json`, sin bundler. El proyecto se sirve directo desde la raíz con Apache/XAMPP. Tampoco hay suite de tests automatizada (no correr/asumir `npm test`, `phpunit`, etc. — no existen).

Base de datos: `gestion_multipyme` (MySQL/MariaDB). **Conviven varios puntos de conexión** porque el proyecto migró de convención más de una vez:
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
- `test.php` (raíz) — contiene credenciales de base de datos de producción hardcodeadas y está commiteado al repo. Marcado aquí para que no se use como ejemplo de conexión; considerar rotar esa credencial y eliminar el archivo.

## Multi-tenant

Casi toda función de `_model.php` recibe `$empresa_idx` y lo usa en el `WHERE` de cada query. **Nunca omitir el filtro de empresa** — es el único aislamiento entre clientes del sistema. `header1.php` resuelve la empresa activa desde `$_GET['empresa_id']` (no desde sesión), validando contra `conf__empresas_perfiles` + `conf__usuarios_perfiles` que el usuario tenga acceso a esa empresa.

## Convenciones de código a respetar

- `mysqli` puro, siempre prepared statements con bind por tipo (`"iis"`, etc.), nunca concatenar variables en el SQL.
- Debug vía `error_log()` con prefijos tipo `"=== INICIO editarDeposito ID: $id ==="`, no `var_dump`.
- Helpers de asset ya existentes: `asset_local()` (assets propios) y `asset()` (assets AdminLTE/vendor). `url()` para construir rutas internas.
- Los estados "obtener nombre de estado" hacen `SHOW COLUMNS` defensivo sobre `conf__estados_registros` antes de armar el SELECT (por si la columna se llama `estado_registro`, `nombre_estado` o `descripcion` según versión de la tabla). Mantener esa compatibilidad al tocar ese código, no asumir un solo nombre de columna.
- CDNs externos (DataTables buttons, jszip, pdfmake, SweetAlert2) se cargan por `<script src>` directo en cada `pagina.php`, no hay bundler.

## Ejemplos de referencia ya en el repo

- **CRUD simple (Diccionario)**: `gestion/depositos.php` + `depositos_ajax.php` + `depositos_model.php` + `depositos.js`
- **Proceso Largo con master-detail**: `gestion/facturas_proveedores.php` + su `_model.php` (~2700 líneas: además del CRUD, maneja detalle de items, actualización de stock al confirmar, actualización de costos de producto, generación de asientos contables, cálculo de impuestos/IVA por jurisdicción, sincronización de comprobantes fiscales)
- **Motor de configuración (módulo `conf`)**: `paginas.php` + `paginas_ajax.php` + `paginas_model.php` — el ABM que administra el propio árbol de páginas/funciones descripto arriba

## Qué falta documentar (pendiente, no asumir)

- Integración ARCA/AFIP (facturación electrónica) — no se subieron los archivos correspondientes todavía.
- Reglas de negocio fiscales argentinas específicas (IVA, condiciones fiscales) más allá de lo que se ve en el schema.
- Convenciones de nomenclatura de branches/commits si las hay.

## Regla para Claude Code

Antes de generar una página nueva, replicar el patrón de 4 archivos + las 3 tablas de configuración (`conf__tablas`, `conf__tablas_estados_registros`, `conf__paginas_funciones`) usando `depositos` como plantilla si es CRUD simple, o `facturas_proveedores` si involucra workflow de estados y/o detalle. Ante cualquier duda sobre una convención no cubierta acá, preguntar antes de inventar un patrón nuevo.
