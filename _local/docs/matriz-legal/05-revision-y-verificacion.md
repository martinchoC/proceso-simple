# Revisión previa del DBML y verificación contra la base real

Archivo revisado: `04-modelo-de-datos.dbml` (v0.1).

## Qué se pudo y qué no

- Hice una **revisión previa** del DBML desde dos ángulos: SQL y convenciones. Los hallazgos están abajo.
- **No** pude ejecutar `revisor-sql` ni `revisor-convenciones`, ni consultar la base real. Para eso están las consultas y los prompts de las secciones siguientes.
- No modifiqué el DBML. Conviene corregirlo (v0.2) cuando vuelvan los resultados reales, para no rehacerlo dos veces.

## Hallazgos de la revisión previa

### Ángulo SQL

| # | Hallazgo | Por qué importa | Acción |
| --- | --- | --- | --- |
| 1 | El DBML usa `int` para `tabla_estado_registro_id`; el DDL de `conf__paginas_funciones` que compartiste lo declara `SMALLINT(4)` | Una clave lógica con distinto tipo complica los cruces y los índices | Verificar con la consulta 3 y unificar |
| 2 | El DBML no fija motor ni juego de caracteres; tu DDL usa InnoDB y `utf8mb4_unicode_ci` | Si las tablas nuevas salen con otro juego de caracteres, fallan los cruces con `conf__*` | Agregarlo al generar el SQL |
| 3 | Tu DDL usa `current_timestamp()`, estilo habitual de MariaDB | Las restricciones CHECK (ámbito con una sola clave completa) requieren MySQL 8.0.16 o MariaDB 10.2.1 | Confirmar la versión con la consulta 5 |
| 4 | Solo hay dos índices definidos | Las consultas de vigencia, vencimiento y alertas van a necesitar índices compuestos, por ejemplo en ciclos (empresa y vencimiento), asignadas (empresa y vigencia), ubicaciones (entidad, base y vigencia), alertas (programada y enviada) | Agregar en v0.2 |
| 5 | Faltan unicidades de negocio | Sin ellas se pueden duplicar versiones, feriados o valores de opción | Únicos en `(norma_id, version)`, `(territorio_id, fecha)` de feriados, `(opcion_id, codigo)` y `(obligacion_asignada_id, periodo_desde)` en ciclos |
| 6 | MySQL no impone reglas de vigencia: una sola pertenencia principal vigente por nodo, un solo titular vigente por entidad, vigencias que no se solapan | Son reglas centrales de la lógica y hoy dependen del código | Triggers, columna generada con índice único, o validación obligatoria en la capa de aplicación |
| 7 | Cuatro tablas usan referencia polimórfica (accesos confidenciales, auditoría de accesos, recálculos pendientes, registro de cambios) | No hay integridad referencial sobre `objeto_tabla` y `objeto_id` | Aceptable, pero documentarlo y validar en la aplicación |
| 8 | El ámbito se expresa con cuatro claves opcionales sin CHECK | Podría quedar un ámbito con dos claves o ninguna | CHECK, o validación en la aplicación si la versión no lo soporta |

### Ángulo convenciones

| # | Hallazgo | Por qué importa | Acción |
| --- | --- | --- | --- |
| 9 | Tu motor de ABM pide `tabla_estado_registro_id` en cada tabla registrada en `conf__tablas`; unas 20 tablas del DBML (relaciones puras) no lo tienen | El motor no podría operarlas | Decidir si se agrega a todas o si las relaciones se registran de otra forma |
| 10 | Tu regla "toda consulta filtra por empresa" pide `empresa_id` en las tablas operativas; faltan en `ml__ciclos_tareas`, `ml__evidencias`, `ml__alertas`, `ml__incidencias`, `ml__excepciones` y las tres tablas RACI de ciclos y tareas | Hoy se filtraría solo por cruces | Agregar `empresa_id` o aceptar el cruce de forma explícita |
| 11 | `conf__tablas` pide un tipo de tabla (diccionario, proceso corto o proceso largo) para cada una; el DBML no lo asigna | Sin tipo no hay estados ni funciones estándar | Clasificar las 69 tablas antes de registrarlas |
| 12 | Los campos de auditoría que dice el DBML (`creado_por`, `creado_en`, `modificado_por`, `modificado_en`) vienen de los borradores; tu DDL real usa `actualizado_en` | Auditoría inconsistente entre `conf__*` y `ml__*` | Usar los campos reales; verificar con la consulta 7 |
| 13 | Los nombres `conf__empresas`, `conf__usuarios` y `conf__perfiles` son supuestos | Si difieren, rompen todas las claves hacia ellas | Verificar con las consultas 1 y 2 |
| 14 | La columna de empresa puede llamarse `empresa_id` o `empresa_idx` según la tabla | El DBML usa `empresa_id` en todas partes | Verificar con la consulta 4 |

