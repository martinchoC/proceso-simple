# Diseño del módulo M0: instalación de la matriz legal

Fecha: 2026-10-02. Etapa de diseño: no se ejecutó SQL, no se modificó código, base ni archivos existentes. Este documento no es el script: fija el orden, las verificaciones, las decisiones y las preguntas que hay que cerrar antes de escribirlo.

Formato de las referencias: `archivo:línea`. Salvo indicación, los números de línea de volcado son de `casalucho_produccion.sql` (MySQL 8.0.46, base `ps_gestion_casalucho`) y, cuando se comparan, de `casalucho.sql` (MariaDB 10.4.22, base `gestion_multipyme`). `aecbb1.sql` es otro sistema y no se usó.

## Fuentes y cómo se leyeron

| Documento | Nota |
| --- | --- |
| `01-logica-del-proceso.md` | Fuente de verdad del proceso. Estados: `01:238` (ciclo) y `01:239` (tarea). Gobierno: `01:131-139`. Módulos: `01:145-181`. |
| `03-plan-de-modulos.md` | M0 en `03:69-87`. **No existe una sección titulada "Reglas técnicas"**: hay un párrafo "Reglas técnicas que salen del repositorio" (`03:37-43`) dentro de "Reglas que no se negocian" (`03:23-35`). Usé esas dos, más "Decisiones ya tomadas" (`03:17-21`) y "Cómo se agrega una página de un módulo" (`03:89-96`). |
| `04-modelo-de-datos.dbml` | Es la v0.2 (`04:2`). El nombre `04-modelo-de-datos-v0.2.dbml` que usan `03:12` y `07:3` **no existe** en el repositorio (ver Contradicciones, C1). 69 tablas `ml__` verificadas por script. |
| `06-hallazgos-del-proyecto.md` | Me apoyé en sus hallazgos; solo re-verifiqué lo que este diseño necesita y lo nuevo. |
| `07-clasificacion-de-tablas.md` | 53 Diccionario + 7 Proceso corto + 9 Proceso largo = 69, coinciden uno a uno con el DBML (verificado por script). |

Para no depender de lecturas a ojo, parseé los `INSERT` de los volcados con un script descartable (fuera del repositorio) y comparé `casalucho_produccion.sql` contra `casalucho.sql`.

---

## Resumen: lo que cambia o precisa el plan de M0

1. **El prototipo no tiene otras referencias en `conf__` más allá de las conocidas.** En los dos volcados: 23 filas en `conf__tablas` (49-71) y 14 en `conf__tablas_estados_registros` (83-96), nada más. Ni páginas, ni funciones, ni perfiles, ni permisos apuntan al prototipo. Las páginas 63 y 64 tienen `tabla_id = 0` y ninguna función. Detalle en la sección 2.
2. **"La base no tiene datos" no es literal: el prototipo tiene 24 filas semilla en 7 tablas** (`:88572-88899`). El criterio de parada tiene que ser "ninguna fila fuera de esas 24" y medirse con `COUNT(*)`: la consulta 8 de `06` usa `information_schema.tables.table_rows`, que en InnoDB es una estimación (`06:296-302`; el propio volcado dice "~N rows (aproximadamente)", `:88570`).
3. **Hacen falta 12 estados genéricos nuevos, no 11**, si se respetan los estados de las otras 7 tablas de proceso largo de `07:21-29`. Los 15 nombres de ciclo y tarea dan 11 (comparten 4), pero "Con error" (recálculos, alertas, cargas) no tiene equivalente. La alternativa de quedarse en 11 existe y se explica en la sección 3.
4. **La marca de estado final tiene que ir en `conf__tablas_estados_registros` (`es_final`), no en el estado genérico.** El mismo estado genérico es final en una tabla y no en otra: "Rechazada" no es final en la tarea (`01:239`, "devuelta por el revisor") y sí lo es en excepciones y cambios masivos. "Vencido" no es final en el ciclo (`01:238`, "recuperable") y sí lo es en la excepción.
5. **Los `valor_estandar` nuevos tienen que ser mayores que 1.** Unas 25 funciones `obtenerEstadoInicial` de `modules/gestion/` toman como estado inicial el de menor `valor_estandar` de todo el catálogo (por ejemplo `depositos_model.php:109`, `clientes_model.php:143-147`). Hoy es Activo (1); un estado nuevo con valor 0 o 1 rompería todas las altas de gestión.
6. **El código de gestión ya espera un `codigo_estandar = 'CANCELADO'`** (`ventas_remitos_model.php:650` y `:1359`). El "Cancelado" genérico nuevo debe usar exactamente ese código, sabiendo que deja de ser exclusivo de la matriz.
7. **43 de las 53 tablas "Diccionario" del `07` no tienen columna de estado en el DBML**, y dos de las de "Proceso corto" tampoco (C2 y C3). Hay que decidirlo antes de escribir el DDL.
8. **Los ids no se pueden tomar de los volcados.** Producción tiene la página 96 apuntando a `tabla_id = 108` (`:27041`), pero `conf__tablas` termina en 107 (`:27737`): el script `20260925_productos_tipos_abm.sql` quedó aplicado a medias allá. Local, según `20261001_arca_integraciones.sql:22-24`, ya va por tabla 109, estado de tabla 221, página 99 y función 447. Propongo bloques de ids reservados para el módulo 3 (sección 6).
9. **`modules/ml/` es una copia congelada de diciembre de 2025** (un solo commit, `e0f95fa`, 2025-12-29), y **30 de sus 31 `*_ajax.php` no validan sesión**. Por ejemplo, `ml/usuarios_ajax.php:10-13` con `ml/usuarios_model.php:2-5` devuelve `SELECT * FROM conf__usuarios` (con hashes de contraseña) sin login, si existe `ml/conexion.php`. Ese archivo está en la copia local (sin versionar). Es un riesgo de seguridad que existe hoy, independiente de M0. Lo mismo vale para las copias idénticas de `gestion/` y `conf/` (sección 5).
10. **`layout_nombre` no tiene efecto funcional.** Solo lo lee `core/plantilla.php:14` y `:65` (layout viejo), con una consulta `WHERE id = ...` sobre una columna que no existe. Ponerlo en `'default'` es cosmético.
11. **`copiarPerfilesModulo` no propaga funciones que se asignen después.** Saltea los perfiles ya copiados (`empresas_modulos_model.php:203-209`). Si los perfiles se copian en M0, cada script de M1 a M8 tiene que dar de alta los permisos en `conf__empresas_perfiles_funciones` de las empresas 2 y 3, no solo en `conf__perfiles_funciones`. Además, `heredarPaginasFuncionesDesdePerfilBase` asigna **todas** las funciones del módulo, sin mirar el perfil base (`empresas_perfiles_funciones_model.php:460-466`).

---

## 1. Orden de ejecución

### Principio

El DDL hace commit implícito en los dos motores. El propio repositorio lo dice en `20260927_sync_local_a_produccion.sql:25-26`. `START TRANSACTION … COMMIT` protege solo los `INSERT`, `UPDATE` y `DELETE`. Por eso:

- Cada bloque de DML va en su transacción, con verificación antes y conteo después.
- Cada bloque de DDL es **reanudable**: si se corta a mitad, se vuelve a correr sin romper nada (`DROP TABLE IF EXISTS`, `CREATE TABLE IF NOT EXISTS` con verificación previa de que no existe nada con ese nombre, y FKs, columnas y CHECK agregados con un helper que consulta `information_schema`, como `_sync_add_column` y `_sync_add_fk` de `20260927_sync_local_a_produccion.sql:36-65`).
- Las paradas se fuerzan con un helper `_m0_assert(condición, mensaje)` que hace `SIGNAL SQLSTATE '45000'`; funciona igual en los dos motores. Con el cliente `mysql` en modo lote, el primer error aborta el script y, al cerrarse la conexión, se descarta la transacción abierta. En HeidiSQL eso depende de la opción "detener ante errores" y la sesión queda abierta: hay que hacer `ROLLBACK` a mano. Por eso recomiendo correrlo con `mysql` (pregunta 16).

### Secuencia

| Orden | Paso de `03` | Qué se hace | ¿Destructivo? | Verificación previa y criterio de parada |
| --- | --- | --- | --- | --- |
| E0 | — | Ventana de mantenimiento, volcado fresco de la base destino y prueba completa en los dos motores (sección 6) | No | — |
| E1 | 1 | Script `00_verificacion` (solo `SELECT`) | No | Las consultas V1 a V6 de abajo |
| E2 | 2 (respaldo) | `mysqldump`: completo, prototipo y filas `conf__` afectadas | No | Que el respaldo tenga 23 `CREATE TABLE` `ml__` y 24 filas semilla, y que el de `conf__` tenga 23 + 14 filas |
| E3 | 2 (baja `conf__`) | `DELETE` de `conf__tablas_estados_registros` y `conf__tablas` del prototipo, en transacción | **Sí** | Repetir V2 y V4 en la misma sesión; `ROW_COUNT()` = 14 y 23 |
| E4 | 2 (baja física) | `DROP` de las 23 tablas, en orden de FK | **Sí, irreversible** | Repetir V1 y V3 justo antes; después, 0 tablas del prototipo |
| E5 | 3 | `CREATE` de las 69 tablas (fase A), FKs (fase B) y verificación del DDL | No (aditivo) | V5: ninguna de las 69 existe |
| E6 | 4 | `ALTER` para agregar `es_final` (si se aprueba) y alta de los estados genéricos | Aditivo sobre una tabla `conf__` compartida | V6: ids y códigos libres |
| E7 | 5 | Alta de las 69 tablas en `conf__tablas` y de sus estados en `conf__tablas_estados_registros` | No | V6: bloque de ids libre |
| E8 | 6 | Perfiles base del módulo 3 y su copia a las empresas 2 y 3 | No | V6 |
| E9 | 7 | `UPDATE conf__modulos SET layout_nombre = 'default'` (opcional) | Mínimo | `layout_nombre = ''` y `modulo_id = 3` en el `WHERE` |
| E10 | — | Script `05_verificacion_final` y prueba funcional | No | Sección 6 |
| E11 | 8 | Limpieza de `modules/ml/`, en un commit aparte y con confirmación | Sí (archivos) | Sección 5 |
| E12 | 9 | Corrección de `CLAUDE.md` | No | Texto propuesto abajo |

