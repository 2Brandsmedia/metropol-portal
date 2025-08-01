<?php

declare(strict_types=1);

/**
 * Health Monitor - Kontinuierliche System-Gesundheitsüberwachung
 * 
 * Überwacht das System kontinuierlich und löst bei Bedarf automatische
 * Wartungsmaßnahmen oder Alerts aus.
 * 
 * Ausführung alle 5 Minuten via Cron:
 * */5 * * * * php /path/to/scripts/health-monitor.php
 * 
 * @author 2Brands Media GmbH
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Core\Config;
use App\Agents\MaintenanceAgent;
use App\Agents\MonitorAgent;

// Konfiguration
$config = new Config();
$db = new Database($config->get('database'));

$maintenanceAgent = new MaintenanceAgent($db, [
    'emergency_maintenance_threshold' => 95,
    'enable_automated_maintenance' => true
]);

$monitorAgent = new MonitorAgent($db, [
    'enable_system_monitoring' => true,
    'enable_alerts' => true
]);

// Timestamp für Logging
$timestamp = date('Y-m-d H:i:s');
echo "[{$timestamp}] Starting health monitoring check\n";

try {
    // System-Gesundheitscheck durchführen
    $healthCheck = $maintenanceAgent->performSystemHealthCheck();
    $healthScore = $healthCheck['health_score'];
    
    echo "Current health score: {$healthScore}/100\n";

    // System-Metriken sammeln
    $monitorAgent->collectSystemMetrics();

    // Kritische Zustände prüfen
    $criticalIssues = [];
    
    foreach ($healthCheck['checks'] as $checkName => $checkResult) {
        if (!($checkResult['healthy'] ?? true)) {
            $criticalIssues[] = $checkName . ': ' . ($checkResult['message'] ?? 'Failed');
            echo "⚠ {$checkName}: {$checkResult['message']}\n";
        }
    }

    // Automatische Maßnahmen bei kritischen Zuständen
    if ($healthScore < 50) {
        echo "CRITICAL: Health score below 50% - triggering emergency maintenance\n";
        
        $emergencyResult = $maintenanceAgent->runEmergencyMaintenance('health_monitor_critical');
        
        if ($emergencyResult['success'] ?? false) {
            echo "Emergency maintenance completed successfully\n";
        } else {
            echo "Emergency maintenance failed: " . ($emergencyResult['error'] ?? 'Unknown error') . "\n";
        }
        
    } elseif ($healthScore < 70) {
        echo "WARNING: Health score below 70% - scheduling maintenance tasks\n";
        
        // Spezifische Wartungsaufgaben für häufige Probleme
        $maintenanceTasks = [];
        
        if (!$healthCheck['checks']['memory_usage']['healthy']) {
            $maintenanceTasks[] = 'cache_cleanup';
            $maintenanceTasks[] = 'session_cleanup';
        }
        
        if (!$healthCheck['checks']['disk_space']['healthy']) {
            $maintenanceTasks[] = 'temp_file_cleanup';
            $maintenanceTasks[] = 'log_rotation';
        }
        
        if (!$healthCheck['checks']['performance_metrics']['healthy']) {
            $maintenanceTasks[] = 'database_optimization';
        }
        
        // Wartungsaufgaben ausführen
        foreach ($maintenanceTasks as $task) {
            echo "Executing maintenance task: {$task}\n";
            $taskResult = $maintenanceAgent->executeMaintenanceTask($task);
            
            if ($taskResult['success']) {
                echo "  ✓ {$task} completed in {$taskResult['duration_seconds']}s\n";
            } else {
                echo "  ✗ {$task} failed: {$taskResult['error']}\n";
            }
        }
    }

    // Performance-Metriken prüfen
    $performanceIssues = checkPerformanceMetrics($db);
    if (!empty($performanceIssues)) {
        echo "Performance issues detected:\n";
        foreach ($performanceIssues as $issue) {
            echo "  - {$issue}\n";
        }
    }

    // Resource-Monitoring
    $resourceStatus = checkResourceUsage($db);
    if ($resourceStatus['critical']) {
        echo "CRITICAL: Resource usage exceeded thresholds\n";
        foreach ($resourceStatus['issues'] as $issue) {
            echo "  - {$issue}\n";
        }
    }

    // Sicherheits-Monitoring
    $securityStatus = checkSecurityMetrics($db);
    if ($securityStatus['threats_detected']) {
        echo "Security threats detected:\n";
        foreach ($securityStatus['threats'] as $threat) {
            echo "  - {$threat}\n";
        }
    }

    // Status in Datenbank loggen
    logHealthStatus($db, $healthScore, $criticalIssues, $performanceIssues);

    echo "Health monitoring completed successfully\n";
    exit(0);

} catch (Exception $e) {
    echo "ERROR: Health monitoring failed: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
    
    // Kritischen Fehler loggen
    try {
        $monitorAgent->logError($e, 'critical', ['context' => 'health_monitor']);
    } catch (Exception $logException) {
        echo "Failed to log error: " . $logException->getMessage() . "\n";
    }
    
    exit(1);
}

/**
 * Prüft Performance-Metriken der letzten 10 Minuten
 */
