# Hallazgos del proyecto: verificación contra el repositorio

Fecha: 2026-10-02. Etapa de exploración: no se modificó código ni base.

Responde a las preguntas de `05-revision-y-verificacion.md` con evidencia del repositorio. Formato de las referencias: `archivo:línea`.

## Fuentes usadas

| Fuente | Qué es | Motor (cabecera del volcado) |
| --- | --- | --- |
| `casalucho_produccion.sql` | Volcado HeidiSQL de producción, base `ps_gestion_casalucho`, 177 tablas | **MySQL 8.0.46** (`casalucho_produccion.sql:3`) |
| `casalucho.sql`, `casalucho_local.sql`, `casalucho_desarrollo.sql` | Volcados locales, base `gestion_multipyme`, 173 tablas | **MariaDB 10.4.22** (`casalucho.sql:3`) |
| `aecbb1.sql` | Otro sistema (tablas `conf__empresa`, `conf__estado_registro`, etc.). **No es este esquema; no usar.** | MariaDB 11.4.13 |
| `_local/sql/mantenimiento/*.sql` | Scripts de alta de páginas posteriores a los volcados | — |
| `core/permisos.php`, `templates/adminlte/header1.php`, `modules/conf/*`, `modules/gestion/*` | Código | — |

Salvo que se indique otra cosa, los números de línea son de `casalucho_produccion.sql`. Los volcados locales coinciden en estructura; la diferencia es el motor (ver punto 4).

---

## Resumen: lo que cambia el plan

1. **Ya existe un módulo Matriz Legal en la base**: `conf__modulos` id 3, `'Matriz Legal'`, `base_datos='ml__'`, `modulo_url='ml'` (`:26932`). Está habilitado en las empresas 2 y 3 (`:205-206`).
2. **Ya existen 23 tablas `ml__*`** en producción y en local (`:88556-89000`), registradas en `conf__tablas` con ids 49 a 71 (`:27679-27701`). **Diez de ellas chocan por nombre con el DBML v0.1** y tienen otra estructura: `ml__ambitos_tipos`, `ml__entidades`, `ml__entidades_tipos`, `ml__normas`, `ml__normas_tipos`, `ml__obligaciones`, `ml__obligaciones_jurisdicciones`, `ml__obligaciones_tipos_entidades`, `ml__procedimientos` y `ml__procedimientos_instrucciones`. Hay que decidir si se reemplazan, se migran o se renombran antes de generar DDL.
3. **El prototipo existente no usa `empresa_id`**: usa `empresa_ambito_id`, que apunta a `ml__ambitos_organizacionales`, no a `conf__empresas` (`:88596`, `:88608`). No cumple la regla multi-tenant de `CLAUDE.md`.
4. **`conf__registros_estados` no existe.** La tabla real es `conf__estados_registros` (`:700`).
5. **`tabla_estado_registro_id` guarda en la práctica el id genérico de `conf__estados_registros`**, no la PK de `conf__tablas_estados_registros`. Ver punto 2. Esto contradice `CLAUDE.md` y afecta cómo se modelan los 8 estados de ciclo y los 7 de tarea.
6. **Producción es MySQL 8, local es MariaDB 10.4.** Los scripts nuevos tienen que correr en los dos motores. El script `20260929_productos_compatibilidad_indices.sql:19` usa `ADD INDEX IF NOT EXISTS`, que es sintaxis exclusiva de MariaDB y falla en MySQL 8.
7. **El módulo 3 no tiene perfiles.** No hay `conf__perfiles` ni `conf__empresas_perfiles` con `modulo_id = 3` (`:27471-27474`, `:224-235`). Hoy solo lo ve el superadmin (`core/permisos.php:11`).
8. **`modules/ml/` no contiene código de matriz legal**: son copias de páginas de `conf` y `gestion` (35 archivos con `$modudo_idx = 2` y uno con `= 1`, ninguno con `= 3`). Ningún `.php` ni `.js` menciona `ml__`.

