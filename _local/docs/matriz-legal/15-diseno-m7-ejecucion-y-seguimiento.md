# Módulo 7: Ejecución y Seguimiento (Diseño Técnico y Casos de Aceptación)

## 1. Alcance y Objetivos
El Módulo 7 implementa la capa de trazabilidad probatoria, gestión de no conformidades/desvíos, sistema de notificaciones automáticas y el tablero consolidado de indicadores de cumplimiento (KPIs) para la Matriz Legal y Corporativa.

Casos cubiertos:
- **C12**: Subida de evidencias y comprobantes con cálculo de hash SHA-256 para integridad documental.
- **C16**: Validez temporal de evidencias (`valida_hasta`) y custodia con período de retención legal (`retener_hasta`).
- **C13**: Registro de incidencias y hallazgos de auditoría con disparo de planes de acción.
- **C29**: Vinculación de incidencias con ciclos de seguimiento derivados.
- **C04 / C05**: Alertas preventivas programadas antes del vencimiento según periodicidad y criticidad.
- **C17**: Registro y trazabilidad de ejecuciones con auditoría de estados.
- **C34**: Tablero consolidado y cálculo de indicadores de cierre (% Cumplimiento, % Fuera de Plazo, % Incumplido, Ponderación por criticidad).

---

## 2. Mapa de Páginas y Funciones (conf__paginas)
- **Página 351**: *Ejecución y Seguimiento* (Agrupador raíz de menú, `padre_id = 0`, `orden = 50`)
- **Página 352**: *Bandeja de Evidencias* (`evidencias.php`, `orden = 10`)
  - Funciones: Consultar evidencias (3221), Subir evidencia (3222), Descargar evidencia (3223), Anular evidencia (3224).
- **Página 353**: *Hallazgos e Incidencias* (`incidencias.php`, `orden = 20`)
  - Funciones: Consultar incidencias (3231), Registrar incidencia (3232), Cerrar incidencia (3233), Vincular ciclo seguimiento (3234).
- **Página 354**: *Consola de Alertas* (`alertas.php`, `orden = 30`)
  - Funciones: Consultar alertas (3241), Generar alertas preventivas (3242), Reenviar alerta (3243), Marcar leída (3244).
- **Página 355**: *Tablero de Cumplimiento* (`tablero_cumplimiento.php`, `orden = 40`)
  - Funciones: Consultar tablero (3251), Calcular cierre período (3252), Exportar reporte consolidado (3253).

---

## 3. Modelo de Datos y Tablas
- `ml__evidencias`: Gestión documental, hash SHA-256, `es_comprobante`, vigencia (`valida_hasta`), retención (`retener_hasta`).
- `ml__incidencias`: Desviaciones y hallazgos con enlace a `ciclo_seguimiento_id`.
- `ml__alertas`: Alertas por canal (EMAIL, SISTEMA, PUSH) para R y A.
- `ml__indicadores_cierres`: Métricas consolidadas mensuales/trimestrales por empresa, región y grupo.

---

## 4. Requisitos de UI y Experiencia de Usuario
- DataTables con `pageLength: 10`, selector abajo a la izquierda (`li`).
- Botones de acción compactos **icon-only** con tooltips Bootstrap.
- Multi-tenant estricto con `usuario_tiene_acceso_pagina()`.
