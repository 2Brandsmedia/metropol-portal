<?php

declare(strict_types=1);

/**
 * API Limit Monitor - Automatische Überwachung und Updates
 * 
 * Cron-Job für täglich automatische API-Limit-Checks
 * Läuft täglich um 06:00 Uhr: 0 6 * * * /usr/bin/php /path/to/api-limit-monitor.php
 * 
 * @author 2Brands Media GmbH
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Core\Config;
use App\Agents\APILimitAgent;
use App\Services\AlertService;

try {
    echo "[" . date('Y-m-d H:i:s') . "] Starting API Limit Monitor...\n";
    
    // Konfiguration laden
    $config = new Config();
    $db = new Database($config);
    
    // APILimitAgent initialisieren
    $apiLimitAgent = new APILimitAgent($db, $config);
    $alertService = new AlertService($db, $config);
    
    // 1. API-Limit-Änderungen prüfen
    echo "[" . date('Y-m-d H:i:s') . "] Checking for API limit changes...\n";
    $limitChanges = $apiLimitAgent->detectApiLimitChanges();
    
    if (!empty($limitChanges)) {
        echo "[" . date('Y-m-d H:i:s') . "] Found " . count($limitChanges) . " API limit changes\n";
        
        foreach ($limitChanges as $provider => $changes) {
            echo "  - {$provider}: " . json_encode($changes) . "\n";
        }
    } else {
        echo "[" . date('Y-m-d H:i:s') . "] No API limit changes detected\n";
    }
    
    // 2. Budget-Überwachung
    echo "[" . date('Y-m-d H:i:s') . "] Monitoring cost budgets...\n";
    $budgetAlerts = $apiLimitAgent->monitorCostBudgets();
    
    if (!empty($budgetAlerts)) {
        echo "[" . date('Y-m-d H:i:s') . "] Found " . count($budgetAlerts) . " budget alerts\n";
        
        foreach ($budgetAlerts as $alert) {
            echo "  - {$alert['provider']}: {$alert['percentage']}% of budget used\n";
            
            if ($alert['level'] === 'critical') {
                echo "    CRITICAL: Emergency measures activated!\n";
            }
        }
    } else {
        echo "[" . date('Y-m-d H:i:s') . "] All budgets within limits\n";
    }
    
    // 3. Kapazitätsplanung
    echo "[" . date('Y-m-d H:i:s') . "] Predicting capacity needs...\n";
    $capacityPredictions = $apiLimitAgent->predictCapacityNeeds();
    
    foreach ($capacityPredictions as $provider => $prediction) {
        echo "  - {$provider}: {$prediction['recommendation']} (Days until limit: {$prediction['days_until_limit']})\n";
        
        if ($prediction['recommendation'] === 'urgent_capacity_increase_needed') {
            echo "    WARNING: Urgent capacity increase needed!\n";
            
            // Alert senden
            $alertService->sendAlert([
                'type' => 'urgent_capacity_increase',
                'provider' => $provider,
                'prediction' => $prediction,
                'priority' => 'high'
            ]);
        }
    }
    
    // 4. API-Gesundheit überwachen
    echo "[" . date('Y-m-d H:i:s') . "] Monitoring API health...\n";
    $healthStatus = $apiLimitAgent->monitorApiHealth();
    
    foreach ($healthStatus as $provider => $health) {
        $status = $health['overall_health'];
        echo "  - {$provider}: {$status} (Response: {$health['response_time']}ms, Errors: {$health['error_rate']}%)\n";
        
        if ($status === 'unhealthy') {
            echo "    ALERT: API is unhealthy!\n";
            
            $alertService->sendAlert([
                'type' => 'api_unhealthy',
                'provider' => $provider,
                'health' => $health,
                'priority' => 'high'
            ]);
        }
    }
    
    // 5. Dashboard-Daten aktualisieren
    echo "[" . date('Y-m-d H:i:s') . "] Updating dashboard data...\n";
    $dashboardData = $apiLimitAgent->getDashboardData();
    
    // Cache-Dashboard-Daten für schnellen Zugriff
    $cacheKey = 'api_dashboard_data';
    $db->query(
        'INSERT INTO cache (cache_key, data, expires_at) 
         VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))
         ON DUPLICATE KEY UPDATE 
            data = VALUES(data),
            expires_at = VALUES(expires_at)',
        [$cacheKey, json_encode($dashboardData)]
    );
    
    // 6. Alte Logs aufräumen (> 30 Tage)
    echo "[" . date('Y-m-d H:i:s') . "] Cleaning up old logs...\n";
    
    $cleanupTables = [
        'api_usage' => 30,
        'api_errors' => 30,
        'api_request_metadata' => 7,
        'api_warnings' => 30,
        'api_fallback_log' => 30
    ];
    
    foreach ($cleanupTables as $table => $days) {
        try {
            $cutoffDate = date('Y-m-d', strtotime("-{$days} days"));
            $deleted = $db->delete($table, 'created_at < ?', [$cutoffDate]);
            
            if ($deleted > 0) {
                echo "  - Cleaned {$deleted} old records from {$table}\n";
            }
        } catch (Exception $e) {
            echo "  - Warning: Failed to clean {$table}: " . $e->getMessage() . "\n";
        }
    }
    
    // 7. Statistiken generieren
    echo "[" . date('Y-m-d H:i:s') . "] Generating statistics...\n";
    
    $stats = [
        'timestamp' => date('Y-m-d H:i:s'),
        'limit_changes' => count($limitChanges),
        'budget_alerts' => count($budgetAlerts),
        'critical_alerts' => count(array_filter($budgetAlerts, fn($a) => $a['level'] === 'critical')),
        'unhealthy_apis' => count(array_filter($healthStatus, fn($h) => $h['overall_health'] === 'unhealthy')),
        'total_requests_today' => getTotalRequestsToday($db),
        'total_cost_today' => getTotalCostToday($db, $apiLimitAgent),
        'cache_hit_rate' => getCacheHitRate($db)
    ];
    
    // Statistiken loggen
    $db->insert('api_monitor_stats', [
        'stats_date' => date('Y-m-d'),
        'stats_data' => json_encode($stats),
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    echo "[" . date('Y-m-d H:i:s') . "] Monitor completed successfully\n";
    echo "Statistics: " . json_encode($stats, JSON_PRETTY_PRINT) . "\n";
    
    // Erfolgs-Benachrichtigung (nur bei wichtigen Ereignissen)
    if ($stats['critical_alerts'] > 0 || $stats['unhealthy_apis'] > 0) {
        $alertService->sendAlert([
            'type' => 'daily_monitor_report',
            'stats' => $stats,
            'priority' => 'medium'
        ]);
    }
    
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    
    // Fehler-Alert senden
    try {
        if (isset($alertService)) {
            $alertService->sendAlert([
                'type' => 'monitor_script_error',
                'error' => $e->getMessage(),
                'priority' => 'high'
            ]);
        }
    } catch (Exception $alertError) {
        echo "[" . date('Y-m-d H:i:s') . "] Failed to send error alert: " . $alertError->getMessage() . "\n";
    }
    
    exit(1);
}

/**
 * Helper-Funktionen
 */
