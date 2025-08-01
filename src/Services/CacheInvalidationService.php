<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Config;
use App\Agents\CacheAgent;
use Exception;

/**
 * CacheInvalidationService - Intelligente Cache-Invalidierung
 * 
 * Strategien für smarte Invalidierung:
 * 1. Traffic-basierte Invalidierung (Rush-Hour, Verkehrsstörungen)
 * 2. Zeit-basierte Invalidierung (unterschiedliche TTL je nach Tageszeit)
 * 3. Event-basierte Invalidierung (Playlist-Änderungen, Nutzer-Updates)
 * 4. Dependency-basierte Invalidierung (abhängige Cache-Keys)
 * 5. Confidence-basierte Invalidierung (niedrige Confidence = frühere Invalidierung)
 * 
 * @author 2Brands Media GmbH
 */
class CacheInvalidationService
{
    private Database $db;
    private Config $config;
    private CacheAgent $cacheAgent;
    
    // Invalidierungsstrategien
    private const STRATEGY_TRAFFIC_BASED = 'traffic_based';
    private const STRATEGY_TIME_BASED = 'time_based';
    private const STRATEGY_EVENT_BASED = 'event_based';
    private const STRATEGY_DEPENDENCY_BASED = 'dependency_based';
    private const STRATEGY_CONFIDENCE_BASED = 'confidence_based';
    
    // Traffic-Schweregrad-Schwellenwerte
    private const TRAFFIC_SEVERITY_THRESHOLDS = [
        'low' => 1.1,      // 10% länger als normal
        'medium' => 1.25,  // 25% länger als normal
        'high' => 1.4,     // 40% länger als normal
        'severe' => 1.6    // 60% länger als normal
    ];

    public function __construct(Database $db, Config $config, CacheAgent $cacheAgent)
    {
        $this->db = $db;
        $this->config = $config;
        $this->cacheAgent = $cacheAgent;
    }

    /**
     * Hauptmethode für intelligente Cache-Invalidierung
     */
    public function runIntelligentInvalidation(): array
    {
        $results = [
            'total_checked' => 0,
            'invalidated' => 0,
            'strategies_applied' => [],
            'performance_impact' => [],
            'recommendations' => []
        ];
        
        // 1. Traffic-basierte Invalidierung
        $trafficResults = $this->applyTrafficBasedInvalidation();
        $results = $this->mergeInvalidationResults($results, $trafficResults, self::STRATEGY_TRAFFIC_BASED);
        
        // 2. Zeit-basierte Invalidierung
        $timeResults = $this->applyTimeBasedInvalidation();
        $results = $this->mergeInvalidationResults($results, $timeResults, self::STRATEGY_TIME_BASED);
        
        // 3. Event-basierte Invalidierung
        $eventResults = $this->applyEventBasedInvalidation();
        $results = $this->mergeInvalidationResults($results, $eventResults, self::STRATEGY_EVENT_BASED);
        
        // 4. Dependency-basierte Invalidierung
        $dependencyResults = $this->applyDependencyBasedInvalidation();
        $results = $this->mergeInvalidationResults($results, $dependencyResults, self::STRATEGY_DEPENDENCY_BASED);
        
        // 5. Confidence-basierte Invalidierung
        $confidenceResults = $this->applyConfidenceBasedInvalidation();
        $results = $this->mergeInvalidationResults($results, $confidenceResults, self::STRATEGY_CONFIDENCE_BASED);
        
        // Performance-Impact berechnen
        $results['performance_impact'] = $this->calculatePerformanceImpact($results);
        
        // Empfehlungen generieren
        $results['recommendations'] = $this->generateInvalidationRecommendations($results);
        
        return $results;
    }

