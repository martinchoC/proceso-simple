# Estudio contable: clientes que usan gestion_multipyme en otra instancia

Borrador de diseño, 2026-11-04. Nada de esto está construido todavía.

## El problema

El estudio (tenant empresa 2) tiene hoy tres formas de tener los comprobantes de un cliente:

| Caso | Cómo entra la información | Dónde |
|---|---|---|
| Cliente sin ERP | Carga a mano en mini-gestión (modo MANUAL) o, a futuro, importando Mis Comprobantes de ARCA | `estudio__minigestion_comprobantes` |
| Cliente que usa el ERP **en esta misma base** | El estudio lee en vivo la empresa vinculada (modo ERP) | `estudio__clientes.empresa_vinculada_id` |
| Cliente que usa el ERP **en otra instancia** (otro servidor, otra base) | **No hay forma** | — |

Para el tercer caso se cargaron tres clientes de ejemplo en el padrón (`_local/tests/fixtures/estudio_arca/alta_clientes_estudio.sql`):

| Cliente | CUIT | Sede | Por qué está en otra instancia |
|---|---|---|---|
| Metalúrgica Comahue SRL | 30-71702288-9 | Neuquén, CM con Río Negro | Servidor propio en la planta, sin salida a internet salvo HTTPS saliente |
| Distribuidora Golfo Azul SA | 30-70655419-6 | Chubut | Instancia en un hosting contratado por la empresa, con IP pública |
| Panificadora Atlántica SRL | 30-71488630-0 | Buenos Aires | Instancia que administra otro estudio o un integrador; el cliente nos da acceso solo a lo que necesitamos |

Los tres casos se eligieron porque cada uno descarta una solución: el primero no puede recibir conexiones (descarta que el estudio "tire" los datos), el tercero no nos da acceso a su servidor (descarta leer su base) y ninguno va a pasar a nuestra instancia en lo inmediato.

## Opciones

### A. Que pasen a esta instancia (multi-tenant)
Son una empresa más en `conf__empresas`, el estudio los vincula con `empresa_vinculada_id` + `conf__empresas_vinculaciones` y la mini-gestión los lee en modo ERP. Es la mejor opción cuando es posible: no hay copia de datos ni desfase. Antes hay que cerrar una deuda: **hoy nadie llama a `vinculacionesVerificarPermiso()`** y `estudio_cliente_obtener_empresa_erp()` lee cualquier empresa sin chequear el vínculo. No sirve para estos tres.

### B. Leer su base directamente
Descartada: credenciales de una base ajena en nuestro servidor, acoplado al esquema (que cambia seguido), y no funciona con la Metalúrgica.

### C. Mis Comprobantes de ARCA, igual que un cliente sin ERP
Funciona desde el día uno y para cualquiera, pero desperdicia lo que su ERP ya sabe: la provincia de cada percepción (ARCA las suma en "Otros Tributos"), las retenciones sufridas, los asientos, el plan de cuentas y los cobros y pagos. Queda como respaldo y como control cruzado.

### D. Paquete de exportación del ERP (archivo)
En la instancia del cliente, una página "Exportar para el estudio" arma por período un ZIP con un formato propio y versionado ("paquete contable v1", más abajo). El cliente lo descarga y lo sube en el estudio, o lo manda por mail. No requiere conectividad entre servidores ni dar acceso a nadie.

### E. API de ingreso (el mismo paquete por HTTPS)
La instancia del cliente **empuja** el paquete al estudio por HTTPS (`POST`), a mano con un botón o por cron al cerrar el período. Se elige el sentido "push" y no que el estudio lo pida porque el servidor del estudio es el que está siempre publicado; la Metalúrgica solo tiene salida HTTPS.

## Recomendación

**D y E son la misma cosa con dos transportes: un único formato y un único importador en el estudio.** Se construye primero D (archivo) y después E reusa todo: el endpoint de la API solo recibe el ZIP que hoy se sube a mano. C queda para los clientes sin ERP y como control (lo que trae el paquete tiene que coincidir con Mis Comprobantes por CAE).

