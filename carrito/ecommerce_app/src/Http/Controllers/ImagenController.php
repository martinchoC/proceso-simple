<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Exceptions\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Support\Config;

final class ImagenController extends Controller
{
    /** Tipos permitidos: se fuerza el Content-Type, nunca se toma del request. */
    private const TIPOS = [
        'image/jpeg' => 'image/jpeg',
        'image/png'  => 'image/png',
        'image/webp' => 'image/webp',
        'image/gif'  => 'image/gif',
        'jpg'        => 'image/jpeg',
        'jpeg'       => 'image/jpeg',
        'png'        => 'image/png',
        'webp'       => 'image/webp',
    ];

    public function show(Request $request): Response
    {
        $imagen = $this->app->imagenes()->imagenDeCatalogo(
            $request->paramInt('id'),
            Config::int('ecom.empresa_id')
        );

        if ($imagen === null) {
            throw new HttpException(404, 'Imagen no encontrada.');
        }

        $tipo = self::TIPOS[strtolower($imagen['imagen_tipo'])] ?? 'application/octet-stream';

        return Response::raw($imagen['imagen_data'], $tipo, [
            'Cache-Control'            => 'private, max-age=86400',
            'Content-Disposition'      => 'inline',
            'X-Content-Type-Options'   => 'nosniff',
        ]);
    }
}
