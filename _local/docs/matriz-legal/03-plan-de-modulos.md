# Plan de traspaso a Claude Code – Matriz legal y corporativa del grupo

Versión 3. Incorpora `06-hallazgos-del-proyecto.md`, el diseño de M0 (`08-diseno-m0.md`) y las decisiones tomadas sobre él.

## Material del repositorio

| Archivo | Contenido |
| --- | --- |
| `01-logica-del-proceso.md` | Fuente de verdad: la lógica completa, 16 secciones. Los cuatro dibujos no se exportan; el texto cubre lo mismo. |
| `02-casos-de-aceptacion.md` | 36 casos en formato Dado / Cuando / Entonces, más las pruebas de las diez opciones configurables. |
| `03-plan-de-modulos.md` | Este archivo: orden de trabajo, agentes y dependencias. |
| `04-modelo-de-datos.dbml` | Modelo de datos v0.3: 69 tablas `ml__` y 4 tablas existentes de referencia. La versión figura en el encabezado del archivo. |
| `05-revision-y-verificacion.md` | Revisión previa del modelo v0.1 y consultas de verificación (históricas). |
| `06-hallazgos-del-proyecto.md` | Exploración del repositorio: nombres reales, tipos, convenciones y estado del prototipo. |
| `07-clasificacion-de-tablas.md` | Clasificación de las 69 tablas, estados nuevos y bloques de ids. Versión 2. |
| `08-diseno-m0.md` | Diseño del módulo de instalación M0: orden, verificaciones, perfiles, limpieza y pruebas. |

## Decisiones tomadas

1. **Prototipo `ml__` existente:** se reemplaza. Sus 22 filas semilla se descartan (no coinciden con el modelo nuevo) y quedan en el respaldo. El criterio para continuar es "ninguna fila fuera de esas 22", medido con `COUNT(*)`, no con `table_rows`.
2. **`tabla_estado_registro_id`** (`smallint`) guarda el id genérico de `conf__estados_registros`, como el resto del código. Sin `DEFAULT` en las tablas de proceso.
3. **Estados:** 12 genéricos nuevos (ids 16 a 27), con "Con error" propio. `valor_estandar` desde 200.
4. **Marca de estado final:** columna `es_final` en `conf__tablas_estados_registros`, por tabla.
5. **Tablas de diccionario:** estado solo en los maestros y los catálogos con ABM propio (20 tablas). El resto se registra sin estados.
6. **Auditoría:** `creado_en`, `creado_por`, `actualizado_en`, `actualizado_por`, con la convención del repositorio para `actualizado_en`.
7. **Ids reservados** para el módulo 3, iguales en los dos entornos (ver `07`).
8. **Perfiles:** 8 perfiles base (301 a 308). Los de matriz (301, 302, 303 y 307) solo a la empresa titular, que es la 2 (Casa Lucho); los otros cuatro a las empresas 2 y 3.
9. **Un solo módulo** (el 3) para matriz regional y empresa, con perfiles y páginas distintos. Se reutilizan las páginas 63 y 64. No se toca `layout_nombre`.
10. **Fechas y plazos:** `DATETIME` en UTC, con el huso en `ml__territorios`; `ml__ciclos` usa `DATETIME` para inicio, vencimiento y cierre.
11. **Reglas de vigencia:** "una sola abierta" con columna generada `STORED` y `UNIQUE`; no solapamiento en triggers o en la aplicación, dentro de cada módulo.
12. **FK hacia `conf__empresas`, `conf__usuarios` y `conf__empresas_perfiles`:** se declaran. Antes hay que confirmar que `conf/empresas_model.php:90` muestra un mensaje claro cuando el borrado falla.
13. **Cliente:** los scripts se corren con `mysql` por línea de comandos, que aborta en el primer error.
14. **`modules/ml/`:** lo que sobra se borra en un commit aparte, con confirmación.

## Reglas que no se negocian

