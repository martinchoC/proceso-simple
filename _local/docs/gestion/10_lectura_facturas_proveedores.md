# Facturas de proveedores: carga desde archivo (PDF / JPG / PNG)

Gestión (módulo 2, página 57) y Gestión Normal (módulo 5, página 557), `facturas_proveedores.php`. Botón **Cargar
desde archivo**, visible con el permiso de **Agregar** (funciones 233 / 5233). El sistema lee el comprobante, abre el formulario de siempre pre-cargado con
el documento a la izquierda, y el usuario revisa y guarda con **Guardar Factura**: la factura queda en **Borrador**
y se confirma después como cualquier otra. Nunca se guarda sola.

Desde el 2026-10-30 corre en los dos módulos con un solo código: el modelo de lectura está en
`core/facturas_lectura/fp_lectura_model.php` y el JS en `assets/js/facturas-proveedores-lectura.js`. Cada módulo
tiene un `facturas_proveedores_lectura_model.php` que solo fija `FPL_PAGINA_IDX` (57 / 557) y lo incluye. La página,
el JS y el `_ajax.php` de facturas de proveedores son iguales en los dos salvo los ids de módulo y página.

## Archivos

| Archivo | Qué hace |
|---|---|
| `_local/sql/mantenimiento/20261027_fp_lectura_archivo_01.sql` | `f_vto_CAE`, switch y tope de IA en `gestion__empresas_parametros`, tablas `gestion__facturas_proveedores_adjuntos` y `gestion__documentos_extracciones`. Reanudable, MariaDB y MySQL 8. Correr antes de subir el PHP |
| `core/facturas_lectura/FacturaExtractor.php` | `fl_config()`, utilidades (CUIT módulo 11, importes, fechas, códigos ARCA), `DocumentoFactura`, `ResultadoExtraccion` (esquema normalizado: valor + confianza + capa por campo), interfaz `CapaExtraccion`, `ValidadorFactura`, orquestador `FacturaExtractor` |
| `core/facturas_lectura/CapaQrArca.php` | Capa 1: QR de ARCA (contenido de jsQR o `zbarimg`) |
| `core/facturas_lectura/CapaTextoPdf.php` | Capa 2: `pdftotext -layout` o el texto de pdf.js |
| `core/facturas_lectura/CapaOcr.php` | Capa 3: `tesseract -l spa` con preprocesado GD, o el texto de tesseract.js |
| `core/facturas_lectura/ParserTextoFactura.php` | Interpreta el texto (capas 2 y 3) |
| `core/facturas_lectura/CapaIa.php`, `ProveedorIaClaude.php` | Capa 4: interfaz `ProveedorIa` + Claude por HTTP (`curl`), salida forzada a JSON por esquema |
| `core/csrf.php` | Token CSRF por sesión (`csrf_token()`, `csrf_exigir()`) |
| `core/facturas_lectura/fp_lectura_model.php` (+ `modules/gestion*/facturas_proveedores_lectura_model.php`, que fijan la página) | Lo que depende de la empresa: archivo, log, tope de IA, propuesta para el formulario, alta del proveedor, vínculo del adjunto, guardado en Borrador |
| `assets/js/facturas-proveedores-lectura.js` | Lectura en el navegador, subida, lote, panel del documento, resaltado de campos (lo cargan las dos páginas) |
| `facturas_proveedores.php` / `.js` / `_ajax.php` | Botón, modal, panel; `fpPrecargarDesdeArchivo()`; acciones `lectura_*` y vínculo del adjunto en `agregar` |
| `empresas_parametros*` (módulos 2 y 5) | Switch "Leer facturas de proveedores con IA" y "Lecturas con IA por día" |
| `assets/js/pdfjs-dist-3.11.174`, `jsqr-1.4.0`, `tesseract.js-5.1.1`, `tesseract.js-core-5.1.1`, `tesseract.js-data-spa-4.0.0` | Librerías locales (≈ 13 MB; se cargan recién cuando se usan) |
| `_local/docs/gestion/10_lectura_facturas_proveedores_config.example.php` | Configuración de ejemplo (sin claves) |

## Cómo funciona

1. El navegador lee lo que puede: el texto de las primeras 3 páginas con pdf.js, la primera página como imagen y el
   QR con jsQR. Si no hay texto (imagen o PDF escaneado), hace OCR con tesseract.js, con escala de grises y
   contraste. Después sube el archivo con eso (`lectura_procesar`, POST con CSRF).
