<?php
/**
 * Debug-Datei für firmenpro.de
 * Diese Datei hilft bei der Fehlerdiagnose
 * 
 * @author 2Brands Media GmbH
 */

// Maximale Fehlerausgabe aktivieren
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

// Header setzen
header('Content-Type: text/plain; charset=UTF-8');

echo "=== METROPOL PORTAL DEBUG INFO ===\n\n";

// PHP Version
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "\n";
echo "Document Root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'Unknown') . "\n";
echo "Script Filename: " . ($_SERVER['SCRIPT_FILENAME'] ?? 'Unknown') . "\n\n";

// Wichtige PHP Extensions prüfen
echo "=== PHP EXTENSIONS ===\n";
$required_extensions = ['pdo', 'pdo_mysql', 'mbstring', 'json', 'openssl', 'curl'];
foreach ($required_extensions as $ext) {
    echo $ext . ": " . (extension_loaded($ext) ? "✓ Loaded" : "✗ Missing") . "\n";
}
echo "\n";

// Dateisystem-Checks
echo "=== FILE SYSTEM CHECKS ===\n";
$paths_to_check = [
    'vendor/autoload.php' => 'Composer Autoloader',
    '.env' => 'Environment File',
    'public/index.php' => 'Main Entry Point',
    'installer/InstallAgent.php' => 'Installer',
    'logs/' => 'Logs Directory',
    'storage/' => 'Storage Directory'
];

foreach ($paths_to_check as $path => $description) {
    $full_path = __DIR__ . '/' . $path;
    $exists = file_exists($full_path);
    $readable = $exists && is_readable($full_path);
    $writable = $exists && is_writable($full_path);
    
    echo sprintf(
        "%-30s: %s %s %s\n",
        $description,
        $exists ? "✓ Exists" : "✗ Missing",
        $readable ? "✓ Readable" : "✗ Not Readable",
        $writable ? "✓ Writable" : "✗ Not Writable"
    );
}
echo "\n";

// Umgebungsvariablen testen (ohne sensitive Daten)
echo "=== ENVIRONMENT CHECK ===\n";
if (file_exists(__DIR__ . '/.env')) {
    echo ".env file found\n";
    
    // Versuche .env zu laden
    if (file_exists(__DIR__ . '/vendor/autoload.php')) {
        try {
            require_once __DIR__ . '/vendor/autoload.php';
            $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__);
            $dotenv->load();
            echo "Environment loaded successfully\n";
            echo "APP_ENV: " . ($_ENV['APP_ENV'] ?? 'not set') . "\n";
            echo "APP_DEBUG: " . ($_ENV['APP_DEBUG'] ?? 'not set') . "\n";
            echo "APP_URL: " . ($_ENV['APP_URL'] ?? 'not set') . "\n";
        } catch (Exception $e) {
            echo "Error loading environment: " . $e->getMessage() . "\n";
        }
    } else {
        echo "Composer autoloader not found - cannot load .env\n";
    }
} else {
    echo ".env file NOT found - installation may be required\n";
}
echo "\n";

// Datenbankverbindung testen (wenn .env geladen)
if (isset($_ENV['DB_HOST'])) {
    echo "=== DATABASE CONNECTION TEST ===\n";
    try {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $_ENV['DB_HOST'] ?? 'localhost',
            $_ENV['DB_PORT'] ?? '3306',
            $_ENV['DB_DATABASE'] ?? '',
            $_ENV['DB_CHARSET'] ?? 'utf8mb4'
        );
        
        $pdo = new PDO($dsn, $_ENV['DB_USERNAME'] ?? '', $_ENV['DB_PASSWORD'] ?? '');
        echo "✓ Database connection successful\n";
        
        // Tabellen prüfen
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        echo "Tables found: " . count($tables) . "\n";
        
    } catch (PDOException $e) {
        echo "✗ Database connection failed: " . $e->getMessage() . "\n";
    }
} else {
    echo "=== DATABASE CONNECTION TEST ===\n";
    echo "Cannot test - database credentials not loaded\n";
}
echo "\n";

// Installation Status
echo "=== INSTALLATION STATUS ===\n";
if (file_exists(__DIR__ . '/.env') && file_exists(__DIR__ . '/vendor/autoload.php')) {
    echo "✓ Portal appears to be installed\n";
} else {
    echo "✗ Portal needs installation\n";
    echo "  Visit: " . $_SERVER['HTTP_HOST'] . "/install.php\n";
}

echo "\n=== END DEBUG INFO ===\n";