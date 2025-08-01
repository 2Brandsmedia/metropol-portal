<?php

declare(strict_types=1);

namespace App\Agents;

use App\Core\Database;
use App\Agents\MonitorAgent;
use App\Agents\GoogleMapsAgent;
use App\Agents\GeoAgent;
use App\Middleware\RateLimitMiddleware;
use App\Services\AlertService;
use Exception;

/**
 * MonitoringIntegrationAgent - Integriert Monitoring in bestehende Agenten
 * 
 * Erweitert bestehende Agenten um umfassendes Monitoring:
 * - GoogleMapsAgent: API-Call-Tracking und Response-Time-Monitoring
 * - GeoAgent: Geocoding-Performance und Cache-Hit-Rate-Tracking
 * - RateLimitMiddleware: Security-Event-Monitoring
 * - Automatische Alert-Generierung für alle Agenten
 * 
 * @author 2Brands Media GmbH
 */
class MonitoringIntegrationAgent
{
    private Database $db;
    private MonitorAgent $monitor;
    private AlertService $alertService;
    private array $config;
    private array $agentInstances = [];

    public function __construct(Database $db, MonitorAgent $monitor, AlertService $alertService, array $config = [])
    {
        $this->db = $db;
        $this->monitor = $monitor;
        $this->alertService = $alertService;
        $this->config = array_merge([
            'enable_api_monitoring' => true,
            'enable_performance_alerts' => true,
            'enable_error_tracking' => true,
            'api_timeout_threshold_ms' => 5000,
            'geocoding_accuracy_threshold' => 0.95,
            'cache_hit_rate_threshold' => 0.80,
            'rate_limit_alert_threshold' => 10
        ], $config);
    }

    /**
     * Integriert Monitoring in GoogleMapsAgent
     */
    public function integrateGoogleMapsAgent(GoogleMapsAgent $agent): GoogleMapsMonitoringWrapper
    {
        return new GoogleMapsMonitoringWrapper($agent, $this->monitor, $this->alertService, $this->config);
    }

    /**
     * Integriert Monitoring in GeoAgent
     */
    public function integrateGeoAgent(GeoAgent $agent): GeoAgentMonitoringWrapper
    {
        return new GeoAgentMonitoringWrapper($agent, $this->monitor, $this->alertService, $this->config);
    }

    /**
     * Integriert Monitoring in RateLimitMiddleware
     */
    public function integrateRateLimitMiddleware(RateLimitMiddleware $middleware): RateLimitMonitoringWrapper
    {
        return new RateLimitMonitoringWrapper($middleware, $this->monitor, $this->alertService, $this->config);
    }

    /**
     * Sammelt Monitoring-Metriken von allen integrierten Agenten
     */
    public function collectAggregatedMetrics(): array
    {
        try {
            return [
                'google_maps' => $this->getGoogleMapsMetrics(),
                'geocoding' => $this->getGeocodingMetrics(),
                'rate_limiting' => $this->getRateLimitingMetrics(),
                'api_usage' => $this->getAPIUsageMetrics(),
                'cache_performance' => $this->getCachePerformanceMetrics()
            ];

        } catch (Exception $e) {
            $this->monitor->logError($e, 'error', ['context' => 'aggregated_metrics_collection']);
            return [];
        }
    }

    /**
     * Google Maps API Metriken
     */
    private function getGoogleMapsMetrics(): array
    {
        try {
            // API-Calls der letzten 24 Stunden
            $apiCalls = $this->db->selectOne(
                'SELECT COUNT(*) as count, AVG(response_time_ms) as avg_response_time
                 FROM performance_metrics 
                 WHERE endpoint LIKE "%google-maps%" 
                 AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)'
            );

            // Fehlerrate
            $errorRate = $this->db->selectOne(
                'SELECT 
                    (COUNT(CASE WHEN status_code >= 400 THEN 1 END) / COUNT(*)) * 100 as error_rate
                 FROM performance_metrics 
                 WHERE endpoint LIKE "%google-maps%" 
                 AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)'
            );