---

## 1. Nombres reales y columnas

### `conf__empresas` (`:165-178`): charset **latin1**

| Columna | Tipo |
| --- | --- |
| `empresa_id` (PK) | `smallint unsigned` AUTO_INCREMENT |
| `empresa` | `char(200)` |
| `documento_tipo_id` | `smallint` |
| `documento_numero` | `double` |
| `telefono`, `domicilio`, `email` | `char(250)` |
| `localidad_id` | `smallint` |
| `base_conf` | `varchar(150)` |
| `tabla_estado_registro_id` | `smallint NOT NULL DEFAULT 1` |

### `conf__usuarios` (`:27990-28002`)

| Columna | Tipo |
| --- | --- |
| `usuario_id` (PK) | `int` (con signo) AUTO_INCREMENT |
| `usuario_nombre` | `varchar(100) NOT NULL` |
| `usuario` | `varchar(20) NOT NULL`, UNIQUE (el índice se llama `email` pero cubre `usuario`, `:28001`) |
| `email` | `varchar(100) NOT NULL` |
| `password`, `password_temporal` | `varchar(255)` |
| `duracion_sid_minutos` | `int DEFAULT 60` |
| `tabla_estado_registro_id` | `smallint NOT NULL DEFAULT 1` |
| `creado_en` | `timestamp DEFAULT CURRENT_TIMESTAMP` |

### `conf__perfiles` (`:27460-27466`): perfil **base**, por módulo

| Columna | Tipo |
| --- | --- |
| `perfil_id` (PK) | `int unsigned` AUTO_INCREMENT |
| `modulo_id` | `int unsigned` |
| `perfil_nombre` | `varchar(100)` **utf8mb3_spanish2_ci** |
| `tabla_estado_registro_id` | `smallint NOT NULL DEFAULT 1` |

**Importante para el DBML:** los permisos efectivos no cuelgan de `conf__perfiles`, sino de `conf__empresas_perfiles` (`:210-219`: `empresa_perfil_id int unsigned`, `empresa_id int unsigned`, `modulo_id`, `perfil_id_base`, `empresa_perfil_nombre`). El usuario se asigna con `conf__usuarios_perfiles.empresa_perfil_id` (`:28022`). Las referencias del DBML a `conf__perfiles.perfil_id` (por ejemplo en `ml__usuarios_ambitos` y `ml__delegaciones`) deberían apuntar a `conf__empresas_perfiles.empresa_perfil_id`; de lo contrario no hay perfil por empresa.

### `conf__registros_estados`: **no existe**. La real es `conf__estados_registros` (`:700-708`)

| Columna | Tipo |
| --- | --- |
| `estado_registro_id` (PK) | `smallint unsigned` AUTO_INCREMENT |
| `estado_registro` | `varchar(50)` |
| `codigo_estandar` | `varchar(50)` |
| `valor_estandar` | `smallint unsigned` |
| `color_id` | `tinyint DEFAULT 1` |
| `orden_estandar` | `smallint unsigned` |

Datos (`:713-726`): 1 Activo, 2 Inactivo, 3 Borrador, 4 Pendiente de Aprobación, 5 Confirmado, 6 Eliminado, 7 Pendiente de Recepción, 8 Recibido, 9 En Preparación, 10 Entrega Parcial, 11 Entrega Total, 12 Facturado, 13 Pendiente de Facturación, 15 Facturación Parcial. **Ninguno corresponde a los 8 estados de ciclo ni a los 7 de tarea**: hay que darlos de alta.

### `conf__tablas` (`:27618-27628`)

