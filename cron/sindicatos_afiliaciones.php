<?php
/**
 * cron/sindicatos_afiliaciones.php
 * Proceso CLI diario de afiliaciones (Módulo 6, §5.6):
 *  1. Vencimientos: pasa a Vencida (20) las afiliaciones de familiares cuya cobertura terminó (edad o
 *     documento de excepción vencido) y lista las que vencen en los próximos 30 días (aviso a la familia).
 *  2. Cuotas directas del mes en curso (voluntarios y jubilados con importe): idempotente, se puede correr
 *     todos los días.
 *
 * Uso CLI:
 *   php cron/sindicatos_afiliaciones.php [--empresa=7] [--fecha=AAAA-MM-DD] [--sin-cuotas]
 */

if (php_sapi_name() !== 'cli') {
    die("Este script solo puede ejecutarse desde la línea de comandos (CLI).\n");
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../modules/sindicatos/sindi_afiliaciones_core_model.php';

$options = getopt('', ['empresa::', 'fecha::', 'sin-cuotas']);
$filtroEmpresaId = isset($options['empresa']) ? (int)$options['empresa'] : null;
$fecha = isset($options['fecha']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $options['fecha']) ? $options['fecha'] : date('Y-m-d');
$generarCuotas = !isset($options['sin-cuotas']);
$usuarioSistema = 1;

echo "[" . date('Y-m-d H:i:s') . "] Afiliaciones: vencimientos y cuotas al {$fecha}\n";

$lockRes = $conn->query("SELECT GET_LOCK('sindi_cron_afiliaciones', 10) AS tiene_lock");
$lockRow = $lockRes->fetch_assoc();
if (!$lockRow || (int)$lockRow['tiene_lock'] !== 1) {
    die("[" . date('Y-m-d H:i:s') . "] ERROR: proceso ya en ejecución.\n");
}

try {
    $sqlEmp = "SELECT DISTINCT em.empresa_id FROM conf__empresas_modulos em WHERE em.modulo_id = 6 AND em.tabla_estado_registro_id = 1"
            . ($filtroEmpresaId ? " AND em.empresa_id = ?" : "");
    $stmt = $conn->prepare($sqlEmp);
    if ($filtroEmpresaId) {
        $stmt->bind_param("i", $filtroEmpresaId);
    }
    $stmt->execute();
    $empresas = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'empresa_id');
    $stmt->close();

    foreach ($empresas as $empId) {
        $empId = (int)$empId;
        echo "  -> Empresa {$empId}\n";

        $conn->begin_transaction();
        try {
            $res = sindi_afil_procesar_vencimientos($conn, $empId, $fecha, 30);
            $conn->commit();
            echo "     Vencidas: {$res['vencidas']}. Por vencer en 30 días: " . count($res['avisos']) . "\n";
            foreach ($res['avisos'] as $a) {
                echo "       - {$a['numero_afiliado']} {$a['apellido']} {$a['nombre']} vence {$a['f_vigencia']}"
                   . ($a['titular_email'] ? " (avisar a {$a['titular_email']})" : "") . "\n";
            }
        } catch (Throwable $t) {
            $conn->rollback();
            echo "     ERROR vencimientos: " . $t->getMessage() . "\n";
        }

        if ($generarCuotas) {
            try {
                $periodo = (int)date('Ym', strtotime($fecha));
                $cuotas = sindi_afil_generar_cuotas($conn, $empId, $usuarioSistema, $periodo);
                echo "     Cuotas directas {$periodo}: {$cuotas['generadas']} generadas" . ($cuotas['errores'] ? ", " . count($cuotas['errores']) . " con error" : "") . "\n";
                foreach ($cuotas['errores'] as $e) {
                    echo "       ! {$e}\n";
                }
            } catch (Throwable $t) {
                echo "     Cuotas no generadas: " . $t->getMessage() . "\n";
            }
        }
    }
} finally {
    $conn->query("SELECT RELEASE_LOCK('sindi_cron_afiliaciones')");
}
echo "[" . date('Y-m-d H:i:s') . "] Fin.\n";
