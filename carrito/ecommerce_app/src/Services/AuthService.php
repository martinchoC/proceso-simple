<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Exceptions\ValidationException;
use App\Repositories\EntidadRepository;
use App\Repositories\LoginIntentoRepository;
use App\Repositories\UsuarioRepository;
use App\Support\Config;
use App\Support\Csrf;
use App\Support\Documento;
use App\Support\Logger;
use App\Support\Session;
use PDOException;

/**
 * Autenticación contra conf__usuarios.
 *
 * Decisiones de seguridad:
 *  - Mensaje de error único para usuario inexistente / clave incorrecta.
 *  - password_verify siempre se ejecuta (hash dummy) para no filtrar la
 *    existencia del usuario por tiempo de respuesta.
 *  - Throttling por usuario + IP, con auditoría de cada intento.
 *  - Regeneración de ID de sesión y rotación de token CSRF en el login.
 *  - Expiración por inactividad (duracion_sid_minutos del usuario) y absoluta.
 */
final class AuthService
{
    /** Hash de referencia para igualar tiempos cuando el usuario no existe. */
    private const HASH_DUMMY = '$2y$12$u489HddYy.csZN9ckYgmB.QMV76iomzQ23/pVzjK7MJru8A/0A85.';

    private const MENSAJE_CREDENCIALES = 'Usuario o contraseña incorrectos.';

    /** El usuario no se corresponde con ninguna entidad cliente del ERP. */
    private const MENSAJE_SIN_CLIENTE = 'Tu usuario no corresponde a un cliente habilitado en la tienda. '
        . 'El usuario debe ser el CUIL/CUIT del cliente.';

    /** El cliente existe, pero su tipo no tiene habilitado el acceso web. */
    private const MENSAJE_SIN_PERMISOS = 'No poseés permisos suficientes para acceder al sitio. '
        . 'Contactá a tu vendedor para que habiliten el acceso web de tu cuenta.';

    public function __construct(
        private readonly UsuarioRepository $usuarios,
        private readonly EntidadRepository $entidades,
        private readonly LoginIntentoRepository $intentos,
        private readonly SessionGuard $guard,
        private readonly Session $session,
        private readonly Csrf $csrf,
        private readonly Logger $logger,
    ) {
    }

    public function login(string $usuario, string $clave, string $ip, string $userAgent): void
    {
        $maxIntentos = Config::int('login.max_intentos', 5);
        $ventana = Config::int('login.ventana_minutos', 15);

        if ($this->intentos->fallidosRecientes($usuario, $ip, $ventana) >= $maxIntentos) {
            $this->logger->warning('Login bloqueado por throttling', ['usuario' => $usuario, 'ip' => $ip]);
            throw new ValidationException(
                'Demasiados intentos fallidos. Esperá ' . Config::int('login.bloqueo_minutos', 15) . ' minutos.'
            );
        }

        $registro = $this->usuarios->buscarPorUsuario($usuario);
        $hash = is_array($registro) ? (string) $registro['password'] : self::HASH_DUMMY;
        $passwordOk = password_verify($clave, $hash);

        $activo = is_array($registro) && (int) $registro['tabla_estado_registro_id'] === 1;

        if (!$passwordOk || !$activo) {
            $this->intentos->registrar($usuario, $ip, false, $userAgent);
            $this->logger->warning('Login fallido', [
                'usuario' => $usuario,
                'ip'      => $ip,
                'motivo'  => $passwordOk ? 'usuario_inactivo' : 'credenciales',
            ]);
            throw new ValidationException(self::MENSAJE_CREDENCIALES);
        }

        /** @var array<string,mixed> $registro */
        $usuarioId = (int) $registro['usuario_id'];

        // Se resuelve acá y no al principio: para entonces la conexión ya
        // resolvió los parámetros de negocio que puedan faltar en el .env.
        $cliente = $this->clienteDelUsuario((string) $registro['usuario'], $usuarioId, $usuario, $ip, $userAgent);

        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            $this->usuarios->actualizarPassword($usuarioId, password_hash($clave, PASSWORD_DEFAULT));
        }

        $this->session->regenerate();
        $this->csrf->rotate();

        $this->guard->iniciar([
            'usuario_id'     => $usuarioId,
            'usuario'        => (string) $registro['usuario'],
            'usuario_nombre' => (string) $registro['usuario_nombre'],
            'entidad_id'     => (int) $cliente['entidad_id'],
            'entidad_nombre' => (string) ($cliente['entidad_fantasia'] ?: $cliente['entidad_nombre']),
            'idle_minutes'   => (int) ($registro['duracion_sid_minutos'] ?: Config::int('session.idle_minutes', 60)),
        ]);

        $this->intentos->registrar($usuario, $ip, true, $userAgent);
        $this->intentos->limpiarPorUsuario($usuario);

