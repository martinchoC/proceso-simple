# 15 — Bandeja de captura de documentos (recepción cliente → estudio y facturas / gastos con IA)

Fecha: 2026-11-09. Estado: **fase 1 implementada; fase 2.1 enlaces externos y portal implementado**; checklist y fases 3–5 en diseño.

## Fase 2.1: Enlaces externos y portal público (2026-11-09)

Script `_local/sql/mantenimiento/20261109_bandeja_documentos_03_enlaces.sql`:
- Tabla `gestion__bandeja_enlaces`: `enlace_id`, `empresa_id`, `estudio_cliente_id`, `token_hash` (SHA-256), `token_prefijo`, `etiqueta`, `f_vencimiento`, `activo`, `max_archivos_dia`, `max_bytes_archivo`, `creado_por`, `fecha_alta`.
- Columnas en `gestion__bandeja_documentos`: `enlace_id`, `requerimiento_id`, y `usuario_id` nullable para cargas externas sin sesión.
- Tabla 457 registrada en `conf__tablas`.

| Archivo | Qué hace |
|---|---|
| `public/subir_documento.php` | Endpoint y portal web público (mobile y desktop) de subida sin sesión. Valida token criptográfico (`?t=...`), MIME real con finfo (PDF, JPG, PNG), tamaño y cupo diario. Arrastrar y soltar, selector de archivos/fotos, y feedback en tiempo real. |
| `core/bandeja/bandeja_service.php` | `bdj_enlace_crear` (genera token seguro de 32 bytes y guarda su hash), `bdj_enlace_listar`, `bdj_enlace_revocar`, `bdj_enlace_validar` y `bdj_enlace_url`. |
| `core/bandeja/bandeja_ajax.php` | Acciones `enlaces_listar`, `enlace_crear` y `enlace_revocar`. |
| `templates/bandeja/bandeja_documentos_vista.php` | Botón "Enlaces de subida", opción de filtro "Portal / Enlace externo", y modal `#modalBdjEnlaces` con generación de links, botón de copiado al portapapeles y revocación. |
| `assets/js/bandeja-documentos.js` | Lógica de visualización del badge "Portal / Cliente", apertura del modal, formulario de creación y revocación de enlaces. |

Probado end-to-end: generación de token, validación, subida con curl y desde portal, control de MIME, cupo diario, revocación inmediata (403), y visualización unificada en la bandeja con su enlace y estado RECIBIDO.

## Fase 1: lo implementado (2026-11-09)

Script `_local/sql/mantenimiento/20261109_bandeja_documentos_01.sql` (correr **antes** de subir el código: el log de
lecturas pasa a escribir `empresa_ia_id`). Probado en `gestion_multipyme_sim`, dos veces seguidas (reanudable).

| Archivo | Qué hace |
|---|---|
| `core/bandeja/bandeja_service.php` | `bdj_registrar` (alta + DUPLICADO / DESCARTADO por hash), `bdj_leyendo`, `bdj_lectura`, `bdj_procesado`, `bdj_listar`, `bdj_resumen`, `bdj_descartar`, `bdj_archivo_ruta` |
| `core/bandeja/bandeja_ajax.php` | `bdj_despachar`: `resumen`, `listar`, `opciones`, `archivo`, `subir`, `leer`, `guardar`, `descartar` ({success, data, message}; escrituras POST + CSRF + `permisos_exigir_accion`; `guardar` exige además "Agregar" de Facturas de Proveedores) |
| `templates/bandeja/bandeja_documentos_vista.php`, `assets/js/bandeja-documentos.js` | Pantalla ui-simple: indicadores, solapas Pendientes / Guardados / Descartados y repetidos / Todos, filtros, subir varios (se suben y después se leen de a uno), leer, guardar en Borrador, descartar, ver archivo, "Revisar" |
| `modules/gestion*/bandeja_documentos.php` / `_ajax.php` | Solo fijan la página (141 / 641) e incluyen el modelo de facturas y el de lectura del módulo |
| `core/facturas_lectura/fp_lectura_model.php` | Ganchos: `fplGuardarArchivoDesdeRuta` registra (PANTALLA / CARPETA), `fplProcesar` pasa por LEYENDO y deja LISTO / REVISAR / ERROR, `fplPropuestaGuardada` actualiza el estado (p. ej. REVISAR → LISTO al dar de alta el proveedor), `fplVincularAdjunto` marca PROCESADO, `fplGuardarBorrador` deja REVISAR con el motivo si el alta falla, `fplLog` guarda documento / `empresa_ia_id` / cliente y devuelve el id, y el tope se cuenta por `empresa_ia_id` |
| `assets/js/facturas-proveedores-lectura.js` | `facturas_proveedores.php?lectura_adjunto=N` abre el formulario con la última lectura de ese archivo ("Revisar" de la bandeja) |

