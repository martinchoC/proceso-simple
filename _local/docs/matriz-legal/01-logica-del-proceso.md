# Lógica del proceso – Matriz legal, normativa y corporativa del grupo

Oct 2, 2026 · @Pablo Passoni

## 1. Propósito y alcance

El sistema es una matriz legal, normativa y corporativa para un **grupo de empresas** que se gestiona de manera regional. Responde una pregunta que hoy se resuelve a mano: *qué normas y políticas aplican a cada empresa y a cada cosa que controla, qué hay que hacer por ello, quién lo hace y cómo se prueba.*

La normativa que debe contemplar llega desde cuatro orígenes:

- **Mundial:** convenios y estándares internacionales que valen para todo el grupo.
- **Regional:** normas de una región o de una subregión, que valen para los países que la integran.
- **País:** leyes, decretos y resoluciones del país donde opera cada empresa.
- **Corporativa:** políticas que define el propio grupo o una empresa.

Este documento fija la **lógica del proceso**: cómo se organizan el grupo y el territorio, cómo se decide qué aplica a quién, cómo se pasa del requisito a la ejecución y quién administra cada parte. No define tablas ni pantallas; eso viene después, a partir de este entendimiento.

## 2. Estructura del grupo y del territorio

El sistema trabaja con dos jerarquías independientes que se cruzan en la empresa y en la entidad.

**Territorio.** Es un árbol de profundidad libre: cada nodo tiene un tipo (mundo, región, subregión, país, provincia, estado o localidad) y cuelga de un nodo superior. El dibujo muestra los niveles habituales. Un país puede pertenecer a más de una región o subregión, por ejemplo a distintos bloques: esas pertenencias sirven para aplicar normas, y una de ellas es la principal para consolidar reportes.

**Grupo.** El grupo agrupa empresas, y cada empresa opera en un país. Las regiones de gestión del grupo son independientes de las regiones geográficas, porque la gestión puede agrupar países de otra forma. La estructura del grupo tiene tres niveles: grupo, región del grupo y empresa. La entidad es todo lo que se controla, física (un tanque, un vehículo) o lógica; pertenece a una empresa y se ubica en un lugar del territorio. Cada empresa tiene además una entidad lógica raíz que la representa, y a ella aplican las obligaciones de la empresa misma. Una entidad móvil, como un buque o un camión, tiene una ubicación base y declara los países donde opera: le aplican las normas de la base y de cada país de operación. Las entidades pueden ser jerárquicas, como un tanque dentro de una terminal, pero la jerarquía solo agrupa y consolida: cada entidad recibe únicamente las obligaciones de su tipo.

> *[Dibujo no exportable: "territorio y grupo · dos jerarquías unidas por empresa y entidad". El texto de arriba dice lo mismo.]*

La ubicación de la empresa y de la entidad es lo que permite heredar normas: se explica en la sección 4.

## 3. Fuentes normativas por alcance

Toda norma declara su **ámbito de aplicación** una sola vez: un punto del territorio o un punto de la estructura corporativa. Desde ahí alcanza a todo lo que cuelga de ese punto.

| Alcance | Ejemplos | A quién aplica |
| --- | --- | --- |
| Mundial | Convenios internacionales y estándares como ISO 9001 o ISO 14001 | A todas las empresas y entidades del grupo |
| Regional | Normas de una región o de una subregión | A todos los países de esa región o subregión |
| País | Leyes, decretos y resoluciones, como la ley 19587 y el decreto 351/79 de Argentina | A las empresas y entidades del país |
| Local | Normas de una provincia, estado o localidad | A las entidades ubicadas ahí |
| Corporativo | Código de ética y política HSSE | A todo el grupo, a una región del grupo o a una empresa |

**Tipo de norma.** Además del alcance, cada norma tiene un tipo que gobierna su comportamiento: legal, regulatoria, convenio o contrato, política corporativa, o auditoría y estándar. Los procedimientos internos no son normas: son soporte que guía las tareas. Todas son el mismo concepto; no hay un tratamiento aparte para políticas o auditorías.

**Relaciones entre normas.** Una norma puede modificar, reglamentar, derogar, complementar, reemplazar, derivar de otra o verificarla, y esas relaciones cruzan niveles. Por ejemplo, una política del grupo deriva de un estándar mundial, y un procedimiento local, que es soporte y no norma, se vincula a esa política.

**Reglas sobre normas (decisiones confirmadas)**

- **Granularidad:** la obligación se liga a la norma completa, con referencia opcional al artículo.
- **Normas incompatibles:** cuando una prohíbe lo que otra exige, se marca el conflicto, se escala al área legal y su decisión queda registrada.
- **Aplicación gradual:** cada obligación puede tener vigencias distintas por tipo o tamaño de entidad.
- **Norma regional que requiere norma local:** aplica de inmediato, salvo que se marque como "requiere norma local"; en ese caso sus obligaciones se activan cuando se carga y vincula la norma local.

**Idioma.** Cada norma se carga con su texto en el idioma original y, de manera opcional, un título traducido.

## 4. Qué normas aplican a cada entidad

Una norma aplica a una entidad por herencia: alcanza todo lo que cuelga del punto donde está declarada. Ninguna norma se asigna entidad por entidad.

> *[Dibujo no exportable: "herencia de normas · dos árboles que convergen en la entidad".]*

**Pasos para determinar lo que aplica a una entidad**

1. Ubicar la entidad: su empresa, su país y su lugar dentro del país; si es móvil, su base y los países donde opera.
2. Reunir las normas de todos los niveles del territorio que la contienen: mundo, región, subregión, país y división interna.
3. Sumar las normas corporativas del grupo, de su región del grupo y de su empresa.
4. Quedarse con las obligaciones que aplican a su tipo de entidad.
5. Aplicar las condiciones adicionales, si las hay (por ejemplo, facturación o cantidad de empleados).
6. Resolver los solapamientos entre niveles.

**Ejemplo ilustrativo.** Un tanque de una empresa que opera en Argentina hereda un estándar mundial como la ISO 14001, la ley 19587 del país y la política HSSE del grupo, y recibe solo las obligaciones definidas para ese tipo de entidad.

