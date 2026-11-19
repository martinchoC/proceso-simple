<?php
// Sincroniza los perfiles de la empresa 1000 (módulo 5) con sus perfiles base y les da acceso a sucursales.
// Uso: C:\xampp\php\php.exe _local/scratch/sync_perfiles_emp1000.php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/perfiles_predefinidos.php';
$empresa_id = 1000;
$res = mysqli_query($conn, "SELECT DISTINCT perfil_id_base FROM conf__empresas_perfiles WHERE empresa_id = $empresa_id AND modulo_id = 5 AND tabla_estado_registro_id = 1");
while ($r = mysqli_fetch_row($res)) {
    try {
        $out = perfil_base_copiar_a_empresa($conn, $empresa_id, 5, (int) $r[0], true);
        echo "base {$r[0]} -> perfil {$out['empresa_perfil_id']} +{$out['funciones']} funciones\n";
    } catch (Throwable $e) {
        echo "base {$r[0]}: " . $e->getMessage() . "\n";
    }
}
