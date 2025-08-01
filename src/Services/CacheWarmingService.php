<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Config;
use App\Agents\CacheAgent;
use App\Agents\GeoAgent;
use App\Agents\GoogleMapsAgent;
use App\Agents\RouteAgent;
use Exception;

/**
 * CacheWarmingService - Intelligentes Cache-Vorwärmen
 * 
 * Strategien:
 * 1. Pattern-basiertes Warming basierend auf historischen Daten
 * 2. Route-abhängiges Warming (alle Segmente einer häufigen Route)
 * 3. Time-basiertes Warming (Rush-Hour Routen vorwärmen)
 * 4. User-Pattern Warming (wiederkehrende Nutzer-Patterns)
 * 5. Predictive Warming basierend auf Wochentag/Uhrzeit
 * 
 * @author 2Brands Media GmbH
 */
class CacheWarmingService
{
    private Database $db;
    private Config $config;
    private CacheAgent $cacheAgent;
    private GeoAgent $geoAgent;
    private GoogleMapsAgent $mapsAgent;
    private RouteAgent $routeAgent;
    
    // Warming-Strategien
    private const STRATEGY_HISTORICAL = 'historical';
    private const STRATEGY_ROUTE_SEGMENTS = 'route_segments';
    private const STRATEGY_TIME_BASED = 'time_based';
    private const STRATEGY_USER_PATTERNS = 'user_patterns';
    private const STRATEGY_PREDICTIVE = 'predictive';
    
    // Warming-Prioritäten
    private const PRIORITY_CRITICAL = 1;    // Sofort ausführen
    private const PRIORITY_HIGH = 3;        // Binnen 5 Minuten
    private const PRIORITY_MEDIUM = 5;      // Binnen 30 Minuten
    private const PRIORITY_LOW = 8;         // Nächtliches Wartungsfenster
    
    public function __construct(
        Database $db, 
        Config $config,
        CacheAgent $cacheAgent,
        GeoAgent $geoAgent,
        GoogleMapsAgent $mapsAgent,
        RouteAgent $routeAgent
    ) {
        $this->db = $db;
        $this->config = $config;
        $this->cacheAgent = $cacheAgent;
        $this->geoAgent = $geoAgent;
        $this->mapsAgent = $mapsAgent;
        $this->routeAgent = $routeAgent;
    }

    /**
     * Hauptmethode für intelligentes Cache-Warming
     */
    public function executeWarmingStrategy(): array
    {
        $results = [
            'total_jobs_processed' => 0,
            'successful_warmings' => 0,
            'failed_warmings' => 0,
            'api_calls_made' => 0,
            'estimated_cost' => 0.0,
            'strategies_used' => [],
            'performance_improvement' => []
        ];
        
        // 1. Kritische Jobs zuerst (hohe Priorität)
        $criticalResults = $this->processCriticalWarmingJobs();
        $results = $this->mergeResults($results, $criticalResults);
        
        // 2. Historische Daten-basiertes Warming
        if ($this->shouldRunStrategy(self::STRATEGY_HISTORICAL)) {
            $historicalResults = $this->executeHistoricalWarming();
            $results = $this->mergeResults($results, $historicalResults);
            $results['strategies_used'][] = self::STRATEGY_HISTORICAL;
        }
        
        // 3. Route-Segment Warming für häufige Routen
        if ($this->shouldRunStrategy(self::STRATEGY_ROUTE_SEGMENTS)) {
            $routeResults = $this->executeRouteSegmentWarming();
            $results = $this->mergeResults($results, $routeResults);
            $results['strategies_used'][] = self::STRATEGY_ROUTE_SEGMENTS;
        }
        
        // 4. Zeit-basiertes Warming (Rush-Hour etc.)
        if ($this->shouldRunStrategy(self::STRATEGY_TIME_BASED)) {
            $timeResults = $this->executeTimeBasedWarming();
            $results = $this->mergeResults($results, $timeResults);
            $results['strategies_used'][] = self::STRATEGY_TIME_BASED;
        }
        
        // 5. User-Pattern basiertes Warming
        if ($this->shouldRunStrategy(self::STRATEGY_USER_PATTERNS)) {
            $userResults = $this->executeUserPatternWarming();
            $results = $this->mergeResults($results, $userResults);
            $results['strategies_used'][] = self::STRATEGY_USER_PATTERNS;
        }
        
        // 6. Predictive Warming für wahrscheinliche zukünftige Requests
        if ($this->shouldRunStrategy(self::STRATEGY_PREDICTIVE)) {
            $predictiveResults = $this->executePredictiveWarming();
            $results = $this->mergeResults($results, $predictiveResults);
            $results['strategies_used'][] = self::STRATEGY_PREDICTIVE;
        }
        
        // Performance-Verbesserung berechnen
        $results['performance_improvement'] = $this->calculatePerformanceImprovement($results);
        
        return $results;
    }