La pantalla "Cargar desde archivo" y el cron no cambian lo que muestran ni lo que hacen: solo escriben además en la bandeja.
Un archivo repetido se sigue leyendo en esas dos entradas (como antes); la página de la bandeja, en cambio, no lee los que
quedan DUPLICADO. Un archivo cuya factura se eliminó no cuenta como repetido.

Probado (simulación, módulos 2 y 5, IA deshabilitada en la copia para no gastar; la lectura de IA se simuló con
`fplLog` y los datos de una lectura real): resumen / listar / opciones; archivo propio y de otra empresa (404); descartar
sin CSRF, por GET, sin la función (403) y un PROCESADO (0); perfil sin la página (403); volver a subir un archivo sin
terminar (el anterior queda DESCARTADO) y uno ya guardado (DUPLICADO con `duplicado_de_id`); lectura sin IA → ERROR;
lectura → REVISAR con motivos y → LISTO; guardar desde la bandeja en los dos módulos (Borrador con sucursal, CAE, adjunto
y espejo; el documento PROCESADO con destino; un segundo intento se rechaza); guardar uno no listo; cron en los dos
módulos (mismos mensajes y carpetas que antes, fila CARPETA). **Falta**: navegador (subir desde la página, que por CLI
no pasa `is_uploaded_file`, y el "Revisar") y una lectura con IA real.

Hallazgo (previo a la bandeja): la propuesta de lectura no cuenta como duplicada una factura eliminada, pero
`agregarFacturaProveedor` sí la rechaza ("Ya existe una factura de este proveedor con este número"). El documento queda
REVISAR con ese motivo.

## Para qué

Hoy un documento entra al sistema de una sola manera: alguien lo sube desde la pantalla de Facturas de proveedores o lo deja
en la carpeta del cron (`10_lectura_facturas_proveedores.md`). Falta:

1. Que el **cliente del estudio** mande sus documentos sin entrar al sistema, y que el estudio vea qué entregó y qué falta.
2. Que la lectura cubra más que facturas de proveedores (tickets / gastos, extractos).
3. Ver y controlar el consumo de IA por empresa (hoy el log existe pero solo se mira en la tabla).

Se resuelve con **una sola bandeja** que reutiliza lo que ya está hecho; no se reescribe la lectura.

## Qué se reutiliza (no se toca)

| Pieza | Dónde |
|---|---|
| Lectura en capas (QR ARCA → texto → OCR → IA) con confianza por campo y validaciones (CUIT, sumas, duplicado tipo + PV + número) | `core/facturas_lectura/FacturaExtractor.php` y capas |
| Guardado en Borrador | `fplGuardarBorrador` (`fp_lectura_model.php`) |
| Clave de IA por empresa cifrada, habilitación y tope diario | `gestion__empresas_parametros` (`ia_lectura_habilitada`, tope), `core/secretos.php` |
| Log de lecturas con tokens y costo | `gestion__documentos_extracciones` |
| Adjuntos de factura | `gestion__facturas_proveedores_adjuntos` |
| Permisos estudio ↔ empresa por área | `conf__empresas_vinculaciones` / `vinculacionesVerificarPermiso` |
| Principio "la IA nunca graba" | propuesta + confirmación humana (`14_asistente_ia.md`) |

**Regla de código:** todo va en `core/` (código único); los archivos de `modules/gestion*` y `gestion_normal*` solo fijan la
página (2 / 5, `pagina_idx + 500`). Cuidado con `facturas_proveedores*`: ya hay deriva entre 2 y 5 (la importación de
comprobantes de ARCA está solo en el 5); la bandeja no debe depender de esa parte.

## Modelo

### Fase 1 — verificado contra la base el 2026-11-09 (script `20261109_bandeja_documentos_01.sql`)

