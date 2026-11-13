# 16 — Seguimiento de Contratos y Certificaciones de Avance

Fecha: 2026-11-11. Versión 1 — Diseño de arquitectura unificada sobre Órdenes de Compra y Portal de Proveedores.

---

## 1. Objetivo y Fundamentos

En el sistema de gestión, un contrato comercial de servicios con un proveedor (mantenimientos, abonos, alquileres, seguridad, obras civiles o consultoría) es operativamente una **Orden de Compra Marco / Abierta por Servicios**.

Este módulo extiende la gestión de compras existente para cubrir tres necesidades críticas de negocio:
1. **Seguimiento de Vigencias y Renovaciones:** Evitar renovaciones automáticas no deseadas mediante alarmas tempranas en la ventana de preaviso de rescisión (30/60 días antes).
2. **Certificaciones de Avance Técnico (Medición de Obra / Servicios por Hitos):** En servicios complejos, impedir que el proveedor facture libremente sin un **Certificado de Avance** previo, emitido o aprobado por la inspección técnica interna de la empresa.
3. **Portal de Proveedores con Three-Way Match:** Permitir al proveedor consultar sus contratos y certificados aprobados, e imputar su factura directamente contra el documento autorizado, bloqueando sobre-facturaciones o desvíos.

---

## 2. Decisión de Arquitectura: Unificación en Órdenes de Compra

Para no generar redundancias, tablas paralelas ni duplicar el motor de imputación de facturas:
* **El modelo de datos se mantiene unificado en `gestion__ordenes_compra`**, incorporando los atributos de vigencia, plazos y modalidades.
* La tabla `gestion__facturas_proveedores` **ya cuenta con `orden_compra_id`** y su mecanismo de recálculo (`ocRecalcularRecepcionPorFactura`), por lo que la imputación de facturas tanto para compras físicas como para contratos se realiza por el mismo canal probado.
* En la interfaz (UI), el usuario dispone de vistas o selectores especializados (`Bienes / Mercadería` vs `Contratos / Servicios`) para no mezclar el control de remitos físicos y depósitos con el control de vigencias y cláusulas contractuales.

---

## 3. Tipos de Contrato y Modalidades Operativas

La columna `tipo_circuito` en `gestion__ordenes_compra` discrimina el comportamiento del documento:

| `tipo_circuito` | Modalidad | Dinámica de Facturación y Control |
|---|---|---|
| `BIENES` | Compra tradicional de insumos / stock | Remitos de recepción física en depósitos / sucursales. Control por unidades recibidas. |
| `CONTRATO_RECURRENTE` | Canon o abono periódico fijo (alquileres, SaaS, seguridad) | Control de vigencia, fecha fin y preaviso. Facturación mensual/periódica de importe fijo pactado. |
| `CONTRATO_AVANCE` | Obras, proyectos, montajes o servicios por hitos | **Exige Certificación de Avance previa**. No se puede facturar sin un certificado aprobado. |

---

## 4. Circuito de Contratos con Certificación de Avance

```
┌─────────────────────────────────┐
│ 1. Orden de Compra / Contrato   │ (tipo_circuito = 'CONTRATO_AVANCE', requiere_certificacion_avance = 1)
│    Presupuesto total contratado │ Define: % anticipo financiero, % fondo de reparo, responsable técnico.
└───────────────┬─────────────────┘
                │
                ▼
┌─────────────────────────────────┐
│ 2. Medición de Avance           │ El contratista solicita certificar un % acumulado o hitos ejecutados.
│    (Portal o Mesa de Entrada)   │ Adjunta memoria técnica, bitácora de obra o evidencia.
└───────────────┬─────────────────┘
                │
                ▼
┌─────────────────────────────────┐
│ 3. Aprobación Técnica           │ El inspector o Project Manager revisa la medición:
│    (Certificado Oficial ERP)    │ Valida el % real, descuenta amortización de anticipo y fondo de reparo.
│                                 │ Estado: APROBADO -> Habilita facturación por el monto neto exacto.
└───────────────┬─────────────────┘
                │
                ▼
┌─────────────────────────────────┐
│ 4. Factura del Proveedor        │ El proveedor emite factura imputada al Certificado N°.
│    (Three-Way Match)            │ Validación estricta: Monto Factura == Monto Neto Autorizado.
└───────────────┬─────────────────┘
                │
                ▼
┌─────────────────────────────────┐
│ 5. Tesorería y Pagos            │ La factura entra a gestion__facturas_proveedores y se cancela vía OP
│    (pagos.php)                  │ según su fecha de vencimiento pactada.
└─────────────────────────────────┘
```

### Fórmula Financiera del Certificado

Para cada emisión de certificado:
1. **Avance Bruto del Período:**
   $$\text{Monto Bruto} = (\% \text{ Avance Acumulado Actual} - \% \text{ Avance Acumulado Anterior}) \times \text{Monto Total Contrato}$$
