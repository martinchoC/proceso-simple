# Clasificación de las 69 tablas ml__ y estados nuevos

Versión 2. Reemplaza a la primera: incorpora las decisiones tomadas a partir de `08-diseno-m0.md`. Base: `04-modelo-de-datos.dbml` (v0.3) y `06-hallazgos-del-proyecto.md`.

Sirve para registrar las tablas en `conf__tablas` (columna `tabla_tipo_id`) y sus estados en `conf__tablas_estados_registros`.

Tipos de tabla reales (`conf__tablas_tipos`): 1 Diccionario, 2 Proceso corto, 3 Proceso largo. Estados patrón cargados hoy: el tipo 1 usa Activo e Inactivo; el tipo 2 tiene 6 (Borrador, Pendiente de Aprobación, Confirmado, Eliminado, Pendiente de Recepción y Recibido). **El tipo 3 no tiene estados patrón y no se cargan**: cada tabla de proceso largo lleva los suyos, por script.

## Resumen

| Tipo | Tablas | Estados |
| --- | --- | --- |
| Diccionario con estado | 20 | Activo e Inactivo (id 1 y 2) |
| Diccionario sin estado | 33 | Ninguno: se registran en `conf__tablas` sin estados y se cierran con `vigente_hasta` |
| Proceso corto | 7 | Se cargan explícitamente 4: Borrador (3), Pendiente de Aprobación (4), Confirmado (5), Eliminado (6) |
| Proceso largo | 9 | Propios de cada tabla (ver abajo) |

Las tablas de proceso (corto y largo) no llevan `DEFAULT` en `tabla_estado_registro_id`: el código fija siempre el estado inicial.

## Proceso largo

Notación: id genérico y nombre propio en la tabla. **I** = inicial (`es_inicial`) y **F** = final (`es_final`).

| Tabla | Estados |
| --- | --- |
| `ml__normas_conflictos` | 17 Abierto **I** · 19 Escalado al área legal · 5 Resuelto **F** |
| `ml__excepciones` | 4 Solicitada **I** · 19 En aprobación legal · 5 Aprobada · 25 Rechazada **F** · 20 Vencida **F** |
| `ml__recalculos_pendientes` | 17 Pendiente **I** · 21 Procesado **F** · 27 Con error (reintento) |
| `ml__ciclos` | 17 Pendiente **I** · 18 En curso · 20 Vencido · 21 Cumplido **F** · 22 Cumplido fuera de plazo **F** · 23 Incumplido **F** · 24 Eximido **F** · 26 Cancelado **F** |
| `ml__ciclos_tareas` | 16 Inactiva **I** · 17 Pendiente · 18 En curso · 19 En revisión · 21 Cumplida **F** · 25 Rechazada (vuelve a En curso) · 26 Cancelada **F** |
| `ml__alertas` | 17 Programada **I** · 21 Enviada **F** · 27 Con error · 26 Cancelada **F** |
| `ml__incidencias` | 17 Abierta **I** · 18 En seguimiento · 21 Cerrada **F** |
| `ml__cargas` | 3 Cargada **I** · 27 Validada con errores · 4 Validada · 5 Confirmada **F** |
| `ml__cambios_masivos` | 3 Borrador **I** · 4 Pendiente de aprobación · 5 Aprobado · 21 Aplicado **F** · 25 Rechazado **F** |

En la tarea, "Inactiva" es el estado inicial: toda tarea nace dentro de un ciclo y el motor activa enseguida las que no tienen predecesoras. Si la revisión es opcional, la transición va de En curso a Cumplida sin pasar por En revisión.

Criterio: **Confirmado (5)** significa que se tomó una decisión (resuelto, aprobado, confirmado) y **Cumplido (21)** que se ejecutó (procesado, enviado, cerrado, aplicado).

## Proceso corto

| Tabla | Observación |
| --- | --- |
| `ml__empresas_eventos` | Se confirma antes de recalcular lo futuro |
| `ml__normas` | Vigente o derogada se resuelve con las fechas y las relaciones, no con el estado |
| `ml__normas_versiones` | Borrador, publicada (Confirmado); obsoleta por `vigente_hasta` |
| `ml__procedimientos` | Soporte; aprobación del procedimiento |
| `ml__procedimientos_versiones` | Borrador, publicada (Confirmado); obsoleta por `vigente_hasta` |
| `ml__obligaciones` | Activa o suspendida se resuelve con las fechas |
| `ml__reasignaciones` | Borrador hasta que se aplica |

## Diccionario con estado

`ml__grupos`, `ml__regiones_grupo`, `ml__empresas_grupo`, `ml__empresas_eventos_tipos`, `ml__territorios_tipos`, `ml__territorios`, `ml__feriados`, `ml__entidades_tipos`, `ml__entidades`, `ml__datos_tipos`, `ml__organismos`, `ml__normas_tipos`, `ml__normas_jerarquias`, `ml__normas_relaciones_tipos`, `ml__criticidades`, `ml__periodicidades`, `ml__vencimientos_reglas`, `ml__tareas_plantillas`, `ml__obligaciones_asignadas`, `ml__evidencias`