**Solapamientos (decisión confirmada).** Se cumplen todas las normas que aplican. Cuando dos niveles exigen lo mismo, se genera una sola obligación con la exigencia más estricta y se conserva el origen de cada norma para la auditoría. Una política corporativa puede ser más exigente que la ley, pero nunca menos. Si las normas son incompatibles y no solo más o menos exigentes, se aplica la regla de conflicto de la sección 3.

## 5. Del requisito a la ejecución

Una norma no se cumple: se cumplen obligaciones. El proceso completo es una cadena en la que cada paso alimenta al siguiente.

1. **Cargar la norma** con su tipo y su ámbito de aplicación (secciones 3 y 4).
2. **Derivar obligaciones.** Cada obligación dice qué hay que cumplir, cada cuánto o ante qué evento, a qué tipos de entidad aplica y bajo qué condiciones.
3. **Asignar a las entidades.** Al dar de alta una entidad, o al cambiar su ubicación o su tipo, el sistema calcula lo que le aplica por herencia y crea, por cada obligación aplicable, la obligación asignada a esa entidad, con fecha de inicio y de fin, solapamientos consolidados y excepciones; de ella cuelgan los ciclos.
4. **Generar ciclos.** Una obligación repetitiva genera un ciclo por período, hasta doce meses hacia adelante con una ventana móvil; una obligación por evento genera un ciclo cuando un usuario con permiso registra el evento, y su plazo se mide en horas o días desde ese momento. Feriados y prórrogas pueden mover el vencimiento; los feriados salen de un calendario por país, con ajuste local, y los plazos se miden con el huso horario del país de la entidad. La fecha de vencimiento la calcula la regla que declara cada obligación.
5. **Generar tareas.** Cada ciclo crea sus tareas a partir de las plantillas de la obligación; la norma nunca genera tareas directamente. Las tareas pueden depender unas de otras: una se activa cuando se cumplen todas las que la preceden. Los procedimientos, las instrucciones y los documentos vigentes guían y respaldan la ejecución.
6. **Ejecutar y probar.** El responsable ejecuta la tarea y adjunta evidencias; un revisor aprueba o rechaza.
7. **Cerrar el ciclo** con uno de sus estados finales: cumplido (con comprobante, si corresponde), cumplido fuera de plazo, incumplido, eximido o cancelado.
8. **Alertar y escalar.** Antes del vencimiento se avisa por correo y dentro del sistema al responsable y, si no hay avance, a su superior, con plazos ajustables por obligación; tras el vencimiento se abre una incidencia.

**El ciclo es la unidad auditable.** No se edita históricamente: cada período es un ciclo nuevo, y cada ciclo conserva la norma, la versión de documentos y las evidencias con las que se cumplió.

**Corrección y calidad (decisiones confirmadas)**

- **Error en un ciclo cerrado:** se corrige con un ciclo complementario enlazado al original, que queda intacto.
- **Hallazgo de auditoría:** el plan de acción es un ciclo de seguimiento enlazado al ciclo de origen, con sus propias tareas.
- **Evidencia con vencimiento,** como un certificado: lleva fecha de validez, avisa al acercarse y puede disparar el ciclo de renovación.

**Responsables.** Se definen según el modelo RACI (responsable, aprobador, consultado e informado) para cada obligación, ciclo o tarea.

## 6. Vida de una norma

Las normas cambian: se publican, se modifican, se derogan. El sistema debe absorber esos cambios sin romper lo ya cumplido. La regla común es que **lo futuro se recalcula y lo pasado no se toca**.

| Qué ocurre | Qué se recalcula | Qué se conserva |
| --- | --- | --- |
| Se publica una norma nueva en un ámbito | Sus obligaciones se asignan a todas las entidades que cuelgan de ese ámbito | Los ciclos ya cerrados |
| Se modifica una norma (nuevo documento sobre la misma norma) | Se revisan sus obligaciones y los ciclos futuros usan la versión nueva | Los ciclos ya generados mantienen la versión con la que nacieron |
| Se deroga o reemplaza una norma | Sus obligaciones terminan con la fecha de derogación o pasan a las de la norma que la reemplaza | El historial de ciclos cumplidos |
| Una entidad cambia de lugar, de empresa o de tipo | Se reevalúa lo que le aplica y se abre la asignación nueva | El historial de asignaciones anteriores |
| Se da de alta una entidad | Hereda todo lo vigente en su ubicación y su estructura | No corresponde |

**Estados.** Una norma pasa de vigente a derogada; su documento, de borrador a publicado y luego obsoleto; sus obligaciones pueden estar activas o suspendidas. Los estados salen de un catálogo, no del código.

**Reglas de tiempo (decisiones confirmadas)**

- **Cambio a mitad de período:** si una empresa se suma o una entidad cambia de país, de empresa o de tipo, el ciclo en curso se cierra con lo que aplicaba y se abre uno nuevo desde la fecha del cambio.
- **Derogación:** los ciclos futuros se cancelan y los abiertos se cierran como eximidos con la fecha de derogación.
- **Vigencia:** toda norma, obligación y excepción tiene fecha de inicio y de fin, y los ciclos se generan solo dentro de esa vigencia.
- **Excepciones:** llevan motivo y fecha de vencimiento; al vencer, la obligación vuelve a regir.

**Salida de una empresa del grupo (decisión confirmada):** queda inactiva desde la fecha de salida, sus ciclos abiertos se cierran como eximidos y la matriz conserva su historial en solo lectura.

**Cambios societarios (decisiones confirmadas)**

- **Fusión:** la empresa absorbida queda inactiva con la fecha de fusión, sus entidades pasan a la absorbente con una asignación nueva y su historial queda en solo lectura.
- **Cambio de razón social:** es la misma empresa; el nombre anterior se guarda con sus fechas.
- **Escisión:** se crean empresas nuevas y las entidades se transfieren.
- **Entidad compartida:** una empresa titular responde por el cumplimiento y las otras la ven vinculada, en solo lectura.

