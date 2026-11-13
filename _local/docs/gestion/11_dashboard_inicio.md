# Dashboard de inicio (gestion / gestion_normal)

Estado: Fases 1 a 3 construidas el 2026-10-27. Falta la Fase 4 (pruebas formales, EXPLAIN, caso del simulador).

**Fase 3 (frontend)**: `modules/gestion*/index.php` (vista sin SQL + modal), `assets/js/dashboard/widgets.js` (carga independiente por widget, dibujo por tipo, minicharts SVG / CSS, `window.Dash`), `assets/js/dashboard/config.js` (modal con SortableJS, que ya carga `footer1.php`, y botones subir / bajar), `assets/css/dashboard.css`. La barra superior de `header1.php` **no** cambia (decisión del 2026-10-27): sigue mostrando las páginas del módulo con `es_acceso_directo = 1` que el usuario tiene permitidas; la personalización y los obligatorios solo valen para el widget de accesos del inicio. Probado en Chrome sin interfaz: carga, badge, modal, guardar, restablecer, widget con error aislado y 390 px sin desplazamiento horizontal.

**Sin accesos en el inicio** (`20261028_dashboard_inicio_05_sin_accesos.sql`, decisión del 2026-10-27): el widget "Accesos directos" (11 / 511) quedó inactivo porque repetía la barra superior; con él se fue el badge de pedidos (el número sigue en el indicador "Pedidos a preparar"). Sin ese widget activo, `dash_config()` devuelve `accesos_activos = false` y el modal oculta la solapa de accesos. Para volver atrás: estado 1 en esas dos filas.

**Perfiles base** (`20261028_dashboard_inicio_03_perfiles_base.sql`): 'Administrador' / 'Acceso total' con base 1 o 3 pasan a 201 / 501 y 'Pedidos' (base 4) a 202 / 502. No cambia permisos, pero desde ahora `perfil_base_copiar_a_empresa()` les agregaría las funciones de su base que les falten. Siguen sin base actual 'Administrativo 1' y 'Cliente Mayorista'.

**Decisiones tomadas**: valores por defecto y obligatorios por perfil base; pedidos al vuelo; "a preparar" = estado 5 (el 9 se muestra aparte); ventas en neto sin IVA ni otros impuestos, con los X; se corrigió el flujo de fondos de tesorería; semilla: "Nuevos Pedidos" obligatorio para Ventas y Depósito y stock.

**Construido en la Fase 2**:
- Scripts `_local/sql/mantenimiento/20261028_dashboard_inicio_01_estructura.sql` y `_02_config.sql` (aplicados en local, idempotentes).
- `core/dashboard/dashboard_service.php` (contexto, permisos, layout, accesos, guardar / restablecer, caché, ejecución de handlers), `core/dashboard/dashboard_endpoint.php` (despachador) y los handlers de `core/dashboard/widgets/`: `ventas` (día / mes), `pedidos_pendientes` (también el badge), `saldo_ctacte` (clientes / proveedores, con `ctacteResumen()` sin entidad), `fp_por_vencer`, `stock_bajo_minimo`, `top_entidades` (ventas / compras).
- `modules/gestion/inicio_ajax.php` y `modules/gestion_normal/inicio_ajax.php`: solo fijan el módulo.
- `tesoreria_dashboard_model.php` (los dos módulos): cobros y pagos del flujo de fondos por la matriz de cancelaciones (RCX / OP sin ventas) y en moneda local.

**Diferencia con el diseño**: los perfiles de empresa que no salen de un perfil base con layout cargado (en local, los de `perfil_id_base` 3 / 4, los perfiles base viejos del módulo 2 hoy inactivos, que usan 17 perfiles de empresa, Casa Lucho incluida, o NULL) ven todos los widgets que tienen permitidos, igual que el superadmin sin perfil. No tienen accesos obligatorios.

Verificado contra la base local `gestion_multipyme` el 2026-10-27.

## 0. Correcciones al planteo (verificadas en el código y la base)

