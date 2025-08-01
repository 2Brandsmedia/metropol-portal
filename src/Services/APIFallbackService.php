<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Config;
use App\Services\APIUsageTracker;
use Exception;

/**
 * APIFallbackService - Implementiert Fallback-Strategien für API-Ausfälle
 * 
 * Bietet verschiedene Fallback-Modi:
 * - Cache-Only: Nur aus gespeicherten Daten
 * - Alternative APIs: Wechsel zu anderen Anbietern
 * - Degraded Service: Reduzierte Funktionalität
 * - Graceful Degradation: Benutzerfreundliche Fehlermeldungen
 * 
 * @author 2Brands Media GmbH
 */
class APIFallbackService
{
    private Database $db;
    private Config $config;
    
    public function __construct(Database $db, Config $config)
    {
        $this->db = $db;
        $this->config = $config;
    }

    /**
     * Cache-Only Fallback für Geocoding
     */
    public function geocodingCacheOnly(string $address): ?array
    {
        // Normalisierte Adresse für Cache-Lookup
        $normalizedAddress = $this->normalizeAddress($address);
        $addressHash = hash('sha256', $normalizedAddress);
        
        $cached = $this->db->selectOne(
            'SELECT * FROM geocache WHERE address_hash = ?',
            [$addressHash]
        );
        
        if ($cached) {
            return [
                'latitude' => (float) $cached['latitude'],
                'longitude' => (float) $cached['longitude'],
                'address' => $cached['address'],
                'provider' => $cached['provider'],
                'confidence' => (float) $cached['confidence'],
                'fallback_used' => true,
                'fallback_type' => 'cache_only'
            ];
        }
        
        // Fuzzy-Suche im Cache für ähnliche Adressen
        return $this->fuzzyGeocodeSearch($normalizedAddress);
    }

    /**
     * Alternative API für Google Maps Routing
     */
    public function routingAlternativeApi(array $waypoints, array $options = []): ?array
    {
        // Fallback zu OpenRouteService wenn verfügbar
        if ($this->config->has('api.ors_key')) {
            return $this->useOpenRouteService($waypoints, $options);
        }
        
        // Fallback zu einfacher Luftlinie-Berechnung
        return $this->calculateAirlineDistance($waypoints, $options);
    }

    /**
     * Degraded Service für Maps
     */
    public function mapsDegradedService(array $request): array
    {
        return [
            'success' => true,
            'data' => [
                'mode' => 'degraded',
                'available_features' => [
                    'basic_routing' => true,
                    'traffic_data' => false,
                    'alternative_routes' => false,
                    'real_time_updates' => false
                ],
                'message' => 'Kartenfunktionen sind eingeschränkt verfügbar',
                'fallback_used' => true
            ],
            'fallback_type' => 'degraded_service'
        ];
    }

    /**
     * Benutzerfreundliche Buffer-Messages
     */
    public function getBufferMessage(string $apiProvider, string $context, string $warningLevel): array
    {
        $messages = $this->getBufferMessages();
        
        $contextMessages = $messages[$context] ?? $messages['general'];
        $levelMessages = $contextMessages[$warningLevel] ?? $contextMessages['blocked'];
        
        return [
            'type' => $this->getMessageType($warningLevel),
            'title' => $levelMessages['title'],
            'message' => $levelMessages['message'],
            'action' => $levelMessages['action'],
            'icon' => $this->getMessageIcon($warningLevel),
            'show_progress' => $warningLevel !== 'blocked',
            'estimated_wait' => $this->getEstimatedWait($apiProvider, $warningLevel),
            'alternatives' => $this->getAlternatives($context, $apiProvider)
        ];
    }

    /**
     * Smart Caching mit priorisiertem Prefetch
     */
    public function smartCachePrefetch(string $context, array $userData = []): void
    {
        switch ($context) {
            case 'routing':
                $this->prefetchRoutingData($userData);
                break;
                
            case 'geocoding':
                $this->prefetchGeocodingData($userData);
                break;
                
            case 'maps':
                $this->prefetchMapTiles($userData);
                break;
        }
    }