Las nueve primeras están en la sección 9 del documento principal. Ningún módulo puede romperlas.

1. La norma origina obligaciones; nunca genera tareas directamente.
2. El ámbito de una norma se declara una vez y se hereda; nada se asigna entidad por entidad.
3. La obligación definida pertenece a la norma; a cada entidad se le asigna una obligación asignada, y de esa cuelgan los ciclos.
4. El ciclo es la unidad auditable: no se edita históricamente y se corrige con un ciclo complementario.
5. Las tareas no existen sin ciclo.
6. Toda relación lleva fecha de inicio y de fin; nada se borra, se cierra con fecha.
7. Los estados salen de un catálogo, nunca del código.
8. Lo futuro se recalcula y lo pasado no se toca.
9. El huso horario de un plazo es el del país de la entidad.

Reglas técnicas que salen del repositorio:

10. **Multiempresa:** toda tabla operativa lleva `empresa_id` (`smallint unsigned`) y todo SQL filtra por él.
11. **Seguridad del AJAX:** la empresa sale de la sesión y se valida con `usuario_tiene_acceso_pagina`; nunca llega por parámetro con un valor por defecto. El modelo a copiar es `arca_integraciones_ajax.php:24-57`. **No copiar** `depositos_ajax.php`.
12. **Dos motores:** producción es MySQL 8.0.46 y el entorno local es MariaDB 10.4.22. Todo script corre en los dos. Sin `ADD INDEX IF NOT EXISTS`, sin columnas `JSON` y sin `DEFAULT` en columnas `TEXT`.
13. **Juego de caracteres:** `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`. No copiar el `utf8mb4_general_ci` del script `20261001_arca_integraciones.sql`. Ojo con cruzar texto contra tablas `conf__` en `latin1` o `utf8mb3_spanish2_ci`.
14. **Nombres:** tabla hasta 50 caracteres (`conf__tablas.tabla_nombre`); `CHECK` y `FK` con nombre único en todo el esquema.
15. **DDL no transaccional:** `START TRANSACTION … COMMIT` protege solo los `INSERT`, `UPDATE` y `DELETE`. Los scripts de DDL tienen que poder reanudarse.

## Antes de ejecutar M0

1. **Destino.** La empresa titular del grupo es la 2 (Casa Lucho). La base de destino es `gestion_multipyme` (local, MariaDB); producción (`ps_gestion_casalucho`, en otro servidor) va después de pasar las pruebas.
2. **Verificación.** Correr el script `00` (solo lectura) en la base de prueba y en producción, y revisar V0 y V2.
3. **Ventana de mantenimiento** y quién ejecuta con privilegios de DDL en producción.
4. **Entorno de prueba.** Un esquema de prueba en el servidor de producción (MySQL 8) y otro local en MariaDB 10.4. Probar la secuencia completa, el rollback y la reanudación en los dos.
5. **Seguridad de `modules/ml/`.** Tratar aparte, y primero, los endpoints sin sesión (ver más abajo).

## Módulos y orden