El orden E3 antes que E4 es deliberado. La baja en `conf__` es transaccional y verificable. Si después el `DROP` falla a mitad, se vuelve a correr (`IF EXISTS`). Tanto las filas `conf__` huérfanas como las tablas sin fila en `conf__` son inocuas para el código actual, porque ningún archivo menciona `ml__` (verificado con Grep sobre `*.php`, `*.js` y `*.sql` fuera de los volcados).

### Verificaciones previas (script `00`, solo lectura)

**V0. Entorno.** Se registra la salida; no detiene nada, pero alimenta el diseño del DDL (sección 7).

```sql
SELECT VERSION(), DATABASE(), @@GLOBAL.sql_mode, @@SESSION.sql_mode,
       @@explicit_defaults_for_timestamp, @@character_set_server, @@collation_server,
       @@time_zone, @@system_time_zone, @@innodb_default_row_format,
       @@lower_case_table_names, @@foreign_key_checks, @@log_bin, @@log_bin_trust_function_creators;
```

**V1. Inventario exacto del prototipo.** Para: si no son exactamente las 23 tablas esperadas o si alguna no es InnoDB.

```sql
SELECT table_name, engine, table_collation
FROM information_schema.tables
WHERE table_schema = DATABASE() AND table_name LIKE 'ml\_\_%'
ORDER BY table_name;
```

**V2. Datos reales (no `table_rows`).** Una fila por tabla, con lo esperado al lado. Para si alguna fila no coincide.

```sql
SELECT 'ml__ambitos_organizacionales' t, COUNT(*) n, 1 esperado FROM ml__ambitos_organizacionales
UNION ALL SELECT 'ml__ambitos_tipos', COUNT(*), 3 FROM ml__ambitos_tipos
UNION ALL SELECT 'ml__entidades_tipos', COUNT(*), 6 FROM ml__entidades_tipos
UNION ALL SELECT 'ml__jurisdicciones', COUNT(*), 1 FROM ml__jurisdicciones
UNION ALL SELECT 'ml__jurisdicciones_tipos', COUNT(*), 3 FROM ml__jurisdicciones_tipos
UNION ALL SELECT 'ml__normas_tipos', COUNT(*), 4 FROM ml__normas_tipos
UNION ALL SELECT 'ml__raci_tipos', COUNT(*), 4 FROM ml__raci_tipos
UNION ALL SELECT 'ml__normas', COUNT(*), 0 FROM ml__normas
-- ... las 15 restantes con esperado 0
;
```

Los esperados salen de `:88572` (1), `:88587` (3), `:88629` (6), `:88655` (1), `:88669` (3), `:88744` (4) y `:88899` (4). Total: 24.

**V3. Dependencias fuera del prototipo.** Para si hay una FK que cruza el límite `ml__`, o si algún trigger, vista, rutina o evento menciona `ml__`.

```sql
SELECT constraint_name, table_name, referenced_table_name
FROM information_schema.referential_constraints
WHERE constraint_schema = DATABASE()
  AND (table_name LIKE 'ml\_\_%' OR referenced_table_name LIKE 'ml\_\_%');
-- esperado: 35 filas, todas con table_name Y referenced_table_name ml__

SELECT 'trigger', trigger_name FROM information_schema.triggers
 WHERE trigger_schema = DATABASE() AND (event_object_table LIKE 'ml\_\_%' OR action_statement LIKE '%ml\_\_%')
UNION ALL SELECT 'view', table_name FROM information_schema.views
 WHERE table_schema = DATABASE() AND view_definition LIKE '%ml\_\_%'
UNION ALL SELECT 'routine', routine_name FROM information_schema.routines
 WHERE routine_schema = DATABASE() AND routine_definition LIKE '%ml\_\_%'
UNION ALL SELECT 'event', event_name FROM information_schema.events
 WHERE event_schema = DATABASE() AND event_definition LIKE '%ml\_\_%';
-- esperado: 0 filas
```

`ROUTINE_DEFINITION` sale `NULL` si el usuario no es el definer ni tiene privilegios: en ese caso el 0 no prueba nada (conocimiento general del motor, no verificado en el repositorio). En producción hay rutinas, triggers y un evento (`:89225`, `:89403`, `:89394`), pero ninguno toca `ml__`.

**V4. Referencias en `conf__`.** Para si algún valor difiere del esperado.

```sql
SELECT 'tablas 49-71 del prototipo' c, COUNT(*) n, 23 esperado FROM conf__tablas
 WHERE tabla_id BETWEEN 49 AND 71 AND modulo_id = 3 AND tabla_nombre LIKE 'ml\_\_%'
UNION ALL SELECT 'otras tablas del modulo 3', COUNT(*), 0 FROM conf__tablas
 WHERE (modulo_id = 3 OR tabla_nombre LIKE 'ml\_\_%') AND tabla_id NOT BETWEEN 49 AND 71
UNION ALL SELECT 'estados de tabla del prototipo', COUNT(*), 14 FROM conf__tablas_estados_registros
 WHERE tabla_id BETWEEN 49 AND 71
UNION ALL SELECT 'paginas con tabla del prototipo', COUNT(*), 0 FROM conf__paginas WHERE tabla_id BETWEEN 49 AND 71
UNION ALL SELECT 'paginas del modulo 3', COUNT(*), 2 FROM conf__paginas WHERE modulo_id = 3
UNION ALL SELECT 'hijas de 63/64', COUNT(*), 0 FROM conf__paginas WHERE padre_id IN (63, 64)
UNION ALL SELECT 'funciones del modulo 3', COUNT(*), 0 FROM conf__paginas_funciones pf
 JOIN conf__paginas p ON p.pagina_id = pf.pagina_id WHERE p.modulo_id = 3
UNION ALL SELECT 'perfiles del modulo 3', COUNT(*), 0 FROM conf__perfiles WHERE modulo_id = 3
UNION ALL SELECT 'perfiles de empresa del modulo 3', COUNT(*), 0 FROM conf__empresas_perfiles WHERE modulo_id = 3
UNION ALL SELECT 'subgrupos conf', COUNT(*), 0 FROM conf__tablas_comprobantes_subgrupos WHERE tabla_id BETWEEN 49 AND 71
UNION ALL SELECT 'subgrupos gestion', COUNT(*), 0 FROM gestion__comprobantes_subgrupos WHERE tabla_id BETWEEN 49 AND 71;
```

**V5. Nombres libres para las 69 tablas nuevas.** Se corre después de E4. Para si existe alguna tabla `ml__`.

**V6. Ids y códigos libres.** Para si algún id del bloque está ocupado o si ya existe alguno de los códigos.

```sql
SELECT (SELECT COUNT(*) FROM conf__estados_registros WHERE estado_registro_id BETWEEN 16 AND 27) ocupados_estados,
       (SELECT COUNT(*) FROM conf__estados_registros WHERE codigo_estandar IN
          ('EN_ESPERA','PENDIENTE','EN_CURSO','EN_REVISION','VENCIDO','CUMPLIDO',
           'CUMPLIDO_FUERA_PLAZO','INCUMPLIDO','EXIMIDO','RECHAZADO','CANCELADO','CON_ERROR')) codigos_tomados,
       (SELECT COUNT(*) FROM conf__tablas WHERE tabla_id BETWEEN 201 AND 269) ocupados_tablas,
       (SELECT COUNT(*) FROM conf__tablas_estados_registros WHERE tabla_estado_registro_id BETWEEN 2001 AND 2200) ocupados_estados_tabla,
       (SELECT COUNT(*) FROM conf__perfiles WHERE perfil_id BETWEEN 301 AND 320) ocupados_perfiles,
       (SELECT COUNT(*) FROM conf__empresas_perfiles WHERE empresa_perfil_id BETWEEN 301 AND 340) ocupados_perfiles_empresa,
       (SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema = DATABASE()
          AND table_name = 'conf__tablas_estados_registros' AND column_name = 'es_final') ya_tiene_es_final;
-- esperado: 0, 0, 0, 0, 0, 0, NULL
```

Los bloques son la propuesta de la sección 6; si no se aprueban, se reemplazan por el máximo más uno, como en la plantilla `20260925_productos_tipos_abm.sql:22-27`.

### Respaldo (E2)

Se corre con el `mysqldump` del mismo motor que el servidor: el de MySQL 8 para producción y el de MariaDB para local. Mezclarlos da errores (por ejemplo, `--column-statistics=0` hace falta cuando el cliente MySQL 8 vuelca un MariaDB). Esto es conocimiento general, no verificado en el repositorio.

```
# 1. Base completa (red de seguridad; el volcado de producción pesa unos 116 MB)
mysqldump --single-transaction --no-tablespaces --routines --triggers --events \
  --default-character-set=utf8mb4 [--set-gtid-purged=OFF  # solo cliente MySQL]  BASE > pre_m0_completo.sql

# 2. Las 23 tablas del prototipo (estructura y datos)
mysqldump --single-transaction --no-tablespaces --default-character-set=utf8mb4 BASE \
  ml__ambitos_organizacionales ml__ambitos_tipos ... ml__tareas_evidencias > pre_m0_ml_prototipo.sql

# 3. Solo las filas conf__ que se borran, para restaurarlas sin pisar cambios de otros
mysqldump --single-transaction --no-tablespaces --no-create-info --skip-triggers \
  --where="tabla_id BETWEEN 49 AND 71" BASE conf__tablas conf__tablas_estados_registros > pre_m0_conf_prototipo.sql

# 4. Las tablas conf__ que M0 modifica, completas (para comparar antes y después)
mysqldump --single-transaction --no-tablespaces BASE conf__estados_registros conf__tablas \
  conf__tablas_estados_registros conf__perfiles conf__empresas_perfiles conf__modulos > pre_m0_conf_completo.sql
```

