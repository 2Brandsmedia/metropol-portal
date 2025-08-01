#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Deployment-Skript für All-Inkl Hosting
 * 
 * Achtung: Enthält sensible Daten - nach Deployment löschen!
 * 
 * @author 2Brands Media GmbH
 */

echo "===============================================\n";
echo "Metropol Portal - Deployment zu All-Inkl\n";
echo "===============================================\n\n";

// FTP-Konfiguration
$ftpConfig = [
    'server' => 'w019e3c7.kasserver.com',
    'username' => 'w019e3c7',
    'password' => '2Brands2025!',
    'port' => 21,
    'passive' => true,
    'ssl' => false,
    'timeout' => 90,
    'root' => '/w019e3c7/firmenpro.de'
];

// Datenbank-Konfiguration
$dbConfig = [
    'host' => 'localhost',
    'name' => 'd0446399',
    'user' => 'd0446399',
    'password' => '2Brands2025!',
    'charset' => 'utf8mb4'
];

// Projekt-Pfade
$localRoot = dirname(__DIR__);
$remoteRoot = $ftpConfig['root'];

// Verzeichnisse die erstellt werden müssen
$directories = [
    'cache',
    'logs',
    'uploads',
    'temp',
    'backups'
];

// Dateien/Ordner die NICHT hochgeladen werden
$excludePatterns = [
    '.git',
    '.gitignore',
    '.github',
    '.DS_Store',
    'node_modules',
    'tests',
    'docs',
    '*.log',
    '*.md',
    'deploy-to-allinkl.php', // Dieses Skript selbst
    'tsconfig.json',
    'package.json',
    'package-lock.json',
    'composer.lock',
    'phpunit.xml',
    'phpcs.xml',
    'phpstan.neon'
];

/**
 * FTP-Verbindung herstellen
 */
function connectFTP(array $config) {
    echo "Verbinde zu FTP-Server {$config['server']}...\n";
    
    $conn = ftp_connect($config['server'], $config['port'], $config['timeout']);
    if (!$conn) {
        die("Fehler: Konnte keine Verbindung zum FTP-Server herstellen\n");
    }
    
    if (!ftp_login($conn, $config['username'], $config['password'])) {
        die("Fehler: FTP-Login fehlgeschlagen\n");
    }
    
    if ($config['passive']) {
        ftp_pasv($conn, true);
    }
    
    echo "FTP-Verbindung erfolgreich!\n\n";
    return $conn;
}

/**
 * Verzeichnis rekursiv hochladen
 */
function uploadDirectory($conn, $localDir, $remoteDir, $excludePatterns = []) {
    // Remote-Verzeichnis erstellen
    @ftp_mkdir($conn, $remoteDir);
    
    $files = scandir($localDir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        
        // Prüfen ob Datei/Ordner ausgeschlossen werden soll
        $skip = false;
        foreach ($excludePatterns as $pattern) {
            if (fnmatch($pattern, $file)) {
                $skip = true;
                break;
            }
        }
        if ($skip) {
            continue;
        }
        
        $localPath = $localDir . '/' . $file;
        $remotePath = $remoteDir . '/' . $file;
        
        if (is_dir($localPath)) {
            echo "Erstelle Verzeichnis: $remotePath\n";
            uploadDirectory($conn, $localPath, $remotePath, $excludePatterns);
        } else {
            echo "Upload: $file -> $remotePath\n";
            if (!ftp_put($conn, $remotePath, $localPath, FTP_BINARY)) {
                echo "WARNUNG: Konnte $file nicht hochladen\n";
            }
        }
    }
}

/**
 * Datenbank-Setup
 */