Estructura real de lo existente (`SHOW CREATE TABLE`, local y `gestion_multipyme_sim` iguales):
- `gestion__facturas_proveedores_adjuntos`: `adjunto_id`, `empresa_id`, `factura_proveedor_id` (NULL hasta guardar),
  `archivo_ruta`, `nombre_original`, `mime`, `tamanio_bytes`, `sha256`, `usuario_id`, `fecha_alta`,
  `tabla_estado_registro_id`; índice `(empresa_id, sha256)` **no único** (en local hay 24 adjuntos, 20 repetidos por hash).
- `gestion__documentos_extracciones`: `extraccion_id`, `empresa_id`, `usuario_id`, `adjunto_id` (NOT NULL), `capa`,
  `proveedor_ia`, `modelo`, `tokens_entrada` / `_salida`, `costo_estimado`, `duracion_ms`, `resultado`, `mensaje`, `datos`,
  `fecha`; tope por `(empresa_id, capa, fecha)`. No tiene lugar para el cliente del estudio ni para quién paga.

Ajustes al diseño original:
1. **Sin UNIQUE por hash.** El diseño pedía `UNIQUE (empresa_id, hash)` y a la vez una fila `DUPLICADO`: son
   incompatibles, y además hoy se puede volver a subir un archivo que se leyó y no se guardó. Queda índice simple, la fila
   repetida se registra (`DUPLICADO` con `duplicado_de_id` si el anterior está `PROCESADO`) y las anteriores sin terminar
   del mismo archivo pasan a `DESCARTADO` ("se volvió a subir").
2. **El archivo sigue en el adjunto.** La bandeja copia sus metadatos (mismos nombres: `archivo_ruta`, `nombre_original`,
   `mime`, `tamanio_bytes`, `sha256`) y apunta con `adjunto_id` (UNIQUE): es lo que la factura usa para ver su original.
   Los tipos sin factura (fase 3, clientes sin empresa en el ERP) tendrán bandeja sin adjunto; ahí se relaja
   `gestion__documentos_extracciones.adjunto_id` a NULL.
3. **Quién paga la IA** va en el log: `empresa_ia_id` (clave y tope; = `empresa_id` salvo documentos de clientes del
   estudio) y `estudio_cliente_id`; `empresa_id` sigue siendo la dueña del documento (lo usa `fplUltimaLectura`). El tope se
   cuenta por `empresa_ia_id`. Más `bandeja_documento_id`.
4. `destino_tabla` → `destino_tabla_id` + `destino_registro_id` (id de `conf__tablas`, como `tabla_origen_id` de comprobantes).
5. `estado` propio (VARCHAR, como el ENUM de `gestion__bancos_extracto`), sin motor de estados: los botones de la bandeja son
   fijos (`data-funcion` + `permisos_exigir_accion`). `origen` incluye `ANTERIOR` para lo leído antes de la bandeja.
6. Fuera de fase 1: `enlace_id`, `requerimiento_id` (fase 2), habilitación con constancia y tope por cliente (fase 2 / 4).

Configuración: tabla 456; páginas 141 / 641 "Bandeja de Documentos" bajo el grupo propio "Importación de Comprobantes" (páginas 145 / 645, scripts `20261109_bandeja_documentos_01.sql` y `20261109_bandeja_documentos_02_menu_importaciones.sql`); funciones 3571–3573 /
8571–8573 (Acceder `consultar`, Cargar `agregar`, Descartar `inhabilitar`), heredadas de Facturas de Proveedores
(Visualizar → Acceder; Agregar → las tres). Procesar (guardar en Borrador) exige "Agregar" de Facturas de Proveedores.
Lo ya leído entra como `ANTERIOR`: con factura `PROCESADO`; mismo archivo en una factura `DUPLICADO`; subido de nuevo
`DESCARTADO`; el resto `REVISAR`.

### Diseño original

### `gestion__bandeja_documentos` (una fila por archivo recibido)