    /**
     * Kritische Warming-Jobs (hohe Priorität)
     */
    private function processCriticalWarmingJobs(): array
    {
        $jobs = $this->db->select(
            'SELECT * FROM cache_warming_queue 
             WHERE status = "pending" 
               AND priority <= ? 
               AND execute_after <= NOW()
             ORDER BY priority ASC, scheduled_at ASC 
             LIMIT 20',
            [self::PRIORITY_HIGH]
        );
        
        $results = ['successful_warmings' => 0, 'failed_warmings' => 0, 'api_calls_made' => 0];
        
        foreach ($jobs as $job) {
            try {
                $this->markJobAsProcessing($job['id']);
                $success = $this->executeWarmingJob($job);
                
                if ($success) {
                    $this->markJobAsCompleted($job['id']);
                    $results['successful_warmings']++;
                    $results['api_calls_made']++;
                } else {
                    $this->markJobAsFailed($job['id'], 'No data returned');
                    $results['failed_warmings']++;
                }
                
            } catch (Exception $e) {
                $this->markJobAsFailed($job['id'], $e->getMessage());
                $results['failed_warmings']++;
                error_log("Critical warming job failed: " . $e->getMessage());
            }
        }
        
        return $results;
    }

    /**
     * Historische Daten-basiertes Warming
     */
    private function executeHistoricalWarming(): array
    {
        // Häufig genutzte Cache-Keys der letzten 7 Tage identifizieren
        $frequentKeys = $this->db->select(
            'SELECT 
                cache_key, cache_type, 
                COUNT(*) as usage_count,
                AVG(hit_count) as avg_hits,
                JSON_EXTRACT(metadata, "$.options") as options
             FROM enhanced_cache 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
               AND hit_count > 5
               AND expires_at < DATE_ADD(NOW(), INTERVAL 2 HOUR)
             GROUP BY cache_key, cache_type
             HAVING usage_count > 3
             ORDER BY usage_count DESC, avg_hits DESC
             LIMIT 50'
        );
        
        $results = ['successful_warmings' => 0, 'failed_warmings' => 0, 'api_calls_made' => 0];
        
        foreach ($frequentKeys as $key) {
            // Nur wenn Cache bald abläuft oder bereits abgelaufen
            $existingCache = $this->cacheAgent->get($key['cache_key'], $key['cache_type']);
            
            if ($existingCache === null) {
                try {
                    $options = json_decode($key['options'] ?? '{}', true);
                    $data = $this->loadDataForCacheKey($key['cache_key'], $key['cache_type'], $options);
                    
                    if ($data !== null) {
                        $this->cacheAgent->set($key['cache_key'], $data, $key['cache_type'], $options);
                        $results['successful_warmings']++;
                        $results['api_calls_made']++;
                    }
                } catch (Exception $e) {
                    $results['failed_warmings']++;
                    error_log("Historical warming failed for {$key['cache_key']}: " . $e->getMessage());
                }
                
                // Rate-Limiting zwischen Requests
                usleep(250000); // 0.25 Sekunden
            }
        }
        
        return $results;
    }

