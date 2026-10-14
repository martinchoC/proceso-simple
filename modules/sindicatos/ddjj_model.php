<?php
declare(strict_types=1);

/**
 * modules/sindicatos/ddjj_model.php
 * Adaptador de modelo para la Página 1020 (Declaraciones Juradas).
 */

require_once __DIR__ . '/sindi_ddjj_core_model.php';

// Re-exporta y conecta funciones del core model para la página 1020
function sindi_listar_ddjj_pantalla(mysqli $conexion, int $empresa_id, int $usuario_id, int $pagina_id, ?int $empleador_id = null, ?int $periodo = null): array
{
    return sindi_listar_ddjj($conexion, $empresa_id, $usuario_id, $pagina_id, $empleador_id, $periodo);
}
