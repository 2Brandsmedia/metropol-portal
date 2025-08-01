<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use App\Agents\MonitorAgent;

/**
 * Monitoring Dashboard Controller
 * 
 * Stellt API-Endpoints für das Real-Time Monitoring Dashboard bereit
 * 
 * @author 2Brands Media GmbH
 */
class MonitoringController
{
    private Database $db;
    private MonitorAgent $monitor;

    public function __construct(Database $db, MonitorAgent $monitor)
    {
        $this->db = $db;
        $this->monitor = $monitor;
    }

    /**
     * Dashboard-Hauptseite
     */
    public function dashboard(Request $request): Response
    {
        // Prüfen ob User Admin ist
        if (!$this->isAdmin($request)) {
            return new Response('Zugriff verweigert', 403);
        }

        ob_start();
        include __DIR__ . '/../../templates/monitoring/dashboard.php';
        $content = ob_get_clean();

        return new Response($content);
    }

    /**
     * Live-Metriken API
     */
    public function liveMetrics(Request $request): Response
    {
        if (!$this->isAdmin($request)) {
            return (new Response())->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $metrics = [
                'timestamp' => time(),
                'system' => $this->getSystemMetrics(),
                'performance' => $this->getPerformanceMetrics(),
                'errors' => $this->getErrorMetrics(),
                'api' => $this->getApiMetrics(),
                'alerts' => $this->getActiveAlerts()
            ];

            return (new Response())->json($metrics);

        } catch (\Exception $e) {
            return (new Response())->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * System-Health Status
     */
    public function health(Request $request): Response
    {
        try {
            $health = $this->monitor->healthCheck();
            $statusCode = $health['healthy'] ? 200 : 503;
            
            return (new Response())->json($health, $statusCode);

        } catch (\Exception $e) {
            return (new Response())->json([
                'healthy' => false,
                'error' => $e->getMessage(),
                'timestamp' => date('c')
            ], 500);
        }
    }

    /**
     * Performance-Report für bestimmten Zeitraum
     */
    public function performanceReport(Request $request): Response
    {
        if (!$this->isAdmin($request)) {
            return (new Response())->json(['error' => 'Unauthorized'], 403);
        }

        $hours = (int) ($request->query('hours') ?? 24);
        $endpoint = $request->query('endpoint');

        try {
            $report = [
                'period' => [
                    'hours' => $hours,
                    'from' => date('c', time() - $hours * 3600),
                    'to' => date('c')
                ],
                'summary' => $this->getPerformanceSummary($hours, $endpoint),
                'trends' => $this->getPerformanceTrends($hours, $endpoint),
                'slowest_endpoints' => $this->getSlowestEndpoints($hours),
                'performance_distribution' => $this->getPerformanceDistribution($hours, $endpoint)
            ];

            return (new Response())->json($report);

        } catch (\Exception $e) {
            return (new Response())->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Error-Analysis Report
     */
    public function errorReport(Request $request): Response
    {
        if (!$this->isAdmin($request)) {
            return (new Response())->json(['error' => 'Unauthorized'], 403);
        }

        $hours = (int) ($request->query('hours') ?? 24);

        try {
            $report = [
                'period' => [
                    'hours' => $hours,
                    'from' => date('c', time() - $hours * 3600),
                    'to' => date('c')
                ],
                'summary' => $this->getErrorSummary($hours),
                'by_severity' => $this->getErrorsBySeverity($hours),
                'by_type' => $this->getErrorsByType($hours),
                'top_errors' => $this->getTopErrors($hours),
                'error_trends' => $this->getErrorTrends($hours)
            ];

            return (new Response())->json($report);

        } catch (\Exception $e) {
            return (new Response())->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Alert-Management
     */
    public function alerts(Request $request): Response
    {
        if (!$this->isAdmin($request)) {
            return (new Response())->json(['error' => 'Unauthorized'], 403);
        }

        if ($request->getMethod() === 'POST') {
            return $this->createAlert($request);
        }

        try {
            $alerts = $this->db->select(
                'SELECT * FROM alerts ORDER BY severity DESC, created_at DESC'
            );

            return (new Response())->json($alerts);

        } catch (\Exception $e) {
            return (new Response())->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Alert-Konfiguration erstellen
     */
    private function createAlert(Request $request): Response
    {
        try {
            $data = $request->json();
            
            // Validierung
            $required = ['name', 'alert_type', 'metric_type', 'condition_operator', 'threshold_value', 'severity'];
            foreach ($required as $field) {
                if (!isset($data[$field])) {
                    return (new Response())->json(['error' => "Field {$field} is required"], 400);
                }
            }

            $this->db->insert(
                'INSERT INTO alerts (
                    name, description, alert_type, metric_type, condition_operator,
                    threshold_value, time_window_minutes, evaluation_frequency_minutes,
                    severity, enabled, notification_channels, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $data['name'],
                    $data['description'] ?? null,
                    $data['alert_type'],
                    $data['metric_type'],
                    $data['condition_operator'],
                    $data['threshold_value'],
                    $data['time_window_minutes'] ?? 5,
                    $data['evaluation_frequency_minutes'] ?? 1,
                    $data['severity'],
                    $data['enabled'] ?? true,
                    json_encode($data['notification_channels'] ?? []),
                    $_SESSION['user_id'] ?? null
                ]
            );

            return (new Response())->json(['success' => true, 'message' => 'Alert created successfully']);

        } catch (\Exception $e) {
            return (new Response())->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * System-Metriken sammeln
     */
    private function getSystemMetrics(): array
    {
        try {
            $latest = $this->db->select(
                'SELECT metric_type, value, percentage, status, measured_at
                 FROM system_metrics 
                 WHERE measured_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                 ORDER BY measured_at DESC'
            );

            $metrics = [];
            foreach ($latest as $metric) {
                $type = $metric['metric_type'];
                if (!isset($metrics[$type])) {
                    $metrics[$type] = $metric;
                }
            }

            return $metrics;

        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Performance-Metriken sammeln
     */
    private function getPerformanceMetrics(): array
    {
        try {
            // Durchschnittliche Response-Zeiten der letzten 15 Minuten
            $avgResponseTimes = $this->db->select(
                'SELECT endpoint, 
                        AVG(response_time_ms) as avg_response_time,
                        COUNT(*) as request_count,
                        MIN(response_time_ms) as min_response_time,
                        MAX(response_time_ms) as max_response_time,
                        target_response_time
                 FROM performance_metrics 
                 WHERE created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
                 GROUP BY endpoint, target_response_time
                 ORDER BY avg_response_time DESC
                 LIMIT 10'
            );

            // Performance-Grade-Verteilung
            $performanceGrades = $this->db->select(
                'SELECT performance_grade, COUNT(*) as count
                 FROM performance_metrics
                 WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
                 GROUP BY performance_grade'
            );

            return [
                'avg_response_times' => $avgResponseTimes,
                'performance_grades' => $performanceGrades,
                'total_requests_last_hour' => $this->db->selectOne(
                    'SELECT COUNT(*) as count FROM performance_metrics 
                     WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)'
                )['count'] ?? 0
            ];

        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Error-Metriken sammeln
     */
    private function getErrorMetrics(): array
    {
        try {
            // Fehler-Verteilung nach Severity
            $errorsBySeverity = $this->db->select(
                'SELECT severity, COUNT(*) as count, SUM(error_count) as total_errors
                 FROM error_logs 
                 WHERE first_seen > DATE_SUB(NOW(), INTERVAL 1 HOUR)
                 GROUP BY severity
                 ORDER BY FIELD(severity, "emergency", "critical", "error", "warning", "notice", "info", "debug")'
            );

            // Neueste ungelöste Fehler
            $recentErrors = $this->db->select(
                'SELECT severity, error_type, message, error_count, first_seen, last_seen
                 FROM error_logs 
                 WHERE resolved_at IS NULL
                 ORDER BY last_seen DESC
                 LIMIT 10'
            );

            return [
                'by_severity' => $errorsBySeverity,
                'recent_unresolved' => $recentErrors,
                'total_errors_last_hour' => array_sum(array_column($errorsBySeverity, 'total_errors'))
            ];

        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * API-Metriken sammeln
     */
    private function getApiMetrics(): array
    {
        try {
            $currentHour = date('Y-m-d H:00:00');
            
            $apiStats = $this->db->select(
                'SELECT endpoint, http_method,
                        SUM(request_count) as total_requests,
                        SUM(success_count) as total_success,
                        SUM(error_count) as total_errors,
                        AVG(avg_response_time_ms) as avg_response_time,
                        MAX(max_response_time_ms) as max_response_time
                 FROM api_monitoring
                 WHERE hour_bucket >= DATE_SUB(?, INTERVAL 23 HOUR)
                 GROUP BY endpoint, http_method
                 ORDER BY total_requests DESC
                 LIMIT 15',
                [$currentHour]
            );

            return [
                'endpoint_stats' => $apiStats,
                'total_api_calls_24h' => array_sum(array_column($apiStats, 'total_requests')),
                'avg_success_rate' => $this->calculateSuccessRate($apiStats)
            ];

        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Aktive Alerts sammeln
     */
    private function getActiveAlerts(): array
    {
        try {
            $activeAlerts = $this->db->select(
                'SELECT al.*, a.name as alert_name, a.alert_type, a.description
                 FROM alert_logs al
                 JOIN alerts a ON al.alert_id = a.id
                 WHERE al.resolved_at IS NULL
                 ORDER BY al.created_at DESC
                 LIMIT 20'
            );

            return [
                'active_alerts' => $activeAlerts,
                'total_active' => count($activeAlerts),
                'critical_count' => count(array_filter($activeAlerts, fn($a) => $a['severity'] === 'critical'))
            ];

        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Performance-Zusammenfassung
     */
    private function getPerformanceSummary(int $hours, ?string $endpoint): array
    {
        try {
            $whereClause = 'WHERE created_at > DATE_SUB(NOW(), INTERVAL ? HOUR)';
            $params = [$hours];
            
            if ($endpoint) {
                $whereClause .= ' AND endpoint = ?';
                $params[] = $endpoint;
            }

            $summary = $this->db->selectOne(
                "SELECT 
                    COUNT(*) as total_requests,
                    AVG(response_time_ms) as avg_response_time,
                    MIN(response_time_ms) as min_response_time,
                    MAX(response_time_ms) as max_response_time,
                    COUNT(CASE WHEN performance_grade = 'excellent' THEN 1 END) as excellent_count,
                    COUNT(CASE WHEN performance_grade = 'good' THEN 1 END) as good_count,
                    COUNT(CASE WHEN performance_grade = 'warning' THEN 1 END) as warning_count,
                    COUNT(CASE WHEN performance_grade = 'critical' THEN 1 END) as critical_count
                 FROM performance_metrics {$whereClause}",
                $params
            );

            return $summary ?: [];

        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Performance-Trends (stündlich)
     */
    private function getPerformanceTrends(int $hours, ?string $endpoint): array
    {
        try {
            $whereClause = 'WHERE created_at > DATE_SUB(NOW(), INTERVAL ? HOUR)';
            $params = [$hours];
            
            if ($endpoint) {
                $whereClause .= ' AND endpoint = ?';
                $params[] = $endpoint;
            }

            $trends = $this->db->select(
                "SELECT 
                    DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') as hour,
                    COUNT(*) as request_count,
                    AVG(response_time_ms) as avg_response_time,
                    MAX(response_time_ms) as max_response_time
                 FROM performance_metrics {$whereClause}
                 GROUP BY DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00')
                 ORDER BY hour",
                $params
            );

            return $trends;

        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Langsamste Endpoints
     */
    private function getSlowestEndpoints(int $hours): array
    {
        try {
            return $this->db->select(
                'SELECT endpoint, 
                        COUNT(*) as request_count,
                        AVG(response_time_ms) as avg_response_time,
                        MAX(response_time_ms) as max_response_time,
                        AVG(target_response_time) as target_response_time
                 FROM performance_metrics
                 WHERE created_at > DATE_SUB(NOW(), INTERVAL ? HOUR)
                 GROUP BY endpoint
                 HAVING request_count >= 10
                 ORDER BY avg_response_time DESC
                 LIMIT 10',
                [$hours]
            );

        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Performance-Verteilung
     */
    private function getPerformanceDistribution(int $hours, ?string $endpoint): array
    {
        try {
            $whereClause = 'WHERE created_at > DATE_SUB(NOW(), INTERVAL ? HOUR)';
            $params = [$hours];
            
            if ($endpoint) {
                $whereClause .= ' AND endpoint = ?';
                $params[] = $endpoint;
            }

            $distribution = $this->db->select(
                "SELECT 
                    CASE 
                        WHEN response_time_ms <= 100 THEN '0-100ms'
                        WHEN response_time_ms <= 200 THEN '101-200ms'
                        WHEN response_time_ms <= 500 THEN '201-500ms'
                        WHEN response_time_ms <= 1000 THEN '501-1000ms'
                        WHEN response_time_ms <= 2000 THEN '1001-2000ms'
                        ELSE '2000ms+'
                    END as time_bucket,
                    COUNT(*) as count
                 FROM performance_metrics {$whereClause}
                 GROUP BY time_bucket
                 ORDER BY MIN(response_time_ms)",
                $params
            );

            return $distribution;

        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Error-Zusammenfassung
     */
    private function getErrorSummary(int $hours): array
    {
        try {
            return $this->db->selectOne(
                'SELECT 
                    COUNT(DISTINCT id) as unique_errors,
                    SUM(error_count) as total_occurrences,
                    COUNT(CASE WHEN severity IN ("critical", "emergency") THEN 1 END) as critical_errors,
                    COUNT(CASE WHEN resolved_at IS NULL THEN 1 END) as unresolved_errors
                 FROM error_logs
                 WHERE first_seen > DATE_SUB(NOW(), INTERVAL ? HOUR)',
                [$hours]
            ) ?: [];

        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Errors nach Severity
     */
    private function getErrorsBySeverity(int $hours): array
    {
        try {
            return $this->db->select(
                'SELECT severity, COUNT(*) as unique_errors, SUM(error_count) as total_occurrences
                 FROM error_logs
                 WHERE first_seen > DATE_SUB(NOW(), INTERVAL ? HOUR)
                 GROUP BY severity
                 ORDER BY FIELD(severity, "emergency", "critical", "error", "warning", "notice", "info", "debug")',
                [$hours]
            );

        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Errors nach Type
     */
    private function getErrorsByType(int $hours): array
    {
        try {
            return $this->db->select(
                'SELECT error_type, COUNT(*) as unique_errors, SUM(error_count) as total_occurrences
                 FROM error_logs
                 WHERE first_seen > DATE_SUB(NOW(), INTERVAL ? HOUR)
                 GROUP BY error_type
                 ORDER BY total_occurrences DESC
                 LIMIT 10',
                [$hours]
            );

        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Top Errors
     */
    private function getTopErrors(int $hours): array
    {
        try {
            return $this->db->select(
                'SELECT error_type, message, severity, error_count, first_seen, last_seen, resolved_at
                 FROM error_logs
                 WHERE first_seen > DATE_SUB(NOW(), INTERVAL ? HOUR)
                 ORDER BY error_count DESC, last_seen DESC
                 LIMIT 15',
                [$hours]
            );

        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Error-Trends
     */
    private function getErrorTrends(int $hours): array
    {
        try {
            return $this->db->select(
                'SELECT 
                    DATE_FORMAT(first_seen, "%Y-%m-%d %H:00:00") as hour,
                    COUNT(*) as error_count,
                    COUNT(CASE WHEN severity IN ("critical", "emergency") THEN 1 END) as critical_count
                 FROM error_logs
                 WHERE first_seen > DATE_SUB(NOW(), INTERVAL ? HOUR)
                 GROUP BY DATE_FORMAT(first_seen, "%Y-%m-%d %H:00:00")
                 ORDER BY hour',
                [$hours]
            );

        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Success-Rate berechnen
     */
    private function calculateSuccessRate(array $apiStats): float
    {
        $totalRequests = array_sum(array_column($apiStats, 'total_requests'));
        $totalSuccess = array_sum(array_column($apiStats, 'total_success'));
        
        return $totalRequests > 0 ? round(($totalSuccess / $totalRequests) * 100, 2) : 0;
    }

    /**
     * Admin-Berechtigung prüfen
     */
    private function isAdmin(Request $request): bool
    {
        // Vereinfachte Admin-Prüfung - in Realität würde hier die Rolle geprüft
        return isset($_SESSION['user_id']) && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
    }
}