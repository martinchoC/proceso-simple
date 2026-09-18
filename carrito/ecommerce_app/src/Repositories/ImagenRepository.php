<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Repository;

/**
 * Las imágenes se sirven desde la BD por un endpoint propio, nunca por ruta
 * de archivo tomada del request: elimina path traversal y evita exponer
 * imágenes de otra empresa.
 */
final class ImagenRepository extends Repository
{
    /** @return array{imagen_tipo:string,imagen_data:string}|null */
    public function imagenDeCatalogo(int $imagenId, int $empresaId): ?array
    {
        $row = $this->first(
            'SELECT i.imagen_tipo, i.imagen_data
               FROM conf__imagenes i
         INNER JOIN gestion__productos_imagenes pi
                 ON pi.imagen_id = i.imagen_id
                AND pi.empresa_id = :empresa_id
                AND pi.tabla_estado_registro_id = 1
         INNER JOIN gestion__productos p
                 ON p.producto_id = pi.producto_id
                AND p.empresa_id = pi.empresa_id
                AND p.tabla_estado_registro_id = 1
              WHERE i.imagen_id = :imagen_id
                AND i.tabla_estado_registro_id = 1
              LIMIT 1',
            ['imagen_id' => $imagenId, 'empresa_id' => $empresaId]
        );

        if ($row === null || !is_string($row['imagen_data'])) {
            return null;
        }

        return [
            'imagen_tipo' => (string) $row['imagen_tipo'],
            'imagen_data' => $row['imagen_data'],
        ];
    }
}
