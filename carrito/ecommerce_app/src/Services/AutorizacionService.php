<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\PermisoRepository;
use App\Support\Config;
use App\Support\Logger;
use App\Support\Session;

/**
 * Permisos efectivos del usuario en el storefront.
 *
 * Hay dos modelos, y se elige explícitamente con ECOM_PERMISOS. No hay
 * degradación automática de uno a otro: en un control de acceso, caer sin
 * aviso al modelo más permisivo por un problema de esquema es cómo se
 * abren agujeros silenciosos.
 *
 *  - 'tienda' (por defecto): el permiso ya se decidió en el login. Entrar
 *    exige ser un usuario activo cuyo nombre es el CUIL de una entidad
 *    cliente activa de la empresa, y que el tipo de esa entidad tenga
 *    acceso_web = 1. Quien pasó ese filtro es un cliente habilitado y usa
 *    todo el storefront: ver catálogo, carrito y pedidos propios.
 *    No necesita ninguna configuración adicional en el ERP.
 *    Para revocar: acceso_web = 0 en el tipo de cliente, es_cliente = 0 en
 *    la entidad, o baja del usuario. Impacta en la request siguiente.
 *
 *  - 'erp': permiso por función, recorriendo perfiles del ERP hasta
 *    conf__paginas_funciones.codigo_funcion. Más fino, pero requiere tener
 *    el módulo, las páginas, las funciones y los perfiles cargados.
 *
 * Los permisos se cachean por request (no en sesión) para que un cambio
 * impacte en la request siguiente sin necesidad de re-login.
 */
final class AutorizacionService
{
    /** Funciones del storefront. En modo 'tienda' se otorgan todas juntas. */
    public const FUNCIONES = [
        'ecom.catalogo.ver',
        'ecom.carrito.gestionar',
        'ecom.pedido.crear',
        'ecom.pedido.ver',
    ];

    private const MODOS = ['tienda', 'erp'];

    /** @var string[]|null */
    private ?array $codigos = null;

    public function __construct(
        private readonly PermisoRepository $permisos,
        private readonly Session $session,
        private readonly Logger $logger,
    ) {
    }

    public function puede(string $codigoFuncion): bool
    {
        return in_array($codigoFuncion, $this->codigos(), true);
    }

    /** Modo activo, ya validado contra la lista de modos admitidos. */
    public function modo(): string
    {
        $modo = strtolower(trim((string) Config::get('ecom.permisos', 'tienda')));

        return in_array($modo, self::MODOS, true) ? $modo : 'tienda';
    }

    /** @return string[] */
    public function codigos(): array
    {
        if ($this->codigos !== null) {
            return $this->codigos;
        }

        $usuarioId = (int) $this->session->get('usuario_id', 0);
        if ($usuarioId <= 0) {
            return $this->codigos = [];
        }

        if ($this->modo() === 'tienda') {
            return $this->codigos = self::FUNCIONES;
        }

        // Modo 'erp'. Si el esquema no tiene la columna que sostiene este
        // modelo, se deniega y se registra: es un error de configuración,
        // no una razón para dar permisos.
        if (!$this->permisos->soportaCodigoFuncion()) {
            $this->logger->error('Permisos en modo erp sin soporte en el esquema', [
                'falta'      => 'conf__paginas_funciones.codigo_funcion',
                'usuario_id' => $usuarioId,
                'solucion'   => 'usar ECOM_PERMISOS=tienda o agregar la columna al ERP',
            ]);

            return $this->codigos = [];
        }

        return $this->codigos = $this->permisos->codigosPorUsuario(
            $usuarioId,
            Config::int('ecom.empresa_id'),
            Config::int('ecom.modulo_id')
        );
    }
}
