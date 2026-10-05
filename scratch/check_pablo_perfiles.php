<?php
require_once __DIR__ . '/../db.php';

echo "=== CONF__EMPRESAS_PERFILES (modulo 4 o empresa 2) ===\n";
$res = mysqli_query($conn, "SELECT * FROM conf__empresas_perfiles WHERE modulo_id = 4 OR empresa_id = 2");
while ($r = mysqli_fetch_assoc($res)) {
    echo "empresa_perfil_id: {$r['empresa_perfil_id']} | empresa_id: {$r['empresa_id']} | modulo_id: {$r['modulo_id']} | perfil_id_base: {$r['perfil_id_base']} | descripcion: {$r['descripcion']}\n";
}

echo "\n=== CONF__USUARIOS_PERFILES PARA USUARIO 1 (Pablo) ===\n";
$res_up = mysqli_query($conn, "SELECT up.*, ep.empresa_id, ep.modulo_id, ep.descripcion 
                               FROM conf__usuarios_perfiles up 
                               LEFT JOIN conf__empresas_perfiles ep ON ep.empresa_perfil_id = up.empresa_perfil_id 
                               WHERE up.usuario_id = 1");
while ($r = mysqli_fetch_assoc($res_up)) {
    echo "usuario_perfil_id: {$r['usuario_perfil_id']} | ep_id: {$r['empresa_perfil_id']} | emp: {$r['empresa_id']} | mod: {$r['modulo_id']} | desc: {$r['descripcion']} | estado: {$r['tabla_estado_registro_id']}\n";
}
