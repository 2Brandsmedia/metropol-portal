<?php

declare(strict_types=1);

/**
 * Simple Database Wrapper
 * 
 * PDO-Wrapper ohne externe Dependencies
 * 
 * @author 2Brands Media GmbH
 */
class Database
{
    private static ?PDO $instance = null;
    private static array $config = [];
    
    /**
     * Konfiguriert die Datenbankverbindung
     */
    public static function configure(array $config): void
    {
        self::$config = array_merge([
            'host' => 'localhost',
            'port' => 3306,
            'database' => '',
            'username' => '',
            'password' => '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'options' => []
        ], $config);
    }
    
    /**
     * Gibt die PDO-Instanz zurück
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            self::connect();
        }
        
        return self::$instance;
    }
    
    /**
     * Stellt die Datenbankverbindung her
     */
    private static function connect(): void
    {
        // Konfiguration aus Env laden falls nicht gesetzt
        if (empty(self::$config)) {
            self::configure([
                'host' => Env::get('DB_HOST', 'localhost'),
                'port' => Env::get('DB_PORT', '3306'),
                'database' => Env::get('DB_DATABASE', ''),
                'username' => Env::get('DB_USERNAME', ''),
                'password' => Env::get('DB_PASSWORD', '')
            ]);
        }
        
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            self::$config['host'],
            self::$config['port'],
            self::$config['database'],
            self::$config['charset']
        );
        
        $options = array_merge([
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . self::$config['charset'] . " COLLATE " . self::$config['collation']
        ], self::$config['options']);
        
        try {
            self::$instance = new PDO(
                $dsn,
                self::$config['username'],
                self::$config['password'],
                $options
            );
        } catch (PDOException $e) {
            throw new Exception('Datenbankverbindung fehlgeschlagen: ' . $e->getMessage());
        }
    }
    
    /**
     * Führt eine Query aus
     */
    public static function query(string $sql, array $params = []): PDOStatement
    {
        $pdo = self::getInstance();
        
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            throw new Exception('Query fehlgeschlagen: ' . $e->getMessage());
        }
    }
    
    /**
     * Holt einen einzelnen Datensatz
     */
    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = self::query($sql, $params);
        $result = $stmt->fetch();
        return $result !== false ? $result : null;
    }
    
    /**
     * Holt alle Datensätze
     */
    public static function fetchAll(string $sql, array $params = []): array
    {
        $stmt = self::query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Holt einen einzelnen Wert
     */
    public static function fetchValue(string $sql, array $params = [])
    {
        $stmt = self::query($sql, $params);
        return $stmt->fetchColumn();
    }
    
    /**
     * Fügt einen Datensatz ein
     */
    public static function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_map(fn($col) => ':' . $col, $columns);
        
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );
        
        self::query($sql, $data);
        return (int) self::getInstance()->lastInsertId();
    }
    
    /**
     * Aktualisiert Datensätze
     */
    public static function update(string $table, array $data, array $where): int
    {
        $setParts = [];
        $params = [];
        
        foreach ($data as $column => $value) {
            $setParts[] = "$column = :set_$column";
            $params["set_$column"] = $value;
        }
        
        $whereParts = [];
        foreach ($where as $column => $value) {
            $whereParts[] = "$column = :where_$column";
            $params["where_$column"] = $value;
        }
        
        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s',
            $table,
            implode(', ', $setParts),
            implode(' AND ', $whereParts)
        );
        
        $stmt = self::query($sql, $params);
        return $stmt->rowCount();
    }
    
    /**
     * Löscht Datensätze
     */
    public static function delete(string $table, array $where): int
    {
        $whereParts = [];
        $params = [];
        
        foreach ($where as $column => $value) {
            $whereParts[] = "$column = :$column";
            $params[$column] = $value;
        }
        
        $sql = sprintf(
            'DELETE FROM %s WHERE %s',
            $table,
            implode(' AND ', $whereParts)
        );
        
        $stmt = self::query($sql, $params);
        return $stmt->rowCount();
    }
    
    /**
     * Startet eine Transaktion
     */
    public static function beginTransaction(): bool
    {
        return self::getInstance()->beginTransaction();
    }
    
    /**
     * Committed eine Transaktion
     */
    public static function commit(): bool
    {
        return self::getInstance()->commit();
    }
    
    /**
     * Rollt eine Transaktion zurück
     */
    public static function rollback(): bool
    {
        return self::getInstance()->rollBack();
    }
    
    /**
     * Prüft ob eine Tabelle existiert
     */
    public static function tableExists(string $table): bool
    {
        $sql = "SHOW TABLES LIKE ?";
        $result = self::fetchValue($sql, [$table]);
        return $result !== false;
    }
    
    /**
     * Schließt die Verbindung
     */
    public static function close(): void
    {
        self::$instance = null;
    }
}