2. El servidor guarda el archivo: tipo real por finfo, hasta 10 MB, nombre aleatorio, en
   `uploads/facturas_proveedores/<empresa>/` con `.htaccess` que niega el acceso. Después corre las capas en orden,
   y cada una usa el binario del servidor si está o, si no, lo que mandó el navegador:
   QR → texto del PDF → (si no hubo texto) OCR. Se corta cuando los datos validan. El QR prevalece sobre el texto y se avisa
   cuando no coinciden.
3. Si faltan obligatorios o la validación falla, entra la **IA**, solo si hay API key, la empresa la tiene
   habilitada y no llegó al tope del día. Corrige lo que leyeron el texto o el OCR, pero el QR sigue prevaleciendo.
4. Validaciones:
   - CUIT con dígito verificador y distinto del de la empresa.
   - Receptor = la empresa (aviso si no).
   - Neto + IVA + no gravado + exento + percepciones + tributos = total, con tolerancia de $0,05.
   - Fecha no futura y CAE de 14 dígitos con vencimiento posterior a la emisión.
   - Factura C sin IVA; B o C sin IVA discriminado van a no gravado.
   - Solo pesos.

   Contra la base:
   - tipo de compras por código ARCA;
   - proveedor por CUIT;
   - duplicado (proveedor + tipo + PV + número, sin las eliminadas);
   - alícuotas configuradas.
5. Propuesta para el formulario:
   - **Ítems:** primero se busca cada ítem por el código del proveedor (`gestion__productos_proveedores.codigo_proveedor`
     de ese proveedor). Los códigos se comparan sin separadores (`fplCodigoClave`: "16791/I" = "16791-I"); si dos
     vínculos dan la misma clave gana el que coincide tal cual, y si no el de menor `producto_id`. Los que tienen
     producto asociado van como líneas propias.
   - **Resto:** lo que no tiene código asociado se resume en una línea por alícuota con el **último producto de las
     compras anteriores del proveedor**: la última línea de su última factura no eliminada, controle stock o no
     (`fplUltimoProductoProveedor`). Si ese producto controla stock, se avisa que la línea va con cantidad 1. Sin
     compras anteriores, "Gastos varios". La importación de ARCA sigue con su regla, que usa solo productos sin stock.
   - Si los ítems asociados suman más que el neto de su alícuota, se descarta la asociación y queda una línea por
     alícuota.
   - **Proveedor sin ítems** (2026-11-07, `gestion__entidades.compra_lectura_items = 0`, script
     `20261107_fp_lectura_items_proveedor_01.sql`): para facturas de servicios (teléfono, gas, luz) no se asocian
     ítems y queda una línea por alícuota con el producto de imputación, aunque la lectura traiga ítems (se muestran
     igual como referencia). Se cambia con el interruptor "Cargar los ítems producto por producto" del panel (acción
     `lectura_items_proveedor`: guarda la preferencia del proveedor que salió de la lectura y rearma la propuesta) o en
     la ficha del proveedor (Proveedores > Observaciones Generales). Vale también para el lote y el cron. Código en
     `core/facturas_lectura/fp_lectura_preferencias.php`; sin la columna se lee todo, como antes.
   - **Descuento general al pie** (2026-11-07): la IA lee `descuento_general` (importe) y `descuento_general_pct`
     aparte, sin repartirlo en los renglones. Si con IVA discriminado los ítems gravados suman más que el neto (están a
     precio de lista), `fplDescuentoGeneral` toma el % leído (o importe / suma de los ítems, 2 decimales) y las líneas
     de ítems van con precio de lista + `descuento_general_pct` (costo = neto); el campo "Desc. general %" del
     formulario lo muestra y el descuento del proveedor no lo pisa (`fpLecturaEnCurso`). El panel muestra por ítem
     precio de lista y "precio a importar". Si los ítems ya suman el neto, el descuento leído no se aplica. Los
     centavos de redondeo (hasta 0,01 por línea) no generan otra línea.
   - **Otros tributos:** se suman a no gravado, con aviso.
   - **Percepciones:** la de IVA va a la configuración de la empresa. La de IIBB va a la configuración de la provincia
     leída; si no hay provincia o configuración, queda pendiente para elegirla en el panel.
6. Panel del documento:
   - qué capas leyeron;
   - datos leídos con confianza y capa;
   - errores con su motivo y avisos;
   - "Dar de alta el proveedor" (con confirmación);
   - "Reintentar con IA" (con confirmación);
   - percepciones pendientes e ítems.

   En el formulario, los campos con confianza media o baja se ven en amarillo o rosado, y los que fallan en rojo con
   el motivo.
7. Al guardar, `agregar` liga el adjunto a la factura nueva y le copia el CAE y su vencimiento de la lectura (los
   toma el servidor). Al editar una factura con archivo, el panel muestra el original.