function setupDatabase(array $config) {
    echo "\n===============================================\n";
    echo "Datenbank-Setup\n";
    echo "===============================================\n\n";
    
    try {
        $dsn = "mysql:host={$config['host']};charset={$config['charset']}";
        $pdo = new PDO($dsn, $config['user'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        
        // Datenbank verwenden
        $pdo->exec("USE `{$config['name']}`");
        echo "Datenbank {$config['name']} ausgewählt\n";
        
        // Migrations-Dateien ausführen
        $migrationsPath = dirname(__DIR__) . '/database/migrations';
        $migrations = glob($migrationsPath . '/*.php');
        sort($migrations);
        
        echo "\nFühre Migrationen aus...\n";
        foreach ($migrations as $migration) {
            $name = basename($migration);
            echo "Migration: $name\n";
            
            // Migration ausführen
            require $migration;
            $className = 'Create' . str_replace(['_', '.php'], ['', ''], ucwords(substr($name, 4), '_'));
            if (class_exists($className)) {
                $instance = new $className($pdo);
                if (method_exists($instance, 'up')) {
                    $instance->up();
                }
            }
        }
        
        // Admin-Benutzer anlegen
        echo "\nErstelle Admin-Benutzer...\n";
        $adminPassword = password_hash('Admin2025!', PASSWORD_BCRYPT, ['cost' => 12]);
        $pdo->exec("
            INSERT IGNORE INTO users (name, email, password, role, is_active, created_at, updated_at)
            VALUES ('Administrator', 'admin@firmenpro.de', '$adminPassword', 'admin', 1, NOW(), NOW())
        ");
        
        echo "\nDatenbank-Setup erfolgreich!\n";
        echo "Admin-Login: admin@firmenpro.de / Admin2025!\n";
        echo "WICHTIG: Bitte ändern Sie das Passwort nach dem ersten Login!\n";
        
    } catch (Exception $e) {
        die("Datenbank-Fehler: " . $e->getMessage() . "\n");
    }
}

/**
 * Hauptprogramm
 */
try {
    // FTP-Verbindung
    $ftp = connectFTP($ftpConfig);
    
    // Basis-Verzeichnisse erstellen
    echo "Erstelle Basis-Verzeichnisse...\n";
    foreach ($directories as $dir) {
        $remotePath = $remoteRoot . '/' . $dir;
        @ftp_mkdir($ftp, $remotePath);
        echo "Verzeichnis erstellt: $remotePath\n";
    }
    echo "\n";
    
    // Projekt-Dateien hochladen
    echo "Lade Projekt-Dateien hoch...\n";
    echo "===============================================\n";
    
    // Einzelne Verzeichnisse hochladen
    $uploadDirs = ['src', 'public', 'config', 'database', 'templates', 'lang', 'routes', 'scripts'];
    foreach ($uploadDirs as $dir) {
        if (is_dir($localRoot . '/' . $dir)) {
            echo "\nUpload Verzeichnis: $dir\n";
            uploadDirectory($ftp, $localRoot . '/' . $dir, $remoteRoot . '/' . $dir, $excludePatterns);
        }
    }
    
    // Root-Dateien hochladen
    $rootFiles = ['composer.json'];
    foreach ($rootFiles as $file) {
        if (file_exists($localRoot . '/' . $file)) {
            echo "\nUpload Root-Datei: $file\n";
            ftp_put($ftp, $remoteRoot . '/' . $file, $localRoot . '/' . $file, FTP_BINARY);
        }
    }
    
    // Datei-Berechtigungen setzen
    echo "\nSetze Datei-Berechtigungen...\n";
    @ftp_chmod($ftp, 0755, $remoteRoot . '/cache');
    @ftp_chmod($ftp, 0755, $remoteRoot . '/logs');
    @ftp_chmod($ftp, 0755, $remoteRoot . '/uploads');
    @ftp_chmod($ftp, 0755, $remoteRoot . '/temp');
    
    // FTP-Verbindung schließen
    ftp_close($ftp);
    echo "\nFTP-Upload abgeschlossen!\n";
    
    // Datenbank einrichten
    setupDatabase($dbConfig);
    
    echo "\n===============================================\n";
    echo "Deployment erfolgreich abgeschlossen!\n";
    echo "===============================================\n\n";
    echo "Nächste Schritte:\n";
    echo "1. Öffnen Sie https://firmenpro.de\n";
    echo "2. Loggen Sie sich mit admin@firmenpro.de / Admin2025! ein\n";
    echo "3. Ändern Sie das Admin-Passwort\n";
    echo "4. Konfigurieren Sie E-Mail-Einstellungen\n";
    echo "5. Richten Sie Cron-Jobs ein\n";
    echo "6. LÖSCHEN SIE DIESES DEPLOYMENT-SKRIPT!\n\n";
    
} catch (Exception $e) {
    die("\nFEHLER: " . $e->getMessage() . "\n");
}