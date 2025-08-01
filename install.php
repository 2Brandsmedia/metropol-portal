<?php

declare(strict_types=1);

/**
 * Metropol Portal - Installation Wizard
 * 
 * Dieser Installer führt Sie durch die Installation des Metropol Portals.
 * Nach erfolgreicher Installation wird diese Datei automatisch gelöscht.
 * 
 * @author 2Brands Media GmbH
 */

// Error Reporting für Installation
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

// Prüfen ob bereits installiert
if (file_exists(__DIR__ . '/.env') && file_exists(__DIR__ . '/public/index.php')) {
    // Portal ist bereits installiert - Weiterleitung zur Anwendung
    header('Location: public/index.php');
    exit('Metropol Portal ist bereits installiert. <a href="public/index.php">Zur Anwendung</a>');
}

// Installer-Verzeichnis prüfen
if (!file_exists(__DIR__ . '/installer/')) {
    die('Fehler: Installer-Verzeichnis nicht gefunden. Bitte laden Sie das komplette Installationspaket hoch.');
}

// Session starten für Wizard-State
session_start();

// CSRF-Token generieren falls nicht vorhanden
if (!isset($_SESSION['installer_csrf_token'])) {
    $_SESSION['installer_csrf_token'] = bin2hex(random_bytes(32));
}

// Installer laden und ausführen
try {
    require_once __DIR__ . '/installer/InstallAgent.php';
    
    $installer = new \Installer\InstallAgent();
    $installer->run();
    
} catch (\Exception $e) {
    // Fehlerbehandlung
    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Installationsfehler - Metropol Portal</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gray-100 min-h-screen flex items-center justify-center">
        <div class="bg-white p-8 rounded-lg shadow-lg max-w-md w-full">
            <div class="text-center">
                <svg class="mx-auto h-12 w-12 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <h2 class="mt-4 text-xl font-semibold text-gray-900">Installationsfehler</h2>
                <p class="mt-2 text-gray-600">Bei der Installation ist ein Fehler aufgetreten:</p>
                <div class="mt-4 p-4 bg-red-50 rounded text-red-700 text-sm text-left">
                    <?php echo htmlspecialchars($e->getMessage()); ?>
                </div>
                <div class="mt-6">
                    <button onclick="location.reload()" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">
                        Erneut versuchen
                    </button>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
}