    /**
     * Traffic-basierte Invalidierung
     * Invalidiert Cache-Einträge basierend auf aktuellen Verkehrsbedingungen
     */
    private function applyTrafficBasedInvalidation(): array
    {
        $results = ['checked' => 0, 'invalidated' => 0, 'reason' => []];
        
        // Aktuelle Traffic-Situation analysieren
        $currentTrafficConditions = $this->analyzeCurrentTrafficConditions();
        
        if ($currentTrafficConditions['severity'] === 'normal') {
            return $results; // Keine Invalidierung bei normalem Verkehr
        }
        
        // Route und Traffic-Caches prüfen die von aktuellen Bedingungen betroffen sind
        $affectedCaches = $this->db->select(
            "SELECT id, cache_key, cache_type, created_at, 
                    JSON_EXTRACT(metadata, '$.traffic_severity') as cached_severity,
                    JSON_EXTRACT(metadata, '$.route_areas') as route_areas
             FROM enhanced_cache 
             WHERE cache_type IN ('route', 'traffic', 'matrix')
               AND expires_at > NOW()
               AND JSON_EXTRACT(metadata, '$.with_traffic') = true"
        );
        
        foreach ($affectedCaches as $cache) {
            $results['checked']++;
            
            $shouldInvalidate = false;
            $reason = '';
            
            // Prüfen ob Route in betroffenen Gebieten
            $routeAreas = json_decode($cache['route_areas'] ?? '[]', true);
            $affectedAreas = $currentTrafficConditions['affected_areas'] ?? [];
            
            if (!empty(array_intersect($routeAreas, $affectedAreas))) {
                $shouldInvalidate = true;
                $reason = 'Route in verkehrsbetroffenen Gebieten';
            }
            
            // Prüfen ob sich Traffic-Schweregrad deutlich geändert hat
            $cachedSeverity = $cache['cached_severity'] ?? 'unknown';
            $currentSeverity = $currentTrafficConditions['severity'];
            
            if ($this->hasTrafficSeverityChanged($cachedSeverity, $currentSeverity)) {
                $shouldInvalidate = true;
                $reason = "Traffic-Schweregrad geändert: {$cachedSeverity} → {$currentSeverity}";
            }
            
            // Cache-Alter berücksichtigen (ältere Caches bei Traffic-Problemen schneller invalidieren)
            $cacheAge = time() - strtotime($cache['created_at']);
            $maxAge = $this->getTrafficBasedMaxAge($currentSeverity);
            
            if ($cacheAge > $maxAge) {
                $shouldInvalidate = true;
                $reason = "Cache zu alt für aktuelle Traffic-Bedingungen ({$cacheAge}s > {$maxAge}s)";
            }
            
            if ($shouldInvalidate) {
                $this->invalidateCacheEntry($cache['id'], $cache['cache_key'], 'traffic_based', $reason);
                $results['invalidated']++;
                $results['reason'][] = $reason;
            }
        }
        
        return $results;
    }

