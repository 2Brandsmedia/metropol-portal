<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Konfigurations-Manager
 * 
 * @author 2Brands Media GmbH
 */
class Config
{
    private array $config = [];

    public function __construct()
    {
        $this->loadEnvironment();
        $this->loadConfigFiles();
    }

    private function loadEnvironment(): void
    {
        // Umgebungsvariablen sind bereits durch Composer geladen
        // Hier können wir sie in die Config übernehmen
        $this->config['app'] = [
            'name' => $_ENV['APP_NAME'] ?? 'Metropol Portal',
            'env' => $_ENV['APP_ENV'] ?? 'production',
            'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'url' => $_ENV['APP_URL'] ?? 'http://localhost',
            'timezone' => $_ENV['APP_TIMEZONE'] ?? 'Europe/Berlin',
            'maintenance_file' => dirname(__DIR__, 2) . '/storage/.maintenance'
        ];

        $this->config['database'] = [
            'host' => $_ENV['DB_HOST'] ?? 'localhost',
            'port' => $_ENV['DB_PORT'] ?? '3306',
            'database' => $_ENV['DB_DATABASE'] ?? 'metropol_portal',
            'username' => $_ENV['DB_USERNAME'] ?? 'root',
            'password' => $_ENV['DB_PASSWORD'] ?? '',
            'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
            'collation' => $_ENV['DB_COLLATION'] ?? 'utf8mb4_unicode_ci'
        ];

        $this->config['jwt'] = [
            'secret' => $_ENV['JWT_SECRET'] ?? 'change-this-secret',
            'expire' => (int) ($_ENV['JWT_EXPIRE'] ?? 86400),
            'algorithm' => $_ENV['JWT_ALGORITHM'] ?? 'HS256'
        ];

        $this->config['session'] = [
            'lifetime' => (int) ($_ENV['SESSION_LIFETIME'] ?? 120),
            'secure' => filter_var($_ENV['SESSION_SECURE'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'httponly' => filter_var($_ENV['SESSION_HTTPONLY'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'samesite' => $_ENV['SESSION_SAMESITE'] ?? 'lax'
        ];

        $this->config['cors'] = [
            'allowed_origins' => explode(',', $_ENV['CORS_ALLOWED_ORIGINS'] ?? '*'),
            'allowed_methods' => explode(',', $_ENV['CORS_ALLOWED_METHODS'] ?? 'GET,POST,PUT,DELETE,OPTIONS'),
            'allowed_headers' => explode(',', $_ENV['CORS_ALLOWED_HEADERS'] ?? 'Content-Type,Authorization,X-Requested-With')
        ];

        $this->config['security'] = [
            'force_https' => filter_var($_ENV['FORCE_HTTPS'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'csp_enabled' => filter_var($_ENV['CSP_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'hsts_enabled' => filter_var($_ENV['HSTS_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'hsts_max_age' => (int) ($_ENV['HSTS_MAX_AGE'] ?? 31536000)
        ];

        $this->config['api'] = [
            'ors_key' => $_ENV['ORS_API_KEY'] ?? '',
            'ors_url' => $_ENV['ORS_API_URL'] ?? 'https://api.openrouteservice.org/v2',
            'rate_limit' => (int) ($_ENV['RATE_LIMIT_PER_MINUTE'] ?? 60)
        ];
        
        $this->config['google'] = [
            'maps_api_key' => $_ENV['GOOGLE_MAPS_API_KEY'] ?? '',
            'traffic_enabled' => filter_var($_ENV['GOOGLE_MAPS_TRAFFIC'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'departure_time' => $_ENV['GOOGLE_MAPS_DEPARTURE_TIME'] ?? 'now',
            'traffic_model' => $_ENV['GOOGLE_MAPS_TRAFFIC_MODEL'] ?? 'best_guess',
            'alternatives' => filter_var($_ENV['GOOGLE_MAPS_ALTERNATIVES'] ?? true, FILTER_VALIDATE_BOOLEAN)
        ];

        $this->config['cache'] = [
            'driver' => $_ENV['CACHE_DRIVER'] ?? 'file',
            'lifetime' => (int) ($_ENV['CACHE_LIFETIME'] ?? 3600)
        ];
    }

    private function loadConfigFiles(): void
    {
        $configPath = dirname(__DIR__, 2) . '/config';
        
        if (!is_dir($configPath)) {
            return;
        }

        $files = glob($configPath . '/*.php');
        
        foreach ($files as $file) {
            $key = basename($file, '.php');
            $config = require $file;
            
            if (is_array($config)) {
                $this->config[$key] = array_merge($this->config[$key] ?? [], $config);
            }
        }
    }

    /**
     * Holt einen Konfigurationswert
     */
    public function get(string $key, $default = null)
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Setzt einen Konfigurationswert
     */
    public function set(string $key, $value): void
    {
        $keys = explode('.', $key);
        $config = &$this->config;

        foreach ($keys as $i => $k) {
            if ($i === count($keys) - 1) {
                $config[$k] = $value;
            } else {
                if (!isset($config[$k]) || !is_array($config[$k])) {
                    $config[$k] = [];
                }
                $config = &$config[$k];
            }
        }
    }

    /**
     * Prüft ob ein Konfigurationswert existiert
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * Gibt alle Konfigurationswerte zurück
     */
    public function all(): array
    {
        return $this->config;
    }
}