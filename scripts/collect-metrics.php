<?php

declare(strict_types=1);

/**
 * Automatische Metriken-Sammlung für Cron-Job
 * 
 * Sammelt alle System-Metriken und führt Performance-Überwachung durch
 * 
 * @author 2Brands Media GmbH
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Core\Config;
use App\Agents\MonitorAgent;
use App\Services\SystemResourceMonitor;

try {
    $config = new Config();
    $db = new Database($config);
    $monitor = new MonitorAgent($db);
    $resourceMonitor = new SystemResourceMonitor($db, $monitor);
    
    // System-Metriken sammeln
    $metrics = $resourceMonitor->collectSystemMetrics();
    
    // Resource-Alerts prüfen
    $alerts = $resourceMonitor->checkResourceAlerts();
    
    $timestamp = date('Y-m-d H:i:s');
    echo "[{$timestamp}] Collected " . count($metrics) . " metrics";
    
    if (!empty($alerts)) {
        echo ", " . count($alerts) . " alerts triggered";
        
        // Kritische Alerts loggen
        foreach ($alerts as $alert) {
            if ($alert['severity'] === 'critical') {
                error_log("CRITICAL ALERT: " . $alert['message']);
            }
        }
    }
    
    echo "\n";

} catch (Exception $e) {
    error_log("Metrics collection failed: " . $e->getMessage());
    exit(1);
}