    /**
     * Fallback-Qualitätsbewertung
     */
    public function evaluateFallbackQuality(string $apiProvider, string $fallbackType, array $result): float
    {
        $baseScore = 0.5; // Mindestqualität für Fallbacks
        
        switch ($fallbackType) {
            case 'cache_only':
                // Cache-Alter und Confidence berücksichtigen
                $cacheAge = $result['cache_age_hours'] ?? 24;
                $confidence = $result['confidence'] ?? 0.5;
                return min(1.0, $baseScore + ($confidence * 0.3) - ($cacheAge * 0.01));
                
            case 'alternative_api':
                // Alternative API ist meist gleichwertig
                return 0.8;
                
            case 'degraded_service':
                // Reduzierte Funktionalität
                return 0.6;
                
            case 'airline_distance':
                // Luftlinie ist sehr ungenau
                return 0.3;
                
            default:
                return $baseScore;
        }
    }

    /**
     * Private Helper-Methoden
     */
    private function normalizeAddress(string $address): string
    {
        $normalized = mb_strtolower(trim($address), 'UTF-8');
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        
        $replacements = [
            'str.' => 'straße',
            'str ' => 'straße ',
            'platz.' => 'platz',
            'plz ' => 'postleitzahl ',
        ];
        
        return str_replace(array_keys($replacements), array_values($replacements), $normalized);
    }

    private function fuzzyGeocodeSearch(string $address): ?array
    {
        // Suche nach ähnlichen Adressen im Cache
        $words = explode(' ', $address);
        $searchTerms = [];
        
        foreach ($words as $word) {
            if (strlen($word) > 3) {
                $searchTerms[] = "%{$word}%";
            }
        }
        
        if (empty($searchTerms)) {
            return null;
        }
        
        $whereClause = str_repeat('address LIKE ? OR ', count($searchTerms));
        $whereClause = rtrim($whereClause, ' OR ');
        
        $similar = $this->db->select(
            "SELECT *, 
                    (LENGTH(address) - LENGTH(REPLACE(LOWER(address), LOWER(?), ''))) / LENGTH(?) as relevance
             FROM geocache 
             WHERE {$whereClause}
             ORDER BY relevance DESC, hits DESC
             LIMIT 3",
            array_merge([$address, $address], $searchTerms)
        );
        
        if (!empty($similar)) {
            $best = $similar[0];
            return [
                'latitude' => (float) $best['latitude'],
                'longitude' => (float) $best['longitude'],
                'address' => $best['address'],
                'provider' => $best['provider'],
                'confidence' => (float) $best['confidence'] * 0.7, // Reduziert wegen Fuzzy-Match
                'fallback_used' => true,
                'fallback_type' => 'fuzzy_cache',
                'original_query' => $address,
                'matched_address' => $best['address']
            ];
        }
        
        return null;
    }

    private function useOpenRouteService(array $waypoints, array $options): ?array
    {
        $apiKey = $this->config->get('api.ors_key');
        $baseUrl = $this->config->get('api.ors_url', 'https://api.openrouteservice.org/v2');
        
        // Waypoints für ORS formatieren
        $coordinates = [];
        foreach ($waypoints as $point) {
            $coordinates[] = [
                (float) ($point['longitude'] ?? $point['lng']),
                (float) ($point['latitude'] ?? $point['lat'])
            ];
        }
        
        $requestData = [
            'coordinates' => $coordinates,
            'format' => 'json',
            'instructions' => false,
            'geometry' => true
        ];
        
        try {
            $response = $this->makeOrsRequest($baseUrl . '/directions/driving-car', $requestData, $apiKey);
            
            if (!empty($response['routes'][0])) {
                $route = $response['routes'][0];
                
                return [
                    'total_distance' => $route['summary']['distance'],
                    'total_duration' => $route['summary']['duration'],
                    'total_distance_km' => round($route['summary']['distance'] / 1000, 2),
                    'total_duration_min' => round($route['summary']['duration'] / 60, 0),
                    'polyline' => $route['geometry'] ?? '',
                    'provider' => 'openrouteservice',
                    'fallback_used' => true,
                    'fallback_type' => 'alternative_api',
                    'warnings' => ['Traffic-Daten nicht verfügbar in Fallback-Modus']
                ];
            }
        } catch (Exception $e) {
            error_log("ORS fallback failed: " . $e->getMessage());
        }
        
        return null;
    }