| Planteo | Lo que hay | Decisión propuesta |
|---|---|---|
| Layout `cabecera_v2.php` + `menu_v2.php` | No existen. El layout vigente es `templates/adminlte/header1.php` / `footer1.php` con estilo ui-simple (`core/ui.php`). | Usar `header1.php` + `$ui_simple = true` + `ui_encabezado()`. |
| `index.php` | El de la raíz es el selector empresa/módulo (redirige si hay una sola combinación). `modules/gestion/index.php` y `modules/gestion_normal/index.php` están **vacíos** (solo header/footer). | El dashboard va en el `index.php` de cada módulo. La raíz no se toca. |
| "Campo de candidata en la tabla de páginas" | Ya existe: `conf__paginas.es_acceso_directo` + `orden_acceso_directo`, se edita en `conf/paginas.php`, y `header1.php` (línea ~347) ya muestra en la barra superior todas las páginas marcadas que el usuario tiene permitidas. Hay 9 marcadas en el módulo 2, 8 en el 5, 6 en el 4 y 4 en el 6. | Reusar ese campo. La barra superior y el widget de accesos pasan a usar el mismo servicio. |
| `perfil_id` | Hay dos niveles: `conf__perfiles` (perfil base, por módulo: 201–208 / 501–508) y `conf__empresas_perfiles` (la copia por empresa, que es lo que se asigna al usuario y la única fuente de permisos). Todos los perfiles de empresa activos tienen `perfil_id_base` salvo 3. | Los valores por defecto y los obligatorios se cuelgan del **perfil base** (`conf__perfiles.perfil_id`), y se resuelven por `conf__empresas_perfiles.perfil_id_base`. Valen para todas las empresas sin copiar nada. El override por empresa queda como provisión futura. |
| "Un perfil por usuario" | Un usuario puede tener varios perfiles en la misma empresa y módulo (en la base local hay casos con 2 y 3). | Unión: widgets y obligatorios de todos sus perfiles, sin repetir; orden = el menor orden entre perfiles. |
| `sucursal_id` de la sesión | No existe sucursal en la sesión. Los accesos por sucursal son un conjunto por usuario (`AccesosSucursalesHelper`, `gestion__perfiles_sucursales` + excepciones). | Filtrar con `AccesosSucursalesHelper::filtroSqlSucursal()` (el conjunto permitido), igual que los listados. Un selector de sucursal en el dashboard queda como provisión futura. |
| `saldo_pendiente` de `gestion__comprobantes` | La columna es `importe_pendiente`, y no sirve sumarla sin filtro: también la tienen pedidos (tabla 82), remitos (81) y borradores (131 facturas de proveedor en estado 3 con saldo). Las cuentas corrientes (`clientes_ctacte_model.php` / `proveedores_ctacte_model.php`) **no usan esa caché a propósito**: toman los tipos de la matriz de cancelaciones y calculan el pendiente con las imputaciones confirmadas. | Los KPI de saldo usan el mismo criterio que la cuenta corriente, para que el número del dashboard coincida con el de esas páginas. |
| Stock bajo mínimo "si el módulo existe" | Existe y ya está materializado: `gestion__stock_niveles` (mínimo/máximo por boca y producto) y `gestion__stock_alertas` (una alerta abierta por boca + producto, la mantiene `stockEvaluarNivel()` al mover stock). Ya hay `salContarAbiertas()`. | Se construye ahora, reusando `stock_alertas_model.php`. |
| Materializar "pedidos pendientes por sucursal" | Es un `COUNT` sobre una tabla chica (3 filas en local) y el estado del pedido se cambia en 3 modelos × 2 módulos (`ventas_pedidos_model`, `ventas_pedidos_gestion_model`, `ventas_remitos_model`, que actualiza el pedido al remitir). | **Recomiendo calcularlo al vuelo** con un índice compuesto: según tu propia regla es barato, y un contador que hay que recalcular desde 6 archivos se desincroniza al primer camino que se olvide. Abajo dejo el diseño materializado por si igual lo querés (opción B). |
| Torta para el top 5 | Con 5 porciones + "resto" la torta se lee mal. | Barras horizontales (el valor se lee directo y entra en el ancho de un celular). |

**Bug preexistente encontrado** (fuera del alcance, no lo toco sin tu OK): `tesoreria_dashboard_model.php::obtenerFlujoFondosProyectado()` toma "pagos a proveedores" como `signo = -1`, pero las facturas de proveedor tienen `signo = 1` en `gestion__comprobantes`. Resultado: los egresos proyectados suman las notas de crédito de venta, y los cobros proyectados incluyen facturas de proveedor, pedidos y remitos. Además no filtra por `tabla_origen_id`.

## 1. Modelo de datos

