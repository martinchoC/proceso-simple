# Diseño M4: Obligaciones y Aplicabilidad — Módulo Matriz Legal

**Fecha:** 2026-10-03  
**Agente responsable:** `disenador-proceso`  
**Módulo ERP:** Módulo 3 (`ml`)  
**Fuentes:** [01-logica-del-proceso.md](file:///c:/xampp/htdocs/casalucho/docs/matriz-legal/01-logica-del-proceso.md) (Sección 5 "Del requisito a la ejecución"), [02-casos-de-aceptacion.md](file:///c:/xampp/htdocs/casalucho/docs/matriz-legal/02-casos-de-aceptacion.md) (Casos C07, C10, C20, C21, C22, C23, C24, C26) y [04-modelo-de-datos.dbml](file:///c:/xampp/htdocs/casalucho/docs/matriz-legal/04-modelo-de-datos.dbml) (tablas 38 a 47).

---

## 1. Alcance y objetivos de M4

Una norma en sí misma no se cumple directamente: se cumplen **obligaciones concretas** derivadas de ella. M4 modela la transformación del texto normativo en requerimientos ejecutables, tipificados y calendarizados.

### Tablas comprendidas (10 tablas creadas en M0):
1. **`ml__criticidades`**: Clasificación del impacto de cumplimiento (Baja, Media, Alta, Crítica/Mandatoria) con peso numérico para el cálculo del indicador ponderado (Opción 8).
2. **`ml__periodicidades`**: Cadencia de repetición (anual, semestral, mensual, plurianual N años - Caso C26, por evento - Caso C07, o por aviso de autoridad).
3. **`ml__vencimientos_reglas`**: Fórmulas de cálculo de vencimiento (día fijo, último día hábil, según terminación fiscal CUIT/NIF - Caso C22, vencimiento de permiso de la entidad - Caso C23, u horas desde evento).
4. **`ml__obligaciones`**: Requerimiento legal o corporativo derivado de una norma, con referencia a artículo, criticidad, periodicidad, regla de vencimiento y exigencias de revisión y evidencia.
5. **`ml__obligaciones_tipos_entidades`**: Vinculación de aplicabilidad a tipos de entidad específicos con reglas JSON de condición (Caso C20).
6. **`ml__obligaciones_jurisdicciones`**: Filtro territorial específico para la obligación si es más restrictiva que la norma padre.
7. **`ml__obligaciones_dependencias`**: Red de prelación entre obligaciones (lógica "Y": una obligación no se activa hasta cumplir la predecesora - Caso C24).
8. **`ml__obligaciones_alertas`**: Umbrales de notificación anticipada (días antes, destinatario RACI, canal mail/sistema).
9. **`ml__tareas_plantillas`**: Plantillas operativas que se instancian en cada ciclo generado, con offsets de días de inicio y vencimiento, y vínculo al procedimiento de soporte.
10. **`ml__tareas_plantillas_dependencias`**: Secuencia de ejecución entre tareas (predecesora -> sucesora) con estado activador.

---

## 2. Casos de Aceptación Cubiertos

- **C07:** Obligación por evento (ej: notificar derrame o incidente en 24h con huso horario local).
- **C10:** Obligación con vigencia futura (no genera ciclos antes de su `vigente_desde`).
- **C20:** Aplicación gradual de la norma por tipo de entidad o condiciones específicas (reglas JSON).
- **C21:** Bloqueo de activación de obligaciones si la norma regional `requiere_norma_local` y no fue vinculada aún.
- **C22:** Regla de vencimiento calculada dinámicamente según dato de la empresa (ej: CUIT / NIF).
- **C23:** Regla de vencimiento que corre desde la fecha de expiración propia de un certificado o permiso de la entidad.
- **C24:** Dependencias entre obligaciones con lógica "Y" estricta.
- **C26:** Periodicidad plurianual (cada N años) o por fecha fijada por la autoridad de control.

---

## 3. Mapa de pantallas y arquitectura de páginas (`conf__paginas`)

Agrupador raíz en menú lateral:
- **(321) Obligaciones y Tareas** (`#`, `padre_id = 0`, `orden = 35`):
  - **(322) Reglas y Periodicidades** (`obligaciones_reglas.php`, `orden = 10`): Criticidades, periodicidades y fórmulas de vencimiento.
  - **(323) Matriz de Obligaciones** (`obligaciones.php`, `orden = 20`): ABM maestro de obligaciones, aplicabilidad a tipos de entidad, dependencias y alertas.
  - **(324) Plantillas de Tareas** (`tareas_plantillas.php`, `orden = 30`): Diseño de tareas tipo que heredarán los ciclos operativos, con asignación de procedimientos y encadenamiento.

---

## 4. Botones y Funciones (`conf__paginas_funciones`)

Bloque **3120 a 3150**:
| `funcion_id` | `pagina_id` | Función | `accion_js` | Estado Origen | Estado Destino | Perfiles |
|:---:|:---:|---|---|:---:|:---:|---|
| **3121** | 322 | Guardar Criticidad | `guardar_criticidad` | 0 | 1 | 301, 302 |
| **3122** | 322 | Guardar Periodicidad | `guardar_periodicidad` | 0 | 1 | 301, 302 |
| **3123** | 322 | Guardar Regla Vencimiento | `guardar_regla_vencimiento` | 0 | 1 | 301, 302 |
| **3130** | 323 | Crear Obligación | `crear_obligacion` | 0 | 2061 | 301, 302 |
| **3131** | 323 | Enviar Obligación a Revisión | `enviar_revision_obligacion` | 2061 | 2062 | 301, 302 |
| **3132** | 323 | Aprobar Obligación | `aprobar_obligacion` | 2062 | 2063 | 301, 303 |
| **3133** | 323 | Suspender / Dar de Baja | `suspender_obligacion` | 2063 | 2064 | 301, 303 |
| **3135** | 323 | Asignar Tipo de Entidad | `asignar_tipo_entidad` | 0 | 1 | 301, 302 |
| **3136** | 323 | Vincular Dependencia Obligación | `vincular_dependencia_obligacion` | 0 | 1 | 301, 302 |
| **3137** | 323 | Configurar Alerta | `configurar_alerta` | 0 | 1 | 301, 302 |
| **3140** | 324 | Guardar Plantilla de Tarea | `guardar_tarea_plantilla` | 0 | 1 | 301, 302, 304 |
| **3141** | 324 | Vincular Dependencia Tarea | `vincular_dependencia_tarea` | 0 | 1 | 301, 302, 304 |

---

## 5. Directrices de UI/UX
- **DataTables:** `pageLength: 10`, selector de registros abajo a la izquierda (`li`) y búsqueda responsive.
- **Acciones:** Botones compactos icon-only (`btn-group-sm` con tooltips descriptivos).
- **Diseño responsive:** Totalmente compatible con dispositivos móviles y temas de AdminLTE 4.
