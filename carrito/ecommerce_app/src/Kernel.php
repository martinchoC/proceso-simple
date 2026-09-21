<?php

declare(strict_types=1);

namespace App;

use App\Database\Connection;
use App\Http\Exceptions\HttpException;
use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\CsrfMiddleware;
use App\Http\Middleware\GuestMiddleware;
use App\Http\Middleware\Middleware;
use App\Http\Middleware\PermisoMiddleware;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Repositories\CarritoRepository;
use App\Repositories\EntidadRepository;
use App\Repositories\ImagenRepository;
use App\Repositories\LoginIntentoRepository;
use App\Repositories\ParametrosRepository;
use App\Repositories\PedidoRepository;
use App\Repositories\PermisoRepository;
use App\Repositories\PrecioRepository;
use App\Repositories\ProductoRepository;
use App\Repositories\UsuarioRepository;
use App\Services\AuthService;
use App\Services\AutorizacionService;
use App\Services\CarritoService;
use App\Services\CatalogoService;
use App\Services\ParametrosService;
use App\Services\PedidoService;
use App\Services\PrecioService;
use App\Services\SessionGuard;
use App\Support\Csrf;
use App\Support\Logger;
use App\Support\Session;
use App\View\View;
use PDO;
use Throwable;

/**
 * Contenedor liviano + despachador. Instancia perezosa: sólo se construye lo
 * que la request usa. Se prefiere esto a un DI container completo por
 * simplicidad y trazabilidad.
 */
final class Kernel
{
    /** @var array<string,object> */
    private array $instances = [];

    private bool $parametrosListos = false;

    public function __construct(
        private readonly string $basePath,
        private readonly Logger $logger,
    ) {
    }

    public function handle(Request $request): Response
    {
        $this->session()->start();

        try {
            $route = (require $this->basePath . '/config/routes.php')($this->router())->match($request);
            $request = $request->withParams($route['params']);

            foreach ($route['middleware'] as $name) {
                $result = $this->middleware($name)->handle($request);
                if ($result instanceof Response) {
                    return $result;   // cortocircuito: redirect o error
                }
            }

            [$class, $method] = $route['handler'];
            /** @var object $controller */
            $controller = $this->make($class);

            return $controller->{$method}($request);
        } catch (HttpException $e) {
            // Los 4xx son parte de la operación normal (no encontrado, sin
            // permiso) y no se loguean. Los 5xx SÍ: siempre indican un problema
            // de configuración o de datos que hay que poder diagnosticar.
            if ($e->status() >= 500) {
                $this->logger->error('Error de configuración', [
                    'mensaje'    => $e->getMessage(),
                    'path'       => $request->path,
                    'metodo'     => $request->method,
                    'usuario_id' => (int) $this->session()->get('usuario_id', 0),
                ]);
            }

            return $this->renderError($request, $e->status(), $e->getMessage());
        } catch (Throwable $e) {
            $this->logger->error('Error no controlado', [
                'exception' => $e::class,
                'message'   => $e->getMessage(),
                // Causa real (ej. el error del driver MySQL). Va SÓLO al log,
                // nunca a la respuesta: el usuario ve un mensaje genérico.
                'causa'     => $e->getPrevious()?->getMessage(),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
                'path'      => $request->path,
                'usuario_id' => (int) $this->session()->get('usuario_id', 0),
            ]);

            return $this->renderError($request, 500, 'Se produjo un error inesperado.');
        }
    }

    private function renderError(Request $request, int $status, string $message): Response
    {
        if ($request->expectsJson()) {
            return Response::json(['ok' => false, 'error' => $message], $status);
        }

        return Response::html(
            $this->view()->render('errors/generico', ['status' => $status, 'mensaje' => $message]),
            $status
        );
    }

    // ── Infraestructura ───────────────────────────────────────────────────

    /**
     * Devuelve la conexión y, la primera vez, resuelve los IDs de negocio que
     * falten en el .env. Engancharlo acá garantiza que cualquier consulta del
     * storefront ya encuentre la configuración completa, sin repetir la
     * resolución en cada controlador.
     */
    public function pdo(): PDO
    {
        $pdo = Connection::get();

        if (!$this->parametrosListos) {
            $this->parametrosListos = true;   // se marca antes: corta la recursión
            $this->parametros()->resolver();
        }

        return $pdo;
    }

    /** Detección automática de empresa, módulo, sucursal, moneda y comprobante. */
    public function parametros(): ParametrosService
    {
        return $this->singleton(ParametrosService::class, fn (): ParametrosService => new ParametrosService(
            new ParametrosRepository(Connection::get()),
            $this->basePath . '/storage/cache/parametros.json'
        ));
    }

    public function session(): Session
    {
        return $this->singleton(Session::class, static fn (): Session => new Session());
    }

    public function csrf(): Csrf
    {
        return $this->singleton(Csrf::class, fn (): Csrf => new Csrf($this->session()));
    }

    public function view(): View
    {
        return $this->singleton(View::class, fn (): View => new View(
            $this->basePath . '/resources/views',
            $this->session(),
            $this->csrf()
        ));
    }

    private function router(): Router
    {
        return $this->singleton(Router::class, static fn (): Router => new Router());
    }

    // ── Repositorios ──────────────────────────────────────────────────────

    public function usuarios(): UsuarioRepository
    {
        return $this->singleton(UsuarioRepository::class, fn () => new UsuarioRepository($this->pdo()));
    }

    public function permisos(): PermisoRepository
    {
        return $this->singleton(PermisoRepository::class, fn () => new PermisoRepository($this->pdo()));
    }

