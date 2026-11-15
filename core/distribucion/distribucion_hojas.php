<?php
/**
 * Distribución: armado del reparto, hojas de ruta, lista de carga y rendición (pasos 5b-4 y 5b-5, doc 24).
 * Hoja: 3 Borrador → 5 Confirmada → 21 Rendida; 38 Anulada. Estado propio (servicio), no motor de botones.
 * Un pedido está en una sola hoja activa (Borrador o Confirmada). La rendición registra el resultado de cada pedido;
 * no mueve stock, remitos ni cobranzas (decisión 4). Sin el indicador las acciones no corren.
 */

require_once __DIR__ . '/distribucion_service.php';

const VDHR_RESULTADOS = ['ENTREGADO', 'PARCIAL', 'RECHAZADO', 'NO_VISITADO'];

if (!function_exists('vdhr_exigir')) {
    /** @throws InvalidArgumentException sin el indicador */
    function vdhr_exigir(mysqli $conexion, int $empresa_id): void
    {
        if (!vdis_empresa_usa($conexion, $empresa_id)) {
            throw new InvalidArgumentException('La empresa no usa distribución (Configuración > Empresas).');
        }
    }
}

if (!function_exists('vdhr_hoja_fila')) {
    /** Cabecera de la hoja con su camión (o null). */
    function vdhr_hoja_fila(mysqli $conexion, int $empresa_id, int $hoja_id, bool $bloquear = false): ?array
    {
        $r = vcar_filas($conexion,
            "SELECT h.*, c.nombre AS camion_nombre, c.patente, c.capacidad_kg, c.capacidad_m3, b.boca_nombre
               FROM gestion__distribucion_hojas h
               LEFT JOIN gestion__distribucion_camiones c ON c.camion_id = h.camion_id
               LEFT JOIN gestion__bocas b ON b.boca_id = h.boca_id
              WHERE h.hoja_id = ? AND h.empresa_id = ?" . ($bloquear ? ' FOR UPDATE' : ''), 'ii', [$hoja_id, $empresa_id]);
        return $r ? $r[0] : null;
    }
}

if (!function_exists('vdhr_pedidos_info')) {
    /** Datos de los pedidos para el armado y la hoja: cliente, entrega, total, zona, vendedor, depósito y peso / volumen. */
    function vdhr_pedidos_info(mysqli $conexion, int $empresa_id, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            return [];
        }
        $con_vend = vcar_empresa_usa($conexion, $empresa_id);
        $filas = vcar_filas($conexion,
            "SELECT vp.venta_pedido_id, vp.comprobante_nro, vp.f_emision, vp.f_entrega_estimada, vp.total, vp.tabla_estado_registro_id,
                    vp.entidad_id, vp.entidad_sucursal_id, vp.direccion_entrega, vp.observaciones, vp.boca_despacho_id, vp.sucursal_id,
                    " . ($con_vend ? 've.entidad_nombre' : 'NULL') . " AS vendedor_nombre,
                    e.entidad_nombre, e.entidad_fantasia, es.sucursal_nombre AS entrega_sucursal, es.sucursal_telefono, b.boca_nombre AS deposito,
                    cp.condicion_pago, ct.comprobante_tipo
               FROM gestion__ventas_pedidos vp
               LEFT JOIN gestion__entidades e ON e.entidad_id = vp.entidad_id
               LEFT JOIN gestion__entidades_sucursales es ON es.sucursal_id = vp.entidad_sucursal_id
               LEFT JOIN gestion__bocas b ON b.boca_id = vp.boca_despacho_id
               LEFT JOIN gestion__condiciones_pago cp ON cp.condicion_pago_id = vp.condicion_pago_id
               LEFT JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = vp.comprobante_tipo_id
               " . ($con_vend ? 'LEFT JOIN gestion__entidades ve ON ve.entidad_id = vp.vendedor_entidad_id' : '') . "
              WHERE vp.empresa_id = ? AND vp.venta_pedido_id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")",
            'i' . str_repeat('i', count($ids)), array_merge([$empresa_id], $ids));
        $pesos = vdis_pesos_pedidos($conexion, $empresa_id, $ids);
        $out = [];
        foreach ($filas as $f) {
            $id = (int) $f['venta_pedido_id'];
            $z = vdis_zona_de_cliente($conexion, $empresa_id, (int) $f['entidad_id'], $f['entidad_sucursal_id'] ? (int) $f['entidad_sucursal_id'] : null);
            $f['zona_id'] = $z['zona_id'] ?? null;
            $f['zona_nombre'] = $z['nombre'] ?? null;
            $out[$id] = $f + ($pesos[$id] ?? ['peso_kg' => 0, 'volumen_m3' => 0, 'sin_peso' => 0, 'sin_volumen' => 0, 'lineas' => 0]);
        }
        return $out;
    }
}