## 7. Gobierno: quién administra qué

Como el grupo se gestiona de manera regional, cada nivel de normas tiene un responsable que lo mantiene, y cada persona ve solo lo que corresponde a su ámbito.

| Nivel | Quién lo administra | Qué ve |
| --- | --- | --- |
| Mundial y corporativo del grupo | Equipo corporativo del grupo | Todo el grupo |
| Regional y subregional | Responsable regional | Todos los países de su región |
| País y local | Responsable legal de cada país | Las empresas y entidades de su país |
| Empresa | Responsable de la empresa | Su empresa y sus entidades |
| Ejecución de tareas | Responsables, revisores y aprobadores asignados | Las tareas que les tocan |

Los permisos combinan dos cosas: **qué puede hacer** el usuario (crear, editar, aprobar, publicar) y **sobre qué ámbito** puede hacerlo (un país, una región geográfica, una región del grupo o una empresa). Se reutiliza el módulo de perfiles y accesos de gestion_multipyme: los perfiles parten de `conf__perfiles` de la empresa titular del sistema, cada empresa puede crear los suyos, y a cada perfil se le agrega el ámbito.

**Consolidación.** Cada nivel ve el cumplimiento de todo lo que hay debajo: un responsable regional ve el avance de sus países, y el grupo ve el de todas las regiones.

## 8. Módulos: matriz regional y módulo de empresa

El sistema se ofrece como dos tipos de módulo que comparten el mismo modelo de datos y la misma estructura de perfiles y accesos: cambian las páginas y los permisos.

> *[Dibujo no exportable: "dos tipos de módulo · matriz regional y empresas, sobre una misma base".]*

**Matriz regional (módulo del grupo).** Es donde se define y se consolida. Administra:

- El territorio y la estructura del grupo (regiones y empresas).
- Las normas mundiales, regionales, de país y locales, y las políticas del grupo y de la región.
- Las obligaciones derivadas de esas normas y su aplicabilidad.
- Los tableros de cumplimiento de todo el grupo.

Lo usan el equipo corporativo, los responsables regionales y los responsables legales de cada país (sección 7).

**Módulo de empresa.** Es donde cada empresa gestiona lo suyo:

- Sus entidades.
- Sus políticas y normas internas, obligaciones propias, reglas y procedimientos.
- La ejecución: ciclos, tareas y evidencias.
- Su propio tablero.

La empresa ve, en solo lectura, lo que hereda de la matriz regional.

**Cómo se relacionan**

1. **Hereda:** la empresa recibe las normas y obligaciones que le corresponden por su país y por el grupo (sección 4), y no las modifica.
2. **Suma:** puede agregar obligaciones, reglas y procedimientos propios, incluso derivados de una norma heredada.
3. **Reporta:** cada ciclo y cada tarea de la empresa alimentan el tablero consolidado de la matriz.

Lo heredado se cumple y lo propio se suma: una empresa no puede quitar lo que le llega de la matriz. Si necesita una excepción, la pide a la matriz regional y queda registrada como eximida; para una norma legal hacen falta además fundamento y aprobación del área legal.

**Separación de datos.** Cada empresa ve solo sus datos; la matriz regional ve los de todas sus empresas, según el ámbito de su perfil.

**Decisiones confirmadas**

- Cada empresa puede crear sus propios perfiles, además de los que parten de `conf__perfiles` de la empresa titular.
- Las excepciones a lo heredado las aprueba la matriz regional; si se trata de una norma legal, requieren además fundamento y aprobación del área legal.
- Una empresa puede endurecer lo heredado con exigencias propias, pero nunca relajarlo. Si la exigencia propia cubre lo mismo que la heredada, se consolida en una sola obligación con la exigencia más estricta, conservando ambos orígenes.

## 9. Reglas del sistema

Estas reglas sostienen la lógica y no se negocian al diseñar las bases de datos.

- **Las normas no se cumplen:** solo originan obligaciones, y no disparan tareas directamente.
- **El ámbito se declara una vez y se hereda.** Ninguna norma se asigna entidad por entidad.
- **Las normas de distintos niveles conviven:** se cumplen todas, y cada obligación conserva su norma de origen.
- **Las obligaciones aplican por tipo de entidad** (y condiciones) y no se cierran por cumplimiento: generan ciclos y terminan solo por vigencia o derogación.
- **Todo lo que se controla es una entidad,** física o lógica, con posible jerarquía que solo agrupa y consolida; pertenece a una empresa y se ubica en el territorio, con una base y países de operación si es móvil.
- **El ciclo es la unidad auditable:** no se edita históricamente y cada período es un ciclo nuevo.
- **Las tareas no existen sin ciclo** y pueden depender de otras; una se activa cuando se cumplen todas sus predecesoras.
- **Los estados nunca se escriben en el código:** salen de un catálogo, gobernados por el tipo de tabla.
- **Lo futuro se recalcula y lo pasado no se toca.**
- **Trazabilidad completa:** norma, obligación, ciclo, tarea, evidencia y comprobante quedan encadenados.

**Vigencia y plazos (decisiones confirmadas)**

- **Toda relación lleva fecha de inicio y de fin:** pertenencias territoriales, asignaciones, ámbitos de perfil y delegaciones. Nada se borra: se cierra con fecha.
- **Cada obligación declara su regla de vencimiento:** por período, por dato de la empresa, por fecha propia de la entidad o por aviso de la autoridad.
- **Los plazos se miden con el huso horario del país de la entidad.**

**Entidades, periodicidad y personas (decisiones confirmadas)**

- **Entidad que pasa a otra empresa del grupo:** cada empresa conserva el historial de su período; la nueva ve solo el suyo y la matriz ve ambos.
- **Periodicidad:** admite cada N meses o años, y también la fecha que fija la autoridad.
- **Personas:** una persona puede tener varios ámbitos a la vez, cada uno con su perfil y sus fechas.

**Obligaciones y personas (decisiones confirmadas)**

