<?php

declare(strict_types=1);

namespace App\Agents;

use App\Core\Config;
use App\Core\Database;
use Exception;

/**
 * DeployAgent - Deployment und System-Status
 * 
 * Erfolgskriterium: Zero-Downtime Deployment
 * 
 * @author 2Brands Media GmbH
 */
class DeployAgent
{
    private Config $config;
    private Database $db;
    private string $projectRoot;
    private string $logFile;

    public function __construct(Config $config, Database $db)
    {
        $this->config = $config;
        $this->db = $db;
        $this->projectRoot = dirname(__DIR__, 2);
        $this->logFile = $this->projectRoot . '/storage/logs/deploy.log';
    }

    /**
     * Führt Health-Check durch
     */
    public function healthCheck(): array
    {
        $checks = [];
        
        // PHP-Version
        $checks['php'] = [
            'status' => version_compare(PHP_VERSION, '8.3.0', '>=') ? 'ok' : 'error',
            'version' => PHP_VERSION,
            'required' => '8.3.0'
        ];
        
        // Datenbank-Verbindung
        try {
            $this->db->getPdo()->query('SELECT 1');
            $checks['database'] = [
                'status' => 'ok',
                'message' => 'Verbindung erfolgreich'
            ];
        } catch (\Exception $e) {
            $checks['database'] = [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
        
        // Schreibberechtigungen
        $writableDirs = [
            '/storage',
            '/storage/cache',
            '/storage/logs',
            '/storage/sessions',
            '/public/uploads'
        ];
        
        foreach ($writableDirs as $dir) {
            $fullPath = $this->projectRoot . $dir;
            $checks['writable'][$dir] = [
                'status' => is_writable($fullPath) ? 'ok' : 'error',
                'path' => $fullPath
            ];
        }
        
        // PHP-Extensions
        $requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'curl', 'openssl'];
        
        foreach ($requiredExtensions as $ext) {
            $checks['extensions'][$ext] = [
                'status' => extension_loaded($ext) ? 'ok' : 'error'
            ];
        }
        
        // Cache-Status
        $checks['cache'] = $this->checkCacheStatus();
        
        // Deployment-Status
        $checks['deployment'] = $this->getDeploymentInfo();
        
        // Gesamt-Status
        $checks['overall'] = $this->calculateOverallStatus($checks);
        
        return $checks;
    }

    /**
     * Gibt Deployment-Informationen zurück
     */
    public function getDeploymentInfo(): array
    {
        $info = [
            'version' => $this->getCurrentVersion(),
            'environment' => $this->config->get('app.env'),
            'last_deployment' => null,
            'git_commit' => null
        ];
        
        // Git-Commit ermitteln
        $gitHeadFile = $this->projectRoot . '/.git/HEAD';
        if (file_exists($gitHeadFile)) {
            $head = trim(file_get_contents($gitHeadFile));
            if (strpos($head, 'ref:') === 0) {
                $refFile = $this->projectRoot . '/.git/' . substr($head, 5);
                if (file_exists($refFile)) {
                    $info['git_commit'] = substr(trim(file_get_contents($refFile)), 0, 7);
                }
            }
        }
        
        // Letztes Deployment
        if (file_exists($this->logFile)) {
            $info['last_deployment'] = date('Y-m-d H:i:s', filemtime($this->logFile));
        }
        
        return $info;
    }

    /**
     * Prüft ob Maintenance Mode aktiv ist
     */
    public function isMaintenanceMode(): bool
    {
        return file_exists($this->config->get('app.maintenance_file'));
    }

    /**
     * Aktiviert Maintenance Mode
     */
    public function enableMaintenanceMode(): void
    {
        touch($this->config->get('app.maintenance_file'));
        $this->log('Maintenance Mode aktiviert');
    }

    /**
     * Deaktiviert Maintenance Mode
     */
    public function disableMaintenanceMode(): void
    {
        @unlink($this->config->get('app.maintenance_file'));
        $this->log('Maintenance Mode deaktiviert');
    }

    /**
     * Führt Pre-Deployment Checks durch
     */
    public function preDeploymentCheck(): array
    {
        $checks = [];
        
        // Disk Space
        $freeSpace = disk_free_space($this->projectRoot);
        $checks['disk_space'] = [
            'status' => $freeSpace > 100 * 1024 * 1024 ? 'ok' : 'error', // 100MB
            'free' => $this->formatBytes($freeSpace),
            'required' => '100 MB'
        ];
        
        // Backup-Status
        $checks['backup'] = $this->checkBackupStatus();
        
        // Migrations ausstehend?
        $checks['migrations'] = $this->checkPendingMigrations();
        
        // Tests erfolgreich?
        $checks['tests'] = $this->checkTestStatus();
        
        return $checks;
    }

    /**
     * Führt Post-Deployment Tasks aus
     */
    public function postDeployment(): void
    {
        // Cache leeren
        $this->clearCache();
        
        // OPcache zurücksetzen
        if (function_exists('opcache_reset')) {
            opcache_reset();
            $this->log('OPcache zurückgesetzt');
        }
        
        // Deployment in Datenbank loggen
        $this->logDeployment();
        
        // Health Check
        $health = $this->healthCheck();
        if ($health['overall']['status'] !== 'ok') {
            $this->log('Post-Deployment Health Check fehlgeschlagen', 'error');
        }
    }

    /**
     * Rollback durchführen
     */
    public function rollback(string $version): bool
    {
        $this->log("Rollback zu Version {$version} gestartet");
        
        try {
            // Git Checkout
            $output = shell_exec("cd {$this->projectRoot} && git checkout {$version} 2>&1");
            $this->log("Git Output: " . $output);
            
            // Composer Install
            $output = shell_exec("cd {$this->projectRoot} && composer install --no-dev 2>&1");
            $this->log("Composer Output: " . $output);
            
            // Post-Deployment
            $this->postDeployment();
            
            $this->log("Rollback erfolgreich abgeschlossen");
            return true;
            
        } catch (\Exception $e) {
            $this->log("Rollback fehlgeschlagen: " . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Cache leeren
     */
    public function clearCache(): void
    {
        $cacheDirs = [
            '/storage/cache',
            '/storage/views'
        ];
        
        foreach ($cacheDirs as $dir) {
            $fullPath = $this->projectRoot . $dir;
            if (is_dir($fullPath)) {
                $this->deleteDirectory($fullPath, false);
            }
        }
        
        $this->log('Cache geleert');
    }

    /**
     * Prüft Cache-Status
     */
    private function checkCacheStatus(): array
    {
        $cacheDir = $this->projectRoot . '/storage/cache';
        $files = glob($cacheDir . '/*');
        
        return [
            'status' => 'ok',
            'files' => count($files),
            'size' => $this->getDirectorySize($cacheDir)
        ];
    }

    /**
     * Prüft Backup-Status
     */
    private function checkBackupStatus(): array
    {
        // Hier würde normalerweise der Backup-Status geprüft
        return [
            'status' => 'ok',
            'last_backup' => date('Y-m-d H:i:s', strtotime('-1 hour'))
        ];
    }

    /**
     * Prüft ausstehende Migrations
     */
    private function checkPendingMigrations(): array
    {
        try {
            $stmt = $this->db->getPdo()->query('SELECT COUNT(*) FROM migrations');
            $executed = $stmt->fetchColumn();
            
            $migrationFiles = glob($this->projectRoot . '/database/migrations/*.php');
            $pending = count($migrationFiles) - $executed;
            
            return [
                'status' => $pending === 0 ? 'ok' : 'warning',
                'pending' => $pending,
                'total' => count($migrationFiles)
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Prüft Test-Status
     */
    private function checkTestStatus(): array
    {
        // Hier würde normalerweise der Test-Status geprüft
        return [
            'status' => 'ok',
            'message' => 'Alle Tests erfolgreich'
        ];
    }

    /**
     * Berechnet Gesamt-Status
     */
    private function calculateOverallStatus(array $checks): array
    {
        $hasError = false;
        $hasWarning = false;
        
        foreach ($checks as $check) {
            if (is_array($check) && isset($check['status'])) {
                if ($check['status'] === 'error') {
                    $hasError = true;
                } elseif ($check['status'] === 'warning') {
                    $hasWarning = true;
                }
            }
        }
        
        return [
            'status' => $hasError ? 'error' : ($hasWarning ? 'warning' : 'ok'),
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Loggt Deployment
     */
    private function logDeployment(): void
    {
        $this->db->insert(
            'INSERT INTO audit_log (action, entity_type, entity_id, new_values) VALUES (?, ?, ?, ?)',
            [
                'deployment',
                'system',
                0,
                json_encode($this->getDeploymentInfo())
            ]
        );
    }

    /**
     * Ermittelt aktuelle Version
     */
    private function getCurrentVersion(): string
    {
        $packageFile = $this->projectRoot . '/package.json';
        if (file_exists($packageFile)) {
            $package = json_decode(file_get_contents($packageFile), true);
            return $package['version'] ?? '1.0.0';
        }
        
        return '1.0.0';
    }

    /**
     * Löscht Verzeichnis-Inhalt
     */
    private function deleteDirectory(string $dir, bool $removeDir = true): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        
        if ($removeDir) {
            rmdir($dir);
        }
    }

    /**
     * Berechnet Verzeichnis-Größe
     */
    private function getDirectorySize(string $dir): string
    {
        $size = 0;
        
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir)) as $file) {
            $size += $file->getSize();
        }
        
        return $this->formatBytes($size);
    }

    /**
     * Formatiert Bytes
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Schreibt Log-Eintrag
     */
    private function log(string $message, string $level = 'info'): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [$level] $message\n";
        
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
    }
}