    /**
     * Zeit-basierte Invalidierung
     * Passt TTL basierend auf Tageszeit und Wochentag an
     */
    private function applyTimeBasedInvalidation(): array
    {
        $results = ['checked' => 0, 'invalidated' => 0, 'reason' => []];
        
        $currentHour = (int)date('H');
        $currentWeekday = (int)date('N'); // 1 = Montag
        $isWorkday = $currentWeekday >= 1 && $currentWeekday <= 5;
        $isRushHour = ($currentHour >= 7 && $currentHour <= 9) || ($currentHour >= 17 && $currentHour <= 19);
        
        // Cache-Einträge mit zeitabhängigen Daten
        $timeBasedCaches = $this->db->select(
            "SELECT id, cache_key, cache_type, created_at, expires_at,
                    JSON_EXTRACT(metadata, '$.time_sensitive') as time_sensitive,
                    JSON_EXTRACT(metadata, '$.created_hour') as created_hour,
                    JSON_EXTRACT(metadata, '$.created_weekday') as created_weekday
             FROM enhanced_cache 
             WHERE cache_type IN ('route', 'traffic', 'matrix')
               AND expires_at > NOW()
               AND (JSON_EXTRACT(metadata, '$.time_sensitive') = true
                    OR JSON_EXTRACT(metadata, '$.with_traffic') = true)"
        );
        
        foreach ($timeBasedCaches as $cache) {
            $results['checked']++;
            
            $shouldInvalidate = false;
            $reason = '';
            
            $createdHour = (int)($cache['created_hour'] ?? date('H', strtotime($cache['created_at'])));
            $createdWeekday = (int)($cache['created_weekday'] ?? date('N', strtotime($cache['created_at'])));
            
            // Rush-Hour vs. Non-Rush-Hour Wechsel
            $wasRushHour = ($createdHour >= 7 && $createdHour <= 9) || ($createdHour >= 17 && $createdHour <= 19);
            if ($isRushHour !== $wasRushHour) {
                $shouldInvalidate = true;
                $reason = $isRushHour ? 'Rush-Hour begonnen' : 'Rush-Hour beendet';
            }
            
            // Werktag vs. Wochenende Wechsel
            $wasWorkday = $createdWeekday >= 1 && $createdWeekday <= 5;
            if ($isWorkday !== $wasWorkday) {
                $shouldInvalidate = true;
                $reason = $isWorkday ? 'Werktag begonnen' : 'Wochenende begonnen';
            }
            
            // Dynamische TTL-Anpassung basierend auf aktueller Zeit
            $expectedTTL = $this->calculateTimeBasedTTL($cache['cache_type'], $isRushHour, $isWorkday);
            $currentAge = time() - strtotime($cache['created_at']);
            
            if ($currentAge > $expectedTTL) {
                $shouldInvalidate = true;
                $reason = "TTL für aktuelle Zeitbedingungen überschritten";
            }
            
            if ($shouldInvalidate) {
                $this->invalidateCacheEntry($cache['id'], $cache['cache_key'], 'time_based', $reason);
                $results['invalidated']++;
                $results['reason'][] = $reason;
            }
        }
        
        return $results;
    }

    /**
     * Event-basierte Invalidierung
     * Invalidiert Cache bei relevanten System-Events
     */
    private function applyEventBasedInvalidation(): array
    {
        $results = ['checked' => 0, 'invalidated' => 0, 'reason' => []];
        
        // Kürzliche Events prüfen die Cache-Invalidierung auslösen könnten
        $recentEvents = $this->getRecentSystemEvents();
        
        foreach ($recentEvents as $event) {
            $affectedCaches = $this->findCachesAffectedByEvent($event);
            
            foreach ($affectedCaches as $cache) {
                $results['checked']++;
                
                $reason = "Event-basierte Invalidierung: {$event['type']} - {$event['description']}";
                $this->invalidateCacheEntry($cache['id'], $cache['cache_key'], 'event_based', $reason);
                $results['invalidated']++;
                $results['reason'][] = $reason;
            }
        }
        
        return $results;
    }

    /**
     * Dependency-basierte Invalidierung
     * Invalidiert abhängige Cache-Einträge wenn Parent-Cache invalidiert wird
     */
    private function applyDependencyBasedInvalidation(): array
    {
        $results = ['checked' => 0, 'invalidated' => 0, 'reason' => []];
        
        // Cache-Einträge mit Parent-Dependencies finden
        $dependentCaches = $this->db->select(
            "SELECT id, cache_key, cache_type, parent_keys
             FROM enhanced_cache 
             WHERE parent_keys IS NOT NULL 
               AND JSON_LENGTH(parent_keys) > 0
               AND expires_at > NOW()"
        );
        
        foreach ($dependentCaches as $cache) {
            $results['checked']++;
            
            $parentKeys = json_decode($cache['parent_keys'], true) ?? [];
            $invalidParents = [];
            
            foreach ($parentKeys as $parentKey) {
                // Prüfen ob Parent-Cache noch existiert und gültig ist
                $parentExists = $this->db->selectOne(
                    'SELECT id FROM enhanced_cache 
                     WHERE cache_key = ? AND expires_at > NOW()',
                    [$parentKey]
                );
                
                if (!$parentExists) {
                    $invalidParents[] = $parentKey;
                }
            }
            
            if (!empty($invalidParents)) {
                $reason = "Parent-Cache invalidiert: " . implode(', ', $invalidParents);
                $this->invalidateCacheEntry($cache['id'], $cache['cache_key'], 'dependency_based', $reason);
                $results['invalidated']++;
                $results['reason'][] = $reason;
            }
        }
        
        return $results;
    }

