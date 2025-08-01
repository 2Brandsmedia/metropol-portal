<?php

declare(strict_types=1);

/**
 * Monitoring-System Setup-Skript
 * 
 * Initialisiert das komplette Monitoring-System:
 * - Datenbank-Tabellen erstellen
 * - Standard-Alerts konfigurieren
 * - Monitoring-Middleware registrieren
 * - Cron-Jobs für automatische Überwachung einrichten
 * 
 * @author 2Brands Media GmbH
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Core\Config;
use App\Agents\MonitorAgent;
use App\Services\AlertService;
use App\Services\SystemResourceMonitor;
use App\Services\PerformanceAnalysisService;
use App\Agents\MonitoringIntegrationAgent;

echo "=== Metropol Portal Monitoring Setup ===\n\n";

try {
    // Konfiguration laden
    $config = new Config();
    $db = new Database($config);
    
    echo "✓ Datenbankverbindung hergestellt\n";

    // 1. Monitoring-Tabellen erstellen
    echo "\n1. Erstelle Monitoring-Tabellen...\n";
    
    $migrationFile = __DIR__ . '/../database/migrations/021_create_monitoring_tables.php';
    if (file_exists($migrationFile)) {
        require_once $migrationFile;
        $migration = new CreateMonitoringTables($db->getPdo());
        $migration->up();
        echo "✓ Monitoring-Tabellen erstellt\n";
    } else {
        echo "⚠ Migration-Datei nicht gefunden: {$migrationFile}\n";
    }

    // 2. MonitorAgent initialisieren
    echo "\n2. Initialisiere MonitorAgent...\n";
    $monitor = new MonitorAgent($db, [
        'enable_performance_monitoring' => true,
        'enable_error_logging' => true,
        'enable_system_monitoring' => true,
        'enable_alerts' => true,
        'slow_query_threshold_ms' => 100,
        'memory_limit_mb' => 128
    ]);
    echo "✓ MonitorAgent initialisiert\n";

    // 3. AlertService initialisieren
    echo "\n3. Initialisiere AlertService...\n";
    $alertService = new AlertService($db, [
        'smtp_host' => 'smtp.all-inkl.com',
        'smtp_port' => 587,
        'default_alert_email' => 'admin@2brands-media.de',
        'alert_cooldown_minutes' => 15
    ]);
    echo "✓ AlertService initialisiert\n";

    // 4. Standard-Alerts erstellen
    echo "\n4. Erstelle Standard-Alert-Regeln...\n";
    $integrationAgent = new MonitoringIntegrationAgent($db, $monitor, $alertService);
    $defaultAlerts = $integrationAgent->setupDefaultAlerts();
    echo "✓ " . count($defaultAlerts) . " Standard-Alerts erstellt\n";

    // 5. System-Resource-Monitor initialisieren
    echo "\n5. Initialisiere System-Resource-Monitor...\n";
    $resourceMonitor = new SystemResourceMonitor($db, $monitor, [
        'collection_interval_seconds' => 300,
        'retention_days' => 30
    ]);
    
    // Erste Metriken sammeln
    $metrics = $resourceMonitor->collectSystemMetrics();
    echo "✓ System-Resource-Monitor initialisiert (" . count($metrics) . " Metriken gesammelt)\n";

    // 6. Performance-Analysis-Service initialisieren
    echo "\n6. Initialisiere Performance-Analysis-Service...\n";
    $performanceAnalysis = new PerformanceAnalysisService($db, $monitor, $alertService, [
        'regression_threshold_percent' => 25,
        'trend_analysis_days' => 7,
        'min_samples_for_analysis' => 100
    ]);
    
    // Baselines berechnen (falls Daten vorhanden)
    try {
        $baselines = $performanceAnalysis->updatePerformanceBaselines();
        echo "✓ Performance-Analysis-Service initialisiert (" . count($baselines) . " Baselines berechnet)\n";
    } catch (Exception $e) {
        echo "✓ Performance-Analysis-Service initialisiert (keine Baseline-Daten verfügbar)\n";
    }

    // 7. Monitoring-Dashboard-Routes registrieren
    echo "\n7. Registriere Dashboard-Routes...\n";
    $routeConfig = [
        '/admin/monitoring' => 'MonitoringController@dashboard',
        '/api/monitoring/live-metrics' => 'MonitoringController@liveMetrics',
        '/api/monitoring/health' => 'MonitoringController@health',
        '/api/monitoring/performance-report' => 'MonitoringController@performanceReport',
        '/api/monitoring/error-report' => 'MonitoringController@errorReport',
        '/api/monitoring/alerts' => 'MonitoringController@alerts'
    ];
    
    echo "✓ " . count($routeConfig) . " Dashboard-Routes definiert\n";

    // 8. Cron-Job-Konfiguration generieren
    echo "\n8. Generiere Cron-Job-Konfiguration...\n";
    $cronJobs = generateCronConfiguration();
    file_put_contents(__DIR__ . '/../monitoring-crontab', $cronJobs);
    echo "✓ Cron-Job-Konfiguration erstellt: monitoring-crontab\n";

    // 9. Test der Monitoring-Funktionen
    echo "\n9. Teste Monitoring-Funktionen...\n";
    
    // Health-Check durchführen
    $healthCheck = $monitor->healthCheck();
    if ($healthCheck['healthy']) {
        echo "✓ System-Health-Check erfolgreich\n";
    } else {
        echo "⚠ System-Health-Check mit Warnungen\n";
    }

    // Test-Alert senden
    try {
        $testAlertResults = $alertService->sendTestAlert();
        $successfulChannels = array_filter($testAlertResults, fn($r) => $r['success']);
        echo "✓ Test-Alert erfolgreich an " . count($successfulChannels) . " Kanäle gesendet\n";
    } catch (Exception $e) {
        echo "⚠ Test-Alert fehlgeschlagen: " . $e->getMessage() . "\n";
    }

    // 10. Abschlussmeldung und nächste Schritte
    echo "\n=== Setup erfolgreich abgeschlossen! ===\n\n";
    
    echo "Nächste Schritte:\n";
    echo "1. Cron-Jobs installieren: crontab monitoring-crontab\n";
    echo "2. Monitoring-Dashboard aufrufen: /admin/monitoring\n";
    echo "3. SMTP-Konfiguration für E-Mail-Alerts anpassen\n";
    echo "4. Webhook-URLs für externe Benachrichtigungen konfigurieren\n";
    echo "5. Performance-Baselines nach 24h Betrieb überprüfen\n\n";

    echo "Monitoring-Endpoints:\n";
    foreach ($routeConfig as $route => $controller) {
        echo "- {$route}\n";
    }
    
    echo "\nLog-Dateien:\n";
    echo "- /tmp/metropol_alerts.log (Alert-Logs)\n";
    echo "- /tmp/metropol_critical_alerts.log (Kritische Alerts)\n";
    echo "- error_log (System-Errors)\n\n";

} catch (Exception $e) {
    echo "\n❌ Setup fehlgeschlagen: " . $e->getMessage() . "\n";
    echo "Stack-Trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

/**
 * Generiert Cron-Job-Konfiguration für automatisches Monitoring
 */