function checkPerformanceMetrics(Database $db): array
{
    $issues = [];
    
    try {
        // Durchschnittliche Response-Zeit prüfen
        $avgResponseTime = $db->selectOne(
            'SELECT AVG(response_time_ms) as avg_response 
             FROM performance_metrics 
             WHERE created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)'
        );
        
        $avgResponse = (float) ($avgResponseTime['avg_response'] ?? 0);
        if ($avgResponse > 500) {
            $issues[] = "High average response time: {$avgResponse}ms";
        }
        
        // Slow Queries prüfen
        $slowQueries = $db->selectOne(
            'SELECT COUNT(*) as count 
             FROM query_performance 
             WHERE execution_time_ms > 100 
             AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)'
        );
        
        $slowCount = (int) ($slowQueries['count'] ?? 0);
        if ($slowCount > 10) {
            $issues[] = "High number of slow queries: {$slowCount}";
        }
        
        // Error Rate prüfen
        $errorRate = $db->selectOne(
            'SELECT 
                (SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) * 100.0 / COUNT(*)) as error_percentage
             FROM performance_metrics 
             WHERE created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)'
        );
        
        $errorPercent = (float) ($errorRate['error_percentage'] ?? 0);
        if ($errorPercent > 5) {
            $issues[] = "High error rate: {$errorPercent}%";
        }
        
    } catch (Exception $e) {
        $issues[] = "Failed to check performance metrics: " . $e->getMessage();
    }
    
    return $issues;
}

/**
 * Prüft Ressourcenverbrauch
 */
function checkResourceUsage(Database $db): array
{
    $issues = [];
    $critical = false;
    
    try {
        // Memory Usage
        $memoryUsage = $db->selectOne(
            'SELECT percentage FROM system_metrics 
             WHERE metric_type = "memory" 
             ORDER BY measured_at DESC 
             LIMIT 1'
        );
        
        $memoryPercent = (float) ($memoryUsage['percentage'] ?? 0);
        if ($memoryPercent > 90) {
            $issues[] = "Critical memory usage: {$memoryPercent}%";
            $critical = true;
        } elseif ($memoryPercent > 80) {
            $issues[] = "High memory usage: {$memoryPercent}%";
        }
        
        // Disk Usage
        $diskUsage = $db->selectOne(
            'SELECT percentage FROM system_metrics 
             WHERE metric_type = "disk" 
             ORDER BY measured_at DESC 
             LIMIT 1'
        );
        
        $diskPercent = (float) ($diskUsage['percentage'] ?? 0);
        if ($diskPercent > 95) {
            $issues[] = "Critical disk usage: {$diskPercent}%";
            $critical = true;
        } elseif ($diskPercent > 85) {
            $issues[] = "High disk usage: {$diskPercent}%";
        }
        
    } catch (Exception $e) {
        $issues[] = "Failed to check resource usage: " . $e->getMessage();
        $critical = true;
    }
    
    return ['critical' => $critical, 'issues' => $issues];
}

/**
 * Prüft Sicherheits-Metriken
 */
function checkSecurityMetrics(Database $db): array
{
    $threats = [];
    $threatsDetected = false;
    
    try {
        // Fehlgeschlagene Logins in letzten 15 Minuten
        $failedLogins = $db->selectOne(
            'SELECT COUNT(*) as count FROM error_logs 
             WHERE message LIKE "%login%" 
             AND severity IN ("warning", "error") 
             AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)'
        );
        
        $loginFailures = (int) ($failedLogins['count'] ?? 0);
        if ($loginFailures > 20) {
            $threats[] = "High number of failed logins: {$loginFailures}";
            $threatsDetected = true;
        }
        
        // Kritische Sicherheitsfehler
        $criticalErrors = $db->selectOne(
            'SELECT COUNT(*) as count FROM error_logs 
             WHERE severity = "critical" 
             AND (error_type LIKE "%Security%" OR message LIKE "%security%")
             AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)'
        );
        
        $securityErrors = (int) ($criticalErrors['count'] ?? 0);
        if ($securityErrors > 0) {
            $threats[] = "Critical security errors detected: {$securityErrors}";
            $threatsDetected = true;
        }
        
        // Verdächtige IP-Aktivität
        $suspiciousActivity = $db->select(
            'SELECT ip_address, COUNT(*) as request_count 
             FROM error_logs 
             WHERE created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
             AND ip_address IS NOT NULL 
             GROUP BY ip_address 
             HAVING request_count > 50'
        );
        
        if (!empty($suspiciousActivity)) {
            foreach ($suspiciousActivity as $activity) {
                $threats[] = "Suspicious activity from IP {$activity['ip_address']}: {$activity['request_count']} requests";
                $threatsDetected = true;
            }
        }
        
    } catch (Exception $e) {
        $threats[] = "Failed to check security metrics: " . $e->getMessage();
        $threatsDetected = true;
    }
    
    return ['threats_detected' => $threatsDetected, 'threats' => $threats];
}

/**
 * Loggt Health-Status in Datenbank
 */
function logHealthStatus(Database $db, int $healthScore, array $criticalIssues, array $performanceIssues): void
{
    try {
        $status = 'healthy';
        if ($healthScore < 50) {
            $status = 'critical';
        } elseif ($healthScore < 70) {
            $status = 'warning';
        } elseif (!empty($criticalIssues) || !empty($performanceIssues)) {
            $status = 'degraded';
        }
        
        $details = [
            'health_score' => $healthScore,
            'critical_issues' => $criticalIssues,
            'performance_issues' => $performanceIssues,
            'timestamp' => date('c')
        ];
        
        $db->insert(
            'INSERT INTO audit_log (user_id, action, resource_type, resource_id, details, ip_address, created_at) 
             VALUES (?, ?, ?, ?, ?, ?, NOW())',
            [
                null, // System user
                'health_check',
                'system',
                $status,
                json_encode($details),
                'health_monitor'
            ]
        );
        
    } catch (Exception $e) {
        echo "Warning: Failed to log health status: " . $e->getMessage() . "\n";
    }
}