Para verificar el respaldo: `grep -c "CREATE TABLE"` da 23 en el archivo 2; contar las filas de cada `INSERT` (24 en el 2; 23 + 14 en el 3); guardar `CHECKSUM TABLE` de las tablas del archivo 4 para comparar después del rollback de prueba.

### Orden de los `DROP` (E4)

Lo calculé a partir de las 35 FK (`:88566-88999`; en `casalucho.sql`, `:70331-70742`, con las mismas FK). Se borra primero lo que nadie referencia. Las autorreferencias (`ml__ambitos_organizacionales`, `ml__entidades`, `ml__jurisdicciones`) no traban el `DROP`.

| Nivel | Tablas |
| --- | --- |
| 1 | `ml__tareas_dependencias`, `ml__tareas_evidencias`, `ml__tareas_ciclos`, `ml__normas_ambitos_aplicacion`, `ml__normas_procedimientos`, `ml__obligaciones_jurisdicciones`, `ml__obligaciones_raci`, `ml__obligaciones_tipos_entidades`, `ml__procedimientos_instrucciones`, `ml__procedimientos_obligaciones` |
| 2 | `ml__tareas`, `ml__roles_organizacionales`, `ml__raci_tipos` |
| 3 | `ml__obligaciones`, `ml__procedimientos`, `ml__entidades` |
| 4 | `ml__normas`, `ml__jurisdicciones`, `ml__entidades_tipos` |
| 5 | `ml__normas_tipos`, `ml__jurisdicciones_tipos`, `ml__ambitos_organizacionales` |
| 6 | `ml__ambitos_tipos` |

Un `DROP TABLE IF EXISTS` por tabla, con `FOREIGN_KEY_CHECKS = 1`. **No recomiendo `SET FOREIGN_KEY_CHECKS = 0`**, por tres razones:

- Con los controles activos, el propio motor frena si en la base viva hay una FK externa que los volcados no muestran.
- Con los controles desactivados, MySQL deja borrar una tabla referenciada desde afuera y quedan definiciones de FK colgadas.
- El orden ya está calculado y es corto.

Un `DROP` por sentencia, y no uno múltiple, porque MySQL 8 tiene DDL atómico y MariaDB 10.4 no (conocimiento general). Así el comportamiento ante un corte es el mismo en los dos motores y se reanuda tabla por tabla.

---

## 2. Filas a dar de baja y referencias reales

### Lo que sale de los volcados

| Tabla `conf__` | Producción | `casalucho.sql` | Acción |
| --- | --- | --- | --- |
| `conf__tablas` ids 49 a 71, todas `modulo_id = 3`, tipo 1 | `:27679-27701` | `:24513-24535` | Borrar (23) |
| `conf__tablas_estados_registros` 83 a 96 (tablas 49, 50, 51, 52, 53, 55 y 66, Activo e Inactivo) | `:27807-27820` | `:24640-24653` | Borrar (14). **No hay más**: busqué todas las filas con `tabla_id` entre 49 y 71 |
| `conf__paginas` 63 "Tablas" y 64 "Principal", `tabla_id = 0`, `url = 'index.php'`, sin hijas | `:27010-27011` | `:23867-23868` | Reutilizar (ver abajo) |
| `conf__paginas_funciones` de las páginas 63 y 64 | 0 filas | 0 filas | — |
| `conf__perfiles_funciones` y `conf__empresas_perfiles_funciones` sobre esas funciones | 0 | 0 | — |
| `conf__perfiles` y `conf__empresas_perfiles` del módulo 3 | 0 (`:27471-27474`, `:224-235`) | 0 (`:24309-24312`, `:217-228`) | — |
| `conf__empresas_modulos` del módulo 3 (empresas 2 y 3) | `:205-206` | `:199-200` | Se conservan |
| `conf__modulos` id 3 | `:26932` | `:23790` | Solo `layout_nombre` (E9) |
| `conf__tablas_comprobantes_subgrupos`, `gestion__comprobantes_subgrupos`, `xxx_conf__tablas_estados` (tienen `tabla_id`) | Sin filas con `tabla_id` 49-71 | Igual | — |

**Falso positivo descartado:** `accesos_pagina` tiene filas con `pagina_id` 63 y 64 (`:58-59`), pero es una tabla vieja con otra numeración: "Tipos de Valores" y "Plan de Cuentas". No tiene relación con `conf__paginas` y no se toca.

**En el código** no hay ninguna referencia: Grep de `ml__` en `*.php`, `*.js` y `*.sql` fuera de los volcados devuelve 0. `$pagina_idx = 63` o `64`, `$modudo_idx = 3` y `modulo_id = 3` tampoco aparecen.

### Diferencias entre volcados que importan

- Las filas del prototipo son idénticas en los dos volcados. Las semillas también (comparadas por diff). El DDL difiere solo en formato: `CHARACTER SET` explícito por columna en MySQL y anchos de entero en MariaDB.
- **Producción está desfasada respecto de los scripts.** Tiene las páginas 96 a 98 y las funciones 433 a 442 (`:27041-27043`, `:27366-27375`), pero no tiene `conf__tablas` 108 (última fila, `:27737`) ni los estados 218 y 219: la página 96 apunta a una tabla que no está registrada. Local (`casalucho.sql`) llega solo hasta la página 95 y la función 432.
- `conf__empresas_perfiles` 5, 6 y 7 tienen estado 0 en producción (`:228-230`) y 1 en local (`:221-223`); los nombres de 9 y 12 también difieren. No afecta a M0, pero confirma que los entornos ya divergen.
- `casalucho_local.sql` y `casalucho_desarrollo.sql` son solo estructura (no tienen `INSERT` en `conf__`), y `casalucho_desarrollo.sql` trae el esquema dos veces (las `ml__` empiezan en `:2771` y otra vez en `:6053`; 348 `CREATE TABLE`). No sirven para verificar filas.

### Lo que no se puede saber sin la base viva

- Si alguien cargó datos en `ml__` después del 27/09 (V2).
- Si hubo altas en `conf__` posteriores a los volcados que toquen el módulo 3: perfiles creados desde la pantalla, funciones de las páginas 63 y 64 (V4).
- Los máximos reales de cada id y si los scripts del 25/09 al 01/10 están aplicados en cada entorno (V6 y la consulta 0 de cada script).
- FKs, triggers o rutinas creados a mano después de los volcados (V3).
- `sql_mode`, `explicit_defaults_for_timestamp`, `lower_case_table_names` y privilegios reales (V0).

### Páginas 63 y 64: propuesta

**Reutilizarlas sin cambios en M0.** Nada las referencia y cumplen el rol que el módulo necesita:

- 64 "Principal" (`index.php`, orden 10) es la portada del módulo. El selector de empresa del header lleva a `modules/<modulo_url>` (`header1.php:148-152`), o sea a `modules/ml/index.php`, que existe.
- 63 "Tablas" (orden 900) queda como contenedor de las páginas de catálogo de M1 y M2.

Hoy solo las ve el superadmin, porque las páginas sin funciones quedan denegadas (`core/permisos.php:68-104`). Borrarlas es trivial y no rompe nada, pero no gana nada. En M1 se ajusta el `$modudo_idx` de `ml/index.php`, que hoy es 2.

---

## 3. Estados genéricos nuevos y marca de estado final

### Cuenta: de 15 nombres a 11, y por qué hace falta un 12.º

- Ciclo, 8 estados (`01:238`): pendiente, en curso, cumplido, cumplido fuera de plazo, vencido, incumplido, eximido y cancelado.
- Tarea, 7 estados (`01:239`): inactiva, pendiente, en curso, en revisión, cumplida, rechazada y cancelada.
- Son 15 nombres. **Cuatro se comparten** entre ciclo y tarea con el mismo genérico: pendiente, en curso, cumplido/cumplida y cancelado/cancelada. Quedan **11**. Los nombres propios ("Inactiva", "Cumplida", "Rechazada", "Cancelada") van en `conf__tablas_estados_registros.tabla_estado_registro`, como propone `07:51-63`.
- **Reutilizar estados existentes no reduce la cuenta del ciclo y la tarea**: ninguno de los 14 actuales (`:713-726`) equivale a esos estados. "En revisión" podría ir sobre "Pendiente de Aprobación" (4), pero entonces el revisor de una tarea y el aprobador de un documento compartirían el estado. Lo descarto para el ciclo y la tarea, y lo uso para las tablas con aprobación formal (excepciones, cambios masivos, cargas).
- **Las otras 7 tablas de proceso largo** (`07:21-29`) se cubren con los existentes (3 Borrador, 4 Pendiente de Aprobación, 5 Confirmado) y los 11 nuevos, salvo **"Con error"**: lo usan recálculos, alertas y cargas ("Validada con errores"), y no tiene equivalente. Mapearlo a "Rechazado" permite quedarse en 11, pero mezcla "lo rechazó una persona" con "falló el proceso". Por eso **recomiendo 12** (pregunta 3).

### Estados propuestos para `conf__estados_registros`

Columnas reales: `:700-708`. Los ids 1 a 13 y 15 están ocupados; el **14 queda como hueco**: no lo reutilizo, porque no sé qué datos viejos pudieron guardarlo. En los dos volcados ninguna fila de `conf__tablas_estados_registros` ni de `conf__paginas_funciones` usa el 14, pero no puedo revisar todas las tablas de negocio de la base viva. Los `valor_estandar` y `orden_estandar` van de 200 en adelante: son mayores que 1, para no cambiar el estado inicial que calculan las funciones de gestión (resumen, punto 5), y no chocan con los actuales (1 a 100).

