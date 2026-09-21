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
        // 1. Liberar el cerrojo de la sesión de inmediato: la autenticación y permisos
        // ya fueron validados por los middlewares. Esto permite que las 24 imágenes
        // se carguen concurrentemente en paralelo sin serializarse.
        $this->app->session()->close();

        $imagenId = $request->paramInt('id');
        $etag = '"img-' . $imagenId . '"';

        // 2. Caché condicional HTTP: si el navegador ya la tiene, no transferimos
        // datos ni consultamos el BLOB de la base de datos.
        $ifNoneMatch = $request->header('If-None-Match');
        if ($ifNoneMatch !== null && (trim($ifNoneMatch) === $etag || trim($ifNoneMatch) === 'W/' . $etag)) {
            return Response::raw('', '', [
                'ETag'          => $etag,
                'Cache-Control' => 'private, max-age=604800, stale-while-revalidate=86400',
            ], 304);
        }

        $imagen = $this->app->imagenes()->imagenDeCatalogo(
            $imagenId,
            Config::int('ecom.empresa_id')
        );

        if ($imagen === null) {
            throw new HttpException(404, 'Imagen no encontrada.');
        }

        $tipo = self::TIPOS[strtolower($imagen['imagen_tipo'])] ?? 'application/octet-stream';

        return Response::raw($imagen['imagen_data'], $tipo, [
            'ETag'                     => $etag,
            'Cache-Control'            => 'private, max-age=604800, stale-while-revalidate=86400',
            'Content-Disposition'      => 'inline',
            'X-Content-Type-Options'   => 'nosniff',
        ]);
    }
}
