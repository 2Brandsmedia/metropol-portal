<?php

declare(strict_types=1);

namespace App\Agents;

use App\Core\Database;
use App\Core\Config;
use Exception;
use PDO;

/**
 * CacheAgent - Intelligentes Multi-Layer Cache System
 * 
 * Ziel: 50% Reduktion der externen API-Aufrufe durch:
 * - Multi-Layer Caching (Memory, Database, Browser)
 * - Predictive Caching basierend auf Nutzungspatterns
 * - Intelligente Cache-Warming Strategien
 * - Optimierte TTL basierend auf Datenvolatilität
 * - Geteilte Caches zwischen ähnlichen Requests
 * 
 * Performance-Ziele:
 * - Cache-Hit-Rate > 80%
 * - Cache-Response < 50ms
 * - API-Reduktion um 50%
 * 
 * @author 2Brands Media GmbH
 */
class CacheAgent
{
    private Database $db;
    private Config $config;
    
    // Cache-Layer
    private const LAYER_MEMORY = 'memory';
    private const LAYER_DATABASE = 'database';
    private const LAYER_BROWSER = 'browser';
    private const LAYER_SHARED = 'shared';
    
    // Cache-Types
    private const TYPE_ROUTE = 'route';
    private const TYPE_GEOCODING = 'geocoding';
    private const TYPE_TRAFFIC = 'traffic';
    private const TYPE_MATRIX = 'matrix';
    private const TYPE_AUTOCOMPLETE = 'autocomplete';
    
    // TTL-Strategien basierend auf Datenvolatilität
    private const TTL_STRATEGIES = [
        self::TYPE_GEOCODING => [
            'base' => 2592000,      // 30 Tage - sehr stabil
            'confidence_factor' => true,
            'min' => 86400,         // 1 Tag minimum
            'max' => 7776000        // 90 Tage maximum
        ],
        self::TYPE_ROUTE => [
            'base' => 3600,         // 1 Stunde - mittlere Volatilität
            'traffic_factor' => true,
            'min' => 300,           // 5 Minuten minimum
            'max' => 86400          // 1 Tag maximum
        ],
        self::TYPE_TRAFFIC => [
            'base' => 300,          // 5 Minuten - hoch volatil
            'time_factor' => true,
            'min' => 60,            // 1 Minute minimum
            'max' => 900            // 15 Minuten maximum
        ],
        self::TYPE_MATRIX => [
            'base' => 1800,         // 30 Minuten
            'distance_factor' => true,
            'min' => 300,           // 5 Minuten minimum
            'max' => 3600           // 1 Stunde maximum
        ],
        self::TYPE_AUTOCOMPLETE => [
            'base' => 3600,         // 1 Stunde
            'popularity_factor' => true,
            'min' => 600,           // 10 Minuten minimum
            'max' => 86400          // 1 Tag maximum
        ]
    ];
    
    // Memory-Cache für aktuelle Session
    private array $memoryCache = [];
    
    // Performance-Tracking
    private array $sessionStats = [
        'hits' => 0,
        'misses' => 0,
        'total_response_time' => 0,
        'api_calls_saved' => 0,
        'cost_saved' => 0.0
    ];

    public function __construct(Database $db, Config $config)
    {
        $this->db = $db;
        $this->config = $config;
    }

    /**
     * Intelligente Cache-Abfrage mit Multi-Layer Fallback
     */
    public function get(string $key, string $type, ?callable $dataProvider = null, array $options = []): mixed
    {
        $startTime = microtime(true);
        
        // 1. Memory-Layer prüfen (schnellster)
        if ($this->hasMemoryCache($key)) {
            $data = $this->getFromMemory($key);
            $this->recordCacheHit($type, self::LAYER_MEMORY, $startTime);
            return $data;
        }
        
        // 2. Database-Layer prüfen
        $cached = $this->getFromDatabase($key, $type);
        if ($cached !== null) {
            // In Memory-Cache für zukünftige Requests
            $this->setToMemory($key, $cached['data'], $cached['ttl_seconds']);
            $this->recordCacheHit($type, self::LAYER_DATABASE, $startTime);
            $this->updateCacheAccess($cached['id']);
            return $cached['data'];
        }
        
        // 3. Shared-Cache prüfen (ähnliche Requests)
        $sharedData = $this->findSimilarCachedData($key, $type, $options);
        if ($sharedData !== null) {
            $this->recordCacheHit($type, self::LAYER_SHARED, $startTime);
            return $sharedData;
        }
        
        // 4. Cache-Miss - Daten laden wenn Provider vorhanden
        if ($dataProvider !== null) {
            $data = $dataProvider();
            if ($data !== null) {
                $this->set($key, $data, $type, $options);
                $this->recordCacheMiss($type, $startTime);
                return $data;
            }
        }
        
        $this->recordCacheMiss($type, $startTime);
        return null;
    }