    /**
     * Confidence-basierte Invalidierung
     * Invalidiert Cache-Einträge mit niedriger Confidence früher
     */
    private function applyConfidenceBasedInvalidation(): array
    {
        $results = ['checked' => 0, 'invalidated' => 0, 'reason' => []];
        
        // Cache-Einträge mit niedriger Confidence
        $lowConfidenceCaches = $this->db->select(
            "SELECT id, cache_key, cache_type, created_at,
                    JSON_EXTRACT(metadata, '$.confidence') as confidence,
                    prediction_score
             FROM enhanced_cache 
             WHERE (JSON_EXTRACT(metadata, '$.confidence') < 0.7 
                    OR prediction_score < 0.5)
               AND expires_at > NOW()
               AND cache_type = 'geocoding'"
        );
        
        foreach ($lowConfidenceCaches as $cache) {
            $results['checked']++;
            
            $confidence = (float)($cache['confidence'] ?? 1.0);
            $predictionScore = (float)($cache['prediction_score'] ?? 1.0);
            
            // Niedrige Confidence = kürzere TTL
            $baseAge = time() - strtotime($cache['created_at']);
            $confidenceFactor = max(0.3, min($confidence, $predictionScore));
            $adjustedMaxAge = 86400 * $confidenceFactor; // Basis: 24h, angepasst nach Confidence
            
            if ($baseAge > $adjustedMaxAge) {
                $reason = "Niedrige Confidence ({$confidence}) - vorzeitige Invalidierung nach {$baseAge}s";
                $this->invalidateCacheEntry($cache['id'], $cache['cache_key'], 'confidence_based', $reason);
                $results['invalidated']++;
                $results['reason'][] = $reason;
            }
        }
        
        return $results;
    }

    /**
     * Analysiert aktuelle Verkehrsbedingungen
     */
    private function analyzeCurrentTrafficConditions(): array
    {
        // Vereinfachte Traffic-Analyse - in Realität würde hier Google Traffic API abgefragt
        $currentHour = (int)date('H');
        $currentWeekday = (int)date('N');
        
        $severity = 'normal';
        $affectedAreas = [];
        
        // Rush-Hour Simulation
        if (($currentHour >= 7 && $currentHour <= 9) || ($currentHour >= 17 && $currentHour <= 19)) {
            if ($currentWeekday >= 1 && $currentWeekday <= 5) {
                $severity = 'medium';
                $affectedAreas = ['city_center', 'highway_a1', 'business_district'];
            }
        }
        
        // Spätabends weniger Traffic
        if ($currentHour >= 22 || $currentHour <= 5) {
            $severity = 'low';
        }
        
        return [
            'severity' => $severity,
            'affected_areas' => $affectedAreas,
            'timestamp' => time(),
            'source' => 'system_analysis'
        ];
    }

    /**
     * Prüft ob sich Traffic-Schweregrad signifikant geändert hat
     */
    private function hasTrafficSeverityChanged(string $cached, string $current): bool
    {
        $severityLevels = ['low' => 1, 'normal' => 2, 'medium' => 3, 'high' => 4, 'severe' => 5];
        
        $cachedLevel = $severityLevels[$cached] ?? 2;
        $currentLevel = $severityLevels[$current] ?? 2;
        
        // Änderung um mehr als 1 Level = signifikant
        return abs($cachedLevel - $currentLevel) > 1;
    }

