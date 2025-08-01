<?php

declare(strict_types=1);

/**
 * System Diagnostics - Umfassende Systemdiagnose und Reporting
 * 
 * Generiert detaillierte Berichte über Systemzustand, Performance und Wartungsbedarf.
 * Kann manuell oder automatisch (z.B. täglich) ausgeführt werden.
 * 
 * Verwendung:
 * php scripts/system-diagnostics.php [--format=html|json|text] [--output=file.ext]
 * 
 * @author 2Brands Media GmbH
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Core\Config;
use App\Agents\MaintenanceAgent;
use App\Agents\MonitorAgent;

// Kommandozeilen-Argumente parsen
$options = getopt('', ['format:', 'output:']);
$format = $options['format'] ?? 'text';
$outputFile = $options['output'] ?? null;

if (!in_array($format, ['html', 'json', 'text'])) {
    echo "Error: Invalid format. Use html, json, or text.\n";
    exit(1);
}

// Konfiguration
$config = new Config();
$db = new Database($config->get('database'));

$maintenanceAgent = new MaintenanceAgent($db);
$monitorAgent = new MonitorAgent($db);

echo "Generating system diagnostics report...\n";

try {
    // Diagnose-Daten sammeln
    $diagnostics = [
        'generated_at' => date('c'),
        'system_info' => getSystemInfo(),
        'health_check' => $maintenanceAgent->performSystemHealthCheck(),
        'database_analysis' => analyzeDatabaseHealth($db),
        'performance_analysis' => analyzePerformance($db),
        'security_analysis' => analyzeSecurityStatus($db),
        'capacity_analysis' => analyzeCapacity($db),
        'maintenance_history' => getMaintenanceHistory($db),
        'recommendations' => []
    ];

    // Empfehlungen basierend auf Analyse generieren
    $diagnostics['recommendations'] = generateRecommendations($diagnostics);

    // Report formatieren und ausgeben
    $report = formatReport($diagnostics, $format);

    if ($outputFile) {
        file_put_contents($outputFile, $report);
        echo "Report saved to: {$outputFile}\n";
    } else {
        echo $report;
    }

    echo "\nDiagnostics completed successfully.\n";
    exit(0);

} catch (Exception $e) {
    echo "ERROR: Failed to generate diagnostics: " . $e->getMessage() . "\n";
    exit(1);
}

/**
 * Sammelt grundlegende Systeminformationen
 */
function getSystemInfo(): array
{
    return [
        'php_version' => PHP_VERSION,
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'operating_system' => php_uname(),
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'current_memory_usage' => memory_get_usage(true),
        'peak_memory_usage' => memory_get_peak_usage(true),
        'disk_free_space' => disk_free_space('.') ?: 0,
        'disk_total_space' => disk_total_space('.') ?: 0,
        'loaded_extensions' => get_loaded_extensions(),
        'timezone' => date_default_timezone_get()
    ];
}

/**
 * Analysiert Datenbank-Gesundheit
 */
function analyzeDatabaseHealth(Database $db): array
{
    $analysis = [
        'connection_status' => 'unknown',
        'table_status' => [],
        'index_analysis' => [],
        'query_performance' => [],
        'storage_analysis' => []
    ];

    try {
        // Verbindungstest
        $db->selectOne('SELECT 1');
        $analysis['connection_status'] = 'healthy';

        // Tabellenstatus
        $tables = ['users', 'playlists', 'stops', 'geocache', 'cache', 'performance_metrics', 'error_logs'];
        foreach ($tables as $table) {
            try {
                $tableInfo = $db->selectOne(
                    "SELECT 
                        TABLE_ROWS as row_count,
                        ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2) as size_mb,
                        ROUND((DATA_FREE / 1024 / 1024), 2) as free_mb,
                        ENGINE,
                        TABLE_COLLATION
                     FROM information_schema.TABLES 
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
                    [$table]
                );
                $analysis['table_status'][$table] = $tableInfo;
            } catch (Exception $e) {
                $analysis['table_status'][$table] = ['error' => $e->getMessage()];
            }
        }

        // Query Performance Analyse
        $slowQueries = $db->select(
            'SELECT query_type, table_name, AVG(execution_time_ms) as avg_time, COUNT(*) as count
             FROM query_performance 
             WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
             GROUP BY query_type, table_name
             HAVING avg_time > 50
             ORDER BY avg_time DESC
             LIMIT 10'
        );
        $analysis['query_performance'] = $slowQueries;

        // Storage Analyse
        $storageInfo = $db->selectOne(
            "SELECT 
                ROUND(SUM(DATA_LENGTH) / 1024 / 1024, 2) as data_size_mb,
                ROUND(SUM(INDEX_LENGTH) / 1024 / 1024, 2) as index_size_mb,
                ROUND(SUM(DATA_FREE) / 1024 / 1024, 2) as free_space_mb,
                COUNT(*) as table_count
             FROM information_schema.TABLES 
             WHERE TABLE_SCHEMA = DATABASE()"
        );
        $analysis['storage_analysis'] = $storageInfo;

    } catch (Exception $e) {
        $analysis['connection_status'] = 'failed';
        $analysis['error'] = $e->getMessage();
    }

    return $analysis;
}

