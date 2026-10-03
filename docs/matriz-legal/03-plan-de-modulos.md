# Plan de traspaso a Claude Code – Matriz legal y corporativa del grupo

## Material del repositorio

| Archivo | Contenido |
| --- | --- |
| `01-logica-del-proceso.md` | Fuente de verdad: la lógica completa, 16 secciones. Se exporta del documento "Lógica del proceso" como Markdown. Los cuatro dibujos no se exportan; el texto cubre lo mismo. |
| `02-casos-de-aceptacion.md` | 36 casos en formato Dado / Cuando / Entonces, más las pruebas de las diez opciones configurables. |
| `03-plan-de-modulos.md` | Este archivo: orden de trabajo, agentes y dependencias. |

## Reglas que no se negocian

Están en la sección 9 del documento principal. Ningún módulo puede romperlas.

1. La norma origina obligaciones; nunca genera tareas directamente.
2. El ámbito de una norma se declara una vez y se hereda; nada se asigna entidad por entidad.
3. La obligación definida pertenece a la norma; a cada entidad se le asigna una obligación asignada, y de esa cuelgan los ciclos.
4. El ciclo es la unidad auditable: no se edita históricamente y se corrige con un ciclo complementario.
5. Las tareas no existen sin ciclo.
6. Toda relación lleva fecha de inicio y de fin; nada se borra, se cierra con fecha.
7. Los estados salen de un catálogo, nunca del código.
8. Lo futuro se recalcula y lo pasado no se toca.
9. El huso horario de un plazo es el del país de la entidad.

## Antes de escribir código

1. **Validación del área legal.** Usar el documento "Resumen para validación legal". Bloquea el módulo M5 y las partes de M6 que dependen de derogaciones y excepciones.
2. **Fase 0: modelo de datos.** Reconciliar el modelo del primer documento con esta lógica y escribir el DBML o el SQL unificado. Pasarlo por `revisor-sql` y `revisor-convenciones` antes de generar código.
3. **Nombres de configuración.** Los borradores anteriores usaron nombres distintos para las tablas de configuración (`conf__*`). Usar los de la base real de gestion_multipyme.
4. **Casos reales.** Contrastar las reglas con cinco o seis casos de la operación antes de congelar el modelo.

## Módulos y orden

| # | Módulo | Alcance | Agente | Depende de | Casos |
| --- | --- | --- | --- | --- | --- |
| M1 | Territorio y grupo | Árbol territorial de profundidad libre, pertenencias con vigencia, regiones del grupo, empresas, entidades (físicas, lógicas, móviles y raíz), cambios societarios | `disenador-proceso` | Fase 0 | C03, C04, C06, C08, C13 a C17 |
| M2 | Catálogos, estados, perfiles y configuración | Tipos de entidad y de norma, 8 estados de ciclo y 7 de tarea, perfiles con ámbito múltiple y delegaciones, las 10 opciones configurables | `disenador-proceso` | M1 | C09, C32, C33, C34 |
| M3 | Normas y soporte | Normas con ámbito y tipo, relaciones, versiones y documentos, procedimientos e instrucciones, confidencialidad, idioma | `disenador-proceso` | M1, M2 | C02, C10, C12, C18, C19, C21, C33 |
| M4 | Obligaciones y aplicabilidad | Obligación definida, tipos de entidad, jurisdicción, condiciones, reglas de vencimiento, periodicidad, dependencias entre obligaciones, plantillas de tareas | `disenador-proceso` | M3 | C07, C10, C20 a C24, C26 |
| M5 | Motor de herencia | Cálculo por herencia, consolidación de solapamientos, obligaciones asignadas, excepciones, normas incompatibles | Cadena completa: escribir la palabra clave **GRANDE** | M1 a M4 y validación legal | C01, C05, C06, C08, C09, C11, C19, C20, C25, C35 |
| M6 | Ciclos y tareas | Generación a doce meses, dependencias (Y y por resultado), cambios a mitad de período, derogación, ciclos complementarios y de seguimiento | `disenador-proceso`; evaluar GRANDE por tamaño | M5 | C02 a C05, C07, C10, C12, C14, C16, C22 a C25, C28 a C31 |
| M7 | Ejecución y seguimiento | Evidencias con validez, alertas, RACI, reasignación, indicador de cumplimiento, tableros consolidados | `disenador-proceso` | M6 | C16, C17, C27, C30, C31, C34 |
| M8 | Cargas y cambios masivos | Plantillas de carga, vista previa de impacto, aprobación | `disenador-proceso` | M5, M6 | C35, C36 |

## Pasos dentro de cada módulo

1. Entregar al agente la sección del documento que corresponde y los casos de aceptación del módulo.
2. Pedir primero el diseño de tablas del módulo y revisarlo antes de generar código.
3. Generar el código.
4. Correr `revisor-sql`, `revisor-ajax`, `revisor-convenciones` y `revisor-casos-borde` (con los casos del módulo).
5. Cuando el módulo pasa los casos, correr `doc-tecnica` y `manual-usuario`.

## Qué mirar en cada revisión

- **Estados:** ninguno escrito en el código; todos desde catálogo.
- **Vigencia:** toda relación nueva lleva fecha de inicio y de fin.
- **Opciones configurables:** el valor por defecto coincide con lo decidido, y la alternativa tiene su prueba.
- **Ámbito:** los permisos combinan lo que el usuario puede hacer y sobre qué ámbito.

## Pendientes que condicionan

- Validación del área legal.
- Contraste con casos reales de la operación.
- Un solo punto sigue marcado "a confirmar" en el documento: que los tipos de norma los mantiene solo el grupo. Conviene confirmarlo antes de M2.
