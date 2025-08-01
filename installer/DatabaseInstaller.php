<?php

declare(strict_types=1);

namespace Installer;

/**
 * DatabaseInstaller - Führt Datenbank-Setup durch
 * 
 * @author 2Brands Media GmbH
 */
class DatabaseInstaller
{
    private \PDO $pdo;
    private array $config;
    
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->connect();
    }
    
    /**
     * Stellt Datenbankverbindung her
     */
    private function connect(): void
    {
        $dsn = "mysql:host={$this->config['host']};port={$this->config['port']};dbname={$this->config['name']};charset=utf8mb4";
        
        $this->pdo = new \PDO($dsn, $this->config['user'], $this->config['pass'], [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ]);
    }
    
    /**
     * Führt die Installation durch
     */
    public function install(): void
    {
        // Migrations-Dateien ausführen
        $this->runMigrations();
        
        // Basis-Daten einfügen
        $this->seedDatabase();
    }
    
    /**
     * Führt alle Migrationen aus
     */
    private function runMigrations(): void
    {
        $migrationsPath = __DIR__ . '/../database/migrations/';
        
        if (!is_dir($migrationsPath)) {
            throw new \Exception("Migrations-Verzeichnis nicht gefunden: $migrationsPath");
        }
        
        // Migrations-Tabelle erstellen falls nicht vorhanden
        $this->createMigrationsTable();
        
        // Alle Migrations-Dateien laden
        $files = glob($migrationsPath . '*.php');
        sort($files);
        
        foreach ($files as $file) {
            $filename = basename($file);
            
            // Prüfen ob Migration bereits ausgeführt wurde
            if ($this->migrationExists($filename)) {
                continue;
            }
            
            // Migration ausführen
            $this->runMigration($file, $filename);
        }
    }
    
    /**
     * Erstellt die Migrations-Tabelle
     */
    private function createMigrationsTable(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS migrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL,
                executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_migration (migration)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        $this->pdo->exec($sql);
    }
    
    /**
     * Prüft ob eine Migration bereits ausgeführt wurde
     */
    private function migrationExists(string $migration): bool
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM migrations WHERE migration = ?");
        $stmt->execute([$migration]);
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Führt eine einzelne Migration aus
     */
    private function runMigration(string $file, string $filename): void
    {
        // Migration-Datei einbinden
        $migration = include $file;
        
        // Wenn die Datei ein Array mit 'up' Funktion zurückgibt
        if (is_array($migration) && isset($migration['up'])) {
            $migration['up']($this->pdo);
        }
        // Wenn die Datei direkt SQL zurückgibt
        elseif (is_string($migration)) {
            $this->pdo->exec($migration);
        }
        // Wenn es eine PHP-Datei mit SQL-Statements ist
        else {
            // Versuche SQL aus der Datei zu extrahieren
            $content = file_get_contents($file);
            if (preg_match('/\$sql\s*=\s*["\'](.+?)["\']/s', $content, $matches)) {
                $this->pdo->exec($matches[1]);
            }
        }
        
        // Migration als ausgeführt markieren
        $stmt = $this->pdo->prepare("INSERT INTO migrations (migration) VALUES (?)");
        $stmt->execute([$filename]);
    }
    
    /**
     * Fügt Basis-Daten ein
     */
    private function seedDatabase(): void
    {
        // Sprachen einfügen (falls Tabelle existiert)
        try {
            $languages = [
                ['code' => 'de', 'name' => 'Deutsch', 'is_default' => 1],
                ['code' => 'en', 'name' => 'English', 'is_default' => 0],
                ['code' => 'tr', 'name' => 'Türkçe', 'is_default' => 0]
            ];
            
            $stmt = $this->pdo->prepare("INSERT IGNORE INTO languages (code, name, is_default) VALUES (?, ?, ?)");
            foreach ($languages as $lang) {
                $stmt->execute([$lang['code'], $lang['name'], $lang['is_default']]);
            }
        } catch (\Exception $e) {
            // Tabelle existiert möglicherweise nicht - ignorieren
        }
        
        // Standard-Einstellungen (falls Tabelle existiert)
        try {
            $settings = [
                ['key' => 'site_name', 'value' => 'Metropol Portal'],
                ['key' => 'items_per_page', 'value' => '20'],
                ['key' => 'max_stops_per_playlist', 'value' => '20'],
                ['key' => 'default_work_time_minutes', 'value' => '30'],
                ['key' => 'enable_traffic_data', 'value' => '1'],
                ['key' => 'cache_ttl_minutes', 'value' => '60']
            ];
            
            $stmt = $this->pdo->prepare("INSERT IGNORE INTO settings (`key`, `value`) VALUES (?, ?)");
            foreach ($settings as $setting) {
                $stmt->execute([$setting['key'], $setting['value']]);
            }
        } catch (\Exception $e) {
            // Tabelle existiert möglicherweise nicht - ignorieren
        }
    }
    
    /**
     * Erstellt den Admin-Account
     */
    public function createAdmin(array $adminData): void
    {
        // Prüfen ob users Tabelle existiert
        $stmt = $this->pdo->query("SHOW TABLES LIKE 'users'");
        if ($stmt->rowCount() === 0) {
            throw new \Exception("Users-Tabelle nicht gefunden");
        }
        
        // Admin erstellen
        $sql = "INSERT INTO users (username, email, password, role, is_active, created_at) 
                VALUES (:username, :email, :password, 'admin', 1, NOW())";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'username' => $adminData['username'],
            'email' => $adminData['email'],
            'password' => $adminData['password']
        ]);
    }
    
    /**
     * Testet die Datenbankverbindung
     */
    public static function testConnection(array $config): bool
    {
        try {
            $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['name']};charset=utf8mb4";
            $pdo = new \PDO($dsn, $config['user'], $config['pass']);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            
            // Einfacher Test-Query
            $pdo->query("SELECT 1");
            
            return true;
        } catch (\PDOException $e) {
            return false;
        }
    }
    
    /**
     * Gibt Informationen über die Datenbank zurück
     */
    public function getDatabaseInfo(): array
    {
        $info = [];
        
        // MySQL Version
        $stmt = $this->pdo->query("SELECT VERSION() as version");
        $info['mysql_version'] = $stmt->fetch()['version'];
        
        // Tabellen zählen
        $stmt = $this->pdo->query("SHOW TABLES");
        $info['table_count'] = $stmt->rowCount();
        
        // Datenbank-Größe
        $stmt = $this->pdo->prepare("
            SELECT 
                SUM(data_length + index_length) / 1024 / 1024 AS size_mb
            FROM information_schema.tables 
            WHERE table_schema = ?
        ");
        $stmt->execute([$this->config['name']]);
        $info['size_mb'] = round($stmt->fetch()['size_mb'] ?? 0, 2);
        
        return $info;
    }
}