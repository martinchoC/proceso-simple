# F4 — Sincronización de clientes con servidor propio (contrato)

Diseño, 2026-11-06. Nada construido. Parte de `02_clientes_con_erp_remoto.md` (opciones A–E y "paquete contable v1") y lo
convierte en un contrato implementable. Donde este documento y el 02 difieren, vale este.

## 1. Alcance y principios

- Aplica a los clientes con `estudio__clientes.origen_datos = 'REMOTO'` (instancia propia de gestion_multipyme, con
  `remoto_url` https). `EXTERNO` (software de terceros) queda fuera: entra por Mis Comprobantes de ARCA o archivos.
- **Espejo de solo lectura.** Lo que llega del cliente no se edita en el estudio. Si algo está mal se corrige en el
  origen y se vuelve a sincronizar. El estudio agrega lo suyo (DDJJ, papeles de trabajo, ajustes) en tablas propias.
- **Un formato, dos transportes.** El mismo paquete versionado viaja por *push* (el cliente lo envía al estudio) o por
  *pull* (el estudio lo pide al endpoint de exportación del cliente). El importador es uno solo.
- **Idempotente por claves naturales.** Importar dos veces el mismo paquete no cambia nada; importar uno más nuevo
  actualiza. Los ids remotos nunca se usan como ids locales: se guardan en una tabla de mapeo.
- **La vinculación es el consentimiento.** Sin una fila vigente en `conf__empresas_vinculaciones` con permiso del área,
  no se exporta ni se importa ese dato (mismo criterio que F0 para clientes locales).

## 2. Transportes

| | Push (recomendado por defecto) | Pull (opcional) |
|---|---|---|
| Quién inicia | Instancia del cliente (botón o cron al cerrar el período) | Estudio (botón "Sincronizar" o cron) |
| Endpoint | En el estudio: `api/estudio/v1/paquetes.php` (POST multipart, ZIP) | En el cliente: `api/export/v1/paquete.php` (GET, devuelve el ZIP) |
| Sirve para | Todos, incluido el cliente sin conexiones entrantes | Instancias con IP pública que prefieren no programar envíos |
| Credencial | Token emitido por el estudio, guardado cifrado en el cliente | Token emitido por el cliente, guardado cifrado en el estudio |

En los dos casos el token está **atado a una vinculación**: la del cliente hacia el estudio, en la instancia que
recibe el pedido. Revocar la vinculación invalida el token.

## 3. Credencial: token atado a la vinculación

Tabla nueva (en las dos instancias: la usa la que recibe pedidos), en lugar de reusar `estudio__vinculaciones`
(sin uso, propia del estudio, no sirve del lado del cliente):

```
conf__empresas_vinculaciones_tokens
  token_id            bigint PK
  vinculacion_id      bigint  FK conf__empresas_vinculaciones (local)
  sentido             enum('EXPORTA','RECIBE')   EXPORTA = esta instancia entrega datos (pull); RECIBE = acepta push
  prefijo             char(8)          primeros 8 caracteres, para identificar el token en logs y pantallas
  token_hash          char(64)         SHA-256 del token; el token se muestra una sola vez al crearlo
  cuit_contraparte    varchar(11)      CUIT que debe venir en el manifiesto (push) / CUIT del estudio que pide (pull)
  areas               varchar(100)     subconjunto de las áreas permitidas por la vinculación ('ventas,compras,...')
  fecha_desde / fecha_hasta, ultimo_uso_at, ultimo_uso_ip
  tabla_estado_registro_id  1 activo / 2 revocado
```

El token del otro lado se guarda cifrado con `core/secretos.php` (como la clave de la IA): en el cliente, en
Parámetros de Empresa (push); en el estudio, en la ficha del cliente (pull). Nunca vuelve al navegador.

Cada pedido lleva:
- `Authorization: Bearer <token>`
- `X-Firma: HMAC-SHA256(token, metodo + "\n" + ruta + "\n" + timestamp + "\n" + sha256(cuerpo))`
- `X-Timestamp`: se rechaza con más de 5 minutos de diferencia.

