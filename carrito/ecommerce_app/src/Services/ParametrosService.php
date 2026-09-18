<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Exceptions\HttpException;
use App\Repositories\ParametrosRepository;
use App\Support\Config;

/**
 * Resuelve los IDs de negocio contra la base compartida con el sistema de
 * administración, para que el .env sólo necesite las credenciales de la BD.
 *
 * Reglas:
 *  - Un valor definido en el .env SIEMPRE gana: la detección es un fallback.
 *  - El resultado se cachea en storage/cache/parametros.json (TTL configurable)
 *    para no pagar 6 consultas por request.
 *  - Si algo es ambiguo (varias empresas con el módulo), NO se adivina: se
 *    informa el error y se pide definirlo en el .env. Elegir mal la empresa
 *    significaría mostrar precios y pedidos de otra compañía.
 */
final class ParametrosService
{
    private const MODULO_NOMBRE = 'Ecommerce B2B';
    private const TTL_SEGUNDOS = 3600;

    private bool $resuelto = false;

    public function __construct(
        private readonly ParametrosRepository $repo,
        private readonly string $archivoCache,
    ) {
    }

    /** Completa en Config todos los ecom.* que estén sin definir. */
    public function resolver(): void
    {
        if ($this->resuelto) {
            return;
        }
        $this->resuelto = true;

        if (!$this->faltanValores()) {
            return;
        }

        $cache = $this->leerCache();
        if ($cache !== null) {
            $this->aplicar($cache);
            if (!$this->faltanValores()) {
                return;
            }
        }

        $detectado = $this->detectar();
        $this->aplicar($detectado);
        $this->guardarCache($detectado);

        $this->validar();
    }

    /** Borra la caché: usar tras cambiar perfiles, listas o puntos de venta. */
    public function invalidarCache(): void
    {
        if (is_file($this->archivoCache)) {
            @unlink($this->archivoCache);
        }
    }

    /** @return array<string,int> */
    public function detectar(): array
    {
        $valores = [];

        $moduloId = Config::int('ecom.modulo_id')
            ?: (int) ($this->repo->moduloIdPorNombre(self::MODULO_NOMBRE) ?? 0);
        $valores['modulo_id'] = $moduloId;

        $empresaId = Config::int('ecom.empresa_id');
        if ($empresaId <= 0 && $moduloId > 0) {
            // 1º módulo habilitado para la empresa (conf__empresas_modulos),
            // 2º evidencia de uso (usuarios vigentes), 3º perfil único.
            $empresaId = (int) ($this->repo->empresaPorModuloHabilitado($moduloId)
                ?? $this->repo->empresaPorUsuariosDelModulo($moduloId)
                ?? $this->repo->empresaUnicaDelModulo($moduloId)
                ?? 0);
        }
        $valores['empresa_id'] = $empresaId;

        if ($empresaId > 0) {
            $valores['sucursal_id'] = Config::int('ecom.sucursal_id')
                ?: (int) ($this->repo->sucursalConPuntoVenta($empresaId) ?? 0);

            $valores['moneda_id'] = Config::int('ecom.moneda_id')
                ?: (int) ($this->repo->monedaBase($empresaId) ?? 1);

            $valores['comprobante_tipo_pedido'] = Config::int('ecom.comprobante_tipo_pedido')
                ?: (int) ($this->repo->comprobanteTipoPedido($empresaId) ?? 0);

            $valores['lista_precio_default_id'] = Config::int('ecom.lista_precio_default_id')
                ?: (int) ($this->repo->listaPrecioUnica($empresaId) ?? 0);
        }

        $valores['tabla_origen_pedidos_id'] = Config::int('ecom.tabla_origen_pedidos_id')
            ?: (int) ($this->repo->tablaOrigenPedidos() ?? 0);

        return $valores;
    }

    /** @return array<string,string> diagnóstico legible para el verificador */
    public function diagnostico(): array
    {
        $this->resolver();

        return [
            'modulo_id'               => (string) Config::int('ecom.modulo_id'),
            'empresa_id'              => (string) Config::int('ecom.empresa_id'),
            'sucursal_id'             => (string) Config::int('ecom.sucursal_id'),
            'moneda_id'               => (string) Config::int('ecom.moneda_id'),
            'comprobante_tipo_pedido' => (string) Config::int('ecom.comprobante_tipo_pedido'),
            'lista_precio_default_id' => (string) Config::int('ecom.lista_precio_default_id'),
            'tabla_origen_pedidos_id' => (string) Config::int('ecom.tabla_origen_pedidos_id'),
        ];
    }

    private function faltanValores(): bool
    {
        foreach (['modulo_id', 'empresa_id', 'sucursal_id', 'comprobante_tipo_pedido', 'tabla_origen_pedidos_id'] as $clave) {
            if (Config::int("ecom.$clave") <= 0) {
                return true;
            }
        }
        return false;
    }

    /** @param array<string,int> $valores */
    private function aplicar(array $valores): void
    {
        foreach ($valores as $clave => $valor) {
            if ((int) $valor > 0 && Config::int("ecom.$clave") <= 0) {
                Config::set("ecom.$clave", (int) $valor);
            }
        }
    }

    private function validar(): void
    {
        if (Config::int('ecom.modulo_id') <= 0) {
            throw new HttpException(500, 'Falta instalar el módulo del ecommerce. Ejecutá la migración SQL.');
        }
        if (Config::int('ecom.empresa_id') <= 0) {
            throw new HttpException(500, 'No se pudo determinar la empresa. Definí ECOM_EMPRESA_ID en el .env.');
        }
        if (Config::int('ecom.sucursal_id') <= 0) {
            throw new HttpException(500, 'La empresa no tiene una sucursal con punto de venta configurado.');
        }
    }

    /** @return array<string,int>|null */
    private function leerCache(): ?array
    {
        if (!is_file($this->archivoCache)) {
            return null;
        }
        if ((time() - (int) filemtime($this->archivoCache)) > self::TTL_SEGUNDOS) {
            return null;
        }

        $contenido = @file_get_contents($this->archivoCache);
        if ($contenido === false) {
            return null;
        }

        $datos = json_decode($contenido, true);
        if (!is_array($datos)) {
            return null;
        }

        // Sanea: sólo enteros positivos con claves conocidas.
        $limpio = [];
        foreach ($this->diagnosticoClaves() as $clave) {
            if (isset($datos[$clave]) && is_int($datos[$clave]) && $datos[$clave] > 0) {
                $limpio[$clave] = $datos[$clave];
            }
        }

        return $limpio;
    }

    /** @param array<string,int> $valores */
    private function guardarCache(array $valores): void
    {
        $dir = dirname($this->archivoCache);
        if (!is_dir($dir) && !@mkdir($dir, 0o750, true)) {
            return;   // caché opcional: si no se puede escribir, se detecta por request
        }

        @file_put_contents(
            $this->archivoCache,
            json_encode($valores, JSON_UNESCAPED_SLASHES) ?: '{}',
            LOCK_EX
        );
    }

    /** @return string[] */
    private function diagnosticoClaves(): array
    {
        return [
            'modulo_id', 'empresa_id', 'sucursal_id', 'moneda_id',
            'comprobante_tipo_pedido', 'lista_precio_default_id', 'tabla_origen_pedidos_id',
        ];
    }
}