function generateCronConfiguration(): string
{
    $phpPath = PHP_BINARY;
    $scriptPath = __DIR__;
    
    return "# Metropol Portal Monitoring Cron Jobs
# Entwickelt von 2Brands Media GmbH

# System-Metriken alle 5 Minuten sammeln
*/5 * * * * {$phpPath} {$scriptPath}/collect-metrics.php >> /tmp/monitoring-cron.log 2>&1

# Performance-Analyse alle 15 Minuten
*/15 * * * * {$phpPath} {$scriptPath}/analyze-performance.php >> /tmp/monitoring-cron.log 2>&1

# Alert-Evaluierung jede Minute
* * * * * {$phpPath} {$scriptPath}/evaluate-alerts.php >> /tmp/monitoring-cron.log 2>&1

# Alte Metriken täglich um 2:00 Uhr bereinigen
0 2 * * * {$phpPath} {$scriptPath}/cleanup-metrics.php >> /tmp/monitoring-cron.log 2>&1

# Health-Check alle 10 Minuten
*/10 * * * * {$phpPath} {$scriptPath}/health-check.php >> /tmp/monitoring-cron.log 2>&1

# Performance-Baselines wöchentlich am Sonntag um 3:00 Uhr aktualisieren
0 3 * * 0 {$phpPath} {$scriptPath}/update-baselines.php >> /tmp/monitoring-cron.log 2>&1

";
}

echo "Setup-Skript beendet.\n";