| Columna | Tipo |
| --- | --- |
| `tabla_id` (PK) | `int` AUTO_INCREMENT |
| `tabla_nombre` | `varchar(50)` |
| `tabla_descripcion` | `text` |
| `modulo_id` | `int unsigned DEFAULT 0` |
| `tabla_tipo_id` | `int unsigned DEFAULT 0` |
| `tabla_patron_id` | `int unsigned DEFAULT 1` |
| `tabla_estado_registro_id` | `smallint NOT NULL DEFAULT 1` |

Ojo: `tabla_nombre varchar(50)`. Los nombres `ml__*` del DBML más largos que 50 caracteres no entran. Hoy ninguno lo supera, pero conviene revisarlo al nombrar.

Tipos de tabla (`conf__tablas_tipos`, `:27946-27949`): 1 Diccionario, 2 Proceso Corto, 3 Proceso Largo. Estados patrón por tipo (`conf__tablas_tipos_estados`, `:27966-27974`): el tipo 1 usa Activo/Inactivo; el tipo 2 usa Borrador, Pendiente de Aprobación, Confirmado, Eliminado, Pendiente de Recepción y Recibido. **El tipo 3 no tiene estados patrón cargados.**

### `conf__tablas_estados_registros` (`:27761-27770`)

| Columna | Tipo |
| --- | --- |
| `tabla_estado_registro_id` (PK) | `smallint` (con signo) AUTO_INCREMENT |
| `tabla_id` | `smallint unsigned` (pero `conf__tablas.tabla_id` es `int`) |
| `estado_registro_id` | `smallint unsigned` |
| `tabla_estado_registro` | `varchar(50)` (nombre propio del estado en esa tabla) |
| `color_id` | `tinyint` |
| `es_inicial` | `tinyint(1)` |
| `orden` | `smallint unsigned` |

---

## 2. Tipo de `tabla_estado_registro_id` y columna de empresa

### Tipo

Conteo sobre las 143 columnas `tabla_estado_registro_id` de producción:

| Tipo | Cantidad | Ejemplos |
| --- | --- | --- |
| `smallint` | 92 | `conf__empresas:175`, las 23 `ml__*` salvo una |
| `smallint unsigned` | 45 | `gestion__depositos:29252`, `ml__ambitos_tipos:88581` |
| `int` | 3 | `gestion__comprobantes:28353`, `gestion__facturas_proveedores:31561`, `gestion__ordenes_compra:46617` |
| `tinyint unsigned` | 2 | `gestion__cont_tipos_asientos:29209` |
| `tinyint` | 1 | `conf__empresas_modulos:193` |

En `conf__paginas_funciones`, `tabla_estado_registro_origen_id` y `tabla_estado_registro_destino_id` son `int unsigned` (`:27056-27057`).

El `SMALLINT(4)` que cita el punto 1 de `05` sale del volcado MariaDB (`casalucho.sql:24595`). MySQL 8 no muestra el ancho de visualización, así que es el mismo tipo. **Recomendación: `smallint NOT NULL DEFAULT 1`**, igual que la PK de `conf__tablas_estados_registros` y que la mayoría.

### Qué valor guarda realmente

`CLAUDE.md` dice que apunta a `conf__tablas_estados_registros`. El código y los datos muestran otra cosa:

- `facturas_proveedores_model.php:363` hace `LEFT JOIN conf__estados_registros er ON fp.tabla_estado_registro_id = er.estado_registro_id`.
- Las funciones de la página de facturas de proveedores (pagina 57) usan las transiciones 0→3, 3→4, 4→5 y 3→6 (`:27203-27211`). Son los ids **genéricos** (Borrador, Pendiente, Confirmado, Eliminado), no los ids propios de la tabla 84 en `conf__tablas_estados_registros` (147 a 150, `:27869-27872`).
- Lo mismo con depósitos (pagina 71): 0→1, 1→2 y 2→1 (`:27221-27225`). También con el script `20260925_productos_tipos_abm.sql`, que crea los estados 218/219 pero carga funciones con 1/2.
- Todos los filtros `tabla_estado_registro_id = 1` del código significan "Activo" genérico.