    /**
     * Route-Segment Warming für häufige Routen
     */
    private function executeRouteSegmentWarming(): array
    {
        // Häufige Routen aus Playlists identifizieren
        $frequentRoutes = $this->db->select(
            'SELECT 
                p.id as playlist_id,
                COUNT(*) as usage_count,
                GROUP_CONCAT(s.address ORDER BY s.order_index) as route_addresses
             FROM playlists p
             JOIN stops s ON p.id = s.playlist_id
             WHERE p.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY p.id
             HAVING usage_count > 2
             ORDER BY usage_count DESC
             LIMIT 20'
        );
        
        $results = ['successful_warmings' => 0, 'failed_warmings' => 0, 'api_calls_made' => 0];
        
        foreach ($frequentRoutes as $route) {
            $addresses = explode(',', $route['route_addresses']);
            
            // Für jedes Segment der Route Traffic-Daten vorwärmen
            for ($i = 0; $i < count($addresses) - 1; $i++) {
                try {
                    $origin = trim($addresses[$i]);
                    $destination = trim($addresses[$i + 1]);
                    
                    // Geocoding für beide Adressen sicherstellen
                    $originGeo = $this->geoAgent->geocode($origin);
                    $destinationGeo = $this->geoAgent->geocode($destination);
                    
                    if ($originGeo && $destinationGeo) {
                        // Route mit Traffic vorwärmen
                        $routeOptions = [
                            'departure_time' => 'now',
                            'with_traffic' => true,
                            'mode' => 'driving'
                        ];
                        
                        $routeData = $this->mapsAgent->calculateRoute(
                            [
                                ['latitude' => $originGeo['latitude'], 'longitude' => $originGeo['longitude']],
                                ['latitude' => $destinationGeo['latitude'], 'longitude' => $destinationGeo['longitude']]
                            ],
                            $routeOptions
                        );
                        
                        if ($routeData) {
                            $results['successful_warmings']++;
                            $results['api_calls_made']++;
                        }
                    }
                    
                } catch (Exception $e) {
                    $results['failed_warmings']++;
                    error_log("Route segment warming failed: " . $e->getMessage());
                }
                
                // Rate-Limiting
                usleep(500000); // 0.5 Sekunden zwischen Segmenten
            }
        }
        
        return $results;
    }

    /**
     * Zeit-basiertes Warming (Rush-Hour, Wochentage)
     */
    private function executeTimeBasedWarming(): array
    {
        $results = ['successful_warmings' => 0, 'failed_warmings' => 0, 'api_calls_made' => 0];
        $currentHour = (int)date('H');
        $currentWeekday = (int)date('N'); // 1 = Montag
        
        // Nur während bestimmter Zeiten aktiv
        $isRushHour = ($currentHour >= 7 && $currentHour <= 9) || ($currentHour >= 17 && $currentHour <= 19);
        $isWorkday = $currentWeekday >= 1 && $currentWeekday <= 5;
        
        if (!$isWorkday && !$isRushHour) {
            return $results; // Keine Zeit-basierte Warming außerhalb der Hauptzeiten
        }
        
        // Häufig genutzte Routen zu dieser Tageszeit
        $timeBasedRoutes = $this->db->select(
            'SELECT 
                cache_key, cache_type, hit_count,
                JSON_EXTRACT(metadata, "$.options") as options
             FROM enhanced_cache 
             WHERE cache_type IN ("route", "traffic")
               AND HOUR(created_at) BETWEEN ? AND ?
               AND DAYOFWEEK(created_at) BETWEEN ? AND ?
               AND hit_count > 3
               AND expires_at < DATE_ADD(NOW(), INTERVAL 1 HOUR)
             ORDER BY hit_count DESC
             LIMIT 30',
            [
                max(1, $currentHour - 1), min(23, $currentHour + 1), // ±1 Stunde
                $isWorkday ? 2 : 1, $isWorkday ? 6 : 7 // Werktage oder Wochenende
            ]
        );
        
        foreach ($timeBasedRoutes as $route) {
            try {
                $options = json_decode($route['options'] ?? '{}', true);
                $options['departure_time'] = 'now'; // Aktuelle Traffic-Daten
                
                $data = $this->loadDataForCacheKey($route['cache_key'], $route['cache_type'], $options);
                
                if ($data !== null) {
                    $this->cacheAgent->set($route['cache_key'], $data, $route['cache_type'], $options);
                    $results['successful_warmings']++;
                    $results['api_calls_made']++;
                }
                
            } catch (Exception $e) {
                $results['failed_warmings']++;
                error_log("Time-based warming failed: " . $e->getMessage());
            }
            
            usleep(300000); // 0.3 Sekunden
        }
        
        return $results;
    }

