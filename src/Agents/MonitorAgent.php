<?php

declare(strict_types=1);

namespace App\Agents;

use App\Core\Database;
use PDO;
use Exception;
use Throwable;

/**
 * MonitorAgent - Umfassendes System-Monitoring und Error-Logging
 * 
 * Erfolgskriterium: 99.9% Uptime mit proaktiven Alerts
 * 
 * Verantwortlichkeiten:
 * - Performance-Metriken sammeln und analysieren
 * - Error-Logging mit Context-Informationen
 * - System-Ressourcen-Überwachung
 * - Alert-Management und Benachrichtigungen
 * - Response-Time-Tracking für alle API-Endpoints
 * 
 * @author 2Brands Media GmbH
 */
class MonitorAgent
{
    private Database $db;
    private array $config;
    private array $performanceTargets;
    private string $requestId;
    private float $requestStartTime;
    private array $metrics = [];

    public function __construct(Database $db, array $config = [])
    {
        $this->db = $db;
        $this->config = array_merge([
            'enable_performance_monitoring' => true,
            'enable_error_logging' => true,
            'enable_system_monitoring' => true,
            'enable_alerts' => true,
            'log_slow_queries' => true,
            'slow_query_threshold_ms' => 100,
            'memory_limit_mb' => 128,
            'cpu_threshold_percent' => 80,
            'disk_threshold_percent' => 85,
            'alert_cooldown_minutes' => 15,
        ], $config);

        $this->performanceTargets = [
            '/api/auth/login' => 100,
            '/api/route/calculate' => 300,
            '/api/playlists' => 200,
            '/api/geo/geocode' => 200,
            'default' => 200
        ];

        $this->requestId = $this->generateRequestId();
        $this->requestStartTime = microtime(true);
    }

    /**
     * Startet Request-Monitoring
     */
    public function startRequest(string $endpoint, string $method = 'GET', ?int $userId = null): void
    {
        $this->requestStartTime = microtime(true);
        $this->requestId = $this->generateRequestId();
        
        $this->metrics = [
            'endpoint' => $endpoint,
            'method' => $method,
            'user_id' => $userId,
            'start_time' => $this->requestStartTime,
            'memory_start' => memory_get_usage(true),
            'db_queries' => 0,
            'db_query_time' => 0,
            'cache_hits' => 0,
            'cache_misses' => 0
        ];

        if ($this->config['enable_performance_monitoring']) {
            register_shutdown_function([$this, 'endRequest']);
        }
    }

    /**
     * Beendet Request-Monitoring
     */
    public function endRequest(int $statusCode = 200, ?array $additionalMetrics = null): void
    {
        if (empty($this->metrics)) {
            return;
        }

        $endTime = microtime(true);
        $responseTime = ($endTime - $this->requestStartTime) * 1000; // in ms
        $memoryUsage = (memory_get_peak_usage(true) - $this->metrics['memory_start']) / 1024 / 1024; // in MB

        $endpoint = $this->metrics['endpoint'];
        $targetTime = $this->performanceTargets[$endpoint] ?? $this->performanceTargets['default'];

        try {
            $this->db->insert(
                'INSERT INTO performance_metrics (
                    endpoint, http_method, response_time_ms, memory_usage_mb,
                    db_queries_count, db_query_time_ms, cache_hits, cache_misses,
                    status_code, user_id, session_id, request_id, ip_address,
                    target_response_time, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                [
                    $endpoint,
                    $this->metrics['method'],
                    (int) round($responseTime),
                    round($memoryUsage, 2),
                    $this->metrics['db_queries'],
                    (int) round($this->metrics['db_query_time']),
                    $this->metrics['cache_hits'],
                    $this->metrics['cache_misses'],
                    $statusCode,
                    $this->metrics['user_id'],
                    session_id(),
                    $this->requestId,
                    $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    $targetTime
                ]
            );

            // Performance-Alert prüfen
            if ($responseTime > $targetTime * 1.5) {
                $this->checkPerformanceAlert($endpoint, $responseTime, $targetTime);
            }

            // API-Monitoring-Aggregation aktualisieren
            $this->updateApiMonitoring($endpoint, $this->metrics['method'], $responseTime, $statusCode);

        } catch (Exception $e) {
            error_log("MonitorAgent: Failed to log performance metrics: " . $e->getMessage());
        }
    }