| id | `estado_registro` | `codigo_estandar` | `valor_estandar` / `orden_estandar` | `color_id` sugerido |
| --- | --- | --- | --- | --- |
| 16 | En espera | `EN_ESPERA` | 200 | 2 (secundario) |
| 17 | Pendiente | `PENDIENTE` | 210 | 6 (info) |
| 18 | En curso | `EN_CURSO` | 220 | 1 (primario) |
| 19 | En revisión | `EN_REVISION` | 230 | 5 (advertencia) |
| 20 | Vencido | `VENCIDO` | 240 | 4 (peligro) |
| 21 | Cumplido | `CUMPLIDO` | 250 | 3 (éxito) |
| 22 | Cumplido fuera de plazo | `CUMPLIDO_FUERA_PLAZO` | 260 | 5 |
| 23 | Incumplido | `INCUMPLIDO` | 270 | 4 |
| 24 | Eximido | `EXIMIDO` | 280 | 2 |
| 25 | Rechazado | `RECHAZADO` | 290 | 4 |
| 26 | Cancelado | `CANCELADO` | 300 | 8 (oscuro) |
| 27 | Con error (si se aprueba) | `CON_ERROR` | 310 | 4 |

Los colores salen de `conf__colores` (`:137-146`). Sobre `CANCELADO`: gestión ya lo reconoce (`ventas_remitos_model.php:650` y `:1359`). Si una página de gestión llega a usar el estado 26, se activa esa lógica. Es deseable, pero deja de ser un estado exclusivo de la matriz.

### Matriz: tablas de proceso largo y estados

Notación: id genérico, nombre propio en la tabla, **I** = inicial (`es_inicial`) y **F** = final (`es_final`, si se aprueba).

| Tabla | Estados |
| --- | --- |
| `ml__ciclos` | 17 Pendiente **I** · 18 En curso · 20 Vencido · 21 Cumplido **F** · 22 Cumplido fuera de plazo **F** · 23 Incumplido **F** · 24 Eximido **F** · 26 Cancelado **F** |
| `ml__ciclos_tareas` | 16 Inactiva **I** · 17 Pendiente · 18 En curso · 19 En revisión · 21 Cumplida **F** · 25 Rechazada (vuelve a En curso) · 26 Cancelada **F** |
| `ml__normas_conflictos` | 17 Abierto **I** · 19 Escalado al área legal · 5 Resuelto **F** |
| `ml__excepciones` | 4 Solicitada **I** · 19 En aprobación legal · 5 Aprobada · 25 Rechazada **F** · 20 Vencida **F** |
| `ml__recalculos_pendientes` | 17 Pendiente **I** · 21 Procesado **F** · 27 Con error (reintento) |
| `ml__alertas` | 17 Programada **I** · 21 Enviada **F** · 27 Con error · 26 Cancelada **F** |
| `ml__incidencias` | 17 Abierta **I** · 18 En seguimiento · 21 Cerrada **F** |
| `ml__cargas` | 3 Cargada **I** · 27 Validada con errores · 4 Validada · 5 Confirmada **F** |
| `ml__cambios_masivos` | 3 Borrador **I** · 4 Pendiente de aprobación · 5 Aprobado · 21 Aplicado **F** · 25 Rechazado **F** |
| Proceso corto (7 tablas, `07:31-41`) | 3 Borrador **I** · 4 Pendiente de Aprobación · 5 Confirmado · 6 Eliminado **F** |

Son 42 filas de estados para las tablas de proceso largo y 28 para las de proceso corto (si las 7 tienen la columna, ver C3). Para las de Diccionario son 2 por tabla, entre 20 y 106 filas según la pregunta 5.

Criterio de mapeo: **5 Confirmado** es "se tomó una decisión" (resuelto, aprobado, confirmado) y **21 Cumplido** es "se ejecutó" (procesado, enviado, cerrado, aplicado). La regla de oro se respeta: en cada tabla, cada estado usa un genérico distinto, porque la columna guarda el genérico (decisión 2).

Sobre la tarea: `es_inicial` admite uno solo por tabla. Marco "Inactiva", porque toda tarea nace dentro de un ciclo y el motor activa enseguida las que no tienen predecesoras. Si la revisión es opcional (`01:425`), la transición es 18 → 21 sin pasar por 19, y eso no cambia el catálogo.

`conf__tablas_tipos_estados` del tipo 3: **propongo no cargar patrones.** Cada tabla de proceso largo tiene estados propios. `agregarEstadosTabla` (`tablas_model.php:172-182`) devuelve "No hay estados patrón" para el tipo 3, lo que obliga a cargar los estados por script, que es lo que se hace igual.

### Dónde guardar la marca de estado final

| Opción | A favor | En contra | Impacto en el código existente |
| --- | --- | --- | --- |
| **A. `es_final TINYINT(1) NOT NULL DEFAULT 0` en `conf__tablas_estados_registros`** | Es simétrica con `es_inicial`, que ya vive ahí (`:27767`). Es por tabla, que es lo que exige la matriz: Rechazado es final en excepciones y no en tareas; Vencido es final en excepciones y no en ciclos. | Toca una tabla `conf__` compartida con gestión. La pantalla `conf/tablas_estados.php` no la muestra hasta que se la agregue. | Los `SELECT ter.*` o `SELECT *` (`tablas_model.php:287`, `conf/tablas_estados_model.php:134`, `gestion/tablas_estados_model.php:7` y `:124`) devuelven una clave más en el array asociativo, sin efecto. Todos los `INSERT` sobre la tabla tienen lista de columnas (`tablas_model.php:238-243`, `conf/tablas_estados_model.php:85-88`, `gestion/tablas_estados_model.php:69`), así que toman el `DEFAULT 0`. Las filas de gestión quedan en 0, igual que hoy. |
| B. `es_final` en `conf__estados_registros` | Una sola marca por estado. | **Incorrecta para este dominio**: el mismo genérico es final en una tabla y no en otra (ver la matriz). | `SELECT *` en `conf/paginas_funciones_model.php:68`, `conf/tablas_estados_model.php:18`, `conf/tablas_tipos_model.php:91` y `conf/estados_registros_model.php:90`: inocuo. Las comprobaciones defensivas con `SHOW COLUMNS` (por ejemplo `depositos_model.php:28`) buscan `estado_registro`, `nombre_estado` o `descripcion`, así que no las afecta. |
| C. Tabla propia `ml__estados_finales (tabla_id, estado_registro_id)` | No toca `conf__`. | Duplica la configuración del motor en dos lugares, suma una tabla número 70 y obliga a hacer joins en cada consulta de tablero. | Ninguno. |
| D. Convención por `valor_estandar` (por ejemplo, 250 o más = final) | No hay DDL. | Es genérica, así que tiene el mismo defecto que B. Además, `valor_estandar` ya tiene otro uso implícito: el mínimo es el estado inicial de gestión. Es frágil. | Ninguno directo, pero queda una regla que no está escrita en ningún lado. |

**Recomendación: A.** Se agrega con un helper idempotente (consulta a `information_schema.columns`, como `_sync_add_column`) y `AFTER es_inicial`. En M6, la pantalla `conf/tablas_estados.php` suma el checkbox.

---

## 4. Perfiles base del módulo 3

### Cómo funciona hoy

- `conf__perfiles` (`:27460-27466`) tiene `perfil_id`, `modulo_id`, `perfil_nombre` (utf8mb3_spanish2_ci) y estado. **No tiene `empresa_id`**: el perfil base es por módulo, no "de la empresa titular" (ver C7).
- `copiarPerfilesModulo` (`empresas_modulos_model.php:193-260`, llamado desde `empresas_modulos_ajax.php:101`) inserta en `conf__empresas_perfiles` (`empresa_id`, `modulo_id`, `perfil_id_base`, `empresa_perfil_nombre` = `perfil_nombre`, estado 1) cada perfil activo del módulo que la empresa todavía no tenga (`NOT EXISTS`). Después copia sus `conf__perfiles_funciones` con `asignado = 1`.
- Para ver el módulo hace falta un `conf__empresas_perfiles` con `modulo_id = 3` asignado al usuario en `conf__usuarios_perfiles` vigente (`core/permisos.php:130-186`). Para ver una página, `conf__empresas_perfiles_funciones.asignado = 1` (`core/permisos.php:68-104`).

### Roles que salen de `01`

`01:131-139` y `01:149-175` dicen que el permiso es **qué puede hacer** (perfil) más **sobre qué ámbito** (`ml__usuarios_ambitos`). Entonces el responsable regional y el responsable legal de país hacen lo mismo con distinto ámbito: no necesitan perfiles distintos. Propongo 8 perfiles base, ids del bloque 301 en adelante (sección 6):

| id | `perfil_nombre` | Qué hace | Fuente |
| --- | --- | --- | --- |
| 301 | Administrador de matriz | Territorio, grupo, regiones, catálogos, configuración del grupo, perfiles y ámbitos | `01:133`, `01:151`, `01:436` |
| 302 | Gestor normativo | Carga, edita y publica normas, versiones y obligaciones en su ámbito (mundial, regional, de país o local); aprueba las excepciones a políticas | `01:133-135`, `01:152-153`, `01:180` |
| 303 | Área legal | Resuelve conflictos entre normas y aprueba excepciones a normas legales con fundamento | `01:49`, `01:180`, `01:226` |
| 304 | Responsable de empresa | Entidades, normas internas, obligaciones propias, procedimientos, pedido de excepciones y tablero de la empresa | `01:136`, `01:158-165` |
| 305 | Responsable de tareas | Ejecuta tareas y adjunta evidencias (R del RACI); registra eventos | `01:83`, `01:137` |
| 306 | Revisor y aprobador | Aprueba o rechaza tareas y cierra ciclos (A del RACI) | `01:83`, `01:412` |
| 307 | Auditor | Solo lectura; acceso de auditoría a lo confidencial, que queda registrado | `01:218`, `01:231` |
| 308 | Consulta | Solo lectura sin acceso de auditoría (C e I del RACI, tableros) | `01:95`, `01:141` |