    /**
     * Intelligente Cache-Speicherung mit optimaler TTL
     */
    public function set(string $key, mixed $data, string $type, array $options = []): void
    {
        $ttl = $this->calculateOptimalTTL($type, $data, $options);
        $metadata = $this->buildMetadata($data, $options);
        $predictionScore = $this->calculatePredictionScore($key, $type, $options);
        
        // Database-Layer (persistent)
        $this->saveToDatabase($key, $data, $type, $ttl, $metadata, $predictionScore, $options);
        
        // Memory-Layer (session-cache)
        $this->setToMemory($key, $data, min($ttl, 3600)); // Max 1h in Memory
        
        // Cache-Warming für verwandte Daten einplanen
        $this->scheduleRelatedWarming($key, $type, $data, $options);
    }

    /**
     * Predictive Caching - lädt wahrscheinlich benötigte Daten vor
     */
    public function warmPredictiveCache(): int
    {
        $warmedCount = 0;
        
        // High-Priority Warming Queue abarbeiten
        $warmingJobs = $this->getWarmingQueue(10);
        
        foreach ($warmingJobs as $job) {
            try {
                $this->markJobAsProcessing($job['id']);
                
                // Daten basierend auf Job-Type laden
                $data = $this->executeWarmingJob($job);
                
                if ($data !== null) {
                    $this->set($job['cache_key'], $data, $job['cache_type'], 
                              json_decode($job['request_data'], true));
                    $this->markJobAsCompleted($job['id']);
                    $warmedCount++;
                } else {
                    $this->markJobAsFailed($job['id'], 'No data returned');
                }
                
            } catch (Exception $e) {
                $this->markJobAsFailed($job['id'], $e->getMessage());
                error_log("Cache warming failed for job {$job['id']}: " . $e->getMessage());
            }
            
            // Rate-Limiting zwischen Warming-Jobs
            usleep(500000); // 0.5 Sekunden
        }
        
        return $warmedCount;
    }

    /**
     * Intelligente Cache-Invalidierung basierend auf Tags
     */
    public function invalidateByTags(array $tags): int
    {
        $placeholders = str_repeat('?,', count($tags) - 1) . '?';
        
        $invalidated = $this->db->select(
            "SELECT id, cache_key FROM enhanced_cache 
             WHERE JSON_OVERLAPS(invalidation_tags, JSON_ARRAY({$placeholders}))",
            $tags
        );
        
        foreach ($invalidated as $item) {
            $this->delete($item['cache_key']);
        }
        
        return count($invalidated);
    }

    /**
     * Fuzzy-Matching für ähnliche Cache-Einträge (besonders für Geocoding)
     */
    private function findSimilarCachedData(string $key, string $type, array $options): mixed
    {
        if ($type !== self::TYPE_GEOCODING) {
            return null;
        }
        
        // Adresse aus Key extrahieren und normalisieren
        $address = $this->extractAddressFromKey($key);
        if (empty($address)) {
            return null;
        }
        
        $normalizedAddress = $this->normalizeAddressForFuzzyMatch($address);
        
        // Ähnliche Adressen suchen (Levenshtein-Distanz)
        $similar = $this->db->select(
            "SELECT cache_key, data, 
                    CASE 
                        WHEN JSON_EXTRACT(metadata, '$.original_address') IS NOT NULL
                        THEN JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.original_address'))
                        ELSE cache_key
                    END as stored_address
             FROM enhanced_cache 
             WHERE cache_type = ? 
               AND expires_at > NOW() 
               AND hit_count > 0
             ORDER BY hit_count DESC 
             LIMIT 50",
            [$type]
        );
        
        foreach ($similar as $item) {
            $storedNormalized = $this->normalizeAddressForFuzzyMatch($item['stored_address']);
            $similarity = $this->calculateStringSimilarity($normalizedAddress, $storedNormalized);
            
            // Bei > 85% Ähnlichkeit verwenden
            if ($similarity >= 0.85) {
                $this->recordFuzzyMatch($key, $item['cache_key'], $similarity);
                return json_decode($item['data'], true);
            }
        }
        
        return null;
    }

