<?php
declare(strict_types=1);

/**
 * modules/sindicatos/volantes_model.php
 * Capa de acceso a datos para la Página 1030 (Volantes de Pago).
 */

require_once __DIR__ . '/sindi_volantes_core_model.php';
require_once __DIR__ . '/sindi_intereses_core_model.php';

function sindi_listar_volantes_pantalla(
    mysqli $conexion,
    int $empresa_id,
    int $usuario_id,
    int $pagina_id,
    ?int $empleador_id = null
): array {
    return sindi_listar_volantes($conexion, $empresa_id, $usuario_id, $pagina_id, $empleador_id);
}