### Copia a las empresas 2 y 3

Se replica la semántica de `copiarPerfilesModulo` en SQL, con ids explícitos (bloque 301 a 316): `INSERT … SELECT … WHERE NOT EXISTS (empresa_id, modulo_id = 3, perfil_id_base)`, `empresa_perfil_nombre = perfil_nombre` y estado 1. Son 8 por empresa, 16 en total. No se copian funciones, porque todavía no hay páginas.

Hay dos cosas para decidir (pregunta 9):

- **¿Todos los perfiles van a las dos empresas?** Los de matriz (301, 302, 303 y 307) tienen sentido en la empresa titular del grupo. En una empresa que solo ejecuta sobran, aunque no dan acceso a nada hasta que tengan funciones y usuarios.
- **Consecuencia para M1 a M8**: como los perfiles quedan copiados, cada script de módulo tiene que asignar funciones en las dos tablas: `conf__perfiles_funciones` (plantilla) y `conf__empresas_perfiles_funciones` (permiso efectivo de las empresas 2 y 3). Volver a correr `copiarPerfilesModulo` no sirve (resumen, punto 11). Y no usar `heredarPaginasFuncionesDesdePerfilBase`, que da todas las funciones del módulo a cualquier perfil.

Nota de tipos: `conf__empresas_perfiles.empresa_id` es `int unsigned` (`:212`), mientras que `conf__empresas.empresa_id` es `smallint unsigned` (`:166`). Las FKs del DBML hacia `conf__empresas_perfiles.empresa_perfil_id` (`int unsigned`) son correctas.

---

## 5. Inventario de `modules/ml/`

Son 120 archivos: 109 `.php` en la raíz, `ajax/` (3), `layout/` (4), `views/` (1), `gestion.zip`, `debug.log` y `debug_log.txt`. Comparé cada archivo byte a byte (`cmp`) contra el de mismo nombre en `modules/gestion/` y `modules/conf/`. Todos entraron en un único commit (`e0f95fa`, 2025-12-29) y no se tocaron más: son una foto vieja de gestión. Ninguno define `$modudo_idx = 3`; 35 tienen 2 y `tablas_tipos.php` tiene 1.

| Grupo | Archivos | Criterio | Clasificación |
| --- | --- | --- | --- |
| Idénticos a `gestion/` (algunos también a `conf/`) | `admin_funciones_perfil.php`, `compras_pedidos_model.php`, `comprobantes.php`, `comprobantes_ajax.php`, `empresas.php`, `empresas_ajax.php`, `estados_registros.php`, `estados_registros_ajax.php`, `iconos.php`, `iconos_ajax.php`, `index5.php`, `modulos.php`, `modulos_ajax.php`, `paginas.php`, `paginas_ajax.php`, `pedidos_clientes.php`, `pedidos_clientes_ajax.php`, `pedidos_compra.php`, `perfiles.php`, `perfiles_ajax.php`, `perfiles_funciones.php`, `perfiles_funciones_ajax.php`, `perfiles_usuarios.php`, `perfiles_usuarios_ajax.php`, `productos_categorias.php`, `productos_categorias_ajax.php`, `tablas.php`, `tablas_ajax.php`, `tablas_estados.php`, `tablas_tipos.php`, `usuarios.php`, `usuarios_ajax.php`, `views/dashboard.php` | Duplicado exacto, sin uso en el módulo 3 | **Sobra** |
| Legacy de AdminLTE 3 | `layout/footer.php`, `layout/header.php`, `layout/layout.php`, `layout/sidebar.php` (idénticos a `gestion/layout/`) | `CLAUDE.md` los marca como código muerto | **Sobra** |
| Endpoints rotos | `ajax/cargar_funciones.php`, `ajax/crear_funcion.php`, `ajax/guardar_funciones_perfil.php` (idénticos a `gestion/ajax/`) | Incluyen `../../../config/db.php`, que no existe | **Sobra** |
| Copias "copy" | `listas_precios_productos copy.php`, `listas_precios_productos_ajax copy.php`, `listas_precios_productos_model copy.php`, `modulos_ajax copy.php`, `modulos_model copy.php`, `modelos copy.php`, `submodelos copy.php`, `submodelos_ajax copy.php`, `submodelos_model copy.php`, `sucursales_tipos copy.php`, `sucursales_tipos_ajax copy.php`, `sucursales_tipos_model copy.php` | `CLAUDE.md` excluye los `* copy.php` | **Sobra** |
| Copias divergentes (versión vieja) | Las 52 restantes: `compras_pedidos*`, `comprobantes_model.php`, `comprobantes_fiscales*`, `comprobantes_grupos*`, `comprobantes_tipos*`, `empresas_model.php`, `entidades*`, `estados_registros_model.php`, `iconos_model.php`, `listas_precios*`, `listas_precios_productos*`, `marcas*`, `modelos*`, `modulos_model.php`, `paginas_funciones*`, `paginas_model.php`, `pedidos_clientes_model.php`, `pedidos_compra_*`, `perfiles_funciones_model.php`, `perfiles_model.php`, `perfiles_usuarios_model.php`, `productos*`, `productos_categorias_model.php`, `submodelos*`, `sucursales*`, `sucursales_tipos*`, `sucursales_ubicaciones*`, `tablas_estados_ajax.php`, `tablas_estados_model.php`, `tablas_model.php`, `tablas_tipos_ajax.php`, `tablas_tipos_model.php`, `usuarios_model.php` | Diferencias de 10 a 4.893 líneas contra `gestion/`. Como `ml/` no cambió desde 2025-12-29, la versión vigente es la de `gestion/` o `conf/` | **Sobra** (antes de borrar, mirar el `diff` de los 3 o 4 más grandes para confirmar que no hay nada propio) |
| Respaldo binario | `gestion.zip` (221.904 bytes, 101 archivos de `gestion/` fechados en 2025) | Respaldo manual commiteado | **Sobra** |
| Logs | `debug.log` (0 bytes, igual a `gestion/` y `conf/`) y `debug_log.txt` (2.096 líneas de pedidos AJAX de agosto de 2025, igual a `gestion/`) | Basura de ejecución commiteada | **Sobra** |
| Conexión | `conexion.php` (sin versionar, ignorado por `.gitignore`, credenciales fijas, igual a `gestion/`) | El código nuevo usa `config.php` y `db.php`. Hay que sacarlo de cada servidor, fuera de git | **Revisar** (operación, no commit) |
| Portada | `index.php` (igual a `gestion/`, `$modudo_idx = 2`) | El selector de empresa y las páginas 63 y 64 llevan acá | **Conservar** (en M1, poner `$modudo_idx = 3` y armar la portada) |

**Riesgo actual, independiente de M0:** 30 de los 31 `*_ajax.php` de `ml/` no verifican sesión. Solo `pedidos_compra_ajax.php` lo hace. Ejemplo: `ml/usuarios_ajax.php:1-13` responde `listar` con `obtenerUsuarios`, que hace `SELECT * FROM conf__usuarios` (`ml/usuarios_model.php:2-5`). Si en un servidor existe `modules/ml/conexion.php` (en esta copia local existe), ese endpoint expone usuarios y hashes sin login. Las versiones idénticas de `gestion/usuarios_ajax.php` y `conf/usuarios_ajax.php` tienen el mismo problema. Conviene tratarlo con prioridad, aparte de M0. No toqué nada.

---

## 6. Estructura de los scripts y pruebas

### Archivos propuestos en `sql/mantenimiento/`

| Archivo | Contenido | Tipo |
| --- | --- | --- |
| `AAAAMMDD_ml_m0_00_verificacion.sql` | V0 a V6. Solo `SELECT`. Al final imprime "OK para seguir" o la lista de fallas | Lectura |
| `AAAAMMDD_ml_m0_01_baja_prototipo.sql` | Cabecera con los comandos de respaldo (como pasos manuales). Helper `_m0_assert`. E3 en transacción con asserts de conteo. E4 con 23 `DROP TABLE IF EXISTS` en el orden de la sección 1. Verificación final: 0 tablas del prototipo | DML + DDL |
| `AAAAMMDD_ml_m0_02_ddl_tablas.sql` | Helpers `_m0_add_fk` y `_m0_add_column`. Fase A: 69 `CREATE TABLE IF NOT EXISTS` sin FKs, con PK, índices, UNIQUE y CHECK. Fase B: las FKs con el helper. Verificación: 69 tablas, columnas por tabla, FKs, motor y collation. Limpieza de helpers | DDL |
| `AAAAMMDD_ml_m0_03_estados_y_tablas.sql` | `es_final` con helper (DDL). Después, en transacción: estados 16 a 26 o 27, 69 filas de `conf__tablas` y filas de `conf__tablas_estados_registros` | DDL + DML |
| `AAAAMMDD_ml_m0_04_perfiles.sql` | En transacción: 8 perfiles, 16 copias y `layout_nombre` | DML |
| `AAAAMMDD_ml_m0_05_verificacion_final.sql` | Controles posteriores (abajo) | Lectura |
| `AAAAMMDD_ml_m0_99_rollback.sql` | Reversión (abajo). Solo se corre a mano | DDL + DML |

Reglas comunes:

