# Casos de aceptación – Matriz legal y corporativa del grupo

Fuente: secciones 11 a 13 y 16 del documento `01-logica-del-proceso.md`. Cada caso está redactado como Dado / Cuando / Entonces y lista los módulos (M1 a M8) que lo cubren. Los módulos están definidos en `03-plan-de-modulos.md`.

Cada módulo se da por terminado cuando pasan los casos que lo mencionan.

---

## Primera ronda (C01 a C12)

### C01 – Un país pertenece a dos bloques regionales
**Módulos:** M1, M5
- **Dado** un país con dos pertenencias regionales, una de ellas principal.
- **Cuando** se publica una norma en cada bloque y ambas exigen lo mismo.
- **Entonces** las dos aplican a las entidades del país, se genera una sola obligación con la exigencia más estricta y se conserva el origen de cada norma. Los reportes consolidan por la pertenencia principal.

### C02 – Se modifica una norma con ciclos en curso
**Módulos:** M3, M6
- **Dado** ciclos ya generados con la versión vigente de una norma.
- **Cuando** se publica una versión nueva de la norma.
- **Entonces** los ciclos generados conservan la versión con la que nacieron y los ciclos futuros usan la nueva.

### C03 – Una empresa se suma al grupo a mitad de año
**Módulos:** M1, M5, M6
- **Dado** una empresa que se incorpora con una fecha de alta a mitad de período.
- **Cuando** se registra el alta.
- **Entonces** hereda todo lo vigente en su país y en el grupo, y sus ciclos se abren desde la fecha de alta.

### C04 – Una empresa sale del grupo por venta
**Módulos:** M1, M6
- **Dado** una empresa con ciclos abiertos.
- **Cuando** se registra su salida con una fecha.
- **Entonces** queda inactiva desde esa fecha, sus ciclos abiertos se cierran como eximidos y la matriz conserva su historial en solo lectura.

### C05 – Una entidad cambia de país a mitad de ciclo
**Módulos:** M5, M6
- **Dado** una entidad con un ciclo en curso.
- **Cuando** cambia de país con una fecha de cambio.
- **Entonces** el ciclo en curso se cierra con lo que aplicaba y se abre uno nuevo desde la fecha del cambio, con lo que aplica en el país nuevo.

### C06 – Una entidad móvil opera en varios países
**Módulos:** M1, M5
- **Dado** un buque con una ubicación base y países de operación declarados.
- **Cuando** se calculan las normas que le aplican.
- **Entonces** aplican las de la base y las de cada país de operación, sin cerrar ciclos cada vez que se mueve.

### C07 – Obligación por evento, como notificar un incidente en 24 horas
**Módulos:** M4, M6
- **Dado** una obligación por evento con un plazo en horas.
- **Cuando** un usuario con permiso registra el evento.
- **Entonces** se genera el ciclo y el plazo se mide en horas desde ese momento, con el huso horario del país de la entidad.

### C08 – Un tanque dentro de una terminal
**Módulos:** M1, M5
- **Dado** una terminal con un tanque como entidad hija.
- **Cuando** se asignan obligaciones.
- **Entonces** cada entidad recibe solo las obligaciones de su tipo; la jerarquía agrupa y consolida, sin heredar obligaciones.

### C09 – Una empresa pide una excepción y luego vence
**Módulos:** M2, M5
- **Dado** una obligación asignada con una excepción aprobada, con motivo y fecha de vencimiento.
- **Cuando** llega la fecha de vencimiento de la excepción.
- **Entonces** la obligación vuelve a regir. Mientras la excepción estuvo vigente, sus ciclos figuran como eximidos.
- **Y** si la excepción es sobre una norma legal, exige fundamento y aprobación del área legal; si es sobre una política, la aprueba la matriz regional.

### C10 – Una norma entra en vigencia dentro de seis meses
**Módulos:** M3, M4, M6
- **Dado** una norma con fecha de inicio de vigencia a seis meses.
- **Cuando** se cargan sus obligaciones.
- **Entonces** no se generan ciclos antes de esa fecha.

### C11 – Una empresa agrega una obligación propia más estricta que la heredada
**Módulos:** M5
- **Dado** una empresa que agrega una exigencia propia que cubre lo mismo que una heredada y es más estricta.
- **Cuando** se calcula lo que aplica a sus entidades.
- **Entonces** se consolida en una sola obligación asignada con la exigencia más estricta, conservando ambos orígenes. La empresa no puede relajar lo heredado.