2. **Amortización de Anticipo Financiero:**
   $$\text{Descuento Anticipo} = \text{Monto Bruto} \times \frac{\% \text{ Anticipo Pactado}}{100}$$
3. **Fondo de Reparo / Retención de Garantía:**
   $$\text{Fondo Reparo} = \text{Monto Bruto} \times \frac{\% \text{ Fondo Reparo}}{100}$$
4. **Monto Neto Autorizado a Facturar:**
   $$\mathbf{\text{Neto Facturable} = \text{Monto Bruto} - \text{Descuento Anticipo} - \text{Fondo Reparo}}$$

---

## 5. Modelo de Datos y Estructura SQL

### 5.1 Ampliación en `gestion__ordenes_compra`
* `tipo_circuito` ENUM('BIENES', 'CONTRATO_RECURRENTE', 'CONTRATO_AVANCE') NOT NULL DEFAULT 'BIENES'
* `es_contrato` TINYINT(1) NOT NULL DEFAULT 0
* `contrato_nro_legal` VARCHAR(100) NULL COMMENT 'Identificador legal externo o número de contrato impreso'
* `fecha_inicio` DATE NULL COMMENT 'Inicio de vigencia'
* `fecha_fin` DATE NULL COMMENT 'Fin de vigencia del contrato'
* `dias_preaviso_rescision` INT NOT NULL DEFAULT 30 COMMENT 'Días de anticipación para notificar no renovación'
* `tiene_renovacion_automatica` TINYINT(1) NOT NULL DEFAULT 0
* `requiere_certificacion_avance` TINYINT(1) NOT NULL DEFAULT 0
* `pct_anticipo_financiero` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT '% de anticipo a amortizar en certificados'
* `pct_fondo_reparo` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT '% de fondo de reparo / retención de garantía'
* `responsable_tecnico_id` INT(11) NULL COMMENT 'Usuario habilitado para aprobar certificaciones'
* `archivo_contrato_adjunto` VARCHAR(255) NULL COMMENT 'PDF del contrato firmado'

### 5.2 Nueva Tabla: `gestion__ordenes_compra_certificaciones`
Registra los certificados emitidos e hitos aprobados contra el contrato:
* `certificacion_id` INT(11) AUTO_INCREMENT PRIMARY KEY
* `empresa_id` INT(11) NOT NULL
* `orden_compra_id` INT(11) NOT NULL
* `nro_certificado` INT(11) NOT NULL COMMENT 'Correlativo por contrato (1, 2, 3...)'
* `fecha_medicion` DATE NOT NULL
* `periodo_mes` VARCHAR(7) NULL COMMENT 'AAAA-MM'
* `avance_acumulado_pct` DECIMAL(5,2) NOT NULL DEFAULT 0.00
* `avance_periodo_pct` DECIMAL(5,2) NOT NULL DEFAULT 0.00
* `monto_bruto_periodo` DECIMAL(14,2) NOT NULL DEFAULT 0.00
* `descuento_anticipo` DECIMAL(14,2) NOT NULL DEFAULT 0.00
* `fondo_reparo_retenido` DECIMAL(14,2) NOT NULL DEFAULT 0.00
* `monto_neto_facturable` DECIMAL(14,2) NOT NULL DEFAULT 0.00
* `tabla_estado_registro_id` INT(11) NOT NULL DEFAULT 3 COMMENT 'Id genérico: 3 Borrador / 4 En revisión / 5 Aprobado / 12 Facturado / 6 Eliminado / 38 Anulado'
* `usuario_aprobacion_id` INT(11) NULL
* `fecha_aprobacion` DATETIME NULL
* `factura_proveedor_id` INT(11) NULL COMMENT 'Comprobante de compra que absorbe el certificado'
* `observaciones_tecnicas` TEXT NULL
* `archivo_adjunto` VARCHAR(255) NULL COMMENT 'Memoria descriptiva o acta de medición firmada'
* Auditoría estándar (`fecha_creacion`, `fecha_modificacion`, `usuario_creacion_id`, `usuario_modificacion_id`)

---

## 6. Integración con el Portal de Proveedores

1. **Catálogo de Contratos del Proveedor:** Al autenticarse, el proveedor visualiza sus contratos activos, vigencia restante, techo presupuestario y saldo consumido.
2. **Presentación de Medición:** Si el contrato exige certificación, el proveedor presenta su propuesta de avance físico con adjuntos técnicos.
3. **Restricción de Facturación:** El portal bloquea el ingreso de comprobantes si:
   - El contrato está vencido o en período de preaviso bloqueado.
   - El importe supera el saldo disponible del contrato.
   - Si el contrato exige certificación y no existe un Certificado en estado `Aprobado` listo para facturar.
4. **Three-Way Match Garantizado:** La factura ingresada se vincula directamente a `orden_compra_id` y `certificacion_id`, impidiendo inconsistencias contables o reclamos posteriores.