if (!function_exists('vdhr_disponibles')) {
    /**
     * Pedidos para armar: en 42, de la empresa, sin hoja activa (Borrador o Confirmada). Filtros: boca_id (depósito de
     * despacho), zona_id (0 = todas, -1 = sin zona).
     */
    function vdhr_disponibles(mysqli $conexion, int $empresa_id, array $filtros = []): array
    {
        vdhr_exigir($conexion, $empresa_id);
        $boca = intval($filtros['boca_id'] ?? 0);
        $ids = array_column(vcar_filas($conexion,
            "SELECT vp.venta_pedido_id FROM gestion__ventas_pedidos vp
              WHERE vp.empresa_id = ? AND vp.tabla_estado_registro_id = ?" . ($boca > 0 ? ' AND vp.boca_despacho_id = ' . $boca : '') . "
                AND NOT EXISTS (SELECT 1 FROM gestion__distribucion_hojas_pedidos hp JOIN gestion__distribucion_hojas h ON h.hoja_id = hp.hoja_id
                                 WHERE hp.venta_pedido_id = vp.venta_pedido_id AND hp.tabla_estado_registro_id = 1 AND h.tabla_estado_registro_id IN (3, 5))
              ORDER BY vp.f_entrega_estimada IS NULL, vp.f_entrega_estimada, vp.venta_pedido_id LIMIT 1000",
            'ii', [$empresa_id, VDIS_ESTADO_PARA_DISTRIBUCION]), 'venta_pedido_id');
        $info = vdhr_pedidos_info($conexion, $empresa_id, $ids);
        $zona = intval($filtros['zona_id'] ?? 0);
        $out = [];
        foreach ($ids as $id) {
            $p = $info[(int) $id] ?? null;
            if (!$p) continue;
            if ($zona > 0 && (int) $p['zona_id'] !== $zona) continue;
            if ($zona === -1 && $p['zona_id'] !== null) continue;
            $out[] = $p;
        }
        return $out;
    }
}

