<?php
require_once "conexion.php";

class PaginasFuncionesTiposModel {
    private $conexion;
    
    public function __construct($conexion) {
        $this->conexion = $conexion;
    }
    
    public function obtenerFunciones($filtros = []) {
        $sql = "SELECT 
                    cpf.*,
                    ctt.tabla_tipo,
                    ci.icono_nombre,
                    ci.icono_clase,
                    cc.nombre_color,
                    cc.color_clase,
                    cc.bg_clase,
                    cc.text_clase,
                    cer_origen.estado_registro as estado_origen_nombre,
                    cer_origen.codigo_estandar as codigo_origen,
                    cer_origen.valor_estandar as valor_origen,
                    cer_destino.estado_registro as estado_destino_nombre,
                    cer_destino.codigo_estandar as codigo_destino,
                    cer_destino.valor_estandar as valor_destino,
                    cfe.nombre as funcion_estandar_nombre
                FROM conf__paginas_funciones_tipos cpf
                LEFT JOIN conf__tablas_tipos ctt ON cpf.tabla_id = ctt.tabla_tipo_id
                LEFT JOIN conf__iconos ci ON cpf.icono_id = ci.icono_id
                LEFT JOIN conf__colores cc ON cpf.color_id = cc.color_id
                LEFT JOIN conf__estados_registros cer_origen ON cpf.tabla_estado_registro_origen_id = cer_origen.estado_registro_id
                LEFT JOIN conf__estados_registros cer_destino ON cpf.tabla_estado_registro_destino_id = cer_destino.estado_registro_id
                LEFT JOIN conf__funciones_estandar cfe ON cpf.funcion_estandar_id = cfe.funcion_estandar_id
                WHERE 1=1";
        
        $params = [];
        
        // Aplicar filtros
        if (!empty($filtros['tabla_tipo_id'])) {
            $sql .= " AND cpf.tabla_id = ?";
            $params[] = intval($filtros['tabla_tipo_id']);
        }
        
        if (isset($filtros['estado_registro'])) {
            $sql .= " AND cpf.tabla_estado_registro_id = ?";
            $params[] = intval($filtros['estado_registro']);
        }
        
        $sql .= " ORDER BY cpf.orden ASC, cpf.pagina_funcion_id DESC";
        
        $stmt = $this->conexion->prepare($sql);
        
        if (!empty($params)) {
            $types = str_repeat('i', count($params));
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $data = [];
        
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        
        return $data;
    }
    
    public function obtenerFuncionPorId($id) {
        $sql = "SELECT * FROM conf__paginas_funciones_tipos WHERE pagina_funcion_id = ?";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }
    
    public function agregarFuncion($data) {
        $sql = "INSERT INTO conf__paginas_funciones_tipos (
                    tabla_id, icono_id, color_id, funcion_estandar_id,
                    nombre_funcion, accion_js, descripcion,
                    tabla_estado_registro_origen_id, tabla_estado_registro_destino_id,
                    orden, tabla_estado_registro_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->conexion->prepare($sql);
        
        // Preparar valores NULL para campos opcionales
        $icono_id = !empty($data['icono_id']) ? $data['icono_id'] : null;
        $color_id = !empty($data['color_id']) ? $data['color_id'] : null;
        $funcion_estandar_id = !empty($data['funcion_estandar_id']) ? $data['funcion_estandar_id'] : null;
        
        $stmt->bind_param(
            'iiissssiiii',
            $data['tabla_id'],
            $icono_id,
            $color_id,
            $funcion_estandar_id,
            $data['nombre_funcion'],
            $data['accion_js'],
            $data['descripcion'],
            $data['tabla_estado_registro_origen_id'],
            $data['tabla_estado_registro_destino_id'],
            $data['orden'],
            $data['tabla_estado_registro_id']
        );
        
        return $stmt->execute();
    }
    
    public function editarFuncion($id, $data) {
        $sql = "UPDATE conf__paginas_funciones_tipos SET
                    tabla_id = ?,
                    icono_id = ?,
                    color_id = ?,
                    funcion_estandar_id = ?,
                    nombre_funcion = ?,
                    accion_js = ?,
                    descripcion = ?,
                    tabla_estado_registro_origen_id = ?,
                    tabla_estado_registro_destino_id = ?,
                    orden = ?,
                    tabla_estado_registro_id = ?
                WHERE pagina_funcion_id = ?";
        
        $stmt = $this->conexion->prepare($sql);
        
        // Preparar valores NULL para campos opcionales
        $icono_id = !empty($data['icono_id']) ? $data['icono_id'] : null;
        $color_id = !empty($data['color_id']) ? $data['color_id'] : null;
        $funcion_estandar_id = !empty($data['funcion_estandar_id']) ? $data['funcion_estandar_id'] : null;
        
        $stmt->bind_param(
            'iiissssiiiii',
            $data['tabla_id'],
            $icono_id,
            $color_id,
            $funcion_estandar_id,
            $data['nombre_funcion'],
            $data['accion_js'],
            $data['descripcion'],
            $data['tabla_estado_registro_origen_id'],
            $data['tabla_estado_registro_destino_id'],
            $data['orden'],
            $data['tabla_estado_registro_id'],
            $id
        );
        
        return $stmt->execute();
    }
    
    public function cambiarEstadoFuncion($id, $nuevo_estado) {
        $sql = "UPDATE conf__paginas_funciones_tipos SET 
                tabla_estado_registro_id = ? 
                WHERE pagina_funcion_id = ?";
        
        $stmt = $this->conexion->prepare($sql);
        $stmt->bind_param('ii', $nuevo_estado, $id);
        
        return $stmt->execute();
    }
    
    public function eliminarFuncion($id) {
        $sql = "DELETE FROM conf__paginas_funciones_tipos WHERE pagina_funcion_id = ?";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bind_param('i', $id);
        
        return $stmt->execute();
    }
    
    public function obtenerTiposTabla() {
        $sql = "SELECT * FROM conf__tablas_tipos WHERE tabla_estado_registro_id = 1 ORDER BY tabla_tipo";
        $result = $this->conexion->query($sql);
        $data = [];
        
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        
        return $data;
    }
    
    public function obtenerEstadosRegistro() {
        $sql = "SELECT * FROM conf__estados_registros ORDER BY orden_estandar ASC, estado_registro ASC";
        $result = $this->conexion->query($sql);
        $data = [];
        
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        
        return $data;
    }
    
    public function obtenerIconos() {
        $sql = "SELECT * FROM conf__iconos WHERE tabla_estado_registro_id = 1 ORDER BY icono_nombre";
        $result = $this->conexion->query($sql);
        $data = [];
        
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        
        return $data;
    }
    
    public function obtenerColores() {
        $sql = "SELECT * FROM conf__colores WHERE tabla_estado_registro_id = 1 ORDER BY nombre_color";
        $result = $this->conexion->query($sql);
        $data = [];
        
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        
        return $data;
    }
    
    public function obtenerFuncionesEstandar() {
        $sql = "SELECT * FROM conf__funciones_estandar WHERE estado_registro_id = 1 ORDER BY nombre";
        $result = $this->conexion->query($sql);
        $data = [];
        
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        
        return $data;
    }
    
    public function obtenerFuncionesEstandarPorTipo($tipo_id) {
        $sql = "SELECT * FROM conf__funciones_estandar 
                WHERE (tabla_tipo_id = ? OR tabla_tipo_id IS NULL) 
                AND estado_registro_id = 1 
                ORDER BY nombre";
        
        $stmt = $this->conexion->prepare($sql);
        $stmt->bind_param('i', $tipo_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = [];
        
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        
        return $data;
    }
}
?>