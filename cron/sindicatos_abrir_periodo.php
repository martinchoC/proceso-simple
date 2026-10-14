<?php
/**
 * cron/sindicatos_abrir_periodo.php
 * Proceso CLI automático: Apertura mensual de períodos para empleadores.
 * Módulo 6 — Sindicatos (§5.2, §10).
 * 
 * Uso CLI:
 *   php cron/sindicatos_abrir_periodo.php [--empresa=3] [--periodo=202610]
 */

if (php_sapi_name() !== 'cli') {
    die("Este script solo puede ejecutarse desde la línea de comandos (CLI).\n");
}

require_once __DIR__ . '/../db.php';

// Parsear argumentos CLI
$options = getopt('', ['empresa::', 'periodo::']);
$filtroEmpresaId = isset($options['empresa']) ? (int)$options['empresa'] : null;
$periodoTarget = isset($options['periodo']) ? (int)$options['periodo'] : (int)date('Ym');

echo "[" . date('Y-m-d H:i:s') . "] Iniciando apertura de período $periodoTarget...\n";

// Concurrencia: Adquirir LOCK en MySQL
$lockRes = $conn->query("SELECT GET_LOCK('sindi_cron_abrir_periodo', 10) AS tiene_lock");
$lockRow = $lockRes->fetch_assoc();
if (!$lockRow || (int)$lockRow['tiene_lock'] !== 1) {
    die("[" . date('Y-m-d H:i:s') . "] ERROR: Proceso ya en ejecución (no se pudo obtener GET_LOCK).\n");
}

try {
    // 1. Obtener empresas que tienen habilitado el Módulo 6 (Sindicatos)
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

    foreach ($empresas as $e) {
        $empId = (int)$e['empresa_id'];
        echo "  -> Procesando Empresa ID $empId ({$e['nombre']})...\n";

        // Obtener empleadores activos
        $empStmt = $conn->prepare("SELECT e.empleador_id, ent.cuit 
                                  FROM sindi__empleadores e
                                  JOIN gestion__entidades ent ON ent.entidad_id = e.entidad_id
                                  WHERE e.empresa_id = ? AND e.tabla_estado_registro_id = 1");
        $empStmt->bind_param("i", $empId);
        $empStmt->execute();
        $empleadores = $empStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $empStmt->close();

        $creados = 0;
        $existentes = 0;

        $insStmt = $conn->prepare("INSERT INTO sindi__empleadores_periodos (
            empresa_id, empleador_id, periodo, f_vto, situacion
        ) VALUES (?, ?, ?, ?, 'PENDIENTE')
        ON DUPLICATE KEY UPDATE f_vto = IF(situacion='PENDIENTE', VALUES(f_vto), f_vto)");

        foreach ($empleadores as $emp) {
            $empleadorId = (int)$emp['empleador_id'];
            $cuit = preg_replace('/[^0-9]/', '', (string)$emp['cuit']);
            $digito = (int)substr($cuit, -1);

            // Obtener fecha de vencimiento según dígito y período de sindi__vencimientos
            $vStmt = $conn->prepare("SELECT f_vto FROM sindi__vencimientos 
                                    WHERE empresa_id = ? AND periodo = ? AND terminacion_cuit = ? 
                                    LIMIT 1");
            $vStmt->bind_param("iii", $empId, $periodoTarget, $digito);
            $vStmt->execute();
            $vRow = $vStmt->get_result()->fetch_assoc();
            $vStmt->close();

            if ($vRow && !empty($vRow['f_vto'])) {
                $fVto = $vRow['f_vto'];
            } else {
                // Estimación por defecto: día 15 del mes siguiente
                $y = (int)substr((string)$periodoTarget, 0, 4);
                $m = (int)substr((string)$periodoTarget, 4, 2);
                $m++;
                if ($m > 12) { $m = 1; $y++; }
                $fVto = sprintf('%04d-%02d-15', $y, $m);
            }

            $insStmt->bind_param("iiis", $empId, $empleadorId, $periodoTarget, $fVto);
            $insStmt->execute();
            if ($conn->affected_rows === 1) {
                $creados++;
            } else {
                $existentes++;
            }
        }
        $insStmt->close();

        echo "     [OK] Empleadores procesados: " . count($empleadores) . " (Nuevos abiertos: $creados, Ya existentes: $existentes)\n";
    }

    echo "[" . date('Y-m-d H:i:s') . "] Apertura de período finalizada exitosamente.\n";
} finally {
    $conn->query("SELECT RELEASE_LOCK('sindi_cron_abrir_periodo')");
}
