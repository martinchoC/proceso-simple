# Diseño M1: Territorio y Grupo — Módulo Matriz Legal

**Fecha:** 2026-10-03  
**Agente responsable:** `disenador-proceso`  
**Módulo ERP:** Módulo 3 (`ml`)  
**Fuentes:** [01-logica-del-proceso.md](file:///c:/xampp/htdocs/casalucho/docs/matriz-legal/01-logica-del-proceso.md), [02-casos-de-aceptacion.md](file:///c:/xampp/htdocs/casalucho/docs/matriz-legal/02-casos-de-aceptacion.md) (casos C01, C03, C04, C06, C08, C13 a C17) y [04-modelo-de-datos.dbml](file:///c:/xampp/htdocs/casalucho/docs/matriz-legal/04-modelo-de-datos.dbml) (tablas 1 a 17).

---

## 1. Alcance y objetivos de M1

M1 construye la base estructural del sistema: la **geografía normativa** (árbol territorial de profundidad libre) y la **estructura corporativa** (grupos, regiones, empresas y entidades). 

Resuelve dos jerarquías independientes que convergen en la **entidad** sujeta a control:
1. **Jerarquía territorial:** Mundo → Bloques/Regiones → Países → Provincias/Estados → Localidades.
2. **Jerarquía corporativa:** Grupo → Región del Grupo → Empresa → Entidad (física, lógica, móvil, raíz).

### Casos de aceptación cubiertos
- **C01:** Un país pertenece a múltiples bloques regionales (con una pertenencia principal para consolidar).
- **C03:** Una empresa se incorpora al grupo a mitad de período (vigencia desde fecha de alta).
- **C04:** Una empresa sale del grupo (cierre con `vigente_hasta`).
- **C06:** Entidades móviles (buque, camión) con base fija y países de operación.
- **C08:** Entidades jerárquicas (tanque dentro de terminal): agrupan y consolidan sin heredar obligaciones cruzadas de tipo.
- **C13:** Un país cambia de pertenencia territorial/regional con fechas históricas sin solapamiento.
- **C14:** Fusión de empresas (absorción societaria y traspaso de entidades).
- **C15:** Cambio de razón social / nombre de fantasía conservando historial.
- **C16:** Transferencia de entidad entre empresas del grupo con trazabilidad histórica.
- **C17:** Entidad compartida entre empresas (una titular y otra vinculada en solo lectura).

---

## 2. Mapa de pantallas y arquitectura de páginas (`conf__paginas`)

Siguiendo la convención del proyecto, las páginas se cuelgan del árbol de `conf__paginas` bajo el Módulo 3.

Reutilizamos las páginas existentes:
- **Página 64:** Portada (`modules/ml/index.php`)
- **Página 63:** Contenedor de catálogos y tablas base

### Estructura propuesta de menús y páginas (Bloque 300-399)

```
[Módulo 3: Matriz Legal]
 ├── (64) Portada (index.php)
 ├── (301) Estructura Corporativa (menú agrupador)
 │    ├── (302) Grupos y Regiones (grupos_regiones.php) ──> ml__grupos, ml__regiones_grupo, ml__empresas_regiones
 │    ├── (303) Eventos Societarios (empresas_eventos.php) ──> ml__empresas_eventos, ml__empresas_nombres
 │    └── (304) Entidades (entidades.php) ──> ml__entidades, ml__entidades_empresas, ml__entidades_ubicaciones
 └── (63) Configuración y Tablas (menú agrupador existente)
      ├── (305) Territorios y Husos (territorios.php) ──> ml__territorios, ml__territorios_pertenencias, ml__territorios_tipos
      ├── (306) Feriados (feriados.php) ──> ml__feriados
      └── (307) Tipos de Entidades y Datos (entidades_tipos.php) ──> ml__entidades_tipos, ml__datos_tipos
```

### Detalle de registro en `conf__tablas` y `conf__paginas`

| `pagina_id` | Menú padre | Nombre en menú | Archivo base | Tabla principal (`conf__tablas`) | Patrón |
|:---:|:---:|---|---|:---:|:---:|
| **301** | 0 | `Estructura Corporativa` | *(agrupador)* | 0 | Contenedor |
| **302** | 301 | `Grupos y Regiones` | `grupos_regiones.php` | 201 (`ml__grupos`) | Diccionario con pestañas |
| **303** | 301 | `Eventos Societarios` | `empresas_eventos.php` | 206 (`ml__empresas_eventos`) | Proceso corto (Borrador → Confirmado) |
| **304** | 301 | `Entidades` | `entidades.php` | 212 (`ml__entidades`) | Diccionario jerárquico / detalle |
| **305** | 63 | `Territorios y Husos` | `territorios.php` | 208 (`ml__territorios`) | Diccionario árbol / pertenencias |
| **306** | 63 | `Feriados` | `feriados.php` | 210 (`ml__feriados`) | Diccionario |
| **307** | 63 | `Tipos de Entidad y Datos` | `entidades_tipos.php` | 211 (`ml__entidades_tipos`) | Diccionario |

---

## 3. Especificación funcional de las pantallas

### Pantalla 1: `territorios.php` (Territorios y Husos)
- **Objetivo:** Administrar el árbol territorial multi-país de profundidad libre y las pertenencias históricas.
- **Vista principal:**
  - Panel izquierdo con árbol visual o selector por niveles (Mundo → Región → País → Provincia/Estado → Localidad).
  - Panel derecho con DataTables de los territorios del nivel seleccionado, mostrando código, nombre, tipo, huso horario IANA (ej. `America/Argentina/Buenos_Aires`) y pertenencia principal.
- **Funcionalidades & Acciones:**
  - **Agregar territorio:** Modal con código, nombre, tipo de territorio (`ml__territorios_tipos`), huso horario y asignación de nodo superior con fecha de inicio de vigencia (`ml__territorios_pertenencias`).
  - **Editar:** Modificar nombre o huso horario.
  - **Cambiar pertenencia / Región (C13):** Modal para cerrar la pertenencia actual (`vigente_hasta = hoy`) y abrir una nueva pertenencia superior (`vigente_desde = hoy`), manteniendo la columna generada STORED `principal_abierta_id`.
  - **Inhabilitar / Habilitar:** Transición Activo / Inactivo.

### Pantalla 2: `feriados.php` (Calendario de Feriados)
- **Objetivo:** Mantener el calendario de días no laborables por país/territorio para el cálculo de plazos en días hábiles (M4 y M6).
- **Vista principal:**
  - Filtro por Territorio (por defecto el país de la empresa activa) y Año.
  - Tabla con fecha, descripción, territorio y marca de laborable/no laborable.
- **Acciones:**
  - Agregar / Editar / Eliminar feriado.
  - Clonar feriados nacionales a nivel provincial/local.

### Pantalla 3: `grupos_regiones.php` (Grupos Corporativos y Regiones)
- **Objetivo:** Definir el grupo económico, sus regiones de gestión corporativa y la vinculación de empresas.
- **Vista:**
  - Pestaña 1: ABM de Grupos (`ml__grupos`).
  - Pestaña 2: Regiones del Grupo (`ml__regiones_grupo`).
  - Pestaña 3: Asignación de Empresas a Regiones con vigencias (`ml__empresas_regiones`).

### Pantalla 4: `empresas_eventos.php` (Eventos y Cambios Societarios)
- **Objetivo:** Registrar fusiones, escisiones, cambios de razón social y altas/bajas de empresas en el grupo (Casos C03, C04, C14, C15).
- **Naturaleza:** **Proceso Corto** (`tabla_tipo_id = 2`).
  - Estados: `Borrador (3)` → `Pendiente de Aprobación (4)` → `Confirmado (5)` / `Eliminado (6)`.
- **Flujo:**
  1. El responsable de empresa carga un evento societario (ej. "Fusión por absorción", "Cambio de denominación").
  2. En estado *Borrador* se cargan los datos y la fecha efectiva del evento.
  3. Se pasa a *Pendiente de Aprobación*.
  4. El Administrador de Matriz o Área Legal *Confirma* el evento.
  5. Al confirmar, se disparan los impactos automáticos: actualización en `ml__empresas_nombres` o baja en `ml__empresas_grupo`.

### Pantalla 5: `entidades.php` (Entidades Sujetas a Control)
- **Objetivo:** La pantalla central de activos y unidades de negocio de la empresa.
- **Vista:**
  - Selector de vista: Tabla plana o Árbol jerárquico.
  - Columnas: Código, Nombre, Tipo de Entidad, Empresa Titular, Ubicación Territorial Actual, Estado, Es Móvil (Sí/No), Es Raíz (Sí/No).
- **Pestañas en vista de detalle / edición:**
  1. **Datos Principales:** Código, nombre, entidad padre (`entidad_padre_id`), tipo.
  2. **Empresas y Titularidad (C16, C17):** Historial de empresas a las que pertenece, indicando empresa titular (`es_titular = 1`) y empresas vinculadas en solo lectura. Transferencia entre empresas con fechas.
  3. **Ubicaciones (C05, C06):** Si es fija, su localidad territorial actual e historial de traslados. Si es móvil, su base operativa y lista de países de operación autorizados.
  4. **Datos Dinámicos:** Campos personalizados definidos en `ml__datos_tipos`.

---

## 4. Botones y transiciones (`conf__paginas_funciones`)

Bloque reservado **3001 a 3999**:

| `funcion_id` | `pagina_id` | Función | `accion_js` | Estado Origen | Estado Destino | Permiso Perfiles |
|:---:|:---:|---|---|:---:|:---:|---|
| **3001** | 305 | Agregar Territorio | `agregar` | 0 | 1 | 301 (Admin Matriz) |
| **3002** | 305 | Editar Territorio | `editar` | 1 | 1 | 301 |
| **3003** | 305 | Cambiar Pertenencia | `cambiar_pertenencia`| 1 | 1 | 301 |
| **3004** | 305 | Inactivar | `inactivar` | 1 | 2 | 301 |
| **3005** | 305 | Activar | `activar` | 2 | 1 | 301 |
| **3010** | 306 | Agregar Feriado | `agregar` | 0 | 1 | 301, 304 |
| **3011** | 306 | Editar Feriado | `editar` | 1 | 1 | 301, 304 |
| **3012** | 306 | Eliminar Feriado | `eliminar` | 1 | 2 | 301, 304 |
| **3020** | 303 | Registrar Evento | `agregar` | 0 | 3 | 301, 304 |
| **3021** | 303 | Editar Evento | `editar` | 3 | 3 | 301, 304 |
| **3022** | 303 | Enviar a Aprobación | `enviar_aprobacion` | 3 | 4 | 301, 304 |
| **3023** | 303 | Confirmar Evento | `confirmar` | 4 | 5 | 301, 303 (Legal) |
| **3024** | 303 | Desestimar / Eliminar | `eliminar` | 3, 4 | 6 | 301 |
| **3030** | 304 | Agregar Entidad | `agregar` | 0 | 1 | 301, 304 |
| **3031** | 304 | Editar Entidad | `editar` | 1 | 1 | 301, 304 |
| **3032** | 304 | Transferir Entidad | `transferir` | 1 | 1 | 301 |
| **3033** | 304 | Inactivar Entidad | `inactivar` | 1 | 2 | 301, 304 |
| **3034** | 304 | Activar Entidad | `activar` | 2 | 1 | 301, 304 |

---

## 5. Matriz de permisos de perfiles (Seguridad)

- **301 Administrador de Matriz:** Acceso total a todas las páginas de estructura territorial, grupos y aprobación de eventos.
- **302 Gestor Normativo:** Solo lectura de territorios y entidades para referenciar en normas.
- **303 Área Legal:** Lectura general + Aprobación/Confirmación de eventos societarios (`confirmar`).
- **304 Responsable de Empresa:** Gestión de sus entidades (`entidades.php`), carga de eventos societarios en borrador (`empresas_eventos.php`), feriados locales.
- **307 Auditor y 308 Consulta:** Solo lectura (`listar`, `obtener`) en todas las pantallas.

---

## 6. Plan de implementación de código para M1

El desarrollo se ejecutará en **3 etapas estructuradas**:

1. **Script SQL de configuración (`conf__paginas`, `conf__paginas_funciones`, `conf__perfiles_funciones`)**:
   - Da de alta las páginas 301 a 307 y sus botones en la base de datos.
2. **Desarrollo del componente Core Territorial (`modules/ml/territorios.*` y `feriados.*`)**:
   - Implementa los 4 archivos estándar (`.php`, `_ajax.php`, `_model.php`, `.js`) con prepared statements mysqli y control de sesión multi-empresa estricto.
3. **Desarrollo del componente de Entidades y Eventos (`modules/ml/entidades.*`, `empresas_eventos.*`)**:
   - Soporte para árbol jerárquico, transferencias históricas y ejecución del proceso corto con confirmación de impacto.