### Varios archivos o una carpeta (2026-10-30)

La ventana acepta varios archivos, una carpeta elegida con "Elegir una carpeta" o una carpeta arrastrada (con sus
subcarpetas). Con un solo archivo sigue el flujo de arriba.

Con varios, se leen de a uno (en el navegador y en el servidor, igual que uno solo) y se listan en una tabla con el
comprobante, el proveedor, el total y el estado:

| Estado | Qué significa |
|---|---|
| Listo | Sin errores, con proveedor cargado, sin percepciones pendientes ni duplicado |
| Revisar | Hay algo para corregir; se ven los primeros motivos |
| No se pudo leer | El archivo no se pudo leer |
| Guardada en Borrador | La factura ya está creada |

- **Guardar los listos en Borrador** (acción `lectura_guardar_lote`, con CSRF y permiso de Agregar): crea las facturas
  sin pasar por el formulario, con `fplGuardarBorrador`:
  - sucursal elegida arriba de la tabla;
  - condición de pago del proveedor (o la de la empresa) para el vencimiento;
  - moneda base;
  - las líneas y percepciones de la propuesta;
  - archivo y CAE ligados.

  Si un archivo falla, no corta a los demás.
- **Revisar** abre el formulario con esa fila. Al guardar, la fila queda como guardada, y al cerrar el formulario se
  vuelve a la tabla.

### Cron: carpeta de entrada (2026-10-30)

`cron/facturas_proveedores_lectura.php` (CLI). Por cada empresa configurada en `'cron' => ['empresas' => [...]]` de
la configuración (o `--empresa=N --dir=… --usuario=…`), procesa los archivos de la carpeta de entrada:

- **Listo:** se guarda en Borrador con `fplGuardarBorrador` y el archivo se mueve a `<entrada>/procesados/`.
- **No listo** (incluye proveedor no cargado, duplicado, tipo de archivo no válido o sin datos leídos): se mueve a
  `<entrada>/no_procesados/` con `<archivo>.motivo.txt`. El cron no da de alta proveedores.

Detalles de la corrida:

- El usuario configurado tiene que tener permiso de Agregar en Facturas de proveedores de esa empresa (página 57 en
  el módulo 2, 557 en el 5); es el autor de las facturas.
- Módulo: `'modulo' => 2|5` en la configuración de la empresa o, si falta, el módulo activo de la empresa
  (`conf__empresas_modulos`) en el que el usuario tiene ese permiso (primero el 5). Los modelos de facturas de los dos
  módulos no se pueden cargar juntos: cada corrida trabaja con uno y, si hay empresas de los dos, el script se relanza
  con `--modulo=2` y `--modulo=5`.
- Hay una sola corrida por empresa a la vez (`GET_LOCK`).
- `--simular` lee y muestra sin guardar facturas ni mover archivos.
- El cron no tiene navegador: lee el QR, el texto y el OCR **solo con los binarios del servidor**, más la IA si la
  empresa la tiene habilitada. Sin binarios ni IA solo procesa PDF con texto, y las fotos quedan en `no_procesados`.

Probado sobre la copia del simulador con una factura válida (procesada: Borrador con sucursal, condición de pago,
espejo, línea, adjunto y CAE), proveedor nuevo, `.txt`, imagen sin texto y la misma factura repetida (duplicado).
Las cuatro últimas quedaron en `no_procesados` con su motivo.

Log: cada lectura deja una fila en `gestion__documentos_extracciones` con empresa, usuario, adjunto, capa,
proveedor y modelo de IA, tokens, costo estimado, duración, resultado y el resultado normalizado. El tope diario se
cuenta con las filas `capa = 'IA'` del día de la empresa que paga la IA (`empresa_ia_id`, desde el 2026-11-09).

**Bandeja de documentos** (2026-11-09, `15_bandeja_captura.md`): todo archivo que entra por la pantalla o por el cron
queda además en `gestion__bandeja_documentos` (páginas 141 / 641 bajo Compras) con su estado (Listo, Para revisar,
Guardado, Repetido...), y se puede leer, guardar en Borrador o descartar desde ahí.

## Instalación en producción

1. Correr `20261027_fp_lectura_archivo_01.sql`, `20261030_fp_lectura_ia_clave_empresa_01.sql` y
   `20261107_fp_lectura_items_proveedor_01.sql`.
2. Subir el código y las carpetas de `assets/js` nuevas.
3. Opcional, recomendado si el servidor es un VPS Debian o Ubuntu:
   `apt install poppler-utils zbar-tools tesseract-ocr tesseract-ocr-spa`. Sin eso funciona igual con la lectura del
   navegador; con los binarios lee también el servidor, que es más preciso en OCR y no depende del equipo del usuario.
