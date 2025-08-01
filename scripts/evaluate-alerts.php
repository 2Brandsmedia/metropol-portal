<?php

declare(strict_types=1);

/**
 * Automatische Alert-Evaluierung für Cron-Job
 * 
 * Evaluiert alle aktiven Alert-Regeln und sendet Benachrichtigungen
 * 
 * @author 2Brands Media GmbH
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Core\Config;
use App\Agents\MonitorAgent;
use App\Services\AlertService;

try {
    $config = new Config();
    $db = new Database($config);
    $monitor = new MonitorAgent($db);
    $alertService = new AlertService($db);
    
    // Alle Alert-Regeln evaluieren
    $triggeredAlerts = $alertService->evaluateAlerts();
    
    $timestamp = date('Y-m-d H:i:s');
    echo "[{$timestamp}] Evaluated alerts";
    
    if (!empty($triggeredAlerts)) {
        echo ", " . count($triggeredAlerts) . " alerts triggered";
        
        // Kritische Alerts separat loggen
        $criticalAlerts = array_filter($triggeredAlerts, fn($a) => $a['severity'] === 'critical');
        if (!empty($criticalAlerts)) {
            error_log("CRITICAL: " . count($criticalAlerts) . " critical alerts triggered");
        }
    }
    
    echo "\n";

} catch (Exception $e) {
    error_log("Alert evaluation failed: " . $e->getMessage());
    exit(1);
}