| Campo | Notas |
|---|---|
| `bandeja_documento_id` | PK |
| `empresa_id` | empresa dueña del documento. **Siempre** sale de la sesión o del enlace, nunca del request |
| `estudio_cliente_id` | 0 = empresa propia; > 0 = cliente del estudio (mismo criterio que libros societarios) |
| `origen` | `PANTALLA`, `CARPETA`, `PORTAL`, más adelante `WHATSAPP`, `EMAIL` |
| `enlace_id` | enlace externo usado (NULL si entró logueado) |
| `archivo_ruta`, `mime`, `bytes`, `hash_sha256` | archivo fuera del webroot; **UNIQUE (`empresa_id`, `hash_sha256`)** para cortar duplicados exactos |
| `tipo_documento` | `FACTURA_COMPRA`, `GASTO`, `EXTRACTO`, `OTRO`; lo elige el que sube o lo propone la lectura |
| `estado` | ver abajo |
| `motivo` | texto de por qué quedó en REVISAR / ERROR / DUPLICADO |
| `extraccion_id` | FK a `gestion__documentos_extracciones` (capa usada, tokens, costo, resultado normalizado) |
| `destino_tabla`, `destino_id` | qué se creó al confirmar (factura en Borrador, gasto, etc.) |
| `requerimiento_id` | a qué ítem de la checklist responde (opcional) |
| `usuario_id`, `f_alta`, `f_proceso` | auditoría |

Estados: `RECIBIDO → LEYENDO → LISTO | REVISAR | ERROR → PROCESADO | DESCARTADO | DUPLICADO`.
`LISTO` no graba solo: pasa a `PROCESADO` cuando alguien con permiso confirma, o cuando la empresa tiene activado el guardado
automático en Borrador (el mismo "Guardar los listos en Borrador" de hoy).

### `gestion__bandeja_enlaces` (acceso externo para el cliente)

`enlace_id`, `empresa_id`, `estudio_cliente_id`, `token_hash` (solo se guarda el hash; el token se muestra una vez),
`etiqueta`, `f_vencimiento`, `activo`, `max_archivos_dia`, `max_bytes_archivo`, `creado_por`. El enlace **solo puede subir**:
no lista, no descarga, no ve nada de la empresa.

### Checklist (módulo 1)

- `gestion__bandeja_requisitos`: plantilla por cliente (qué se espera cada período: compras, ventas, extracto de cada
  cuenta, retenciones) y periodicidad.
- `gestion__bandeja_requerimientos`: instancia por período (`requisito_id`, `periodo`, `f_limite`, `estado`
  PENDIENTE / RECIBIDO / CONFORME / OBSERVADO). Se generan por cron al inicio de cada período.
- Un documento recibido se asocia al requerimiento (a mano o por tipo + período detectado).

## Flujo

1. **Entrada** (pantalla, carpeta del cron, enlace externo): se valida y se guarda el archivo, se calcula el hash; si ya existe
   para esa empresa → `DUPLICADO`. Sin IA ni lectura todavía: subir es barato y no falla.
2. **Lectura** (cola, de a uno, `GET_LOCK` por empresa): `FacturaExtractor` en sus capas; la IA solo si la empresa la habilitó
   y no llegó al tope. Resultado en `gestion__documentos_extracciones`.
3. **Decisión por confianza**: sin errores, proveedor cargado, sin duplicado → `LISTO`; cualquier duda → `REVISAR` con el motivo.
4. **Confirmación** humana (o guardado automático opcional) → Borrador y `PROCESADO`.
5. **Estudio**: tablero por cliente y período con lo recibido, lo que falta y lo que está para revisar.

## Entrada externa: cómo se protege

- Endpoint propio de subida, sin sesión, bajo `public/`. La empresa se resuelve **solo por el token**.
- Token aleatorio de 32 bytes; se guarda su hash; vencimiento; se puede desactivar. Respuesta genérica ante token inválido.
- Validar el tipo real del archivo (`finfo`, no la extensión): PDF / JPG / PNG; tope de tamaño (hoy 10 MB) y de archivos por día.
- Almacenamiento fuera del webroot o con `.htaccess` deny, igual que `uploads/libros_societarios/`; descarga solo por acción con permiso.
- Límite de intentos por IP y por token.
- El enlace no da acceso a lectura: aunque se filtre, un tercero puede subir basura (acotada por límites), no ver datos.
- Subir un archivo no dispara IA por sí solo: la lectura con IA respeta habilitación y tope diario de la empresa.

## Consumo de IA por empresa y por cliente del estudio