    /**
     * Loggt Fehler mit umfangreichem Context
     */
    public function logError(
        Throwable $error,
        string $severity = 'error',
        ?array $context = null,
        ?int $userId = null
    ): void {
        if (!$this->config['enable_error_logging']) {
            return;
        }

        $errorType = get_class($error);
        $trace = $this->sanitizeTrace($error->getTrace());
        
        $contextData = array_merge([
            'exception_code' => $error->getCode(),
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'referer' => $_SERVER['HTTP_REFERER'] ?? null,
            'php_version' => PHP_VERSION,
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
        ], $context ?? []);

        try {
            // Prüfen ob dieser Fehler bereits kürzlich aufgetreten ist
            $existingError = $this->db->selectOne(
                'SELECT id, error_count, first_seen FROM error_logs 
                 WHERE error_type = ? AND file_path = ? AND line_number = ? AND resolved_at IS NULL
                 AND first_seen > DATE_SUB(NOW(), INTERVAL 1 HOUR)',
                [$errorType, $error->getFile(), $error->getLine()]
            );

            if ($existingError) {
                // Bestehenden Fehler aktualisieren
                $this->db->update(
                    'UPDATE error_logs SET 
                     error_count = error_count + 1, 
                     last_seen = NOW(),
                     context = JSON_MERGE_PATCH(context, ?)
                     WHERE id = ?',
                    [json_encode($contextData), $existingError['id']]
                );
            } else {
                // Neuen Fehler einfügen
                $this->db->insert(
                    'INSERT INTO error_logs (
                        severity, error_type, message, file_path, line_number,
                        trace, context, user_id, session_id, request_id,
                        ip_address, user_agent, url, http_method, 
                        response_time_ms, memory_usage_mb
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [
                        $severity,
                        $errorType,
                        $error->getMessage(),
                        $error->getFile(),
                        $error->getLine(),
                        json_encode($trace),
                        json_encode($contextData),
                        $userId,
                        session_id(),
                        $this->requestId,
                        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                        $_SERVER['HTTP_USER_AGENT'] ?? null,
                        $_SERVER['REQUEST_URI'] ?? null,
                        $_SERVER['REQUEST_METHOD'] ?? null,
                        isset($this->requestStartTime) ? (int) ((microtime(true) - $this->requestStartTime) * 1000) : null,
                        round(memory_get_usage(true) / 1024 / 1024, 2)
                    ]
                );
            }

            // Critical/Emergency Fehler sofort melden
            if (in_array($severity, ['critical', 'emergency'])) {
                $this->triggerAlert('error', "Critical error: {$error->getMessage()}", [
                    'error_type' => $errorType,
                    'file' => $error->getFile(),
                    'line' => $error->getLine(),
                    'severity' => $severity
                ]);
            }

        } catch (Exception $e) {
            error_log("MonitorAgent: Failed to log error: " . $e->getMessage());
            error_log("Original error: " . $error->getMessage());
        }
    }

    /**
     * Überwacht System-Ressourcen (angepasst für Shared Hosting)
     */
    public function collectSystemMetrics(): void
    {
        if (!$this->config['enable_system_monitoring']) {
            return;
        }

        try {
            // Memory Usage
            $memoryUsage = memory_get_usage(true);
            $memoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));
            $memoryPercent = $memoryLimit > 0 ? ($memoryUsage / $memoryLimit) * 100 : 0;

            $this->logSystemMetric('memory', $memoryUsage / 1024 / 1024, $memoryPercent);

            // Disk Usage (wenn möglich)
            if (function_exists('disk_free_space') && function_exists('disk_total_space')) {
                $diskFree = disk_free_space('./');
                $diskTotal = disk_total_space('./');
                if ($diskFree !== false && $diskTotal !== false) {
                    $diskUsed = $diskTotal - $diskFree;
                    $diskPercent = ($diskUsed / $diskTotal) * 100;
                    $this->logSystemMetric('disk', $diskUsed / 1024 / 1024 / 1024, $diskPercent); // GB
                }
            }

            // Load Average (nur auf Linux)
            if (function_exists('sys_getloadavg')) {
                $load = sys_getloadavg();
                if ($load !== false) {
                    $this->logSystemMetric('load', $load[0], null);
                }
            }

