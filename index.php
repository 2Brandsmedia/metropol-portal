<?php
/**
 * Metropol Portal - Root Index Bootstrap
 * 
 * Diese Datei lädt die Anwendung direkt ohne Weiterleitung,
 * um Redirect-Loops zu vermeiden.
 * 
 * @author 2Brands Media GmbH
 */

// Prüfen ob Installation abgeschlossen
if (!file_exists(__DIR__ . '/.env')) {
    // Noch nicht installiert - zum Installer
    header('Location: install.php');
    exit;
}

// Arbeitsverzeichnis auf public setzen
chdir(__DIR__ . '/public');

// Anwendung direkt laden (kein Redirect!)
require_once __DIR__ . '/public/index.php';