- Arrancar con `SET NAMES utf8mb4;` y un `sql_mode` de sesión válido en los dos motores: `STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION`. **No incluir `NO_AUTO_CREATE_USER`**: es el valor por defecto de MariaDB, pero MySQL 8 lo eliminó y da error (conocimiento general).
- No hacer `USE` con el nombre de la base: `20260927_sync_local_a_produccion.sql:31` fija `ps_gestion_casalucho`, pero local se llama `gestion_multipyme`. Se trabaja sobre `DATABASE()`.
- Archivos en UTF-8: hay nombres con tilde ("En revisión", "Área legal").
- **Bloques de ids reservados para el módulo 3**, iguales en los dos entornos:

| Tabla | Bloque |
| --- | --- |
| `conf__estados_registros` | 16 a 27 |
| `conf__tablas` | 201 a 269 |
| `conf__tablas_estados_registros` | 2001 a 2200 (la PK es `smallint` con signo, máximo 32767, `:27762`) |
| `conf__perfiles` | 301 a 320 |
| `conf__empresas_perfiles` | 301 a 340 |
| `conf__paginas` (M1 a M8) | 300 a 399 |
| `conf__paginas_funciones` (M1 a M8) | 3001 a 3999 |

Por qué: el PHP fija `$pagina_idx`, y los scripts de M1 a M8 citan `tabla_id`, perfiles y estados, así que los ids tienen que ser los mismos en producción y en local. Los máximos ya difieren entre entornos (sección 2), y gestión sigue dando altas desde la pantalla. El `AUTO_INCREMENT` salta después del bloque, sin efecto práctico.

### Fragmento ilustrativo del DDL (no es el script)

```sql
CREATE TABLE IF NOT EXISTS `ml__territorios_pertenencias` (
  `territorio_pertenencia_id` INT NOT NULL AUTO_INCREMENT,
  `territorio_id`             INT NOT NULL,
  `territorio_superior_id`    INT NOT NULL,
  `es_principal`              TINYINT(1) NOT NULL DEFAULT 0,
  `vigente_desde`             DATE NOT NULL,
  `vigente_hasta`             DATE NULL DEFAULT NULL,
  `principal_abierta_id`      INT AS (IF(`es_principal` = 1 AND `vigente_hasta` IS NULL, `territorio_id`, NULL)) STORED,
  `creado_en`                 TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por`                INT NULL,
  `actualizado_en`            TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_por`           INT NULL,
  PRIMARY KEY (`territorio_pertenencia_id`),
  UNIQUE KEY `uk_terr_pert_principal_abierta` (`principal_abierta_id`),
  KEY `ix_terr_pert_terr_hasta` (`territorio_id`, `vigente_hasta`),
  CONSTRAINT `ck_terr_pert_vigencia` CHECK (`vigente_hasta` IS NULL OR `vigente_hasta` >= `vigente_desde`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Las FKs (`fk_terr_pert_territorio`, `fk_terr_pert_superior`) van en la fase B. El DBML tiene 149 `ref`: 99 entre tablas `ml__` y 50 hacia `conf__` (26 a `conf__empresas`, 21 a `conf__usuarios` y 3 a `conf__empresas_perfiles`). Declarar estas últimas tiene un efecto: el `DELETE FROM conf__empresas` de `conf/empresas_model.php:90` va a fallar para empresas con datos de matriz. Es deseable, pero es un cambio de comportamiento (pregunta 19).

### Cómo se prueba

**Entorno MariaDB 10.4.22 (XAMPP local)**

1. Hacer un volcado fresco de la base local real.
2. Importarlo en una base nueva, por ejemplo `ml_m0_prueba`. Nunca sobre una base en uso: `casalucho.sql` trae `DELETE FROM` antes de cada `INSERT` (por ejemplo `:24465`) y `casalucho_produccion.sql` trae 177 `DROP TABLE IF EXISTS`.
3. Correr 00 → respaldo → 01 → 02 → 03 → 04 → 05.

**Entorno MySQL 8.0.46**

Lo ideal es un esquema de prueba en el mismo servidor de producción: misma versión, `sql_mode`, `lower_case_table_names` y privilegios. Si no, un contenedor con 8.0.x y el `sql_mode` y `lower_case_table_names = 0` que devuelva V0 en producción. Importar un volcado fresco de producción y repetir la secuencia.

**En cada entorno**

1. Secuencia completa y verificación final en verde.
2. Rollback (99) y comparación con el estado inicial: `CHECKSUM TABLE` de las tablas del respaldo 4, y las 23 tablas y 24 semillas restauradas.
3. **Prueba de reanudación**: volver a correr 01 a 04, cortar 02 a mitad (matar la sesión después de unas 30 tablas), relanzarlo y verificar que termina igual.
4. Volver a correr 03 y 04 completos y verificar que no duplican filas (las guardas `NOT EXISTS` funcionan).

**Prueba funcional** (en la aplicación, contra la base de prueba)

- El superadmin entra a la empresa 2 y el módulo 3, y ve el menú con "Principal" y "Tablas".
- En gestión, dar de alta un depósito y un cliente: siguen naciendo en estado 1, porque `obtenerEstadoInicial` no cambió.
- `conf/tablas.php` y `conf/tablas_estados.php` listan las 69 tablas y sus estados.
- `conf/estados_registros.php` muestra los estados nuevos.
- Un usuario no superadmin con un perfil 30x asignado ve el módulo 3 en el selector y ninguna página (todavía no hay funciones).

### Controles posteriores (script `05`)

| Control | Esperado |
| --- | --- |
| Tablas `ml__` en `information_schema.tables` | 69, todas InnoDB y `utf8mb4_unicode_ci` |
| Columnas con collation distinta de `utf8mb4_unicode_ci` en las `ml__` | 0 |
| Tablas exclusivas del prototipo que siguen existiendo (`ml__ambitos_organizacionales`, `ml__jurisdicciones`, `ml__tareas`…, 13 en total) | 0 |
| FKs de las `ml__` (`referential_constraints`) | El número que salga del DDL definitivo (149 si se declaran todas) |
| `conf__tablas` del módulo 3 agrupadas por `tabla_tipo_id` | (1, 53), (2, 7), (3, 9) |
| Nombres de `conf__tablas` del módulo 3 que no existen físicamente | 0 (comparando con `COLLATE utf8mb4_unicode_ci` contra `information_schema`) |
| Tablas `ml__` con columna `tabla_estado_registro_id` y sin filas de estado, o al revés | 0 |
| Tablas con estados y `SUM(es_inicial) <> 1` | 0 |
| `conf__estados_registros` con id 16 o mayor | 11 o 12 |
| `SELECT estado_registro_id FROM conf__estados_registros WHERE valor_estandar IS NOT NULL ORDER BY valor_estandar LIMIT 1` | 1 (no se rompió el estado inicial de gestión) |
| `conf__perfiles` del módulo 3 / `conf__empresas_perfiles` del módulo 3 con `perfil_id_base` | 8 / 16 |
| `conf__modulos.layout_nombre` del módulo 3 | `'default'` (si se aprueba) |

### Rollback (script `99`)

1. Verificar que ninguna tabla de gestión empezó a usar los estados 16 a 27, sobre todo 26 `CANCELADO`: hay que revisar `conf__tablas_estados_registros` y `conf__paginas_funciones` fuera del módulo 3. Si alguna los usa, no se borran.
2. `DROP` de las 69 tablas nuevas, de hijas a padres (están vacías).
3. En transacción, `DELETE` por bloque de ids **y** `modulo_id = 3`: `conf__empresas_perfiles`, `conf__perfiles`, `conf__tablas_estados_registros`, `conf__tablas` y los estados del bloque.
4. `ALTER TABLE conf__tablas_estados_registros DROP COLUMN es_final` (con helper que verifica que la columna existe).
5. Restaurar los respaldos 2 y 3. Volver `layout_nombre` a `''`.
6. Correr 00 de nuevo: tiene que dar exactamente lo mismo que antes de M0.

---

## 7. MySQL 8.0.46 frente a MariaDB 10.4.22: lo que afecta al DDL de las 69 tablas

**V** = verificado en el repositorio. **G** = conocimiento general del motor, no verificable acá (confirmar con V0 o con la prueba).

| Tema | Diferencia | Regla para el DDL | Base |
| --- | --- | --- | --- |
| `CURRENT_TIMESTAMP` y `current_timestamp()` | MySQL lo exporta sin paréntesis (`:27059`) y MariaDB con paréntesis (`casalucho.sql:23912`). Los dos aceptan ambas formas | Escribir `CURRENT_TIMESTAMP` | V |
| `TIMESTAMP` y `explicit_defaults_for_timestamp` | MySQL 8 lo tiene en ON; MariaDB 10.4, en OFF. Con OFF, un `timestamp` sin `NULL` explícito queda `NOT NULL`, y el primero recibe solo `DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | Declarar todo explícito: `TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP` o la forma `NOT NULL` del repositorio (ver C8). Nunca un `timestamp` "pelado" | G (V0 lo confirma). En el repositorio no hay ningún `timestamp` nulable: 18 tablas usan `NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE …` (V) |
| `DATETIME`, `TIMESTAMP` y zona horaria (regla 9, `03:35`) | `TIMESTAMP` se guarda en UTC y se convierte con la `time_zone` de la sesión; tiene límite en 2038. `DATETIME` no se convierte. El PHP fija Buenos Aires (`db.php:8`), pero la conexión no fija `time_zone` (`db.php:28-34`), así que `NOW()` y `CURDATE()` usan la zona del servidor (`core/permisos.php:27` usa `CURDATE()`) | `TIMESTAMP` solo para auditoría (`creado_en`, `actualizado_en`). Plazos y vencimientos en `DATETIME` calculados por la aplicación con el huso del país (`ml__territorios.huso_horario`, `04:175`). Hay que decidir si se guarda en UTC o en hora local de la entidad (pregunta 15). `ml__ciclos.fecha_vencimiento` es `date` (`04:907`) y no admite plazos en horas (ver C15) | V (código) y G (motores) |
| `CHECK` | MariaDB lo aplica desde 10.2.1; MySQL, desde 8.0.16. Los dos están en uso (`:46352` y `casalucho.sql:40828`). MySQL nombra solo `<tabla>_chk_<n>` (`:46352`) y exige **nombres únicos en todo el esquema**. MySQL además prohíbe `CHECK` sobre columnas con acciones referenciales `CASCADE` o `SET NULL` y sobre `AUTO_INCREMENT`. Ninguno de los dos admite funciones no deterministas como `CURDATE()` | Nombrar siempre `ck_<tabla_abreviada>_<regla>`, de 64 caracteres como máximo y únicos. Las FKs de las columnas de ámbito, sin `CASCADE` ni `SET NULL` | V (uso y nombres) y G (restricciones) |
| Columnas generadas e índices ("una sola vigente") | Los dos aceptan `AS (…) STORED` (`:28396` y `casalucho.sql:25269`, donde MariaDB 10.4 lo exporta como `STORED`). `UNIQUE` permite varios `NULL` en los dos. La expresión tiene que ser determinista: "vigente" se puede modelar como "abierta" (`vigente_hasta IS NULL`), no como "vigente hoy" | `STORED` (no `VIRTUAL` ni `PERSISTENT`), sin declarar `NULL` o `NOT NULL` en la columna generada (MariaDB no lo admite). Sirve para una sola principal abierta y un solo titular abierto (`04:34-35`). El no solapamiento de vigencias futuras sigue necesitando trigger o validación en la aplicación | V (sintaxis) y G (restricciones) |
| `JSON` | MariaDB lo trata como `LONGTEXT` con `CHECK (json_valid())` (`casalucho.sql:40828-40829`); en producción esa misma tabla quedó como `longtext` con CHECK con nombre automático (`:46341-46353`). MariaDB 10.4 no tiene los operadores `->` ni `->>` | `TEXT`, como dice `03:41` y el DBML (`04:30`). Validar el JSON en PHP. Si se quiere `CHECK (json_valid(...))`, ponerle nombre explícito | V (tipos) y G (operadores) |
| `IF NOT EXISTS` en columnas e índices | `ADD COLUMN IF NOT EXISTS`, `ADD INDEX IF NOT EXISTS` y `CREATE INDEX IF NOT EXISTS` existen solo en MariaDB (`20260929_productos_compatibilidad_indices.sql:17-19`). `CREATE TABLE IF NOT EXISTS` y `DROP TABLE IF EXISTS` sirven en los dos | Helpers con `information_schema` y `PREPARE`, como `20260927_sync_local_a_produccion.sql:36-65` | V |
| Ancho de enteros | MySQL 8.0.17 en adelante lo descarta salvo en `tinyint(1)` (`:27767`); MariaDB lo muestra (`int(11)`) | Escribir `INT`, `SMALLINT UNSIGNED` y `TINYINT(1)` para booleanos. `BOOLEAN` equivale a `TINYINT(1)` en los dos | V |
| Collation por defecto | MySQL 8 usa `utf8mb4_0900_ai_ci`; MariaDB 10.4, `utf8mb4_general_ci`. Sin `COLLATE`, cada motor crea una cosa distinta y aparecen errores de "Illegal mix of collations" en joins y `UNION` | Siempre `DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci` (`03:42`). Nunca `utf8` (alias de utf8mb3). Ojo con cruces de texto contra `conf__empresas` y `conf__modulos` (latin1, `:178` y `:26925`) y contra `conf__perfiles.perfil_nombre` y `conf__empresas_perfiles.empresa_perfil_nombre` (utf8mb3_spanish2_ci, `:215`) | V (collations) y G (defaults) |
| Longitud de índices | Con `ROW_FORMAT=DYNAMIC` el prefijo llega a 3072 bytes (768 caracteres utf8mb4); con COMPACT, a 767 bytes (191 caracteres) | Controlar `@@innodb_default_row_format` (V0). Las claves más largas del DBML (`varchar(500)` en `archivo_ref`, que no se indexa; `codigo varchar(80)`; `objeto_tabla varchar(64)` + `int`) entran holgadas en DYNAMIC | G (5 tablas de producción declaran `ROW_FORMAT=DYNAMIC`, V) |
| `DEFAULT` en `TEXT` | MariaDB lo acepta; MySQL solo como expresión, `DEFAULT ('')` | No poner `DEFAULT` en columnas `TEXT` | G |
| `sql_mode` | Los defaults difieren (MySQL 8 incluye `ONLY_FULL_GROUP_BY` y `NO_ZERO_DATE`). Los triggers de producción quedaron guardados con `SQL_MODE=''` (`:89401`), lo que sugiere que el `sql_mode` real de producción es vacío | Fijar el `sql_mode` de sesión del script (sección 6). Consultar el global con V0. No usar fechas `0000-00-00` | V (indicio) y G |
| Nombres de FK | Únicos por esquema en InnoDB, en los dos motores | `fk_<tabla_abreviada>_<columna>`, de 64 caracteres como máximo. Los nombres largos (`ml__obligaciones_asignadas_origenes`, 35 caracteres) obligan a abreviar | G |
| DDL atómico | MySQL 8 lo tiene por sentencia; MariaDB 10.4 no | Una sentencia por tabla y scripts reanudables | G |
| `lower_case_table_names` | Local es Windows (`casalucho.sql:4`, valor habitual 1); producción es Linux (`:4`, valor habitual 0) | Todos los nombres en minúscula, también en el SQL que arma el PHP | V (sistemas operativos) y G (valores) |
| Triggers | Producción ya tiene triggers, rutinas y un evento (`:89403`, `:89225`, `:89394`), así que el usuario tiene privilegios. Con `log_bin` activo, MySQL puede exigir `log_bin_trust_function_creators` para crear rutinas | Las reglas que van en trigger (`04:33-39`) se escriben en el módulo dueño (M1 en adelante), no en M0 | V (existencia) y G (privilegios) |