            // Process Count (wenn verfügbar)
            if (function_exists('shell_exec')) {
                $processCount = (int) shell_exec('ps aux | wc -l') ?: 0;
                if ($processCount > 0) {
                    $this->logSystemMetric('processes', $processCount, null);
                }
            }

        } catch (Exception $e) {
            error_log("MonitorAgent: Failed to collect system metrics: " . $e->getMessage());
        }
    }

    /**
     * Loggt System-Metrik
     */
    private function logSystemMetric(string $type, float $value, ?float $percentage): void
    {
        try {
            $this->db->insert(
                'INSERT INTO system_metrics (metric_type, value, percentage, measured_at) 
                 VALUES (?, ?, ?, NOW())',
                [$type, $value, $percentage]
            );

            // Alert-Prüfung
            $this->checkSystemAlert($type, $value, $percentage);

        } catch (Exception $e) {
            error_log("MonitorAgent: Failed to log system metric: " . $e->getMessage());
        }
    }

    /**
     * Prüft Performance-Alerts
     */
    private function checkPerformanceAlert(string $endpoint, float $responseTime, int $targetTime): void
    {
        try {
            // Prüfen ob Alert bereits aktiv ist (Cooldown)
            $recentAlert = $this->db->selectOne(
                'SELECT id FROM alert_logs 
                 WHERE alert_name = ? AND resolved_at IS NULL 
                 AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)',
                ["performance_slow_response_{$endpoint}", $this->config['alert_cooldown_minutes']]
            );

            if ($recentAlert) {
                return; // Alert-Cooldown aktiv
            }

            $severity = $responseTime > $targetTime * 3 ? 'critical' : 
                       ($responseTime > $targetTime * 2 ? 'high' : 'medium');

            $this->triggerAlert('performance', "Slow response time for {$endpoint}: {$responseTime}ms (target: {$targetTime}ms)", [
                'endpoint' => $endpoint,
                'response_time' => $responseTime,
                'target_time' => $targetTime,
                'severity' => $severity
            ]);

        } catch (Exception $e) {
            error_log("MonitorAgent: Failed to check performance alert: " . $e->getMessage());
        }
    }

    /**
     * Prüft System-Alerts
     */
    private function checkSystemAlert(string $metricType, float $value, ?float $percentage): void
    {
        if ($percentage === null) {
            return;
        }

        $thresholds = [
            'memory' => ['warning' => 80, 'critical' => 90],
            'disk' => ['warning' => 85, 'critical' => 95],
            'cpu' => ['warning' => 80, 'critical' => 90]
        ];

        if (!isset($thresholds[$metricType])) {
            return;
        }

        $threshold = $thresholds[$metricType];
        $severity = $percentage >= $threshold['critical'] ? 'critical' : 
                   ($percentage >= $threshold['warning'] ? 'high' : null);

        if ($severity) {
            $this->triggerAlert('system', "{$metricType} usage high: {$percentage}%", [
                'metric_type' => $metricType,
                'value' => $value,
                'percentage' => $percentage,
                'severity' => $severity
            ]);
        }
    }

    /**
     * Löst Alert aus
     */
    private function triggerAlert(string $alertType, string $message, array $context = []): void
    {
        if (!$this->config['enable_alerts']) {
            return;
        }

        try {
            $alertName = $alertType . '_' . md5($message . serialize($context));

            $this->db->insert(
                'INSERT INTO alert_logs (
                    alert_id, alert_name, severity, trigger_value, threshold_value,
                    message, context, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())',
                [
                    0, // Wird später durch Alert-Konfiguration ersetzt
                    $alertName,
                    $context['severity'] ?? 'medium',
                    $context['value'] ?? 0,
                    $context['threshold'] ?? 0,
                    $message,
                    json_encode($context)
                ]
            );

            // Benachrichtigung senden (vereinfacht)
            $this->sendNotification($alertType, $message, $context);

        } catch (Exception $e) {
            error_log("MonitorAgent: Failed to trigger alert: " . $e->getMessage());
        }
    }

    /**
     * Sendet Benachrichtigung (vereinfachte Version)
     */
    private function sendNotification(string $type, string $message, array $context): void
    {
        // Hier würde normalerweise E-Mail/Webhook/Slack Integration stehen
        error_log("ALERT [{$type}]: {$message}");
        
        // Für kritische Alerts zusätzlich in separates Log
        if (($context['severity'] ?? '') === 'critical') {
            file_put_contents(
                '/tmp/metropol_critical_alerts.log',
                date('Y-m-d H:i:s') . " [CRITICAL] {$message}\n" . json_encode($context) . "\n\n",
                FILE_APPEND | LOCK_EX
            );
        }
    }

    /**
     * Aktualisiert API-Monitoring-Aggregation
     */
    private function updateApiMonitoring(string $endpoint, string $method, float $responseTime, int $statusCode): void
    {
        try {
            $hourBucket = date('Y-m-d H:00:00');
            $isSuccess = $statusCode >= 200 && $statusCode < 400;

            $this->db->statement(
                'INSERT INTO api_monitoring (
                    endpoint, http_method, hour_bucket, request_count, success_count, error_count,
                    avg_response_time_ms, min_response_time_ms, max_response_time_ms
                ) VALUES (?, ?, ?, 1, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    request_count = request_count + 1,
                    success_count = success_count + ?,
                    error_count = error_count + ?,
                    avg_response_time_ms = ((avg_response_time_ms * (request_count - 1)) + ?) / request_count,
                    min_response_time_ms = LEAST(min_response_time_ms, ?),
                    max_response_time_ms = GREATEST(max_response_time_ms, ?)',
                [
                    $endpoint, $method, $hourBucket,
                    $isSuccess ? 1 : 0, $isSuccess ? 0 : 1,
                    $responseTime, (int) $responseTime, (int) $responseTime,
                    $isSuccess ? 1 : 0, $isSuccess ? 0 : 1,
                    $responseTime, (int) $responseTime, (int) $responseTime
                ]
            );

        } catch (Exception $e) {
            error_log("MonitorAgent: Failed to update API monitoring: " . $e->getMessage());
        }
    }

    /**
     * Trackt Datenbank-Query Performance
     */
    public function trackQuery(string $query, float $executionTime, ?array $result = null): void
    {
        if (!$this->config['log_slow_queries'] && $executionTime < $this->config['slow_query_threshold_ms']) {
            return;
        }

        try {
            $queryHash = md5(preg_replace('/\s+/', ' ', trim($query)));
            $queryType = strtoupper(explode(' ', trim($query))[0]);
            $tableName = $this->extractTableName($query);

            $this->db->insert(
                'INSERT INTO query_performance (
                    query_hash, query_type, table_name, execution_time_ms,
                    rows_examined, rows_affected, query_text, called_from,
                    user_id, session_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $queryHash,
                    $queryType,
                    $tableName,
                    round($executionTime, 3),
                    $result['rows_examined'] ?? null,
                    $result['rows_affected'] ?? null,
                    strlen($query) > 1000 ? substr($query, 0, 1000) . '...' : $query,
                    $this->getCallingFunction(),
                    $this->metrics['user_id'] ?? null,
                    session_id()
                ]
            );

        } catch (Exception $e) {
            error_log("MonitorAgent: Failed to track query: " . $e->getMessage());
        }
    }

    /**
     * Hilfsmethoden
     */
    private function generateRequestId(): string
    {
        return uniqid('req_', true);
    }

    private function sanitizeTrace(array $trace): array
    {
        // Entfernt sensible Daten aus Stack-Trace
        return array_map(function ($item) {
            unset($item['args']); // Argumente können sensible Daten enthalten
            return $item;
        }, array_slice($trace, 0, 10)); // Nur erste 10 Levels
    }

    private function parseMemoryLimit(string $limit): int
    {
        $limit = strtolower(trim($limit));
        $bytes = (int) $limit;

        switch (substr($limit, -1)) {
            case 'g': $bytes *= 1024;
            case 'm': $bytes *= 1024;
            case 'k': $bytes *= 1024;
        }

        return $bytes;
    }

    private function extractTableName(string $query): ?string
    {
        // Vereinfachte Tabellennamen-Extraktion
        if (preg_match('/(?:FROM|JOIN|INTO|UPDATE)\s+`?(\w+)`?/i', $query, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function getCallingFunction(): ?string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        foreach ($trace as $item) {
            if (isset($item['class']) && $item['class'] !== self::class) {
                return $item['class'] . '::' . $item['function'];
            }
        }
        return null;
    }

    /**
     * Öffentliche Methoden für externe Integration
     */
    public function incrementCacheHit(): void
    {
        $this->metrics['cache_hits']++;
    }

    public function incrementCacheMiss(): void
    {
        $this->metrics['cache_misses']++;
    }

    public function incrementDbQuery(float $executionTime = 0): void
    {
        $this->metrics['db_queries']++;
        $this->metrics['db_query_time'] += $executionTime;
    }

    /**
     * Health-Check für Monitoring-System
     */
    public function healthCheck(): array
    {
        try {
            $checks = [
                'database' => $this->checkDatabaseHealth(),
                'tables' => $this->checkMonitoringTables(),
                'disk_space' => $this->checkDiskSpace(),
                'memory' => $this->checkMemoryUsage(),
                'recent_errors' => $this->checkRecentErrors()
            ];

            $overall = array_reduce($checks, function ($carry, $check) {
                return $carry && $check['healthy'];
            }, true);

            return [
                'healthy' => $overall,
                'checks' => $checks,
                'timestamp' => date('c')
            ];

        } catch (Exception $e) {
            return [
                'healthy' => false,
                'error' => $e->getMessage(),
                'timestamp' => date('c')
            ];
        }
    }

    private function checkDatabaseHealth(): array
    {
        try {
            $this->db->selectOne('SELECT 1');
            return ['healthy' => true, 'message' => 'Database connection OK'];
        } catch (Exception $e) {
            return ['healthy' => false, 'message' => 'Database connection failed: ' . $e->getMessage()];
        }
    }

    private function checkMonitoringTables(): array
    {
        try {
            $tables = ['system_metrics', 'error_logs', 'performance_metrics', 'alert_logs'];
            foreach ($tables as $table) {
                $this->db->selectOne("SELECT 1 FROM {$table} LIMIT 1");
            }
            return ['healthy' => true, 'message' => 'All monitoring tables accessible'];
        } catch (Exception $e) {
            return ['healthy' => false, 'message' => 'Monitoring tables check failed: ' . $e->getMessage()];
        }
    }

    private function checkDiskSpace(): array
    {
        try {
            if (function_exists('disk_free_space')) {
                $free = disk_free_space('./');
                $total = disk_total_space('./');
                if ($free !== false && $total !== false) {
                    $usedPercent = (($total - $free) / $total) * 100;
                    $healthy = $usedPercent < 90;
                    return [
                        'healthy' => $healthy,
                        'message' => sprintf('Disk usage: %.1f%%', $usedPercent)
                    ];
                }
            }
            return ['healthy' => true, 'message' => 'Disk space check not available'];
        } catch (Exception $e) {
            return ['healthy' => false, 'message' => 'Disk space check failed: ' . $e->getMessage()];
        }
    }

    private function checkMemoryUsage(): array
    {
        try {
            $usage = memory_get_usage(true);
            $limit = $this->parseMemoryLimit(ini_get('memory_limit'));
            $usedPercent = $limit > 0 ? ($usage / $limit) * 100 : 0;
            $healthy = $usedPercent < 85;
            
            return [
                'healthy' => $healthy,
                'message' => sprintf('Memory usage: %.1f%% (%.1fMB)', $usedPercent, $usage / 1024 / 1024)
            ];
        } catch (Exception $e) {
            return ['healthy' => false, 'message' => 'Memory check failed: ' . $e->getMessage()];
        }
    }

    private function checkRecentErrors(): array
    {
        try {
            $criticalErrors = $this->db->selectOne(
                'SELECT COUNT(*) as count FROM error_logs 
                 WHERE severity IN ("critical", "emergency") 
                 AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)'
            );

            $count = (int) ($criticalErrors['count'] ?? 0);
            $healthy = $count < 5;

            return [
                'healthy' => $healthy,
                'message' => "Critical errors in last hour: {$count}"
            ];
        } catch (Exception $e) {
            return ['healthy' => false, 'message' => 'Error check failed: ' . $e->getMessage()];
        }
    }
}