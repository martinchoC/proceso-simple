# Canal del cliente, qué compra cada canal y qué canales vende cada sucursal (paso 3)

Estado: **propuesta, a aprobar**. Sigue a los docs 19 (precios por presentación) y 20 (presentaciones en ventas).
Rama `distribuidora`. Fecha: 2026-11-15.

## Lo que ya está decidido o hecho

- **Canal = lista** (doc 19, confirmado el 2026-11-14): el canal del cliente sale de su lista vigente en
  `gestion__entidades_condiciones_clientes`; la sucursal solo restringe qué canales vende.
- **Qué presentaciones compra cada canal**: ya resuelto por la lista. Una presentación se ofrece solo si la lista
  del cliente tiene su precio (`gestion__listas_precios_presentaciones`); la unidad suelta solo si la lista tiene
  `vende_unidad_base = 1`. El pedido, el remito y la factura lo validan en el servidor (paso 2).

Falta: (1) un **nombre de canal** que agrupe listas (hoy "mayorista" es implícito); (2) la **regla de sucursal**;
(3) un **solo criterio** para elegir la lista del cliente.

## Reconocimiento (base copia de producción, solo lectura)

| Dato | Hoy |
|---|---|
| Listas de Casa Lucho (empresa 2) | 1 General (`lista_precio_defecto_id` de la empresa), 3 Mostrador (código `MOST`, la usa la factura a consumidor final) |
| Clientes con condición vigente | 473 de 518; 45 sin condición → hoy cargan precio 0 (doc 18, hallazgo 8) |
| Tipo de cliente (`gestion__entidades_clientes_tipos`) | Catálogo **global** (sin `empresa_id`): Distribuidor, Minorista, Comercio, Gobierno, Otro. 471 de 473 condiciones dicen "Distribuidor" (es el valor por defecto, no un dato cargado a conciencia) |
| `gestion__entidades_listas_precios` | 0 filas; ningún archivo de casalucho la lee. Según el Prompt 3, el carrito la consulta primero (no leí `casalucho-carrito`: regla 1) |
| Elección de lista en el ERP | 3 copias del mismo criterio con diferencias: pedidos (`obtenerListaPrecioVigenteCliente`, ordena solo por `f_desde`), remitos (`...ClienteRemitos`, desempata por id), facturas (`vfObtenerCondicionCliente`; sin cliente, lista `MOST`), más `lpre_lista_cliente` en core |
| Sucursales | 1 Bahía Blanca, 2 Mar del Plata. El pedido y el remito eligen sucursal; la factura la toma del punto de venta |

## 1. Canal: tres maneras (el Prompt 3 pide dos; agrego la ya decidida)

| | A. Atributo de la **lista** (recomendada) | B. Campo explícito en el cliente | C. Mapeo desde el tipo de cliente |
|---|---|---|---|
| Dónde | `gestion__listas_precios.canal_venta_id` | `gestion__entidades_condiciones_clientes.canal_venta_id` | tabla `tipo de cliente → canal` por empresa |
| Coherencia precio / canal | Siempre: el canal es el de la lista que da los precios | Pueden contradecirse (cliente "minorista" con lista mayorista): hay que validar o elegir cuál manda | Igual que B, y además el tipo hoy no es confiable (471/473 "Distribuidor") |
| Carga | Una vez por lista (2 listas) | Por cliente (518) | Por tipo, pero hay que recargar el tipo de cada cliente |
| Catálogo | Propio de la empresa | Propio de la empresa | El tipo es global: el mapeo tendría que ser por empresa y no se puede tocar el catálogo de otros |
| Respeta lo decidido (doc 19) | Sí | No | No |

**Recomiendo A.** El canal es una etiqueta de la lista; el cliente lo hereda de su lista vigente. Para cambiar a un
cliente de canal se le cambia la lista, como hoy. Si más adelante hay dos listas mayoristas (A y B), las dos llevan
el canal "Mayorista".

Estructura (migración `20261116_canales_venta_01.sql`, solo agregado, reanudable):

- `conf__empresas.usa_canales_venta` TINYINT(1) NOT NULL DEFAULT 0.
- `gestion__canales_venta` (canal_venta_id, empresa_id, codigo, nombre, orden, tabla_estado_registro_id, auditoría).
  Por empresa, para que cada una defina los suyos (Minorista, Mayorista; después quizás Gastronomía). Sin datos:
  los crea la empresa en su ABM.
- `gestion__listas_precios.canal_venta_id` INT UNSIGNED NULL (NULL = sin canal: la lista se vende en todas las sucursales).
- `gestion__sucursales_canales_venta` (sucursal_id, canal_venta_id, empresa_id), PK (sucursal_id, canal_venta_id).
  **Sucursal sin filas = vende todos los canales** (como hoy).

## 2. Regla de sucursal

Con el indicador prendido, al **grabar** un pedido, remito o factura de venta se valida en el servidor:
canal de la lista usada ∈ canales de la sucursal (o la sucursal no tiene restricción, o la lista no tiene canal).
Si no: "La sucursal Mar del Plata no vende al canal Minorista". La pantalla lo avisa antes (badge del canal al
elegir cliente y sucursal) y deshabilita grabar, pero quien manda es el servidor.