    /**
     * Optimale TTL basierend auf Datenvolatilität berechnen
     */
    private function calculateOptimalTTL(string $type, mixed $data, array $options): int
    {
        $strategy = self::TTL_STRATEGIES[$type] ?? self::TTL_STRATEGIES[self::TYPE_ROUTE];
        $baseTTL = $strategy['base'];
        
        // Typ-spezifische Anpassungen
        switch ($type) {
            case self::TYPE_GEOCODING:
                // Höhere Confidence = längere TTL
                if (isset($strategy['confidence_factor']) && isset($data['confidence'])) {
                    $confidenceFactor = max(0.5, min(2.0, $data['confidence']));
                    $baseTTL = (int)($baseTTL * $confidenceFactor);
                }
                break;
                
            case self::TYPE_ROUTE:
                // Mit Traffic-Daten = kürzere TTL
                if (isset($strategy['traffic_factor']) && isset($options['with_traffic'])) {
                    $baseTTL = $options['with_traffic'] ? 300 : $baseTTL;
                }
                break;
                
            case self::TYPE_TRAFFIC:
                // Tageszeit berücksichtigen (Rush-Hour = kürzere TTL)
                if (isset($strategy['time_factor'])) {
                    $hour = (int)date('H');
                    if (($hour >= 7 && $hour <= 9) || ($hour >= 17 && $hour <= 19)) {
                        $baseTTL = max(60, $baseTTL / 2); // Rush-Hour: Halbierte TTL
                    }
                }
                break;
        }
        
        // Min/Max-Grenzen einhalten
        return max($strategy['min'], min($strategy['max'], $baseTTL));
    }

    /**
     * Prediction Score für zukünftige Nutzung berechnen
     */
    private function calculatePredictionScore(string $key, string $type, array $options): float
    {
        $score = 0.0;
        
        // Basis-Score nach Cache-Type
        $baseScores = [
            self::TYPE_GEOCODING => 0.8,      // Sehr wahrscheinlich wiederverwendet
            self::TYPE_ROUTE => 0.6,          // Oft wiederholt
            self::TYPE_TRAFFIC => 0.3,        // Zeitabhängig
            self::TYPE_MATRIX => 0.7,         // Matrix-Berechnungen werden oft wiederholt
            self::TYPE_AUTOCOMPLETE => 0.4    // Session-abhängig
        ];
        
        $score = $baseScores[$type] ?? 0.5;
        
        // Tageszeit-Faktor
        $hour = (int)date('H');
        if ($hour >= 6 && $hour <= 18) {
            $score += 0.2; // Arbeitszeit = höhere Wahrscheinlichkeit
        }
        
        // Wochentag-Faktor
        $weekday = (int)date('N');
        if ($weekday >= 1 && $weekday <= 5) {
            $score += 0.1; // Werktage = höhere Wahrscheinlichkeit
        }
        
        // Historische Nutzung prüfen
        $historicalUsage = $this->getHistoricalUsage($key, $type);
        if ($historicalUsage > 5) {
            $score += min(0.3, $historicalUsage * 0.02);
        }
        
        return min(1.0, $score);
    }

    /**
     * Related Cache-Warming einplanen
     */
    private function scheduleRelatedWarming(string $key, string $type, mixed $data, array $options): void
    {
        if ($type === self::TYPE_ROUTE && isset($data['segments'])) {
            // Für jedes Segment Traffic-Daten vorwärmen
            foreach ($data['segments'] as $segment) {
                if (isset($segment['start_location'], $segment['end_location'])) {
                    $this->scheduleWarmingJob(
                        self::TYPE_TRAFFIC,
                        ['origin' => $segment['start_location'], 'destination' => $segment['end_location']],
                        7 // Mittlere Priorität
                    );
                }
            }
        }
        
        if ($type === self::TYPE_GEOCODING && isset($data['latitude'], $data['longitude'])) {
            // Reverse-Geocoding für dieselbe Position vorwärmen
            $this->scheduleWarmingJob(
                self::TYPE_GEOCODING,
                ['lat' => $data['latitude'], 'lng' => $data['longitude'], 'reverse' => true],
                8 // Niedrige Priorität
            );
        }
    }

