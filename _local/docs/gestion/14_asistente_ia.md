# 14 — Asistente IA (página 139 / 639)

Fecha: 2026-11-04. Script: `_local/sql/mantenimiento/20261104_asistente_ia_01.sql`.

## Qué hace

Página **Ventas > Asistente IA** (módulos 2 y 5). El usuario escribe un pedido en sus palabras y la IA lo arma:

| Pedido | Resultado |
|---|---|
| "Factura a Ferretería López con 10 tornillos 8 mm y 2 cajas de clavos" | Factura de venta en **Borrador** |
| "Remito a consumidor final con 3 bolsas de cemento" | Remito de venta en **Borrador** |
| "Dar de alta como cliente a Juan Pérez, monotributista, de Viedma" | Cliente / proveedor nuevo (o el rol que le falta a uno existente) |
| "Dar de alta este proveedor" + constancia de inscripción de ARCA (PDF / JPG / PNG) | Ídem, con los datos leídos de la constancia |

**La IA nunca graba.** Consulta con herramientas y arma una *propuesta*; la pantalla la muestra (cliente, punto de venta,
líneas, precios, totales o los datos del alta) con **Confirmar y guardar en Borrador** / **Descartar**. Al confirmar, el
servidor revalida todo y graba con las funciones de siempre. Después se sigue en la pantalla normal (confirmar, pedir CAE...).

## Arquitectura

```
modules/gestion*/asistente_ia.php          vista (fija $pagina_idx 139 / 639) → templates/asistente_ia/asistente_ia_vista.php
modules/gestion*/asistente_ia_ajax.php     ajax_guard(pagina) → core/asistente_ia/asistente_ajax.php
assets/js/asistente-ia.js                  chat, tarjetas de propuesta, confirmar / descartar
core/asistente_ia/
  asistente_proveedores.php   diálogo con herramientas: Claude (Messages API) o Gemini (generateContent), curl
  asistente_herramientas.php  definición (JSON Schema) y ejecución de las 8 herramientas; validaciones
  asistente_service.php       conversación, ciclo modelo ↔ herramientas, propuestas, confirmación
  asistente_ajax.php          despachador {success, data, message}
```

Las páginas de destino (Facturación, Remitos, Clientes, Proveedores) se resuelven **por url en el mismo módulo**, y se
incluyen los modelos de ventas de ese módulo (`ventas_facturas_model.php`, `ventas_remitos_model.php`; `clientes_model.php`
solo al grabar un alta). El código del asistente es uno solo para los dos módulos.

### Herramientas

| Herramienta | Qué hace |
|---|---|
| `buscar_entidades` | clientes / proveedores por nombre, fantasía o CUIT (máx. 10), con condición IVA, roles y sucursales |
| `buscar_productos` | `vfBuscarProductos` del módulo: precio de la lista del cliente (o de consumidor final) e IVA |
| `opciones_comprobante` | PV que el usuario puede usar (`vfPuntosVenta` / `obtenerPuntosVentaDepositoRemitos`, filtrados por accesos por sucursal) y tipos habilitados (`vfTiposPorPuntoVenta` con el cliente: solo la letra que corresponde; sin NC/ND) |
| `proponer_factura_venta` / `proponer_remito_venta` | `asis_validar_comprobante`: cliente con rol CLI, sucursal, PV y tipo contra lo anterior, productos activos, precio de la lista (o el indicado), descuento general del cliente (o el indicado), totales con `vfCalcularDetallesFactura`. Guarda la propuesta |
| `catalogos_alta_entidad` | condiciones IVA, regímenes y sedes de IIBB, condiciones de pago y listas de la empresa |
| `buscar_localidades` | `conf__localidades` por nombre / CP / provincia |
| `consultar_padron_arca` | padrón de ARCA por CUIT (`core/arca_padron_service.php`, desde 2026-11-04): razón social, tipo, condición IVA, domicilio con provincia / localidad del ERP, si ya existe. Requiere la integración ARCA_PADRON de la empresa |
| `proponer_alta_entidad` | `asis_validar_alta_entidad`: CUIT con dígito verificador, no el de la empresa; si el CUIT existe y le falta el rol, propone agregarlo; nombre duplicado; catálogos válidos. Guarda la propuesta |
| `remitos_pendientes_facturar` | `vfRemitosPendientes` del cliente agrupado por remito: número con el código fiscal del PV, fecha, líneas con lo pendiente (`venta_remito_detalle_id`) y total estimado, con fecha e importe ya formateados |
| `buscar_facturas_emitidas` | facturas de venta emitidas (estado distinto de 2 / 3 / 6 / 38, solo tipos raíz: sin NC / ND) de un período y/o cliente, filtradas por accesos por sucursal (máx. 100) |
| `proponer_copia_facturas` | `asis_validar_copia_facturas`: copia facturas (por ids o período / cliente, máx. 50) con `incremento_pct` sobre el precio sin IVA de cada línea, `redondeo` opcional, fecha nueva y `reemplazos_texto` en leyendas y observaciones. Guarda la propuesta `COPIA_FACTURAS` |
| `ofrecer_opciones` | registra un mensaje `OPCIONES` (pregunta + opciones, simple o múltiple) que la pantalla dibuja como botones / casillas; la elección vuelve como el próximo mensaje del usuario |

