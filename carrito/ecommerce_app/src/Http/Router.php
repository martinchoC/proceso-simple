<?php

declare(strict_types=1);

namespace App\Http;

use App\Http\Exceptions\HttpException;

/**
 * Router explícito: cada ruta declara su controlador, su acción y la lista
 * de middlewares. No hay resolución "mágica" de clases desde la URL, lo que
 * elimina el riesgo de invocar código arbitrario.
 */
final class Router
{
    /** @var array<int,array{method:string,pattern:string,regex:string,handler:array{0:string,1:string},middleware:string[]}> */
    private array $routes = [];

    /**
     * @param array{0:string,1:string} $handler [ClaseControlador::class, 'metodo']
     * @param string[] $middleware
     */
    public function add(string $method, string $pattern, array $handler, array $middleware = []): void
    {
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[0-9]+)', $pattern) . '$#';

        $this->routes[] = [
            'method'     => strtoupper($method),
            'pattern'    => $pattern,
            'regex'      => $regex,
            'handler'    => $handler,
            'middleware' => $middleware,
        ];
    }

    /**
     * @return array{handler:array{0:string,1:string},middleware:string[],params:array<string,string>}
     * @throws HttpException 404 / 405
     */
    public function match(Request $request): array
    {
        $pathMatched = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $request->path, $matches)) {
                continue;
            }
            $pathMatched = true;

            if ($route['method'] !== $request->method) {
                continue;
            }

            $params = [];
            foreach ($matches as $key => $value) {
                if (!is_int($key)) {
                    $params[$key] = $value;
                }
            }

            return [
                'handler'    => $route['handler'],
                'middleware' => $route['middleware'],
                'params'     => $params,
            ];
        }

        throw new HttpException($pathMatched ? 405 : 404, $pathMatched ? 'Método no permitido.' : 'Página no encontrada.');
    }
}
