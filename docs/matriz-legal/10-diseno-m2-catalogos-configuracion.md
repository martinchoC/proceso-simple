# Diseño M2: Catálogos, Estados, Perfiles y Configuración — Módulo Matriz Legal

**Fecha:** 2026-10-03  
**Agente responsable:** `disenador-proceso`  
**Módulo ERP:** Módulo 3 (`ml`)  
**Fuentes:** [01-logica-del-proceso.md](file:///c:/xampp/htdocs/casalucho/docs/matriz-legal/01-logica-del-proceso.md) (Secciones 14, 15 y 16), [02-casos-de-aceptacion.md](file:///c:/xampp/htdocs/casalucho/docs/matriz-legal/02-casos-de-aceptacion.md) (Casos C09, C32, C33, C34 y las 10 opciones configurables) y [04-modelo-de-datos.dbml](file:///c:/xampp/htdocs/casalucho/docs/matriz-legal/04-modelo-de-datos.dbml) (tablas 18 a 24).

---

## 1. Alcance y objetivos de M2

M2 implementa los mecanismos de gobierno corporativo, control de acceso por alcance territorial/organizacional y parametrización transversal de las reglas de negocio de la Matriz Legal.

### Tablas comprendidas (7 tablas creadas en M0):
1. **`ml__ambitos_tipos`**: Tipos de alcance (`GRUPO`, `REGION_GRUPO`, `EMPRESA`, `TERRITORIO`).
2. **`ml__usuarios_ambitos`**: Vinculación estricta de usuarios y perfiles a un ámbito geográfico u organizacional específico (Caso C32).
3. **`ml__delegaciones`**: Suplencias temporales entre usuarios con fechas estrictas de vigencia (Caso C34).
4. **`ml__opciones`**: Catálogo de las 10 opciones configurables del grupo (Sección 16).
5. **`ml__opciones_valores`**: Opciones admisibles (por defecto y alternativas) para cada parámetro.
6. **`ml__configuracion_grupo`**: Valor activo de cada opción para cada grupo corporativo con vigencia.
7. **`ml__raci_roles`**: Catálogo de roles de la matriz RACI (`R`, `A`, `C`, `I`).

---

## 2. Mapa de pantallas y arquitectura de páginas (`conf__paginas`)

Siguiendo la jerarquía del módulo 3:
- Agrupador existente **(63) Tablas y Configuración**:
  - **(308) Configuración del Grupo** (`configuracion.php`): Panel de control de las 10 opciones de cálculo y gobernanza corporativa, y consulta de roles RACI.
  - **(309) Ámbitos de Usuarios** (`usuarios_ambitos.php`): Gestión de alcances territoriales / corporativos para usuarios y perfiles (responsable regional, responsable país, responsable empresa).
  - **(310) Delegaciones y Suplencias** (`delegaciones.php`): Registro de ausencias y cesión temporal de perfiles con vigencias no solapadas.

---

## 3. Especificación funcional de las pantallas

### Pantalla 1: `configuracion.php` (Página 308)
- **Objetivo:** Permitir al Administrador de Matriz (`perfil_id = 301`) definir las 10 directrices del grupo económico.
- **Pestaña 1 (Reglas del Grupo):**
  - Muestra las 10 opciones agrupadas por categoría:
    1. Criterio ante solapamiento de normas (Más estricta vs Mayor jerarquía).
    2. Cambio normativo a mitad de período (Cerrar/abrir vs Aplicar en próximo período).
    3. Derogación de norma con ciclos abiertos (Eximir vs Completar ciclo).
    4. Aprobación de excepciones legales (Matriz/Legal vs Reparto por país).
    5. Revisión técnica de tareas (Obligatoria vs Opcional).
    6. Exigencia de evidencias documentales (Obligatoria vs Opcional).
    7. Aprobación previa de cambios masivos (Obligatoria vs Opcional informativa).
    8. Ponderación de indicador de cumplimiento (Conteo lineal vs Criticidad ponderada).
    9. Auditoría a documentos confidenciales (Auditoría central registrada vs Solo lectura local).
    10. Plazos de días hábiles vs corridos (<15 días hábiles vs corridos).
  - Modificación en caliente con registro de fecha de inicio de vigencia.
- **Pestaña 2 (Catálogo RACI):**
  - Roles estándar: Responsable (`R`), Accountable / Aprobador (`A`), Consultado (`C`), Informado (`I`).

### Pantalla 2: `usuarios_ambitos.php` (Página 309)
- **Objetivo:** Asignar a un usuario/perfil qué parte del mundo o de la corporación tiene autorizada (Caso C32).
- **Control:**
  - Selector de Tipo de Ámbito (`Grupo`, `Región`, `Empresa`, `Territorio`).
  - Constraint `ck_usam_ambito`: La base de datos garantiza que exactamente uno de los 4 IDs tenga valor.
  - Trazabilidad con `vigente_desde` y `vigente_hasta`.

### Pantalla 3: `delegaciones.php` (Página 310)
- **Objetivo:** Gestionar suplencias por vacaciones, licencias o ausencias laborales (Caso C34).
- **Campos:**
  - Usuario Titular (`usuario_id`).
  - Usuario Suplente (`usuario_suplente_id`).
  - Perfil delegado (`empresa_perfil_id`, opcional: si es NULL delega todos sus perfiles).
  - Motivo de la suplencia.
  - Rango de fechas (`vigente_desde` hasta `vigente_hasta`).
- **Validaciones:**
  - El suplente no puede ser el mismo usuario titular.
  - `vigente_hasta >= vigente_desde`.

---

## 4. Botones y Funciones (`conf__paginas_funciones`)

Bloque **3050 a 3070**:
| `funcion_id` | `pagina_id` | Función | `accion_js` | Estado Origen | Estado Destino | Perfiles |
|:---:|:---:|---|---|:---:|:---:|---|
| **3050** | 308 | Modificar Opción | `modificar_opcion` | 1 | 1 | 301 |
| **3055** | 309 | Asignar Ámbito | `agregar_ambito` | 0 | 1 | 301 |
| **3056** | 309 | Cerrar Ámbito | `cerrar_ambito` | 1 | 1 | 301 |
| **3060** | 310 | Registrar Delegación | `agregar_delegacion` | 0 | 1 | 301, 304, 306 |
| **3061** | 310 | Cancelar Delegación | `cancelar_delegacion` | 1 | 2 | 301, 304 |