## Consultas para verificar contra la base real

Correrlas en tu base y pegar los resultados al pedir la revisión.

```sql
-- 1. Tablas conf__ existentes
SELECT table_name
FROM information_schema.tables
WHERE table_schema = DATABASE() AND table_name LIKE 'conf\_\_%'
ORDER BY table_name;

-- 2. Columnas de las tablas que el DBML referencia o reutiliza
SELECT table_name, column_name, column_type, is_nullable, column_default
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND table_name IN ('conf__empresas','conf__usuarios','conf__perfiles',
                     'conf__registros_estados','conf__tablas',
                     'conf__tablas_estados_registros')
ORDER BY table_name, ordinal_position;

-- 3. Tipo real de la columna de estado en todo el esquema
SELECT column_type, COUNT(*) AS cantidad
FROM information_schema.columns
WHERE table_schema = DATABASE() AND column_name = 'tabla_estado_registro_id'
GROUP BY column_type;

-- 4. Cómo se llama la columna de empresa
SELECT column_name, COUNT(*) AS cantidad
FROM information_schema.columns
WHERE table_schema = DATABASE() AND column_name LIKE '%empresa%'
GROUP BY column_name
ORDER BY cantidad DESC;

-- 5. Versión del motor
SELECT VERSION();

-- 6. Motor y juego de caracteres en uso
SELECT engine, table_collation, COUNT(*) AS cantidad
FROM information_schema.tables
WHERE table_schema = DATABASE()
GROUP BY engine, table_collation;

-- 7. Campos de auditoría que usan tus tablas
SELECT column_name, COUNT(*) AS cantidad
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND column_name IN ('creado_en','creado_por','actualizado_en','modificado_en','modificado_por')
GROUP BY column_name;
```

## Orden recomendado

1. Correr las siete consultas y guardar los resultados.
2. Pedir la revisión a `revisor-convenciones` (prompt A).
3. Pedir la revisión a `revisor-sql` (prompt B).
4. Integrar todo en `04-modelo-de-datos.dbml` v0.2, con los tipos, los índices y las claves reales.

## Prompt A: `revisor-convenciones`

> Revisá `_local/docs/matriz-legal/04-modelo-de-datos.dbml` contra las convenciones de gestion_multipyme. Usá los resultados de las consultas de `05-revision-y-verificacion.md` que te pego abajo.
>
> Verificá en particular: nombres y columnas reales de `conf__empresas`, `conf__usuarios` y `conf__perfiles`; tipo real de `tabla_estado_registro_id`; nombre de la columna de empresa; campos de auditoría reales; y qué tablas del DBML necesitan estado y `empresa_id` para que funcione el motor de ABM.
>
> Además proponé la clasificación de las 69 tablas `ml__*` en diccionario, proceso corto y proceso largo, para registrarlas en `conf__tablas`. Devolvé una lista priorizada de correcciones. No cambies la lógica: las reglas están en `01-logica-del-proceso.md`.
>
> Resultados de las consultas: [pegar aquí]

## Prompt B: `revisor-sql`

> Revisá `_local/docs/matriz-legal/04-modelo-de-datos.dbml` desde el punto de vista de SQL para la versión de motor que indican los resultados que te pego abajo.
>
> Revisá: tipos de datos y su coherencia con `conf__*`; motor y juego de caracteres; índices para las consultas de vigencia, vencimiento y alertas; unicidades de negocio; cómo hacer cumplir las reglas de vigencia que MySQL no impone (una sola pertenencia principal vigente, un solo titular vigente por entidad, vigencias que no se solapan); las referencias polimórficas; y el CHECK para el ámbito.
>
> Devolvé una lista priorizada de hallazgos y el DBML corregido como v0.2. No cambies la lógica: las reglas están en `01-logica-del-proceso.md`.
>
> Resultados de las consultas: [pegar aquí]