- **Dependencia entre obligaciones:** una obligación declara de cuál depende; su ciclo no se activa hasta que el de la otra se cumple, con lógica Y.
- **Umbrales:** la empresa carga el dato, como empleados o facturación, con fecha y queda registrado; al cruzar el umbral rige la regla de cambio a mitad de período.
- **Responsable que deja la empresa:** sus tareas se reasignan en bloque con fecha y, hasta entonces, pasan al aprobador del RACI.

**Confidencialidad, cambios masivos y carga inicial (decisiones confirmadas)**

- **Confidencialidad:** las normas, los documentos y las evidencias pueden llevar una marca de confidencialidad; solo los ven los perfiles y ámbitos que se indiquen, salvo el acceso de auditoría de la matriz regional, que queda registrado.
- **Cambio masivo:** antes de aplicar un cambio que alcanza a muchas entidades se muestra una vista previa del impacto (entidades, ciclos y tareas afectados) y se aplica recién con aprobación.
- **Carga inicial:** plantillas de carga masiva por tipo (normas, obligaciones, entidades), con validación y vista previa antes de confirmar.

**Obligaciones, empresa y excepciones (decisiones confirmadas)**

- **Obligación definida y asignada:** la obligación definida pertenece a la norma; a cada entidad se le asigna una obligación asignada, con sus fechas, su exigencia consolidada y sus excepciones, y de ella cuelgan los ciclos.
- **La empresa como entidad:** cada empresa tiene una entidad lógica raíz; las obligaciones corporativas aplican al tipo de entidad Empresa.
- **Excepciones:** en las políticas las aprueba la matriz regional; en las normas legales hacen falta fundamento (resolución de la autoridad o inaplicabilidad probada) y aprobación del área legal.

**Procedimientos, confidencialidad y regiones (decisiones confirmadas)**

- **Procedimientos:** son soporte; la norma o política es la fuente. El procedimiento y sus instrucciones guían las tareas y no generan obligaciones.
- **Confidencialidad:** la marca restringe a los perfiles indicados, pero la matriz regional conserva un acceso de auditoría y queda registrado quién lo ve.
- **Ámbito "región":** hay dos tipos de ámbito, región geográfica y región del grupo; cada perfil elige cuál usa.

**Catálogos y estados (decisiones confirmadas)**

- **Tipos de entidad:** catálogo base del grupo, mantenido por el equipo corporativo; cada empresa puede agregar valores propios sin tocar los del grupo.
- **Tipos de norma:** los mantiene solo el grupo, porque gobiernan el comportamiento del sistema (a confirmar).
- **Estados del ciclo (ocho):** pendiente, generado y sin iniciar; en curso, con trabajo iniciado; cumplido, cerrado dentro del plazo; cumplido fuera de plazo, cerrado después del vencimiento; vencido, pasó la fecha y sigue abierto, de forma recuperable; incumplido, cerrado sin cumplir; eximido, no corresponde por una excepción o por derogación; cancelado, ciclo futuro que ya no se genera. Son finales: cumplido, cumplido fuera de plazo, incumplido, eximido y cancelado.
- **Estados de la tarea (siete):** inactiva, espera a sus predecesoras; pendiente, activa y sin iniciar; en curso; en revisión, espera aprobación; cumplida, aprobada; rechazada, devuelta por el revisor; cancelada.
- Todos los estados salen de un catálogo, no del código.

**Indicador de cumplimiento (decisiones confirmadas)**

- **Dos indicadores:** cumplimiento a tiempo (ciclos cumplidos sobre los ciclos con vencimiento en el período) y cumplimiento total (cumplidos más cumplidos fuera de plazo, sobre los mismos ciclos).
- **Qué no se cuenta:** los ciclos eximidos y cancelados quedan fuera del cálculo. Solo se cuentan los ciclos del período ya vencidos o ya cerrados; los demás se muestran aparte como por vencer.
- **Eximidos:** se informan aparte, con su cantidad y su motivo.
- **Consolidación:** entre empresas, países y regiones se suman los ciclos de todas las entidades y el porcentaje se calcula sobre el total, sin promediar porcentajes.

**Ciclos, alertas, cambios y retención (decisiones confirmadas)**

- **Horizonte de ciclos:** se generan hasta doce meses hacia adelante, renovados con una ventana móvil; el horizonte es configurable por grupo.
- **Alertas:** por correo y dentro del sistema, al responsable antes del vencimiento y a su superior si no hay avance; los plazos se ajustan por obligación.
- **Registro de cambios:** quién, cuándo y qué cambió en normas, obligaciones, reglas y perfiles.
- **Retención:** las evidencias se conservan el plazo que defina cada obligación.

## 10. Decisiones de diseño

Estas siete decisiones quedaron cerradas y ya están reflejadas en las secciones anteriores. Las tres decisiones sobre perfiles, excepciones y exigencias propias están en la sección 8.

| # | Tema | Pregunta | Decisión |
| --- | --- | --- | --- |
| 1 | Solapamientos | Cuando dos niveles exigen lo mismo, ¿qué prevalece? | Una sola obligación con la exigencia más estricta, conservando el origen de cada norma |
| 2 | Pertenencia territorial | ¿Un país puede estar en más de una región o subregión? | Sí, con varias pertenencias para aplicar normas y una principal para consolidar reportes |
| 3 | Profundidad del territorio | ¿Los niveles son fijos o un árbol libre? | Árbol de profundidad libre, donde cada nodo tiene un tipo |
| 4 | Regiones del grupo | ¿Coinciden con las regiones geográficas? | No: se mantienen separadas |
| 5 | Idioma | ¿Las normas se cargan en uno o en varios idiomas? | Texto en el idioma original y título traducido opcional |
| 6 | Calendarios | ¿Los feriados que mueven vencimientos son por país o por localidad? | Un calendario por país, con ajuste local |
| 7 | Normas y tareas | ¿Una norma puede originar tareas directamente? | No: la norma origina obligaciones, y cada obligación genera un ciclo y sus tareas. Se respeta la regla de oro |

## 11. Ejemplos ilustrativos