**Conclusión:** la columna guarda `conf__estados_registros.estado_registro_id`. `conf__tablas_estados_registros` funciona como lista de estados habilitados por tabla (con nombre y color propios), no como clave. El modelo de ciclos y tareas debe seguir esta convención, o bien plantear el cambio de forma explícita.

### Columna de empresa

- En la base **siempre es `empresa_id`**: 75 columnas, ninguna `empresa_idx`.
- **`empresa_idx` es solo el nombre de la variable PHP y del parámetro AJAX** (`depositos_ajax.php:28`, `arca_integraciones_ajax.php:38`). El SQL usa `WHERE empresa_id = ?` (`depositos_model.php:120`).
- Tipo de `empresa_id`: 34 `int unsigned`, 25 `int`, 16 `smallint unsigned`. **La PK es `smallint unsigned`** (`:166`), y es lo que usa el script más reciente (`20261001_arca_integraciones.sql:82`). Para declarar FOREIGN KEY contra `conf__empresas` el tipo tiene que coincidir: usar `smallint unsigned`. El `int` del DBML no sirve.
- Prototipo `ml__*`: no tiene `empresa_id`. Usa `empresa_ambito_id → ml__ambitos_organizacionales` (`:88596`, `:88754`, `:88909`).

---

## 3. Campos de auditoría

No hay una convención única. Conteo en producción:

| Columna | Tablas | Ejemplos |
| --- | --- | --- |
| `creado_en` | 24 | `conf__usuarios:27999`, `conf__colores:128` |
| `actualizado_en` | 18 | `conf__paginas_funciones:27059` (`ON UPDATE CURRENT_TIMESTAMP`) |
| `creado_por` | 11 | `gestion__listas_precios:31782` |
| `fecha_creacion` | 8 | `gestion__facturas_proveedores:31562` |
| `created_at` / `updated_at` | 7 / 6 | `gestion__logs:46345` |
| `usuario_creacion_id` / `usuario_modificacion_id` | 4 / 4 | `gestion__ordenes_compra:46620-46621` |
| `fecha_modificacion` | 3 | `gestion__facturas_proveedores:31563` |
| `fecha_alta` / `usuario_alta` | 3 / 3 | `gestion__entidades:29372-29373` |
| `f_alta` / `f_modificacion` | — | `gestion__depositos:29253-29254` |
| `fecha_creacion`, `usuario_creacion`, `fecha_actualizacion`, `usuario_actualizacion` | 1 | `conf__usuarios_perfiles:28025-28028` |
| `modificado_en` / `modificado_por` | **0** | — |
| `actualizado_por` | **0** | — |

- La mayoría de las `conf__*` principales (`conf__empresas`, `conf__perfiles`, `conf__tablas`, `conf__paginas`) **no tienen auditoría**. Las 23 `ml__*` existentes tampoco.
- Los nombres del DBML (`creado_por`, `creado_en`, `modificado_por`, `modificado_en`) coinciden a medias: `modificado_*` no existe en ninguna tabla.
- **Propuesta (a decidir):** `creado_en`, `creado_por`, `actualizado_en` y `actualizado_por`. Las tres primeras son las más usadas y `actualizado_por` completa el par. Las columnas `*_por` serían `int`, para coincidir con `conf__usuarios.usuario_id`, que es `int` con signo.

Existe además una tabla de log genérica, `gestion__logs` (`:46345-46353`), con `datos_anteriores` y `datos_nuevos` en JSON y CHECK `json_valid`. Puede servir de referencia para `ml__log_cambios`.

---

## 4. Motor y juego de caracteres

| Engine / charset / collation | Tablas | Ejemplos |
| --- | --- | --- |
| InnoDB / utf8mb4 / **utf8mb4_unicode_ci** | 146 | todas las `ml__*`, `conf__paginas`, `conf__tablas`, `gestion__depositos` |
| InnoDB / latin1 | 26 | **`conf__empresas:178`**, **`conf__modulos:26925`**, `conf__personas` |
| InnoDB / utf8mb3 | 5 | `gestion__comprobantes_imputaciones` |