    /**
     * Berechnet maximales Cache-Alter basierend auf Traffic-Severity
     */
    private function getTrafficBasedMaxAge(string $severity): int
    {
        return match($severity) {
            'low' => 3600,      // 1 Stunde bei wenig Verkehr
            'normal' => 1800,   // 30 Minuten bei normalem Verkehr
            'medium' => 600,    // 10 Minuten bei mittlerem Verkehr
            'high' => 300,      // 5 Minuten bei hohem Verkehr
            'severe' => 120,    // 2 Minuten bei schwerem Verkehr
            default => 1800
        };
    }

    /**
     * Berechnet zeit-basierte TTL
     */
    private function calculateTimeBasedTTL(string $cacheType, bool $isRushHour, bool $isWorkday): int
    {
        $baseTTL = match($cacheType) {
            'route' => 3600,      // 1 Stunde
            'traffic' => 300,     // 5 Minuten
            'matrix' => 1800,     // 30 Minuten
            default => 1800
        };
        
        // Rush-Hour = kürzere TTL
        if ($isRushHour) {
            $baseTTL = (int)($baseTTL * 0.5);
        }
        
        // Wochenende = längere TTL (weniger Verkehr)
        if (!$isWorkday) {
            $baseTTL = (int)($baseTTL * 1.5);
        }
        
        return $baseTTL;
    }

    /**
     * Holt kürzliche System-Events
     */
    private function getRecentSystemEvents(): array
    {
        return $this->db->select(
            "SELECT event_type as type, description, created_at, metadata
             FROM audit_log 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
               AND event_type IN ('playlist_updated', 'user_updated', 'stops_modified', 'route_changed')
             ORDER BY created_at DESC"
        );
    }

    /**
     * Findet Caches die von einem Event betroffen sind
     */
    private function findCachesAffectedByEvent(array $event): array
    {
        $affectedCaches = [];
        
        switch ($event['type']) {
            case 'playlist_updated':
                // Playlist-spezifische Caches invalidieren
                $metadata = json_decode($event['metadata'] ?? '{}', true);
                $playlistId = $metadata['playlist_id'] ?? null;
                
                if ($playlistId) {
                    $affectedCaches = $this->db->select(
                        "SELECT id, cache_key FROM enhanced_cache 
                         WHERE JSON_EXTRACT(invalidation_tags, '$') LIKE ?
                           AND expires_at > NOW()",
                        ['%playlist_' . $playlistId . '%']
                    );
                }
                break;
                
            case 'stops_modified':
                // Alle Route-Caches invalidieren
                $affectedCaches = $this->db->select(
                    "SELECT id, cache_key FROM enhanced_cache 
                     WHERE cache_type IN ('route', 'matrix')
                       AND expires_at > NOW()"
                );
                break;
        }
        
        return $affectedCaches;
    }

    /**
     * Invalidiert einen spezifischen Cache-Eintrag
     */
    private function invalidateCacheEntry(int $id, string $cacheKey, string $strategy, string $reason): void
    {
        // Cache-Eintrag löschen
        $this->db->delete('enhanced_cache', 'id = ?', [$id]);
        
        // Invalidierung protokollieren
        $this->db->insert('cache_invalidations', [
            'cache_key' => $cacheKey,
            'strategy' => $strategy,
            'reason' => $reason,
            'invalidated_at' => date('Y-m-d H:i:s')
        ]);
        
        // Memory-Cache auch invalidieren falls vorhanden
        // (Dies würde in der echten CacheAgent-Implementation passieren)
    }

