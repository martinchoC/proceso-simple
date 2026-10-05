<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../core/permisos.php';

$res = usuario_obtener_empresas_modulos($conexion, 1);
print_r($res);