| # | Módulo | Alcance | Tablas | Agente | Depende de | Casos |
| --- | --- | --- | --- | --- | --- | --- |
| M0 | Instalación del módulo | Ver abajo | — | `disenador-proceso` | Verificación de la base | — |
| M1 | Territorio y grupo | Árbol territorial de profundidad libre, pertenencias con vigencia, regiones del grupo, empresas, entidades (físicas, lógicas, móviles y raíz), cambios societarios | 17 | `disenador-proceso` | M0 | C03, C04, C06, C08, C13 a C17 |
| M2 | Catálogos, estados, perfiles y configuración | Tipos de entidad y de norma, ámbitos, perfiles con ámbito múltiple y delegaciones, las 10 opciones configurables, RACI | 7 | `disenador-proceso` | M1 | C09, C32, C33, C34 |
| M3 | Normas y soporte | Normas con ámbito y tipo, relaciones, versiones, procedimientos e instrucciones, confidencialidad, idioma | 13 | `disenador-proceso` | M1, M2 | C02, C10, C12, C18, C19, C21, C33 |
| M4 | Obligaciones y aplicabilidad | Obligación definida, tipos de entidad, jurisdicción, condiciones, reglas de vencimiento, periodicidad, dependencias entre obligaciones, plantillas de tareas | 10 | `disenador-proceso` | M3 | C07, C10, C20 a C24, C26 |
| M5 | Motor de herencia | Cálculo por herencia, consolidación de solapamientos, obligaciones asignadas, excepciones, normas incompatibles | 4 | Cadena completa: escribir la palabra clave **GRANDE** | M1 a M4 y validación legal | C01, C05, C06, C08, C09, C11, C19, C20, C25, C35 |
| M6 | Ciclos y tareas | Generación a doce meses, dependencias (Y y por resultado), cambios a mitad de período, derogación, ciclos complementarios y de seguimiento | 8 | `disenador-proceso`; evaluar GRANDE por tamaño | M5 | C02 a C05, C07, C10, C12, C14, C16, C22 a C25, C28 a C31 |
| M7 | Ejecución y seguimiento | Evidencias con validez, alertas, reasignación, indicador de cumplimiento, tableros consolidados | 4 | `disenador-proceso` | M6 | C16, C17, C27, C30, C31, C34 |
| M8 | Cargas y cambios masivos | Plantillas de carga, vista previa de impacto, aprobación | 5 | `disenador-proceso` | M5, M6 | C35, C36 |

Además hay una tabla transversal, `ml__log_cambios`, que se crea en M0. Total: 69 tablas.

## M0: instalación del módulo

El módulo 3 ya existe como "Matriz Legal" (`modulo_url = 'ml'`), habilitado en las empresas 2 y 3, sin perfiles ni código. M0 lo deja listo. El diseño completo, con consultas y referencias, está en `08-diseno-m0.md`.

**Scripts** en `_local/sql/mantenimiento/`, con fecha como prefijo:

| Archivo | Contenido |
| --- | --- |
| `…_ml_m0_00_verificacion.sql` | Solo lectura: entorno, inventario del prototipo, `COUNT(*)` de datos, dependencias, referencias en `conf__`, ids libres |
| `…_ml_m0_01_baja_prototipo.sql` | Respaldo (pasos manuales), baja de las filas `conf__` en transacción y `DROP` de las 23 tablas en orden de FK |
| `…_ml_m0_02_ddl_tablas.sql` | Las 69 tablas, primero sin FK y después las FK, con helpers que consultan `information_schema` |
| `…_ml_m0_03_estados_y_tablas.sql` | `es_final`, los 12 estados, las 69 filas de `conf__tablas` y sus estados |
| `…_ml_m0_04_perfiles.sql` | Los 8 perfiles base y su copia a las empresas |
| `…_ml_m0_05_verificacion_final.sql` | Controles posteriores |
| `…_ml_m0_99_rollback.sql` | Reversión; se corre solo a mano |

**Orden:** verificación → respaldo → baja de filas `conf__` → baja física → tablas nuevas → estados y registro → perfiles → verificación final. La baja de las filas `conf__` va antes que los `DROP` porque es transaccional y verificable.

**Perfiles base** (ids 301 a 308): Administrador de matriz, Gestor normativo, Área legal, Responsable de empresa, Responsable de tareas, Revisor y aprobador, Auditor y Consulta. El responsable regional y el responsable legal de un país hacen lo mismo con distinto ámbito, así que no necesitan perfiles distintos.

**Después de M0** (`modules/ml/` y permisos):