if (!function_exists('vdhr_hojas')) {
    /** Hojas de una fecha (o de un rango con $hasta), con cantidad de pedidos y peso / volumen (foto o en vivo). */
    function vdhr_hojas(mysqli $conexion, int $empresa_id, string $desde, ?string $hasta = null, bool $incluir_anuladas = false): array
    {
        $desde = vcar_fecha($desde);
        $hasta = $hasta ? vcar_fecha($hasta) : $desde;
        $hojas = vcar_filas($conexion,
            "SELECT h.*, c.nombre AS camion_nombre, c.patente, c.capacidad_kg, c.capacidad_m3, b.boca_nombre,
                    (SELECT COUNT(*) FROM gestion__distribucion_hojas_pedidos hp WHERE hp.hoja_id = h.hoja_id AND hp.tabla_estado_registro_id = 1) AS pedidos
               FROM gestion__distribucion_hojas h
               LEFT JOIN gestion__distribucion_camiones c ON c.camion_id = h.camion_id
               LEFT JOIN gestion__bocas b ON b.boca_id = h.boca_id
              WHERE h.empresa_id = ? AND h.fecha_reparto BETWEEN ? AND ?" . ($incluir_anuladas ? '' : ' AND h.tabla_estado_registro_id <> 38') . "
              ORDER BY h.fecha_reparto, h.numero", 'iss', [$empresa_id, $desde, $hasta]);
        foreach ($hojas as &$h) {
            if ((int) $h['tabla_estado_registro_id'] === 3) {
                $t = vdhr_totales_vivos($conexion, $empresa_id, (int) $h['hoja_id']);
                $h['peso_kg'] = $t['peso_kg'];
                $h['volumen_m3'] = $t['volumen_m3'];
                $h['sin_datos'] = $t['sin_datos'];
            }
        }
        unset($h);
        return $hojas;
    }
}

if (!function_exists('vdhr_ids_hoja')) {
    /** Pedidos activos de la hoja, en orden. */
    function vdhr_ids_hoja(mysqli $conexion, int $hoja_id): array
    {
        return array_map('intval', array_column(vcar_filas($conexion,
            "SELECT venta_pedido_id FROM gestion__distribucion_hojas_pedidos WHERE hoja_id = ? AND tabla_estado_registro_id = 1 ORDER BY orden, hoja_pedido_id",
            'i', [$hoja_id]), 'venta_pedido_id'));
    }
}

if (!function_exists('vdhr_totales_vivos')) {
    function vdhr_totales_vivos(mysqli $conexion, int $empresa_id, int $hoja_id, array $extra_ids = []): array
    {
        $ids = array_values(array_unique(array_merge(vdhr_ids_hoja($conexion, $hoja_id), array_map('intval', $extra_ids))));
        $kg = 0.0; $m3 = 0.0; $sin = 0;
        foreach (vdis_pesos_pedidos($conexion, $empresa_id, $ids) as $p) {
            $kg += $p['peso_kg'];
            $m3 += $p['volumen_m3'];
            $sin += ($p['sin_peso'] > 0 || $p['sin_volumen'] > 0) ? 1 : 0;
        }
        return ['peso_kg' => round($kg, 3), 'volumen_m3' => round($m3, 4), 'sin_datos' => $sin, 'pedidos' => count($ids)];
    }
}

if (!function_exists('vdhr_hoja')) {
    /** Hoja con sus pedidos en orden (datos, peso / volumen y rendición). */
    function vdhr_hoja(mysqli $conexion, int $empresa_id, int $hoja_id): ?array
    {
        $h = vdhr_hoja_fila($conexion, $empresa_id, $hoja_id);
        if (!$h) {
            return null;
        }
        $lineas = vcar_filas($conexion,
            "SELECT hoja_pedido_id, venta_pedido_id, orden, peso_kg AS foto_peso, volumen_m3 AS foto_volumen, sin_datos, resultado, motivo, cobrado_importe, cobrado_medio
               FROM gestion__distribucion_hojas_pedidos WHERE hoja_id = ? AND tabla_estado_registro_id = 1 ORDER BY orden, hoja_pedido_id", 'i', [$hoja_id]);
        $info = vdhr_pedidos_info($conexion, $empresa_id, array_column($lineas, 'venta_pedido_id'));
        foreach ($lineas as &$l) {
            $l += $info[(int) $l['venta_pedido_id']] ?? [];
        }
        unset($l);
        $h['pedidos'] = $lineas;
        $t = (int) $h['tabla_estado_registro_id'] === 3 ? vdhr_totales_vivos($conexion, $empresa_id, $hoja_id) : null;
        if ($t) {
            $h['peso_kg'] = $t['peso_kg'];
            $h['volumen_m3'] = $t['volumen_m3'];
        }
        return $h;
    }
}

if (!function_exists('vdhr_crear')) {
    /** Hoja nueva en Borrador (número correlativo por empresa). Chofer y depósito: los del camión si no vienen. */
    function vdhr_crear(mysqli $conexion, int $empresa_id, array $d, int $usuario_id): int
    {
        vdhr_exigir($conexion, $empresa_id);
        $fecha = vcar_fecha($d['fecha_reparto'] ?? null);
        $camion = intval($d['camion_id'] ?? 0);
        $c = $camion > 0 ? vcar_filas($conexion, "SELECT * FROM gestion__distribucion_camiones WHERE camion_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1", 'ii', [$camion, $empresa_id]) : [];
        if ($camion > 0 && !$c) {
            throw new InvalidArgumentException('El camión no existe o está inactivo.');
        }
        $chofer = trim((string) ($d['chofer'] ?? '')) ?: ($c[0]['chofer'] ?? null);
        $boca = intval($d['boca_id'] ?? 0) ?: (intval($c[0]['boca_id'] ?? 0) ?: null);
        if ($boca !== null && !vdis_es_deposito($conexion, $empresa_id, $boca)) {
            throw new InvalidArgumentException('El depósito no es de esta empresa.');
        }
        $num = (int) (vcar_filas($conexion, "SELECT IFNULL(MAX(numero), 0) + 1 AS n FROM gestion__distribucion_hojas WHERE empresa_id = ? FOR UPDATE", 'i', [$empresa_id])[0]['n'] ?? 1);
        return vcar_ejecutar($conexion,
            "INSERT INTO gestion__distribucion_hojas (empresa_id, numero, fecha_reparto, camion_id, chofer, boca_id, observaciones, creado_por)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            'iisisisi', [$empresa_id, $num, $fecha, $camion ?: null, $chofer ? mb_substr($chofer, 0, 100) : null, $boca,
                         mb_substr(trim((string) ($d['observaciones'] ?? '')), 0, 255) ?: null, $usuario_id], true);
    }
}

if (!function_exists('vdhr_editar')) {
    /** Cabecera de una hoja en Borrador o Confirmada (fecha, camión, chofer, depósito, observaciones). */
    function vdhr_editar(mysqli $conexion, int $empresa_id, int $hoja_id, array $d): void
    {
        vdhr_exigir($conexion, $empresa_id);
        $h = vdhr_hoja_fila($conexion, $empresa_id, $hoja_id, true);
        if (!$h || !in_array((int) $h['tabla_estado_registro_id'], [3, 5], true)) {
            throw new InvalidArgumentException('La hoja no existe o ya no se puede modificar.');
        }
        $camion = intval($d['camion_id'] ?? 0) ?: null;
        if ($camion && !vcar_filas($conexion, "SELECT 1 FROM gestion__distribucion_camiones WHERE camion_id = ? AND empresa_id = ?", 'ii', [$camion, $empresa_id])) {
            throw new InvalidArgumentException('El camión no existe.');
        }
        $boca = intval($d['boca_id'] ?? 0) ?: null;
        if ($boca !== null && !vdis_es_deposito($conexion, $empresa_id, $boca)) {
            throw new InvalidArgumentException('El depósito no es de esta empresa.');
        }
        vcar_ejecutar($conexion, "UPDATE gestion__distribucion_hojas SET fecha_reparto = ?, camion_id = ?, chofer = ?, boca_id = ?, observaciones = ?, actualizado_en = NOW()
                                   WHERE hoja_id = ? AND empresa_id = ?",
            'sisisii', [vcar_fecha($d['fecha_reparto'] ?? $h['fecha_reparto']), $camion, mb_substr(trim((string) ($d['chofer'] ?? '')), 0, 100) ?: null, $boca,
                        mb_substr(trim((string) ($d['observaciones'] ?? '')), 0, 255) ?: null, $hoja_id, $empresa_id]);
    }
}

if (!function_exists('vdhr_capacidad')) {
    /** Avisos si los totales superan la capacidad del camión de la hoja ([] = entra). */
    function vdhr_capacidad(array $hoja, array $tot): array
    {
        $avisos = [];
        if ($hoja['capacidad_kg'] !== null && (float) $hoja['capacidad_kg'] > 0 && $tot['peso_kg'] > (float) $hoja['capacidad_kg']) {
            $avisos[] = 'Peso: ' . rtrim(rtrim(number_format($tot['peso_kg'], 1, ',', ''), '0'), ',') . ' kg de ' . rtrim(rtrim(number_format((float) $hoja['capacidad_kg'], 1, ',', ''), '0'), ',') . ' kg.';
        }
        if ($hoja['capacidad_m3'] !== null && (float) $hoja['capacidad_m3'] > 0 && $tot['volumen_m3'] > (float) $hoja['capacidad_m3']) {
            $avisos[] = 'Volumen: ' . rtrim(rtrim(number_format($tot['volumen_m3'], 2, ',', ''), '0'), ',') . ' m³ de ' . rtrim(rtrim(number_format((float) $hoja['capacidad_m3'], 2, ',', ''), '0'), ',') . ' m³.';
        }
        return $avisos;
    }
}

if (!function_exists('vdhr_agregar_pedidos')) {
    /**
     * Suma pedidos (en 42, sin otra hoja activa) al final de una hoja en Borrador o Confirmada. Si superan la
     * capacidad del camión y no viene $forzar, no graba y devuelve ['requiere_confirmar' => true, 'avisos' => [...]]
     * (decisión 3: avisa y pide confirmar). Devuelve ['agregados', 'avisos'].
     */
    function vdhr_agregar_pedidos(mysqli $conexion, int $empresa_id, int $hoja_id, array $pedido_ids, bool $forzar = false): array
    {
        vdhr_exigir($conexion, $empresa_id);
        $h = vdhr_hoja_fila($conexion, $empresa_id, $hoja_id, true);
        if (!$h || !in_array((int) $h['tabla_estado_registro_id'], [3, 5], true)) {
            throw new InvalidArgumentException('La hoja no existe o ya no se puede modificar.');
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $pedido_ids))));
        if (!$ids) {
            throw new InvalidArgumentException('Elegí al menos un pedido.');
        }
        $ok = array_map('intval', array_column(vcar_filas($conexion,
            "SELECT vp.venta_pedido_id FROM gestion__ventas_pedidos vp
              WHERE vp.empresa_id = ? AND vp.tabla_estado_registro_id = ? AND vp.venta_pedido_id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")
                AND NOT EXISTS (SELECT 1 FROM gestion__distribucion_hojas_pedidos hp JOIN gestion__distribucion_hojas x ON x.hoja_id = hp.hoja_id
                                 WHERE hp.venta_pedido_id = vp.venta_pedido_id AND hp.tabla_estado_registro_id = 1 AND x.tabla_estado_registro_id IN (3, 5))
              FOR UPDATE",
            'ii' . str_repeat('i', count($ids)), array_merge([$empresa_id, VDIS_ESTADO_PARA_DISTRIBUCION], $ids)), 'venta_pedido_id'));
        if (count($ok) !== count($ids)) {
            throw new InvalidArgumentException('Hay pedidos que no están para distribución o ya están en otra hoja.');
        }
        $tot = vdhr_totales_vivos($conexion, $empresa_id, $hoja_id, $ids);
        $avisos = vdhr_capacidad($h, $tot);
        if ($avisos && !$forzar) {
            return ['requiere_confirmar' => true, 'avisos' => $avisos, 'agregados' => 0];
        }
        $orden = (int) (vcar_filas($conexion, "SELECT IFNULL(MAX(orden), 0) AS o FROM gestion__distribucion_hojas_pedidos WHERE hoja_id = ? AND tabla_estado_registro_id = 1",
            'i', [$hoja_id])[0]['o'] ?? 0);
        foreach ($ids as $id) {
            vcar_ejecutar($conexion, "INSERT INTO gestion__distribucion_hojas_pedidos (hoja_id, venta_pedido_id, orden) VALUES (?, ?, ?)", 'iii', [$hoja_id, $id, ++$orden], true);
        }
        if ((int) $h['tabla_estado_registro_id'] === 5) {
            vdhr_foto($conexion, $empresa_id, $hoja_id);
        }
        return ['agregados' => count($ids), 'avisos' => $avisos];
    }
}