/**
 * Analysiert Performance-Metriken
 */
function analyzePerformance(Database $db): array
{
    $analysis = [
        'response_times' => [],
        'error_rates' => [],
        'endpoint_performance' => [],
        'trends' => []
    ];

    try {
        // Durchschnittliche Response-Zeiten (letzte 24h)
        $responseTimes = $db->select(
            'SELECT 
                endpoint,
                AVG(response_time_ms) as avg_response_time,
                MIN(response_time_ms) as min_response_time,
                MAX(response_time_ms) as max_response_time,
                COUNT(*) as request_count
             FROM performance_metrics 
             WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
             GROUP BY endpoint
             ORDER BY avg_response_time DESC'
        );
        $analysis['response_times'] = $responseTimes;

        // Error Rates
        $errorRates = $db->select(
            'SELECT 
                endpoint,
                COUNT(*) as total_requests,
                SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) as error_requests,
                ROUND((SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) * 100.0 / COUNT(*)), 2) as error_rate_percent
             FROM performance_metrics 
             WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
             GROUP BY endpoint
             HAVING error_rate_percent > 0
             ORDER BY error_rate_percent DESC'
        );
        $analysis['error_rates'] = $errorRates;

        // Performance-Trends (letzte 7 Tage)
        $trends = $db->select(
            'SELECT 
                DATE(created_at) as date,
                AVG(response_time_ms) as avg_response_time,
                COUNT(*) as request_count,
                SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) as error_count
             FROM performance_metrics 
             WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
             GROUP BY DATE(created_at)
             ORDER BY date DESC'
        );
        $analysis['trends'] = $trends;

    } catch (Exception $e) {
        $analysis['error'] = $e->getMessage();
    }

    return $analysis;
}

/**
 * Analysiert Sicherheitsstatus
 */
function analyzeSecurityStatus(Database $db): array
{
    $analysis = [
        'failed_logins' => [],
        'security_errors' => [],
        'suspicious_activity' => [],
        'user_activity' => []
    ];

    try {
        // Fehlgeschlagene Logins (letzte 24h)
        $failedLogins = $db->select(
            'SELECT 
                ip_address,
                COUNT(*) as attempts,
                MIN(created_at) as first_attempt,
                MAX(created_at) as last_attempt
             FROM error_logs 
             WHERE message LIKE "%login%" 
             AND severity IN ("warning", "error")
             AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
             AND ip_address IS NOT NULL
             GROUP BY ip_address
             HAVING attempts > 5
             ORDER BY attempts DESC'
        );
        $analysis['failed_logins'] = $failedLogins;

        // Sicherheitsfehler
        $securityErrors = $db->select(
            'SELECT 
                error_type,
                COUNT(*) as count,
                MAX(created_at) as last_occurrence
             FROM error_logs 
             WHERE severity IN ("critical", "emergency")
             AND (error_type LIKE "%Security%" OR message LIKE "%security%")
             AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
             GROUP BY error_type
             ORDER BY count DESC'
        );
        $analysis['security_errors'] = $securityErrors;

        // Benutzeraktivität
        $userActivity = $db->select(
            'SELECT 
                u.email,
                COUNT(pm.id) as api_requests,
                MAX(pm.created_at) as last_activity
             FROM users u
             LEFT JOIN performance_metrics pm ON u.id = pm.user_id
             WHERE pm.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
             GROUP BY u.id, u.email
             ORDER BY api_requests DESC
             LIMIT 10'
        );
        $analysis['user_activity'] = $userActivity;

    } catch (Exception $e) {
        $analysis['error'] = $e->getMessage();
    }

    return $analysis;
}

