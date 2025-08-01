<?php

declare(strict_types=1);

/**
 * Metropol Portal - Entry Point (Standalone Version)
 * 
 * Diese Version funktioniert ohne Composer
 * 
 * @author 2Brands Media GmbH
 */

// Basis-Pfad definieren
define('BASE_PATH', dirname(__DIR__));

// Prüfe ob Installation notwendig
if (!file_exists(BASE_PATH . '/.env') && file_exists(BASE_PATH . '/install.php')) {
    header('Location: ../install.php');
    exit;
}

// Eigene Libraries laden
require_once BASE_PATH . '/lib/Autoloader.php';
require_once BASE_PATH . '/lib/Env.php';
require_once BASE_PATH . '/lib/Database.php';
require_once BASE_PATH . '/lib/Router.php';
require_once BASE_PATH . '/lib/Session.php';

// Umgebungsvariablen laden
Env::load(BASE_PATH);

// Fehlerbehandlung
error_reporting(E_ALL);
ini_set('display_errors', Env::get('APP_DEBUG', 'false') === 'true' ? '1' : '0');
ini_set('display_startup_errors', Env::get('APP_DEBUG', 'false') === 'true' ? '1' : '0');

// Zeitzone setzen
date_default_timezone_set(Env::get('APP_TIMEZONE', 'Europe/Berlin'));

// Session starten
Session::configure([
    'lifetime' => (int)Env::get('SESSION_LIFETIME', '7200'),
    'secure' => Env::get('SESSION_SECURE_COOKIE', 'false') === 'true',
    'httponly' => true
]);
Session::start();

// Application Bootstrap
try {
    // Application laden
    $app = new \App\Core\Application();
    
    // Routen registrieren
    require_once BASE_PATH . '/routes/web.php';
    require_once BASE_PATH . '/routes/api.php';
    
    // 404 Handler setzen
    $app->getRouter()->setNotFoundHandler(function($request, $response) {
        // Für API-Anfragen JSON zurückgeben
        if (strpos($request->uri, '/api/') === 0) {
            $response->json([
                'error' => 'Route nicht gefunden',
                'path' => $request->uri
            ], 404);
            return;
        }
        
        // Für Web-Anfragen HTML-Seite anzeigen
        $response->setStatusCode(404);
        $html = '
        <!DOCTYPE html>
        <html lang="de">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>404 - Seite nicht gefunden</title>
            <script src="https://cdn.tailwindcss.com"></script>
        </head>
        <body class="bg-gray-100 flex items-center justify-center min-h-screen">
            <div class="text-center">
                <h1 class="text-6xl font-bold text-gray-800">404</h1>
                <p class="text-xl text-gray-600 mt-4">Seite nicht gefunden</p>
                <a href="/" class="mt-6 inline-block bg-indigo-600 text-white px-6 py-3 rounded hover:bg-indigo-700">
                    Zur Startseite
                </a>
            </div>
        </body>
        </html>';
        
        $response->html($html);
    });
    
    // Error Handler setzen
    set_exception_handler(function($exception) {
        error_log($exception->getMessage());
        
        if (Env::get('APP_DEBUG', 'false') === 'true') {
            echo '<h1>Fehler</h1>';
            echo '<pre>' . htmlspecialchars($exception->getMessage()) . '</pre>';
            echo '<pre>' . htmlspecialchars($exception->getTraceAsString()) . '</pre>';
        } else {
            http_response_code(500);
            echo '<h1>Ein Fehler ist aufgetreten</h1>';
            echo '<p>Bitte versuchen Sie es später erneut.</p>';
        }
        exit;
    });
    
    // Application ausführen
    $app->run();
    
} catch (Exception $e) {
    // Kritischer Fehler
    error_log('Kritischer Fehler: ' . $e->getMessage());
    
    if (Env::get('APP_DEBUG', 'false') === 'true') {
        echo '<h1>Kritischer Fehler</h1>';
        echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
        echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    } else {
        http_response_code(500);
        echo '<!DOCTYPE html>';
        echo '<html><head><title>Fehler</title></head>';
        echo '<body><h1>Ein kritischer Fehler ist aufgetreten</h1>';
        echo '<p>Bitte kontaktieren Sie den Administrator.</p></body></html>';
    }
}