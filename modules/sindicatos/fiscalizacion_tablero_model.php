<?php
/**
 * modules/sindicatos/fiscalizacion_tablero_model.php
 * Modelo para la pantalla de Tablero de Fiscalización (Página 1040).
 */

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/accesos_empleadores.php';
require_once __DIR__ . '/sindi_fiscalizacion_core_model.php';

class FiscalizacionTableroModel {
    private mysqli $c;

    public function __construct(mysqli $conn) {
        $this->c = $conn;
    }

    /**
     * Obtiene métricas y KPIs generales de fiscalización.
     */
    public function obtenerKpis(int $empresa_id): array {
        // Total empleadores activos
        $sqlTot = "SELECT COUNT(*) AS total FROM sindi__empleadores WHERE empresa_id = ? AND tabla_estado_registro_id = 1";
        $stmt = $this->c->prepare($sqlTot);
        $stmt->bind_param("i", $empresa_id);
        $stmt->execute();
        $totEmpleadores = (int)$stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();

        // Conteo por categoría de mora
        $sqlMora = "SELECT cm.codigo, COUNT(e.empleador_id) AS cant
                    FROM sindi__categorias_mora cm
                    LEFT JOIN sindi__empleadores e ON e.categoria_mora_id = cm.categoria_mora_id AND e.empresa_id = ? AND e.tabla_estado_registro_id = 1
                    GROUP BY cm.categoria_mora_id, cm.codigo";
        $mStmt = $this->c->prepare($sqlMora);
        $mStmt->bind_param("i", $empresa_id);
        $mStmt->execute();
        $moraRows = $mStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $mStmt->close();

        $kpiMora = [
            'PUNTUAL' => 0,
            'MORA_TEMPRANA' => 0,
            'MORA_MODERADA' => 0,
            'MORA_PROLONGADA' => 0,
            'MORA_CRONICA' => 0
        ];
        foreach ($moraRows as $mr) {
            $kpiMora[$mr['codigo']] = (int)$mr['cant'];
        }

        // Deuda total estimada o exigible en gestión comprobantes
        $sqlDeuda = "SELECT COALESCE(SUM(c.importe_pendiente), 0.00) AS deuda_total
                     FROM gestion__comprobantes c
                     JOIN sindi__empleadores e ON e.entidad_id = c.entidad_id
                     WHERE c.empresa_id = ? AND c.importe_pendiente > 0 AND c.tabla_estado_registro_id = 5";
        $dStmt = $this->c->prepare($sqlDeuda);
        $dStmt->bind_param("i", $empresa_id);
        $dStmt->execute();
        $deudaTotal = (float)$dStmt->get_result()->fetch_assoc()['deuda_total'];
        $dStmt->close();

        // Actas en curso
        $sqlActas = "SELECT COUNT(*) AS cant FROM sindi__actas WHERE empresa_id = ? AND tabla_estado_registro_id IN (3, 18)";
        $aStmt = $this->c->prepare($sqlActas);
        $aStmt->bind_param("i", $empresa_id);
        $aStmt->execute();
        $actasEnCurso = (int)$aStmt->get_result()->fetch_assoc()['cant'];
        $aStmt->close();

        return [
            'total_empleadores' => $totEmpleadores,
            'puntuales' => $kpiMora['PUNTUAL'],
            'mora_leve_media' => $kpiMora['MORA_TEMPRANA'] + $kpiMora['MORA_MODERADA'],
            'mora_grave_cronica' => $kpiMora['MORA_PROLONGADA'] + $kpiMora['MORA_CRONICA'],
            'deuda_total' => $deudaTotal,
            'actas_en_curso' => $actasEnCurso
        ];
    }

    /**
     * Obtiene la matriz de cumplimiento empleador x período.
     */
    public function obtenerMatriz(int $empresa_id, array $filtros): array {
        return sindiFiscalizacionMatrizCumplimiento($this->c, $empresa_id, $filtros);
    }

    /**
     * Ejecuta el recálculo masivo de categorías de mora.
     */
    public function recalcularMora(int $empresa_id): array {
        return sindiFiscalizacionRecalcularMora($this->c, $empresa_id);
    }

    /**
     * Detecta trabajadores omitidos para un empleador y período.
     */
    public function detectarOmitidos(int $empresa_id, int $empleador_id, int $periodo): array {
        return sindiFiscalizacionDetectarEmpleadosOmitidos($this->c, $empresa_id, $empleador_id, $periodo);
    }

    /**
     * Obtiene el listado de categorías de mora para filtros.
     */
    public function listarCategoriasMora(): array {
        $res = $this->c->query("SELECT categoria_mora_id, codigo, nombre, color FROM sindi__categorias_mora ORDER BY dias_desde ASC");
        return $res->fetch_all(MYSQLI_ASSOC);
    }
}