/**
 * Analysiert Systemkapazität
 */
function analyzeCapacity(Database $db): array
{
    $analysis = [
        'current_usage' => [],
        'growth_projections' => [],
        'resource_limits' => [],
        'scaling_recommendations' => []
    ];

    try {
        // Aktuelle Nutzung
        $currentUsage = [
            'total_users' => (int) ($db->selectOne('SELECT COUNT(*) as count FROM users')['count'] ?? 0),
            'active_playlists' => (int) ($db->selectOne('SELECT COUNT(*) as count FROM playlists WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)')['count'] ?? 0),
            'daily_requests' => (int) ($db->selectOne('SELECT COUNT(*) as count FROM performance_metrics WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)')['count'] ?? 0),
            'cache_entries' => (int) ($db->selectOne('SELECT COUNT(*) as count FROM cache')['count'] ?? 0),
            'geocache_entries' => (int) ($db->selectOne('SELECT COUNT(*) as count FROM geocache')['count'] ?? 0)
        ];
        $analysis['current_usage'] = $currentUsage;

        // Wachstumstrends
        $userGrowth = $db->select(
            'SELECT 
                DATE(created_at) as date,
                COUNT(*) as new_users
             FROM users 
             WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY DATE(created_at)
             ORDER BY date DESC'
        );
        $analysis['growth_projections']['user_growth'] = $userGrowth;

        $requestGrowth = $db->select(
            'SELECT 
                DATE(created_at) as date,
                COUNT(*) as requests
             FROM performance_metrics 
             WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
             GROUP BY DATE(created_at)
             ORDER BY date DESC'
        );
        $analysis['growth_projections']['request_growth'] = $requestGrowth;

        // Resource Limits
        $analysis['resource_limits'] = [
            'php_memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'max_input_vars' => ini_get('max_input_vars'),
            'upload_max_filesize' => ini_get('upload_max_filesize')
        ];

    } catch (Exception $e) {
        $analysis['error'] = $e->getMessage();
    }

    return $analysis;
}

/**
 * Holt Wartungshistorie
 */