    /**
     * User-Pattern basiertes Warming
     */
    private function executeUserPatternWarming(): array
    {
        // Wiederkehrende Benutzer-Patterns analysieren
        $userPatterns = $this->db->select(
            'SELECT 
                u.id as user_id,
                GROUP_CONCAT(DISTINCT s.address) as frequent_addresses,
                COUNT(DISTINCT p.id) as playlist_count
             FROM users u
             JOIN playlists p ON u.id = p.user_id
             JOIN stops s ON p.id = s.playlist_id
             WHERE p.created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
             GROUP BY u.id
             HAVING playlist_count > 3
             ORDER BY playlist_count DESC
             LIMIT 15'
        );
        
        $results = ['successful_warmings' => 0, 'failed_warmings' => 0, 'api_calls_made' => 0];
        
        foreach ($userPatterns as $pattern) {
            $addresses = array_unique(explode(',', $pattern['frequent_addresses']));
            
            // Geocoding für häufige Adressen vorwärmen
            foreach ($addresses as $address) {
                $address = trim($address);
                if (empty($address)) continue;
                
                try {
                    $geocode = $this->geoAgent->geocode($address);
                    if ($geocode) {
                        $results['successful_warmings']++;
                        $results['api_calls_made']++;
                    }
                } catch (Exception $e) {
                    $results['failed_warmings']++;
                }
                
                usleep(100000); // 0.1 Sekunden
            }
        }
        
        return $results;
    }

    /**
     * Predictive Warming basierend auf Machine Learning Patterns
     */
    private function executePredictiveWarming(): array
    {
        $results = ['successful_warmings' => 0, 'failed_warmings' => 0, 'api_calls_made' => 0];
        
        // Cache-Einträge mit hohem Prediction-Score aber niedrigem aktuellen Cache
        $predictiveCandidates = $this->db->select(
            'SELECT 
                cache_key, cache_type, prediction_score,
                JSON_EXTRACT(usage_pattern, "$") as pattern_data,
                JSON_EXTRACT(metadata, "$.options") as options
             FROM enhanced_cache 
             WHERE prediction_score > 0.7
               AND (expires_at < DATE_ADD(NOW(), INTERVAL 30 MINUTE) OR expires_at IS NULL)
               AND hit_count > 2
             ORDER BY prediction_score DESC
             LIMIT 25'
        );
        
        foreach ($predictiveCandidates as $candidate) {
            try {
                $options = json_decode($candidate['options'] ?? '{}', true);
                
                // Pattern-Daten auslesen für intelligentere Vorhersagen
                $patternData = json_decode($candidate['pattern_data'] ?? '{}', true);
                
                // Basierend auf Pattern anpassen
                if (isset($patternData['preferred_departure_time'])) {
                    $options['departure_time'] = $patternData['preferred_departure_time'];
                }
                
                $data = $this->loadDataForCacheKey(
                    $candidate['cache_key'], 
                    $candidate['cache_type'], 
                    $options
                );
                
                if ($data !== null) {
                    // Prediction-Score erhöhen bei erfolgreichem Warming
                    $this->updatePredictionScore($candidate['cache_key'], 
                                               min(1.0, $candidate['prediction_score'] + 0.05));
                    
                    $this->cacheAgent->set($candidate['cache_key'], $data, 
                                         $candidate['cache_type'], $options);
                    $results['successful_warmings']++;
                    $results['api_calls_made']++;
                } else {
                    // Prediction-Score reduzieren bei Fehlschlag
                    $this->updatePredictionScore($candidate['cache_key'], 
                                               max(0.0, $candidate['prediction_score'] - 0.1));
                }
                
            } catch (Exception $e) {
                $results['failed_warmings']++;
                error_log("Predictive warming failed: " . $e->getMessage());
            }
            
            usleep(400000); // 0.4 Sekunden
        }
        
        return $results;
    }

    /**
     * Hilfsmethoden
     */
    private function shouldRunStrategy(string $strategy): bool
    {
        $currentHour = (int)date('H');
        $currentWeekday = (int)date('N');
        
        // Verschiedene Strategien zu verschiedenen Zeiten
        switch ($strategy) {
            case self::STRATEGY_HISTORICAL:
                return true; // Immer verfügbar
                
            case self::STRATEGY_ROUTE_SEGMENTS:
                return $currentHour >= 6 && $currentHour <= 20; // Tagsüber
                
            case self::STRATEGY_TIME_BASED:
                return $currentWeekday >= 1 && $currentWeekday <= 5; // Werktage
                
            case self::STRATEGY_USER_PATTERNS:
                return $currentHour >= 8 && $currentHour <= 18; // Geschäftszeiten
                
            case self::STRATEGY_PREDICTIVE:
                return true; // Immer verfügbar
                
            default:
                return false;
        }
    }

