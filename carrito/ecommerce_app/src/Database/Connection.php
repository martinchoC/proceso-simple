<?php

declare(strict_types=1);

namespace App\Database;

use App\Support\Config;
use PDO;
use PDOException;
use RuntimeException;

/**
 * Fábrica de PDO. Emulación de prepares DESACTIVADA: los placeholders viajan
 * al servidor, lo que elimina la clase entera de inyección por interpolación.
 */
final class Connection
{
    private static ?PDO $pdo = null;

    public static function get(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            (string) Config::get('db.host'),
            Config::int('db.port', 3306),
            (string) Config::get('db.name'),
            (string) Config::get('db.charset', 'utf8mb4')
        );

        try {
            self::$pdo = new PDO(
                $dsn,
                (string) Config::get('db.user'),
                (string) Config::get('db.pass'),
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::ATTR_STRINGIFY_FETCHES  => false,
                ]
            );
        } catch (PDOException $e) {
            // No propagar credenciales ni DSN al cliente.
            throw new RuntimeException('No se pudo conectar a la base de datos.', 0, $e);
        }

        return self::$pdo;
    }
}