- **Usar `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`**, como el 82% de las tablas y todo el prototipo `ml__`.
- Excepciones a tener en cuenta: `conf__empresas` y `conf__modulos` son latin1, y `conf__perfiles.perfil_nombre` es utf8mb3_spanish2_ci (`:27463`). Las FK y los cruces por columnas enteras no se ven afectados; solo los cruces por texto contra esas columnas.
- El script más nuevo, `20261001_arca_integraciones.sql:46`, usa `utf8mb4_general_ci`, distinto del resto. **No copiarlo.**
- **Versión del motor:** producción MySQL 8.0.46, local MariaDB 10.4.22. Los dos soportan CHECK (MySQL lo aplica desde 8.0.16; MariaDB desde 10.2.1), y ya hay CHECK en uso (`gestion__logs:46352`). Los dos soportan FOREIGN KEY: hay 60 en producción, 35 de ellas en las `ml__*`. Hay que evitar sintaxis exclusiva de un motor: `ADD INDEX IF NOT EXISTS` (solo MariaDB) o columnas `JSON` con semánticas distintas.
- `current_timestamp()` (estilo MariaDB) aparece solo en los volcados locales; MySQL lo exporta como `CURRENT_TIMESTAMP`. Los dos aceptan ambas formas.

---

## 5. Cómo se registra un módulo nuevo

### Cadena de tablas

```
conf__modulos ──< conf__empresas_modulos (empresa habilitada)
      │
      ├──< conf__tablas (tabla_tipo_id) ──< conf__tablas_estados_registros
      │
      ├──< conf__paginas (padre_id, orden, tabla_id) ──< conf__paginas_funciones (origen→destino, accion_js)
      │
      └──< conf__perfiles (base) ──< conf__perfiles_funciones (plantilla)
                │
                └─(perfil_id_base)─ conf__empresas_perfiles ──< conf__empresas_perfiles_funciones (permiso efectivo)
                                           │
                                           └──< conf__usuarios_perfiles (fecha_inicio / fecha_fin)
```

| Paso | Tabla | ABM que lo administra | Estado actual del módulo 3 |
| --- | --- | --- | --- |
| 1 | `conf__modulos` | `modules/conf/modulos.php` (`modulos_model.php:17` `agregarModulo`) | Existe (`:26932`). `layout_nombre=''` (los demás usan `'default'`); revisar. |
| 2 | `conf__empresas_modulos` | `modules/conf/empresas_modulos.php`; `copiarPerfilesModulo` en `empresas_modulos_model.php:193` copia los perfiles base a la empresa | Empresas 2 y 3 (`:205-206`) |
| 3 | `conf__tablas` + `conf__tablas_estados_registros` | `modules/conf/tablas.php`; `agregarEstadosTabla` en `tablas_model.php:172` copia los estados patrón del tipo | 23 tablas, todas tipo 1 (incluidas `ml__tareas` y `ml__tareas_ciclos`). Solo 7 tienen estados cargados (`:27807-27820`). |
| 4 | `conf__paginas` | `modules/conf/paginas.php` | Solo hay dos páginas contenedoras, 63 "Tablas" y 64 "Principal", ambas con `url='index.php'` (`:27010-27011`) |
| 5 | `conf__paginas_funciones` | `modules/conf/paginas_funciones.php`; `copiarFuncionesDeTipo` en `paginas_model.php:353` copia desde `conf__paginas_funciones_tipos` (`:27414-27429`, plantillas para los tipos 1 y 2) | Ninguna |
| 6 | `conf__perfiles` + `conf__perfiles_funciones` | `modules/conf/perfiles.php`, `perfiles_funciones.php` | **Ningún perfil con `modulo_id = 3`** |
| 7 | `conf__empresas_perfiles` + `conf__empresas_perfiles_funciones` | `modules/conf/empresas_perfiles.php`, `empresas_perfiles_funciones.php`; `heredarPaginasFuncionesDesdePerfilBase` en `empresas_perfiles_funciones_model.php:440` | Ninguno |
| 8 | `conf__usuarios_perfiles` | `modules/conf/perfiles_usuarios.php` (valida solapamiento en `perfiles_usuarios_model.php:356`) | — |