Convenciones: `utf8mb4_unicode_ci`, InnoDB, sin `JSON` ni `DEFAULT` en `TEXT`, ids explícitos en el catálogo, script reanudable en `_local/sql/mantenimiento/20261028_dashboard_inicio_01_estructura.sql` (`CREATE TABLE IF NOT EXISTS`; índices sobre tablas existentes con el procedimiento `IF NOT EXISTS (… INFORMATION_SCHEMA.STATISTICS …)`, el patrón de `20261019_entidades_roles_01_backfill.sql`).

Tipos de las PK referenciadas (verificados): `conf__usuarios.usuario_id INT(11)`, `conf__paginas.pagina_id INT(11)`, `conf__perfiles.perfil_id INT(11) UNSIGNED`, `conf__empresas.empresa_id SMALLINT(6) UNSIGNED`, `conf__modulos.modulo_id TINYINT(3) UNSIGNED`. Las FK usan exactamente esos tipos. `conf__paginas` se borra con `DELETE` físico en `conf/paginas_model.php:619`, así que toda FK hacia páginas es `ON DELETE CASCADE` (si no, borrar una página fallaría).

### 1.1 Catálogo de widgets

```sql
CREATE TABLE IF NOT EXISTS conf__dashboard_widgets (
  widget_id          INT UNSIGNED NOT NULL,                 -- explícito: módulo 2 = 1..99, módulo 5 = mismo id + 500
  modulo_id          TINYINT(3) UNSIGNED NOT NULL,
  codigo             VARCHAR(40)  NOT NULL,                 -- 'ventas_hoy', único por módulo
  titulo             VARCHAR(80)  NOT NULL,
  descripcion        VARCHAR(255) DEFAULT NULL,             -- va al "?" de ui_ayuda()
  tipo               ENUM('kpi','lista','minichart','atajos','badge') NOT NULL,
  handler            VARCHAR(60)  NOT NULL,                 -- archivo core/dashboard/widgets/<handler>.php, ^[a-z0-9_]+$
  pagina_id          INT(11) DEFAULT NULL,                  -- permiso y link; NULL solo para 'atajos'
  parametros_default TEXT DEFAULT NULL,                     -- JSON como texto (sin columna JSON por MariaDB 10.4)
  ancho_default      TINYINT UNSIGNED NOT NULL DEFAULT 4,   -- col-lg-N: 3, 4, 6 o 12 (lista blanca en PHP)
  ttl_segundos       SMALLINT UNSIGNED NOT NULL DEFAULT 0,  -- 0 = sin caché
  tabla_estado_registro_id SMALLINT NOT NULL DEFAULT 1,     -- 1 activo / 2 inactivo
  PRIMARY KEY (widget_id),
  UNIQUE KEY uk_dw_modulo_codigo (modulo_id, codigo),
  KEY ix_dw_pagina (pagina_id),
  CONSTRAINT fk_dw_modulo FOREIGN KEY (modulo_id) REFERENCES conf__modulos (modulo_id),
  CONSTRAINT fk_dw_pagina FOREIGN KEY (pagina_id) REFERENCES conf__paginas (pagina_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

- Una fila por módulo (como `conf__paginas`): el `pagina_id` del permiso es distinto en el 2 y en el 5.
- `requiere_bienes` no se duplica: si la página del widget tiene `conf__paginas.requiere_bienes = 1` y la empresa es SERVICIOS, el widget no se ofrece (mismo criterio que `core/perfiles_predefinidos.php`).
- `tipo = 'badge'`: no se dibuja como tarjeta; aporta el número que se muestra sobre el acceso directo de su `pagina_id`. Así el badge reusa el mismo permiso y el mismo handler, sin una tabla aparte.

### 1.2 Layout por defecto del perfil base

```sql
CREATE TABLE IF NOT EXISTS conf__dashboard_perfiles_widgets (
  perfil_id  INT(11) UNSIGNED NOT NULL,
  widget_id  INT UNSIGNED NOT NULL,
  orden      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  ancho      TINYINT UNSIGNED DEFAULT NULL,                 -- NULL = ancho_default del widget
  PRIMARY KEY (perfil_id, widget_id),
  KEY ix_dpw_widget (widget_id),
  CONSTRAINT fk_dpw_perfil FOREIGN KEY (perfil_id) REFERENCES conf__perfiles (perfil_id) ON DELETE CASCADE,
  CONSTRAINT fk_dpw_widget FOREIGN KEY (widget_id) REFERENCES conf__dashboard_widgets (widget_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 1.3 Layout del usuario

```sql
CREATE TABLE IF NOT EXISTS conf__dashboard_usuarios_widgets (
  usuario_id INT(11) NOT NULL,
  empresa_id SMALLINT(6) UNSIGNED NOT NULL,
  widget_id  INT UNSIGNED NOT NULL,
  orden      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  visible    TINYINT(1) NOT NULL DEFAULT 1,
  ancho      TINYINT UNSIGNED DEFAULT NULL,
  actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (usuario_id, empresa_id, widget_id),
  KEY ix_duw_widget (widget_id),
  KEY ix_duw_empresa (empresa_id),
  CONSTRAINT fk_duw_usuario FOREIGN KEY (usuario_id) REFERENCES conf__usuarios (usuario_id) ON DELETE CASCADE,
  CONSTRAINT fk_duw_empresa FOREIGN KEY (empresa_id) REFERENCES conf__empresas (empresa_id) ON DELETE CASCADE,
  CONSTRAINT fk_duw_widget  FOREIGN KEY (widget_id)  REFERENCES conf__dashboard_widgets (widget_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Va por empresa: el mismo usuario en la empresa 2 y en la 7 tiene permisos distintos. El módulo sale del widget. Al guardar se graban **todos** los widgets permitidos (los ocultos con `visible = 0`), así "personalizado" = existe alguna fila del usuario para widgets de ese módulo, sin marcador aparte.

### 1.4 Accesos directos

```sql
CREATE TABLE IF NOT EXISTS conf__perfiles_accesos_directos (      -- obligatorios
  perfil_id INT(11) UNSIGNED NOT NULL,
  pagina_id INT(11) NOT NULL,
  orden     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (perfil_id, pagina_id),
  KEY ix_pad_pagina (pagina_id),
  CONSTRAINT fk_pad_perfil FOREIGN KEY (perfil_id) REFERENCES conf__perfiles (perfil_id) ON DELETE CASCADE,
  CONSTRAINT fk_pad_pagina FOREIGN KEY (pagina_id) REFERENCES conf__paginas (pagina_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS conf__usuarios_accesos_directos (      -- elegidos
  usuario_id INT(11) NOT NULL,
  empresa_id SMALLINT(6) UNSIGNED NOT NULL,
  pagina_id  INT(11) NOT NULL,
  orden      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (usuario_id, empresa_id, pagina_id),
  KEY ix_uad_pagina (pagina_id),
  KEY ix_uad_empresa (empresa_id),
  CONSTRAINT fk_uad_usuario FOREIGN KEY (usuario_id) REFERENCES conf__usuarios (usuario_id) ON DELETE CASCADE,
  CONSTRAINT fk_uad_empresa FOREIGN KEY (empresa_id) REFERENCES conf__empresas (empresa_id) ON DELETE CASCADE,
  CONSTRAINT fk_uad_pagina  FOREIGN KEY (pagina_id)  REFERENCES conf__paginas (pagina_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Marca de "el usuario ya eligió sus accesos" en esta empresa y módulo.
-- Sin ella no se distingue "eligió ninguno" de "nunca configuró" (las dos son cero filas arriba).
CREATE TABLE IF NOT EXISTS conf__usuarios_inicio (
  usuario_id INT(11) NOT NULL,
  empresa_id SMALLINT(6) UNSIGNED NOT NULL,
  modulo_id  TINYINT(3) UNSIGNED NOT NULL,
  accesos_personalizados TINYINT(1) NOT NULL DEFAULT 0,
  actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (usuario_id, empresa_id, modulo_id),
  CONSTRAINT fk_ui_usuario FOREIGN KEY (usuario_id) REFERENCES conf__usuarios (usuario_id) ON DELETE CASCADE,
  CONSTRAINT fk_ui_empresa FOREIGN KEY (empresa_id) REFERENCES conf__empresas (empresa_id) ON DELETE CASCADE,
  CONSTRAINT fk_ui_modulo  FOREIGN KEY (modulo_id)  REFERENCES conf__modulos (modulo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Reglas de resolución (empresa E, módulo M, usuario U, perfiles base P = `perfil_id_base` de sus `conf__empresas_perfiles` vigentes en E y M):

- **Permitidas** = `usuario_obtener_paginas_permitidas(U, E)` ∩ páginas activas de M ∩ (no `requiere_bienes` si E es SERVICIOS).
- **Obligatorios** = `conf__perfiles_accesos_directos` de P ∩ permitidas.
- **Candidatas ofrecidas** = páginas de M con `es_acceso_directo = 1` ∩ permitidas − obligatorios.
- **Visibles** = obligatorios (primero, por orden) + elegidos ∩ candidatas ofrecidas (por el orden del usuario).
- **Sin personalizar** (`accesos_personalizados = 0` o sin fila): elegidos = todas las candidatas ofrecidas por `orden_acceso_directo`. Es el comportamiento actual de la barra superior, así que los módulos 4 y 6 (que no van a tener filas de perfil) siguen igual.
- **Widgets**: si U tiene filas para widgets de M en E, se usan esas (∩ permitidos, los nuevos que no figuran no se agregan solos); si no, unión de `conf__dashboard_perfiles_widgets` de P. Superadmin sin perfiles o perfil sin base: todos los widgets activos del módulo.
- Una página o widget que deja de estar permitido simplemente no se devuelve; la fila del usuario queda (vuelve a aparecer si recupera el permiso).

### 1.5 Caché de resultados de widgets

```sql
CREATE TABLE IF NOT EXISTS conf__dashboard_cache (
  empresa_id  SMALLINT(6) UNSIGNED NOT NULL,
  widget_id   INT UNSIGNED NOT NULL,
  clave       CHAR(40) NOT NULL,          -- sha1(parámetros + conjunto de sucursales permitidas)
  payload     MEDIUMTEXT NOT NULL,        -- data JSON ya armada
  calculado_en DATETIME NOT NULL,
  expira_en   DATETIME NOT NULL,
  PRIMARY KEY (empresa_id, widget_id, clave),
  KEY ix_dc_expira (expira_en),
  CONSTRAINT fk_dc_widget FOREIGN KEY (widget_id) REFERENCES conf__dashboard_widgets (widget_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Por qué TTL y no "recalcular al cambiar el dato": los saldos cambian con cada factura, NC, recibo, OP, imputación, anulación y desimputación (más de 10 caminos de escritura en tesorería, ventas y compras). Invalidar desde todos es la misma trampa que el contador de pedidos. Con TTL corto el número puede tener unos minutos de atraso, se muestra "Actualizado hace N min" y un botón "Actualizar" lo recalcula (`forzar=1`). La clave incluye el conjunto de sucursales permitidas: dos usuarios con accesos distintos no comparten resultado. Se purga lo vencido al escribir (`DELETE … WHERE expira_en < NOW() LIMIT 200`).

### 1.6 Índices nuevos sobre tablas existentes

| Tabla | Índice | Para |
|---|---|---|
| `gestion__comprobantes` | `ix_comp_emp_origen_fecha (empresa_id, tabla_origen_id, f_emision)` | ventas del día / mes, top clientes y proveedores. Hoy solo existe `(empresa_id, f_emision)`, que lee también recibos, OP y sindicatos. |
| `gestion__comprobantes` | `ix_comp_emp_vto (empresa_id, f_vto)` | facturas de proveedor por vencer |
| `gestion__ventas_pedidos` | `ix_vped_emp_estado_suc (empresa_id, tabla_estado_registro_id, sucursal_id)` | pedidos pendientes (cubre el `COUNT … GROUP BY sucursal_id`) |
| `gestion__comprobantes_imputaciones` | `ix_imp_empresa_origen (empresa_id, comp_origen_id)` | subconsulta de imputado por origen en la cuenta corriente: hoy hay `(comp_origen_id)` sin la empresa adelante |

Saldos por tipo: `uk_comp_numeracion` ya empieza por `(empresa_id, comprobante_tipo_id)`, sirve. Stock: `uk_sal_abierta (empresa_id, boca_id, producto_id, abierta)` y `ix_sal_empresa_estado` ya existen. Fase 4 confirma cada uno con `EXPLAIN`.

### 1.7 Configuración (filas)

En el mismo script (02_config): filas del catálogo con id explícito, layout por defecto de los perfiles base 201–208 / 501–508 y obligatorios. No hace falta alta en `conf__paginas`: el `index.php` del módulo ya es punto de entrada y el endpoint valida por empresa + página de cada widget (ver §3).

## 2. Widgets iniciales

Fechas de negocio en la zona horaria del servidor PHP (`date('Y-m-d')`), pasadas como parámetro: nunca `CURDATE()` (MariaDB local y MySQL de producción pueden diferir). Importes en moneda local (`importe * tipo_cambio`), como la cuenta corriente. Estados por `codigo_estandar`, no por id.

| # | Código (id m2 / m5) | Tipo | Página de permiso (m2) | Fuente | Cálculo | TTL |
|---|---|---|---|---|---|---|
| 1 | `ventas_hoy` (1 / 501) | kpi | 56 `ventas_facturas.php` | `gestion__comprobantes` con `tabla_origen_id` = tabla de `gestion__ventas_facturas` (resuelta por nombre en `conf__tablas`), estados CONFIRMADO / CANC_PARCIAL / CANC_TOTAL (no entra 4 "Pendiente de ARCA"), `SUM(signo * importe_neto * tipo_cambio)`, `f_emision = hoy`, filtro de sucursales permitidas. Sub: cantidad de comprobantes. | al vuelo | 0 |
| 2 | `ventas_mes` (2 / 502) | kpi + minichart línea | 56 | Igual, del 1 del mes a hoy, agrupado por día (serie del minichart) y el mismo tramo del mes anterior (1 al día equivalente). Variación % contra ese tramo, no contra el mes anterior completo, que siempre daría "bajando" a mitad de mes. | al vuelo, ≤ 62 días por índice | 300 |
| 3 | `pedidos_pendientes` (3 / 503) | kpi, + `badge` (4 / 504) sobre la página 91 | 91 `ventas_pedidos_gestion.php` (`requiere_bienes`) | `gestion__ventas_pedidos`, estado 5 "Confirmado - Pend. Revisión" (a preparar) y 9 "En preparación" (sub), `GROUP BY sucursal_id`, sucursales permitidas. | al vuelo (opción A, recomendada) | 0 |
| 5 | `saldo_cobrar` (5 / 505) | kpi | 103 `clientes_ctacte.php` | Criterio de `clientes_ctacte_model.php` (`ctacteSqlComprobantes`): deuda = pendiente de los tipos que cancela el RCX; sub: vencido y a favor. Saldo de la empresa entera, como decide esa página (un cliente debe lo mismo lo mire quien lo mire). Necesita una función nueva `ctacteTotalesEmpresa()` en el modelo (sin `GROUP BY entidad`). | al vuelo + caché | 600 |
| 6 | `saldo_pagar` (6 / 506) | kpi | 118 `proveedores_ctacte.php` | Igual con `proveedores_ctacte_model.php`. | al vuelo + caché | 600 |
| 7 | `fp_por_vencer` (7 / 507) | lista | 57 `facturas_proveedores.php` | Pendientes de la cuenta corriente de proveedores con `COALESCE(f_vto, f_emision)` entre hoy y hoy + N días (parámetro `dias`, por defecto 7), las 5 más próximas + total; sub: vencidas. | al vuelo | 0 |
| 8 | `stock_bajo_minimo` (8 / 508) | kpi + lista (5) | 113 `stock_alertas.php` (`requiere_bienes`) | `gestion__stock_alertas` con `abierta = 1`, ya materializada por `stockEvaluarNivel()`; contador con `salContarAbiertas()` (ya filtra sucursales). | lectura de lo materializado | 0 |
| 9 | `top_clientes` (9 / 509) | minichart barras | 56 | Ventas confirmadas de los últimos 30 días por `entidad_id`, top 5 + "Resto". Consumidor final sin entidad agrupado como "Consumidor final". | al vuelo + caché | 1800 |
| 10 | `top_proveedores` (10 / 510) | minichart barras | 57 | Compras (tabla de `gestion__facturas_proveedores`) por `f_contabilidad`, mismo armado. | al vuelo + caché | 1800 |
| 11 | `accesos` (11 / 511) | atajos | — (cada link se filtra por su propia página) | Servicio de accesos de §1.4 + badges en lote. | al vuelo | 0 |

Los widgets 5 y 6 (y 9 y 10) van en requests separados a propósito: `clientes_ctacte_model.php` y `proveedores_ctacte_model.php` declaran las mismas funciones (`ctacteSqlComprobantes`, `ctacteResumen`…) y no se pueden incluir en el mismo proceso. El lote de badges solo puede incluir handlers que no choquen (hoy: pedidos).

**Opción B, pedidos materializados** (solo si la elegís):

```sql
CREATE TABLE IF NOT EXISTS gestion__contadores_sucursales (
  empresa_id INT NOT NULL, sucursal_id INT NOT NULL, codigo VARCHAR(40) NOT NULL,
  valor INT NOT NULL DEFAULT 0, actualizado_en DATETIME NOT NULL,
  PRIMARY KEY (empresa_id, sucursal_id, codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Recalcula con un `COUNT` completo de la sucursal (nunca `+1/-1`), en una sola función `contadorPedidosRecalcular($conexion, $empresa_id, $sucursal_id)` llamada dentro de la transacción del cambio de estado en `ventas_pedidos_model.php`, `ventas_pedidos_gestion_model.php` y `ventas_remitos_model.php` de los dos módulos, más una reconciliación al leer si `actualizado_en` tiene más de 1 hora. Son 6 archivos que tocar y un contador que puede desviarse; por eso no lo recomiendo con este volumen.

## 3. Endpoints

Archivos: `modules/gestion/inicio_ajax.php` y `modules/gestion_normal/inicio_ajax.php`, finos (fijan el módulo e incluyen el motor). El motor es uno solo en `core/dashboard/` (servicio de layout, accesos, permisos, caché y el despachador de handlers), así no hay dos copias que sincronizar. Handlers en `core/dashboard/widgets/<handler>.php`, cada uno devuelve un callable `function (mysqli $c, array $ctx, array $params): array`. Incluyen el modelo de `modules/gestion/` (las tablas son las mismas en el 2 y el 5).

Arranque común: `ob_start()` → `ajax_guard()` (sesión + empresa de la sesión + perfil en ella; sin `pagina_idx`) → módulo fijo del archivo, validando que el usuario tenga un perfil de ese módulo en la empresa (403 si no) → `try { … } catch (Throwable $e) { error_log(…) }` → `ob_clean()` → `json_encode`. Ningún parámetro del request elige empresa, sucursal, usuario ni perfil. Respuesta siempre `{success, data, message}`; los mensajes de error al usuario son genéricos y el detalle solo va a `error_log`.

### GET `accion=layout`
```json
{ "success": true, "message": "",
  "data": {
    "widgets": [ { "widget_id": 1, "codigo": "ventas_hoy", "titulo": "Ventas de hoy", "ayuda": "…",
                   "tipo": "kpi", "ancho": 3, "orden": 1 } ],
    "accesos": [ { "pagina_id": 91, "titulo": "Nuevos Pedidos", "url": "ventas_pedidos_gestion.php?pagina_id=91&empresa_id=2&modulo_id=2",
                   "icono": "fa-solid fa-cart-shopping", "obligatorio": true, "badge_widget_id": 4 } ],
    "personalizado": { "widgets": false, "accesos": false } } }
```
Solo widgets visibles y permitidos, en orden. `url` armada en el servidor. Los títulos se muestran con `textContent`.

### GET `accion=widget&widget_id=N[&forzar=1]`
Valida: el widget existe, está activo, es del módulo y el usuario tiene su `pagina_id` (en **cada** llamada). Si no: `success:false`, `codigo:"WIDGET_NO_PERMITIDO"` (HTTP 403). Error del handler: `success:false`, `codigo:"WIDGET_ERROR"`, HTTP 200, el front muestra "No se pudo cargar · Reintentar".
```json
{ "success": true, "message": "",
  "data": { "tipo": "kpi", "valor": 1234567.89, "formato": "moneda",
            "sub": [ { "etiqueta": "Comprobantes", "valor": 14, "formato": "entero" } ],
            "variacion_pct": 12.4, "serie": [ 0, 1200.5, 800 ], "link": "ventas_facturas.php?…" },
  "meta": { "calculado_en": "2026-10-28 10:15:00", "desde_cache": true } }
```
Otros tipos: `lista` → `{filas:[{texto, detalle, valor, formato, link, alerta:bool}], total, vacio}`; `minichart` → `{grafico:"barras"|"linea", series:[{etiqueta, valor}], formato}`. `formato` ∈ `moneda | entero | decimal | porcentaje` (lista blanca: el front formatea, nunca interpreta HTML). `forzar=1` saltea la caché.

### GET `accion=badges`
`{ "data": { "91": 3 } }`: un request para todos los badges de los accesos visibles; cada uno valida su página.

### GET `accion=config`
Para el modal: `widgets` (todos los permitidos con `visible`, `orden`, `ancho`), `accesos.obligatorios` (bloqueados), `accesos.disponibles` (candidatas ofrecidas) y `accesos.elegidos` (ids en orden), `anchos_validos: [3,4,6,12]`.

### POST `accion=guardar` (CSRF: header `X-CSRF-Token`, `csrf_exigir()`)
```json
{ "widgets": [ { "widget_id": 1, "visible": 1, "orden": 1, "ancho": 3 } ],
  "accesos": [ 93, 94 ] }
```
Validación: cada `widget_id` ∈ widgets permitidos del módulo; `ancho` ∈ lista blanca; `accesos` ∈ candidatas ofrecidas (un obligatorio o una página no ofrecida → 400 con el id rechazado, no se guarda nada); sin repetidos; tope de 50 y 20. El orden se toma de la posición en el arreglo, no de un número del cliente. Una transacción: borra las filas del usuario de ese módulo en la empresa, inserta, upsert de `conf__usuarios_inicio`. Respuesta `{success:true, message:"Guardado"}` → toast `UiSimple.ok`.

### POST `accion=restablecer` (CSRF)
Borra las filas del usuario de ese módulo en la empresa (widgets y accesos) y la marca. Devuelve el `layout` resultante para redibujar sin recargar.

## 4. Frontend (resumen; se detalla en la Fase 3)

`index.php` con `$ui_simple = true`, `ui_encabezado('Inicio', …, botón "Personalizar")`, `csrf_token()` en `window`, grilla `row g-3` con `col-12 col-md-6 col-lg-{ancho}`. Dos archivos JS: `assets/js/dashboard/widgets.js` (pide el layout, crea un esqueleto por widget, carga cada uno con su propio `fetch` en paralelo, dibuja por `tipo`: un renderizador por tipo, no por widget, así un widget nuevo de un tipo existente no toca el front) y `assets/js/dashboard/config.js` (modal, drag & drop con HTML5 nativo y botones subir/bajar para teclado y celular, guardar/restablecer). Minicharts en SVG armado con `createElementNS` (sin `innerHTML`). La barra superior de `header1.php` pasa a usar el servicio de accesos de `core/dashboard/`.

## 5. Riesgos

1. **Cambio de comportamiento de la barra de accesos para todos los módulos**: `header1.php` es común. Mitigación: sin personalizar y sin obligatorios, el resultado es exactamente el actual.
2. **Números que no coinciden con otras pantallas**: por eso saldos = criterio de cuenta corriente, y ventas = mismos estados que el Libro IVA. El dashboard de tesorería hoy da otro número (bug de la sección 0).
3. **Caché y permisos**: la caché guarda datos ya filtrados; la clave incluye sucursales, y el permiso de página se valida antes de leerla. Si se le quita el permiso a un usuario, deja de ver el widget de inmediato.
4. **Funciones duplicadas en los modelos de cuenta corriente**: un handler nunca puede incluir los dos (error fatal); se documenta en el handler.
5. **Rendimiento del chequeo de permisos**: `usuario_obtener_paginas_permitidas()` hace una consulta por perfil y se llama en cada request de widget (≈ 10 por carga). Aceptable (2–3 consultas indexadas); si molesta, se memoriza en `$_SESSION` con la versión de permisos (provisión futura).
6. **Tablas `latin1`** (`gestion__ventas_facturas`, `gestion__ventas_pedidos`): no hay FK ni comparación de texto hacia ellas, sin impacto.
7. **Fechas**: zona horaria de PHP como única fuente (§2).
8. **Estudio contable** tiene su propio `dashboard_estudio.php` (página 401): queda fuera; podría migrarse al motor más adelante.

## 6. Provisión futura (no se construye ahora)

- Override por empresa de los valores por defecto y obligatorios (`conf__dashboard_empresas_perfiles_widgets`).
- Pantalla de administración para los defaults y obligatorios por perfil (en la Fase 2 se cargan por script).
- Parámetros por usuario de cada widget (p. ej. días de "por vencer").
- Selector de sucursal en el dashboard (hoy: todas las permitidas).
- Contador materializado de pedidos (opción B), si el volumen lo justifica.
- Refresco automático periódico de los widgets.

## 7. Supuestos y preguntas abiertas

1. ¿Defaults y obligatorios por **perfil base** (recomendado) o por perfil de empresa?
2. ¿Pedidos pendientes **al vuelo** (recomendado) o materializados (opción B)?
3. "Pendientes de preparar" = estado 5 (Confirmado - Pend. Revisión). ¿Sumo el 9 (En preparación) al número principal o solo como subtítulo?
4. Ventas: ¿**neto sin IVA** (propuesto) o total?
5. ¿Ventas de comprobantes X (no fiscales) entran? Propuesto: sí.
6. ¿Arreglo en esta tarea el bug de signos de `obtenerFlujoFondosProyectado()` o lo dejo anotado?
7. ¿Alguna página tiene que dejar de ser candidata o pasar a obligatoria para algún perfil base? Propuesta para la semilla: obligatorio "Nuevos Pedidos" para Ventas y Depósito; el resto, candidatas.