Validaciones del guard (`core/api_guard.php`, no usa sesión ni `ajax_guard`): token activo y vigente, vinculación
vigente con el área pedida (`vinculacionesVerificarPermiso`), CUIT del manifiesto = `cuit_contraparte`, firma y
tiempo, límite de pedidos por token (p. ej. 60 por hora) y log de cada llamada (tabla de log o
`gestion__integraciones_resultados`). Errores: 401 (token / firma), 403 (sin vinculación o área), 409 (versión de
esquema no soportada), 413 (paquete demasiado grande), 422 (validación), 429 (límite).

## 4. Paquete y esquema versionado

ZIP con `manifiesto.json` + un JSON por entidad (formato del 02, con estos agregados):

```json
{
  "esquema": "paquete-contable",
  "version": "1.0",
  "origen": { "url": "https://erp.cliente.com.ar", "version_sistema": "2026.11", "empresa_cuit": "30719990017" },
  "periodo": { "desde": "2026-10-01", "hasta": "2026-10-31", "cerrado_en_origen": true },
  "generado_at": "2026-11-06T10:00:00-03:00",
  "secuencia": 3,
  "areas": ["ventas", "compras", "impuestos", "contabilidad"],
  "archivos": { "ventas.json": { "registros": 120, "sha256": "..." }, "...": {} },
  "totales_control": { "ventas_neto": 0, "ventas_iva": 0, "compras_neto": 0, "compras_iva": 0 }
}
```

- `version` sigue semver: el importador acepta la misma *major* y cualquier *minor* (las minor solo agregan campos
  opcionales). Un cambio incompatible sube la major y el estudio responde 409 hasta actualizarse.
- Solo viajan las áreas que el token y la vinculación permiten; el resto ni se arma.
- Todo en ISO: fechas `AAAA-MM-DD`, importes con punto decimal, moneda ISO + TC.
- `secuencia` crece por (CUIT, período): un paquete con secuencia menor o igual a la última importada se ignora
  salvo que el hash sea distinto (en ese caso, aviso y se procesa).

## 5. Claves naturales

| Entidad | Clave natural | Notas |
|---|---|---|
| Comprobante de venta | CUIT emisor + tipo ARCA + PV fiscal + número | PV = `codigo_fiscal` (nunca el id). Igual que el importador de Emitidos. |
| Comprobante de compra | CUIT del cliente + CUIT proveedor + tipo ARCA + PV + número | El PV es el del proveedor. |
| Comprobante no fiscal (X, recibos, OP) | CUIT + código del tipo en origen + PV + número | Solo si el área lo incluye. |
| Retención sufrida / practicada | CUIT agente + impuesto + jurisdicción + número de certificado | Sin número (PENDIENTE): clave del comprobante que la origina + línea. |
| Cuenta contable | CUIT + código de cuenta del plan del cliente | Se mapea contra el plan del estudio en un paso aparte. |
| Asiento | CUIT + id remoto del asiento | Los asientos no tienen clave natural: aquí sí se usa el mapeo. |
| Entidad (cliente / proveedor) | CUIT; sin CUIT, documento; consumidor final sin identificar no se mapea | |

Un registro que estaba en un paquete anterior del mismo período y no viene en uno nuevo **no se borra**: queda
marcado como anulado en el espejo con la secuencia que lo dio de baja.

## 6. Tablas del estudio

```
estudio__sync_lotes            lote_id, empresa_id (estudio), cliente_id, transporte (PUSH/PULL), version_esquema,
                               periodo_desde/hasta, secuencia, hash_zip, archivo, token_id, recibido_at,
                               estado (RECIBIDO / IMPORTADO / RECHAZADO / CON_AVISOS), resumen (texto), usuario_id
estudio__sync_mapeo            empresa_id, cliente_id, entidad ('VENTA','COMPRA','RETENCION','ASIENTO','CUENTA',...),
                               clave_natural (varchar 120), id_remoto, id_local, hash_registro, lote_id_alta,
                               lote_id_ultima_mod, anulado_en_lote_id
                               UNIQUE (empresa_id, cliente_id, entidad, clave_natural)
estudio__sync_comprobantes     espejo de solo lectura (cabecera) + _iva (por alícuota) + _tributos (por impuesto y
                               jurisdicción). Misma forma que estudio__minigestion_comprobantes(_iva/_percepciones)
                               para que libros, tablero y liquidación usen una sola vista.
estudio__sync_cuentas_mapeo    cuenta del cliente (código) -> cuenta del plan del estudio, cargado a mano o sugerido
```

