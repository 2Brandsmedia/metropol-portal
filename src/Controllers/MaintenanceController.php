<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Agents\MaintenanceAgent;
use App\Agents\MonitorAgent;
use Exception;

/**
 * MaintenanceController - API-Endpunkte für Wartungsfunktionen
 * 
 * Bietet REST-API für Wartungsoperationen und System-Monitoring.
 * Zugriff nur für Administratoren.
 * 
 * @author 2Brands Media GmbH
 */
class MaintenanceController
{
    private MaintenanceAgent $maintenanceAgent;
    private MonitorAgent $monitorAgent;

    public function __construct(MaintenanceAgent $maintenanceAgent, MonitorAgent $monitorAgent)
    {
        $this->maintenanceAgent = $maintenanceAgent;
        $this->monitorAgent = $monitorAgent;
    }

    /**
     * GET /api/maintenance/health
     * System-Gesundheitscheck
     */
    public function getHealthStatus(Request $request): Response
    {
        try {
            $healthCheck = $this->maintenanceAgent->performSystemHealthCheck();
            
            return Response::json([
                'success' => true,
                'data' => $healthCheck
            ]);

        } catch (Exception $e) {
            return Response::json([
                'success' => false,
                'error' => 'Health check failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/maintenance/run
     * Wartung manuell ausführen
     */
    public function runMaintenance(Request $request): Response
    {
        try {
            $data = $request->json();
            $schedule = $data['schedule'] ?? 'daily';
            
            if (!in_array($schedule, ['hourly', 'daily', 'weekly', 'monthly', 'emergency'])) {
                return Response::json([
                    'success' => false,
                    'error' => 'Invalid schedule. Use: hourly, daily, weekly, monthly, emergency'
                ], 400);
            }

            $result = $this->maintenanceAgent->runScheduledMaintenance($schedule);
            
            return Response::json([
                'success' => true,
                'data' => $result
            ]);

        } catch (Exception $e) {
            return Response::json([
                'success' => false,
                'error' => 'Maintenance failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/maintenance/emergency
     * Notfall-Wartung ausführen
     */
    public function runEmergencyMaintenance(Request $request): Response
    {
        try {
            $data = $request->json();
            $reason = $data['reason'] ?? 'manual_trigger';
            
            $result = $this->maintenanceAgent->runEmergencyMaintenance($reason);
            
            return Response::json([
                'success' => true,
                'data' => $result
            ]);

        } catch (Exception $e) {
            return Response::json([
                'success' => false,
                'error' => 'Emergency maintenance failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/maintenance/task/{task}
     * Einzelne Wartungsaufgabe ausführen
     */
    public function runTask(Request $request, string $task): Response
    {
        try {
            $result = $this->maintenanceAgent->executeMaintenanceTask($task);
            
            return Response::json([
                'success' => true,
                'data' => $result
            ]);

        } catch (Exception $e) {
            return Response::json([
                'success' => false,
                'error' => "Task '{$task}' failed: " . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/maintenance/diagnostics
     * System-Diagnose abrufen
     */
    public function getDiagnostics(Request $request): Response
    {
        try {
            // Einfache Diagnose-Daten (vollständige Diagnose über CLI-Skript)
            $diagnostics = [
                'timestamp' => date('c'),
                'health_check' => $this->maintenanceAgent->performSystemHealthCheck(),
                'system_info' => [
                    'php_version' => PHP_VERSION,
                    'memory_usage' => memory_get_usage(true),
                    'peak_memory' => memory_get_peak_usage(true),
                    'memory_limit' => ini_get('memory_limit')
                ]
            ];
            
            return Response::json([
                'success' => true,
                'data' => $diagnostics
            ]);

        } catch (Exception $e) {
            return Response::json([
                'success' => false,
                'error' => 'Diagnostics failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/maintenance/status
     * Wartungsstatus und -historie
     */
    public function getMaintenanceStatus(Request $request): Response
    {
        try {
            // Letzten Wartungslauf aus Audit-Log laden
            $db = app('database');
            
            $lastMaintenance = $db->select(
                'SELECT action, details, created_at 
                 FROM audit_log 
                 WHERE action LIKE "maintenance_%" 
                 ORDER BY created_at DESC 
                 LIMIT 10'
            );

            $upcomingMaintenance = [
                'next_daily' => $this->getNextMaintenanceTime('daily'),
                'next_weekly' => $this->getNextMaintenanceTime('weekly'),
                'next_monthly' => $this->getNextMaintenanceTime('monthly')
            ];

            return Response::json([
                'success' => true,
                'data' => [
                    'last_maintenance' => $lastMaintenance,
                    'upcoming_maintenance' => $upcomingMaintenance,
                    'health_score' => $this->maintenanceAgent->performSystemHealthCheck()['health_score']
                ]
            ]);

        } catch (Exception $e) {
            return Response::json([
                'success' => false,
                'error' => 'Status check failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/maintenance/metrics
     * Performance- und System-Metriken
     */
    public function getMetrics(Request $request): Response
    {
        try {
            $db = app('database');
            $timeframe = $request->query('timeframe', '24h');
            
            // Zeitraum-Mapping
            $intervalMap = [
                '1h' => '1 HOUR',
                '24h' => '24 HOUR',
                '7d' => '7 DAY',
                '30d' => '30 DAY'
            ];
            
            $interval = $intervalMap[$timeframe] ?? '24 HOUR';

            // Performance-Metriken
            $performanceMetrics = $db->select(
                "SELECT 
                    endpoint,
                    AVG(response_time_ms) as avg_response_time,
                    MIN(response_time_ms) as min_response_time,
                    MAX(response_time_ms) as max_response_time,
                    COUNT(*) as request_count
                 FROM performance_metrics 
                 WHERE created_at > DATE_SUB(NOW(), INTERVAL {$interval})
                 GROUP BY endpoint
                 ORDER BY avg_response_time DESC"
            );

            // System-Metriken
            $systemMetrics = $db->select(
                "SELECT 
                    metric_type,
                    AVG(value) as avg_value,
                    MAX(value) as max_value,
                    AVG(percentage) as avg_percentage,
                    MAX(percentage) as max_percentage
                 FROM system_metrics 
                 WHERE measured_at > DATE_SUB(NOW(), INTERVAL {$interval})
                 GROUP BY metric_type"
            );

            // Error-Rate
            $errorMetrics = $db->select(
                "SELECT 
                    DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') as hour,
                    COUNT(*) as error_count,
                    severity
                 FROM error_logs 
                 WHERE created_at > DATE_SUB(NOW(), INTERVAL {$interval})
                 GROUP BY hour, severity
                 ORDER BY hour DESC"
            );

            return Response::json([
                'success' => true,
                'data' => [
                    'timeframe' => $timeframe,
                    'performance_metrics' => $performanceMetrics,
                    'system_metrics' => $systemMetrics,
                    'error_metrics' => $errorMetrics
                ]
            ]);

        } catch (Exception $e) {
            return Response::json([
                'success' => false,
                'error' => 'Metrics retrieval failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/maintenance/cache/clear
     * Cache leeren
     */
    public function clearCache(Request $request): Response
    {
        try {
            $result = $this->maintenanceAgent->executeMaintenanceTask('cache_cleanup');
            
            return Response::json([
                'success' => true,
                'message' => 'Cache cleared successfully',
                'data' => $result
            ]);

        } catch (Exception $e) {
            return Response::json([
                'success' => false,
                'error' => 'Cache clear failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/maintenance/database/optimize
     * Datenbank optimieren
     */
    public function optimizeDatabase(Request $request): Response
    {
        try {
            $result = $this->maintenanceAgent->executeMaintenanceTask('database_optimization');
            
            return Response::json([
                'success' => true,
                'message' => 'Database optimized successfully',
                'data' => $result
            ]);

        } catch (Exception $e) {
            return Response::json([
                'success' => false,
                'error' => 'Database optimization failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/maintenance/logs
     * Wartungs-Logs abrufen
     */
    public function getLogs(Request $request): Response
    {
        try {
            $db = app('database');
            $limit = min((int) $request->query('limit', 50), 200); // Max 200 Einträge
            $level = $request->query('level'); // Optional: Filter nach Log-Level
            
            $sql = 'SELECT action, details, created_at, ip_address 
                    FROM audit_log 
                    WHERE action LIKE "maintenance_%"';
            $params = [];
            
            if ($level) {
                $sql .= ' AND action LIKE ?';
                $params[] = "maintenance_{$level}%";
            }
            
            $sql .= ' ORDER BY created_at DESC LIMIT ?';
            $params[] = $limit;
            
            $logs = $db->select($sql, $params);
            
            return Response::json([
                'success' => true,
                'data' => [
                    'logs' => $logs,
                    'total_count' => count($logs),
                    'limit' => $limit
                ]
            ]);

        } catch (Exception $e) {
            return Response::json([
                'success' => false,
                'error' => 'Log retrieval failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Hilfsmethode: Nächster Wartungstermin berechnen
     */
    private function getNextMaintenanceTime(string $schedule): string
    {
        $now = new \DateTime();
        
        switch ($schedule) {
            case 'daily':
                $next = clone $now;
                $next->setTime(3, 0, 0); // 3 Uhr morgens
                if ($next <= $now) {
                    $next->add(new \DateInterval('P1D'));
                }
                return $next->format('c');
                
            case 'weekly':
                $next = clone $now;
                $next->setTime(3, 30, 0); // 3:30 Uhr morgens
                $next->modify('next sunday');
                return $next->format('c');
                
            case 'monthly':
                $next = clone $now;
                $next->setTime(4, 0, 0); // 4 Uhr morgens
                $next->modify('first day of next month');
                return $next->format('c');
                
            default:
                return $now->format('c');
        }
    }
}