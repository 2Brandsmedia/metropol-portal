<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

/**
 * Datenbank-Verbindungsklasse
 * 
 * @author 2Brands Media GmbH
 */
class Database
{
    private ?PDO $connection = null;
    private Config $config;
    private array $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ];

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Stellt eine Datenbankverbindung her
     */
    public function connect(): PDO
    {
        if ($this->connection === null) {
            try {
                $dsn = sprintf(
                    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                    $this->config->get('database.host'),
                    $this->config->get('database.port'),
                    $this->config->get('database.database'),
                    $this->config->get('database.charset')
                );

                $this->connection = new PDO(
                    $dsn,
                    $this->config->get('database.username'),
                    $this->config->get('database.password'),
                    $this->options
                );

            } catch (PDOException $e) {
                throw new DatabaseException(
                    'Datenbankverbindung fehlgeschlagen: ' . $e->getMessage(),
                    $e->getCode(),
                    $e
                );
            }
        }

        return $this->connection;
    }

    /**
     * Gibt die PDO-Instanz zurück
     */
    public function getPdo(): PDO
    {
        return $this->connect();
    }

    /**
     * Führt eine SELECT-Abfrage aus
     */
    public function select(string $query, array $bindings = []): array
    {
        $statement = $this->connect()->prepare($query);
        $statement->execute($bindings);
        
        return $statement->fetchAll();
    }

    /**
     * Führt eine SELECT-Abfrage aus und gibt die erste Zeile zurück
     */
    public function selectOne(string $query, array $bindings = []): ?array
    {
        $statement = $this->connect()->prepare($query);
        $statement->execute($bindings);
        
        $result = $statement->fetch();
        
        return $result === false ? null : $result;
    }

    /**
     * Führt eine INSERT-Abfrage aus
     */
    public function insert(string $query, array $bindings = []): int
    {
        $statement = $this->connect()->prepare($query);
        $statement->execute($bindings);
        
        return (int) $this->connection->lastInsertId();
    }

    /**
     * Führt eine UPDATE-Abfrage aus
     */
    public function update(string $query, array $bindings = []): int
    {
        $statement = $this->connect()->prepare($query);
        $statement->execute($bindings);
        
        return $statement->rowCount();
    }

    /**
     * Führt eine DELETE-Abfrage aus
     */
    public function delete(string $query, array $bindings = []): int
    {
        $statement = $this->connect()->prepare($query);
        $statement->execute($bindings);
        
        return $statement->rowCount();
    }

    /**
     * Führt eine beliebige Abfrage aus
     */
    public function statement(string $query, array $bindings = []): bool
    {
        $statement = $this->connect()->prepare($query);
        
        return $statement->execute($bindings);
    }

    /**
     * Startet eine Transaktion
     */
    public function beginTransaction(): void
    {
        $this->connect()->beginTransaction();
    }

    /**
     * Bestätigt eine Transaktion
     */
    public function commit(): void
    {
        $this->connect()->commit();
    }

    /**
     * Macht eine Transaktion rückgängig
     */
    public function rollBack(): void
    {
        $this->connect()->rollBack();
    }

    /**
     * Führt eine Closure in einer Transaktion aus
     */
    public function transaction(\Closure $callback)
    {
        $this->beginTransaction();

        try {
            $result = $callback($this);
            $this->commit();
            
            return $result;
        } catch (\Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    /**
     * Escaped einen String für sichere Verwendung in Queries
     */
    public function quote(string $value): string
    {
        return $this->connect()->quote($value);
    }

    /**
     * Schließt die Datenbankverbindung
     */
    public function disconnect(): void
    {
        $this->connection = null;
    }
}

/**
 * Datenbank-Exception
 */
class DatabaseException extends \Exception
{
}