- Pedido y remito: sucursal del comprobante. Factura: sucursal del punto de venta.
- Consumidor final (factura sin cliente): la lista `MOST` también tiene canal, así una sucursal solo mayorista
  rechaza la venta de mostrador.
- Remito desde pedido y factura desde remito: se valida igual (si el pedido se tomó en otra sucursal, el remito
  sale de la que despacha).

## 3. Un solo criterio para la lista del cliente

Función nueva en `core/canales_venta.php`:

```
cvta_lista_cliente(mysqli $c, int $empresa_id, ?int $entidad_id, ?string $fecha = null): array
  → ['lista_precio_id' => int, 'origen' => 'condicion'|'entidad_lista'|'defecto'|'mostrador'|'ninguna',
     'canal_venta_id' => ?int, 'canal' => ?string]
```

Orden:
1. Condición comercial vigente (`gestion__entidades_condiciones_clientes`; `f_desde` DESC, id DESC; la fecha del
   comprobante, no siempre hoy). Es la que editan las pantallas del ERP.
2. Si no hay: `gestion__entidades_listas_precios` vigente (la del carrito; hoy vacía).
3. Si no hay: `gestion__empresas_parametros.lista_precio_defecto_id`.
4. Sin cliente (consumidor final): la lista `MOST`, como hoy en facturas.

El orden 1 → 2 es al revés del que el Prompt 3 atribuye al carrito. Con la tabla vacía da lo mismo hoy; si algún
día se carga, ERP y carrito diferirían hasta que el carrito use esta función (fase del carrito). Por eso propongo
la condición primero: es la única que el ERP permite editar.

Quién la usa (solo con `usa_canales_venta` prendido; apagado, las funciones actuales siguen igual):
- `lpre_lista_cliente` (core) → delega en `cvta_lista_cliente`.
- `obtenerListaPrecioVigenteCliente`, `obtenerListaPrecioVigenteClienteRemitos`, `vfObtenerCondicionCliente`: si
  no traen lista, completan `lista_precio_id` con la del punto 2 o 3. Las tres conservan el resto de lo que
  devuelven (condición de pago, descuento, condición fiscal). No se reescriben.
- Los 45 clientes sin condición pasan a tomar la lista por defecto en vez de precio 0. Es un cambio de
  comportamiento, solo con el indicador.
- Hallazgo 8 del doc 18 (entra en esta fase): el buscador devuelve `en_lista` y la pantalla avisa "sin precio en la
  lista del cliente" en vez de cargar 0 en silencio.

## 4. Pantallas

- **Canales de venta** (ABM simple, nuevo): página en `conf` o en gestión, a definir al verificar cómo se
  sincronizan; lógica en `core/`, plantilla en `templates/`.
- **Listas de precios** (2 y 5): selector "Canal" (solo con el indicador).
- **Sucursales** (`modules/conf/sucursales*`): casillas "Canales que vende" (sin marcar = todos).
- **Clientes** (2 y 5): junto a la lista vigente, el canal como dato derivado (solo lectura), con la aclaración
  "sale de la lista" para no confundirlo con el tipo de cliente.
- **Pedido, remito, factura**: badge del canal al elegir cliente; aviso si la sucursal no lo vende.

## 5. Orden de trabajo (un commit y una prueba cada uno)

3a. Migración + `core/canales_venta.php` (`cvta_*`: indicador tolerante, catálogo, canal de lista, canales de
    sucursal, `cvta_lista_cliente`, `cvta_validar_sucursal`) + prueba en la copia y contra una base sin las tablas.
3b. ABM de canales, selector en listas, casillas en sucursales, interruptor en empresas.
3c. Lista unificada en pedidos, remitos, facturas y `lpre_lista_cliente` + aviso `en_lista`.
3d. Validación de sucursal en los tres comprobantes + badges.
Cada paso que toca código compartido: comparación antes / después de los casos 03, 04, 06, 09, 19b con el
indicador apagado, y un caso de simulación nuevo (34) con el indicador prendido.

## Qué no cambia

Con el indicador apagado: nada. Precios, mínimos, presentaciones y stock siguen como en los pasos 1 y 2. El tipo de
cliente sigue como está (no se usa para el canal).

## Decisiones a aprobar

1. **Canal**: A (atributo de la lista, recomendado), B (campo en el cliente) o C (mapeo desde el tipo).
2. **Catálogo de canales por empresa** (recomendado) o dos valores fijos (MIN / MAY).
3. **Sucursal que no vende el canal**: bloquear al grabar (recomendado) o solo avisar.
4. **Orden de la lista**: condición → lista de la entidad → defecto (recomendado) o el del carrito
   (lista de la entidad → condición → defecto).
5. **Clientes sin condición**: con el indicador, toman la lista por defecto (recomendado) o siguen en 0 con aviso.
6. **Aviso "sin precio en la lista"** (doc 18, hallazgo 8) en esta fase (recomendado) o después.