    /**
     * Database-Layer Methoden
     */
    private function getFromDatabase(string $key, string $type): ?array
    {
        $result = $this->db->selectOne(
            'SELECT id, data, ttl_seconds, metadata, created_at 
             FROM enhanced_cache 
             WHERE cache_key = ? AND cache_type = ? AND expires_at > NOW()
             ORDER BY hit_count DESC LIMIT 1',
            [$key, $type]
        );
        
        if ($result === null) {
            return null;
        }
        
        return [
            'id' => $result['id'],
            'data' => json_decode($result['data'], true),
            'ttl_seconds' => (int)$result['ttl_seconds'],
            'metadata' => json_decode($result['metadata'] ?? '{}', true),
            'age_seconds' => time() - strtotime($result['created_at'])
        ];
    }

    private function saveToDatabase(string $key, mixed $data, string $type, int $ttl, array $metadata, float $predictionScore, array $options): void
    {
        $invalidationTags = $this->buildInvalidationTags($type, $data, $options);
        $apiCost = $this->estimateApiCost($type, $options);
        
        $this->db->query(
            'INSERT INTO enhanced_cache (
                cache_key, cache_type, data, metadata, ttl_seconds, expires_at, 
                prediction_score, invalidation_tags, api_cost
             ) VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                data = VALUES(data),
                metadata = VALUES(metadata),
                ttl_seconds = VALUES(ttl_seconds),
                expires_at = VALUES(expires_at),
                prediction_score = VALUES(prediction_score),
                invalidation_tags = VALUES(invalidation_tags)',
            [
                $key, $type, json_encode($data), json_encode($metadata),
                $ttl, $ttl, $predictionScore, json_encode($invalidationTags), $apiCost
            ]
        );
    }

    /**
     * Memory-Layer Methoden
     */
    private function hasMemoryCache(string $key): bool
    {
        return isset($this->memoryCache[$key]) && 
               $this->memoryCache[$key]['expires'] > time();
    }

    private function getFromMemory(string $key): mixed
    {
        return $this->memoryCache[$key]['data'] ?? null;
    }

    private function setToMemory(string $key, mixed $data, int $ttl): void
    {
        // Memory-Cache begrenzen (max 100 Einträge)
        if (count($this->memoryCache) >= 100) {
            // LRU: Ältesten Eintrag entfernen
            $oldestKey = array_key_first($this->memoryCache);
            unset($this->memoryCache[$oldestKey]);
        }
        
        $this->memoryCache[$key] = [
            'data' => $data,
            'expires' => time() + $ttl,
            'accessed' => time()
        ];
    }

    /**
     * Performance-Tracking
     */
    private function recordCacheHit(string $type, string $layer, float $startTime): void
    {
        $responseTime = (microtime(true) - $startTime) * 1000;
        
        $this->sessionStats['hits']++;
        $this->sessionStats['total_response_time'] += $responseTime;
        $this->sessionStats['api_calls_saved']++;
        $this->sessionStats['cost_saved'] += $this->estimateApiCost($type, []);
        
        // Database-Update für Hit
        $this->db->query(
            'UPDATE enhanced_cache 
             SET hit_count = hit_count + 1,
                 last_accessed_at = NOW()
             WHERE cache_key = ? AND cache_type = ?',
            [func_get_arg(3) ?? '', $type]
        );
    }

    private function recordCacheMiss(string $type, float $startTime): void
    {
        $responseTime = (microtime(true) - $startTime) * 1000;
        
        $this->sessionStats['misses']++;
        $this->sessionStats['total_response_time'] += $responseTime;
    }

    /**
     * Cache-Warming Queue Management
     */
    private function getWarmingQueue(int $limit = 10): array
    {
        return $this->db->select(
            'SELECT * FROM cache_warming_queue 
             WHERE status = "pending" 
               AND execute_after <= NOW() 
               AND attempts < max_attempts
             ORDER BY priority ASC, scheduled_at ASC 
             LIMIT ?',
            [$limit]
        );
    }

    private function scheduleWarmingJob(string $cacheType, array $requestData, int $priority = 5): void
    {
        $this->db->insert('cache_warming_queue', [
            'cache_key' => $this->buildCacheKey($cacheType, $requestData),
            'cache_type' => $cacheType,
            'priority' => $priority,
            'request_data' => json_encode($requestData),
            'estimated_cost' => $this->estimateApiCost($cacheType, $requestData),
            'expected_usage_count' => $this->estimateUsageCount($cacheType, $requestData)
        ]);
    }

    /**
     * Hilfsmethoden
     */
    private function buildCacheKey(string $type, array $data): string
    {
        return $type . '_' . md5(json_encode($data));
    }

    private function buildMetadata(mixed $data, array $options): array
    {
        return [
            'created_by' => 'CacheAgent',
            'options' => array_filter($options),
            'data_size' => strlen(json_encode($data)),
            'confidence' => $data['confidence'] ?? null,
            'provider' => $data['provider'] ?? null,
            'original_address' => $options['original_address'] ?? null
        ];
    }

    private function buildInvalidationTags(string $type, mixed $data, array $options): array
    {
        $tags = [$type];
        
        if ($type === self::TYPE_ROUTE && isset($options['playlist_id'])) {
            $tags[] = 'playlist_' . $options['playlist_id'];
        }
        
        if (isset($data['provider'])) {
            $tags[] = 'provider_' . $data['provider'];
        }
        
        return $tags;
    }

    private function estimateApiCost(string $type, array $options): float
    {
        $costs = [
            self::TYPE_ROUTE => 0.005,
            self::TYPE_GEOCODING => 0.005,
            self::TYPE_TRAFFIC => 0.005,
            self::TYPE_MATRIX => 0.01,
            self::TYPE_AUTOCOMPLETE => 0.00283
        ];
        
        return $costs[$type] ?? 0.005;
    }

    /**
     * Cache-Statistiken abrufen
     */
    public function getPerformanceStats(): array
    {
        $total = $this->sessionStats['hits'] + $this->sessionStats['misses'];
        $hitRate = $total > 0 ? ($this->sessionStats['hits'] / $total) * 100 : 0;
        $avgResponseTime = $total > 0 ? $this->sessionStats['total_response_time'] / $total : 0;
        
        return [
            'session_stats' => $this->sessionStats,
            'hit_rate_percent' => round($hitRate, 2),
            'avg_response_time_ms' => round($avgResponseTime, 2),
            'api_calls_saved' => $this->sessionStats['api_calls_saved'],
            'estimated_cost_saved' => round($this->sessionStats['cost_saved'], 4),
            'memory_cache_entries' => count($this->memoryCache)
        ];
    }

    public function getDatabaseStats(): array
    {
        $stats = $this->db->selectOne(
            'SELECT 
                COUNT(*) as total_entries,
                AVG(hit_rate) as avg_hit_rate,
                SUM(hit_count) as total_hits,
                SUM(miss_count) as total_misses,
                SUM(data_size_bytes) / 1024 / 1024 as total_size_mb,
                SUM(api_cost) as total_api_cost_saved
             FROM enhanced_cache'
        );
        
        $typeStats = $this->db->select(
            'SELECT 
                cache_type,
                COUNT(*) as entries,
                AVG(hit_rate) as hit_rate,
                SUM(api_cost) as cost_saved
             FROM enhanced_cache
             GROUP BY cache_type'
        );
        
        return [
            'total_entries' => (int)$stats['total_entries'],
            'avg_hit_rate' => round((float)$stats['avg_hit_rate'], 2),
            'total_hits' => (int)$stats['total_hits'],
            'total_misses' => (int)$stats['total_misses'],
            'total_size_mb' => round((float)$stats['total_size_mb'], 2),
            'total_cost_saved' => round((float)$stats['total_api_cost_saved'], 4),
            'by_type' => $typeStats
        ];
    }

    // Weitere Hilfsmethoden für String-Ähnlichkeit, Fuzzy-Matching, etc.
    private function calculateStringSimilarity(string $str1, string $str2): float
    {
        $maxLen = max(strlen($str1), strlen($str2));
        if ($maxLen === 0) return 1.0;
        
        $levenshtein = levenshtein($str1, $str2);
        return 1.0 - ($levenshtein / $maxLen);
    }

    private function normalizeAddressForFuzzyMatch(string $address): string
    {
        // Erweiterte Normalisierung für bessere Fuzzy-Matches
        $normalized = mb_strtolower($address, 'UTF-8');
        $normalized = preg_replace('/[^\w\s]/', '', $normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        return trim($normalized);
    }

    private function extractAddressFromKey(string $key): string
    {
        // Adresse aus Cache-Key extrahieren (format: type_hash)
        $parts = explode('_', $key, 2);
        return $parts[1] ?? '';
    }

    private function delete(string $key): void
    {
        unset($this->memoryCache[$key]);
        $this->db->delete('enhanced_cache', 'cache_key = ?', [$key]);
    }
}