Tres casos para ver la lógica en funcionamiento. Todos los datos son inventados, salvo los nombres de estándares y normas que ya aparecían en el planteo. Se supone un grupo con tres empresas: A (Argentina), B (Chile) y C (Uruguay), cada una con sitios y tanques.

| Paso | Ejemplo 1: estándar mundial | Ejemplo 2: norma de un país | Ejemplo 3: política del grupo |
| --- | --- | --- | --- |
| Norma y ámbito | ISO 14001, ámbito mundo, tipo estándar | Resolución ambiental de un país, ámbito Argentina, tipo regulatoria | Política HSSE, ámbito grupo, tipo política corporativa |
| Obligación | Auditoría interna ambiental, anual | Presentar la declaración jurada ambiental, anual | Inspección de seguridad de tanques, mensual |
| Aplica a (tipo de entidad) | Sitios operativos | Establecimientos | Tanques |
| Entidades alcanzadas por herencia | Un sitio de cada empresa (A, B y C): 3 entidades | Solo los establecimientos de A, por estar en Argentina; B y C no la reciben | Todos los tanques del grupo, por ejemplo TK-101 (A) y TK-201 (B) |
| Ciclos | Un ciclo anual por sitio | Un ciclo anual por establecimiento; el vencimiento se corre con el calendario de feriados de Argentina | Un ciclo por mes y por tanque |
| Tareas, con dependencias | Planificar, ejecutar la auditoría, cargar hallazgos y cerrar | Reunir datos, cargar la declaración, presentarla y archivar el comprobante | Realizar la inspección, cargar el formulario, revisar y aprobar, y cerrar |
| Evidencia | Informe de auditoría | Comprobante de presentación | Formulario de inspección firmado |
| Quién la administra | Norma y obligación: equipo corporativo, en la matriz regional. Ejecución: cada empresa, en su módulo | Norma y obligación: responsable legal del país. Ejecución: la empresa A | Política y obligación: equipo corporativo. Ejecución: cada empresa. Procedimiento de soporte propio: la empresa B, en su módulo, vinculado a la política |
| Qué ilustra | Una norma mundial llega a todas las empresas sin cargarla una por una | Una norma de país llega solo a las empresas de ese país | Solapamiento y suma: si una ley local pidiera inspección semestral, rige la mensual del grupo y se conserva el origen de ambas; B suma un procedimiento propio que sirve de soporte a las tareas y se vincula a la política, sin generar obligaciones |

**Quién recibe qué**

> *[Dibujo no exportable: "alcance de tres normas · quién recibe qué, entre 3 normas y 3 empresas".]*

El esquema cruza las tres normas con las tres empresas: A, por estar en Argentina, recibe las tres obligaciones; B y C reciben solo las de alcance mundial y de grupo.

## 12. Casos para probar el modelo

Doce situaciones incómodas para ver dónde la lógica aguanta y dónde falta una regla. El veredicto es mío, contra lo que el documento define hoy.

| # | Caso | Cómo lo resolvía el modelo antes de decidir | Veredicto | Qué falta decidir |
| --- | --- | --- | --- | --- |
| 1 | Un país pertenece a dos bloques regionales | Las normas de ambos bloques aplican; si exigen lo mismo, rige la más estricta (secciones 2 y 4) | Cubierto | Nada |
| 2 | Se modifica una norma con ciclos en curso | Los ciclos ya generados conservan la versión con la que nacieron; los futuros usan la nueva (sección 6) | Cubierto | Nada |
| 3 | Una empresa se suma al grupo a mitad de año | Hereda todo lo vigente en su país y en el grupo | Cubierto | **Resuelto:** el ciclo en curso se cierra con lo que aplicaba y se abre uno nuevo desde la fecha de alta. |
| 4 | Una empresa sale del grupo por venta | No está definido | Cubierto | **Resuelto:** la empresa queda inactiva desde la fecha de salida, sus ciclos abiertos se cierran como eximidos y la matriz conserva su historial en solo lectura. |
| 5 | Una entidad cambia de país a mitad de ciclo | Se reevalúa lo que le aplica y se abre la asignación nueva | Cubierto | **Resuelto:** el ciclo en curso se cierra con lo que aplicaba y se abre uno nuevo desde la fecha del cambio. |
| 6 | Una entidad móvil opera en varios países (un buque, un camión) | No está definido: una entidad tiene un solo lugar | Cubierto | **Resuelto:** la entidad móvil tiene una ubicación base y declara sus países de operación; aplican las normas de la base y de cada país de operación. |
| 7 | Obligación por evento, como notificar un incidente en 24 horas | El evento genera el ciclo y este sus tareas | Cubierto | **Resuelto:** lo registra un usuario con permiso y el plazo se mide en horas o días desde el evento. |
| 8 | Un tanque dentro de una terminal (entidades jerárquicas) | Las entidades pueden ser jerárquicas | Cubierto | **Resuelto:** cada entidad recibe solo las obligaciones de su tipo; la jerarquía agrupa y consolida, sin heredar obligaciones. |
| 9 | Una empresa pide una excepción y luego vence | La matriz la aprueba y la obligación queda eximida (sección 8) | Cubierto | **Resuelto:** toda excepción lleva motivo y fecha de vencimiento; al vencer, la obligación vuelve a regir. |
| 10 | Una norma entra en vigencia dentro de seis meses | Debería generar ciclos solo desde su vigencia | Cubierto | **Resuelto:** toda norma, obligación y excepción tiene fecha de inicio y de fin, y los ciclos se generan solo dentro de la vigencia. |
| 11 | Una empresa agrega una obligación propia más estricta que la heredada | Se suma y nunca relaja lo heredado (sección 8) | Cubierto | **Resuelto:** se consolida en una sola obligación con la exigencia más estricta, conservando ambos orígenes. |
| 12 | Se deroga una norma con ciclos abiertos | Sus obligaciones se suspenden y se conserva el historial (sección 6) | Cubierto | **Resuelto:** los ciclos futuros se cancelan y los abiertos se cierran como eximidos con la fecha de derogación. |