        $this->logger->info('Login exitoso', ['usuario_id' => $usuarioId, 'ip' => $ip]);
    }

    /**
     * Resuelve el cliente del usuario exigiendo que conf__usuarios.usuario
     * coincida con el documento (CUIL/CUIT) de una entidad cliente activa.
     *
     * Se ejecuta después de validar la contraseña, así que el mensaje puede
     * ser específico sin filtrar nada: quien llega hasta acá ya demostró
     * conocer la credencial. Cada rechazo cuenta como intento fallido para
     * que el throttling siga aplicando.
     *
     * @return array<string,mixed>
     */
    private function clienteDelUsuario(
        string $nombreUsuario,
        int $usuarioId,
        string $usuarioIngresado,
        string $ip,
        string $userAgent
    ): array {
        $empresaId = Config::int('ecom.empresa_id');
        $columna   = Config::get('ecom.entidad_doc_columna', 'cuil');

        $documento = Documento::normalizar($nombreUsuario);

        if ($documento === '') {
            $this->rechazar($usuarioIngresado, $ip, $userAgent, 'usuario_sin_formato_documento', [
                'usuario_id' => $usuarioId,
            ]);
        }

        // Todos los identificadores de la consulta se verifican contra el
        // esquema antes de armarla. Si alguno no existe se deniega con el
        // detalle en el log, en lugar de dejar que falle el driver.
        if (!$this->entidades->esquemaDisponible($columna)) {
            $this->rechazar(
                $usuarioIngresado,
                $ip,
                $userAgent,
                'configuracion_no_coincide_con_el_esquema',
                [
                    'usuario_id' => $usuarioId,
                    'problemas'  => implode(' | ', $this->entidades->verificarEsquema($columna)),
                ],
                self::MENSAJE_SIN_PERMISOS
            );
        }

        // Cualquier problema de esquema que se escape de los controles previos
        // se convierte en denegación con causa registrada, nunca en un 500:
        // un error de infraestructura no debe dejar la puerta abierta ni
        // romper la pantalla de login.
        try {
            $cliente = $this->entidades->clientePorDocumento($documento, $empresaId, $columna);
        } catch (PDOException $e) {
            $this->rechazar($usuarioIngresado, $ip, $userAgent, 'error_sql_resolviendo_cliente', [
                'usuario_id' => $usuarioId,
                'causa'      => $e->getMessage(),
            ], self::MENSAJE_SIN_PERMISOS);
        }

        if ($cliente !== null) {
            // El cliente existe: ahora decide el tipo de cliente del ERP.
            if (!$this->entidades->tipoHabilitaWeb($cliente)) {
                $this->rechazar(
                    $usuarioIngresado,
                    $ip,
                    $userAgent,
                    'tipo_cliente_sin_acceso_web',
                    [
                        'usuario_id'      => $usuarioId,
                        'entidad_id'      => (int) $cliente['entidad_id'],
                        'entidad_tipo_id' => $cliente['entidad_tipo_id'],
                        'acceso_web'      => $cliente['acceso_web'],
                    ],
                    self::MENSAJE_SIN_PERMISOS
                );
            }

            return $cliente;
        }

        // Sin coincidencia: distinguir el dato inconsistente del caso normal
        // para que el log sirva de diagnóstico. El mensaje al usuario es el
        // mismo en los dos casos.
        $motivo = $this->entidades->documentoDuplicado($documento, $empresaId, $columna)
            ? 'documento_duplicado_en_erp'
            : ($this->entidades->columnaDocumentoExiste($columna)
                ? 'sin_entidad_con_ese_documento'
                : 'columna_documento_inexistente');

        $this->rechazar($usuarioIngresado, $ip, $userAgent, $motivo, [
            'usuario_id' => $usuarioId,
            'empresa_id' => $empresaId,
            'columna'    => $columna,
        ]);
    }

    /**
     * @param array<string,mixed> $contexto
     * @return never
     */
    private function rechazar(
        string $usuario,
        string $ip,
        string $userAgent,
        string $motivo,
        array $contexto,
        ?string $mensaje = null
    ): never {
        $this->intentos->registrar($usuario, $ip, false, $userAgent);
        $this->logger->warning('Login rechazado', ['motivo' => $motivo] + $contexto);

        throw new ValidationException($mensaje ?? self::MENSAJE_SIN_CLIENTE);
    }

    public function logout(): void
    {
        $usuarioId = $this->guard->usuarioId();
        $this->guard->cerrar();
        if ($usuarioId > 0) {
            $this->logger->info('Logout', ['usuario_id' => $usuarioId]);
        }
    }

    /** Revocación efectiva: si el usuario se desactiva, la sesión cae en la request siguiente. */
    public function usuarioSigueHabilitado(): bool
    {
        return $this->usuarios->buscarActivoPorId($this->guard->usuarioId()) !== null;
    }
}
