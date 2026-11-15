# Vendedores y carteras de clientes (paso 5a)

Estado: **implementado** (2026-11-15; plan aprobado con las 5 recomendaciones). La parte (b), distribución, va en otra sesión con su propio plan
(al final, un adelanto de cómo encaja).

## Pedido (Prompt 5, parte a)

Rol VEN en `gestion__entidades_roles`, cartera de clientes con vigencia, `vendedor_id` y origen en el pedido,
permisos por cartera.

## Lo que hay hoy (base local y código, solo lectura)

- `gestion__roles_entidades`: 8 roles (CLI, PRO, EMP, ORG, EMPL, EST, AFIL, BAN). **No hay VEN** ni ninguna tabla
  o columna de vendedores, carteras, zonas o comisiones.
- `gestion__ventas_pedidos` no tiene vendedor ni origen.
- Un usuario (`conf__usuarios`) no está ligado a ninguna entidad: hoy no hay forma de saber que el usuario X "es"
  el vendedor Y.
- Pedidos tiene dos pantallas que listan: "Pedidos Realizados" (55 / 555, `ventas_pedidos.php`, alta y edición) y
  "Nuevos Pedidos de Clientes" (91 / 591, `ventas_pedidos_gestion.php`, preparación de los pedidos en 5 / 9 / 10). Las dos ya filtran por accesos por
  sucursal (`AccesosSucursalesHelper::filtroSqlSucursal`): el filtro por cartera sigue el mismo patrón.
- El carrito (`casalucho-carrito`, tablas `ecom__*`) todavía no crea pedidos en casalucho: el origen queda
  preparado para cuando se integre.

## Propuesta

Indicador `conf__empresas.usa_vendedores` (apagado por defecto). Sin él no cambia nada: las columnas nuevas del
pedido no se leen ni se escriben, no hay filtro por cartera y la página nueva no aparece en el menú de nadie.

### 1. Vendedor = entidad con rol VEN

- Fila nueva en el catálogo global `gestion__roles_entidades`: `VEN` "Vendedor" (id explícito 9). Se asigna con
  `actualizarRolIndividualEntidad()` de `core/entidades_roles.php`, como los demás roles.
- El vendedor puede ser una entidad que ya existe (típico: un empleado, rol EMP) o una nueva con alta mínima
  (nombre y, opcional, CUIT / DNI) desde la página nueva, sin pasar por el ABM de Clientes / Proveedores.
- **Usuario del vendedor**: tabla nueva `gestion__vendedores_usuarios` (empresa, entidad vendedor, usuario,
  vigencia, estado). Un usuario es vendedor de una sola entidad por empresa a una fecha.

### 2. Cartera con vigencia

Tabla nueva `gestion__vendedores_carteras`: empresa, vendedor (entidad), cliente (entidad), `f_desde`, `f_hasta`
(NULL = abierta), estado. **Un cliente tiene un solo vendedor a una fecha**: asignar otro cierra la fila anterior
el día antes (nada se borra; el historial queda). Reasignación masiva ("pasar la cartera de A a B desde tal fecha",
por ejemplo por vacaciones o baja).

### 3. Pedido: vendedor y origen

Columnas nuevas, opcionales, en `gestion__ventas_pedidos`:
- `vendedor_entidad_id` INT NULL.
- `origen` VARCHAR(20) NULL: `ERP` (cargado en el sistema por personal interno), `VENDEDOR` (lo cargó un usuario
  vendedor), `CARRITO`, `APP` (reservados para la integración).

Al grabar (solo con el indicador): si el usuario es vendedor → vendedor = él y origen VENDEDOR; si no → vendedor =
el de la cartera del cliente a la fecha del pedido (editable por quien tenga la función nueva "Asignar vendedor")
y origen ERP. El remito y la factura no llevan vendedor propio: se lee del pedido (para comisiones, más adelante).
Listado de pedidos: columna Vendedor (en el detalle desplegable) y filtro por vendedor.

### 4. Permisos por cartera

- Un usuario **ligado a un vendedor** y **sin** la función "Ver todas las carteras" ve y carga solo pedidos de
  clientes de su cartera (a hoy): filtro en los dos listados, en el selector de clientes del pedido y control al
  grabar (`vcar_exigir_cliente`, lanza `InvalidArgumentException`).
- Un usuario que no es vendedor (administración, depósito, supervisor) no tiene restricción: el filtro solo existe
  para quien está ligado a un vendedor. Así, prender el indicador no le saca nada a nadie hasta que se ligan
  usuarios.
- Alcance: pedidos (91 y 55). Remitos, facturas y cobranzas no se filtran en este paso (decisión 3).

### 5. Página nueva "Vendedores y carteras"

Bajo Ventas (147 / 647), con su script de configuración (tabla, funciones con id explícito 3599–3602 /
8599–8602: consultar, agregar, editar, inhabilitar, más 3603 / 8603 "Ver todas las carteras" y 3604 / 8604
"Asignar vendedor"; permisos para los perfiles de administración). Lógica en `core/vendedores.php` (`vcar_*`),
vista en `templates/vendedores/`, JS en `assets/js/vendedores.js`; los archivos de cada módulo solo fijan la página
(como Escalas y promociones). Solapas: Vendedores (alta, usuario ligado, baja con vigencia) y Cartera (clientes de
un vendedor, asignar, quitar, reasignar en masa, clientes sin vendedor). Interruptor "Usa vendedores y carteras" en
Empresas.

