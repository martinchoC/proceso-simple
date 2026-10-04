# Diseño M3: Normas y Soporte — Módulo Matriz Legal

**Fecha:** 2026-10-03  
**Agente responsable:** `disenador-proceso`  
**Módulo ERP:** Módulo 3 (`ml`)  
**Fuentes:** [01-logica-del-proceso.md](file:///c:/xampp/htdocs/casalucho/docs/matriz-legal/01-logica-del-proceso.md) (Secciones 3, 4, 5, 6, 7 y 8), [02-casos-de-aceptacion.md](file:///c:/xampp/htdocs/casalucho/docs/matriz-legal/02-casos-de-aceptacion.md) (Casos C02, C10, C12, C18, C19, C21, C33) y [04-modelo-de-datos.dbml](file:///c:/xampp/htdocs/casalucho/docs/matriz-legal/04-modelo-de-datos.dbml) (tablas 25 a 37).

---

## 1. Alcance y objetivos de M3

M3 modela el **acervo normativo y procedimental** del grupo económico. En este hito se gestionan las fuentes jurídicas y corporativas que originan obligaciones, sus versiones cronológicas, las relaciones de prelación y modificación entre ellas, los conflictos de incompatibilidad legal, los procedimientos de soporte operativo y las restricciones de confidencialidad.

### Tablas comprendidas (13 tablas creadas en M0):
1. **`ml__organismos`**: Entidades emisoras públicas o privadas (ej: OIT, ISO, Congreso de la Nación, SRT, Prefectura Naval).
2. **`ml__normas_tipos`**: Clasificación jurídica/normativa (Ley, Decreto, Resolución, Política Corporativa, Estándar, Convenio).
3. **`ml__normas_jerarquias`**: Orden de prelación legal (1 Tratados, 2 Leyes, 3 Decretos, 4 Resoluciones, 5 Políticas).
4. **`ml__normas`**: Registro maestro de la norma, su ámbito de aplicación único (geográfico o corporativo), idioma y vigencias.
5. **`ml__normas_versiones`**: Texto oficial, documento fuente, hash de integridad y estado de publicación de cada versión.
6. **`ml__normas_relaciones_tipos`**: Tipos de vínculo (`MODIFICA`, `DEROGA`, `REGLAMENTA`, `COMPLEMENTA`, `REEMPLAZA`, `DERIVA_DE`, `VERIFICA`).
7. **`ml__normas_relaciones`**: Red de interdependencia entre normas con vigencia temporal.
8. **`ml__normas_conflictos`**: Registro de contradicciones normativas (prohibición vs exigencia), escalado y dictamen legal vinculante.
9. **`ml__procedimientos`**: Manuales, directivas y procedimientos internos que guían la ejecución operativa (soporte, no norma).
10. **`ml__procedimientos_versiones`**: Versiones auditables de los procedimientos con control de aprobación y publicación.
11. **`ml__procedimientos_instrucciones`**: Pasos o tareas ordenadas que componen un procedimiento específico.
12. **`ml__accesos_confidenciales`**: Permisos explícitos asignados a perfiles específicos para visualizar normas reservadas.
13. **`ml__auditoria_accesos`**: Registro inmutable de consulta y lectura a normas y documentos confidenciales.

---

## 2. Mapa de pantallas y arquitectura de páginas (`conf__paginas`)

En el Módulo 3 se crea un nuevo agrupador raíz en el menú lateral de [header1.php](file:///c:/xampp/htdocs/casalucho/templates/adminlte/header1.php):

- **(311) Normas y Soporte** (`#`, `padre_id = 0`, `orden = 25`): Contenedor principal de normativa.
  - **(312) Catálogos Normativos** (`normas_catalogos.php`, `orden = 10`): Pestañas para Organismos, Tipos de Normas, Jerarquías y Tipos de Relación.
  - **(313) Matriz de Normas y Versiones** (`normas.php`, `orden = 20`): Catálogo unificado de normas, subtabla de versiones, vinculación de relaciones inter-normas y marcación de confidencialidad e idioma.
  - **(314) Conflictos Normativos** (`normas_conflictos.php`, `orden = 30`): Detección de incompatibilidades legales, flujo de escalado y resolución por dictamen de Legales.
  - **(315) Procedimientos de Soporte** (`procedimientos.php`, `orden = 40`): Procedimientos operativos, versiones aprobadas e instrucciones de trabajo asociadas a normas.
  - **(316) Accesos Confidenciales y Auditoría** (`accesos_confidenciales.php`, `orden = 50`): Matriz de excepciones confidenciales y visor de log inmutable de accesos.

---

## 3. Especificación funcional y Casos de Aceptación

### Pantalla 1: `normas_catalogos.php` (Página 312)
* Pestañas para mantener los diccionarios de apoyo:
  1. **Organismos**: Nombre, territorio de radicación (opcional para internacionales) y estado.
  2. **Tipos de Normas**: Código, nombre, marcas booleanas `es_legal` (para requerir dictamen legal ante excepciones) y `genera_obligaciones`.
  3. **Jerarquías de Prelación**: Nivel numérico (menor número = mayor jerarquía legal) para resolución de solapamientos (Opción 1 del Grupo).
  4. **Tipos de Relaciones**: Catálogo de vínculos inter-normas.

### Pantalla 2: `normas.php` (Página 313)
* **Objetivo:** Registro integral del cuerpo normativo y documental.
* **Filtros principales:** Ámbito (Mundial, Regional, País/Territorio, Empresa), Tipo de norma, Organismo, Solo vigentes / Históricas, Confidencial.
* **Maestro - Detalle:**
  * **Cabecera de Norma:** Código oficial, título original, idioma, traducción opcional, organismo, jerarquía, tipo de ámbito y destino (exclusividad de alcance), flag `requiere_norma_local` (Caso C21) y `confidencial` (Caso C33).
  * **Pestaña Versiones:** Historial de versiones del texto o archivo (v1.0, v2.0...) con hash SHA-256, fecha de publicación y botón para activar vigencia (Casos C02 y C10).
  * **Pestaña Relaciones:** Tabla de relaciones hacia otras normas (ej: "Ley 24.557 modifica Art. 12 de Ley 19.587", o "Decreto 351/79 reglamenta Ley 19.587") (Caso C18).
* **Transiciones de Estado de Norma (Proceso Corto):**
  * `2045` Borrador → `2046` Pendiente de Aprobación → `2047` Confirmado / Vigente → `2048` Eliminado / Derogada (Caso C12).

### Pantalla 3: `normas_conflictos.php` (Página 314)
* **Objetivo:** Tratamiento de normas incompatibles donde una prohíbe lo que otra exige (Caso C19).
* **Flujo de Proceso Largo (`ml__normas_conflictos`):**
  * `2084` Abierto / Detectado → `2085` Escalado al Área Legal → `2086` Resuelto / Confirmado.
* **Campos:** Norma A, Norma B, descripción técnica del conflicto, usuario asignado en Legal (`escalado_a`), dictamen resolutivo (`decision`), fecha y responsable de la decisión.

### Pantalla 4: `procedimientos.php` (Página 315)
* **Objetivo:** Documentación de soporte que guía la ejecución de tareas operativas (Sección 3 del proceso).
* **Campos:** Código, nombre, empresa, norma vinculada (opcional), confidencialidad.
* **Sub-entidades:**
  * Versiones de procedimiento con control de aprobación y publicación (Borrador -> Pendiente -> Confirmado).
  * Instrucciones secuenciales de trabajo (`ml__procedimientos_instrucciones`): orden, título, descripción y marca de obligatoriedad.

### Pantalla 5: `accesos_confidenciales.php` (Página 316)
* **Objetivo:** Cumplimiento estricto del Caso C33 y gobierno de información reservada.
* **Control:**
  * Asignación de permisos especiales sobre objetos confidenciales (`ml__normas`, `ml__procedimientos`, `ml__evidencias`) a perfiles y ámbitos específicos.
  * Tablero de auditoría inmutable de accesos: Usuario, objeto, timestamp exacto y motivo declarado.

---

## 4. Botones y Funciones (`conf__paginas_funciones`)

Bloque **3071 a 3110**:
| `funcion_id` | `pagina_id` | Función | `accion_js` | Estado Origen | Estado Destino | Perfiles |
|:---:|:---:|---|---|:---:|:---:|---|
| **3071** | 312 | Guardar Organismo | `guardar_organismo` | 0 | 1 | 301, 302, 303 |
| **3072** | 312 | Guardar Tipo Norma | `guardar_norma_tipo` | 0 | 1 | 301, 302 |
| **3073** | 312 | Guardar Jerarquía | `guardar_jerarquia` | 0 | 1 | 301, 302 |
| **3075** | 313 | Crear Norma | `crear_norma` | 0 | 2045 | 301, 302, 303 |
| **3076** | 313 | Enviar a Revisión | `enviar_revision_norma` | 2045 | 2046 | 301, 302 |
| **3077** | 313 | Aprobar y Publicar Norma | `aprobar_norma` | 2046 | 2047 | 301, 303 |
| **3078** | 313 | Derogar Norma | `derogar_norma` | 2047 | 2048 | 301, 303 |
| **3079** | 313 | Agregar Versión | `agregar_version_norma` | 0 | 2049 | 301, 302 |
| **3080** | 313 | Vincular Relación | `vincular_relacion` | 0 | 1 | 301, 302, 303 |
| **3085** | 314 | Registrar Conflicto | `registrar_conflicto` | 0 | 2084 | 301, 302, 304 |
| **3086** | 314 | Escalar a Legales | `escalar_conflicto` | 2084 | 2085 | 301, 302 |
| **3087** | 314 | Dictaminar / Resolver | `resolver_conflicto` | 2085 | 2086 | 301, 303 |
| **3090** | 315 | Guardar Procedimiento | `guardar_procedimiento` | 0 | 2053 | 301, 302, 304 |
| **3091** | 315 | Aprobar Procedimiento | `aprobar_procedimiento` | 2054 | 2055 | 301, 304, 306 |
| **3095** | 316 | Autorizar Acceso | `autorizar_acceso` | 0 | 1 | 301 |

---

## 5. Directrices de UI/UX
- **DataTables**: `pageLength: 10`, selector de registros abajo a la izquierda (`li`), búsqueda responsive.
- **Acciones**: Botones compactos icon-only en `<div class="btn-group btn-group-sm" role="group">` con tooltips descriptivos.
- **Seguridad**: Si una norma tiene `confidencial = 1`, solo los usuarios con perfil `301`, `307` o con registro vigente en `ml__accesos_confidenciales` pueden ver el título y texto completo; para el resto figura bloqueada con ícono de candado. Toda visualización no trivial genera registro automático en `ml__auditoria_accesos`.
