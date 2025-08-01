<?php

declare(strict_types=1);

/**
 * Produktions-Konfiguration für All-Inkl Hosting
 * 
 * @author 2Brands Media GmbH
 */

return [
    // Datenbank-Konfiguration
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'd0446399',
        'user' => 'd0446399',
        'password' => '2Brands2025!',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'"
        ]
    ],
    
    // Anwendungs-Einstellungen
    'app' => [
        'name' => 'Metropol Portal',
        'env' => 'production',
        'debug' => false,
        'url' => 'https://firmenpro.de',
        'timezone' => 'Europe/Berlin',
        'locale' => 'de',
        'available_locales' => ['de', 'en', 'tr'],
        'version' => '1.0.0'
    ],
    
    // Session-Konfiguration
    'session' => [
        'name' => 'metropol_session',
        'lifetime' => 7200, // 2 Stunden
        'path' => '/',
        'domain' => '.firmenpro.de',
        'secure' => true, // HTTPS only
        'httponly' => true,
        'samesite' => 'Lax'
    ],
    
    // API-Konfiguration
    'api' => [
        'google_maps_key' => 'AIzaSyBi2JVb9KDfyz5SCvO5EfNXKvJpxLNCkF0',
        'ors_key' => '', // Optional - später konfigurierbar
        'rate_limits' => [
            'google_maps' => [
                'daily' => 25000,
                'per_second' => 50
            ],
            'nominatim' => [
                'per_second' => 1,
                'delay_ms' => 1000
            ]
        ]
    ],
    
    // Geocoding-Einstellungen
    'geocoding' => [
        'nominatim_url' => 'https://nominatim.openstreetmap.org',
        'user_agent' => 'MetropolPortal/1.0 (https://firmenpro.de)',
        'delay' => 1000, // Millisekunden zwischen Requests
        'timeout' => 5000, // Request Timeout in Millisekunden
        'cache_lifetime' => 2592000 // 30 Tage
    ],
    
    // Cache-Konfiguration
    'cache' => [
        'driver' => 'database', // Nutzt MySQL cache Tabelle
        'prefix' => 'metropol_',
        'default_ttl' => 3600, // 1 Stunde
        'cleanup_probability' => 0.01 // 1% Chance bei jedem Request
    ],
    
    // Logging-Konfiguration
    'logging' => [
        'path' => '/w019e3c7/firmenpro.de/logs',
        'level' => 'warning', // error, warning, info, debug
        'max_files' => 30, // 30 Tage aufbewahren
        'permissions' => 0664
    ],
    
    // E-Mail-Konfiguration (deaktiviert - wird später konfiguriert)
    'mail' => [
        'enabled' => false,
        'driver' => 'log', // Schreibt E-Mails in Log-Dateien
        'from' => [
            'address' => 'noreply@firmenpro.de',
            'name' => 'Metropol Portal'
        ]
    ],
    
    // Sicherheits-Einstellungen
    'security' => [
        'bcrypt_rounds' => 12,
        'csrf_token_name' => '_token',
        'rate_limit' => [
            'login' => [
                'max_attempts' => 5,
                'decay_minutes' => 15,
                'block_duration' => 60
            ]
        ]
    ],
    
    // Performance-Ziele
    'performance' => [
        'targets' => [
            'login' => 100, // ms
            'api_response' => 200, // ms
            'route_calculation' => 300, // ms
            'page_load' => 1000 // ms
        ],
        'monitoring' => [
            'enabled' => true,
            'sample_rate' => 0.1 // 10% der Requests tracken
        ]
    ],
    
    // Upload-Einstellungen
    'upload' => [
        'max_size' => 10485760, // 10 MB
        'allowed_types' => ['jpg', 'jpeg', 'png', 'gif', 'pdf'],
        'path' => '/w019e3c7/firmenpro.de/uploads'
    ],
    
    // Pfade (absolut für All-Inkl)
    'paths' => [
        'root' => '/w019e3c7/firmenpro.de',
        'public' => '/w019e3c7/firmenpro.de/public',
        'cache' => '/w019e3c7/firmenpro.de/cache',
        'logs' => '/w019e3c7/firmenpro.de/logs',
        'uploads' => '/w019e3c7/firmenpro.de/uploads',
        'temp' => '/w019e3c7/firmenpro.de/temp'
    ]
];