Pantalla de solo lectura sobre `gestion__documentos_extracciones`: por empresa (quien paga) y mes, lecturas por capa, tokens,
costo estimado y fallos. Para el estudio, además, **desglose por cliente** (`estudio_cliente_id`): cuánto consumió cada uno,
que es el dato para decidir si se lo incluye en el honorario o se lo traslada. Cierra el pendiente de la doc 10 ("ver el log
desde una pantalla").
Mejora de paso el riesgo conocido del tope sin bloqueo: contarlo con `GET_LOCK` o `SELECT … FOR UPDATE`.

## Fases

| Fase | Contenido | Depende de |
|---|---|---|
| 1 | Bandeja interna: tabla, estados, pantalla; la pantalla actual y el cron pasan a escribir en ella | — |
| 2 | Enlaces externos + checklist + tablero del estudio | 1 |
| 3 | Tipo GASTO (ticket sin CAE) y EXTRACTO (deriva a `bancos_importar`) | 1 |
| 4 | Pantalla de consumo de IA (por empresa y por cliente del estudio) y tope por cliente | 1 |
| 5 | WhatsApp / email como origen | 2 |

Se empieza por la fase 1: no cambia lo que ve el usuario hoy y las demás se apoyan en ella.

## Decisiones tomadas (2026-11-09)

1. **La IA de un documento que entra por un cliente del estudio la paga el estudio**, y el sistema deja registro de qué cliente
   la usó. Consecuencias de diseño:
   - Se usa la **clave de IA y el tope diario del estudio** (no los del cliente ni los del servidor).
   - Cada lectura guarda `estudio_cliente_id` además de la empresa que la paga (ver "Consumo de IA"). Si
     `gestion__documentos_extracciones` no tiene lugar para eso, se agrega la columna o se resuelve por el vínculo
     `extraccion_id` de la bandeja (a definir al verificar el dump).
   - Como el tope es del estudio, un cliente con mucho volumen puede agotarlo para los demás: se agrega un **tope diario
     opcional por cliente** dentro del tope general (propuesta; apagado por defecto).
   - La habilitación de IA y el aviso de privacidad (el documento viaja a un tercero) los acepta el estudio, y queda
     constancia de quién habilitó y cuándo.
   - Cuando el documento entra por la empresa propia (pantalla o carpeta de una empresa), sigue pagando esa empresa con su clave.
2. **Los clientes del estudio sin empresa en el ERP (nivel 0 y 1) tienen bandeja propia**, como archivo organizado por
   período, sin comprobantes destino: el documento queda archivado y asociado al requerimiento de la checklist
   (`destino_tabla` / `destino_id` vacíos). Si el cliente después se vincula a una empresa, sus documentos pueden pasar a
   procesarse como los demás.

## Decisiones abiertas (para definir antes del DDL)

1. **Retención de archivos** (cuánto tiempo, quién borra) y limpieza de adjuntos huérfanos (ya pendiente en la doc 10).
2. **Guardado automático en Borrador** por empresa: ¿default apagado? (propuesta: apagado).
3. **WhatsApp:** proveedor y reglas de identificación del número (número → usuario / cliente). Se define recién en la fase 5.
4. **Constatación del CAE contra ARCA (WSCDC)**, hoy sin implementar: conviene incluirla antes de automatizar el guardado.
5. **Tope por cliente del estudio:** valor por defecto y si lo fija el estudio por cliente o es uno general.

## Riesgos

- Privacidad: con IA habilitada el documento se envía a un tercero; en el portal el que sube no es quien habilitó la IA.
  La habilitación y el aviso quedan a cargo del estudio, con constancia de quién y cuándo.
- Spam o archivos hostiles por el enlace externo: límites, validación de tipo real y almacenamiento aislado.
- Costos de IA sin control si crece el volumen: tope por empresa y reporte (fase 4) antes de abrir el portal a muchos clientes.
- Deriva 2 vs 5: probar siempre en los dos módulos.
- Mapeo de identidad en WhatsApp: ante duda, el documento va a REVISAR, nunca se imputa solo.

## Pruebas mínimas

Mismo archivo subido dos veces (DUPLICADO) · enlace vencido / desactivado / token inventado · archivo `.exe` renombrado a `.pdf` ·
tope diario de IA alcanzado · documento de otra empresa en la carpeta · dos lecturas simultáneas en el límite del tope ·
cliente del estudio con y sin empresa vinculada · permisos: usuario sin "agregar" no confirma · todo en módulo 2 y en módulo 5.
