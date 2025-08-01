<?php

declare(strict_types=1);

/**
 * Datenbank-Migrations-Runner
 * 
 * Führt alle Migrations in numerischer Reihenfolge aus
 * 
 * @author 2Brands Media GmbH
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

// Umgebungsvariablen laden
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

class MigrationRunner
{
    private PDO $db;
    private string $migrationsPath;
    private string $migrationsTable = 'migrations';

    public function __construct()
    {
        $this->migrationsPath = __DIR__ . '/migrations';
        $this->connectDatabase();
        $this->createMigrationsTable();
    }

    private function connectDatabase(): void
    {
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $_ENV['DB_HOST'],
                $_ENV['DB_PORT'],
                $_ENV['DB_DATABASE'],
                $_ENV['DB_CHARSET']
            );

            $this->db = new PDO(
                $dsn,
                $_ENV['DB_USERNAME'],
                $_ENV['DB_PASSWORD'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                ]
            );

            echo "✓ Datenbankverbindung hergestellt\n";
        } catch (PDOException $e) {
            die("✗ Datenbankverbindung fehlgeschlagen: " . $e->getMessage() . "\n");
        }
    }

    private function createMigrationsTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->migrationsTable} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_migration (migration)
        )";

        $this->db->exec($sql);
        echo "✓ Migrations-Tabelle bereit\n";
    }

    public function run(): void
    {
        $migrations = $this->getPendingMigrations();

        if (empty($migrations)) {
            echo "ℹ Keine ausstehenden Migrations gefunden.\n";
            return;
        }

        echo sprintf("🔄 %d Migration(s) gefunden\n\n", count($migrations));

        foreach ($migrations as $migration) {
            $this->executeMigration($migration);
        }

        echo "\n✅ Alle Migrations erfolgreich ausgeführt!\n";
    }

    private function getPendingMigrations(): array
    {
        // Alle Migration-Dateien finden
        $files = glob($this->migrationsPath . '/*.php');
        sort($files);

        // Bereits ausgeführte Migrations abrufen
        $stmt = $this->db->query("SELECT migration FROM {$this->migrationsTable}");
        $executed = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Nur noch nicht ausgeführte Migrations zurückgeben
        $pending = [];
        foreach ($files as $file) {
            $filename = basename($file);
            if (!in_array($filename, $executed)) {
                $pending[] = $file;
            }
        }

        return $pending;
    }

    private function executeMigration(string $file): void
    {
        $filename = basename($file);
        echo "🔄 Führe aus: {$filename}... ";

        try {
            $this->db->beginTransaction();

            // Migration-Klasse laden und ausführen
            require_once $file;
            
            $className = $this->getClassNameFromFile($file);
            $migration = new $className($this->db);
            
            if (!method_exists($migration, 'up')) {
                throw new Exception("Migration muss eine 'up' Methode haben");
            }

            $migration->up();

            // Migration als ausgeführt markieren
            $stmt = $this->db->prepare(
                "INSERT INTO {$this->migrationsTable} (migration) VALUES (?)"
            );
            $stmt->execute([$filename]);

            $this->db->commit();
            echo "✓\n";

        } catch (Exception $e) {
            $this->db->rollBack();
            echo "✗\n";
            die("Fehler: " . $e->getMessage() . "\n");
        }
    }

    private function getClassNameFromFile(string $file): string
    {
        $filename = basename($file, '.php');
        // Entferne numerischen Präfix (z.B. "001_")
        $parts = explode('_', $filename, 2);
        if (count($parts) > 1) {
            $className = $parts[1];
        } else {
            $className = $filename;
        }
        
        // Konvertiere zu CamelCase
        $className = str_replace('_', '', ucwords($className, '_'));
        
        return $className;
    }

    public function rollback(int $steps = 1): void
    {
        $stmt = $this->db->prepare(
            "SELECT migration FROM {$this->migrationsTable} 
             ORDER BY id DESC LIMIT ?"
        );
        $stmt->execute([$steps]);
        $migrations = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($migrations)) {
            echo "ℹ Keine Migrations zum Zurückrollen gefunden.\n";
            return;
        }

        foreach ($migrations as $migration) {
            $this->rollbackMigration($migration);
        }

        echo "\n✅ Rollback erfolgreich!\n";
    }

    private function rollbackMigration(string $filename): void
    {
        echo "↩ Rolle zurück: {$filename}... ";

        try {
            $this->db->beginTransaction();

            $file = $this->migrationsPath . '/' . $filename;
            require_once $file;
            
            $className = $this->getClassNameFromFile($file);
            $migration = new $className($this->db);
            
            if (!method_exists($migration, 'down')) {
                throw new Exception("Migration muss eine 'down' Methode haben");
            }

            $migration->down();

            // Migration aus der Tabelle entfernen
            $stmt = $this->db->prepare(
                "DELETE FROM {$this->migrationsTable} WHERE migration = ?"
            );
            $stmt->execute([$filename]);

            $this->db->commit();
            echo "✓\n";

        } catch (Exception $e) {
            $this->db->rollBack();
            echo "✗\n";
            die("Fehler: " . $e->getMessage() . "\n");
        }
    }
}

// CLI-Ausführung
if (php_sapi_name() === 'cli') {
    $runner = new MigrationRunner();

    $command = $argv[1] ?? 'up';

    switch ($command) {
        case 'up':
            $runner->run();
            break;
        
        case 'down':
        case 'rollback':
            $steps = isset($argv[2]) ? (int)$argv[2] : 1;
            $runner->rollback($steps);
            break;
        
        default:
            echo "Verwendung: php migrate.php [up|down|rollback] [steps]\n";
            exit(1);
    }
}