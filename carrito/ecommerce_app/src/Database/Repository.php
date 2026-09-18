<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use PDOStatement;

/**
 * Base de repositorios: acceso a PDO + helpers de consulta parametrizada.
 * Ningún repositorio concatena valores de usuario dentro del SQL.
 */
abstract class Repository
{
    public function __construct(protected readonly PDO $pdo)
    {
    }

    /** @param array<string,mixed> $params */
    protected function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * @param array<string,mixed> $params
     * @return array<int,array<string,mixed>>
     */
    protected function all(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    /**
     * @param array<string,mixed> $params
     * @return array<string,mixed>|null
     */
    protected function first(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** @param array<string,mixed> $params */
    protected function scalar(string $sql, array $params = []): mixed
    {
        $value = $this->run($sql, $params)->fetchColumn();
        return $value === false ? null : $value;
    }

    /**
     * Genera placeholders nombrados para un IN (...) a partir de una lista de
     * enteros ya validados. Es la única forma segura de armar un IN dinámico.
     *
     * @param int[] $ids
     * @return array{0:string,1:array<string,int>}
     */
    protected function inPlaceholders(array $ids, string $prefix): array
    {
        $names = [];
        $params = [];
        foreach (array_values($ids) as $i => $id) {
            $key = ":{$prefix}{$i}";
            $names[] = $key;
            $params[substr($key, 1)] = (int) $id;
        }
        return [implode(',', $names), $params];
    }
}
