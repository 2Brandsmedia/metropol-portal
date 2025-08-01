<?php

return [
    // Schritte
    'steps' => [
        'language' => 'Sprache wählen',
        'requirements' => 'Systemanforderungen',
        'database' => 'Datenbank',
        'admin' => 'Administrator',
        'config' => 'Konfiguration',
        'install' => 'Installation'
    ],
    
    // Buttons
    'next' => 'Weiter',
    'back' => 'Zurück',
    'test_connection' => 'Verbindung testen',
    'install_now' => 'Jetzt installieren',
    'go_to_app' => 'Zur Anwendung',
    
    // Schritt 1 - Sprache
    'welcome' => 'Willkommen beim Metropol Portal Installer',
    'select_language' => 'Bitte wählen Sie Ihre bevorzugte Sprache:',
    
    // Schritt 2 - Requirements
    'checking_requirements' => 'Systemanforderungen werden geprüft...',
    'all_requirements_met' => 'Alle Systemanforderungen sind erfüllt!',
    'requirements_not_met' => 'Einige Systemanforderungen sind nicht erfüllt.',
    'please_fix_errors' => 'Bitte beheben Sie die Fehler bevor Sie fortfahren.',
    
    // Schritt 3 - Datenbank
    'database_configuration' => 'Datenbank-Konfiguration',
    'database_host' => 'Datenbank-Host',
    'database_port' => 'Port',
    'database_name' => 'Datenbank-Name',
    'database_user' => 'Benutzername',
    'database_pass' => 'Passwort',
    'database_help' => 'Diese Informationen erhalten Sie von Ihrem Hosting-Provider.',
    
    // Schritt 4 - Admin
    'create_admin' => 'Administrator-Konto erstellen',
    'admin_username' => 'Benutzername',
    'admin_email' => 'E-Mail-Adresse',
    'admin_password' => 'Passwort',
    'admin_password_confirm' => 'Passwort bestätigen',
    'password_requirements' => 'Mindestens 8 Zeichen, Groß-/Kleinbuchstaben und Zahlen',
    
    // Schritt 5 - Konfiguration
    'basic_configuration' => 'Basis-Konfiguration',
    'site_name' => 'Name der Website',
    'timezone' => 'Zeitzone',
    'api_configuration' => 'API-Konfiguration (optional)',
    'google_maps_key' => 'Google Maps API Key',
    'ors_api_key' => 'OpenRouteService API Key',
    'api_keys_optional' => 'API-Keys können auch später konfiguriert werden.',
    
    // Schritt 6 - Installation
    'ready_to_install' => 'Bereit zur Installation',
    'installation_summary' => 'Installations-Zusammenfassung',
    'installing' => 'Installation läuft...',
    'installation_complete' => 'Installation erfolgreich!',
    'installation_success_message' => 'Metropol Portal wurde erfolgreich installiert.',
    'important_security' => 'Wichtig: Der Installer wurde aus Sicherheitsgründen deaktiviert.',
    
    // Fehler
    'error_database_connection' => 'Datenbankverbindung fehlgeschlagen',
    'error_database_exists' => 'Datenbank existiert nicht',
    'error_file_permissions' => 'Keine Schreibrechte',
    'error_php_version' => 'PHP-Version zu alt',
    'error_missing_extension' => 'PHP-Extension fehlt',
    
    // Erfolg
    'success_connection' => 'Verbindung erfolgreich!',
    'success_requirements' => 'Alle Anforderungen erfüllt',
    'success_installation' => 'Installation abgeschlossen'
];