if (!function_exists('vdhr_quitar_pedido')) {
    /** Saca un pedido de una hoja en Borrador o Confirmada (vuelve a quedar para armar). */
    function vdhr_quitar_pedido(mysqli $conexion, int $empresa_id, int $hoja_id, int $pedido_id): void
    {
        vdhr_exigir($conexion, $empresa_id);
        $h = vdhr_hoja_fila($conexion, $empresa_id, $hoja_id, true);
        if (!$h || !in_array((int) $h['tabla_estado_registro_id'], [3, 5], true)) {
            throw new InvalidArgumentException('La hoja no existe o ya no se puede modificar.');
        }
        if (vcar_ejecutar($conexion, "UPDATE gestion__distribucion_hojas_pedidos SET tabla_estado_registro_id = 2, actualizado_en = NOW()
                                       WHERE hoja_id = ? AND venta_pedido_id = ? AND tabla_estado_registro_id = 1", 'ii', [$hoja_id, $pedido_id]) === 0) {
            throw new InvalidArgumentException('El pedido no está en la hoja.');
        }
        if ((int) $h['tabla_estado_registro_id'] === 5) {
            vdhr_foto($conexion, $empresa_id, $hoja_id);
        }
    }
}

if (!function_exists('vdhr_ordenar')) {
    /** Orden de visita: los pedidos de la hoja en el orden dado (los que falten quedan al final, en su orden). */
    function vdhr_ordenar(mysqli $conexion, int $empresa_id, int $hoja_id, array $pedido_ids): void
    {
        vdhr_exigir($conexion, $empresa_id);
        $h = vdhr_hoja_fila($conexion, $empresa_id, $hoja_id, true);
        if (!$h || !in_array((int) $h['tabla_estado_registro_id'], [3, 5], true)) {
            throw new InvalidArgumentException('La hoja no existe o ya no se puede modificar.');
        }
        $actuales = vdhr_ids_hoja($conexion, $hoja_id);
        $nuevo = array_values(array_intersect(array_values(array_unique(array_map('intval', $pedido_ids))), $actuales));
        $nuevo = array_merge($nuevo, array_values(array_diff($actuales, $nuevo)));
        foreach ($nuevo as $i => $id) {
            vcar_ejecutar($conexion, "UPDATE gestion__distribucion_hojas_pedidos SET orden = ? WHERE hoja_id = ? AND venta_pedido_id = ? AND tabla_estado_registro_id = 1",
                'iii', [$i + 1, $hoja_id, $id]);
        }
    }
}

if (!function_exists('vdhr_foto')) {
    /** Guarda peso / volumen de cada pedido y el total de la hoja (al confirmar y si cambia una confirmada). */
    function vdhr_foto(mysqli $conexion, int $empresa_id, int $hoja_id): array
    {
        $ids = vdhr_ids_hoja($conexion, $hoja_id);
        $pesos = vdis_pesos_pedidos($conexion, $empresa_id, $ids);
        $kg = 0.0; $m3 = 0.0;
        foreach ($ids as $id) {
            $p = $pesos[$id] ?? ['peso_kg' => 0, 'volumen_m3' => 0, 'sin_peso' => 0, 'sin_volumen' => 0];
            $kg += $p['peso_kg'];
            $m3 += $p['volumen_m3'];
            vcar_ejecutar($conexion, "UPDATE gestion__distribucion_hojas_pedidos SET peso_kg = ?, volumen_m3 = ?, sin_datos = ?
                                       WHERE hoja_id = ? AND venta_pedido_id = ? AND tabla_estado_registro_id = 1",
                'ddiii', [$p['peso_kg'], $p['volumen_m3'], ($p['sin_peso'] > 0 || $p['sin_volumen'] > 0) ? 1 : 0, $hoja_id, $id]);
        }
        vcar_ejecutar($conexion, "UPDATE gestion__distribucion_hojas SET peso_kg = ?, volumen_m3 = ?, actualizado_en = NOW() WHERE hoja_id = ?",
            'ddi', [round($kg, 3), round($m3, 4), $hoja_id]);
        return ['peso_kg' => round($kg, 3), 'volumen_m3' => round($m3, 4)];
    }
}

if (!function_exists('vdhr_confirmar')) {
    /** Borrador → Confirmada (con al menos un pedido y camión): guarda la foto de peso / volumen. */
    function vdhr_confirmar(mysqli $conexion, int $empresa_id, int $hoja_id, int $usuario_id): array
    {
        vdhr_exigir($conexion, $empresa_id);
        $h = vdhr_hoja_fila($conexion, $empresa_id, $hoja_id, true);
        if (!$h || (int) $h['tabla_estado_registro_id'] !== 3) {
            throw new InvalidArgumentException('Solo se confirma una hoja en Borrador.');
        }
        if (empty($h['camion_id'])) {
            throw new InvalidArgumentException('Elegí el camión de la hoja.');
        }
        if (!vdhr_ids_hoja($conexion, $hoja_id)) {
            throw new InvalidArgumentException('La hoja no tiene pedidos.');
        }
        $t = vdhr_foto($conexion, $empresa_id, $hoja_id);
        vcar_ejecutar($conexion, "UPDATE gestion__distribucion_hojas SET tabla_estado_registro_id = 5, confirmado_por = ?, confirmado_en = NOW() WHERE hoja_id = ?",
            'ii', [$usuario_id, $hoja_id]);
        return $t;
    }
}

if (!function_exists('vdhr_anular')) {
    /** Borrador o Confirmada → Anulada: sus pedidos vuelven a quedar para armar. */
    function vdhr_anular(mysqli $conexion, int $empresa_id, int $hoja_id): void
    {
        vdhr_exigir($conexion, $empresa_id);
        $h = vdhr_hoja_fila($conexion, $empresa_id, $hoja_id, true);
        if (!$h || !in_array((int) $h['tabla_estado_registro_id'], [3, 5], true)) {
            throw new InvalidArgumentException('Solo se anula una hoja en Borrador o Confirmada.');
        }
        vcar_ejecutar($conexion, "UPDATE gestion__distribucion_hojas SET tabla_estado_registro_id = 38, actualizado_en = NOW() WHERE hoja_id = ?", 'i', [$hoja_id]);
    }
}

if (!function_exists('vdhr_lista_carga')) {
    /** Lo pendiente de entregar de todos los pedidos de la hoja, sumado por producto y presentación (para cargar). */
    function vdhr_lista_carga(mysqli $conexion, int $empresa_id, int $hoja_id): array
    {
        if (!vdhr_hoja_fila($conexion, $empresa_id, $hoja_id)) {
            throw new InvalidArgumentException('La hoja no existe.');
        }
        $ids = vdhr_ids_hoja($conexion, $hoja_id);
        if (!$ids) {
            return [];
        }
        $con_pres = vcar_existe($conexion, 'gestion__ventas_pedidos_detalles', 'presentacion_id');
        $filas = vcar_filas($conexion,
            "SELECT d.producto_id, p.producto_codigo, p.producto_nombre, " . ($con_pres ? 'd.presentacion_id, d.presentacion_nombre, d.presentacion_factor' : 'NULL AS presentacion_id, NULL AS presentacion_nombre, NULL AS presentacion_factor') . ",
                    SUM(GREATEST(d.cantidad - IFNULL(d.cantidad_entregada, 0), 0)) AS unidades
               FROM gestion__ventas_pedidos_detalles d
               JOIN gestion__ventas_pedidos vp ON vp.venta_pedido_id = d.venta_pedido_id AND vp.empresa_id = ?
               LEFT JOIN gestion__productos p ON p.producto_id = d.producto_id
              WHERE d.venta_pedido_id IN (" . implode(',', array_fill(0, count($ids), '?')) . ") AND d.tabla_estado_registro_id NOT IN (2, 6)
              GROUP BY d.producto_id, p.producto_codigo, p.producto_nombre" . ($con_pres ? ', d.presentacion_id, d.presentacion_nombre, d.presentacion_factor' : '') . "
             HAVING unidades > 0
              ORDER BY p.producto_nombre, presentacion_nombre", 'i' . str_repeat('i', count($ids)), array_merge([$empresa_id], $ids));
        foreach ($filas as &$f) {
            $fac = (float) ($f['presentacion_factor'] ?? 0);
            $f['unidades'] = (float) $f['unidades'];
            $f['bultos'] = ($f['presentacion_id'] && $fac > 0) ? round($f['unidades'] / $fac, 4) : null;
        }
        unset($f);
        return $filas;
    }
}

// ----------------------------------------------------------------------------------------------- rendición

if (!function_exists('vdhr_rendir_pedido')) {
    /** Resultado de un pedido de una hoja Confirmada: ENTREGADO / PARCIAL / RECHAZADO / NO_VISITADO, motivo y cobro. */
    function vdhr_rendir_pedido(mysqli $conexion, int $empresa_id, int $hoja_id, int $pedido_id, array $d): void
    {
        vdhr_exigir($conexion, $empresa_id);
        $h = vdhr_hoja_fila($conexion, $empresa_id, $hoja_id, true);
        if (!$h || (int) $h['tabla_estado_registro_id'] !== 5) {
            throw new InvalidArgumentException('Solo se rinde una hoja Confirmada.');
        }
        $res = strtoupper(trim((string) ($d['resultado'] ?? '')));
        if (!in_array($res, VDHR_RESULTADOS, true)) {
            throw new InvalidArgumentException('Elegí el resultado de la entrega.');
        }
        $motivo = mb_substr(trim((string) ($d['motivo'] ?? '')), 0, 255);
        if (in_array($res, ['PARCIAL', 'RECHAZADO', 'NO_VISITADO'], true) && $motivo === '') {
            throw new InvalidArgumentException('Indicá el motivo (entrega parcial, rechazo o no visitado).');
        }
        $importe = vdis_num($d['cobrado_importe'] ?? null, 'Importe cobrado');
        $medio = mb_substr(trim((string) ($d['cobrado_medio'] ?? '')), 0, 50);
        if (vcar_ejecutar($conexion, "UPDATE gestion__distribucion_hojas_pedidos SET resultado = ?, motivo = ?, cobrado_importe = ?, cobrado_medio = ?, actualizado_en = NOW()
                                       WHERE hoja_id = ? AND venta_pedido_id = ? AND tabla_estado_registro_id = 1",
                'ssdsii', [$res, $motivo ?: null, $importe, $medio ?: null, $hoja_id, $pedido_id]) === 0
            && !vcar_filas($conexion, "SELECT 1 FROM gestion__distribucion_hojas_pedidos WHERE hoja_id = ? AND venta_pedido_id = ? AND tabla_estado_registro_id = 1", 'ii', [$hoja_id, $pedido_id])) {
            throw new InvalidArgumentException('El pedido no está en la hoja.');
        }
    }
}

if (!function_exists('vdhr_cerrar_rendicion')) {
    /**
     * Confirmada → Rendida cuando todos los pedidos tienen resultado. Los rechazados y no visitados que siguen en 42
     * quedan para otro reparto (ya no están en una hoja activa). Devuelve el resumen.
     */
    function vdhr_cerrar_rendicion(mysqli $conexion, int $empresa_id, int $hoja_id, int $usuario_id): array
    {
        vdhr_exigir($conexion, $empresa_id);
        $h = vdhr_hoja_fila($conexion, $empresa_id, $hoja_id, true);
        if (!$h || (int) $h['tabla_estado_registro_id'] !== 5) {
            throw new InvalidArgumentException('Solo se cierra la rendición de una hoja Confirmada.');
        }
        $filas = vcar_filas($conexion, "SELECT resultado, cobrado_importe FROM gestion__distribucion_hojas_pedidos WHERE hoja_id = ? AND tabla_estado_registro_id = 1", 'i', [$hoja_id]);
        $faltan = count(array_filter($filas, fn($f) => $f['resultado'] === null));
        if ($faltan > 0) {
            throw new InvalidArgumentException("Faltan $faltan pedido(s) sin resultado.");
        }
        $resumen = array_fill_keys(VDHR_RESULTADOS, 0);
        $cobrado = 0.0;
        foreach ($filas as $f) {
            $resumen[$f['resultado']]++;
            $cobrado += (float) $f['cobrado_importe'];
        }
        vcar_ejecutar($conexion, "UPDATE gestion__distribucion_hojas SET tabla_estado_registro_id = 21, rendido_por = ?, rendido_en = NOW() WHERE hoja_id = ?",
            'ii', [$usuario_id, $hoja_id]);
        return ['resultados' => $resumen, 'cobrado' => round($cobrado, 2)];
    }
}

// ----------------------------------------------------------------------------------------------- despachador

if (!function_exists('vdhr_despachar')) {
    function vdhr_despachar(mysqli $conexion, array $ctx, string $accion): array
    {
        $empresa_id = (int) $ctx['empresa_id'];
        $usuario_id = (int) $ctx['usuario_id'];
        if (!vdis_estructura($conexion)) {
            return ['success' => false, 'data' => null, 'message' => 'Falta aplicar el script de distribución.'];
        }
        $ok = fn($data, $msg = '') => ['success' => true, 'data' => $data, 'message' => $msg];
        $R = $_REQUEST;
        $hoja = intval($R['hoja_id'] ?? 0);
        $escritura = in_array($accion, ['hoja_crear', 'hoja_editar', 'agregar_pedidos', 'quitar_pedido', 'ordenar', 'cambiar_deposito',
                                        'confirmar', 'anular', 'rendir_pedido', 'cerrar_rendicion'], true);
        if ($escritura) {
            mysqli_begin_transaction($conexion);
        }
        try {
            switch ($accion) {
                case 'catalogos':
                    $res = $ok(['camiones' => vdis_camiones($conexion, $empresa_id), 'zonas' => vdis_zonas($conexion, $empresa_id),
                                'depositos' => vdis_depositos($conexion, $empresa_id), 'activo' => vdis_empresa_usa($conexion, $empresa_id)]);
                    break;
                case 'disponibles':
                    $res = $ok(vdhr_disponibles($conexion, $empresa_id, ['boca_id' => $R['boca_id'] ?? 0, 'zona_id' => $R['zona_id'] ?? 0]));
                    break;
                case 'hojas':
                    $res = $ok(vdhr_hojas($conexion, $empresa_id, (string) ($R['fecha'] ?? ''), ($R['hasta'] ?? '') ?: null, !empty($R['incluir_anuladas'])));
                    break;
                case 'hoja':
                    $h = vdhr_hoja($conexion, $empresa_id, $hoja);
                    $res = $h ? $ok($h) : ['success' => false, 'data' => null, 'message' => 'La hoja no existe.'];
                    break;
                case 'lista_carga':
                    $res = $ok(vdhr_lista_carga($conexion, $empresa_id, $hoja));
                    break;
                case 'hoja_crear':
                    $res = $ok(['hoja_id' => vdhr_crear($conexion, $empresa_id, $_POST, $usuario_id)], 'Hoja creada');
                    break;
                case 'hoja_editar':
                    vdhr_editar($conexion, $empresa_id, $hoja, $_POST);
                    $res = $ok(null, 'Guardado');
                    break;
                case 'agregar_pedidos':
                    $ids = json_decode((string) ($_POST['pedidos'] ?? '[]'), true);
                    $r = vdhr_agregar_pedidos($conexion, $empresa_id, $hoja, is_array($ids) ? $ids : [], !empty($_POST['forzar']));
                    $res = $ok($r, !empty($r['requiere_confirmar']) ? '' : $r['agregados'] . ' pedido(s) en la hoja');
                    break;
                case 'quitar_pedido':
                    vdhr_quitar_pedido($conexion, $empresa_id, $hoja, intval($_POST['venta_pedido_id'] ?? 0));
                    $res = $ok(null, 'Pedido sacado de la hoja');
                    break;
                case 'ordenar':
                    $ids = json_decode((string) ($_POST['pedidos'] ?? '[]'), true);
                    vdhr_ordenar($conexion, $empresa_id, $hoja, is_array($ids) ? $ids : []);
                    $res = $ok(null, 'Orden guardado');
                    break;
                case 'cambiar_deposito':
                    vdhr_exigir($conexion, $empresa_id);
                    $boca = intval($_POST['boca_id'] ?? 0);
                    if (!vdis_es_deposito($conexion, $empresa_id, $boca)) throw new InvalidArgumentException('El depósito no es de esta empresa.');
                    $n = vcar_ejecutar($conexion, "UPDATE gestion__ventas_pedidos SET boca_despacho_id = ? WHERE venta_pedido_id = ? AND empresa_id = ? AND tabla_estado_registro_id = ?",
                        'iiii', [$boca, intval($_POST['venta_pedido_id'] ?? 0), $empresa_id, VDIS_ESTADO_PARA_DISTRIBUCION]);
                    $res = $n ? $ok(null, 'Depósito cambiado') : ['success' => false, 'data' => null, 'message' => 'El pedido no está para distribución.'];
                    break;
                case 'confirmar':
                    $res = $ok(vdhr_confirmar($conexion, $empresa_id, $hoja, $usuario_id), 'Hoja confirmada');
                    break;
                case 'anular':
                    vdhr_anular($conexion, $empresa_id, $hoja);
                    $res = $ok(null, 'Hoja anulada: sus pedidos vuelven al armado');
                    break;
                case 'rendir_pedido':
                    vdhr_rendir_pedido($conexion, $empresa_id, $hoja, intval($_POST['venta_pedido_id'] ?? 0), $_POST);
                    $res = $ok(null, 'Guardado');
                    break;
                case 'cerrar_rendicion':
                    $res = $ok(vdhr_cerrar_rendicion($conexion, $empresa_id, $hoja, $usuario_id), 'Hoja rendida');
                    break;
                default:
                    $res = ['success' => false, 'data' => null, 'message' => 'Acción desconocida.'];
            }
            if ($escritura) {
                mysqli_commit($conexion);
            }
            return $res;
        } catch (InvalidArgumentException $e) {
            if ($escritura) mysqli_rollback($conexion);
            return ['success' => false, 'data' => null, 'message' => $e->getMessage()];
        } catch (Throwable $e) {
            if ($escritura) mysqli_rollback($conexion);
            error_log('vdhr_despachar ' . $accion . ': ' . $e->getMessage());
            return ['success' => false, 'data' => null, 'message' => 'No se pudo completar la operación.'];
        }
    }
}