Orden sugerido:
1. Tablas del estudio para comprobantes importados con detalle por alícuota y por tributo (hoy `estudio__minigestion_comprobantes` guarda una alícuota y no tiene CAE): las usan tanto el importador de ARCA como el del paquete.
2. Importador de Mis Comprobantes de ARCA en el estudio (probado con los archivos de `_local/tests/fixtures/estudio_arca/`).
3. Exportación del paquete en `gestion` / `gestion_normal` + importación en el estudio.
4. API de ingreso.

## Paquete contable v1 (propuesta)

ZIP `paquete_<cuit>_<AAAA-MM>_<secuencia>.zip`:

```
manifiesto.json          versión del formato, CUIT, razón social, período, instancia de origen (URL, versión del sistema),
                         fecha de generación, si el período está cerrado en origen, cantidades y totales de control,
                         hash SHA-256 de cada archivo
ventas.json              comprobantes emitidos: tipo ARCA, PV, número, fecha, CAE, receptor (CUIT, condición), moneda y
                         TC, netos e IVA por alícuota, no gravado, exento, percepciones practicadas por impuesto y jurisdicción
compras.json             comprobantes recibidos: lo mismo + fecha de contabilización (período de IVA) y percepciones
                         sufridas por impuesto y jurisdicción
retenciones.json         retenciones sufridas (cobranzas) y practicadas (OP), con certificado y período de cómputo
iva_posicion.json        posición mensual si está liquidada en origen
asientos.json            opcional: asientos con cuentas por código del plan del cliente
plan_cuentas.json        opcional: código, nombre e imputable, para mapear contra el plan del estudio
```

- **Identidad de un comprobante**: CUIT emisor + tipo ARCA + PV + número (el mismo criterio que el importador de Emitidos y `uk_comp_numeracion`). Volver a importar un paquete actualiza en vez de duplicar; un comprobante que desaparece en un paquete nuevo del mismo período se marca anulado, no se borra.
- **Lote**: cada importación queda registrada (hash del ZIP, usuario o token, fecha, resultado). Mismo hash = ya importado.
- **Período cerrado**: si el origen manda el período cerrado y después llega un paquete con cambios, el estudio lo acepta pero avisa (puede haber DDJJ presentadas con el anterior).
- **JSON y no CSV** para el paquete porque lleva estructuras anidadas (alícuotas, tributos por jurisdicción); para Excel, el estudio ya puede bajar el libro IVA.
- Los importes van en ISO con punto decimal y las fechas en `AAAA-MM-DD`, como todo lo que viaja en el sistema.

## API de ingreso (propuesta)

- Endpoint en el estudio: `api/estudio/v1/paquetes.php` (`POST`, multipart con el ZIP). Responde `{success, data: {lote_id, recibidos, actualizados, avisos}, message}`.
- Autenticación por **token por cliente**, emitido por el estudio desde la ficha del cliente (`estudio__clientes`), con vigencia y revocable. Se guarda solo el hash del token; se muestra una vez al crearlo. Además, el CUIT del manifiesto tiene que ser el del cliente dueño del token.
- En la instancia del cliente, el token se carga en Parámetros de Empresa, cifrado con `core/secretos.php` como la clave de la IA, y nunca vuelve al navegador.
- Firma HMAC del cuerpo con el token + marca de tiempo (rechazar si tiene más de 5 minutos) para evitar reenvíos.
- El endpoint no usa sesión ni `ajax_guard` (no hay usuario logueado): necesita su propio guard (`core/api_guard.php`) con límite de pedidos por token y log de cada llamada, en la línea de `gestion__integraciones_resultados`.
- La tabla `estudio__vinculaciones` (con `token_autorizacion`, hoy sin uso) se puede reaprovechar para guardar el token y los permisos por área, o reemplazar por una tabla nueva; hay que decidirlo antes de construir.

## Decisiones pendientes

1. ¿Push del cliente (recomendado) o también pull del estudio para las instancias con IP pública como Golfo Azul?
2. ¿El paquete lleva asientos y plan de cuentas desde el principio, o solo comprobantes y retenciones en la v1?
3. ¿Reusar `estudio__vinculaciones` para el token o tabla nueva?
4. ¿La exportación del paquete se ofrece a todas las empresas de gestion / gestion_normal o solo a las que tengan un estudio configurado?