    private function calculateAirlineDistance(array $waypoints, array $options): array
    {
        if (count($waypoints) < 2) {
            return [
                'error' => 'Mindestens 2 Waypoints erforderlich',
                'fallback_used' => true
            ];
        }
        
        $totalDistance = 0;
        $segments = [];
        
        for ($i = 0; $i < count($waypoints) - 1; $i++) {
            $from = $waypoints[$i];
            $to = $waypoints[$i + 1];
            
            $distance = $this->haversineDistance(
                $from['latitude'] ?? $from['lat'],
                $from['longitude'] ?? $from['lng'],
                $to['latitude'] ?? $to['lat'],
                $to['longitude'] ?? $to['lng']
            );
            
            $totalDistance += $distance;
            $segments[] = [
                'distance' => $distance,
                'duration' => $distance / 1000 * 60, // Geschätzt: 1km/min
                'start_address' => $from['address'] ?? 'Unbekannt',
                'end_address' => $to['address'] ?? 'Unbekannt'
            ];
        }
        
        return [
            'total_distance' => $totalDistance,
            'total_duration' => $totalDistance / 1000 * 60,
            'total_distance_km' => round($totalDistance / 1000, 2),
            'total_duration_min' => round($totalDistance / 1000 * 60, 0),
            'segments' => $segments,
            'provider' => 'airline_calculation',
            'fallback_used' => true,
            'fallback_type' => 'airline_distance',
            'warnings' => [
                'Dies ist eine Luftlinien-Schätzung',
                'Echte Fahrtzeit kann erheblich abweichen',
                'Straßenverlauf wird nicht berücksichtigt'
            ]
        ];
    }