### Cómo se evalúa en tiempo de ejecución

- **Empresas y módulos visibles:** `usuario_obtener_empresas_modulos` (`core/permisos.php:130-186`). Exige un `conf__empresas_perfiles` del usuario con ese `modulo_id`, vigente según `CURDATE() BETWEEN fecha_inicio AND fecha_fin`.
- **Páginas permitidas:** `usuario_obtener_paginas_permitidas` (`core/permisos.php:68-104`). La única fuente es `conf__empresas_perfiles_funciones.asignado = 1`; `conf__perfiles_funciones` solo sirve de plantilla. Una página sin funciones queda **denegada**.
- **Superadmin:** `USUARIOS_ACCESO_TOTAL = [1]` (`core/permisos.php:11`) ve todo sin perfiles.
- **Menú:** `header1.php:443-508`. Arma el árbol desde `conf__paginas` filtrando por `$_GET['modulo_id']`. Ojo: en `:445` pisa el `$modudo_idx` que define la página. Los enlaces son relativos (`$row['url'] . '?pagina_id=…&empresa_id=…&modulo_id=…'`, `:508`), así que **las páginas del módulo deben vivir en `modules/ml/`** (la carpeta sale de `modulo_url='ml'`, `header1.php:148-152`).
- **Control de acceso a la página:** `header1.php:117-127`, con `$pagina_idx`.

### Archivos que se tocan en la práctica

El alta no se hace a mano desde las pantallas: se versiona un script en `_local/sql/mantenimiento/AAAAMMDD_<nombre>.sql`. Plantilla: `_local/sql/mantenimiento/20260925_productos_tipos_abm.sql`, que:

1. Hace una consulta de verificación con `MAX(tabla_id)`, `MAX(tabla_estado_registro_id)`, `MAX(pagina_id)` y `MAX(pagina_funcion_id)`, y usa **ids explícitos**.
2. Dentro de `START TRANSACTION … COMMIT`, inserta en `conf__tablas`, `conf__tablas_estados_registros`, `conf__paginas` y `conf__paginas_funciones`.
3. Deja los permisos (`conf__empresas_perfiles_funciones`) como paso manual, documentado en el script.

Por cada página: los 4 archivos (`x.php`, `x_ajax.php`, `x_model.php`, `x.js`) en `modules/ml/`, con `$modudo_idx = 3` y `$pagina_idx = <id de conf__paginas>`. No hace falta tocar `header1.php` ni `core/permisos.php`.

Para matriz legal falta además:

- Perfiles base del módulo 3 y su copia a las empresas 2 y 3.
- Estados nuevos en `conf__estados_registros`: ciclo y tarea.
- Estados patrón del tipo 3 (Proceso Largo), si se usa.

---

## 6. Estructura de referencia

```
modules/
  conf/                 ← ABM del motor (módulos, páginas, funciones, perfiles, tablas)
  gestion/              ← módulo 2, el de referencia
    depositos.php           vista (header1/footer1, $modudo_idx, $pagina_idx)
    depositos_ajax.php      dispatcher por 'accion'
    depositos_model.php     SQL + motor de botones (obtenerBotonesPorEstado, ejecutarTransicionEstado)
    depositos.js            jQuery + DataTables + SweetAlert2
    facturas_proveedores.*  proceso con estados y detalle
    arca_integraciones.*    la más nueva; ver nota de seguridad
  ml/                   ← hoy: copias de conf/gestion, sin código de matriz legal
    layout/  ajax/          legacy (AdminLTE 3), no usar
core/permisos.php       acceso a empresas, módulos y páginas
templates/adminlte/header1.php, footer1.php   layout activo
_local/sql/mantenimiento/      scripts de alta y cambio de esquema
_local/docs/matriz-legal/      esta documentación
```

