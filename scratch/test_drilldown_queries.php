<?php
ini_set('display_errors', 1); error_reporting(E_ALL);
require_once __DIR__ . '/../db.php';

echo "=== TEST QUERIES DRILLDOWN ===\n\n";

// 1. Tareas Globales por Estado y Vencimiento
$sqlKpi = "SELECT 
            COUNT(*) as total_tareas,
            SUM(CASE WHEN ct.fecha_vencimiento < NOW() AND ct.tabla_estado_registro_id NOT IN (2081, 2083) THEN 1 ELSE 0 END) as vencidas,
            SUM(CASE WHEN ct.fecha_vencimiento BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY) AND ct.tabla_estado_registro_id NOT IN (2081, 2083) THEN 1 ELSE 0 END) as proximas_7d,
            SUM(CASE WHEN ct.fecha_vencimiento > DATE_ADD(NOW(), INTERVAL 7 DAY) AND ct.tabla_estado_registro_id IN (2078, 2079, 2080) THEN 1 ELSE 0 END) as en_termino,
            SUM(CASE WHEN ct.tabla_estado_registro_id = 2081 THEN 1 ELSE 0 END) as cumplidas
           FROM ml__ciclos_tareas ct";
$resKpi = mysqli_query($conn, $sqlKpi);
$kpis = mysqli_fetch_assoc($resKpi);
echo "1. KPIs Globales de Tareas:\n";
print_r($kpis);

// 2. Desglose por Región
$sqlReg = "SELECT 
            rg.region_grupo_id,
            rg.nombre as region_nombre,
            COUNT(DISTINCT e.empresa_id) as total_empresas,
            COUNT(DISTINCT c.ciclo_id) as total_ciclos,
            COUNT(ct.ciclo_tarea_id) as total_tareas,
            SUM(CASE WHEN ct.fecha_vencimiento < NOW() AND ct.tabla_estado_registro_id NOT IN (2081, 2083) THEN 1 ELSE 0 END) as vencidas,
            SUM(CASE WHEN ct.fecha_vencimiento BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY) AND ct.tabla_estado_registro_id NOT IN (2081, 2083) THEN 1 ELSE 0 END) as proximas,
            SUM(CASE WHEN ct.tabla_estado_registro_id = 2081 THEN 1 ELSE 0 END) as cumplidas
           FROM ml__regiones_grupo rg
           LEFT JOIN ml__empresas_regiones er ON er.region_grupo_id = rg.region_grupo_id
           LEFT JOIN conf__empresas e ON e.empresa_id = er.empresa_id
           LEFT JOIN ml__ciclos c ON c.empresa_id = e.empresa_id
           LEFT JOIN ml__ciclos_tareas ct ON ct.ciclo_id = c.ciclo_id
           GROUP BY rg.region_grupo_id, rg.nombre";
$resReg = mysqli_query($conn, $sqlReg);
echo "\n2. Desglose por Región:\n";
while ($r = mysqli_fetch_assoc($resReg)) {
    print_r($r);
}

// 3. Desglose por País / Territorio
$sqlPais = "SELECT 
            t.territorio_id,
            t.codigo as pais_codigo,
            t.nombre as pais_nombre,
            COUNT(DISTINCT e.empresa_id) as total_empresas,
            COUNT(DISTINCT c.ciclo_id) as total_ciclos,
            COUNT(ct.ciclo_tarea_id) as total_tareas,
            SUM(CASE WHEN ct.fecha_vencimiento < NOW() AND ct.tabla_estado_registro_id NOT IN (2081, 2083) THEN 1 ELSE 0 END) as vencidas,
            SUM(CASE WHEN ct.fecha_vencimiento BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY) AND ct.tabla_estado_registro_id NOT IN (2081, 2083) THEN 1 ELSE 0 END) as proximas,
            SUM(CASE WHEN ct.tabla_estado_registro_id = 2081 THEN 1 ELSE 0 END) as cumplidas
           FROM ml__territorios t
           LEFT JOIN conf__empresas e ON 1=1 AND e.empresa_id = 2 -- Casa Lucho en Argentina
           LEFT JOIN ml__ciclos c ON c.empresa_id = e.empresa_id
           LEFT JOIN ml__ciclos_tareas ct ON ct.ciclo_id = c.ciclo_id
           WHERE t.territorio_id = 2 -- Argentina
           GROUP BY t.territorio_id, t.codigo, t.nombre";
$resPais = mysqli_query($conn, $sqlPais);
echo "\n3. Desglose por País (Argentina):\n";
while ($r = mysqli_fetch_assoc($resPais)) {
    print_r($r);
}