    public function loginIntentos(): LoginIntentoRepository
    {
        return $this->singleton(LoginIntentoRepository::class, fn () => $this->resolve(LoginIntentoRepository::class));
    }

    public function productos(): ProductoRepository
    {
        return $this->singleton(ProductoRepository::class, fn () => new ProductoRepository($this->pdo()));
    }

    public function precios(): PrecioRepository
    {
        return $this->singleton(PrecioRepository::class, fn () => new PrecioRepository($this->pdo()));
    }

    public function entidades(): EntidadRepository
    {
        return $this->singleton(EntidadRepository::class, fn () => new EntidadRepository($this->pdo()));
    }

    public function carritos(): CarritoRepository
    {
        return $this->singleton(CarritoRepository::class, fn () => new CarritoRepository($this->pdo()));
    }

    public function pedidos(): PedidoRepository
    {
        return $this->singleton(PedidoRepository::class, fn () => new PedidoRepository($this->pdo()));
    }

    public function imagenes(): ImagenRepository
    {
        return $this->singleton(ImagenRepository::class, fn () => new ImagenRepository($this->pdo()));
    }

    // ── Servicios ─────────────────────────────────────────────────────────

    /** Estado de sesión sin base de datos: seguro de usar en rutas públicas. */
    public function guard(): SessionGuard
    {
        return $this->singleton(SessionGuard::class, fn () => new SessionGuard($this->session()));
    }

    public function auth(): AuthService
    {
        return $this->singleton(AuthService::class, fn () => $this->resolve(AuthService::class));
    }

    public function autorizacion(): AutorizacionService
    {
        return $this->singleton(AutorizacionService::class, fn () => $this->resolve(AutorizacionService::class));
    }

    public function preciosService(): PrecioService
    {
        return $this->singleton(PrecioService::class, fn () => new PrecioService($this->precios()));
    }

    public function catalogo(): CatalogoService
    {
        return $this->singleton(CatalogoService::class, fn () => new CatalogoService(
            $this->productos(),
            $this->preciosService(),
            $this->session()
        ));
    }

    public function carrito(): CarritoService
    {
        return $this->singleton(CarritoService::class, fn () => new CarritoService(
            $this->carritos(),
            $this->productos(),
            $this->preciosService()
        ));
    }

    public function pedidoService(): PedidoService
    {
        return $this->singleton(PedidoService::class, fn () => new PedidoService(
            $this->pdo(),
            $this->pedidos(),
            $this->entidades(),
            $this->carrito(),
            $this->logger,
            $this->productos()
        ));
    }

    // ── Resolución ────────────────────────────────────────────────────────

    private function middleware(string $name): Middleware
    {
        [$key, $arg] = array_pad(explode(':', $name, 2), 2, null);

        return match ($key) {
            'auth'    => new AuthMiddleware($this->guard(), fn (): AuthService => $this->auth(), $this->session()),
            'guest'   => new GuestMiddleware($this->guard()),
            'csrf'    => new CsrfMiddleware($this->csrf()),
            'permiso' => new PermisoMiddleware($this->autorizacion(), (string) $arg),
            default   => throw new HttpException(500, 'Middleware desconocido.'),
        };
    }

    private function make(string $class): object
    {
        return new $class($this);
    }

    private function resolve(string $className): object
    {
        $ref = new \ReflectionClass($className);
        $ctor = $ref->getConstructor();
        if ($ctor === null) {
            return new $className();
        }

        $args = [];
        foreach ($ctor->getParameters() as $param) {
            $type = $param->getType();
            $name = $type instanceof \ReflectionNamedType ? $type->getName() : '';

            $val = match (true) {
                is_a($name, \PDO::class, true)                       => $this->pdo(),
                is_a($name, \App\Support\Session::class, true)       => $this->session(),
                is_a($name, \App\Support\Logger::class, true)        => $this->logger,
                is_a($name, \App\Services\SessionGuard::class, true) => $this->guard(),
                is_a($name, \App\Support\Csrf::class, true)          => $this->csrf(),
                is_a($name, PermisoRepository::class, true)          => $this->permisos(),
                is_a($name, UsuarioRepository::class, true)          => $this->usuarios(),
                is_a($name, EntidadRepository::class, true)          => $this->entidades(),
                is_a($name, LoginIntentoRepository::class, true)     => $this->loginIntentos(),
                is_a($name, ProductoRepository::class, true)         => $this->productos(),
                is_a($name, PrecioRepository::class, true)           => $this->precios(),
                is_a($name, CarritoRepository::class, true)          => $this->carritos(),
                is_a($name, PedidoRepository::class, true)           => $this->pedidos(),
                is_a($name, ImagenRepository::class, true)           => $this->imagenes(),
                is_a($name, PrecioService::class, true)              => $this->preciosService(),
                is_a($name, CatalogoService::class, true)            => $this->catalogo(),
                is_a($name, CarritoService::class, true)             => $this->carrito(),
                is_a($name, AuthService::class, true)                => $this->auth(),
                is_a($name, AutorizacionService::class, true)        => $this->autorizacion(),
                is_a($name, PedidoService::class, true)              => $this->pedidoService(),
                $param->isDefaultValueAvailable()                    => $param->getDefaultValue(),
                default                                              => null,
            };

            $args[] = $val;
        }

        return $ref->newInstanceArgs($args);
    }

    /** @param callable():object $factory */
    private function singleton(string $key, callable $factory): object
    {
        return $this->instances[$key] ??= $factory();
    }

    public function logger(): Logger
    {
        return $this->logger;
    }
}