Plantillas recomendadas:

- **Diccionario:** `gestion/depositos.*`. Su DDL (`:29239-29261`) es un buen modelo: `empresa_id smallint unsigned`, UNIQUE de negocio que incluye `empresa_id`, índice por estado y utf8mb4_unicode_ci.
- **Proceso:** `gestion/facturas_proveedores.*`.
- **Seguridad del AJAX:** `gestion/arca_integraciones_ajax.php:24-57`. Es el único ABM nuevo que valida la sesión y llama a `usuario_tiene_acceso_pagina` sobre la empresa. `depositos_ajax.php:28` y `depositos.js:2` toman la empresa por parámetro con **valor por defecto 2 fijo** y no validan la sesión. Ese patrón no se debe copiar en matriz legal: rompe el aislamiento entre empresas.

---

## Consultas de `05` que todavía hace falta correr

Casi todo se pudo responder con los volcados versionados en el repositorio. Los volcados son del 25 al 27/09, y después hubo scripts (`20260929`, `20260930`, `20261001`). Por eso conviene confirmar contra **la base donde se va a instalar el módulo**:

| Consulta de `05` | ¿Hace falta? | Motivo |
| --- | --- | --- |
| 1. Tablas `conf__` | Sí, rápida | El script `20261001` agrega `conf__integraciones*`; confirmar que no haya otras nuevas |
| 2. Columnas | No | Respondida arriba |
| 3. Tipo de `tabla_estado_registro_id` | No | Respondida arriba |
| 4. Columna de empresa | No | Respondida arriba |
| 5. `SELECT VERSION()` | **Sí** | Saber cuál de los dos motores (MySQL 8.0.46 o MariaDB 10.4.22) es el destino, y si hay más de un entorno |
| 6. Motor y charset | No | Respondida arriba |
| 7. Auditoría | No | Respondida arriba |

**Consulta adicional que no está en `05` y es necesaria**, para decidir qué hacer con el prototipo `ml__*`:

```sql
-- 8. Datos cargados en las tablas ml__ existentes
SELECT table_name, table_rows
FROM information_schema.tables
WHERE table_schema = DATABASE() AND table_name LIKE 'ml\_\_%'
ORDER BY table_name;
```

En el volcado de producción solo hay datos semilla en siete catálogos (`:88572` a `:88899`). Si la base viva tiene datos en `ml__normas`, `ml__obligaciones` o `ml__tareas`, hace falta un plan de migración.

---

## Decisiones pendientes antes del DBML v0.2

1. **Prototipo `ml__*` existente:** reemplazarlo (`DROP` y alta nueva), migrarlo o renombrar las tablas del DBML. Afecta las 10 colisiones de nombre y las filas 49 a 71 de `conf__tablas`.
2. **Semántica de `tabla_estado_registro_id`:** seguir guardando el id genérico de `conf__estados_registros`, que es lo que hace todo el código, o pasar a la PK de `conf__tablas_estados_registros`, como dice `CLAUDE.md`. Recomendación: seguir con el id genérico y corregir `CLAUDE.md`.
3. **Perfiles:** el DBML debe referenciar `conf__empresas_perfiles.empresa_perfil_id`, no `conf__perfiles.perfil_id`.
4. **Auditoría:** confirmar `creado_en`, `creado_por`, `actualizado_en` y `actualizado_por`.
5. **Tipos de las FK:** `empresa_id smallint unsigned`, `usuario_id int` y `empresa_perfil_id int unsigned`.
