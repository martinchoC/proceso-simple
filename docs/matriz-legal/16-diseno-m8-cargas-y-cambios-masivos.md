# Módulo 8: Cargas y Cambios Masivos (Diseño Técnico y Casos de Aceptación)

## 1. Alcance y Objetivos
El Módulo 8 implementa la capacidad de ingesta inicial masiva de datos (normas, obligaciones y entidades) desde planillas CSV/Excel estructuradas, así como la gestión controlada de cambios normativos de alcance masivo con análisis de impacto previo y aprobación formal.

Casos cubiertos:
- **C36 (Carga inicial desde planillas existentes)**: Cargas masivas por tipo (`NORMAS`, `OBLIGACIONES`, `ENTIDADES`), parseo, validación línea por línea con detalle de errores, almacenamiento en `ml__cargas` y `ml__cargas_filas`, vista previa y confirmación transaccional con rollback atómico.
- **C35 (Una norma mundial cambia y alcanza a miles de entidades)**: Preparación de cambio masivo (`ml__cambios_masivos`), simulación y desglose de impacto por entidad (`ml__cambios_masivos_detalles` con conteo de ciclos y tareas afectadas), flujo de aprobación y posterior aplicación.
- **Opción Configurable 7 (Aprobación previa de cambios masivos)**: Obligatoria por defecto; requiere perfil autorizante antes del recálculo.

---

## 2. Mapa de Páginas y Funciones (conf__paginas)
- **Página 361**: *Cargas y Cambios Masivos* (Agrupador raíz en menú, `padre_id = 0`, `orden = 60`)
- **Página 362**: *Cargas Masivas* (`cargas_masivas.php`, `orden = 10`)
  - Funciones:
    - 3261: `consultar_cargas` (Ver historial de lotes y cargas)
    - 3262: `subir_validar_carga` (Subir archivo CSV y validar estructura)
    - 3263: `confirmar_carga` (Confirmar inserción transaccional de registros válidos)
    - 3264: `descargar_plantilla` (Descargar plantilla CSV modelo)
- **Página 363**: *Cambios Masivos e Impacto* (`cambios_masivos.php`, `orden = 20`)
  - Funciones:
    - 3271: `consultar_cambios_masivos` (Ver lista de solicitudes de cambio masivo)
    - 3272: `simular_impacto` (Calcular entidades, ciclos y tareas impactadas)
    - 3273: `aprobar_cambio` (Aprobar formalmente el cambio normativo C35)
    - 3274: `aplicar_cambio` (Ejecutar recálculo y propagación en cascada)

---

## 3. Modelo de Datos y Tablas
- `ml__cargas_tipos`: Catálogo de tipos de carga (`NORMAS`, `OBLIGACIONES`, `ENTIDADES`).
- `ml__cargas`: Registro de cada lote subido, archivo físico, usuario, estado (Cargada, Validada, Confirmada, Con Error).
- `ml__cargas_filas`: Registro de cada fila parseada con contenido JSON y detalle de errores detectados.
- `ml__cambios_masivos`: Encabezado del cambio propuesto sobre una versión de norma, solicitante, requerimiento de aprobación y estado.
- `ml__cambios_masivos_detalles`: Desglose del impacto calculado por entidad (ciclos y tareas que se verán alterados o regenerados).

---

## 4. Requisitos de UI y UX
- DataTables con `pageLength: 10`, selector de registros abajo a la izquierda (`li`).
- Botones de acción compactos **icon-only** con tooltips Bootstrap.
- Validaciones multi-tenant estrictas.