            // Traffic-Data Availability
            $trafficDataAvailability = $this->db->selectOne(
                'SELECT 
                    (COUNT(CASE WHEN context LIKE "%traffic_available%:true%" THEN 1 END) / COUNT(*)) * 100 as availability
                 FROM performance_metrics 
                 WHERE endpoint LIKE "%directions%" 
                 AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)'
            );

            return [
                'total_calls_24h' => (int) ($apiCalls['count'] ?? 0),
                'avg_response_time_ms' => (float) ($apiCalls['avg_response_time'] ?? 0),
                'error_rate_percent' => (float) ($errorRate['error_rate'] ?? 0),
                'traffic_data_availability_percent' => (float) ($trafficDataAvailability['availability'] ?? 0)
            ];

        } catch (Exception $e) {
            $this->monitor->logError($e, 'warning', ['context' => 'google_maps_metrics']);
            return [];
        }
    }

    /**
     * Geocoding Metriken
     */
    private function getGeocodingMetrics(): array
    {
        try {
            // Geocoding-Performance
            $geocodingStats = $this->db->selectOne(
                'SELECT 
                    COUNT(*) as total_requests,
                    AVG(response_time_ms) as avg_response_time,
                    COUNT(CASE WHEN status_code = 200 THEN 1 END) as successful_requests
                 FROM performance_metrics 
                 WHERE endpoint LIKE "%geocode%" 
                 AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)'
            );

            // Cache-Hit-Rate aus Cache-Tabelle
            $cacheStats = $this->db->selectOne(
                'SELECT 
                    COUNT(CASE WHEN value IS NOT NULL THEN 1 END) as cache_hits,
                    COUNT(*) as total_cache_requests
                 FROM cache 
                 WHERE `key` LIKE "geocode_%" 
                 AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)'
            );

            $totalRequests = (int) ($geocodingStats['total_requests'] ?? 0);
            $successfulRequests = (int) ($geocodingStats['successful_requests'] ?? 0);
            $cacheHits = (int) ($cacheStats['cache_hits'] ?? 0);
            $totalCacheRequests = (int) ($cacheStats['total_cache_requests'] ?? 0);

            return [
                'total_requests_24h' => $totalRequests,
                'success_rate_percent' => $totalRequests > 0 ? ($successfulRequests / $totalRequests) * 100 : 0,
                'avg_response_time_ms' => (float) ($geocodingStats['avg_response_time'] ?? 0),
                'cache_hit_rate_percent' => $totalCacheRequests > 0 ? ($cacheHits / $totalCacheRequests) * 100 : 0
            ];

        } catch (Exception $e) {
            $this->monitor->logError($e, 'warning', ['context' => 'geocoding_metrics']);
            return [];
        }
    }

    /**
     * Rate-Limiting Metriken
     */
    private function getRateLimitingMetrics(): array
    {
        try {
            // Rate-Limit Events aus Audit-Log
            $rateLimitEvents = $this->db->selectOne(
                'SELECT 
                    COUNT(CASE WHEN action = "rate_limit_attempt" THEN 1 END) as total_attempts,
                    COUNT(CASE WHEN action = "rate_limit_blocked" THEN 1 END) as total_blocks
                 FROM audit_log 
                 WHERE action LIKE "rate_limit_%" 
                 AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)'
            );

            // Unique IPs blocked
            $uniqueBlocks = $this->db->selectOne(
                'SELECT COUNT(DISTINCT ip_address) as unique_ips_blocked
                 FROM audit_log 
                 WHERE action = "rate_limit_blocked" 
                 AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)'
            );

            return [
                'rate_limit_attempts_24h' => (int) ($rateLimitEvents['total_attempts'] ?? 0),
                'blocked_requests_24h' => (int) ($rateLimitEvents['total_blocks'] ?? 0),
                'unique_ips_blocked_24h' => (int) ($uniqueBlocks['unique_ips_blocked'] ?? 0),
                'block_rate_percent' => $rateLimitEvents['total_attempts'] > 0 ? 
                    ($rateLimitEvents['total_blocks'] / $rateLimitEvents['total_attempts']) * 100 : 0
            ];

        } catch (Exception $e) {
            $this->monitor->logError($e, 'warning', ['context' => 'rate_limiting_metrics']);
            return [];
        }
    }

    /**
     * Allgemeine API-Usage Metriken
     */
    private function getAPIUsageMetrics(): array
    {
        try {
            // API-Endpoint-Verteilung
            $endpointDistribution = $this->db->select(
                'SELECT 
                    endpoint,
                    COUNT(*) as request_count,
                    AVG(response_time_ms) as avg_response_time
                 FROM performance_metrics 
                 WHERE endpoint LIKE "/api/%" 
                 AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                 GROUP BY endpoint
                 ORDER BY request_count DESC
                 LIMIT 10'
            );

            // Status-Code-Verteilung
            $statusDistribution = $this->db->select(
                'SELECT 
                    status_code,
                    COUNT(*) as count
                 FROM performance_metrics 
                 WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                 GROUP BY status_code
                 ORDER BY count DESC'
            );

            return [
                'endpoint_distribution' => $endpointDistribution,
                'status_code_distribution' => $statusDistribution,
                'total_api_requests_24h' => array_sum(array_column($endpointDistribution, 'request_count'))
            ];

        } catch (Exception $e) {
            $this->monitor->logError($e, 'warning', ['context' => 'api_usage_metrics']);
            return [];
        }
    }

    /**
     * Cache-Performance Metriken
     */
    private function getCachePerformanceMetrics(): array
    {
        try {
            // Cache-Statistiken
            $cacheStats = $this->db->selectOne(
                'SELECT 
                    COUNT(*) as total_cache_operations,
                    COUNT(CASE WHEN expires_at > NOW() THEN 1 END) as valid_entries,
                    COUNT(CASE WHEN expires_at <= NOW() THEN 1 END) as expired_entries
                 FROM cache'
            );

            // Cache-Hit-Rate aus Performance-Metriken
            $hitRateStats = $this->db->selectOne(
                'SELECT 
                    SUM(cache_hits) as total_hits,
                    SUM(cache_misses) as total_misses
                 FROM performance_metrics 
                 WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)'
            );

            $totalHits = (int) ($hitRateStats['total_hits'] ?? 0);
            $totalMisses = (int) ($hitRateStats['total_misses'] ?? 0);
            $totalRequests = $totalHits + $totalMisses;

            return [
                'total_cache_entries' => (int) ($cacheStats['total_cache_operations'] ?? 0),
                'valid_entries' => (int) ($cacheStats['valid_entries'] ?? 0),
                'expired_entries' => (int) ($cacheStats['expired_entries'] ?? 0),
                'hit_rate_percent' => $totalRequests > 0 ? ($totalHits / $totalRequests) * 100 : 0,
                'total_cache_requests_24h' => $totalRequests
            ];

        } catch (Exception $e) {
            $this->monitor->logError($e, 'warning', ['context' => 'cache_performance_metrics']);
            return [];
        }
    }

    /**
     * Führt automatische Health-Checks für alle integrierten Agenten durch
     */
    public function performHealthChecks(): array
    {
        $healthResults = [];

        try {
            // Google Maps API Health
            $healthResults['google_maps'] = $this->checkGoogleMapsHealth();
            
            // Geocoding Service Health
            $healthResults['geocoding'] = $this->checkGeocodingHealth();
            
            // Rate Limiting Health
            $healthResults['rate_limiting'] = $this->checkRateLimitingHealth();
            
            // Cache System Health
            $healthResults['cache'] = $this->checkCacheHealth();

            // Overall Health Status
            $healthResults['overall'] = [
                'healthy' => array_reduce($healthResults, fn($carry, $check) => $carry && $check['healthy'], true),
                'timestamp' => date('c')
            ];

        } catch (Exception $e) {
            $this->monitor->logError($e, 'error', ['context' => 'health_checks']);
            $healthResults['overall'] = ['healthy' => false, 'error' => $e->getMessage()];
        }

        return $healthResults;
    }

    /**
     * Google Maps API Health Check
     */
    private function checkGoogleMapsHealth(): array
    {
        try {
            // Prüfe letzte API-Calls
            $recentCalls = $this->db->selectOne(
                'SELECT COUNT(*) as count, AVG(response_time_ms) as avg_time
                 FROM performance_metrics 
                 WHERE endpoint LIKE "%google-maps%" 
                 AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)'
            );

            $callCount = (int) ($recentCalls['count'] ?? 0);
            $avgTime = (float) ($recentCalls['avg_time'] ?? 0);

            // Health-Kriterien
            $healthy = true;
            $messages = [];

            if ($avgTime > $this->config['api_timeout_threshold_ms']) {
                $healthy = false;
                $messages[] = "High response time: {$avgTime}ms";
            }

            return [
                'healthy' => $healthy,
                'metrics' => [
                    'recent_calls' => $callCount,
                    'avg_response_time_ms' => $avgTime
                ],
                'messages' => $messages
            ];

        } catch (Exception $e) {
            return ['healthy' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Geocoding Service Health Check
     */
    private function checkGeocodingHealth(): array
    {
        try {
            // Prüfe Geocoding-Erfolgsrate
            $recentGeocoding = $this->db->selectOne(
                'SELECT 
                    COUNT(*) as total,
                    COUNT(CASE WHEN status_code = 200 THEN 1 END) as successful
                 FROM performance_metrics 
                 WHERE endpoint LIKE "%geocode%" 
                 AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)'
            );

            $total = (int) ($recentGeocoding['total'] ?? 0);
            $successful = (int) ($recentGeocoding['successful'] ?? 0);
            $successRate = $total > 0 ? $successful / $total : 1.0;

            $healthy = $successRate >= $this->config['geocoding_accuracy_threshold'];
            $messages = [];

            if (!$healthy) {
                $messages[] = "Low success rate: " . round($successRate * 100, 1) . "%";
            }

            return [
                'healthy' => $healthy,
                'metrics' => [
                    'recent_requests' => $total,
                    'success_rate' => $successRate
                ],
                'messages' => $messages
            ];

        } catch (Exception $e) {
            return ['healthy' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Rate Limiting Health Check
     */
    private function checkRateLimitingHealth(): array
    {
        try {
            // Prüfe Rate-Limit-Aktivitäten
            $recentBlocks = $this->db->selectOne(
                'SELECT COUNT(*) as blocks
                 FROM audit_log 
                 WHERE action = "rate_limit_blocked" 
                 AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)'
            );

            $blockCount = (int) ($recentBlocks['blocks'] ?? 0);
            $healthy = $blockCount < $this->config['rate_limit_alert_threshold'];
            $messages = [];

            if (!$healthy) {
                $messages[] = "High number of blocked requests: {$blockCount}";
            }

            return [
                'healthy' => $healthy,
                'metrics' => [
                    'recent_blocks' => $blockCount
                ],
                'messages' => $messages
            ];

        } catch (Exception $e) {
            return ['healthy' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Cache System Health Check
     */
    private function checkCacheHealth(): array
    {
        try {
            // Prüfe Cache-Hit-Rate
            $cacheStats = $this->db->selectOne(
                'SELECT 
                    SUM(cache_hits) as hits,
                    SUM(cache_misses) as misses
                 FROM performance_metrics 
                 WHERE created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)'
            );

            $hits = (int) ($cacheStats['hits'] ?? 0);
            $misses = (int) ($cacheStats['misses'] ?? 0);
            $total = $hits + $misses;
            $hitRate = $total > 0 ? $hits / $total : 0;

            $healthy = $hitRate >= $this->config['cache_hit_rate_threshold'];
            $messages = [];

            if (!$healthy && $total > 0) {
                $messages[] = "Low cache hit rate: " . round($hitRate * 100, 1) . "%";
            }

            return [
                'healthy' => $healthy,
                'metrics' => [
                    'hit_rate' => $hitRate,
                    'total_requests' => $total
                ],
                'messages' => $messages
            ];

        } catch (Exception $e) {
            return ['healthy' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Erstellt automatische Alerts für Agent-Integration
     */
    public function setupDefaultAlerts(): array
    {
        $defaultAlerts = [
            [
                'name' => 'Google Maps API High Response Time',
                'description' => 'Google Maps API antwortet langsam',
                'alert_type' => 'performance',
                'metric_type' => 'avg_response_time',
                'condition_operator' => 'gt',
                'threshold_value' => $this->config['api_timeout_threshold_ms'],
                'time_window_minutes' => 5,
                'severity' => 'high',
                'notification_channels' => ['email', 'log']
            ],
            [
                'name' => 'Geocoding Low Success Rate',
                'description' => 'Geocoding-Erfolgsrate unter Schwellenwert',
                'alert_type' => 'business',
                'metric_type' => 'geocoding_success_rate',
                'condition_operator' => 'lt',
                'threshold_value' => $this->config['geocoding_accuracy_threshold'] * 100,
                'time_window_minutes' => 15,
                'severity' => 'medium',
                'notification_channels' => ['email', 'log']
            ],
            [
                'name' => 'High Rate Limit Activity',
                'description' => 'Viele Rate-Limit-Blocks erkannt',
                'alert_type' => 'security',
                'metric_type' => 'rate_limit_blocks',
                'condition_operator' => 'gt',
                'threshold_value' => $this->config['rate_limit_alert_threshold'],
                'time_window_minutes' => 15,
                'severity' => 'high',
                'notification_channels' => ['email', 'webhook', 'log']
            ],
            [
                'name' => 'Low Cache Hit Rate',
                'description' => 'Cache-Hit-Rate unter Schwellenwert',
                'alert_type' => 'performance',
                'metric_type' => 'cache_hit_rate',
                'condition_operator' => 'lt',
                'threshold_value' => $this->config['cache_hit_rate_threshold'] * 100,
                'time_window_minutes' => 30,
                'severity' => 'medium',
                'notification_channels' => ['log']
            ]
        ];

        $createdAlerts = [];
        foreach ($defaultAlerts as $alertData) {
            try {
                $alertId = $this->alertService->createAlert($alertData);
                if ($alertId) {
                    $createdAlerts[] = array_merge($alertData, ['id' => $alertId]);
                }
            } catch (Exception $e) {
                $this->monitor->logError($e, 'warning', [
                    'context' => 'setup_default_alerts',
                    'alert_name' => $alertData['name']
                ]);
            }
        }

        return $createdAlerts;
    }
}

/**
 * Monitoring-Wrapper für GoogleMapsAgent
 */
class GoogleMapsMonitoringWrapper
{
    private GoogleMapsAgent $agent;
    private MonitorAgent $monitor;
    private AlertService $alertService;
    private array $config;

    public function __construct(GoogleMapsAgent $agent, MonitorAgent $monitor, AlertService $alertService, array $config)
    {
        $this->agent = $agent;
        $this->monitor = $monitor;
        $this->alertService = $alertService;
        $this->config = $config;
    }

    /**
     * Wrapper für Route-Berechnung mit Monitoring
     */
    public function calculateRoute(array $waypoints, array $options = []): array
    {
        $startTime = microtime(true);
        $this->monitor->startRequest('/api/google-maps/directions', 'POST');

        try {
            $result = $this->agent->calculateRoute($waypoints, $options);
            
            $responseTime = (microtime(true) - $startTime) * 1000;
            
            // Performance-Tracking
            $this->monitor->endRequest(200, [
                'api_provider' => 'google_maps',
                'waypoint_count' => count($waypoints),
                'traffic_enabled' => $options['traffic'] ?? false,
                'response_time_ms' => $responseTime
            ]);

            // Alert bei langsamer Response
            if ($responseTime > $this->config['api_timeout_threshold_ms']) {
                $this->alertService->evaluateAlerts();
            }

            return $result;

        } catch (Exception $e) {
            $this->monitor->logError($e, 'error', [
                'context' => 'google_maps_route_calculation',
                'waypoints' => $waypoints,
                'options' => $options
            ]);
            
            $this->monitor->endRequest(500);
            throw $e;
        }
    }

    // Delegate alle anderen Methoden an den ursprünglichen Agent
    public function __call(string $method, array $arguments)
    {
        return $this->agent->$method(...$arguments);
    }
}

/**
 * Monitoring-Wrapper für GeoAgent
 */
class GeoAgentMonitoringWrapper
{
    private GeoAgent $agent;
    private MonitorAgent $monitor;
    private AlertService $alertService;
    private array $config;

    public function __construct(GeoAgent $agent, MonitorAgent $monitor, AlertService $alertService, array $config)
    {
        $this->agent = $agent;
        $this->monitor = $monitor;
        $this->alertService = $alertService;
        $this->config = $config;
    }

    /**
     * Wrapper für Geocoding mit Monitoring
     */
    public function geocode(string $address): ?array
    {
        $startTime = microtime(true);
        $this->monitor->startRequest('/api/geocode', 'POST');

        try {
            $result = $this->agent->geocode($address);
            
            $responseTime = (microtime(true) - $startTime) * 1000;
            $success = $result !== null;

            // Cache-Hit/Miss tracking
            if ($result && isset($result['cached']) && $result['cached']) {
                $this->monitor->incrementCacheHit();
            } else {
                $this->monitor->incrementCacheMiss();
            }

            $this->monitor->endRequest($success ? 200 : 404, [
                'address' => $address,
                'geocoding_success' => $success,
                'response_time_ms' => $responseTime,
                'confidence' => $result['confidence'] ?? null
            ]);

            return $result;

        } catch (Exception $e) {
            $this->monitor->logError($e, 'error', [
                'context' => 'geocoding_request',
                'address' => $address
            ]);
            
            $this->monitor->endRequest(500);
            throw $e;
        }
    }

    public function __call(string $method, array $arguments)
    {
        return $this->agent->$method(...$arguments);
    }
}

/**
 * Monitoring-Wrapper für RateLimitMiddleware
 */
class RateLimitMonitoringWrapper
{
    private RateLimitMiddleware $middleware;
    private MonitorAgent $monitor;
    private AlertService $alertService;
    private array $config;

    public function __construct(RateLimitMiddleware $middleware, MonitorAgent $monitor, AlertService $alertService, array $config)
    {
        $this->middleware = $middleware;
        $this->monitor = $monitor;
        $this->alertService = $alertService;
        $this->config = $config;
    }

    /**
     * Enhanced Rate-Limiting mit Security-Monitoring
     */
    public function handle($request, $next)
    {
        $identifier = $this->getIdentifier($request);
        
        try {
            $response = $this->middleware->handle($request, $next);
            
            // Security-Event bei Rate-Limit-Überschreitung
            if ($response->getStatusCode() === 429) {
                $this->monitor->logError(
                    new Exception("Rate limit exceeded for {$identifier}"),
                    'warning',
                    [
                        'context' => 'rate_limit_exceeded',
                        'identifier' => $identifier,
                        'route' => $request->getUri(),
                        'security_event' => true
                    ]
                );

                // Prüfe auf verdächtige Aktivitäten
                $this->checkForSuspiciousActivity($identifier, $request);
            }

            return $response;

        } catch (Exception $e) {
            $this->monitor->logError($e, 'error', [
                'context' => 'rate_limit_middleware',
                'identifier' => $identifier
            ]);
            throw $e;
        }
    }

    /**
     * Prüft auf verdächtige Aktivitäten
     */
    private function checkForSuspiciousActivity(string $identifier, $request): void
    {
        // Anzahl der Blocks in der letzten Stunde
        $recentBlocks = $this->monitor->db->selectOne(
            'SELECT COUNT(*) as count FROM audit_log 
             WHERE action = "rate_limit_blocked" 
             AND ip_address = ? 
             AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)',
            [$identifier]
        );

        $blockCount = (int) ($recentBlocks['count'] ?? 0);

        // Bei vielen Blocks -> Escalation
        if ($blockCount > $this->config['rate_limit_alert_threshold']) {
            $this->alertService->evaluateAlerts();
        }
    }

    private function getIdentifier($request): string
    {
        return $request->ip();
    }

    public function __call(string $method, array $arguments)
    {
        return $this->middleware->$method(...$arguments);
    }
}