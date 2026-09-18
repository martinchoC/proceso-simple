<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Repository;
use App\Http\Exceptions\ValidationException;

/**
 * Persistencia del pedido de venta sobre el ERP existente:
 *   gestion__comprobantes + gestion__ventas_pedidos + ..._detalles
 * más ecom__pedidos, tabla puente del storefront (quién y desde dónde se creó).
 *
 * Todas las escrituras se ejecutan dentro de la transacción abierta por
 * PedidoService: este repositorio no hace commit ni rollback.
 */
final class PedidoRepository extends Repository
{
    public function puntoVentaPorSucursal(int $empresaId, int $sucursalId, int $comprobanteTipoId = 1): int
    {
        $params = [
            'sucursal_id' => $sucursalId,
            'tipo_id'     => $comprobanteTipoId,
        ];
        $filtroEmpresaPvc = '';
        $filtroEmpresaPv = '';
        if ($empresaId > 0) {
            $filtroEmpresaPvc = ' AND pvc.empresa_id = :empresa_id_pvc';
            $filtroEmpresaPv = ' AND pv.empresa_id = :empresa_id_pv';
            $params['empresa_id_pvc'] = $empresaId;
            $params['empresa_id_pv'] = $empresaId;
        }

        // Busca el punto de venta de la sucursal que tenga habilitado el tipo de comprobante en gestion__puntos_venta_comprobantes
        $id = $this->scalar(
            "SELECT pv.punto_venta_id
               FROM gestion__puntos_venta pv
         INNER JOIN gestion__puntos_venta_comprobantes pvc
                 ON pvc.punto_venta_id = pv.punto_venta_id
                {$filtroEmpresaPvc}
                AND pvc.comprobante_tipo_id = :tipo_id
                AND pvc.tabla_estado_registro_id = 1
              WHERE pv.sucursal_id = :sucursal_id
                {$filtroEmpresaPv}
                AND pv.tabla_estado_registro_id = 1
           ORDER BY pv.es_web DESC, pv.punto_venta_id ASC
              LIMIT 1",
            $params
        );

        if ($id === null) {
            $fallbackParams = ['sucursal_id' => $sucursalId];
            $fallbackEmpresa = '';
            if ($empresaId > 0) {
                $fallbackEmpresa = ' AND empresa_id = :empresa_id';
                $fallbackParams['empresa_id'] = $empresaId;
            }

            $id = $this->scalar(
                "SELECT punto_venta_id
                   FROM gestion__puntos_venta
                  WHERE sucursal_id = :sucursal_id
                    {$fallbackEmpresa}
                    AND tabla_estado_registro_id = 1
               ORDER BY es_web DESC, punto_venta_id ASC
                  LIMIT 1",
                $fallbackParams
            );
        }

        if ($id === null) {
            throw new ValidationException('No hay punto de venta configurado para la sucursal asignada con tipo de comprobante ' . $comprobanteTipoId . '.');
        }

        return (int) $id;
    }

    /**
     * Numeración atómica: el UPSERT incrementa y bloquea la fila del numerador
     * dentro de la transacción, por lo que dos pedidos simultáneos nunca
     * obtienen el mismo número (la unique uk_numerador lo garantiza).
     */
    public function siguienteNumero(int $empresaId, int $puntoVentaId, int $comprobanteTipoId): int
    {
        $this->run(
            'INSERT INTO gestion__comprobantes_numeradores
                    (empresa_id, punto_venta_id, comprobante_tipo_id, ultimo_numero)
             VALUES (:empresa_id, :punto_venta_id, :tipo_id, 1)
             ON DUPLICATE KEY UPDATE ultimo_numero = ultimo_numero + 1',
            [
                'empresa_id'     => $empresaId,
                'punto_venta_id' => $puntoVentaId,
                'tipo_id'        => $comprobanteTipoId,
            ]
        );

        return (int) $this->scalar(
            'SELECT ultimo_numero
               FROM gestion__comprobantes_numeradores
              WHERE empresa_id = :empresa_id
                AND punto_venta_id = :punto_venta_id
                AND comprobante_tipo_id = :tipo_id
                FOR UPDATE',
            [
                'empresa_id'     => $empresaId,
                'punto_venta_id' => $puntoVentaId,
                'tipo_id'        => $comprobanteTipoId,
            ]
        );
    }