    private function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000; // Erdradius in Metern
        
        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);
        
        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($lonDelta / 2) * sin($lonDelta / 2);
             
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        
        return $earthRadius * $c;
    }

    private function makeOrsRequest(string $url, array $data, string $apiKey): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Content-Type: application/json',
                    'Authorization: ' . $apiKey
                ],
                'content' => json_encode($data),
                'timeout' => 10
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            throw new Exception('ORS API request failed');
        }
        
        $decoded = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response from ORS');
        }
        
        return $decoded;
    }

    private function getBufferMessages(): array
    {
        return [
            'general' => [
                'yellow' => [
                    'title' => 'Hohe Systemlast',
                    'message' => 'Unser System verarbeitet aktuell viele Anfragen. Ihre Anfrage wird möglicherweise etwas langsamer bearbeitet.',
                    'action' => 'Bitte haben Sie etwas Geduld.'
                ],
                'red' => [
                    'title' => 'Kritische Systemlast',
                    'message' => 'Unser System ist stark ausgelastet. Wir priorisieren wichtige Anfragen.',
                    'action' => 'Nur dringende Aktionen werden derzeit schnell verarbeitet.'
                ],
                'blocked' => [
                    'title' => 'Service temporär nicht verfügbar',
                    'message' => 'Der gewünschte Service ist aufgrund von Wartungsarbeiten oder hoher Last temporär nicht verfügbar.',
                    'action' => 'Bitte versuchen Sie es in wenigen Minuten erneut.'
                ]
            ],
            'routing' => [
                'yellow' => [
                    'title' => 'Routenberechnung verlangsamt',
                    'message' => 'Die Berechnung Ihrer Route kann etwas länger dauern als gewöhnlich.',
                    'action' => 'Wir berechnen die beste Route für Sie...'
                ],
                'red' => [
                    'title' => 'Eingeschränkte Routenberechnung',
                    'message' => 'Live-Verkehrsdaten sind momentan nicht verfügbar. Wir zeigen Ihnen die beste geschätzte Route.',
                    'action' => 'Alternative Routen werden priorisiert berechnet.'
                ],
                'blocked' => [
                    'title' => 'Routenberechnung nicht verfügbar',
                    'message' => 'Die Routenberechnung ist temporär nicht verfügbar. Wir zeigen Ihnen eine geschätzte Luftlinien-Route.',
                    'action' => 'Nutzen Sie alternative Navigationshilfen für die genaue Route.'
                ]
            ],
            'geocoding' => [
                'yellow' => [
                    'title' => 'Adresssuche verlangsamt',
                    'message' => 'Die Adresssuche kann momentan etwas länger dauern.',
                    'action' => 'Bereits gesuchte Adressen werden schneller gefunden.'
                ],
                'red' => [
                    'title' => 'Eingeschränkte Adresssuche',
                    'message' => 'Neue Adressen können derzeit nur eingeschränkt gesucht werden.',
                    'action' => 'Verwenden Sie wenn möglich bereits bekannte Adressen.'
                ],
                'blocked' => [
                    'title' => 'Adresssuche nicht verfügbar',
                    'message' => 'Die Adresssuche ist temporär nicht verfügbar.',
                    'action' => 'Nur bereits gespeicherte Adressen sind verfügbar.'
                ]
            ]
        ];
    }

    private function getMessageType(string $warningLevel): string
    {
        switch ($warningLevel) {
            case 'yellow': return 'warning';
            case 'red': return 'error';
            case 'blocked': return 'blocked';
            default: return 'info';
        }
    }

    private function getMessageIcon(string $warningLevel): string
    {
        switch ($warningLevel) {
            case 'yellow': return '⚠️';
            case 'red': return '🚨';
            case 'blocked': return '🚫';
            default: return 'ℹ️';
        }
    }

    private function getEstimatedWait(string $apiProvider, string $warningLevel): ?string
    {
        if ($warningLevel === 'blocked') {
            return $this->getRetryEstimate($apiProvider);
        }
        
        switch ($warningLevel) {
            case 'yellow': return 'ca. 30 Sekunden';
            case 'red': return 'ca. 1-2 Minuten';
            default: return null;
        }
    }

    private function getRetryEstimate(string $apiProvider): string
    {
        // Vereinfachte Schätzung basierend auf API-Typ
        switch ($apiProvider) {
            case APIUsageTracker::API_GOOGLE_MAPS:
                return 'bis morgen früh';
            case APIUsageTracker::API_NOMINATIM:
                return 'in wenigen Minuten';
            default:
                return 'in 1-2 Stunden';
        }
    }

    private function getAlternatives(string $context, string $apiProvider): array
    {
        switch ($context) {
            case 'routing':
                return [
                    'Luftlinien-Route anzeigen',
                    'Externe Navigation verwenden',
                    'Route später planen'
                ];
                
            case 'geocoding':
                return [
                    'Bereits bekannte Adresse wählen',
                    'Koordinaten manuell eingeben',
                    'Später erneut versuchen'
                ];
                
            case 'maps':
                return [
                    'Einfache Kartenansicht',
                    'Liste der Stopps anzeigen',
                    'Externe Karten-App verwenden'
                ];
                
            default:
                return ['Später erneut versuchen'];
        }
    }

    private function prefetchRoutingData(array $userData): void
    {
        // Häufig verwendete Routen des Users vorladen
        if (!empty($userData['user_id'])) {
            $frequentRoutes = $this->db->select(
                'SELECT DISTINCT origin_address, destination_address 
                 FROM route_history 
                 WHERE user_id = ? 
                 ORDER BY usage_count DESC 
                 LIMIT 5',
                [$userData['user_id']]
            );
            
            // Diese Routen im Cache warm halten
            foreach ($frequentRoutes as $route) {
                $this->warmupRouteCache($route['origin_address'], $route['destination_address']);
            }
        }
    }

    private function prefetchGeocodingData(array $userData): void
    {
        // Häufig verwendete Adressen prefetchen
        if (!empty($userData['user_id'])) {
            $frequentAddresses = $this->db->select(
                'SELECT address FROM address_history 
                 WHERE user_id = ? 
                 ORDER BY usage_count DESC 
                 LIMIT 10',
                [$userData['user_id']]
            );
            
            foreach ($frequentAddresses as $addr) {
                $this->warmupGeocodeCache($addr['address']);
            }
        }
    }

    private function prefetchMapTiles(array $userData): void
    {
        // Map Tiles für häufig besuchte Gebiete vorhalten
        // Implementierung je nach Karten-Provider
    }

    private function warmupRouteCache(string $origin, string $destination): void
    {
        // Cache-Warmup für Route (falls nicht bereits vorhanden)
        $cacheKey = 'route_' . md5($origin . '_' . $destination);
        
        if ($this->db->selectOne('SELECT 1 FROM cache WHERE cache_key = ?', [$cacheKey]) === null) {
            // Route im Hintergrund berechnen und cachen
            // Implementierung je nach verfügbaren Ressourcen
        }
    }

    private function warmupGeocodeCache(string $address): void
    {
        $addressHash = hash('sha256', $this->normalizeAddress($address));
        
        if ($this->db->selectOne('SELECT 1 FROM geocache WHERE address_hash = ?', [$addressHash]) === null) {
            // Adresse im Hintergrund geocodieren
            // Implementierung je nach verfügbaren Ressourcen
        }
    }
}