    private function loadDataForCacheKey(string $cacheKey, string $cacheType, array $options): mixed
    {
        // Cache-Key parsen um ursprüngliche Parameter zu rekonstruieren
        $keyParts = explode('_', $cacheKey);
        
        switch ($cacheType) {
            case 'geocoding':
                if (isset($options['address'])) {
                    return $this->geoAgent->geocode($options['address']);
                }
                break;
                
            case 'route':
                if (isset($options['waypoints']) && is_array($options['waypoints'])) {
                    return $this->mapsAgent->calculateRoute($options['waypoints'], $options);
                }
                break;
                
            case 'traffic':
                if (isset($options['origin'], $options['destination'])) {
                    return $this->mapsAgent->calculateRoute([
                        $options['origin'], 
                        $options['destination']
                    ], array_merge($options, ['departure_time' => 'now']));
                }
                break;
        }
        
        return null;
    }

    private function markJobAsProcessing(int $jobId): void
    {
        $this->db->query(
            'UPDATE cache_warming_queue 
             SET status = "processing", attempts = attempts + 1 
             WHERE id = ?',
            [$jobId]
        );
    }

    private function markJobAsCompleted(int $jobId): void
    {
        $this->db->query(
            'UPDATE cache_warming_queue 
             SET status = "completed", processed_at = NOW() 
             WHERE id = ?',
            [$jobId]
        );
    }

    private function markJobAsFailed(int $jobId, string $errorMessage): void
    {
        $this->db->query(
            'UPDATE cache_warming_queue 
             SET status = "failed", error_message = ?, processed_at = NOW() 
             WHERE id = ?',
            [$errorMessage, $jobId]
        );
    }

    private function updatePredictionScore(string $cacheKey, float $newScore): void
    {
        $this->db->query(
            'UPDATE enhanced_cache 
             SET prediction_score = ? 
             WHERE cache_key = ?',
            [$newScore, $cacheKey]
        );
    }

    private function mergeResults(array $target, array $source): array
    {
        $target['total_jobs_processed'] += $source['successful_warmings'] + $source['failed_warmings'];
        $target['successful_warmings'] += $source['successful_warmings'];
        $target['failed_warmings'] += $source['failed_warmings'];
        $target['api_calls_made'] += $source['api_calls_made'];
        $target['estimated_cost'] += $source['api_calls_made'] * 0.005; // Durchschnittliche API-Kosten
        
        return $target;
    }

    private function calculatePerformanceImprovement(array $results): array
    {
        $expectedCacheHitRate = min(0.95, 0.6 + ($results['successful_warmings'] * 0.01));
        $expectedResponseTimeImprovement = $results['successful_warmings'] * 2; // ms
        $expectedApiReduction = ($results['successful_warmings'] / max(1, $results['api_calls_made'])) * 100;
        
        return [
            'expected_cache_hit_rate' => round($expectedCacheHitRate * 100, 2),
            'expected_response_time_improvement_ms' => round($expectedResponseTimeImprovement, 2),
            'expected_api_reduction_percent' => round($expectedApiReduction, 2),
            'estimated_daily_cost_savings' => round($results['estimated_cost'] * 10, 4) // Extrapolation
        ];
    }

    /**
     * Öffentliche API für manuelles Warming
     */
    public function warmSpecificRoute(array $waypoints, array $options = []): bool
    {
        try {
            $routeData = $this->mapsAgent->calculateRoute($waypoints, $options);
            if ($routeData) {
                $cacheKey = 'route_' . md5(json_encode($waypoints) . json_encode($options));
                $this->cacheAgent->set($cacheKey, $routeData, 'route', $options);
                return true;
            }
        } catch (Exception $e) {
            error_log("Manual route warming failed: " . $e->getMessage());
        }
        
        return false;
    }

    public function warmGeocodingForAddresses(array $addresses): int
    {
        $warmedCount = 0;
        
        foreach ($addresses as $address) {
            try {
                $geocode = $this->geoAgent->geocode($address);
                if ($geocode) {
                    $warmedCount++;
                }
                usleep(100000); // Rate-Limiting
            } catch (Exception $e) {
                error_log("Address warming failed for '{$address}': " . $e->getMessage());
            }
        }
        
        return $warmedCount;
    }
}