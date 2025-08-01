<?php

declare(strict_types=1);

/**
 * Simple Environment Variable Loader
 * 
 * Lädt .env Dateien ohne externe Dependencies
 * 
 * @author 2Brands Media GmbH
 */
class Env
{
    private static bool $loaded = false;
    
    /**
     * Lädt Umgebungsvariablen aus .env Datei
     */
    public static function load(string $path = null): void
    {
        if (self::$loaded) {
            return;
        }
        
        if ($path === null) {
            $path = self::findEnvFile();
        }
        
        if (!file_exists($path)) {
            return;
        }
        
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Ignoriere Kommentare
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            
            // Parse KEY=VALUE
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                
                $key = trim($key);
                $value = trim($value);
                
                // Entferne Anführungszeichen
                $value = trim($value, '"\'');
                
                // Setze Umgebungsvariable
                putenv("$key=$value");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
        
        self::$loaded = true;
    }
    
    /**
     * Sucht .env Datei im Projekt
     */
    private static function findEnvFile(): string
    {
        $paths = [
            __DIR__ . '/../.env',
            dirname($_SERVER['SCRIPT_FILENAME']) . '/../.env',
            getcwd() . '/.env'
        ];
        
        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        
        return __DIR__ . '/../.env';
    }
    
    /**
     * Holt einen Umgebungswert
     */
    public static function get(string $key, $default = null)
    {
        if (!self::$loaded) {
            self::load();
        }
        
        // Prüfe verschiedene Quellen
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }
        
        if (isset($_SERVER[$key])) {
            return $_SERVER[$key];
        }
        
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }
        
        return $default;
    }
    
    /**
     * Prüft ob eine Umgebungsvariable existiert
     */
    public static function has(string $key): bool
    {
        if (!self::$loaded) {
            self::load();
        }
        
        return isset($_ENV[$key]) || isset($_SERVER[$key]) || getenv($key) !== false;
    }
    
    /**
     * Setzt eine Umgebungsvariable
     */
    public static function set(string $key, string $value): void
    {
        putenv("$key=$value");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
    
    /**
     * Lädt Umgebungsvariablen neu
     */
    public static function reload(): void
    {
        self::$loaded = false;
        self::load();
    }
    
    /**
     * Erstellt eine .env Datei
     */
    public static function create(array $variables, string $path = null): bool
    {
        if ($path === null) {
            $path = __DIR__ . '/../.env';
        }
        
        $content = '';
        foreach ($variables as $key => $value) {
            // Escape Werte mit Leerzeichen
            if (strpos($value, ' ') !== false) {
                $value = '"' . $value . '"';
            }
            $content .= "$key=$value\n";
        }
        
        return file_put_contents($path, $content) !== false;
    }
}