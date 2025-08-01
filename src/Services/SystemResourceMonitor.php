<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Agents\MonitorAgent;
use Exception;

/**
 * System-Resource-Monitor für Shared Hosting-Umgebungen
 * 
 * Überwacht System-Ressourcen unter den Einschränkungen von Shared Hosting:
 * - Memory Usage mit PHP-Limits
 * - Disk Space (wenn verfügbar)
 * - Process Information (eingeschränkt)
 * - Connection Pooling und Database Load
 * - PHP-spezifische Metriken
 * 
 * @author 2Brands Media GmbH
 */
class SystemResourceMonitor
{
    private Database $db;
    private MonitorAgent $monitor;
    private array $config;
    private array $lastMeasurement = [];

    public function __construct(Database $db, MonitorAgent $monitor, array $config = [])
    {
        $this->db = $db;
        $this->monitor = $monitor;
        $this->config = array_merge([
            'memory_limit_bytes' => $this->parseMemoryLimit(ini_get('memory_limit')),
            'memory_warning_threshold' => 80, // Prozent
            'memory_critical_threshold' => 90,
            'disk_warning_threshold' => 85,
            'disk_critical_threshold' => 95,
            'enable_detailed_logging' => true,
            'collection_interval_seconds' => 300, // 5 Minuten
            'retention_days' => 30
        ], $config);
    }

    /**
     * Sammelt alle verfügbaren System-Metriken
     */
    public function collectSystemMetrics(): array
    {
        $metrics = [];
        $timestamp = time();

        try {
            // Memory Metrics
            $memoryMetrics = $this->collectMemoryMetrics();
            if ($memoryMetrics) {
                $metrics['memory'] = $memoryMetrics;
                $this->logSystemMetric('memory', $memoryMetrics);
            }

            // Disk Metrics (wenn verfügbar)
            $diskMetrics = $this->collectDiskMetrics();
            if ($diskMetrics) {
                $metrics['disk'] = $diskMetrics;
                $this->logSystemMetric('disk', $diskMetrics);
            }

            // Load Metrics (Linux only)
            $loadMetrics = $this->collectLoadMetrics();
            if ($loadMetrics) {
                $metrics['load'] = $loadMetrics;
                $this->logSystemMetric('load', $loadMetrics);
            }

            // PHP Process Metrics
            $processMetrics = $this->collectProcessMetrics();
            if ($processMetrics) {
                $metrics['process'] = $processMetrics;
                $this->logSystemMetric('process', $processMetrics);
            }

            // Database Connection Metrics
            $dbMetrics = $this->collectDatabaseMetrics();
            if ($dbMetrics) {
                $metrics['database'] = $dbMetrics;
                $this->logSystemMetric('database_connections', $dbMetrics);
            }

            // PHP Metrics
            $phpMetrics = $this->collectPHPMetrics();
            if ($phpMetrics) {
                $metrics['php'] = $phpMetrics;
                $this->logSystemMetric('php', $phpMetrics);
            }

            // Network Metrics (basic)
            $networkMetrics = $this->collectNetworkMetrics();
            if ($networkMetrics) {
                $metrics['network'] = $networkMetrics;
                $this->logSystemMetric('network', $networkMetrics);
            }

            $this->lastMeasurement = [
                'timestamp' => $timestamp,
                'metrics' => $metrics
            ];

            return $metrics;

        } catch (Exception $e) {
            $this->monitor->logError($e, 'warning', [
                'context' => 'system_resource_collection'
            ]);
            return [];
        }
    }