### 6. Orden de trabajo (un commit y una prueba cada uno)

5a-1. Migración (`20261118_vendedores_carteras_01.sql`: indicador, rol VEN, 2 tablas, 2 columnas del pedido) +
      `core/vendedores.php` + pruebas en la copia y contra una base sin la estructura.
5a-2. Página "Vendedores y carteras" con su script de configuración; interruptor en Empresas.
5a-3. Pedidos: vendedor y origen al grabar, columna y filtro, permisos por cartera. Comparación antes / después.
Caso de simulación nuevo (36).

## Qué no cambia

Con el indicador apagado: nada. Precios, descuentos, canales, stock, remitos, facturas, asientos y ARCA no se
tocan. No hay comisiones en este paso.

## Decisiones a aprobar

1. **Un vendedor por cliente a una fecha** (recomendado) o varios (por ejemplo uno por línea de productos). Varios
   complica el filtro y la asignación automática.
2. **Ligar usuario y vendedor con tabla propia** (recomendado) o con una columna `usuario_id` en
   `gestion__entidades_roles` (más chico, pero mezcla el rol con el acceso y no guarda vigencia).
3. **Alcance del filtro por cartera**: solo pedidos (recomendado ahora) o también remitos, facturas, cuenta
   corriente y cobranzas (más pantallas compartidas y más comparaciones).
4. **Alta de vendedor**: elegir una entidad existente o alta mínima desde la página (recomendado), sin tocar el ABM
   de Clientes / Proveedores.
5. **Origen**: `ERP` / `VENDEDOR` / `CARRITO` / `APP` (recomendado) o agregar `TELEFONO` / `MOSTRADOR` para
   distinguir cómo llegó un pedido cargado por personal interno.

## Adelanto de la parte (b), distribución (otra sesión, plan propio)

ABM de camiones (capacidad en kg y m³) y zonas; zona del cliente (o de su sucursal de entrega); estado nuevo
"Confirmado para distribución" sobre el motor de estados (tabla de estados, funciones y permisos por script);
depósito / sucursal de despacho en el pedido (columna nueva); pantalla de armado nocturno que suma peso y volumen
de las presentaciones (`gestion__productos_presentaciones` ya los tiene) contra la capacidad del camión; hoja de
ruta con orden manual (sortablejs local); lista de carga por producto y presentación; rendición básica (entregado
/ rechazado / cobrado por pedido). Sin mapas ni optimización de rutas. El vendedor del pedido de este paso sirve
para agrupar y para la rendición.

## Implementado (2026-11-15)

| Paso | Qué |
|---|---|
| 5a-1 | Migración `20261118_vendedores_carteras_01.sql` (`usa_vendedores`, rol VEN id 9, tablas 469 `gestion__vendedores_usuarios` y 470 `gestion__vendedores_carteras`, `gestion__ventas_pedidos.vendedor_entidad_id` / `origen`) y `core/vendedores.php` (`vcar_*`). `sincronizarRolesEntidad` (`core/entidades_roles.php`) no da de baja el rol VEN: el formulario de Entidades tiene las casillas de rol fijas y no lo muestra |
| 5a-2 | Página "Vendedores y carteras" 147 / 647 (`20261118_vendedores_carteras_02_pagina.sql`, funciones 3599–3604 / 8599–8604, solo perfiles de administración): solapas Vendedores, Cartera y Clientes sin vendedor (`core/vendedores_abm.php`, `templates/vendedores/`, `assets/js/vendedores.js`); interruptor en Empresas |
| 5a-3 | Pedidos (91 y 55, módulos 2 y 5): vendedor y origen al grabar (alta, edición y copia), vendedor debajo del cliente en los dos listados (también en la búsqueda), selector "Vendedor" en el pedido para quien tiene "Asignar vendedor" (lo agrega el JS solo con el indicador), filtro por cartera en los listados, en el selector de clientes y al grabar, y control al abrir / operar un pedido por id (`vcar_exigir_pedido`) |

Pruebas: `probar_vcar.php` (núcleo: 3 apagado y 36 prendido en la copia, 4 contra el maestro sin la estructura),
`probar_empresas_vend.php` (interruptor), caso de simulación `36_vendedores_carteras.py` (página y pedidos, con el
indicador apagado al final) y comparación antes / después de los casos 03, 04, 06, 09 y 19b con el indicador
apagado.

Límites conocidos:
- El filtro por cartera usa la cartera **de hoy**: si un cliente pasa a otro vendedor, el anterior deja de ver sus
  pedidos viejos salvo los que quedaron a su nombre (`vendedor_entidad_id`).
- Solo pedidos: remitos, facturas, cuenta corriente y cobranzas no se filtran (decisión 3).
- El remito y la factura no guardan vendedor: se lee del pedido de origen (para comisiones, más adelante).
- La consola del navegador no se revisó en estas pantallas (sin navegador en esta sesión).
