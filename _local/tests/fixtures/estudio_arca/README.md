# Clientes externos del estudio: archivos de ARCA para probar la importación

Datos **ficticios**, generados con `_local/tools/estudio_arca_fixtures/generar.py` (semilla fija: correrlo otra vez da los mismos archivos).
Período: agosto a octubre de 2026. El estudio es el tenant de la empresa 2.

## Qué hay

| Carpeta | Cliente | CUIT | Condición | IIBB | Emitidos / Recibidos (ago-sep-oct) |
|---|---|---|---|---|---|
| `01_ferreteria_del_valle` | Ferretería del Valle SRL | 30-71844214-8 | RI | Río Negro local | 27/10 · 28/12 · 27/11 |
| `02_transportes_patagonicos` | Transportes Patagónicos SA | 30-70992381-8 | RI | CM: RN 62 % + BA 38 % | 10/11 · 11/14 · 11/14 |
| `03_agro_insumos_carmen` | Agro Insumos Carmen SAS | 30-71633057-1 | RI | Buenos Aires local, agente de percepción | 20/9 · 20/7 · 23/9 |
| `04_lucia_fernandez` | Fernández, Lucía Belén | 27-31455820-6 | Monotributo D | Río Negro | 11/4 · 12/3 · 12/4 |
| `05_martin_gomez` | Gómez, Martín Alejandro | 20-28761934-1 | Monotributo H | Buenos Aires | 24/14 · 22/12 · 22/14 |

En cada carpeta, un archivo por mes y sentido, como los baja la web de ARCA:
`Mis Comprobantes Emitidos - CUIT <cuit> - AAAA-MM.xlsx` / `.csv` y lo mismo con `Recibidos`.

- **.xlsx**: copia exacta del formato real (comparado con `_local/docs/Mis Comprobantes * - CUIT 30719136482.xlsx`). Fila 1 = título con el CUIT, fila 2 = encabezados `Fecha`, `Tipo` ("1 - Factura A"), `Punto de Venta`, `Número Desde/Hasta`, `Cód. Autorización`, documento y denominación de la contraparte (en Recibidos también el receptor), `Tipo Cambio`, `Moneda`, `IVA x%` + `Neto Grav. IVA x%` por alícuota (vacías si no se usan), `Neto Gravado Total`, `Neto No Gravado`, `Op. Exentas`, `Otros Tributos`, `Total IVA`, `Imp. Total`. Fecha en texto dd/mm/aaaa e importes numéricos.
- **.csv**: los mismos encabezados, `;` como separador, coma decimal, UTF-8 con BOM, sin fila de título.

Otros archivos:
- `clientes.csv`: padrón de los 5 (condición, categoría de monotributo, jurisdicciones, cierre, actividad).
- `alta_clientes_estudio.sql`: alta en `estudio__clientes` + `estudio__clientes_jurisdicciones` (empresa 2) de estos 5 **y** de los 3 clientes que usan el ERP en otra instancia (ver `_local/docs/estudio_contable/02_clientes_con_erp_remoto.md`). Se puede volver a correr: no duplica nada. Ya está aplicado en la base local (clientes 4 a 11).
- `esperado.json`: lo que tiene que dar la importación, por cliente y mes: cantidades, ventas en pesos, neto gravado, débito fiscal, crédito fiscal computable (solo A recibidas por un RI), otros tributos sufridos, ventas en moneda extranjera, la lista de casos borde y los **cruces**.

## Casos borde a propósito

- **Cruces entre clientes del estudio** (8): la Ferretería le vende a Transportes (A) y a Lucía (B), Transportes le vende a Agro (A). El mismo comprobante (mismo CAE) está en Emitidos de uno y Recibidos del otro: sirve para controlar que las dos puntas cuadren.
- **Notas de crédito y débito**: NC A propias y de proveedor, ND A (estadía), NC C de la monotributista a la obra social. ARCA las informa con importes positivos: el signo sale del tipo.
- **Rango de comprobantes** (Ferretería, septiembre): una fila de Factura B a consumidor final con `Número Desde ≠ Número Hasta` (12 comprobantes).
- **Moneda extranjera** (Transportes, octubre): Factura E en `DOL` con tipo de cambio 1.412,50.
- **Otros Tributos**: percepciones IIBB en compras (3 %), percepción IVA + IIBB sumadas en una sola columna (Agro: ARCA no las separa ni dice la provincia), venta propia con percepción ARBA practicada.
- **Alícuotas mezcladas**: 10,5 % + 21 % en el mismo comprobante, 27 % en telefonía y energía.
- **No gravado y exento**: combustible (impuesto a los combustibles en No Gravado), seguro (exento), venta exenta de semilla.
- **Comprobantes que no dan crédito fiscal**: Factura C de monotributistas recibida por un RI; Factura B recibida por un monotributista (ARCA igual informa neto e IVA); Factura A a un monotributista (RG 5003).
- **Consumidor final**: con DNI y sin identificar (`Sin identificar` / 0).
- **Compra grande** (neumáticos, $ 4,7 M): queda para que el contador decida si es bien de uso.

## Cómo se usaría

Hoy el estudio no tiene importador: mini-gestión (`minigestion.php`, modo MANUAL) carga a mano en `estudio__minigestion_comprobantes`, que guarda **una sola alícuota** por comprobante y no tiene neto no gravado separado del exento ni el CAE. Los importadores que leen este formato están en Gestión Normal (módulo 5), para la empresa misma: `fpImportarComprobantesArca` (Recibidos) y `ventas_facturas_arca_import_model.php` (Emitidos). Si se construye el del estudio, conviene reusar su lectura de columnas (`mapearColumnasArca` de `facturas_proveedores.js`) y guardar el detalle por alícuota.
