<?php
/**
 * Metropol Portal - Root Index
 * 
 * Diese Datei leitet Besucher automatisch zum public-Verzeichnis weiter,
 * wo die eigentliche Anwendung liegt.
 * 
 * @author 2Brands Media GmbH
 */

// Prüfen ob Installation abgeschlossen
if (!file_exists(__DIR__ . '/.env')) {
    // Noch nicht installiert - zum Installer
    header('Location: install.php');
    exit;
}

// Weiterleitung zur Anwendung im public-Verzeichnis
header('Location: public/');
exit;