**Lectura.** Los doce casos quedaron cubiertos: las tres familias de faltas (tiempo, entidades y grupo) tienen ahora una regla.

Cada caso nuevo que aparezca se prueba contra estas reglas; si no queda cubierto, se agrega una fila a la tabla.

## 13. Segunda ronda: buscando las dolencias antes de que ocurran

Veinticuatro casos más duros, agrupados por familia, para encontrar los límites del modelo antes de construirlo. El veredicto es mío, contra lo que el documento define hoy.

| # | Familia | Caso | Veredicto | Qué falta decidir |
| --- | --- | --- | --- | --- |
| 13 | Estructura | Un país cambia de subregión o sale de un bloque regional | Cubierto | **Resuelto:** las pertenencias territoriales llevan fecha de inicio y de fin, y el cambio recalcula lo futuro. |
| 14 | Estructura | Dos empresas del grupo se fusionan | Cubierto | **Resuelto:** la empresa absorbida queda inactiva con la fecha de fusión, sus entidades pasan a la absorbente y su historial queda en solo lectura. |
| 15 | Estructura | Una empresa cambia de razón social o se escinde | Cubierto | **Resuelto:** el cambio de nombre es la misma empresa con el nombre anterior guardado con fechas; la escisión crea empresas nuevas y transfiere las entidades. |
| 16 | Estructura | Una entidad pasa de una empresa del grupo a otra | Cubierto | **Resuelto:** cada empresa conserva el historial de su período; la nueva ve solo el suyo y la matriz ve ambos. |
| 17 | Estructura | Una entidad pertenece a dos empresas, como una terminal en sociedad | Cubierto | **Resuelto:** una empresa titular responde por el cumplimiento y las otras la ven vinculada, en solo lectura. |
| 18 | Normas | Una norma modifica solo un artículo de otra | Cubierto | **Resuelto:** la obligación se liga a la norma, con referencia opcional al artículo. |
| 19 | Normas | Dos normas de distinto nivel son incompatibles: una prohíbe lo que otra exige | Cubierto | **Resuelto:** se marca el conflicto, se escala al área legal y su decisión queda registrada. |
| 20 | Normas | Una norma se aplica de forma gradual, por tamaño o por fecha | Cubierto | **Resuelto:** cada obligación puede tener vigencias distintas por tipo o tamaño de entidad. |
| 21 | Normas | Una norma regional exige una ley local que todavía no existe | Cubierto | **Resuelto:** aplica de inmediato, salvo que se marque como "requiere norma local"; entonces sus obligaciones se activan cuando se carga y vincula la norma local. |
| 22 | Obligaciones | El vencimiento depende de un dato de la empresa, como la terminación del número de identificación fiscal | Cubierto | **Resuelto:** cada obligación declara su regla de vencimiento: por período, por dato de la empresa, por fecha propia de la entidad o por aviso de la autoridad. |
| 23 | Obligaciones | El plazo corre desde la fecha de un permiso que vence (renovar 30 días antes) | Cubierto | **Resuelto:** la regla de vencimiento por fecha propia de la entidad permite disparar el ciclo desde el vencimiento de un permiso. |
| 24 | Obligaciones | Una obligación no puede cumplirse sin haber cumplido otra | Cubierto | **Resuelto:** una obligación declara de cuál depende; su ciclo no se activa hasta que el de la otra se cumple, con lógica Y. |
| 25 | Obligaciones | Una empresa cruza un umbral a mitad de año, de empleados o de facturación | Cubierto | **Resuelto:** la empresa carga el dato con fecha y queda registrado; al cruzar el umbral rige la regla de cambio a mitad de período. |
| 26 | Obligaciones | La periodicidad es plurianual o la fija la autoridad, como cada cinco años o por inspección | Cubierto | **Resuelto:** la periodicidad admite cada N meses o años, y también la fecha que fija la autoridad. |
| 27 | Ejecución | El responsable de varias tareas deja la empresa | Cubierto | **Resuelto:** las tareas se reasignan en bloque con fecha y, hasta entonces, pasan al aprobador del RACI. |
| 28 | Ejecución | Se detecta un error en un ciclo ya cerrado | Cubierto | **Resuelto:** se corrige con un ciclo complementario enlazado al original, que queda intacto. |
| 29 | Ejecución | Un hallazgo de auditoría exige un plan de acción | Cubierto | **Resuelto:** el plan de acción es un ciclo de seguimiento enlazado al ciclo de origen, con sus propias tareas. |
| 30 | Ejecución | Una evidencia vence, como un certificado | Cubierto | **Resuelto:** la evidencia lleva fecha de validez, avisa al acercarse y puede disparar el ciclo de renovación. |
| 31 | Ejecución | Un plazo de 24 horas cruza husos horarios | Cubierto | **Resuelto:** los plazos se miden con el huso horario del país de la entidad. |
| 32 | Gobierno | Una persona tiene perfiles en dos empresas o en dos regiones | Cubierto | **Resuelto:** una persona puede tener varios ámbitos a la vez, cada uno con su perfil y sus fechas. |
| 33 | Gobierno | Un documento es confidencial y solo lo ve cierto nivel | Cubierto | **Resuelto:** una marca de confidencialidad en normas, documentos y evidencias, con los perfiles y ámbitos que pueden verlos. |
| 34 | Gobierno | Un aprobador está ausente o delega | Cubierto | **Resuelto:** la delegación o suplencia es una relación con fecha de inicio y de fin. |
| 35 | Operación | Una norma mundial cambia y alcanza a miles de entidades | Cubierto | **Resuelto:** vista previa del impacto (entidades, ciclos y tareas afectados) y aplicación recién con aprobación. |
| 36 | Operación | Carga inicial desde planillas existentes | Cubierto | **Resuelto:** plantillas de carga masiva por tipo, con validación y vista previa antes de confirmar. |

**Lectura.** Los veinticuatro casos de esta ronda quedaron cubiertos. Sumados a los doce de la primera, las treinta y seis situaciones tienen ahora una regla; falta probarlas con casos reales de la operación.