    /**
     * Hilfsmethoden für Results-Management
     */
    private function mergeInvalidationResults(array $target, array $source, string $strategy): array
    {
        $target['total_checked'] += $source['checked'];
        $target['invalidated'] += $source['invalidated'];
        
        if ($source['invalidated'] > 0) {
            $target['strategies_applied'][] = [
                'strategy' => $strategy,
                'checked' => $source['checked'],
                'invalidated' => $source['invalidated'],
                'reasons' => $source['reason'] ?? []
            ];
        }
        
        return $target;
    }

    private function calculatePerformanceImpact(array $results): array
    {
        $totalInvalidated = $results['invalidated'];
        
        // Geschätzte Performance-Auswirkungen
        return [
            'cache_freshness_improvement' => min(100, $totalInvalidated * 2), // %
            'response_accuracy_improvement' => min(100, $totalInvalidated * 1.5), // %
            'estimated_api_calls_increase' => $totalInvalidated, // Neue API-Calls nötig
            'memory_freed_mb' => round($totalInvalidated * 0.05, 2) // Geschätzter Speicher-Gewinn
        ];
    }

    private function generateInvalidationRecommendations(array $results): array
    {
        $recommendations = [];
        
        if ($results['invalidated'] > 100) {
            $recommendations[] = [
                'type' => 'warning',
                'message' => 'Hohe Anzahl von Invalidierungen - TTL-Strategien überprüfen',
                'action' => 'TTL-Konfiguration anpassen'
            ];
        }
        
        if ($results['invalidated'] === 0 && $results['total_checked'] > 0) {
            $recommendations[] = [
                'type' => 'info',
                'message' => 'Keine Invalidierungen nötig - Cache-System läuft optimal',
                'action' => 'Aktueller Zustand beibehalten'
            ];
        }
        
        $strategyCounts = array_column($results['strategies_applied'], 'invalidated', 'strategy');
        $mostActiveStrategy = array_key_first($strategyCounts) ?? null;
        
        if ($mostActiveStrategy && $strategyCounts[$mostActiveStrategy] > 50) {
            $recommendations[] = [
                'type' => 'optimization',
                'message' => "Strategie '{$mostActiveStrategy}' sehr aktiv - möglicherweise optimierbar",
                'action' => 'Strategie-Parameter fine-tunen'
            ];
        }
        
        return $recommendations;
    }

    /**
     * Manuelle Invalidierung für spezifische Szenarien
     */
    public function invalidateByPlaylist(int $playlistId): int
    {
        return $this->cacheAgent->invalidateByTags(['playlist_' . $playlistId]);
    }

    public function invalidateTrafficCaches(array $affectedAreas = []): int
    {
        $whereClause = "cache_type IN ('route', 'traffic', 'matrix') 
                       AND JSON_EXTRACT(metadata, '$.with_traffic') = true";
        
        if (!empty($affectedAreas)) {
            $areaConditions = array_map(function($area) {
                return "JSON_EXTRACT(metadata, '\$.route_areas') LIKE '%{$area}%'";
            }, $affectedAreas);
            $whereClause .= " AND (" . implode(' OR ', $areaConditions) . ")";
        }
        
        $affectedKeys = $this->db->select(
            "SELECT cache_key FROM enhanced_cache WHERE {$whereClause}"
        );
        
        $count = 0;
        foreach ($affectedKeys as $key) {
            $this->invalidateCacheEntry(0, $key['cache_key'], 'manual_traffic', 'Manual traffic invalidation');
            $count++;
        }
        
        return $count;
    }

    public function getInvalidationStats(int $hours = 24): array
    {
        $stats = $this->db->select(
            "SELECT 
                strategy,
                COUNT(*) as count,
                COUNT(DISTINCT cache_key) as unique_keys
             FROM cache_invalidations 
             WHERE invalidated_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
             GROUP BY strategy
             ORDER BY count DESC",
            [$hours]
        );
        
        return [
            'total_invalidations' => array_sum(array_column($stats, 'count')),
            'by_strategy' => $stats,
            'timeframe_hours' => $hours
        ];
    }
}