4. Para la IA, la clave de la API va por empresa en **Parámetros de Empresa** (campo "Clave de la API de la IA"):
   `gestion__empresas_parametros.ia_api_key_cifrada`, cifrada con `core/secretos.php` (AES-256-GCM, clave maestra
   `SINDI_CLAVE_CIFRADO` del `.env`, que tiene que estar definida en producción). Es de la empresa: cargarla o quitarla
   afecta a todas sus vigencias, y si la vigente no la tiene (creada desde Configuración > Empresas) vale la última
   cargada (`fplIaClaveEmpresa`). Nunca vuelve al navegador: los `obtener*` de parámetros la reemplazan por
   `ia_api_key_cargada` + `ia_api_key_mascara` (`ocultarIaClaveEmpresa`); vacía en el formulario = se conserva. La
   copia del asistente de inicio no la lleva. Sin clave de la empresa se usa la del servidor (configuración de ejemplo
   fuera del webroot con `define('FACTURA_LECTURA_CONFIG', '...')` en el `.env`, o `ANTHROPIC_API_KEY`), que sigue
   sirviendo para modelo, esfuerzo, timeout, etc. Después habilitarla por empresa en Parámetros de Empresa.
5. Revisar que `upload_max_filesize` y `post_max_size` sean al menos 12M, y `max_execution_time` 240 o más (la acción
   lo pide con `set_time_limit`).

## Batería de pruebas manuales

Antes de empezar, empresa con proveedor cargado (CUIT válido) y percepciones de IVA y de IIBB configuradas en la
página 73 / 573.

| # | Caso | Pasos | Resultado esperado |
|---|---|---|---|
| 1 | Factura A electrónica con QR (PDF de "Comprobantes en línea" de ARCA) | Cargar el PDF | Capas "QR ARCA" + "Texto PDF". Proveedor, Factura A, PV, número, fecha y CAE con confianza alta. Una línea por alícuota con IVA, percepciones en la solapa de impuestos. Panel en verde. Guardar: Borrador, con el CAE y el adjunto (al editarla se ve el PDF) |
| 2 | Factura B (la empresa es Responsable Inscripto) | Cargar el PDF | Factura B, todo el importe en una línea "No gravado" (no se computa IVA), total igual al del comprobante |
| 3 | Factura C de un monotributista | Cargar el PDF | Factura C, línea "No gravado" por el total. Si el texto trae IVA: error "Una Factura C no discrimina IVA" |
| 4 | PDF escaneado (sin texto) | Cargar el PDF | Sin "Texto PDF": OCR en el navegador ("Leyendo la imagen (OCR)… %"), o en el servidor si tiene tesseract. Capa "OCR", confianza baja o media, campos resaltados. Si no alcanza y la IA está habilitada, entra la IA y la capa dice "IA" |
| 5 | Foto de celular torcida o con sombra (JPG) | Cargar el JPG | QR leído si se ve nítido (zonas y cuadrantes). OCR con aviso de baja precisión si la confianza es < 60 %. "Reintentar con IA" disponible si está habilitada |
| 6 | Nota de crédito A | Cargar el PDF | Tipo "Nota de Crédito A" y aviso "elija después el comprobante asociado". Importes positivos (el signo lo pone el tipo) |
| 7 | PDF sin QR (otro sistema de facturación) | Cargar el PDF | Solo "Texto PDF". Número de "0001-00001234", letra del recuadro o "FACTURA A". Si los componentes no dan el total: error en rojo con la diferencia, panel en amarillo |
| 8 | Archivo no válido (.docx renombrado a .pdf, imagen dañada, > 10 MB) | Cargar el archivo | Rechazo: "El archivo tiene que ser PDF, JPG o PNG", "La imagen está dañada…" o "no puede superar 10 MB". No se crea adjunto |
| 9 | Comprobante ya cargado | Cargar de nuevo el PDF del caso 1 | Error "Este comprobante ya está cargado (Borrador)" en el número, y aviso de que el mismo archivo ya está adjunto. Al guardar, el alta lo rechaza |
| 10 | Proveedor inexistente | Cargar una factura de un CUIT no cargado | "El proveedor no está cargado" + "Darlo de alta". Confirmar: se crea con rol proveedor, sucursal Casa Central, condición de pago por defecto y, si es A/M, Responsable Inscripto. El formulario se vuelve a cargar con el proveedor |
| 11 | CUIT mal leído (dígito verificador) | Texto u OCR con un dígito cambiado | Error "El dígito verificador del CUIT no es correcto". No se busca ni se ofrece alta |
| 12 | QR contra texto | PDF cuyo texto no coincide con el QR (total o número) | Aviso "…el QR de ARCA dice …: se toma el del QR" |
| 13 | Percepción IIBB sin provincia | Comprobante con "Percepción IIBB" sin nombre de provincia | Pendiente en el panel con un combo de las configuraciones de IIBB. "Agregar" la suma a la solapa de impuestos |
| 14 | Comprobante en dólares | QR o texto con moneda DOL | Error "Por ahora solo se cargan comprobantes en pesos" |
| 15 | IA deshabilitada / tope | Empresa con IA deshabilitada: marcar "Leer con IA". Con tope 1: leer dos veces con IA | "La lectura con IA está deshabilitada para esta empresa". Con tope: "Se alcanzó el tope de 1 lecturas con IA de hoy". El log tiene una fila IA por llamada |
| 16 | Sin permiso de Agregar | Usuario con perfil sin la función 5233 | No ve el botón. Llamar a `lectura_procesar` a mano: 403 |
| 17 | Aislamiento | Con la empresa A, pedir `lectura_archivo&adjunto_id=` de un adjunto de la empresa B | 404 "Archivo no encontrado". Abrir `uploads/facturas_proveedores/...` por URL: 403 |

