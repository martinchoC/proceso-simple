<?php
require_once __DIR__ . '/../../db.php';

echo "=== VERIFICACIÓN DEL EJEMPLO EN GESTION_MULTIPYME ===\n\n";

// Norma
$res = mysqli_query($conn, "SELECT norma_id, codigo, titulo, norma_tipo_id, organismo_id, tabla_estado_registro_id FROM ml__normas WHERE codigo = 'RES-SEN-785-05'");
$norma = mysqli_fetch_assoc($res);
echo "1. NORMA (ml__normas):\n";
print_r($norma);

// Versión
$resV = mysqli_query($conn, "SELECT norma_version_id, norma_id, version, es_vigente, tabla_estado_registro_id FROM ml__normas_versiones WHERE norma_id = {$norma['norma_id']}");
echo "2. VERSIÓN (ml__normas_versiones):\n";
print_r(mysqli_fetch_assoc($resV));

// Entidades
$resE = mysqli_query($conn, "SELECT entidad_id, entidad_padre_id, codigo, nombre FROM ml__entidades WHERE codigo IN ('TERM-DOCK-SUD', 'TK-101')");
echo "3. ENTIDADES (ml__entidades):\n";
while ($r = mysqli_fetch_assoc($resE)) {
    print_r($r);
}

// Obligación
$resO = mysqli_query($conn, "SELECT obligacion_id, norma_id, codigo, descripcion, periodicidad_id FROM ml__obligaciones WHERE codigo = 'OBL-SEN-785-ANUAL'");
echo "4. OBLIGACIÓN (ml__obligaciones):\n";
$obl = mysqli_fetch_assoc($resO);
print_r($obl);

// Obligación Asignada
$resOA = mysqli_query($conn, "SELECT obligacion_asignada_id, obligacion_prevaleciente_id, entidad_id FROM ml__obligaciones_asignadas WHERE obligacion_prevaleciente_id = {$obl['obligacion_id']}");
echo "5. ASIGNACIÓN (ml__obligaciones_asignadas):\n";
$oa = mysqli_fetch_assoc($resOA);
print_r($oa);

// Ciclo
$resC = mysqli_query($conn, "SELECT ciclo_id, obligacion_asignada_id, anio, fecha_inicio, fecha_vencimiento, tabla_estado_registro_id FROM ml__ciclos WHERE obligacion_asignada_id = {$oa['obligacion_asignada_id']}");
echo "6. CICLO (ml__ciclos):\n";
$ciclo = mysqli_fetch_assoc($resC);
print_r($ciclo);

// Tareas
$resT = mysqli_query($conn, "SELECT ciclo_tarea_id, ciclo_id, nombre, usuario_responsable_id, fecha_vencimiento, tabla_estado_registro_id FROM ml__ciclos_tareas WHERE ciclo_id = {$ciclo['ciclo_id']}");
echo "7. TAREAS DEL CICLO (ml__ciclos_tareas):\n";
while ($r = mysqli_fetch_assoc($resT)) {
    print_r($r);
}

// RACI
$resR = mysqli_query($conn, "SELECT cr.ciclo_raci_id, cr.ciclo_id, rr.codigo as rol, u.usuario, u.apellido, u.nombre 
                            FROM ml__ciclos_raci cr 
                            JOIN ml__raci_roles rr ON rr.raci_rol_id = cr.raci_rol_id
                            JOIN conf__usuarios u ON u.usuario_id = cr.usuario_id
                            WHERE cr.ciclo_id = {$ciclo['ciclo_id']}");
echo "8. MATRIZ RACI (ml__ciclos_raci):\n";
while ($r = mysqli_fetch_assoc($resR)) {
    echo " - Rol: {$r['rol']} | Usuario: {$r['usuario']} ({$r['apellido']}, {$r['nombre']})\n";
}

// Evidencia
$resEv = mysqli_query($conn, "SELECT evidencia_id, ciclo_tarea_id, nombre_archivo, tipo_mime FROM ml__evidencias WHERE ciclo_tarea_id = 8");
echo "9. EVIDENCIA (ml__evidencias):\n";
print_r(mysqli_fetch_assoc($resEv));
