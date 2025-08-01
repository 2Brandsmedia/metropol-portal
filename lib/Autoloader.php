<?php

declare(strict_types=1);

/**
 * Simple PSR-4 Autoloader
 * 
 * Lädt automatisch PHP-Klassen ohne Composer
 * 
 * @author 2Brands Media GmbH
 */
class Autoloader
{
    private static array $prefixes = [];
    
    /**
     * Registriert den Autoloader
     */
    public static function register(): void
    {
        spl_autoload_register([self::class, 'loadClass']);
        
        // Standard-Namespaces registrieren
        self::addNamespace('App\\', __DIR__ . '/../src/');
        self::addNamespace('Installer\\', __DIR__ . '/../installer/');
    }
    
    /**
     * Fügt einen Namespace hinzu
     */
    public static function addNamespace(string $prefix, string $baseDir): void
    {
        // Normalisiere Namespace-Prefix
        $prefix = trim($prefix, '\\') . '\\';
        
        // Normalisiere Basis-Verzeichnis
        $baseDir = rtrim($baseDir, DIRECTORY_SEPARATOR) . '/';
        
        // Initialisiere Array falls nicht vorhanden
        if (!isset(self::$prefixes[$prefix])) {
            self::$prefixes[$prefix] = [];
        }
        
        // Füge Basis-Verzeichnis hinzu
        array_push(self::$prefixes[$prefix], $baseDir);
    }
    
    /**
     * Lädt eine Klasse
     */
    public static function loadClass(string $class): void
    {
        // Durchsuche alle registrierten Namespaces
        foreach (self::$prefixes as $prefix => $dirs) {
            // Prüfe ob Klasse mit Prefix beginnt
            if (strpos($class, $prefix) === 0) {
                // Entferne Prefix von Klasse
                $relativeClass = substr($class, strlen($prefix));
                
                // Ersetze Namespace-Separator mit Directory-Separator
                $file = str_replace('\\', '/', $relativeClass) . '.php';
                
                // Durchsuche alle Verzeichnisse
                foreach ($dirs as $dir) {
                    $path = $dir . $file;
                    
                    // Wenn Datei existiert, lade sie
                    if (file_exists($path)) {
                        require $path;
                        return;
                    }
                }
            }
        }
    }
    
    /**
     * Lädt eine Datei wenn sie existiert
     */
    public static function loadFile(string $file): bool
    {
        if (file_exists($file)) {
            require $file;
            return true;
        }
        return false;
    }
}

// Registriere Autoloader automatisch
Autoloader::register();