---

## 8. Riesgos y lo que no se pudo verificar sin la base

**Riesgos**

1. **Divergencia entre entornos y respecto de los volcados.** Los volcados son del 25 al 27/09. Producción tiene `conf__` a medio aplicar (página 96 sin tabla 108). Si no se usan bloques reservados, los ids de M0 pueden diferir entre entornos y romper los `$pagina_idx` de M1.
2. **Semillas tomadas como "sin datos".** Si V2 da más de 24 filas, la decisión 1 de `03:19` no se aplica tal cual.
3. **DDL no transaccional.** Un corte a mitad deja la base en un estado intermedio. Se mitiga con scripts reanudables y con el respaldo.
4. **Concurrencia.** Si alguien edita `conf__tablas`, `conf__perfiles` o `conf__estados_registros` desde la pantalla durante el script, pueden aparecer colisiones de id o conteos que no cierran. Hace falta una ventana de mantenimiento.
5. **Estados genéricos compartidos con gestión.** Las pantallas de `conf/` listan todos los estados (`conf/tablas_estados_model.php:18`, `conf/paginas_funciones_model.php:68`): los nuevos van a aparecer ahí, y eso está bien. `CANCELADO` activa lógica latente de remitos si gestión lo usa (`ventas_remitos_model.php:1359`). Un `valor_estandar` menor o igual que 1 rompería las altas de gestión.
6. **FKs hacia `conf__`.** Bloquean el borrado físico de empresas y usuarios con datos de matriz (`conf/empresas_model.php:90`). Es un cambio de comportamiento, aunque sea deseable.
7. **Permisos de M1 a M8.** `copiarPerfilesModulo` no propaga funciones nuevas, y `heredarPaginasFuncionesDesdePerfilBase` asigna todas las del módulo (resumen, punto 11).
8. **Seguridad de `modules/ml/`** (y de sus gemelos en `gestion/` y `conf/`): endpoints sin sesión (sección 5).
9. **Zona horaria.** El PHP y MySQL pueden tener relojes distintos (`db.php:8` frente a `CURDATE()` en `core/permisos.php:27`). En la matriz, con plazos en horas y varios países, el cálculo tiene que quedar en la aplicación.
10. **`TIMESTAMP` y 2038**: no afecta a la auditoría (siempre es "ahora"), pero descarta `TIMESTAMP` para vigencias y plazos.

**Sin acceso a la base no pude verificar**

- Datos reales en las `ml__` (V2), FKs, triggers y rutinas creados después de los volcados (V3), ni filas `conf__` posteriores (V4).
- `sql_mode` global, `explicit_defaults_for_timestamp`, `innodb_default_row_format`, `lower_case_table_names`, `time_zone`, `log_bin` y `log_bin_trust_function_creators` de cada entorno (V0).
- Privilegios del usuario del script: `REFERENCES` para FKs hacia `conf__`, `CREATE ROUTINE` para los helpers, `ALTER` sobre `conf__tablas_estados_registros`.
- Si alguna tabla de negocio guarda el estado 14 (el hueco).
- Si `modules/ml/conexion.php` existe en el servidor de producción.
- Qué base y qué empresa son el destino real de la matriz (pregunta 1).

---

## Contradicciones encontradas

No corregí ninguno de los documentos; quedan registradas acá con la evidencia de cada lado.

