<?php
/**
 * Sistema Declarativo Basado en Estados
 * Gestiona estados y funciones de forma dinámica desde BD
 */

class SistemaDeclarativo {
    private $conexion;
    private $config;
    
    public function __construct($conexion, $config) {
        $this->conexion = $conexion;
        $this->config = $config;
    }
    
    /**
     * Obtener información de un estado específico
     */
    public function obtenerInfoEstado($estado_id) {
        $sql = "SELECT * FROM conf__estados_registros 
                WHERE tabla_estado_registro_id = ?";
        $stmt = mysqli_prepare($this->conexion, $sql);
        mysqli_stmt_bind_param($stmt, "i", $estado_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        return mysqli_fetch_assoc($result);
    }
    
    /**
     * Obtener estado inicial para este tipo de tabla
     */
    public function obtenerEstadoInicial() {
        $tabla_tipo = $this->config['tabla']['tipo'];
        
        // Primero obtener el ID del tipo de tabla
        $sql = "SELECT tt.tabla_tipo_id 
                FROM conf__tipos_tablas tt
                WHERE tt.identificador = ? 
                LIMIT 1";
        $stmt = mysqli_prepare($this->conexion, $sql);
        mysqli_stmt_bind_param($stmt, "s", $tabla_tipo);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $tipo = mysqli_fetch_assoc($result);
        
        if (!$tipo) {
            return 1; // Estado por defecto
        }
        
        // Buscar estado inicial para este tipo de tabla
        $sql = "SELECT er.tabla_estado_registro_id 
                FROM conf__tablas_tipos_estados tte
                JOIN conf__estados_registros er ON er.tabla_estado_registro_id = tte.tabla_estado_registro_id
                WHERE tte.tabla_tipo_id = ? 
                AND er.es_inicial = 1
                LIMIT 1";
        $stmt = mysqli_prepare($this->conexion, $sql);
        mysqli_stmt_bind_param($stmt, "i", $tipo['tabla_tipo_id']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $estado = mysqli_fetch_assoc($result);
        
        return $estado ? $estado['tabla_estado_registro_id'] : 1;
    }
    
    /**
     * Obtener funciones disponibles para un estado específico
     */
    public function obtenerFuncionesPorEstado($estado_id, $perfil_id = null) {
        $tabla_tipo = $this->config['tabla']['tipo'];
        $pagina_id = $this->config['pagina']['id'];
        
        // Obtener funciones estándar para este tipo de tabla y estado
        $sql = "SELECT 
                    fe.funcion_estandar_id,
                    fe.nombre_funcion,
                    fe.nombre_icono,
                    fe.color_nombre,
                    ttf.tabla_estado_registro_destino_id as estado_destino
                FROM conf__tipos_tablas tt
                JOIN conf__tablas_tipos_funciones ttf ON ttf.tabla_tipo_id = tt.tabla_tipo_id
                JOIN conf__funciones_estandar fe ON fe.funcion_estandar_id = ttf.funcion_estandar_id
                WHERE tt.identificador = ?
                AND ttf.tabla_estado_registro_origen_id = ?
                ORDER BY ttf.orden";
        
        $stmt = mysqli_prepare($this->conexion, $sql);
        mysqli_stmt_bind_param($stmt, "si", $tabla_tipo, $estado_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $funciones = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $funciones[] = $row;
        }
        
        // Filtrar por permisos de página si es necesario
        if ($perfil_id) {
            $funciones = $this->filtrarFuncionesPorPermisos($funciones, $pagina_id, $perfil_id);
        }
        
        return $funciones;
    }
    
    /**
     * Filtrar funciones por permisos de perfil
     */
    private function filtrarFuncionesPorPermisos($funciones, $pagina_id, $perfil_id) {
        if (empty($funciones)) return [];
        
        $funciones_ids = array_column($funciones, 'funcion_estandar_id');
        $ids_str = implode(',', $funciones_ids);
        
        $sql = "SELECT pf.funcion_estandar_id 
                FROM conf__paginas_funciones pf
                WHERE pf.pagina_id = ?
                AND pf.funcion_estandar_id IN ($ids_str)
                AND pf.perfil_id = ?
                AND pf.habilitado = 1";
        
        $stmt = mysqli_prepare($this->conexion, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $pagina_id, $perfil_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $permitidos = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $permitidos[] = $row['funcion_estandar_id'];
        }
        
        // Filtrar funciones permitidas
        return array_filter($funciones, function($func) use ($permitidos) {
            return in_array($func['funcion_estandar_id'], $permitidos);
        });
    }
    
    /**
     * Ejecutar una función declarativa
     */
    public function ejecutarFuncion($registro_id, $funcion_estandar_id, $estado_destino = null) {
        $tabla = $this->config['tabla']['nombre'];
        $campo_id = $this->config['tabla']['campo_id'];
        $campo_estado = $this->config['tabla']['campo_estado'];
        
        // 1. Obtener estado actual del registro
        $sql = "SELECT $campo_estado FROM $tabla WHERE $campo_id = ?";
        $stmt = mysqli_prepare($this->conexion, $sql);
        mysqli_stmt_bind_param($stmt, "i", $registro_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $registro = mysqli_fetch_assoc($result);
        
        if (!$registro) {
            return ['success' => false, 'error' => 'Registro no encontrado'];
        }
        
        $estado_actual = $registro[$campo_estado];
        
        // 2. Validar que la función sea válida para el estado actual
        $funciones_disponibles = $this->obtenerFuncionesPorEstado($estado_actual);
        $funcion_valida = false;
        $nuevo_estado = $estado_destino;
        
        foreach ($funciones_disponibles as $func) {
            if ($func['funcion_estandar_id'] == $funcion_estandar_id) {
                $funcion_valida = true;
                if (!$nuevo_estado && $func['estado_destino']) {
                    $nuevo_estado = $func['estado_destino'];
                }
                break;
            }
        }
        
        if (!$funcion_valida) {
            return ['success' => false, 'error' => 'Función no disponible para este estado'];
        }
        
        // 3. Si es función de edición, no cambia estado
        if ($this->esFuncionEdicion($funcion_estandar_id)) {
            return ['success' => true, 'message' => 'Permitido editar', 'nuevo_estado' => $estado_actual];
        }
        
        // 4. Si hay cambio de estado, ejecutarlo
        if ($nuevo_estado && $nuevo_estado != $estado_actual) {
            $sql = "UPDATE $tabla SET $campo_estado = ? WHERE $campo_id = ?";
            $stmt = mysqli_prepare($this->conexion, $sql);
            mysqli_stmt_bind_param($stmt, "ii", $nuevo_estado, $registro_id);
            
            if (mysqli_stmt_execute($stmt)) {
                return ['success' => true, 'message' => 'Estado actualizado correctamente', 'nuevo_estado' => $nuevo_estado];
            } else {
                return ['success' => false, 'error' => 'Error al actualizar estado'];
            }
        }
        
        return ['success' => true, 'message' => 'Acción ejecutada'];
    }
    
    /**
     * Verificar si una función es de edición
     */
    private function esFuncionEdicion($funcion_estandar_id) {
        $sql = "SELECT tipo_accion FROM conf__funciones_estandar 
                WHERE funcion_estandar_id = ?";
        $stmt = mysqli_prepare($this->conexion, $sql);
        mysqli_stmt_bind_param($stmt, "i", $funcion_estandar_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $funcion = mysqli_fetch_assoc($result);
        
        return $funcion && $funcion['tipo_accion'] == 'editar';
    }
}
?>