function getMaintenanceHistory(Database $db): array
{
    try {
        return $db->select(
            'SELECT 
                action,
                details,
                ip_address,
                created_at
             FROM audit_log 
             WHERE action LIKE "maintenance_%"
             ORDER BY created_at DESC
             LIMIT 20'
        );
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

/**
 * Generiert Empfehlungen basierend auf Analyse
 */
function generateRecommendations(array $diagnostics): array
{
    $recommendations = [];

    // Gesundheitsscore prüfen
    $healthScore = $diagnostics['health_check']['health_score'] ?? 0;
    if ($healthScore < 70) {
        $recommendations[] = [
            'type' => 'critical',
            'category' => 'system_health',
            'message' => "System health score is low ({$healthScore}/100). Immediate maintenance required.",
            'actions' => ['Run emergency maintenance', 'Check system resources', 'Review error logs']
        ];
    }

    // Performance prüfen
    $avgResponseTime = 0;
    if (!empty($diagnostics['performance_analysis']['response_times'])) {
        $avgResponseTime = array_sum(array_column($diagnostics['performance_analysis']['response_times'], 'avg_response_time')) / 
                          count($diagnostics['performance_analysis']['response_times']);
    }

    if ($avgResponseTime > 200) {
        $recommendations[] = [
            'type' => 'warning',
            'category' => 'performance',
            'message' => "Average response time is high (" . round($avgResponseTime) . "ms). Performance optimization needed.",
            'actions' => ['Optimize database queries', 'Review slow endpoints', 'Increase cache usage']
        ];
    }

    // Datenbank prüfen
    if (isset($diagnostics['database_analysis']['storage_analysis']['data_size_mb'])) {
        $dbSize = $diagnostics['database_analysis']['storage_analysis']['data_size_mb'];
        if ($dbSize > 1000) {
            $recommendations[] = [
                'type' => 'info',
                'category' => 'capacity',
                'message' => "Database size is large ({$dbSize}MB). Consider archiving old data.",
                'actions' => ['Archive old performance metrics', 'Clean up expired cache entries', 'Review data retention policies']
            ];
        }
    }

    // Sicherheit prüfen
    $failedLogins = count($diagnostics['security_analysis']['failed_logins'] ?? []);
    if ($failedLogins > 10) {
        $recommendations[] = [
            'type' => 'warning',
            'category' => 'security',
            'message' => "High number of IP addresses with failed login attempts ({$failedLogins}). Security review needed.",
            'actions' => ['Review failed login patterns', 'Consider IP blocking', 'Implement rate limiting']
        ];
    }

    return $recommendations;
}

/**
 * Formatiert Report in gewünschtem Format
 */
function formatReport(array $diagnostics, string $format): string
{
    switch ($format) {
        case 'json':
            return json_encode($diagnostics, JSON_PRETTY_PRINT);
            
        case 'html':
            return generateHtmlReport($diagnostics);
            
        default:
            return generateTextReport($diagnostics);
    }
}

/**
 * Generiert Text-Report
 */
function generateTextReport(array $diagnostics): string
{
    $report = "# METROPOL PORTAL - SYSTEM DIAGNOSTICS REPORT\n";
    $report .= "Generated: " . $diagnostics['generated_at'] . "\n";
    $report .= str_repeat("=", 80) . "\n\n";

    // System Health
    $healthScore = $diagnostics['health_check']['health_score'] ?? 0;
    $report .= "## SYSTEM HEALTH SCORE: {$healthScore}/100\n\n";

    // Recommendations
    if (!empty($diagnostics['recommendations'])) {
        $report .= "## RECOMMENDATIONS\n";
        foreach ($diagnostics['recommendations'] as $rec) {
            $report .= "- [{$rec['type']}] {$rec['message']}\n";
            foreach ($rec['actions'] as $action) {
                $report .= "  * {$action}\n";
            }
            $report .= "\n";
        }
    }

    // Performance Summary
    if (!empty($diagnostics['performance_analysis']['response_times'])) {
        $report .= "## PERFORMANCE SUMMARY\n";
        $report .= "Top slow endpoints:\n";
        foreach (array_slice($diagnostics['performance_analysis']['response_times'], 0, 5) as $endpoint) {
            $report .= "- {$endpoint['endpoint']}: {$endpoint['avg_response_time']}ms avg\n";
        }
        $report .= "\n";
    }

    // Database Health
    if (isset($diagnostics['database_analysis']['storage_analysis'])) {
        $storage = $diagnostics['database_analysis']['storage_analysis'];
        $report .= "## DATABASE STATUS\n";
        $report .= "- Data Size: {$storage['data_size_mb']}MB\n";
        $report .= "- Index Size: {$storage['index_size_mb']}MB\n";
        $report .= "- Free Space: {$storage['free_space_mb']}MB\n";
        $report .= "- Tables: {$storage['table_count']}\n\n";
    }

    return $report;
}

/**
 * Generiert HTML-Report
 */
function generateHtmlReport(array $diagnostics): string
{
    $healthScore = $diagnostics['health_check']['health_score'] ?? 0;
    $healthColor = $healthScore >= 80 ? 'green' : ($healthScore >= 60 ? 'orange' : 'red');
    
    $html = "<!DOCTYPE html>
<html lang='de'>
<head>
    <meta charset='UTF-8'>
    <title>Metropol Portal - System Diagnostics</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { background: #f0f0f0; padding: 20px; border-radius: 5px; }
        .health-score { font-size: 24px; font-weight: bold; color: {$healthColor}; }
        .section { margin: 20px 0; }
        .recommendation { padding: 10px; margin: 5px 0; border-left: 4px solid #ccc; }
        .critical { border-color: red; background: #ffe6e6; }
        .warning { border-color: orange; background: #fff2e6; }
        .info { border-color: blue; background: #e6f3ff; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <div class='header'>
        <h1>Metropol Portal - System Diagnostics</h1>
        <p>Generated: {$diagnostics['generated_at']}</p>
        <div class='health-score'>System Health Score: {$healthScore}/100</div>
    </div>";

    // Recommendations
    if (!empty($diagnostics['recommendations'])) {
        $html .= "<div class='section'><h2>Recommendations</h2>";
        foreach ($diagnostics['recommendations'] as $rec) {
            $html .= "<div class='recommendation {$rec['type']}'>";
            $html .= "<strong>{$rec['message']}</strong><ul>";
            foreach ($rec['actions'] as $action) {
                $html .= "<li>{$action}</li>";
            }
            $html .= "</ul></div>";
        }
        $html .= "</div>";
    }

    $html .= "</body></html>";
    return $html;
}