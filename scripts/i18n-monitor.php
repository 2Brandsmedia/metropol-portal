<?php

declare(strict_types=1);

/**
 * I18n Monitor - Kontinuierliche Überwachung der Übersetzungsqualität
 * 
 * Läuft als Cron-Job und überwacht kontinuierlich:
 * - Übersetzungsabdeckung
 * - Konsistenz zwischen Sprachen
 * - Performance-Metriken
 * - Automatische Benachrichtigungen bei Problemen
 * 
 * @author 2Brands Media GmbH
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Config;
use App\Core\Session;
use App\Agents\I18nAgent;
use App\Agents\MonitorAgent;

class I18nMonitor
{
    private I18nAgent $i18nAgent;
    private MonitorAgent $monitorAgent;
    private Config $config;
    private array $thresholds;
    private string $logFile;

    public function __construct()
    {
        $this->config = new Config();
        $session = new Session();
        $this->i18nAgent = new I18nAgent($this->config, $session);
        $this->monitorAgent = new MonitorAgent($this->config);
        
        // Monitoring-Schwellenwerte
        $this->thresholds = [
            'min_coverage' => 95.0,          // Mindest-Coverage in %
            'max_inconsistencies' => 5,      // Maximale Anzahl Inkonsistenzen
            'max_unused_keys' => 20,         // Maximale Anzahl ungenutzter Schlüssel
            'max_response_time' => 100,      // Maximale Response-Zeit in ms
            'max_memory_usage' => 50         // Maximaler Speicherverbrauch in MB
        ];
        
        $this->logFile = dirname(__DIR__) . '/logs/i18n-monitor.log';
        
        // Log-Verzeichnis erstellen falls nicht vorhanden
        if (!is_dir(dirname($this->logFile))) {
            mkdir(dirname($this->logFile), 0755, true);
        }
    }

    /**
     * Hauptmethode für Monitoring-Durchgang
     */
    public function run(): void
    {
        $this->log("🔍 I18n Monitor gestartet - " . date('Y-m-d H:i:s'));
        
        try {
            $results = [
                'timestamp' => time(),
                'coverage_check' => $this->checkCoverage(),
                'consistency_check' => $this->checkConsistency(),
                'performance_check' => $this->checkPerformance(),
                'unused_keys_check' => $this->checkUnusedKeys(),
                'file_integrity_check' => $this->checkFileIntegrity(),
                'alerts' => []
            ];
            
            // Alerts generieren
            $results['alerts'] = $this->generateAlerts($results);
            
            // Metriken speichern
            $this->storeMetrics($results);
            
            // Alerts versenden falls erforderlich
            if (!empty($results['alerts'])) {
                $this->handleAlerts($results['alerts']);
            }
            
            $this->log("✅ Monitoring-Durchgang abgeschlossen");
            
        } catch (Exception $e) {
            $this->log("❌ Fehler beim Monitoring: " . $e->getMessage());
            
            // Kritischen Alert senden
            $this->handleAlerts([[
                'type' => 'system_error',
                'severity' => 'critical',
                'message' => 'I18n Monitor Systemfehler: ' . $e->getMessage(),
                'timestamp' => time()
            ]]);
        }
    }

    /**
     * Prüft Übersetzungsabdeckung
     */
    private function checkCoverage(): array
    {
        $coverage = $this->i18nAgent->checkCoverage();
        $report = $this->i18nAgent->generateCoverageReport();
        
        return [
            'coverage_by_language' => $coverage,
            'overall_health' => $report['overall_health'],
            'total_keys' => $report['total_keys'],
            'timestamp' => time()
        ];
    }

    /**
     * Prüft Konsistenz zwischen Sprachen
     */
    private function checkConsistency(): array
    {
        $consistencyIssues = $this->i18nAgent->checkTranslationConsistency();
        $missingKeys = $this->i18nAgent->findMissingTranslationKeys();
        $placeholderIssues = $this->i18nAgent->validatePlaceholders();
        
        $totalIssues = count($consistencyIssues) + count($missingKeys) + count($placeholderIssues);
        
        return [
            'consistency_issues' => $consistencyIssues,
            'missing_keys' => $missingKeys,
            'placeholder_issues' => $placeholderIssues,
            'total_issues' => $totalIssues,
            'timestamp' => time()
        ];
    }

    /**
     * Prüft Performance-Metriken
     */
    private function checkPerformance(): array
    {
        $memoryStart = memory_get_usage(true);
        $timeStart = microtime(true);
        
        // Performance-Test durchführen
        for ($i = 0; $i < 10; $i++) {
            $testAgent = new I18nAgent($this->config, new Session());
            $testAgent->t('app.name');
            $testAgent->t('auth.login');
            $testAgent->t('playlist.title');
        }
        
        $timeEnd = microtime(true);
        $memoryEnd = memory_get_usage(true);
        
        $responseTime = ($timeEnd - $timeStart) * 1000; // in ms
        $memoryUsage = ($memoryEnd - $memoryStart) / 1024 / 1024; // in MB
        
        return [
            'response_time_ms' => round($responseTime, 2),
            'memory_usage_mb' => round($memoryUsage, 2),
            'avg_response_time_ms' => round($responseTime / 10, 2),
            'timestamp' => time()
        ];
    }

    /**
     * Prüft ungenutzte Schlüssel
     */
    private function checkUnusedKeys(): array
    {
        $unusedKeys = $this->i18nAgent->findUnusedTranslationKeys();
        
        return [
            'unused_keys' => $unusedKeys,
            'count' => count($unusedKeys),
            'timestamp' => time()
        ];
    }

    /**
     * Prüft Integrität der Übersetzungsdateien
     */
    private function checkFileIntegrity(): array
    {
        $results = [];
        $languages = $this->i18nAgent->getAvailableLanguages();
        
        foreach ($languages as $lang) {
            $filePath = dirname(__DIR__, 1) . "/lang/{$lang}.json";
            
            $results[$lang] = [
                'exists' => file_exists($filePath),
                'readable' => is_readable($filePath),
                'writable' => is_writable($filePath),
                'size' => file_exists($filePath) ? filesize($filePath) : 0,
                'last_modified' => file_exists($filePath) ? filemtime($filePath) : 0,
                'valid_json' => false
            ];
            
            if ($results[$lang]['exists'] && $results[$lang]['readable']) {
                $content = file_get_contents($filePath);
                $results[$lang]['valid_json'] = json_decode($content) !== null;
            }
        }
        
        return $results;
    }

    /**
     * Generiert Alerts basierend auf Monitoring-Ergebnissen
     */
    private function generateAlerts(array $results): array
    {
        $alerts = [];
        
        // Coverage-Alerts
        foreach ($results['coverage_check']['coverage_by_language'] as $lang => $coverage) {
            if ($coverage < $this->thresholds['min_coverage']) {
                $alerts[] = [
                    'type' => 'low_coverage',
                    'severity' => $coverage < 80 ? 'critical' : 'warning',
                    'language' => $lang,
                    'message' => "Niedrige Übersetzungsabdeckung in {$lang}: {$coverage}%",
                    'current_value' => $coverage,
                    'threshold' => $this->thresholds['min_coverage'],
                    'timestamp' => time()
                ];
            }
        }
        
        // Konsistenz-Alerts
        if ($results['consistency_check']['total_issues'] > $this->thresholds['max_inconsistencies']) {
            $alerts[] = [
                'type' => 'consistency_issues',
                'severity' => 'warning',
                'message' => "Zu viele Konsistenzprobleme: {$results['consistency_check']['total_issues']}",
                'current_value' => $results['consistency_check']['total_issues'],
                'threshold' => $this->thresholds['max_inconsistencies'],
                'timestamp' => time()
            ];
        }
        
        // Performance-Alerts
        if ($results['performance_check']['avg_response_time_ms'] > $this->thresholds['max_response_time']) {
            $alerts[] = [
                'type' => 'slow_performance',
                'severity' => 'warning',
                'message' => "Langsame I18n-Performance: {$results['performance_check']['avg_response_time_ms']}ms",
                'current_value' => $results['performance_check']['avg_response_time_ms'],
                'threshold' => $this->thresholds['max_response_time'],
                'timestamp' => time()
            ];
        }
        
        if ($results['performance_check']['memory_usage_mb'] > $this->thresholds['max_memory_usage']) {
            $alerts[] = [
                'type' => 'high_memory_usage',
                'severity' => 'warning',
                'message' => "Hoher Speicherverbrauch: {$results['performance_check']['memory_usage_mb']}MB",
                'current_value' => $results['performance_check']['memory_usage_mb'],
                'threshold' => $this->thresholds['max_memory_usage'],
                'timestamp' => time()
            ];
        }
        
        // Ungenutzte Schlüssel-Alerts
        if ($results['unused_keys_check']['count'] > $this->thresholds['max_unused_keys']) {
            $alerts[] = [
                'type' => 'too_many_unused_keys',
                'severity' => 'info',
                'message' => "Viele ungenutzte Übersetzungsschlüssel: {$results['unused_keys_check']['count']}",
                'current_value' => $results['unused_keys_check']['count'],
                'threshold' => $this->thresholds['max_unused_keys'],
                'timestamp' => time()
            ];
        }
        
        // Datei-Integritäts-Alerts
        foreach ($results['file_integrity_check'] as $lang => $fileInfo) {
            if (!$fileInfo['exists']) {
                $alerts[] = [
                    'type' => 'missing_translation_file',
                    'severity' => 'critical',
                    'language' => $lang,
                    'message' => "Übersetzungsdatei fehlt: {$lang}.json",
                    'timestamp' => time()
                ];
            } elseif (!$fileInfo['valid_json']) {
                $alerts[] = [
                    'type' => 'invalid_json',
                    'severity' => 'critical',
                    'language' => $lang,
                    'message' => "Ungültiges JSON in Übersetzungsdatei: {$lang}.json",
                    'timestamp' => time()
                ];
            } elseif (!$fileInfo['writable']) {
                $alerts[] = [
                    'type' => 'file_permission_issue',
                    'severity' => 'warning',
                    'language' => $lang,
                    'message' => "Keine Schreibberechtigung für: {$lang}.json",
                    'timestamp' => time()
                ];
            }
        }
        
        return $alerts;
    }

    /**
     * Speichert Monitoring-Metriken
     */
    private function storeMetrics(array $results): void
    {
        // Metriken in Datenbank speichern (über MonitorAgent)
        try {
            $this->monitorAgent->recordMetric('i18n_coverage', [
                'de' => $results['coverage_check']['coverage_by_language']['de'] ?? 0,
                'en' => $results['coverage_check']['coverage_by_language']['en'] ?? 0,
                'tr' => $results['coverage_check']['coverage_by_language']['tr'] ?? 0
            ]);
            
            $this->monitorAgent->recordMetric('i18n_consistency', [
                'total_issues' => $results['consistency_check']['total_issues']
            ]);
            
            $this->monitorAgent->recordMetric('i18n_performance', [
                'response_time_ms' => $results['performance_check']['avg_response_time_ms'],
                'memory_usage_mb' => $results['performance_check']['memory_usage_mb']
            ]);
            
            $this->monitorAgent->recordMetric('i18n_unused_keys', [
                'count' => $results['unused_keys_check']['count']
            ]);
            
        } catch (Exception $e) {
            $this->log("⚠️ Fehler beim Speichern der Metriken: " . $e->getMessage());
        }
        
        // Zusätzlich in Log-Datei speichern (Fallback)
        $metricsLog = dirname(__DIR__) . '/logs/i18n-metrics.log';
        $metricsData = json_encode($results) . "\n";
        file_put_contents($metricsLog, $metricsData, FILE_APPEND | LOCK_EX);
    }

    /**
     * Behandelt Alerts (Versenden, Logging, etc.)
     */
    private function handleAlerts(array $alerts): void
    {
        foreach ($alerts as $alert) {
            // Log Alert
            $this->log("🚨 ALERT [{$alert['severity']}] {$alert['type']}: {$alert['message']}");
            
            // Kritische Alerts an MonitorAgent weiterleiten
            if ($alert['severity'] === 'critical') {
                try {
                    $this->monitorAgent->triggerAlert(
                        $alert['type'],
                        $alert['message'],
                        $alert
                    );
                } catch (Exception $e) {
                    $this->log("❌ Fehler beim Triggern des Alerts: " . $e->getMessage());
                }
            }
        }
        
        // Zusammenfassung erstellen
        $criticalCount = count(array_filter($alerts, fn($a) => $a['severity'] === 'critical'));
        $warningCount = count(array_filter($alerts, fn($a) => $a['severity'] === 'warning'));
        $infoCount = count(array_filter($alerts, fn($a) => $a['severity'] === 'info'));
        
        $this->log("📊 Alert-Zusammenfassung: {$criticalCount} kritisch, {$warningCount} Warnung, {$infoCount} Info");
    }

    /**
     * Schreibt ins Log
     */
    private function log(string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] {$message}\n";
        
        echo $logEntry; // Ausgabe für Cron-Job
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Gibt aktuellen Status zurück
     */
    public function getStatus(): array
    {
        return [
            'last_run' => file_exists($this->logFile) ? filemtime($this->logFile) : null,
            'log_file_size' => file_exists($this->logFile) ? filesize($this->logFile) : 0,
            'thresholds' => $this->thresholds,
            'monitoring_active' => true
        ];
    }

    /**
     * CLI-Kommandos verarbeiten
     */
    public function handleCommand(string $command): void
    {
        switch ($command) {
            case 'run':
                $this->run();
                break;
                
            case 'status':
                $status = $this->getStatus();
                echo "I18n Monitor Status:\n";
                echo "Last Run: " . ($status['last_run'] ? date('Y-m-d H:i:s', $status['last_run']) : 'Never') . "\n";
                echo "Log Size: " . round($status['log_file_size'] / 1024, 2) . " KB\n";
                echo "Monitoring: " . ($status['monitoring_active'] ? 'Active' : 'Inactive') . "\n";
                break;
                
            case 'test':
                echo "🧪 Testing I18n Monitor...\n";
                $this->run();
                echo "✅ Test completed\n";
                break;
                
            default:
                echo "Usage: php scripts/i18n-monitor.php [run|status|test]\n";
                break;
        }
    }
}

// CLI-Ausführung
if (php_sapi_name() === 'cli') {
    $monitor = new I18nMonitor();
    $command = $argv[1] ?? 'run';
    $monitor->handleCommand($command);
} else {
    die("Dieses Script kann nur über die Kommandozeile ausgeführt werden.\n");
}