    /**
     * Memory-Metriken sammeln
     */
    private function collectMemoryMetrics(): ?array
    {
        try {
            $memoryUsage = memory_get_usage(true);
            $memoryPeak = memory_get_peak_usage(true);
            $memoryLimit = $this->config['memory_limit_bytes'];

            $usagePercent = $memoryLimit > 0 ? ($memoryUsage / $memoryLimit) * 100 : 0;
            $peakPercent = $memoryLimit > 0 ? ($memoryPeak / $memoryLimit) * 100 : 0;

            // Status bestimmen
            $status = 'normal';
            if ($usagePercent >= $this->config['memory_critical_threshold']) {
                $status = 'critical';
            } elseif ($usagePercent >= $this->config['memory_warning_threshold']) {
                $status = 'warning';
            }

            return [
                'usage_bytes' => $memoryUsage,
                'peak_bytes' => $memoryPeak,
                'limit_bytes' => $memoryLimit,
                'usage_percent' => round($usagePercent, 2),
                'peak_percent' => round($peakPercent, 2),
                'status' => $status,
                'available_mb' => round(($memoryLimit - $memoryUsage) / 1024 / 1024, 2)
            ];

        } catch (Exception $e) {
            error_log("Failed to collect memory metrics: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Disk-Metriken sammeln (wenn möglich)
     */
    private function collectDiskMetrics(): ?array
    {
        try {
            $path = getcwd() ?: './';
            
            if (!function_exists('disk_free_space') || !function_exists('disk_total_space')) {
                return null;
            }

            $freeBytes = disk_free_space($path);
            $totalBytes = disk_total_space($path);

            if ($freeBytes === false || $totalBytes === false) {
                return null;
            }

            $usedBytes = $totalBytes - $freeBytes;
            $usagePercent = ($usedBytes / $totalBytes) * 100;

            // Status bestimmen
            $status = 'normal';
            if ($usagePercent >= $this->config['disk_critical_threshold']) {
                $status = 'critical';
            } elseif ($usagePercent >= $this->config['disk_warning_threshold']) {
                $status = 'warning';
            }

            return [
                'total_bytes' => $totalBytes,
                'used_bytes' => $usedBytes,
                'free_bytes' => $freeBytes,
                'usage_percent' => round($usagePercent, 2),
                'status' => $status,
                'total_gb' => round($totalBytes / 1024 / 1024 / 1024, 2),
                'free_gb' => round($freeBytes / 1024 / 1024 / 1024, 2)
            ];

        } catch (Exception $e) {
            error_log("Failed to collect disk metrics: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Load-Metriken sammeln (Linux)
     */
    private function collectLoadMetrics(): ?array
    {
        try {
            if (!function_exists('sys_getloadavg')) {
                return null;
            }

            $load = sys_getloadavg();
            if ($load === false) {
                return null;
            }

            return [
                'load_1min' => round($load[0], 2),
                'load_5min' => round($load[1], 2),
                'load_15min' => round($load[2], 2),
                'status' => $load[0] > 2.0 ? 'warning' : 'normal'
            ];

        } catch (Exception $e) {
            error_log("Failed to collect load metrics: " . $e->getMessage());
            return null;
        }
    }

    /**
     * PHP-Process-Metriken sammeln
     */
    private function collectProcessMetrics(): ?array
    {
        try {
            $metrics = [
                'process_id' => getmypid(),
                'process_uid' => function_exists('posix_getuid') ? posix_getuid() : null,
                'process_gid' => function_exists('posix_getgid') ? posix_getgid() : null,
                'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
            ];

            // Zusätzliche Process-Info wenn verfügbar
            if (function_exists('proc_open')) {
                $processCount = $this->getProcessCount();
                if ($processCount > 0) {
                    $metrics['total_processes'] = $processCount;
                }
            }

            return $metrics;

        } catch (Exception $e) {
            error_log("Failed to collect process metrics: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Datenbank-Connection-Metriken sammeln
     */
    private function collectDatabaseMetrics(): ?array
    {
        try {
            // Connection-Status prüfen
            $connectionTest = $this->db->selectOne('SELECT 1 as test');
            $connectionHealthy = $connectionTest !== null;

            // Aktive Verbindungen (wenn SHOW PROCESSLIST verfügbar ist)
            $processInfo = null;
            try {
                $processes = $this->db->select('SHOW PROCESSLIST');
                $processInfo = [
                    'active_connections' => count($processes),
                    'connections_by_command' => $this->groupProcessesByCommand($processes)
                ];
            } catch (Exception $e) {
                // SHOW PROCESSLIST nicht verfügbar (normalerweise in Shared Hosting)
            }

            // Database-Performance-Metriken
            $slowQueryCount = $this->getSlowQueryCount();
            $queryCache = $this->getQueryCacheStats();

            return [
                'connection_healthy' => $connectionHealthy,
                'slow_queries_count' => $slowQueryCount,
                'query_cache' => $queryCache,
                'process_info' => $processInfo
            ];

        } catch (Exception $e) {
            error_log("Failed to collect database metrics: " . $e->getMessage());
            return null;
        }
    }

    /**
     * PHP-spezifische Metriken sammeln
     */
    private function collectPHPMetrics(): ?array
    {
        try {
            return [
                'version' => PHP_VERSION,
                'sapi_name' => php_sapi_name(),
                'max_execution_time' => (int) ini_get('max_execution_time'),
                'max_input_time' => (int) ini_get('max_input_time'),
                'post_max_size' => $this->parseMemoryLimit(ini_get('post_max_size')),
                'upload_max_filesize' => $this->parseMemoryLimit(ini_get('upload_max_filesize')),
                'max_file_uploads' => (int) ini_get('max_file_uploads'),
                'opcache_enabled' => function_exists('opcache_get_status') && opcache_get_status() !== false,
                'extensions_loaded' => count(get_loaded_extensions()),
                'include_path_count' => count(explode(PATH_SEPARATOR, get_include_path())),
            ];

        } catch (Exception $e) {
            error_log("Failed to collect PHP metrics: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Basis-Network-Metriken sammeln
     */
    private function collectNetworkMetrics(): ?array
    {
        try {
            $metrics = [
                'server_name' => $_SERVER['SERVER_NAME'] ?? 'unknown',
                'server_addr' => $_SERVER['SERVER_ADDR'] ?? 'unknown',
                'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'request_time' => $_SERVER['REQUEST_TIME'] ?? time(),
            ];

            // HTTP-Connection-Info wenn verfügbar
            if (isset($_SERVER['HTTP_CONNECTION'])) {
                $metrics['http_connection'] = $_SERVER['HTTP_CONNECTION'];
            }

            return $metrics;

        } catch (Exception $e) {
            error_log("Failed to collect network metrics: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Loggt System-Metrik in Datenbank
     */
    private function logSystemMetric(string $type, array $data): void
    {
        try {
            $value = 0;
            $percentage = null;
            $status = 'normal';

            // Werte je nach Metrik-Typ extrahieren
            switch ($type) {
                case 'memory':
                    $value = $data['usage_bytes'] / 1024 / 1024; // MB
                    $percentage = $data['usage_percent'];
                    $status = $data['status'];
                    break;

                case 'disk':
                    $value = $data['used_bytes'] / 1024 / 1024 / 1024; // GB
                    $percentage = $data['usage_percent'];
                    $status = $data['status'];
                    break;

                case 'load':
                    $value = $data['load_1min'];
                    $status = $data['status'];
                    break;

                case 'process':
                    $value = $data['total_processes'] ?? 1;
                    break;

                case 'database_connections':
                    $value = $data['process_info']['active_connections'] ?? 0;
                    break;

                default:
                    return; // Unbekannter Typ
            }

            $this->db->insert(
                'INSERT INTO system_metrics (
                    metric_type, value, percentage, status, server_info, measured_at
                ) VALUES (?, ?, ?, ?, ?, NOW())',
                [
                    $type,
                    $value,
                    $percentage,
                    $status,
                    json_encode($data)
                ]
            );

        } catch (Exception $e) {
            error_log("Failed to log system metric: " . $e->getMessage());
        }
    }

    /**
     * Prüft Resource-Limits und triggert Alerts
     */
    public function checkResourceAlerts(): array
    {
        $alerts = [];

        try {
            $metrics = $this->lastMeasurement['metrics'] ?? $this->collectSystemMetrics();

            // Memory Alert
            if (isset($metrics['memory']) && $metrics['memory']['status'] !== 'normal') {
                $alerts[] = [
                    'type' => 'memory',
                    'severity' => $metrics['memory']['status'] === 'critical' ? 'critical' : 'warning',
                    'message' => "Memory usage: {$metrics['memory']['usage_percent']}%",
                    'data' => $metrics['memory']
                ];
            }

            // Disk Alert
            if (isset($metrics['disk']) && $metrics['disk']['status'] !== 'normal') {
                $alerts[] = [
                    'type' => 'disk',
                    'severity' => $metrics['disk']['status'] === 'critical' ? 'critical' : 'warning',
                    'message' => "Disk usage: {$metrics['disk']['usage_percent']}%",
                    'data' => $metrics['disk']
                ];
            }

            // Load Alert
            if (isset($metrics['load']) && $metrics['load']['status'] !== 'normal') {
                $alerts[] = [
                    'type' => 'load',
                    'severity' => 'warning',
                    'message' => "High system load: {$metrics['load']['load_1min']}",
                    'data' => $metrics['load']
                ];
            }

        } catch (Exception $e) {
            $this->monitor->logError($e, 'warning', [
                'context' => 'resource_alert_check'
            ]);
        }

        return $alerts;
    }

    /**
     * Bereinigt alte Metriken
     */
    public function cleanupOldMetrics(): int
    {
        try {
            $deletedRows = $this->db->delete(
                'DELETE FROM system_metrics 
                 WHERE measured_at < DATE_SUB(NOW(), INTERVAL ? DAY)',
                [$this->config['retention_days']]
            );

            return $deletedRows;

        } catch (Exception $e) {
            $this->monitor->logError($e, 'warning', [
                'context' => 'metrics_cleanup'
            ]);
            return 0;
        }
    }

    /**
     * Gibt Resource-Trends zurück
     */
    public function getResourceTrends(int $hoursBack = 24): array
    {
        try {
            $trends = $this->db->select(
                'SELECT 
                    metric_type,
                    DATE_FORMAT(measured_at, "%Y-%m-%d %H:%i") as time_bucket,
                    AVG(value) as avg_value,
                    AVG(percentage) as avg_percentage,
                    MAX(value) as max_value,
                    MIN(value) as min_value
                 FROM system_metrics 
                 WHERE measured_at > DATE_SUB(NOW(), INTERVAL ? HOUR)
                 GROUP BY metric_type, DATE_FORMAT(measured_at, "%Y-%m-%d %H:%i")
                 ORDER BY time_bucket DESC',
                [$hoursBack]
            );

            // Nach Metrik-Typ gruppieren
            $groupedTrends = [];
            foreach ($trends as $trend) {
                $type = $trend['metric_type'];
                if (!isset($groupedTrends[$type])) {
                    $groupedTrends[$type] = [];
                }
                $groupedTrends[$type][] = $trend;
            }

            return $groupedTrends;

        } catch (Exception $e) {
            $this->monitor->logError($e, 'warning', [
                'context' => 'resource_trends'
            ]);
            return [];
        }
    }

    /**
     * System-Health-Summary
     */
    public function getSystemHealthSummary(): array
    {
        try {
            $currentMetrics = $this->collectSystemMetrics();
            $alerts = $this->checkResourceAlerts();

            $healthScore = 100;
            $issues = [];

            // Reduziere Health-Score basierend auf Problemen
            foreach ($alerts as $alert) {
                if ($alert['severity'] === 'critical') {
                    $healthScore -= 30;
                    $issues[] = $alert['message'];
                } elseif ($alert['severity'] === 'warning') {
                    $healthScore -= 15;
                    $issues[] = $alert['message'];
                }
            }

            $healthScore = max(0, $healthScore);

            return [
                'health_score' => $healthScore,
                'status' => $healthScore >= 80 ? 'healthy' : ($healthScore >= 60 ? 'warning' : 'critical'),
                'active_alerts' => count($alerts),
                'issues' => $issues,
                'metrics' => $currentMetrics,
                'timestamp' => date('c')
            ];

        } catch (Exception $e) {
            return [
                'health_score' => 0,
                'status' => 'error',
                'error' => $e->getMessage(),
                'timestamp' => date('c')
            ];
        }
    }

    /**
     * Hilfsmethoden
     */
    private function parseMemoryLimit(string $limit): int
    {
        if ($limit === '-1') {
            return PHP_INT_MAX;
        }

        $limit = strtolower(trim($limit));
        $bytes = (float) $limit;

        switch (substr($limit, -1)) {
            case 'g': $bytes *= 1024;
            case 'm': $bytes *= 1024;
            case 'k': $bytes *= 1024;
        }

        return (int) $bytes;
    }

    private function getProcessCount(): int
    {
        try {
            if (function_exists('shell_exec')) {
                $count = (int) shell_exec('ps aux | wc -l');
                return max(0, $count - 1); // Header-Zeile abziehen
            }
        } catch (Exception $e) {
            error_log("Failed to get process count: " . $e->getMessage());
        }
        return 0;
    }

    private function groupProcessesByCommand(array $processes): array
    {
        $grouped = [];
        foreach ($processes as $process) {
            $command = $process['Command'] ?? 'unknown';
            $grouped[$command] = ($grouped[$command] ?? 0) + 1;
        }
        return $grouped;
    }

    private function getSlowQueryCount(): int
    {
        try {
            $result = $this->db->selectOne(
                'SELECT COUNT(*) as count FROM query_performance 
                 WHERE is_slow = TRUE 
                 AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)'
            );
            return (int) ($result['count'] ?? 0);
        } catch (Exception $e) {
            return 0;
        }
    }

    private function getQueryCacheStats(): ?array
    {
        try {
            // Versuche Query-Cache-Status zu ermitteln (nicht immer verfügbar)
            $result = $this->db->selectOne('SHOW STATUS LIKE "Qcache_hits"');
            if ($result) {
                return ['hits' => (int) $result['Value']];
            }
        } catch (Exception $e) {
            // Query-Cache-Info nicht verfügbar
        }
        return null;
    }
}