### C12 – Se deroga una norma con ciclos abiertos
**Módulos:** M3, M6
- **Dado** una norma con ciclos futuros y ciclos abiertos.
- **Cuando** se deroga con una fecha.
- **Entonces** los ciclos futuros se cancelan y los abiertos se cierran como eximidos con la fecha de derogación. Con la opción alternativa, los abiertos se completan antes de terminar la obligación.

---

## Segunda ronda (C13 a C36)

### C13 – Un país cambia de subregión
**Módulos:** M1, M5
- **Dado** un país con una pertenencia que termina en una fecha y otra que empieza.
- **Cuando** se registra el cambio.
- **Entonces** las pertenencias llevan fecha de inicio y de fin, y se recalcula solo lo futuro.

### C14 – Dos empresas del grupo se fusionan
**Módulos:** M1, M6
- **Dado** dos empresas del grupo.
- **Cuando** se registra la fusión con una fecha.
- **Entonces** la empresa absorbida queda inactiva, sus entidades pasan a la absorbente con una asignación nueva y su historial queda en solo lectura.

### C15 – Una empresa cambia de razón social o se escinde
**Módulos:** M1
- **Dado** una empresa del grupo.
- **Cuando** cambia de razón social.
- **Entonces** sigue siendo la misma empresa y el nombre anterior se guarda con sus fechas.
- **Cuando** se escinde.
- **Entonces** se crean empresas nuevas y las entidades se transfieren.

### C16 – Una entidad pasa de una empresa del grupo a otra
**Módulos:** M1, M6, M7
- **Dado** una entidad con historial en una empresa.
- **Cuando** pasa a otra empresa del grupo.
- **Entonces** cada empresa conserva el historial de su período; la nueva ve solo el suyo y la matriz ve ambos.

### C17 – Una entidad pertenece a dos empresas
**Módulos:** M1, M7
- **Dado** una terminal compartida por dos empresas.
- **Cuando** se asignan sus obligaciones.
- **Entonces** una empresa titular responde por el cumplimiento y la otra la ve vinculada, en solo lectura.

### C18 – Una norma modifica solo un artículo de otra
**Módulos:** M3, M4
- **Dado** una obligación ligada a una norma, con referencia opcional a un artículo.
- **Cuando** otra norma modifica ese artículo.
- **Entonces** la obligación sigue ligada a la norma completa y se revisan las obligaciones de esa norma.

### C19 – Dos normas de distinto nivel son incompatibles
**Módulos:** M3, M5
- **Dado** dos normas que no solo son más o menos exigentes, sino incompatibles (una prohíbe lo que otra exige).
- **Cuando** el sistema detecta el conflicto.
- **Entonces** se marca el conflicto, se escala al área legal y su decisión queda registrada.

### C20 – Una norma se aplica de forma gradual
**Módulos:** M4, M5
- **Dado** una norma que aplica por tamaño de entidad o por fechas escalonadas.
- **Cuando** se define la obligación.
- **Entonces** puede tener vigencias distintas por tipo o tamaño de entidad.

### C21 – Una norma regional exige una ley local que todavía no existe
**Módulos:** M3, M4
- **Dado** una norma regional marcada como "requiere norma local".
- **Cuando** todavía no se cargó la norma local.
- **Entonces** sus obligaciones no se activan; se activan cuando se carga y vincula la norma local. Una norma regional sin esa marca aplica de inmediato.

### C22 – El vencimiento depende de un dato de la empresa
**Módulos:** M4, M6
- **Dado** una obligación cuya regla de vencimiento depende de un dato de la empresa, como la terminación del número de identificación fiscal.
- **Cuando** se generan sus ciclos.
- **Entonces** la fecha de cada ciclo la calcula la regla que declara la obligación.

### C23 – El plazo corre desde la fecha de un permiso que vence
**Módulos:** M4, M6
- **Dado** una entidad con un permiso que tiene fecha de vencimiento propia.
- **Cuando** se acerca esa fecha.
- **Entonces** la regla de vencimiento por fecha propia de la entidad dispara el ciclo de renovación, por ejemplo 30 días antes.

### C24 – Una obligación no puede cumplirse sin haber cumplido otra
**Módulos:** M4, M6
- **Dado** una obligación que declara depender de otra.
- **Cuando** el ciclo de la otra todavía no se cumplió.
- **Entonces** el ciclo de la obligación dependiente no se activa, con lógica Y.

### C25 – Una empresa cruza un umbral a mitad de año
**Módulos:** M1, M5, M6
- **Dado** una obligación condicionada por un dato de la empresa, como empleados o facturación.
- **Cuando** la empresa carga el dato nuevo con una fecha y cruza el umbral.
- **Entonces** el dato queda registrado y rige la regla de cambio a mitad de período.