Cada caso nuevo que aparezca se prueba contra estas reglas; si no queda cubierto, se agrega una fila a la tabla.

## 14. Revisión crítica: puntos a resolver antes de las bases de datos

Revisión del documento de punta a punta. Ordené los hallazgos por impacto: los primeros seis cambian tablas y relaciones; el resto afecta reglas y operación.

| # | Hallazgo | Por qué importa | Propuesta |
| --- | --- | --- | --- |
| 1 | "Obligación" significa dos cosas: la que define una norma y la que se aplica a una entidad, ya calculada con solapamientos consolidados y exigencias propias | La herencia, la consolidación, las excepciones y los ciclos dependen de cuál de las dos se nombre | **Resuelto:** la obligación definida pertenece a la norma; a cada entidad se le asigna una obligación asignada, de la que cuelgan los ciclos. |
| 2 | La empresa como sujeto de obligaciones | Un balance anual o un código de ética aplican a la empresa, no a un tanque; el documento solo prevé obligaciones sobre entidades | **Resuelto:** cada empresa tiene una entidad lógica raíz, y las obligaciones corporativas aplican al tipo de entidad Empresa. |
| 3 | Procedimiento interno: ¿fuente o soporte? | Figura como tipo de norma (sección 3) y como apoyo de las tareas (sección 5), y no es lo mismo | **Resuelto:** el procedimiento es soporte: la norma o política es la fuente; el procedimiento guía las tareas y no genera obligaciones. |
| 4 | Excepciones a normas legales | Una matriz no puede eximir de una ley; la sección 8 no distingue entre políticas y normas legales | **Resuelto:** en las políticas aprueba la matriz; en las normas legales hacen falta fundamento y aprobación del área legal. |
| 5 | Confidencialidad frente a la matriz | La matriz ve los datos de todas las empresas, pero la marca de confidencialidad limita la vista a los perfiles indicados | **Resuelto:** la marca restringe a los perfiles indicados, pero la matriz conserva acceso de auditoría con registro de quién lo ve. |
| 6 | "Región" con dos sentidos | El ámbito del perfil (sección 7) no aclara si es región geográfica o región del grupo | **Resuelto:** dos tipos de ámbito distintos, región geográfica y región del grupo; cada perfil elige cuál usa. |
| 7 | Catálogos sin dueño | Tipos de entidad, tipos de norma y estados de ciclo y tarea no tienen definido quién los mantiene ni cuáles son; "vencido" e "incumplido" no se distinguen | **Resuelto:** catálogo base del grupo para los tipos de entidad, con valores propios de cada empresa; tipos de norma solo del grupo; ocho estados de ciclo y siete de tarea (sección 9). |
| 8 | No hay indicador de cumplimiento | Los tableros consolidados no son comparables entre empresas sin una definición | **Resuelto:** dos indicadores, a tiempo y total, sobre los ciclos con vencimiento en el período; los eximidos y cancelados no se cuentan y los eximidos se informan aparte; la consolidación suma los ciclos de todas las entidades. |
| 9 | Parámetros de generación y alertas | No se define cuánto hacia adelante se generan los ciclos ni los canales y plazos de las alertas | **Resuelto:** ciclos hasta doce meses hacia adelante con ventana móvil, configurables por grupo; alertas por correo y dentro del sistema, con plazos ajustables por obligación. |
| 10 | Registro de cambios y retención | No se define quién cambió una norma, una regla o una aplicabilidad, ni cuánto tiempo se conservan las evidencias | **Resuelto:** registro de cambios en normas, obligaciones, reglas y perfiles; las evidencias se conservan el plazo que defina cada obligación. |
| 11 | Caso 21 a confirmar | La regla de la norma regional que requiere norma local se asumió sin confirmación | **Resuelto:** confirmada la regla de la norma regional que requiere norma local. |
| 12 | Sesgo en las pruebas | Los 36 casos y sus reglas salieron de la misma conversación, y casi todas las propuestas se aceptaron tal cual | Contrastar con casos reales de la operación y con un especialista legal antes de congelar el modelo |
| 13 | Decisiones dispersas y terminología | Las decisiones están en listas dentro de las secciones 3, 5, 6, 8 y 9, y la sección 10 solo tiene siete; "aprobador" no equivale exactamente a "accountable" del RACI | **Resuelto:** registro de decisiones y glosario en la sección 15. |

**Corregido en esta revisión**

- El encabezado de la sección 12 decía cómo resuelve "hoy" el modelo cuando ya estaban resueltos.
- La regla "las obligaciones nunca se cierran" contradecía la vigencia: ahora terminan solo por vigencia o derogación.
- La derogación hablaba de "suspender" obligaciones y otra regla de cerrarlas: ahora dice que terminan con la fecha de derogación.
- La afirmación de que el modelo "resistió" 36 situaciones era excesiva: ahora dice que tienen una regla y falta probarlas con casos reales.
- La estructura del grupo no explicaba el nivel de región del grupo; quedó dicho en la sección 2.

## 15. Registro de decisiones y glosario

Las decisiones están repetidas como reglas dentro de las secciones 3, 5, 6, 8 y 9. Este registro las reúne por tema, con la sección donde está cada una.

