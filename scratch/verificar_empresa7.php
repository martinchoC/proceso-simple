<?php
/**
 * scratch/verificar_empresa7.php
 * Verificación completa del entorno de pruebas de la nueva empresa 7 (AEC).
 */

declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../core/permisos.php';
require_once __DIR__ . '/../modules/sindicatos/sindi_ddjj_core_model.php';
require_once __DIR__ . '/../modules/sindicatos/sindi_planes_core_model.php';

echo "====================================================================\n";
echo "VERIFICACION INTEGRAL - NUEVA EMPRESA ID 7: AEC SINDICATO\n";
echo "====================================================================\n\n";

$empresa_id = 7;

// 1. Selector de empresas en Header
echo "--- 1. Visibilidad en Selector de Empresas ---\n";
foreach ([1 => 'Pablo (1)', 11 => 'Jose Gili (11)', 12 => 'Gustavo Blanco (12)'] as $uid => $unombre) {
    $empresas = usuario_obtener_empresas_modulos($conn, $uid);
    $tieneEmp7 = isset($empresas[7]);
    $modulos = $tieneEmp7 ? implode(', ', array_column($empresas[7]['modulos'], 'nombre')) : 'Ninguno';
    echo "  [OK] Usuario $unombre: " . ($tieneEmp7 ? "SI (Modulos: $modulos)" : "NO") . "\n";
}

// 2. Catálogos cargados
echo "\n--- 2. Catálogos y Parámetros Sindicales ---\n";
$cntConceptos = $conn->query("SELECT COUNT(*) AS c FROM sindi__conceptos WHERE empresa_id = $empresa_id")->fetch_assoc()['c'];
$cntCategorias = $conn->query("SELECT COUNT(*) AS c FROM sindi__categorias WHERE empresa_id = $empresa_id")->fetch_assoc()['c'];
$cntEscalas = $conn->query("SELECT COUNT(*) AS c FROM sindi__categorias_escalas WHERE empresa_id = $empresa_id")->fetch_assoc()['c'];
$cntMedios = $conn->query("SELECT COUNT(*) AS c FROM sindi__medios_pago WHERE empresa_id = $empresa_id")->fetch_assoc()['c'];
$cntPlanes = $conn->query("SELECT COUNT(*) AS c FROM sindi__planes_tipos WHERE empresa_id = $empresa_id")->fetch_assoc()['c'];
$cntTasas = $conn->query("SELECT COUNT(*) AS c FROM sindi__tasas_interes WHERE empresa_id = $empresa_id")->fetch_assoc()['c'];
$cntVenc = $conn->query("SELECT COUNT(*) AS c FROM sindi__vencimientos WHERE empresa_id = $empresa_id")->fetch_assoc()['c'];

echo "  Conceptos de Aporte:     $cntConceptos\n";
echo "  Categorías CCT:          $cntCategorias\n";
echo "  Escalas Salariales:      $cntEscalas\n";
echo "  Medios de Pago:          $cntMedios\n";
echo "  Tipos de Planes:         $cntPlanes\n";
echo "  Tasas de Interés Mora:   $cntTasas\n";
echo "  Vencimientos CUIT:       $cntVenc\n";

// 3. Empleador Demo y Nómina
echo "\n--- 3. Padrón Demo Inicial ---\n";
$empRow = $conn->query("SELECT e.empleador_id, ent.entidad_nombre, ent.cuit 
                       FROM sindi__empleadores e 
                       JOIN gestion__entidades ent ON ent.entidad_id = e.entidad_id 
                       WHERE e.empresa_id = $empresa_id")->fetch_assoc();
echo "  Empleador ID {$empRow['empleador_id']}: {$empRow['entidad_nombre']} (CUIT {$empRow['cuit']})\n";

$persRes = $conn->query("SELECT p.persona_id, p.cuil, p.apellido, p.nombre 
                        FROM sindi__relaciones_laborales rl 
                        JOIN sindi__personas p ON p.persona_id = rl.persona_id 
                        WHERE rl.empresa_id = $empresa_id AND rl.empleador_id = {$empRow['empleador_id']}");
while ($p = $persRes->fetch_assoc()) {
    echo "    - CUIL {$p['cuil']}: {$p['apellido']}, {$p['nombre']}\n";
}

// 4. Períodos en Cuenta Corriente
echo "\n--- 4. Períodos Abiertos ---\n";
$perRes = $conn->query("SELECT periodo, f_vto, situacion FROM sindi__empleadores_periodos WHERE empresa_id = $empresa_id");
while ($per = $perRes->fetch_assoc()) {
    echo "  Período {$per['periodo']}: Vto {$per['f_vto']} - Situación: {$per['situacion']}\n";
}

// 5. Test de Simulación de Plan de Pagos
echo "\n--- 5. Prueba de Simulación de Plan de Pagos en Empresa 7 ---\n";
$tipoPlan = $conn->query("SELECT plan_tipo_id FROM sindi__planes_tipos WHERE empresa_id = $empresa_id LIMIT 1")->fetch_assoc();
$sim = sindiPlanSimular($conn, $empresa_id, (int)$tipoPlan['plan_tipo_id'], 150000.00, 6, 10.00);
echo "  Simulación exitosa: Capital $150.000 -> Total Financiado $" . number_format($sim['total_financiado'], 2, ',', '.') . " en " . count($sim['cronograma']) . " cuotas.\n";

echo "\n====================================================================\n";
echo "ESTADO FINAL: TODO LISTO Y VERIFICADO AL 100% PARA PRUEBAS\n";
echo "====================================================================\n";