function getTotalRequestsToday(Database $db): int
{
    $result = $db->selectOne(
        'SELECT SUM(request_count) as total 
         FROM api_usage 
         WHERE period_type = "daily" AND period_key = CURDATE()'
    );
    
    return (int) ($result['total'] ?? 0);
}

function getTotalCostToday(Database $db, APILimitAgent $agent): float
{
    $totalCost = 0.0;
    
    $providers = ['google_maps', 'nominatim', 'openrouteservice'];
    
    foreach ($providers as $provider) {
        $usage = $db->selectOne(
            'SELECT request_count 
             FROM api_usage 
             WHERE api_provider = ? AND period_type = "daily" AND period_key = CURDATE()',
            [$provider]
        );
        
        if ($usage) {
            // Hier würde man die echten Kosten pro Request laden
            $costPerRequest = match($provider) {
                'google_maps' => 0.005,
                'nominatim' => 0.0,
                'openrouteservice' => 0.0,
                default => 0.0
            };
            
            $totalCost += (int)$usage['request_count'] * $costPerRequest;
        }
    }
    
    return $totalCost;
}

function getCacheHitRate(Database $db): float
{
    // Vereinfachte Cache-Hit-Rate-Berechnung
    $result = $db->selectOne(
        'SELECT 
            COUNT(*) as cache_requests,
            COUNT(CASE WHEN expires_at > NOW() THEN 1 END) as cache_hits
         FROM cache 
         WHERE created_at >= CURDATE()'
    );
    
    if (!$result || $result['cache_requests'] == 0) {
        return 0.0;
    }
    
    return round(($result['cache_hits'] / $result['cache_requests']) * 100, 2);
}