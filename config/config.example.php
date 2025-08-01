<?php

declare(strict_types=1);

/**
 * Beispiel-Konfiguration - Kopieren Sie diese zu config.php und passen Sie an
 * 
 * @author 2Brands Media GmbH
 */

return [
    // Datenbank-Konfiguration
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'your_database',
        'user' => 'your_user',
        'password' => 'your_password',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci'
    ],
    
    // Anwendungs-Einstellungen
    'app' => [
        'name' => 'Metropol Portal',
        'env' => 'development', // production, development, testing
        'debug' => true,
        'url' => 'http://localhost',
        'timezone' => 'Europe/Berlin',
        'locale' => 'de'
    ],
    
    // API-Keys
    'api' => [
        'google_maps_key' => 'YOUR_GOOGLE_MAPS_API_KEY'
    ]
];