    /** @param array<string,mixed> $datos */
    public function crearComprobante(array $datos): int
    {
        $this->run(
            'INSERT INTO gestion__comprobantes
                    (tabla_origen_id, registro_origen_id, modulo, empresa_id, sucursal_id,
                     comprobante_tipo_id, comprobante_pv, comprobante_nro, entidad_id,
                     entidad_sucursal_id, f_emision, moneda_id, tipo_cambio,
                     importe_bruto, descuento_general, no_gravado, importe_neto,
                     importe_exento, importe_no_gravado, importe_iva, importe_otros_impuestos,
                     importe_total, importe_pendiente, signo, tabla_estado_registro_id, usuario_id)
             VALUES (:tabla_origen_id, 0, :modulo, :empresa_id, :sucursal_id,
                     :comprobante_tipo_id, :punto_venta_id, :comprobante_nro, :entidad_id,
                     :entidad_sucursal_id, :f_emision, :moneda_id, :tipo_cambio,
                     :importe_bruto, :descuento_general, 0, :importe_neto,
                     0, 0, :importe_iva, 0,
                     :importe_total, :importe_pendiente, 1, :estado_id, :usuario_id)',
            $datos
        );

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string,mixed> $datos */
    public function crearPedido(array $datos): int
    {
        $this->run(
            'INSERT INTO gestion__ventas_pedidos
                    (empresa_id, sucursal_id, comprobante_tipo_id, comprobante_id,
                     punto_venta_id, comprobante_nro, entidad_id, entidad_sucursal_id,
                     f_emision, condicion_pago_id, direccion_entrega, moneda_id, tipo_cambio,
                     subtotal, descuento_general_pct, descuentos, exento, no_gravado,
                     impuestos, otros_impuestos, total, observaciones, tabla_estado_registro_id)
             VALUES (:empresa_id, :sucursal_id, :comprobante_tipo_id, :comprobante_id,
                     :punto_venta_id, :comprobante_nro, :entidad_id, :entidad_sucursal_id,
                     :f_emision, :condicion_pago_id, :direccion_entrega, :moneda_id, :tipo_cambio,
                     :subtotal, :descuento_general_pct, :descuentos, 0, 0,
                     :impuestos, 0, :total, :observaciones, :estado_id)',
            $datos
        );

        return (int) $this->pdo->lastInsertId();
    }

    public function vincularComprobanteConPedido(int $comprobanteId, int $ventaPedidoId): void
    {
        $this->run(
            'UPDATE gestion__comprobantes
                SET registro_origen_id = :pedido_id
              WHERE comprobante_id = :comprobante_id',
            ['pedido_id' => $ventaPedidoId, 'comprobante_id' => $comprobanteId]
        );
    }

    /** @param array<string,mixed> $detalle */
    public function crearDetalle(array $detalle): void
    {
        $this->run(
            'INSERT INTO gestion__ventas_pedidos_detalles
                    (venta_pedido_id, producto_id, cantidad, cantidad_entregada,
                     precio_unitario, descuento_item_pct, descuento_general_pct,
                     descuento_general, descuento_item, precio_unitario_bruto,
                     precio_unitario_neto, neto_gravado, iva_alicuota_id, iva_porcentaje,
                     iva_importe, no_gravado, exento, total_linea, tabla_estado_registro_id)
             VALUES (:venta_pedido_id, :producto_id, :cantidad, 0,
                     :precio_unitario, 0, :descuento_general_pct,
                     :descuento_general, 0, :precio_unitario_bruto,
                     :precio_unitario_neto, :neto_gravado, :iva_alicuota_id, :iva_porcentaje,
                     :iva_importe, 0, 0, :total_linea, :estado_id)',
            $detalle
        );
    }

    public function registrarOrigenStorefront(int $ventaPedidoId, int $empresaId, int $usuarioId, int $entidadId): void
    {
        try {
            $this->run(
                'INSERT INTO ecom__pedidos (venta_pedido_id, empresa_id, usuario_id, entidad_id)
                 VALUES (:pedido_id, :empresa_id, :usuario_id, :entidad_id)',
                [
                    'pedido_id'  => $ventaPedidoId,
                    'empresa_id' => $empresaId,
                    'usuario_id' => $usuarioId,
                    'entidad_id' => $entidadId,
                ]
            );
        } catch (\Throwable) {
            // Tabla ecom__pedidos opcional / auditoría
        }
    }

    /** @return array<int,array<string,mixed>> */
    public function listarPorEntidad(int $entidadId, int $empresaId, int $limit, int $offset): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT vp.venta_pedido_id, vp.comprobante_nro, vp.punto_venta_id,
                    vp.punto_venta_id AS comprobante_pv,
                    vp.f_emision,
                    vp.total, vp.total AS importe_total,
                    vp.subtotal, vp.subtotal AS importe_neto,
                    vp.impuestos, vp.impuestos AS importe_iva,
                    vp.descuentos,
                    vp.tabla_estado_registro_id,
                    er.estado_registro
               FROM gestion__ventas_pedidos vp
          LEFT JOIN conf__estados_registros er
                 ON er.estado_registro_id = vp.tabla_estado_registro_id
              WHERE vp.entidad_id = :entidad_id
                AND vp.empresa_id = :empresa_id
           ORDER BY vp.venta_pedido_id DESC
              LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':entidad_id', $entidadId, \PDO::PARAM_INT);
        $stmt->bindValue(':empresa_id', $empresaId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Control de propiedad en la propia consulta: un pedido de otra entidad
     * simplemente no existe para este usuario (mitigación de IDOR).
     *
     * @return array<string,mixed>|null
     */
    public function buscarDeEntidad(int $ventaPedidoId, int $entidadId, int $empresaId): ?array
    {
        return $this->first(
            'SELECT vp.venta_pedido_id, vp.comprobante_nro, vp.punto_venta_id,
                    vp.punto_venta_id AS comprobante_pv,
                    vp.f_emision,
                    vp.subtotal, vp.subtotal AS importe_neto,
                    vp.descuentos,
                    vp.impuestos, vp.impuestos AS importe_iva,
                    vp.total, vp.total AS importe_total,
                    vp.observaciones,
                    vp.entidad_sucursal_id,
                    vp.direccion_entrega,
                    es.sucursal_nombre AS sucursal_entrega_nombre,
                    vp.sucursal_id,
                    sc.sucursal_nombre AS sucursal_compra_nombre,
                    vp.tabla_estado_registro_id,
                    er.estado_registro
               FROM gestion__ventas_pedidos vp
          LEFT JOIN conf__estados_registros er
                 ON er.estado_registro_id = vp.tabla_estado_registro_id
          LEFT JOIN gestion__entidades_sucursales es
                 ON es.sucursal_id = vp.entidad_sucursal_id
                AND es.entidad_id = vp.entidad_id
          LEFT JOIN gestion__sucursales sc
                 ON sc.sucursal_id = vp.sucursal_id
              WHERE vp.venta_pedido_id = :pedido_id
                AND vp.entidad_id = :entidad_id
                AND vp.empresa_id = :empresa_id
              LIMIT 1',
            ['pedido_id' => $ventaPedidoId, 'entidad_id' => $entidadId, 'empresa_id' => $empresaId]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function detalles(int $ventaPedidoId): array
    {
        return $this->all(
            'SELECT d.producto_id, p.producto_codigo, p.producto_nombre,
                    d.cantidad, d.precio_unitario_neto, d.neto_gravado,
                    d.iva_porcentaje, d.iva_importe, d.total_linea
               FROM gestion__ventas_pedidos_detalles d
         INNER JOIN gestion__productos p
                 ON p.producto_id = d.producto_id
              WHERE d.venta_pedido_id = :pedido_id
           ORDER BY d.venta_pedido_detalle_id ASC',
            ['pedido_id' => $ventaPedidoId]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function remitosPorPedido(int $ventaPedidoId): array
    {
        $filas = $this->all(
            'SELECT vr.venta_remito_id, vr.comprobante_tipo_id,
                    COALESCE(ct.comprobante_tipo, \'Remito de Salida\') AS comprobante_tipo,
                    COALESCE(ct.letra, \'A\') AS letra,
                    vr.comprobante_pv, vr.comprobante_nro, vr.f_emision, vr.observaciones,
                    COALESCE(er.estado_registro, \'Generado\') AS estado_registro,
                    vrd.venta_remito_detalle_id, vrd.producto_id,
                    p.producto_codigo, p.producto_nombre,
                    vrd.cantidad, vrd.importe_linea
               FROM gestion__ventas_remitos vr
         INNER JOIN gestion__ventas_remitos_detalles vrd
                 ON vrd.venta_remito_id = vr.venta_remito_id
         INNER JOIN gestion__ventas_pedidos_detalles vpd
                 ON vpd.venta_pedido_detalle_id = vrd.venta_pedido_detalle_id
          LEFT JOIN gestion__comprobantes_tipos ct
                 ON ct.comprobante_tipo_id = vr.comprobante_tipo_id
          LEFT JOIN gestion__productos p
                 ON p.producto_id = vrd.producto_id
          LEFT JOIN conf__estados_registros er
                 ON er.estado_registro_id = vr.tabla_estado_registro_id
              WHERE vpd.venta_pedido_id = :pedido_id
           ORDER BY vr.f_emision DESC, vr.venta_remito_id DESC, vrd.venta_remito_detalle_id ASC',
            ['pedido_id' => $ventaPedidoId]
        );

        $remitos = [];
        foreach ($filas as $fila) {
            $rid = (int) $fila['venta_remito_id'];
            if (!isset($remitos[$rid])) {
                $remitos[$rid] = [
                    'venta_remito_id'  => $rid,
                    'comprobante_tipo' => (string) $fila['comprobante_tipo'],
                    'letra'            => (string) $fila['letra'],
                    'comprobante_pv'   => (int) $fila['comprobante_pv'],
                    'comprobante_nro'  => (int) $fila['comprobante_nro'],
                    'f_emision'        => (string) $fila['f_emision'],
                    'observaciones'    => (string) ($fila['observaciones'] ?? ''),
                    'estado_registro'  => (string) $fila['estado_registro'],
                    'total_importe'    => 0.0,
                    'items'            => [],
                ];
            }
            $importe = (float) $fila['importe_linea'];
            $remitos[$rid]['total_importe'] += $importe;
            $remitos[$rid]['items'][] = [
                'producto_id'     => (int) $fila['producto_id'],
                'producto_codigo' => (string) ($fila['producto_codigo'] ?? ''),
                'producto_nombre' => (string) ($fila['producto_nombre'] ?? 'Producto'),
                'cantidad'        => (float) $fila['cantidad'],
                'importe_linea'   => $importe,
            ];
        }

        return array_values($remitos);
    }
}
