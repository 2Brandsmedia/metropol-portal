<?php

declare(strict_types=1);

/**
 * All-Inkl Deployment Script
 * 
 * Dieses Skript wird durch einen Git-Hook auf All-Inkl ausgeführt
 * 
 * @author 2Brands Media GmbH
 */

class Deployer
{
    private string $projectRoot;
    private string $logFile;
    private bool $hasErrors = false;

    public function __construct()
    {
        $this->projectRoot = dirname(__FILE__);
        $this->logFile = $this->projectRoot . '/storage/logs/deploy-' . date('Y-m-d') . '.log';
    }

    public function deploy(): void
    {
        $this->log('=== Deployment gestartet ===');
        
        try {
            // 1. Maintenance Mode aktivieren
            $this->enableMaintenanceMode();
            
            // 2. Git Pull
            $this->gitPull();
            
            // 3. Composer Dependencies installieren
            $this->composerInstall();
            
            // 4. NPM Dependencies installieren und Build
            $this->npmBuild();
            
            // 5. Datenbank-Migrationen
            $this->runMigrations();
            
            // 6. Cache leeren
            $this->clearCache();
            
            // 7. Berechtigungen setzen
            $this->setPermissions();
            
            // 8. Maintenance Mode deaktivieren
            $this->disableMaintenanceMode();
            
            $this->log('=== Deployment erfolgreich abgeschlossen ===');
            
        } catch (Exception $e) {
            $this->hasErrors = true;
            $this->log('FEHLER: ' . $e->getMessage(), 'error');
            $this->disableMaintenanceMode();
            exit(1);
        }
    }

    private function enableMaintenanceMode(): void
    {
        $this->log('Aktiviere Maintenance Mode...');
        touch($this->projectRoot . '/storage/.maintenance');
    }

    private function disableMaintenanceMode(): void
    {
        $this->log('Deaktiviere Maintenance Mode...');
        @unlink($this->projectRoot . '/storage/.maintenance');
    }

    private function gitPull(): void
    {
        $this->log('Führe Git Pull aus...');
        $this->exec('git pull origin main');
    }

    private function composerInstall(): void
    {
        $this->log('Installiere PHP Dependencies...');
        $this->exec('composer install --no-dev --optimize-autoloader');
    }

    private function npmBuild(): void
    {
        $this->log('Installiere NPM Dependencies und erstelle Build...');
        $this->exec('npm ci --production');
        $this->exec('npm run build');
    }

    private function runMigrations(): void
    {
        $this->log('Führe Datenbank-Migrationen aus...');
        $this->exec('php database/migrate.php');
    }

    private function clearCache(): void
    {
        $this->log('Leere Cache...');
        
        // Cache-Verzeichnisse leeren
        $cacheDirs = [
            '/storage/cache',
            '/storage/views',
            '/storage/logs/old',
        ];
        
        foreach ($cacheDirs as $dir) {
            $fullPath = $this->projectRoot . $dir;
            if (is_dir($fullPath)) {
                $this->exec("find $fullPath -type f -name '*' -delete");
            }
        }
        
        // OPcache zurücksetzen (falls verfügbar)
        if (function_exists('opcache_reset')) {
            opcache_reset();
            $this->log('OPcache zurückgesetzt');
        }
    }

    private function setPermissions(): void
    {
        $this->log('Setze Dateiberechtigungen...');
        
        $directories = [
            '/storage' => '755',
            '/storage/cache' => '755',
            '/storage/logs' => '755',
            '/storage/sessions' => '755',
            '/public/uploads' => '755',
        ];
        
        foreach ($directories as $dir => $permission) {
            $fullPath = $this->projectRoot . $dir;
            if (!is_dir($fullPath)) {
                mkdir($fullPath, octdec($permission), true);
            }
            chmod($fullPath, octdec($permission));
        }
    }

    private function exec(string $command): string
    {
        $output = [];
        $returnVar = 0;
        
        exec($command . ' 2>&1', $output, $returnVar);
        
        $outputString = implode("\n", $output);
        
        if ($returnVar !== 0) {
            throw new Exception("Command failed: $command\nOutput: $outputString");
        }
        
        $this->log("Command: $command\nOutput: $outputString");
        
        return $outputString;
    }

    private function log(string $message, string $level = 'info'): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [$level] $message\n";
        
        // Sicherstellen, dass das Log-Verzeichnis existiert
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
        
        // Auch auf Konsole ausgeben
        echo $logMessage;
    }
}

// Deployment ausführen
$deployer = new Deployer();
$deployer->deploy();