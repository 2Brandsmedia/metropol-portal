<?php

declare(strict_types=1);

namespace Installer;

/**
 * InstallAgent - Hauptklasse für die Installation
 * 
 * Koordiniert den gesamten Installationsprozess und führt
 * den Benutzer durch alle notwendigen Schritte.
 * 
 * @author 2Brands Media GmbH
 */
class InstallAgent
{
    private array $steps = [
        1 => 'language',    // Sprachauswahl
        2 => 'requirements', // System-Check
        3 => 'database',    // Datenbank-Konfiguration
        4 => 'admin',       // Admin-Account
        5 => 'config',      // Basis-Konfiguration
        6 => 'install'      // Installation & Finish
    ];
    
    private array $languages = [
        'de' => 'Deutsch',
        'en' => 'English',
        'tr' => 'Türkçe'
    ];
    
    private array $translations = [];
    private string $currentLang = 'de';
    private int $currentStep = 1;
    
    public function __construct()
    {
        // Aktuellen Schritt aus Session laden
        $this->currentStep = $_SESSION['installer_step'] ?? 1;
        
        // Sprache aus Session laden
        $this->currentLang = $_SESSION['installer_lang'] ?? 'de';
        
        // Übersetzungen laden
        $this->loadTranslations();
    }
    
    /**
     * Hauptmethode - startet den Installer
     */
    public function run(): void
    {
        // POST-Request verarbeiten
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->processStep();
            return;
        }
        
