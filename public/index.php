<?php

declare(strict_types=1);

/**
 * Metropol Portal - Entry Point
 * 
 * @author 2Brands Media GmbH
 */

// Autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Umgebungsvariablen laden
$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Fehlerbehandlung
error_reporting(E_ALL);
ini_set('display_errors', $_ENV['APP_DEBUG'] === 'true' ? '1' : '0');
ini_set('display_startup_errors', $_ENV['APP_DEBUG'] === 'true' ? '1' : '0');

// Zeitzone setzen
date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Europe/Berlin');

// Application Bootstrap
use App\Core\Application;

$app = Application::getInstance();

// Routen laden
require_once __DIR__ . '/../routes/web.php';
require_once __DIR__ . '/../routes/api.php';

// 404 Handler
$app->getRouter()->setNotFoundHandler(function() {
    $response = new \App\Core\Response();
    
    // Für API-Anfragen JSON zurückgeben
    if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') === 0) {
        return $response->json([
            'error' => 'Route nicht gefunden'
        ], 404);
    }
    
    // Für Web-Anfragen 404-Seite anzeigen
    $response->setStatusCode(404);
    
    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <title>404 - Nicht gefunden</title>
        <script src='https://cdn.tailwindcss.com'></script>
    </head>
    <body class='bg-gray-100 flex items-center justify-center h-screen'>
        <div class='text-center'>
            <h1 class='text-6xl font-bold text-gray-800'>404</h1>
            <p class='text-xl text-gray-600 mt-4'>Seite nicht gefunden</p>
            <a href='/' class='mt-6 inline-block bg-indigo-600 text-white px-6 py-3 rounded hover:bg-indigo-700'>Zur Startseite</a>
        </div>
    </body>
    </html>
    ";
    
    $response->setContent($html);
    return $response;
});

// Application ausführen
$app->run();