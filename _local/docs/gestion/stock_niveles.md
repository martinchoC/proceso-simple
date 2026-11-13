# Stock mínimo / máximo por boca y alertas de reposición

Fecha: 2026-10-08. Scripts: `_local/sql/mantenimiento/20261008_stock_niveles_01_ddl.sql`, `_02_configuracion.sql`
(requieren `20261008_stock_01_ddl.sql`, el servicio central de stock).

## Modelo

| Tabla | conf__tablas | Tipo | Qué guarda |
|---|---|---|---|
| `gestion__stock_niveles` | 429 | Diccionario (1 Activo / 2 Inactivo) | Mínimo y máximo por `(empresa, boca, producto)`, cargados a mano. UNIQUE por par. |
| `gestion__stock_alertas` | 430 | Proceso corto (17 / 18 / 21 / 26) | Alerta de reposición **materializada**. Una sola abierta por par (UNIQUE sobre `abierta` 1/NULL). |

- La boca tiene que tener `es_deposito = 1`. El producto, `controla_stock = 1`.
- No hay `sucursal_id`: sale de `gestion__bocas` (filtro de accesos sobre `b.sucursal_id`).
- `stock_maximo` no genera alerta, solo sirve para calcular `cantidad_sugerida = max(COALESCE(máximo, mínimo) − stock, 0)`.

## Cómo se dispara la alerta

`stockEvaluarNivel()` (`modules/gestion/stock_niveles_core_model.php`) es la única que escribe alertas. La llaman:

1. **El servicio central de stock** (`stock_core_model.php`), una vez por movimiento, justo después de actualizar `gestion__stock` con la fila bloqueada y dentro de la misma transacción.
2. **La carga y edición de niveles** (`stockReevaluarNivel`): alta, cambio del mínimo, habilitar o inhabilitar, y copiar de otra boca.

| Situación | Resultado |
|---|---|
| El stock cruza el mínimo hacia abajo (`anterior >= mín` y `nueva < mín`) | Abre la alerta: 17 Pendiente |
| Se carga o sube un mínimo por encima del stock actual | Abre la alerta: 17 |
| Sigue bajo el mínimo con la alerta abierta | Actualiza `stock_actual`, las copias de mínimo y máximo, y la sugerida |
| `stock >= mínimo` con la alerta abierta | La cierra: 21 Repuesta, motivo `REPOSICION` |
| Se baja el mínimo por debajo del stock | La cierra: 26, `NIVEL_MODIFICADO` |
| Se inhabilita el nivel | La cierra: 26, `NIVEL_INACTIVO` |
| El usuario la descarta (página 113) | La cierra: 26, `DESCARTADA`. **No se reabre** con el próximo egreso, sino cuando reponen y vuelve a caer, o cuando cambian el mínimo |

El usuario también puede **Tomar** una alerta (17 → 18 En gestión): queda registrado quién y cuándo. Igual se cierra sola cuando ingresa la mercadería.

Orden de locks: `gestion__stock` → `gestion__stock_niveles` → `gestion__stock_alertas`. La conciliación (debe dar 0 filas, salvo las descartadas) está comentada al final del script 01.

## Pantallas

- **112 Stock - Mínimos y Máximos** (`stock_niveles.php`): grilla por boca con el stock actual, el filtro "solo bajo el mínimo", Agregar, Editar, Inhabilitar/Habilitar y "Copiar de otra boca" (opcionalmente sobrescribe).
- **113 Stock - Alertas de Reposición** (`stock_alertas.php`): abiertas y cerradas de los últimos 30 días, con Tomar y Descartar.
- **Campana del header** (`header1.php`): cantidad de alertas abiertas, solo para quien tiene acceso a la página 113, y filtrada por las sucursales del usuario.

## Mejora futura (no implementada): mínimo y máximo sugeridos por consumo

Objetivo: proponer valores en lugar de cargarlos a mano. El usuario los acepta o los corrige.

1. **Consumo**: egresos de venta por `(boca, producto)` en los últimos N días (parámetro, p. ej. 90), tomados de `gestion__stock_movimientos` (`tipo_movimiento = 'EGRESO'`, `tabla_origen_id` de ventas). Se excluyen ajustes y transferencias. Se calcula el consumo diario promedio `d` y su desvío `σ`.
2. **Mínimo sugerido** = `d × L + z × σ × √L`, donde `L` son los días de reposición del proveedor habitual y `z` el nivel de servicio (1,65 ≈ 95 %).
3. **Máximo sugerido** = mínimo sugerido + `d × R`, donde `R` son los días entre revisiones o compras.
4. **Cuándo**: en lote, con un botón "Calcular sugeridos" o un proceso nocturno. Nunca al vuelo.
5. **Dónde se guarda**: una tabla nueva `gestion__stock_niveles_sugeridos` (boca, producto, fecha de cálculo, parámetros usados, `d`, `σ`, mínimo y máximo sugeridos). Al aceptar se copian a `gestion__stock_niveles` y se agrega ahí `origen_valor ENUM('MANUAL','SUGERIDO')`. Pasan por `stockReevaluarNivel`, igual que una edición manual.
6. **Casos a cubrir**:
   - productos nuevos con menos de N días de historia (sin sugerencia)
   - quiebres de stock, que subestiman el consumo (excluir los días con stock 0)
   - estacionalidad
   - productos vendidos desde otra boca de la misma sucursal