        // Aktuellen Schritt anzeigen
        $this->renderStep();
    }
    
    /**
     * Verarbeitet die Eingaben des aktuellen Schritts
     */
    private function processStep(): void
    {
        // CSRF-Token prüfen
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['installer_csrf_token']) {
            $this->renderError('Sicherheitsfehler: Ungültiges CSRF-Token');
            return;
        }
        
        // Schritt-spezifische Verarbeitung
        switch ($this->steps[$this->currentStep]) {
            case 'language':
                $this->processLanguageStep();
                break;
            case 'requirements':
                $this->processRequirementsStep();
                break;
            case 'database':
                $this->processDatabaseStep();
                break;
            case 'admin':
                $this->processAdminStep();
                break;
            case 'config':
                $this->processConfigStep();
                break;
            case 'install':
                $this->processInstallStep();
                break;
        }
    }
    
    /**
     * Rendert den aktuellen Schritt
     */
    private function renderStep(): void
    {
        $stepName = $this->steps[$this->currentStep];
        $stepFile = __DIR__ . "/steps/step-{$this->currentStep}-{$stepName}.php";
        
        if (!file_exists($stepFile)) {
            $this->renderError("Schritt-Datei nicht gefunden: {$stepFile}");
            return;
        }
        
        // Variablen für Template
        $installer = $this;
        $csrf_token = $_SESSION['installer_csrf_token'];
        $lang = $this->currentLang;
        $t = $this->translations;
        
        // Header ausgeben
        $this->renderHeader();
        
        // Schritt einbinden
        include $stepFile;
        
        // Footer ausgeben
        $this->renderFooter();
    }
    
    /**
     * Rendert den HTML-Header
     */
    private function renderHeader(): void
    {
        ?>
        <!DOCTYPE html>
        <html lang="<?php echo $this->currentLang; ?>">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Metropol Portal Installation - Schritt <?php echo $this->currentStep; ?> von 6</title>
            <script src="https://cdn.tailwindcss.com"></script>
            <link rel="stylesheet" href="installer/assets/css/installer.css">
        </head>
        <body class="bg-gray-50 min-h-screen">
            <div class="container mx-auto py-8 px-4">
                <!-- Header -->
                <div class="text-center mb-8">
                    <h1 class="text-3xl font-bold text-gray-800">Metropol Portal</h1>
                    <p class="text-gray-600 mt-2">Installationsassistent</p>
                </div>
                
                <!-- Progress Bar -->
                <div class="max-w-2xl mx-auto mb-8">
                    <div class="bg-white rounded-lg shadow p-4">
                        <div class="flex justify-between items-center mb-2">
                            <?php for ($i = 1; $i <= 6; $i++): ?>
                                <div class="flex items-center">
                                    <div class="<?php echo $i <= $this->currentStep ? 'bg-indigo-600 text-white' : 'bg-gray-300 text-gray-600'; ?> 
                                                w-8 h-8 rounded-full flex items-center justify-center font-semibold">
                                        <?php echo $i; ?>
                                    </div>
                                    <?php if ($i < 6): ?>
                                        <div class="w-full h-1 <?php echo $i < $this->currentStep ? 'bg-indigo-600' : 'bg-gray-300'; ?> mx-2"></div>
                                    <?php endif; ?>
                                </div>
                            <?php endfor; ?>
                        </div>
                        <div class="text-center text-sm text-gray-600 mt-2">
                            Schritt <?php echo $this->currentStep; ?> von 6: <?php echo $this->getStepTitle(); ?>
                        </div>
                    </div>
                </div>
                
                <!-- Main Content -->
                <div class="max-w-2xl mx-auto">
                    <div class="bg-white rounded-lg shadow-lg p-6">
        <?php
    }
    
    /**
     * Rendert den HTML-Footer
     */
    private function renderFooter(): void
    {
        ?>
                    </div>
                </div>
            </div>
            
            <script src="installer/assets/js/installer.js"></script>
        </body>
        </html>
        <?php
    }
    
    /**
     * Rendert eine Fehlermeldung
     */
    private function renderError(string $message): void
    {
        $this->renderHeader();
        ?>
        <div class="bg-red-50 border border-red-200 rounded p-4 mb-4">
            <p class="text-red-700"><?php echo htmlspecialchars($message); ?></p>
        </div>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['installer_csrf_token']; ?>">
            <button type="submit" name="retry" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">
                Erneut versuchen
            </button>
        </form>
        <?php
        $this->renderFooter();
    }
    
    /**
     * Lädt die Übersetzungen für die aktuelle Sprache
     */
    private function loadTranslations(): void
    {
        $langFile = __DIR__ . "/translations/{$this->currentLang}.php";
        
        if (file_exists($langFile)) {
            $this->translations = include $langFile;
        } else {
            // Fallback zu Deutsch
            $this->translations = include __DIR__ . "/translations/de.php";
        }
    }
    
    /**
     * Gibt den Titel des aktuellen Schritts zurück
     */
    private function getStepTitle(): string
    {
        $titles = [
            'language' => 'Sprache wählen',
            'requirements' => 'Systemanforderungen',
            'database' => 'Datenbank',
            'admin' => 'Administrator',
            'config' => 'Konfiguration',
            'install' => 'Installation'
        ];
        
        return $this->translations['steps'][$this->steps[$this->currentStep]] ?? 
               $titles[$this->steps[$this->currentStep]] ?? 
               'Unbekannter Schritt';
    }
    
    /**
     * Verarbeitet Schritt 1: Sprachauswahl
     */
    private function processLanguageStep(): void
    {
        if (isset($_POST['language']) && array_key_exists($_POST['language'], $this->languages)) {
            $_SESSION['installer_lang'] = $_POST['language'];
            $this->currentLang = $_POST['language'];
            $this->loadTranslations();
            
            // Zum nächsten Schritt
            $_SESSION['installer_step'] = 2;
            header('Location: install.php');
            exit;
        }
    }
    
    /**
     * Verarbeitet Schritt 2: System-Requirements
     */
    private function processRequirementsStep(): void
    {
        // Requirements werden automatisch geprüft, User klickt nur "Weiter"
        $_SESSION['installer_step'] = 3;
        header('Location: install.php');
        exit;
    }
    
    /**
     * Verarbeitet Schritt 3: Datenbank
     */
    private function processDatabaseStep(): void
    {
        $errors = [];
        
        // Validierung
        if (empty($_POST['db_host'])) $errors[] = 'Datenbank-Host fehlt';
        if (empty($_POST['db_name'])) $errors[] = 'Datenbank-Name fehlt';
        if (empty($_POST['db_user'])) $errors[] = 'Datenbank-Benutzer fehlt';
        
        if (!empty($errors)) {
            $_SESSION['installer_errors'] = $errors;
            header('Location: install.php');
            exit;
        }
        
        // Daten in Session speichern
        $_SESSION['installer_db'] = [
            'host' => $_POST['db_host'],
            'port' => $_POST['db_port'] ?? '3306',
            'name' => $_POST['db_name'],
            'user' => $_POST['db_user'],
            'pass' => $_POST['db_pass'] ?? ''
        ];
        
        // Verbindung testen
        try {
            $dsn = "mysql:host={$_POST['db_host']};port={$_POST['db_port']};dbname={$_POST['db_name']};charset=utf8mb4";
            $pdo = new \PDO($dsn, $_POST['db_user'], $_POST['db_pass']);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            
            // Erfolgreich - zum nächsten Schritt
            $_SESSION['installer_step'] = 4;
            header('Location: install.php');
            exit;
            
        } catch (\PDOException $e) {
            $_SESSION['installer_errors'] = ['Datenbankverbindung fehlgeschlagen: ' . $e->getMessage()];
            header('Location: install.php');
            exit;
        }
    }
    
    /**
     * Verarbeitet Schritt 4: Admin-Account
     */
    private function processAdminStep(): void
    {
        $errors = [];
        
        // Validierung
        if (empty($_POST['admin_user'])) $errors[] = 'Benutzername fehlt';
        if (empty($_POST['admin_email'])) $errors[] = 'E-Mail fehlt';
        if (empty($_POST['admin_pass'])) $errors[] = 'Passwort fehlt';
        if ($_POST['admin_pass'] !== $_POST['admin_pass_confirm']) $errors[] = 'Passwörter stimmen nicht überein';
        if (strlen($_POST['admin_pass']) < 8) $errors[] = 'Passwort muss mindestens 8 Zeichen lang sein';
        
        if (!empty($errors)) {
            $_SESSION['installer_errors'] = $errors;
            header('Location: install.php');
            exit;
        }
        
        // Admin-Daten speichern
        $_SESSION['installer_admin'] = [
            'username' => $_POST['admin_user'],
            'email' => $_POST['admin_email'],
            'password' => password_hash($_POST['admin_pass'], PASSWORD_DEFAULT)
        ];
        
        // Zum nächsten Schritt
        $_SESSION['installer_step'] = 5;
        header('Location: install.php');
        exit;
    }
    
    /**
     * Verarbeitet Schritt 5: Konfiguration
     */
    private function processConfigStep(): void
    {
        // Konfiguration speichern
        $_SESSION['installer_config'] = [
            'site_name' => $_POST['site_name'] ?? 'Metropol Portal',
            'timezone' => $_POST['timezone'] ?? 'Europe/Berlin',
            'ors_api_key' => $_POST['ors_api_key'] ?? '',
            'google_maps_key' => $_POST['google_maps_key'] ?? ''
        ];
        
        // Zum letzten Schritt
        $_SESSION['installer_step'] = 6;
        header('Location: install.php');
        exit;
    }
    
    /**
     * Verarbeitet Schritt 6: Installation
     */
    private function processInstallStep(): void
    {
        try {
            // ConfigWriter verwenden
            require_once __DIR__ . '/ConfigWriter.php';
            $configWriter = new ConfigWriter();
            $configWriter->writeEnvFile($_SESSION['installer_db'], $_SESSION['installer_config']);
            
            // DatabaseInstaller verwenden
            require_once __DIR__ . '/DatabaseInstaller.php';
            $dbInstaller = new DatabaseInstaller($_SESSION['installer_db']);
            $dbInstaller->install();
            $dbInstaller->createAdmin($_SESSION['installer_admin']);
            
            // Session bereinigen
            session_destroy();
            
            // Installer löschen oder umbenennen
            $this->selfDestruct();
            
            // Zur Anwendung weiterleiten
            header('Location: public/index.php');
            exit;
            
        } catch (\Exception $e) {
            $_SESSION['installer_errors'] = ['Installation fehlgeschlagen: ' . $e->getMessage()];
            header('Location: install.php');
            exit;
        }
    }
    
    /**
     * Löscht den Installer nach erfolgreicher Installation
     */
    private function selfDestruct(): void
    {
        // install.php umbenennen
        @rename(__DIR__ . '/../install.php', __DIR__ . '/../install.php.done');
        
        // Installer-Verzeichnis umbenennen
        @rename(__DIR__, __DIR__ . '.done');
    }
    
    /**
     * Hilfsmethode: Übersetzung abrufen
     */
    public function t(string $key): string
    {
        return $this->translations[$key] ?? $key;
    }
}