**Facturar remitos** (2026-11-04): `proponer_factura_venta` acepta `remitos` (todo lo pendiente de cada uno) e items con
`venta_remito_detalle_id` (parciales, cantidad ≤ pendiente), mezclables con productos sueltos. Producto, precio, descuento e
IVA salen de la línea del remito (como la pantalla de Facturación); al grabar, `vfInsertarDetallesFactura` descuenta lo
pendiente y recalcula el estado del remito (Facturado / Fact. parcial). Los tipos que se ofrecen son solo raíz (sin NC / ND).

**Copiar facturas con incremento** (2026-11-07): "copiá las facturas de octubre con un 5 % de aumento" / "las de Ferretería
López". Cada copia es una factura como la de `proponer_factura_venta` (pasa por `asis_validar_comprobante`): mismo cliente,
sucursal, PV, productos, cantidades y descuentos; precio = original × (1 + % / 100), redondeado a centavos o a múltiplos de
`redondeo`; IVA del producto actual. El tipo es el de la original si sigue habilitado para el cliente; si cambió su condición
fiscal, el sugerido del mismo PV (con aviso). Las líneas que venían de remitos se copian como productos sueltos (no descuentan
remitos). Se omiten, con el motivo, las de otra moneda, sin acceso al PV, con productos inactivos, etc. Aviso si el cliente
ya tiene una factura de ese tipo en el mes de la copia (posible duplicado). Al confirmar (`asis_grabar_copia_facturas`), cada
una se graba con `asis_grabar_factura` (revalida, exige "agregar" de Facturación) en su propia transacción: si alguna falla,
las otras quedan y el mensaje lo dice; si fallan todas, la propuesta sigue pendiente. El resultado por factura queda en los
datos de la propuesta y la tarjeta se redibuja con él. Probado en `gestion_multipyme_sim` (11 facturas, +10 %, redondeo 10).

**Punto de venta**: `opciones_comprobante` indica cuál usar (el único, el por defecto de Parámetros de Empresa o, si no hay,
pide mostrarlos con `ofrecer_opciones`). Como un modelo chico puede igual elegir solo, la propuesta guarda `pv_alternativas`
(los PV del usuario con ese mismo tipo) y la tarjeta muestra un selector; al confirmar se manda el elegido, el servidor lo
acepta solo si está entre las alternativas, revalida y actualiza la propuesta.

### Confirmación (`asis_confirmar_propuesta`)

1. `GET_LOCK` por propuesta (un doble clic no graba dos veces) y estado `PENDIENTE`.
2. Permiso: función **agregar** de la página de destino (`usuario_puede_funcion`); el alta de cliente y proveedor exige las dos.
3. Revalida con los datos guardados (precios, accesos, estados o un CUIT cargado mientras tanto).
4. Graba:
   - Factura: `guardarVentaFactura` (Borrador, numeración al confirmar, percepciones; el total grabado puede sumar percepciones que la propuesta no muestra: el mensaje final dice el total real). Antes, `exigirAccesoPuntoVenta`.
   - Remito: `agregarRemitoVenta` (valida accesos por sucursal).
   - Alta: una transacción propia: `gestion__entidades` + sucursal 1 "Casa Central" (domicilio, localidad, teléfono, email) + `sincronizarSituacionFiscalEntidad` (IVA / IIBB versionados) + roles con `actualizarRolIndividualEntidad` (cuenta por defecto de la empresa) + condición de cliente (condición de pago, lista) / de proveedor (condición de pago). Con entidad existente: solo roles y condiciones que falten.
5. Marca la propuesta `CONFIRMADA` con el id creado y deja un mensaje `EVENTO`, que la IA recibe al principio del pedido siguiente ("[Novedades: …]").

### Proveedor de IA

La clave es la de la lectura de facturas: la de la empresa (Parámetros de Empresa, cifrada) o la del servidor
(`fl_ia_clave_empresa` / `fl_config`). `AIza…` = Gemini, `sk-ant-…` = Claude.