// 4. Desglose por Empresa
$sqlEmp = "SELECT 
            e.empresa_id,
            e.empresa as empresa_nombre,
            COUNT(DISTINCT c.ciclo_id) as total_ciclos,
            COUNT(ct.ciclo_tarea_id) as total_tareas,
            SUM(CASE WHEN ct.fecha_vencimiento < NOW() AND ct.tabla_estado_registro_id NOT IN (2081, 2083) THEN 1 ELSE 0 END) as vencidas,
            SUM(CASE WHEN ct.fecha_vencimiento BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY) AND ct.tabla_estado_registro_id NOT IN (2081, 2083) THEN 1 ELSE 0 END) as proximas,
            SUM(CASE WHEN ct.tabla_estado_registro_id = 2081 THEN 1 ELSE 0 END) as cumplidas
           FROM conf__empresas e
           LEFT JOIN ml__ciclos c ON c.empresa_id = e.empresa_id
           LEFT JOIN ml__ciclos_tareas ct ON ct.ciclo_id = c.ciclo_id
           WHERE e.empresa_id = 2
           GROUP BY e.empresa_id, e.empresa";
$resEmp = mysqli_query($conn, $sqlEmp);
echo "\n4. Desglose por Empresa (Casa Lucho):\n";
while ($r = mysqli_fetch_assoc($resEmp)) {
    print_r($r);
}

// 5. Desglose por Entidad / Tanque / Planta
$sqlEnt = "SELECT 
            ent.entidad_id,
            ent.codigo as entidad_codigo,
            ent.nombre as entidad_nombre,
            et.nombre as entidad_tipo,
            COUNT(DISTINCT c.ciclo_id) as total_ciclos,
            COUNT(ct.ciclo_tarea_id) as total_tareas,
            SUM(CASE WHEN ct.fecha_vencimiento < NOW() AND ct.tabla_estado_registro_id NOT IN (2081, 2083) THEN 1 ELSE 0 END) as vencidas,
            SUM(CASE WHEN ct.fecha_vencimiento BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY) AND ct.tabla_estado_registro_id NOT IN (2081, 2083) THEN 1 ELSE 0 END) as proximas,
            SUM(CASE WHEN ct.tabla_estado_registro_id = 2081 THEN 1 ELSE 0 END) as cumplidas
           FROM ml__entidades ent
           JOIN ml__entidades_tipos et ON et.entidad_tipo_id = ent.entidad_tipo_id
           LEFT JOIN ml__ciclos c ON c.entidad_id = ent.entidad_id
           LEFT JOIN ml__ciclos_tareas ct ON ct.ciclo_id = c.ciclo_id
           WHERE ent.entidad_id IN (8, 9, 10)
           GROUP BY ent.entidad_id, ent.codigo, ent.nombre, et.nombre";
$resEnt = mysqli_query($conn, $sqlEnt);
echo "\n5. Desglose por Entidad (Tanques / Plantas):\n";
while ($r = mysqli_fetch_assoc($resEnt)) {
    print_r($r);
}

// 6. Lista de Tareas Críticas (Vencidas y Próximas)
$sqlCrit = "SELECT 
             ct.ciclo_tarea_id,
             ct.titulo,
             ct.fecha_vencimiento,
             DATEDIFF(NOW(), ct.fecha_vencimiento) as dias_atraso,
             ent.nombre as entidad_nombre,
             o.codigo as obligacion_codigo,
             u.usuario_nombre as responsable_nombre
            FROM ml__ciclos_tareas ct
            JOIN ml__ciclos c ON c.ciclo_id = ct.ciclo_id
            JOIN ml__entidades ent ON ent.entidad_id = c.entidad_id
            JOIN ml__obligaciones_asignadas oa ON oa.obligacion_asignada_id = c.obligacion_asignada_id
            JOIN ml__obligaciones o ON o.obligacion_id = oa.obligacion_prevaleciente_id
            LEFT JOIN ml__ciclos_tareas_raci ctr ON ctr.ciclo_tarea_id = ct.ciclo_tarea_id AND ctr.raci_rol_id = 1
            LEFT JOIN conf__usuarios u ON u.usuario_id = ctr.usuario_id
            WHERE ct.fecha_vencimiento < DATE_ADD(NOW(), INTERVAL 7 DAY)
              AND ct.tabla_estado_registro_id NOT IN (2081, 2083)
            ORDER BY ct.fecha_vencimiento ASC";
$resCrit = mysqli_query($conn, $sqlCrit);
echo "\n6. Tareas Críticas (Vencidas y Próximas):\n";
while ($r = mysqli_fetch_assoc($resCrit)) {
    print_r($r);
}
