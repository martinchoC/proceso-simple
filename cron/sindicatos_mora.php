<?php
/**
 * cron/sindicatos_mora.php
 * Proceso CLI diario: Recálculo de categoría de mora, marcado de volantes vencidos
 * y vigilancia de plazos de actas de inspección (72 hs).
 * Módulo 6 — Sindicatos (§2.5, §5.5, §10).
 * 
 * Uso CLI:
 *   php cron/sindicatos_mora.php [--empresa=3]
 */

if (php_sapi_name() !== 'cli') {
    die("Este script solo puede ejecutarse desde la línea de comandos (CLI).\n");
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../modules/sindicatos/sindi_fiscalizacion_core_model.php';

// Argumentos
$options = getopt('', ['empresa::']);
$filtroEmpresaId = isset($options['empresa']) ? (int)$options['empresa'] : null;

echo "[" . date('Y-m-d H:i:s') . "] Iniciando proceso de mora, volantes y actas...\n";

// Concurrencia LOCK
$lockRes = $conn->query("SELECT GET_LOCK('sindi_cron_mora', 10) AS tiene_lock");
$lockRow = $lockRes->fetch_assoc();
if (!$lockRow || (int)$lockRow['tiene_lock'] !== 1) {
    die("[" . date('Y-m-d H:i:s') . "] ERROR: Proceso ya en ejecución (no se pudo obtener GET_LOCK).\n");
}

try {
    $whereEmp = "WHERE em.modulo_id = 6 AND em.tabla_estado_registro_id = 1";
    $params = [];
    $types = "";
    if ($filtroEmpresaId !== null && $filtroEmpresaId > 0) {
        $whereEmp .= " AND em.empresa_id = ?";
        $params[] = $filtroEmpresaId;
        $types .= "i";
    }

    $sqlEmp = "SELECT DISTINCT em.empresa_id, emp.empresa AS nombre 
               FROM conf__empresas_modulos em
               JOIN conf__empresas emp ON emp.empresa_id = em.empresa_id
               $whereEmp";
    $stmt = $conn->prepare($sqlEmp);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $empresas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $hoy = date('Y-m-d');
    $ahora = date('Y-m-d H:i:s');

    foreach ($empresas as $e) {
        $empId = (int)$e['empresa_id'];
        echo "  -> Empresa ID $empId ({$e['nombre']}):\n";

        // 1. Recalcular categoría de mora
        echo "     [1/3] Recalculando morosidad de empleadores...";
        $moraStats = sindiFiscalizacionRecalcularMora($conn, $empId);
        echo " OK (Procesados: {$moraStats['procesados']}, Actualizados: {$moraStats['actualizados']})\n";

        // 2. Marcar volantes vencidos (estado 31 Emitido -> 20 Vencido si f_pago_2 < hoy)
        echo "     [2/3] Verificando vencimiento de volantes de pago...";
        $vtoSql = "UPDATE sindi__volantes 
                   SET tabla_estado_registro_id = 20 
                   WHERE empresa_id = ? 
                     AND tabla_estado_registro_id = 31 
                     AND f_pago_2 < ?";
        $vStmt = $conn->prepare($vtoSql);
        $vStmt->bind_param("is", $empId, $hoy);
        $vStmt->execute();
        $volantesVencidos = $vStmt->affected_rows;
        $vStmt->close();
        echo " OK ($volantesVencidos volantes pasados a Vencido)\n";

        // 3. Vigilancia de plazos de actas (vencimiento de 72 hs)
        echo "     [3/3] Vigilando plazos de 72 hs en actas de inspección...";
        $actasSql = "SELECT acta_id, numero_acta, f_vencimiento_72h 
                     FROM sindi__actas 
                     WHERE empresa_id = ? 
                       AND tabla_estado_registro_id IN (3, 18) 
                       AND f_vencimiento_72h IS NOT NULL 
                       AND f_vencimiento_72h < ?";
        $aStmt = $conn->prepare($actasSql);
        $aStmt->bind_param("is", $empId, $ahora);
        $aStmt->execute();
        $actasVencidas = $aStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $aStmt->close();

        if (!empty($actasVencidas)) {
            echo " ALERTA: " . count($actasVencidas) . " actas con plazo de 72 hs expirado:\n";
            foreach ($actasVencidas as $av) {
                echo "        - Acta {$av['numero_acta']} (Venció: {$av['f_vencimiento_72h']})\n";
            }
        } else {
            echo " OK (Sin actas vencidas)\n";
        }
    }

    echo "[" . date('Y-m-d H:i:s') . "] Proceso finalizado exitosamente.\n";
} finally {
    $conn->query("SELECT RELEASE_LOCK('sindi_cron_mora')");
}