### C26 – La periodicidad es plurianual o la fija la autoridad
**Módulos:** M4
- **Dado** una obligación que se repite cada cinco años o por aviso de la autoridad.
- **Cuando** se define su periodicidad.
- **Entonces** la periodicidad admite cada N meses o años, y también la fecha que fija la autoridad.

### C27 – El responsable de varias tareas deja la empresa
**Módulos:** M7
- **Dado** un responsable con varias tareas asignadas.
- **Cuando** deja la empresa.
- **Entonces** sus tareas se reasignan en bloque con una fecha y, hasta entonces, pasan al aprobador del RACI.

### C28 – Se detecta un error en un ciclo ya cerrado
**Módulos:** M6
- **Dado** un ciclo cerrado con un error.
- **Cuando** se corrige.
- **Entonces** se crea un ciclo complementario enlazado al original, que queda intacto.

### C29 – Un hallazgo de auditoría exige un plan de acción
**Módulos:** M6
- **Dado** un hallazgo en un ciclo de auditoría.
- **Cuando** se define el plan de acción.
- **Entonces** el plan es un ciclo de seguimiento enlazado al ciclo de origen, con sus propias tareas.

### C30 – Una evidencia vence
**Módulos:** M6, M7
- **Dado** una evidencia con fecha de validez, como un certificado.
- **Cuando** se acerca esa fecha.
- **Entonces** el sistema avisa y puede disparar el ciclo de renovación.

### C31 – Un plazo de 24 horas cruza husos horarios
**Módulos:** M6, M7
- **Dado** un plazo en horas sobre una entidad.
- **Cuando** quien registra el evento está en otro huso horario.
- **Entonces** el plazo se mide con el huso horario del país de la entidad.

### C32 – Una persona tiene perfiles en dos empresas o en dos regiones
**Módulos:** M2
- **Dado** una persona con más de un ámbito.
- **Cuando** se le asignan perfiles.
- **Entonces** puede tener varios ámbitos a la vez, cada uno con su perfil y sus fechas. El ámbito de región puede ser geográfico o del grupo, y cada perfil elige cuál usa.

### C33 – Un documento es confidencial
**Módulos:** M2, M3
- **Dado** una norma, un documento o una evidencia con marca de confidencialidad.
- **Cuando** un usuario sin el perfil indicado intenta verlo.
- **Entonces** no lo ve. La matriz regional conserva un acceso de auditoría y queda registrado quién lo ve (configurable).

### C34 – Un aprobador está ausente o delega
**Módulos:** M2, M7
- **Dado** un aprobador ausente.
- **Cuando** se registra una suplencia o delegación.
- **Entonces** es una relación con fecha de inicio y de fin.

### C35 – Una norma mundial cambia y alcanza a miles de entidades
**Módulos:** M5, M8
- **Dado** un cambio que alcanza a muchas entidades.
- **Cuando** se prepara el cambio.
- **Entonces** se muestra una vista previa del impacto (entidades, ciclos y tareas afectados) y se aplica recién con aprobación.

### C36 – Carga inicial desde planillas existentes
**Módulos:** M8
- **Dado** planillas existentes de normas, obligaciones y entidades.
- **Cuando** se cargan.
- **Entonces** se usan plantillas de carga masiva por tipo, con validación y vista previa antes de confirmar.

---

## Opciones configurables: dos pruebas por opción

Cada opción de la sección 16 necesita una prueba con el valor por defecto y otra con el valor alternativo.

| # | Opción | Por defecto | Alternativa |
| --- | --- | --- | --- |
| 1 | Solapamientos | La exigencia más estricta | La norma de mayor jerarquía |
| 2 | Cambio a mitad de período | Cerrar y abrir un ciclo nuevo | Completar con las reglas viejas y aplicar las nuevas desde el período siguiente |
| 3 | Derogación con ciclos abiertos | Cerrarlos como eximidos | Completarlos antes de terminar la obligación |
| 4 | Aprobación de excepciones | Políticas: la matriz; normas legales: fundamento y área legal | Otro reparto de aprobadores definido por el grupo |
| 5 | Revisión de la tarea | Obligatoria | Opcional |
| 6 | Evidencia | Obligatoria | Opcional |
| 7 | Aprobación previa de cambios masivos | Obligatoria | Opcional, con vista previa informativa |
| 8 | Ponderación del indicador | Suma de ciclos | Ponderado por criticidad |
| 9 | Acceso de auditoría a confidenciales | Con acceso registrado | Sin acceso para la matriz |
| 10 | Lógica de dependencias | Y | Por resultado, aprobada o rechazada |
