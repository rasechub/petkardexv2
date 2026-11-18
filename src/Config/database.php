<?php
/**
 * PetKardex v2 - Clase Database (Singleton PDO)
 */

declare(strict_types=1);

namespace PetKardex\Config;

use PDO;
use PDOException;
use PDOStatement;

class Database
{
    private static ?Database $instance = null;
    private PDO $connection;

    /**
     * Constructor privado — sólo se llama desde getInstance()
     *
     * @throws PDOException si la conexión falla
     */
    private function __construct()
    {
        $host    = Config::getDbHost();
        $port    = Config::getDbPort();
        $dbName  = Config::getDbName();
        $charset = Config::getDbCharset();

        $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset={$charset}";

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES '{$charset}' COLLATE '{$charset}_unicode_ci'",
        ];

        $this->connection = new PDO(
            $dsn,
            Config::getDbUser(),
            Config::getDbPassword(),
            $options
        );
    }

    /** Evita la clonación del singleton */
    private function __clone() {}

    /**
     * Retorna la única instancia del singleton
     */
    public static function getInstance(): static
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    /**
     * Retorna el objeto PDO subyacente
     */
    public function getConnection(): PDO
    {
        return $this->connection;
    }

    /**
     * Prepara y ejecuta una consulta con parámetros opcionales.
     *
     * @param  string  $sql
     * @param  array   $params  Parámetros de bind (['col' => valor] o posicionales)
     * @return PDOStatement
     */
    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Retorna todas las filas de la consulta como array asociativo.
     *
     * @param  string $sql
     * @param  array  $params
     * @return array
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * Retorna la primera fila de la consulta o null si no hay resultados.
     *
     * @param  string $sql
     * @param  array  $params
     * @return array|null
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch();
        return $row !== false ? $row : null;
    }

    /**
     * Ejecuta una consulta INSERT/UPDATE/DELETE y retorna el número de filas afectadas.
     *
     * @param  string $sql
     * @param  array  $params
     * @return int    Filas afectadas
     */
    public function execute(string $sql, array $params = []): int
    {
        return $this->query($sql, $params)->rowCount();
    }

    /**
     * Retorna el ID generado por el último INSERT.
     */
    public function lastInsertId(): int
    {
        return (int) $this->connection->lastInsertId();
    }

    /**
     * Inicia una transacción.
     */
    public function beginTransaction(): bool
    {
        return $this->connection->beginTransaction();
    }

    /**
     * Confirma la transacción activa.
     */
    public function commit(): bool
    {
        return $this->connection->commit();
    }

    /**
     * Revierte la transacción activa.
     */
    public function rollback(): bool
    {
        return $this->connection->rollBack();
    }

    /**
     * Helper: ejecuta un bloque dentro de una transacción.
     * Si lanza excepción hace rollback y la re-lanza.
     *
     * @param  callable $callback  function(Database $db): mixed
     * @return mixed               Valor retornado por el callback
     * @throws \Throwable
     */
    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }
}
