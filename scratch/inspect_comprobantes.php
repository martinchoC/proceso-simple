<?php
$conexion = mysqli_connect('localhost', 'root', '', 'gestion_multipyme');
$res = $conexion->query("
    SELECT c.signo AS comp_signo, ct.codigo, ct.comprobante_tipo, ct.signo AS tipo_signo, count(*) as cant,
           min(c.importe_pendiente) as min_pend, max(c.importe_pendiente) as max_pend
    FROM gestion__comprobantes c
    JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = c.comprobante_tipo_id
    GROUP BY c.signo, ct.codigo, ct.comprobante_tipo, ct.signo
");
while ($r = $res->fetch_assoc()) {
    echo sprintf("%-10s | comp_signo: %2d | tipo_signo: %-3s | cant: %4d | pend: [%s a %s] | %s\n", 
        $r['codigo'], $r['comp_signo'], $r['tipo_signo'], $r['cant'], $r['min_pend'], $r['max_pend'], $r['comprobante_tipo']
    );
}

echo "\n--- Cancelaciones configuradas para RCX ---\n";
$res2 = $conexion->query("
    SELECT tc.*, cto.codigo as origen_codigo, ctd.codigo as destino_codigo, ctd.comprobante_tipo as destino_nombre
    FROM gestion__comprobantes_tipos_cancelaciones tc
    JOIN gestion__comprobantes_tipos cto ON cto.comprobante_tipo_id = tc.comprobante_tipo_origen_id
    JOIN gestion__comprobantes_tipos ctd ON ctd.comprobante_tipo_id = tc.comprobante_tipo_destino_id
    WHERE cto.codigo IN ('RCX', 'OP')
");
while ($r = $res2->fetch_assoc()) {
    echo "Origen: {$r['origen_codigo']} -> Destino: {$r['destino_codigo']} ({$r['destino_nombre']}) | Empresa: {$r['empresa_id']}\n";
}