- `copiarPerfilesModulo` no propaga las funciones que se asignen después. Cada script de M1 a M8 tiene que dar de alta los permisos en `conf__perfiles_funciones` y en `conf__empresas_perfiles_funciones`.
- No usar `heredarPaginasFuncionesDesdePerfilBase`: asigna todas las funciones del módulo a cualquier perfil.
- `modules/ml/` es una copia congelada de diciembre de 2025. Lo que sobra se borra en un commit aparte; en M1 se pone `$modudo_idx = 3` en `ml/index.php` y se arma la portada.

## Seguridad: urgente y aparte de la matriz

30 de los 31 `*_ajax.php` de `modules/ml/` no validan sesión. Si en un servidor existe `modules/ml/conexion.php`, `ml/usuarios_ajax.php` devuelve usuarios con sus hashes sin login. Las copias idénticas de `gestion/` y `conf/` tienen el mismo problema. Hay que revisarlo y corregirlo antes de instalar la matriz, y por separado.

## Cómo se agrega una página de un módulo

Aplica a M1 a M8. Las páginas viven en `modules/ml/`, porque la carpeta sale de `modulo_url = 'ml'`.

1. Script de mantenimiento con el alta en `conf__paginas` (ids 300 a 399) y `conf__paginas_funciones` (ids 3001 a 3999).
2. Permisos en `conf__perfiles_funciones` y en `conf__empresas_perfiles_funciones` de las empresas 2 y 3.
3. Cuatro archivos por página: `x.php`, `x_ajax.php`, `x_model.php` y `x.js`, con `$modudo_idx = 3` y `$pagina_idx` igual al id de `conf__paginas`.
4. Plantillas: `gestion/depositos.*` para diccionarios y `gestion/facturas_proveedores.*` para procesos con estados y detalle. La seguridad del AJAX se copia de `arca_integraciones_ajax.php`.
5. No hace falta tocar `header1.php` ni `core/permisos.php`.

## Pasos dentro de cada módulo

1. Entregar al agente la sección del documento que corresponde, los casos de aceptación del módulo y las tablas del módulo en el DBML.
2. Pedir primero el diseño de tablas y páginas del módulo y revisarlo antes de generar código.
3. Generar el código.
4. Correr `revisor-sql`, `revisor-ajax`, `revisor-convenciones` y `revisor-casos-borde` (con los casos del módulo).
5. Cuando el módulo pasa los casos, correr `doc-tecnica` y `manual-usuario`.

## Qué mirar en cada revisión

- **Estados:** ninguno escrito en el código; todos desde el catálogo, con el id genérico de `conf__estados_registros`. El código fija siempre el estado inicial en las tablas de proceso.
- **Vigencia:** toda relación nueva lleva fecha de inicio y de fin.
- **Multiempresa:** `empresa_id` en la tabla y en cada consulta; la empresa sale de la sesión.
- **Motores:** nada de sintaxis exclusiva de MariaDB ni de MySQL.
- **Fechas:** plazos en `DATETIME` UTC, calculados con el huso del país de la entidad.
- **Reglas que MySQL no impone:** no solapamiento de vigencias, en trigger o en la aplicación.
- **Opciones configurables:** el valor por defecto coincide con lo decidido, y la alternativa tiene su prueba.
- **Ámbito:** los permisos combinan lo que el usuario puede hacer y sobre qué ámbito.

## Pendientes que condicionan

- Resultado de V0 y V2 en la base de prueba y en producción.
- Ventana de mantenimiento y acceso con privilegios de DDL en producción (la producción está en otro servidor).
- Confirmar la lista de diccionarios con estado (`07`).
- Corregir en `01-logica-del-proceso.md` que los perfiles base son por módulo (`conf__perfiles`) y se copian a cada empresa; no "parten de la empresa titular".
- Corregir `CLAUDE.md` en lo que dice sobre `tabla_estado_registro_id` (texto propuesto en `08`).
- Validación del área legal; bloquea M5 y parte de M6.
- Contraste con casos reales de la operación.
- Confirmar que los tipos de norma los mantiene solo el grupo.
