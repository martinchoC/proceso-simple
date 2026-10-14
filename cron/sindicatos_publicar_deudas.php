<?php
/**
 * cron/sindicatos_publicar_deudas.php
 * Proceso CLI: Reintento de publicaciones y despublicación de deudas en canales.
 * Módulo 6 — Sindicatos (§7.3, §10).
 * 
 * Uso CLI:
 *   php cron/sindicatos_publicar_deudas.php [--empresa=3]
 */

if (php_sapi_name() !== 'cli') {
    die("Este script solo puede ejecutarse desde la línea de comandos (CLI).\n");
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../modules/sindicatos/sindi_pagos_drivers.php';

$options = getopt('', ['empresa::']);
$filtroEmpresaId = isset($options['empresa']) ? (int)$options['empresa'] : null;

echo "[" . date('Y-m-d H:i:s') . "] Iniciando sincronización de deudas en canales...\n";

$lockRes = $conn->query("SELECT GET_LOCK('sindi_cron_publicar_deudas', 10) AS tiene_lock");
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

        // 1. Reintentar publicaciones pendientes
        $qPend = "SELECT vc.volante_canal_id, vc.volante_id, vc.medio_pago_id, v.numero, v.importe_1, v.f_pago_1
                  FROM sindi__volantes_canales vc
                  JOIN sindi__volantes v ON v.volante_id = vc.volante_id
                  WHERE vc.empresa_id = ? AND vc.estado_canal = 'PENDIENTE'
                    AND v.tabla_estado_registro_id = 31";
        $pStmt = $conn->prepare($qPend);
        $pStmt->bind_param("i", $empId);
        $pStmt->execute();
        $pendientes = $pStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $pStmt->close();

        $pubOk = 0;
        $updCanal = $conn->prepare("UPDATE sindi__volantes_canales SET 
            estado_canal = 'PUBLICADO', f_publicacion = NOW(), resultado_codigo = 'OK'
            WHERE volante_canal_id = ?");

        foreach ($pendientes as $p) {
            $updCanal->bind_param("i", $p['volante_canal_id']);
            $updCanal->execute();
            $pubOk++;
        }
        $updCanal->close();
        echo "     [Publicaciones] Reintentadas y confirmadas: $pubOk\n";

        // 2. Despublicar volantes vencidos o anulados
        $qBaja = "SELECT vc.volante_canal_id, vc.volante_id, v.numero, v.tabla_estado_registro_id
                  FROM sindi__volantes_canales vc
                  JOIN sindi__volantes v ON v.volante_id = vc.volante_id
                  WHERE vc.empresa_id = ? AND vc.estado_canal = 'PUBLICADO'
                    AND v.tabla_estado_registro_id IN (20, 38, 40)";
        $bStmt = $conn->prepare($qBaja);
        $bStmt->bind_param("i", $empId);
        $bStmt->execute();
        $paraBaja = $bStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $bStmt->close();

        $despubOk = 0;
        $updBaja = $conn->prepare("UPDATE sindi__volantes_canales SET 
            estado_canal = 'DESPUBLICADO', f_baja = NOW(), resultado_codigo = 'BAJA_OK'
            WHERE volante_canal_id = ?");

        foreach ($paraBaja as $pb) {
            $updBaja->bind_param("i", $pb['volante_canal_id']);
            $updBaja->execute();
            $despubOk++;
        }
        $updBaja->close();
        echo "     [Despublicaciones] Volantes despublicados: $despubOk\n";
    }

    echo "[" . date('Y-m-d H:i:s') . "] Sincronización completada.\n";
} finally {
    $conn->query("SELECT RELEASE_LOCK('sindi_cron_publicar_deudas')");
}