| # | Qué dice el documento | Qué muestra el repositorio u otro documento |
| --- | --- | --- |
| C1 | `03:12` y `07:3` citan `04-modelo-de-datos-v0.2.dbml` | El archivo es `docs/matriz-legal/04-modelo-de-datos.dbml`; la versión figura en `04:2` ("v0.2") |
| C2 | `07:11` dice que las 53 tablas Diccionario usan "Activo e Inactivo (patrón del tipo 1)" | En el DBML, 43 de esas 53 no tienen `tabla_estado_registro_id`. Por ejemplo: `ml__empresas_regiones` (`04:106-119`), `ml__territorios_tipos` (`04:160-168`), `ml__raci_roles` (`04:416-424`), `ml__normas_tipos` (`04:440-451`), `ml__criticidades` (`04:650-658`), `ml__log_cambios` (`04:1209-1223`). Solo 10 la tienen: `ml__grupos`, `ml__regiones_grupo`, `ml__empresas_grupo`, `ml__territorios`, `ml__entidades_tipos`, `ml__entidades`, `ml__tareas_plantillas`, `ml__obligaciones_asignadas`, `ml__evidencias` y `ml__cargas_filas`. `07:15` lo explica para las tablas de relación, pero no para catálogos con ABM propio como `ml__raci_roles` o `ml__normas_tipos`, cuya página de Diccionario necesita Activo e Inactivo para "Inhabilitar" (`20260925_productos_tipos_abm.sql:66-69`) |
| C3 | `07:35` y `07:41` ponen `ml__empresas_eventos` y `ml__reasignaciones` como Proceso corto | Las dos no tienen `tabla_estado_registro_id` en el DBML (`04:147-158` y `04:1020-1033`) |
| C4 | El DBML pone `tabla_estado_registro_id … default: 1` en las 9 tablas de proceso largo (`04:550`, `:855`, `:875`, `:910`, `:937`, `:1071`, `:1089`, `:1149`, `:1182`) y en las 5 de proceso corto que tienen la columna (`04:483`, `:506`, `:566`, `:585`, `:703`) | Con la decisión 2 (`04:10-11`, `03:20`), 1 es el genérico "Activo" (`:713`), que no figura entre los estados de esas tablas (`07:12`, `07:21-29`). El default tiene que ser el estado inicial (3, 4, 16 o 17) o no tener default (pregunta 7) |
| C5 | `07:12` asigna al proceso corto 4 estados del patrón del tipo 2 | El patrón real del tipo 2 tiene 6 estados (`:27969-27974`, con Pendiente de Recepción y Recibido; `07:5` lo reconoce). Si se cargan desde la pantalla con `agregarEstadosTabla` (`tablas_model.php:172`), entran los 6. El script tiene que cargar los 4 explícitamente |
| C6 | `07:49` habla de "11 estados genéricos nuevos" | `07:21-29` usa además "Con error" en tres tablas, que no está entre los 11 ni tiene equivalente existente (sección 3) |
| C7 | `01:139` dice que los perfiles "parten de `conf__perfiles` de la empresa titular del sistema"; `04:67` ("Empresa titular del sistema; sus perfiles son el catalogo base") | `conf__perfiles` no tiene `empresa_id`: es por módulo (`:27460-27466`). Los perfiles por empresa son `conf__empresas_perfiles` (`:210-219`), y la copia se hace por módulo, no desde una empresa (`empresas_modulos_model.php:201-209`) |
| C8 | `04:16`: `actualizado_en timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP` | No hay ningún `timestamp` nulable en los volcados. La convención es `NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` (18 tablas, por ejemplo `:27059`). No es un error, pero es una divergencia que hay que decidir (pregunta 13) |
| C9 | `04:33-37` pone "Ámbito con una sola clave completa (CHECK)" entre las "reglas que MySQL no impone" | `04:31` dice que CHECK funciona en ambos motores, y está en uso (`:46352`, `casalucho.sql:40828`). Esa regla sí se puede imponer con CHECK, siempre que las FKs de esas columnas no tengan `CASCADE` ni `SET NULL` |
| C10 | `03:19` y `03:47` deciden "si hay datos" con la consulta 8 de `06` (`06:296-302`, `table_rows`) | `table_rows` es una estimación en InnoDB; el propio volcado lo marca como aproximado (`:88570`, "~1 rows (aproximadamente)"). Hace falta `COUNT(*)` (V2) |
| C11 | `03:19` y `04:7-8`: reemplazar "siempre que la base de instalación no tenga datos" | El prototipo tiene 24 filas semilla (`:88572-88899`). El criterio tiene que decir qué pasa con ellas (pregunta 2) |
| C12 | `06:17`: "Los volcados locales coinciden en estructura" | `casalucho_local.sql` y `casalucho_desarrollo.sql` son solo estructura, y `casalucho_desarrollo.sql` repite el esquema (`:2771` y `:6053`). Los datos de `conf__` difieren entre producción y `casalucho.sql` (sección 2). Producción tiene la página 96 apuntando a una `conf__tablas` 108 inexistente (`:27041` frente a `:27737`) |
| C13 | `CLAUDE.md:30` y `:33` dicen que `tabla_estado_registro_id` y las transiciones apuntan a `conf__tablas_estados_registros` | Los datos guardan el id genérico (`06:134-143`, decisión 2). Es el paso 9, ya previsto |
| C14 | `03:73` toma de la plantilla "`START TRANSACTION … COMMIT`" | Ese bloque no protege el DDL: el propio repositorio lo advierte en `20260927_sync_local_a_produccion.sql:25-26`. El diseño de la sección 1 lo tiene en cuenta |
| C15 | `04:907`: `ml__ciclos.fecha_vencimiento date` | `01:81` (plazo de un evento "en horas o días") y `01:202` (huso del país) piden fecha y hora. Es interno a la documentación, pero condiciona el DDL |

---

## Paso 9: texto propuesto para `CLAUDE.md`

No lo apliqué. Reemplazar la frase de `CLAUDE.md:30` por:

> Cada tabla de negocio tiene una columna `tabla_estado_registro_id` (NO `estado_id` directo). **Guarda el id genérico de `conf__estados_registros`** (Activo = 1, Inactivo = 2, Borrador = 3, …), no la PK de `conf__tablas_estados_registros`. `conf__tablas_estados_registros` es la lista de estados habilitados para cada tabla, con nombre propio, color, `es_inicial` [y `es_final`]: sirve para configurar y mostrar, no como clave.

Y en `CLAUDE.md:33`, agregar: "Los valores de origen y destino son ids genéricos de `conf__estados_registros`; 0 significa 'sin registro' (alta)". Así lo muestran `:27414-27429` y `20260925_productos_tipos_abm.sql:66-69`.

---

## 9. Preguntas antes de escribir el script

1. **Destino.** ¿La matriz se instala en `ps_gestion_casalucho` (producción, MySQL 8) y `gestion_multipyme` (local), o en otra base o cliente? ¿Cuál es la empresa titular del grupo: 1 CONFIGURACION, 2 Casa Lucho o 3 CEC Patagones (`:183-185`)?
2. **Semillas.** ¿Las 24 filas semilla del prototipo cuentan como "sin datos"? Opciones: (a) sí, se descartan; (b) sí, pero se reutilizan como semilla de M1 y M2 (`raci_tipos` y `normas_tipos` tienen equivalentes en el DBML); (c) no, se para.
3. **"Con error".** ¿Se da de alta como estado genérico 12.º (recomendado) o se mapea a Rechazado y quedan 11?
4. **Marca final.** ¿Se agrega `es_final` en `conf__tablas_estados_registros` (opción A, recomendada)? ¿Sí o no?
5. **Las 43 tablas Diccionario sin columna de estado.** (a) Agregar `tabla_estado_registro_id` a las 53; (b) agregarla solo a los catálogos que van a tener ABM propio y registrar el resto en `conf__tablas` sin estados (recomendado); (c) registrarlas todas sin estados.
6. **`ml__empresas_eventos` y `ml__reasignaciones`.** ¿Se les agrega `tabla_estado_registro_id` (quedan como proceso corto) o se reclasifican como Diccionario?
7. **Default de la columna de estado** en las tablas de proceso: ¿sin `DEFAULT`, para que el código siempre lo fije (recomendado), o `DEFAULT` igual al estado inicial de cada tabla?
8. **Bloques de ids reservados** para el módulo 3 (sección 6): ¿sí o no?
9. **Perfiles.** ¿Aprobás los 8 perfiles base? ¿Se copian los 8 a las empresas 2 y 3, o los de matriz (301, 302, 303 y 307) solo a la empresa titular?
10. **Uno o dos módulos.** `01:145` describe "dos tipos de módulo" (matriz regional y empresa). ¿Se resuelven los dos dentro del módulo 3 con perfiles y páginas, o se crea un segundo módulo en `conf__modulos`?
11. **Páginas 63 y 64.** ¿Se reutilizan (recomendado) o se borran?
12. **`layout_nombre`.** ¿Se pone `'default'` (sin efecto funcional) o se deja vacío?
13. **`actualizado_en`.** ¿`NULL DEFAULT NULL ON UPDATE` como el DBML, o `NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE` como el resto del repositorio?
14. **"Una sola vigente".** ¿Columnas generadas `STORED` con `UNIQUE` para "una sola abierta", y triggers o la aplicación para el no solapamiento? ¿Sí o no?
15. **Fechas y horas de los plazos.** ¿`DATETIME` en UTC con el huso en `ml__territorios` (recomendado), o `DATETIME` en hora local de la entidad? ¿`ml__ciclos.fecha_vencimiento` pasa a `DATETIME`?
16. **Cliente para correr los scripts.** ¿`mysql` por línea de comandos (recomendado, porque aborta en el primer error) o HeidiSQL?
17. **`modules/ml/`.** ¿Autorizás borrar lo clasificado como "sobra", en un commit aparte? ¿Se trata ya, y por separado, el riesgo de los endpoints sin sesión en `ml/`, `gestion/` y `conf/`?
18. **Entorno de prueba MySQL 8.** ¿Hay un esquema de prueba en el servidor de producción, o se levanta un contenedor 8.0.x?
19. **FKs hacia `conf__empresas`, `conf__usuarios` y `conf__empresas_perfiles`.** ¿Se declaran (bloquean borrados físicos en `conf/empresas_model.php:90`) o quedan como referencias lógicas?
20. **Ventana de mantenimiento.** ¿Cuándo, y quién tiene acceso con privilegios de DDL en producción?
