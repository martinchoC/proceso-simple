<?php
/**
 * cron/sindicatos_rendiciones.php
 * Proceso CLI: Procesamiento automático de archivos de rendición y conciliación.
 * Módulo 6 — Sindicatos (§2.4, §7.3.2, §10).
 * 
 * Uso CLI:
 *   php cron/sindicatos_rendiciones.php [--empresa=3]
 */

if (php_sapi_name() !== 'cli') {
    die("Este script solo puede ejecutarse desde la línea de comandos (CLI).\n");
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../modules/sindicatos/sindi_cobros_core_model.php';

$options = getopt('', ['empresa::']);
$filtroEmpresaId = isset($options['empresa']) ? (int)$options['empresa'] : null;

echo "[" . date('Y-m-d H:i:s') . "] Iniciando procesamiento de rendiciones pendientes...\n";

$lockRes = $conn->query("SELECT GET_LOCK('sindi_cron_rendiciones', 10) AS tiene_lock");
$lockRow = $lockRes->fetch_assoc();
if (!$lockRow || (int)$lockRow['tiene_lock'] !== 1) {
    die("[" . date('Y-m-d H:i:s') . "] ERROR: Proceso ya en ejecución.\n");
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

    foreach ($empresas as $e) {
        $empId = (int)$e['empresa_id'];
        echo "  -> Empresa ID $empId ({$e['nombre']}):\n";

        // Buscar rendiciones pendientes de conciliar o procesar (estados 8 Recibido o 5 Conciliado)
        $qRend = "SELECT rendicion_id, f_rendicion, total_importe, tabla_estado_registro_id 
                  FROM sindi__rendiciones 
                  WHERE empresa_id = ? AND tabla_estado_registro_id IN (8, 5)
                  ORDER BY f_rendicion ASC";
        $rStmt = $conn->prepare($qRend);
        $rStmt->bind_param("i", $empId);
        $rStmt->execute();
        $rendiciones = $rStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $rStmt->close();

        if (empty($rendiciones)) {
            echo "     Sin rendiciones pendientes.\n";
            continue;
        }

        foreach ($rendiciones as $rend) {
            $rendId = (int)$rend['rendicion_id'];
            echo "     Procesando cobranzas e imputaciones de rendicion ID $rendId...";
            $resProc = sindi_procesar_cobranza_rendicion($conn, $empId, 1, $rendId); // Usuario sistema = 1
            if ($resProc['success'] ?? false) {
                echo " OK (Pagos conciliados: {$resProc['procesadas_ok']}, Dif: {$resProc['diferencias']})\n";
            } else {
                echo " ERROR (" . ($resProc['message'] ?? 'Fallo') . ")\n";
            }
        }
    }

    echo "[" . date('Y-m-d H:i:s') . "] Rendiciones procesadas exitosamente.\n";
} finally {
    $conn->query("SELECT RELEASE_LOCK('sindi_cron_rendiciones')");
}
