<?php
class ComprasPedidosModel {
    private $db;
    
    public function __construct() {
        $this->conectarDB();
    }
    
    private function conectarDB() {
        try {
            // Ajustar según tu configuración de base de datos
            $host = 'localhost';
            $dbname = 'tu_base_de_datos';
            $username = 'usuario';
            $password = 'contraseña';
            
            $this->db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            throw new Exception("Error de conexión: " . $e->getMessage());
        }
    }
    
    public function obtenerPedidos() {
        $sql = "SELECT 
                    c.comprobante_id,
                    c.numero_comprobante,
                    c.f_emision,
                    c.f_vto,
                    c.total,
                    c.estado_registro_id,
                    s.sucursal_nombre,
                    ct.comprobante_tipo,
                    e.entidad_nombre as proveedor,
                    er.estado_nombre,
                    er.estado_display
                FROM comprobantes c
                LEFT JOIN sucursales s ON c.sucursal_id = s.sucursal_id
                LEFT JOIN comprobante_tipos ct ON c.comprobante_tipo_id = ct.comprobante_tipo_id
                LEFT JOIN entidades e ON c.entidad_id = e.entidad_id
                LEFT JOIN estado_registros er ON c.estado_registro_id = er.estado_registro_id
                WHERE c.estado_registro_id != 6
                ORDER BY c.f_emision DESC, c.comprobante_id DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function obtenerSucursales() {
        $sql = "SELECT sucursal_id, sucursal_nombre FROM sucursales WHERE estado_registro_id = 1 ORDER BY sucursal_nombre";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function obtenerTiposComprobante() {
        $sql = "SELECT comprobante_tipo_id, comprobante_tipo FROM comprobante_tipos WHERE estado_registro_id = 1 ORDER BY comprobante_tipo";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function obtenerProveedores() {
        $sql = "SELECT entidad_id, entidad_nombre FROM entidades WHERE tipo_entidad_id = 2 AND estado_registro_id = 1 ORDER BY entidad_nombre";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function obtenerProductos() {
        $sql = "SELECT producto_id, producto_nombre, codigo_barras FROM productos WHERE estado_registro_id = 1 ORDER BY producto_nombre";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function obtenerSiguienteNumero($sucursal_id, $comprobante_tipo_id) {
        $sql = "SELECT COALESCE(MAX(numero_comprobante), 0) + 1 as siguiente_numero 
                FROM comprobantes 
                WHERE sucursal_id = ? AND comprobante_tipo_id = ? AND estado_registro_id != 6";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$sucursal_id, $comprobante_tipo_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['siguiente_numero'] ?? 1;
    }
    
    public function crearPedido($comprobanteData, $detalles) {
        $this->db->beginTransaction();
        
        try {
            // Insertar comprobante
            $sqlComprobante = "INSERT INTO comprobantes (
                sucursal_id, comprobante_tipo_id, numero_comprobante, entidad_id,
                f_emision, f_vto, f_contabilizacion, observaciones,
                importe_neto, importe_no_gravado, total, punto_venta_id, estado_registro_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmtComprobante = $this->db->prepare($sqlComprobante);
            $stmtComprobante->execute([
                $comprobanteData['sucursal_id'],
                $comprobanteData['comprobante_tipo_id'],
                $comprobanteData['numero_comprobante'],
                $comprobanteData['entidad_id'],
                $comprobanteData['f_emision'],
                $comprobanteData['f_vto'],
                $comprobanteData['f_contabilizacion'],
                $comprobanteData['observaciones'],
                $comprobanteData['importe_neto'],
                $comprobanteData['importe_no_gravado'],
                $comprobanteData['total'],
                $comprobanteData['punto_venta_id'],
                $comprobanteData['estado_registro_id']
            ]);
            
            $comprobante_id = $this->db->lastInsertId();
            
            // Insertar detalles
            $sqlDetalle = "INSERT INTO comprobante_detalles (
                comprobante_id, producto_id, cantidad, precio_unitario, descuento
            ) VALUES (?, ?, ?, ?, ?)";
            
            $stmtDetalle = $this->db->prepare($sqlDetalle);
            
            foreach ($detalles as $detalle) {
                $stmtDetalle->execute([
                    $comprobante_id,
                    $detalle['producto_id'],
                    $detalle['cantidad'],
                    $detalle['precio_unitario'],
                    $detalle['descuento']
                ]);
            }
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error al crear pedido: " . $e->getMessage());
            return false;
        }
    }
    
    public function obtenerPedido($comprobante_id) {
        $sql = "SELECT 
                    c.*,
                    s.sucursal_nombre,
                    ct.comprobante_tipo,
                    e.entidad_nombre as proveedor,
                    er.estado_nombre,
                    er.estado_display
                FROM comprobantes c
                LEFT JOIN sucursales s ON c.sucursal_id = s.sucursal_id
                LEFT JOIN comprobante_tipos ct ON c.comprobante_tipo_id = ct.comprobante_tipo_id
                LEFT JOIN entidades e ON c.entidad_id = e.entidad_id
                LEFT JOIN estado_registros er ON c.estado_registro_id = er.estado_registro_id
                WHERE c.comprobante_id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$comprobante_id]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function obtenerDetallesPedido($comprobante_id) {
        $sql = "SELECT 
                    cd.*,
                    p.producto_nombre,
                    p.codigo_barras
                FROM comprobante_detalles cd
                LEFT JOIN productos p ON cd.producto_id = p.producto_id
                WHERE cd.comprobante_id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$comprobante_id]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function actualizarPedido($comprobante_id, $comprobanteData, $detalles) {
        $this->db->beginTransaction();
        
        try {
            // Actualizar comprobante
            $sqlComprobante = "UPDATE comprobantes SET
                sucursal_id = ?, comprobante_tipo_id = ?, numero_comprobante = ?, entidad_id = ?,
                f_emision = ?, f_vto = ?, f_contabilizacion = ?, observaciones = ?,
                importe_neto = ?, importe_no_gravado = ?, total = ?
                WHERE comprobante_id = ? AND estado_registro_id = 3"; // Solo actualizar si está en borrador
            
            $stmtComprobante = $this->db->prepare($sqlComprobante);
            $stmtComprobante->execute([
                $comprobanteData['sucursal_id'],
                $comprobanteData['comprobante_tipo_id'],
                $comprobanteData['numero_comprobante'],
                $comprobanteData['entidad_id'],
                $comprobanteData['f_emision'],
                $comprobanteData['f_vto'],
                $comprobanteData['f_contabilizacion'],
                $comprobanteData['observaciones'],
                $comprobanteData['importe_neto'],
                $comprobanteData['importe_no_gravado'],
                $comprobanteData['total'],
                $comprobante_id
            ]);
            
            // Eliminar detalles existentes
            $sqlEliminarDetalles = "DELETE FROM comprobante_detalles WHERE comprobante_id = ?";
            $stmtEliminar = $this->db->prepare($sqlEliminarDetalles);
            $stmtEliminar->execute([$comprobante_id]);
            
            // Insertar nuevos detalles
            $sqlDetalle = "INSERT INTO comprobante_detalles (
                comprobante_id, producto_id, cantidad, precio_unitario, descuento
            ) VALUES (?, ?, ?, ?, ?)";
            
            $stmtDetalle = $this->db->prepare($sqlDetalle);
            
            foreach ($detalles as $detalle) {
                $stmtDetalle->execute([
                    $comprobante_id,
                    $detalle['producto_id'],
                    $detalle['cantidad'],
                    $detalle['precio_unitario'],
                    $detalle['descuento']
                ]);
            }
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error al actualizar pedido: " . $e->getMessage());
            return false;
        }
    }
    
    public function cambiarEstadoPedido($comprobante_id, $nuevo_estado) {
        $sql = "UPDATE comprobantes SET estado_registro_id = ? WHERE comprobante_id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$nuevo_estado, $comprobante_id]);
    }
}
?>