## Diccionario sin estado

`ml__empresas_regiones`, `ml__empresas_nombres`, `ml__territorios_pertenencias`, `ml__entidades_empresas`, `ml__entidades_ubicaciones`, `ml__entidades_datos`, `ml__ambitos_tipos`, `ml__usuarios_ambitos`, `ml__delegaciones`, `ml__opciones`, `ml__opciones_valores`, `ml__configuracion_grupo`, `ml__raci_roles`, `ml__normas_relaciones`, `ml__procedimientos_instrucciones`, `ml__accesos_confidenciales`, `ml__auditoria_accesos`, `ml__obligaciones_tipos_entidades`, `ml__obligaciones_jurisdicciones`, `ml__obligaciones_dependencias`, `ml__obligaciones_alertas`, `ml__tareas_plantillas_dependencias`, `ml__obligaciones_asignadas_origenes`, `ml__ciclos_vinculos_tipos`, `ml__ciclos_tareas_dependencias`, `ml__obligaciones_asignadas_raci`, `ml__ciclos_raci`, `ml__ciclos_tareas_raci`, `ml__indicadores_cierres`, `ml__cargas_tipos`, `ml__cargas_filas`, `ml__cambios_masivos_detalles`, `ml__log_cambios`

Son relaciones, vigencias y catálogos fijos del sistema (sin ABM propio). `ml__cargas_filas` pasó a este grupo: el resultado de cada fila se deduce de la columna `errores`.

## Estados nuevos en `conf__estados_registros`

La tabla real guarda un id genérico por estado, y cada tabla le pone nombre propio en `conf__tablas_estados_registros`. Se dan de alta **12 estados genéricos**, con ids del bloque 16 a 27. El id 14 queda como hueco y no se reutiliza. Los 14 actuales (ids 1 a 13 y 15) no equivalen a estos.

| id | Estado | `codigo_estandar` | `valor_estandar` y `orden_estandar` |
| --- | --- | --- | --- |
| 16 | En espera | `EN_ESPERA` | 200 |
| 17 | Pendiente | `PENDIENTE` | 210 |
| 18 | En curso | `EN_CURSO` | 220 |
| 19 | En revisión | `EN_REVISION` | 230 |
| 20 | Vencido | `VENCIDO` | 240 |
| 21 | Cumplido | `CUMPLIDO` | 250 |
| 22 | Cumplido fuera de plazo | `CUMPLIDO_FUERA_PLAZO` | 260 |
| 23 | Incumplido | `INCUMPLIDO` | 270 |
| 24 | Eximido | `EXIMIDO` | 280 |
| 25 | Rechazado | `RECHAZADO` | 290 |
| 26 | Cancelado | `CANCELADO` | 300 |
| 27 | Con error | `CON_ERROR` | 310 |

- **`valor_estandar` desde 200:** unas 25 funciones `obtenerEstadoInicial` de gestión toman como estado inicial el de menor `valor_estandar`. Hoy es Activo (1). Un valor de 0 o 1 en los estados nuevos rompería las altas de gestión.
- **`CANCELADO`:** gestión ya espera ese código (`ventas_remitos_model.php:650` y `:1359`). El estado deja de ser exclusivo de la matriz.
- **Marca de estado final:** columna `es_final TINYINT(1) NOT NULL DEFAULT 0` en `conf__tablas_estados_registros`, junto a `es_inicial`. Es por tabla porque el mismo estado es final en una y no en otra: Rechazado no es final en la tarea y sí en excepciones y cambios masivos; Vencido no es final en el ciclo y sí en la excepción.
- **Estados finales del ciclo:** cumplido, cumplido fuera de plazo, incumplido, eximido y cancelado. "Vencido" es recuperable.

## Bloques de ids reservados para el módulo 3

Iguales en producción y en local, porque el PHP fija `$pagina_idx` y los scripts de M1 a M8 citan estos ids.

| Tabla | Bloque |
| --- | --- |
| `conf__estados_registros` | 16 a 27 |
| `conf__tablas` | 201 a 269 |
| `conf__tablas_estados_registros` | 2001 a 2200 (la PK es `smallint` con signo) |
| `conf__perfiles` | 301 a 320 |
| `conf__empresas_perfiles` | 301 a 340 |
| `conf__paginas` (M1 a M8) | 300 a 399 |
| `conf__paginas_funciones` (M1 a M8) | 3001 a 3999 |

## Pendiente

- Confirmar la lista de Diccionario con estado: son los maestros y los catálogos con página de ABM propia. Si algún catálogo de "sin estado" va a necesitar Inhabilitar, se mueve de grupo.
- Dar de alta `es_final` y cargar los estados del tipo 3 por script (no hay patrón).
