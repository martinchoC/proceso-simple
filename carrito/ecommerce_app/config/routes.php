<?php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CarritoController;
use App\Http\Controllers\CatalogoController;
use App\Http\Controllers\ImagenController;
use App\Http\Controllers\PedidoController;
use App\Http\Router;

/**
 * Tabla de rutas. Cada entrada declara explícitamente sus middlewares.
 * Regla del proyecto: TODA ruta de negocio lleva 'auth' + 'permiso:<código>'
 * y toda ruta mutante lleva 'csrf'.
 */
return static function (Router $router): Router {
    // Autenticación
    $router->add('GET',  '/login',  [AuthController::class, 'showLogin'], ['guest']);
    $router->add('POST', '/login',  [AuthController::class, 'login'],     ['guest', 'csrf']);
    $router->add('POST', '/logout', [AuthController::class, 'logout'],    ['auth', 'csrf']);

    // Catálogo (B2B cerrado: requiere sesión)
    $router->add('GET', '/',                     [CatalogoController::class, 'index'],      ['auth', 'permiso:ecom.catalogo.ver']);
    $router->add('GET', '/catalogo',             [CatalogoController::class, 'index'],      ['auth', 'permiso:ecom.catalogo.ver']);
    $router->add('GET', '/catalogo/modelos',    [CatalogoController::class, 'modelos'],    ['auth', 'permiso:ecom.catalogo.ver']);
    $router->add('GET', '/catalogo/submodelos', [CatalogoController::class, 'submodelos'], ['auth', 'permiso:ecom.catalogo.ver']);
    $router->add('GET', '/productos/{id}',       [CatalogoController::class, 'show'],       ['auth', 'permiso:ecom.catalogo.ver']);
    $router->add('GET', '/imagenes/{id}',        [ImagenController::class, 'show'],         ['auth', 'permiso:ecom.catalogo.ver']);

    // Carrito
    $router->add('GET',  '/carrito',                [CarritoController::class, 'index'],   ['auth', 'permiso:ecom.carrito.gestionar']);
    $router->add('POST', '/carrito/items',          [CarritoController::class, 'agregar'],    ['auth', 'csrf', 'permiso:ecom.carrito.gestionar']);
    $router->add('POST', '/carrito/items/fijar',    [CarritoController::class, 'fijar'],      ['auth', 'csrf', 'permiso:ecom.carrito.gestionar']);
    $router->add('POST', '/carrito/items/cantidad', [CarritoController::class, 'actualizar'], ['auth', 'csrf', 'permiso:ecom.carrito.gestionar']);
    $router->add('POST', '/carrito/items/eliminar', [CarritoController::class, 'eliminar'], ['auth', 'csrf', 'permiso:ecom.carrito.gestionar']);
    $router->add('POST', '/carrito/vaciar',         [CarritoController::class, 'vaciar'],  ['auth', 'csrf', 'permiso:ecom.carrito.gestionar']);

    // Pedidos
    $router->add('GET',  '/pedidos',      [PedidoController::class, 'index'], ['auth', 'permiso:ecom.pedido.ver']);
    $router->add('GET',  '/pedidos/{id}', [PedidoController::class, 'show'],  ['auth', 'permiso:ecom.pedido.ver']);
    $router->add('POST', '/pedidos',      [PedidoController::class, 'store'], ['auth', 'csrf', 'permiso:ecom.pedido.crear']);

    return $router;
};