- **Claude**: `claude-opus-5-5`, effort `medium`, razonamiento adaptativo (no se envía `thinking`), `tool_choice: auto`,
  `cache_control` automático (sistema + herramientas fijos por día), `fallbacks: "default"` con la beta
  `server-side-fallback-2026-07-01` (si la cuenta no la tiene, reintenta sin ella).
- **Gemini**: el `modelo_gemini` de la lectura (hoy `gemini-flash-lite-latest`), salvo `modelo_gemini_asistente`.

Configurable en la sección `asistente` del archivo de `FACTURA_LECTURA_CONFIG` (ver `10_lectura_facturas_proveedores_config.example.php`):
`modelo_claude`, `esfuerzo_asistente`, `max_tokens_respuesta`, `modelo_gemini_asistente`, `precio_entrada_mtok_claude`, `precio_salida_mtok_claude`.

**Historial nativo**: cada conversación guarda en `gestion__ia_asistente_conversaciones.historial` los mensajes en el
formato del proveedor y se reenvían sin tocar (Claude exige los bloques de razonamiento tal cual; Gemini, las partes con
`thoughtSignature`). Por eso nunca se edita un turno anterior y una conversación queda atada a su proveedor y modelo
(si cambia la clave o el modelo, pide empezar otra). `asis_objetos_vacios` repara los `{}` que `json_decode(..., true)`
convierte en `[]`. Si un turno falla a mitad del ciclo, el historial de ese turno no se guarda.

Los archivos adjuntos (máx. 5 MB) se guardan en `uploads/asistente_ia/<empresa>/` (.htaccess deny) y van en base64 en el
historial: se reenvían en cada turno siguiente de esa conversación (con Claude, desde la caché).

## Habilitación, tope y registro

- `gestion__empresas_parametros.ia_asistente_habilitado` (0 por defecto) y `ia_asistente_tope_diario` (50): Parámetros de
  Empresa, junto a la lectura de facturas con IA. Sin clave, deshabilitado o con el tope del día alcanzado, la página lo dice
  y no deja escribir.
- Permiso de la página: función 3527 / 8527 "Usar Asistente IA", asignada a los perfiles que pueden agregar en Facturación,
  Remitos, Clientes o Proveedores.
- `gestion__ia_asistente_mensajes`: cada pedido, respuesta, herramienta (entrada y resultado en `datos`), error y evento.
  El tope cuenta los `USUARIO` del día de la empresa. Tokens y costo estimado por conversación.
- `gestion__ia_asistente_propuestas`: lo propuesto, quién y cuándo lo resolvió y el registro creado.

## Seguridad

- Empresa y usuario salen de `ajax_guard`; toda consulta filtra por empresa; PV y sucursales por `AccesosSucursalesHelper`.
- Conversaciones y propuestas solo del usuario, la empresa y el módulo (`asis_conversacion` / `asis_propuesta`).
- Escrituras (`enviar`, `confirmar`, `descartar`, `archivar`) con CSRF. `enviar` cierra la sesión (`session_write_close`)
  para no trabar las otras pantallas mientras la IA responde (hasta 300 s).
- El texto de los adjuntos se trata como datos (instrucción del sistema); igual, lo peor que puede hacer un documento
  malicioso es armar una propuesta, que el usuario ve antes de confirmar y que pasa por los permisos de siempre.
- Lo que se manda al proveedor de IA: el pedido, los resultados de las herramientas (nombres, CUIT, productos, precios) y el
  adjunto. La ayuda del switch en Parámetros de Empresa lo dice.

## Pruebas (2026-11-04, copia `gestion_multipyme_sim`, Gemini flash-lite)

- Herramientas y confirmación sin IA: factura A (cliente RI, descuento general 30 %, percepción IIBB sumada al grabar),
  remito, alta cliente + proveedor con IVA / IIBB CM / localidad, doble confirmación rechazada, CUIT inválido, CUIT existente.
- Con IA: factura desde texto (6 herramientas, ~US$ 0,002), segundo turno en la misma conversación, alta desde una constancia
  PDF (CUIT, razón social, domicilio, localidad, RI, CM con número y sede).
- Endpoint por `run_ajax.php`: estado, conversaciones, mensajes, CSRF, propuesta ajena, empresa sin acceso, aislamiento por módulo.
- **Falta probar en el navegador** y con una clave de Claude.

## Pendiente / ideas

- ~~Consulta de padrón de ARCA~~: hecha el 2026-11-04 (`consultar_padron_arca`, script `20261104_arca_padron_01.sql`).
- Más circuitos con el mismo mecanismo: presupuestos y pedidos, cobranzas, facturas de proveedores (ya hay lectura de archivo).
- Lista de precios y descuento por línea: hoy toma el descuento general del cliente aunque el usuario indique el precio.