Probado en local por línea de comandos (sin navegador), sobre la empresa 6. Funcionaron bien los casos 1 (QR + texto
del navegador y `pdftotext` del servidor), 3, 6, 7, 9, 10, 11, 12, 13 y 14. La IA se probó contra un servidor que
simula la API: pedido bien armado, lectura de la respuesta, tope diario y log con tokens y costo. Falta probar en el
navegador: pdf.js, jsQR, tesseract.js, el panel y el guardado real.

## Qué quedó sin implementar

- Ítems sin código del proveedor: se muestran como referencia, no se asocian a productos por descripción.
- Comprobante asociado de NC/ND: lo elige el usuario (no se lee el número del comprobante que anula).
- Moneda extranjera (decisión: solo pesos).
- Deskew real (rotación de 90° / 180° o inclinación fuerte): solo escala de grises y contraste. Tesseract corrige la
  inclinación leve.
- Constatación del CAE contra ARCA (WSCDC): el QR y el CAE se leen, pero no se verifica que el comprobante exista.
  Se puede sumar con la integración WSAA que ya existe.
- Limpieza de adjuntos huérfanos: los que se leyeron y nunca se guardaron quedan con `factura_proveedor_id NULL`.
- Exportar o ver el log de lecturas desde una pantalla (hoy solo en la tabla).

## Riesgos conocidos

- **Privacidad:** con la IA habilitada, el documento se envía a Anthropic. Está deshabilitada por defecto, se
  habilita por empresa y el modal avisa.
- **Parser de texto:** además de las etiquetas, lee el código de barras de los comprobantes autorizados (RG 1702:
  CUIT + tipo + PV + CAE + vencimiento, 39 o 40 dígitos), que manda sobre lo leído por etiquetas; la tabla de
  totales horizontal (etiquetas en una fila, valores en la de abajo, con "NaN" o "-" en las vacías; solo si el total
  cierra), y la cantidad entera adelante del código ("10  7800/3  …") si cantidad × precio = importe. Si los ítems no
  suman el neto (PDF con los precios corridos de renglón, p. ej. Cromosol), los arma de a pares precio / importe que
  cumplan la cuenta (`itemsPorCuenta`). Es heurístico; un formato muy distinto puede leer mal un importe. La aritmética lo marca casi
  siempre, pero dos errores que se compensen pasarían. Por eso se revisa siempre antes de guardar.
- **Redondeo:** el formulario recalcula el IVA por línea (neto × %). Puede diferir en centavos del IVA impreso; se
  avisa con la diferencia.
- **OCR en el navegador:** depende del equipo del usuario (≈ 13 MB la primera vez, después queda en caché) y es más
  lento en celulares.
- **Tope de IA:** se cuenta por filas del log, sin bloqueo. Dos lecturas simultáneas en el límite pueden pasarlo en uno.
- **Costo estimado:** usa los precios de la configuración; hay que actualizarlos si cambian.
- **"Gastos varios":** si la empresa no lo tiene, la lectura lo da de alta (como la importación de ARCA) aunque
  después no se guarde la factura.
- **Datos del navegador:** el QR y el texto que manda el navegador no son confiables. El servidor los vuelve a
  validar todos, pero un usuario podría mandar un QR armado: el QR no prueba que el comprobante exista (ver WSCDC).