`hash_registro` (SHA-256 del JSON canónico del registro) decide si una fila cambió: igual = no se toca.

Importación de un lote, en una transacción por archivo (no por lote entero, para no bloquear con paquetes grandes):
validar manifiesto y hashes → por cada registro, buscar en `_mapeo` por clave natural → insertar / actualizar si el
hash difiere / dejar igual → marcar anulados los que faltan → totales de control contra el manifiesto → estado del
lote. Un período liquidado en el estudio (`estudio__minigestion_liquidaciones_iva` vigente) no se pisa: el lote queda
CON_AVISOS y lista lo que cambiaría.

Mini-gestión agrega un tercer modo, `REMOTO`, que lee del espejo (hoy MANUAL lee de `minigestion_*` y ERP de las tablas
de gestión).

## 7. Endpoints (contrato)

**Push — en el estudio**

```
POST /api/estudio/v1/paquetes.php            multipart: paquete=<zip>
200 {success:true, data:{lote_id, estado, nuevos, actualizados, sin_cambios, anulados, avisos:[...]}, message}
GET  /api/estudio/v1/lotes.php?lote_id=N     estado de un lote (para el cron del cliente)
```

**Pull — en la instancia del cliente**

```
GET /api/export/v1/paquete.php?desde=2026-10-01&hasta=2026-10-31&areas=ventas,compras
200 application/zip (manifiesto + archivos)        304 si no hubo cambios desde ?desde_secuencia=N
GET /api/export/v1/estado.php                      versión de esquema soportada, último período cerrado
```

Del lado del cliente la exportación arma el paquete con los mismos criterios de la Posición de IVA
(`iva_posiciones_model.php`): fiscales, ventas en estados confirmados / derivados, compras confirmadas por
`f_contabilidad`, notas de crédito restando.

## 8. Fases sugeridas

1. Espejo + mapeo + importador desde **archivo ZIP subido a mano** (sin red). Exportación en gestion / gestion_normal.
2. Push con token (endpoint del estudio + envío desde el cliente).
3. Pull (endpoint de exportación en el cliente).
4. Asientos y plan de cuentas, si se decide (pregunta 1).

## 9. Preguntas abiertas

1. **¿Quién es la fuente de verdad de la contabilidad?**
   - (a) El cliente: el estudio solo audita; los asientos viajan en el paquete y el espejo es la contabilidad. Simple,
     pero el estudio no puede ajustar sin pedirle al cliente que lo haga.
   - (b) El estudio: viajan solo comprobantes y retenciones; el estudio genera la contabilidad con su plan y sus
     criterios (como mini-gestión). Los asientos del cliente, si existen, quedan como referencia.
   - (c) Mixto: el cliente es dueño de los comprobantes; el estudio, de los asientos de cierre, ajustes y DDJJ.
   Recomendación: **(c)**. Afecta qué entidades viajan (asientos sí o no) y si hace falta `estudio__sync_cuentas_mapeo`.
2. Si el estudio presentó una DDJJ con un período y después llega un lote que lo cambia: ¿se acepta con aviso
   (propuesta) o se rechaza hasta que alguien reabra el período en el estudio?
3. ¿Pull en la v1 o solo push? Push cubre a todos; pull solo agrega comodidad para instancias con IP pública.
4. ¿El token se emite por cliente y por área, o uno por cliente con todas las áreas de la vinculación?
5. ¿Qué pasa con un cliente que pasa de REMOTO a LOCAL (migra a esta instancia)? Propuesta: el espejo queda como
   histórico de solo lectura y desde la fecha de migración se lee en modo ERP.
6. ¿Retención de los ZIP recibidos? (propuesta: 10 años, por ser respaldo de libros IVA).
7. Versión mínima del sistema del cliente para exportar: ¿se fuerza actualización o se mantiene compatibilidad con
   la major anterior durante un tiempo?
