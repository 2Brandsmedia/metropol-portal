<?php
/**
 * Metropol Portal - Root Index Bootstrap
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
if (file_exists(__DIR__ . '/public/index.php')) {
    chdir(__DIR__ . '/public');
    require_once __DIR__ . '/public/index.php';
} else {
    echo "Anwendung noch nicht vollständig installiert.";
}
?>