| # | Tema | Decisión | Sección |
| --- | --- | --- | --- |
| 1 | Territorio | Árbol de profundidad libre; un país puede estar en varias regiones o subregiones, con una principal para consolidar | 2 |
| 2 | Grupo | Tres niveles: grupo, región del grupo y empresa; las regiones del grupo son independientes de las geográficas | 2 |
| 3 | Entidades | Físicas o lógicas; la jerarquía solo agrupa; las móviles tienen base y países de operación; cada empresa tiene una entidad lógica raíz | 2 y 9 |
| 4 | Alcance de las normas | El ámbito se declara una vez y se hereda | 3 y 4 |
| 5 | Solapamientos | Una sola obligación con la exigencia más estricta, conservando el origen de cada norma; si son incompatibles, se escala al área legal | 3 y 4 |
| 6 | Normas | Obligación ligada a la norma con artículo opcional; vigencias distintas por tipo o tamaño; una norma regional que requiere norma local se activa al vincular la local | 3 |
| 7 | Procedimientos | Son soporte: no son normas ni fuentes de obligaciones | 3 y 9 |
| 8 | Obligaciones | Definida por la norma y asignada a cada entidad; de la asignada cuelgan los ciclos; pueden depender de otras con lógica Y | 5 y 9 |
| 9 | Ciclos y tareas | Un ciclo por período, hasta doce meses hacia adelante; las tareas no existen sin ciclo y se activan con lógica Y | 5 y 9 |
| 10 | Cambio a mitad de período | Se cierra el ciclo en curso y se abre uno nuevo desde la fecha del cambio | 6 |
| 11 | Derogación, salida y fusión | Ciclos futuros cancelados y abiertos eximidos; la empresa que sale o se fusiona queda inactiva, con historial en solo lectura | 6 |
| 12 | Vigencia y plazos | Toda relación lleva fecha de inicio y de fin; cada obligación declara su regla de vencimiento; huso horario del país de la entidad | 9 |
| 13 | Corrección y calidad | Ciclo complementario para corregir; ciclo de seguimiento para planes de acción; evidencia con fecha de validez | 5 |
| 14 | Módulos y perfiles | Matriz regional y módulo de empresa; cada empresa puede crear perfiles; una persona puede tener varios ámbitos, geográficos o del grupo | 7 y 8 |
| 15 | Excepciones | Políticas: las aprueba la matriz; normas legales: fundamento y aprobación del área legal; llevan motivo y vencimiento | 6 y 8 |
| 16 | Confidencialidad | Marca en normas, documentos y evidencias; la matriz conserva acceso de auditoría con registro | 9 |
| 17 | Catálogos y estados | Tipos de entidad del grupo con valores de cada empresa; tipos de norma del grupo; ocho estados de ciclo y siete de tarea | 9 |
| 18 | Indicador de cumplimiento | Dos indicadores, a tiempo y total, sobre ciclos ya vencidos o cerrados; eximidos informados aparte; consolidación sumando ciclos | 9 |
| 19 | Operación | Vista previa del impacto con aprobación; carga masiva por plantillas; alertas por correo y en el sistema; registro de cambios; retención por obligación | 5 y 9 |

**Glosario**

- **Obligación definida:** requisito que declara una norma.
- **Obligación asignada:** la obligación definida aplicada a una entidad, con sus fechas, su exigencia consolidada y sus excepciones.
- **Ciclo:** instancia de una obligación asignada para un período.
- **Entidad:** lo que se controla, física o lógica; incluye la entidad raíz de cada empresa.
- **Ámbito:** punto del territorio o de la estructura al que se aplica una norma o un perfil.
- **Matriz regional:** el módulo del grupo; "la matriz" en el texto es ese módulo.
- **RACI:** responsable (R), aprobador (A, quien responde por el resultado), consultado (C) e informado (I).
- **Eximido:** ciclo que no corresponde por una excepción aprobada o por derogación.

## 16. Opciones configurables

Diez reglas del sistema pueden elegirse en lugar de venir fijas. El primer valor de cada una es el valor por defecto, y coincide con lo ya decidido en este documento.

| # | Opción | Valores posibles | Se configura en | Condición o cuidado |
| --- | --- | --- | --- | --- |
| 1 | Solapamientos | La exigencia más estricta; la norma de mayor jerarquía | Grupo | La segunda opción requiere un catálogo de jerarquías normativas |
| 2 | Cambio a mitad de período | Cerrar el ciclo en curso y abrir uno nuevo; completar el ciclo con las reglas viejas y aplicar las nuevas desde el período siguiente | Grupo | Sin condiciones |
| 3 | Derogación con ciclos abiertos | Cerrarlos como eximidos; completarlos antes de terminar la obligación | Grupo | Los ciclos futuros se cancelan en ambos casos |
| 4 | Aprobación de excepciones | Políticas: la matriz; normas legales: fundamento y aprobación del área legal; u otro reparto de aprobadores que defina el grupo | Grupo | Para normas legales conviene mantener siempre el fundamento |
| 5 | Revisión de la tarea | Obligatoria; opcional | Obligación | Si es opcional, la tarea pasa de en curso a cumplida sin pasar por en revisión |
| 6 | Evidencia | Obligatoria; opcional | Obligación | Si es obligatoria, la tarea no puede cerrarse sin adjuntarla |
| 7 | Aprobación previa de cambios masivos | Obligatoria, con vista previa; opcional, con vista previa informativa | Grupo | La vista previa del impacto se muestra siempre |
| 8 | Ponderación del indicador | Suma de ciclos; ponderado por criticidad | Grupo | Requiere un atributo de criticidad en la obligación; al cambiarla se informa y no se recalcula el pasado |
| 9 | Acceso de auditoría a documentos confidenciales | Con acceso de auditoría registrado; sin acceso para la matriz | Grupo | Sin acceso, la matriz no puede auditar esos documentos; conviene que lo decida el área legal |
| 10 | Lógica de dependencias | Y: se necesitan todas las predecesoras; por resultado: la tarea se activa según el resultado de la predecesora, aprobada o rechazada | Dependencia | La opción por resultado permite flujos alternativos: cada dependencia declara con qué resultado de la predecesora se activa la tarea |

**Qué no es configurable.** El ámbito se declara una vez y se hereda; el ciclo no se edita y se corrige con uno complementario; la norma no genera tareas directamente; toda relación lleva fecha de inicio y de fin; los estados salen de un catálogo; la trazabilidad es completa; y el huso horario es el del país de la entidad.

**Cómo se gestiona la configuración (decisión confirmada)**

- La define el equipo corporativo desde la matriz regional. Ninguna opción es configurable por empresa, salvo las dos que se definen por obligación, que fija quien define la obligación.
- Cada cambio lleva fecha de inicio y de fin, aplica solo a lo futuro y queda en el registro de cambios.
- Una opción nueva nace con su valor por defecto.
