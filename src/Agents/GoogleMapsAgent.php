<?php

declare(strict_types=1);

namespace App\Agents;

use App\Core\Database;
use App\Core\Config;
use App\Services\APIUsageTracker;
use App\Agents\APILimitAgent;
use Exception;
use PDO;

/**
 * GoogleMapsAgent - Google Maps API Integration mit Live-Traffic
 * 
 * Nutzt Google Maps Directions API für Echtzeit-Verkehrsdaten
 * 
 * @author 2Brands Media GmbH
 */
class GoogleMapsAgent
{
    private Database $db;
    private Config $config;
    private APILimitAgent $limitAgent;
    private string $apiKey;
    private string $baseUrl = 'https://maps.googleapis.com/maps/api';
    private int $cacheLifetime = 900; // 15 Minuten für Traffic-Daten
    private int $geocodeCacheLifetime = 2592000; // 30 Tage für Geocoding
    private int $routeCacheLifetime = 3600; // 1 Stunde für Routen ohne Traffic
    
    // Konstanten
    private const MODE_DRIVING = 'driving';
    private const MODE_WALKING = 'walking';
    private const MODE_BICYCLING = 'bicycling';
    private const MODE_TRANSIT = 'transit';
    
    private const TRAFFIC_MODEL_BEST_GUESS = 'best_guess';
    private const TRAFFIC_MODEL_PESSIMISTIC = 'pessimistic';
    private const TRAFFIC_MODEL_OPTIMISTIC = 'optimistic';
    
    private const AVOID_TOLLS = 'tolls';
    private const AVOID_HIGHWAYS = 'highways';
    private const AVOID_FERRIES = 'ferries';

    public function __construct(Database $db, Config $config)
    {
        $this->db = $db;
        $this->config = $config;
        $this->limitAgent = new APILimitAgent($db, $config);
        
        $this->apiKey = $this->config->get('google.maps_api_key', '');
        
        if (empty($this->apiKey)) {
            throw new Exception('Google Maps API Key nicht konfiguriert');
        }
    }

    /**
     * Berechnet Route mit Live-Traffic
     */
    public function calculateRoute(array $waypoints, array $options = []): array
    {
        if (count($waypoints) < 2) {
            throw new Exception('Mindestens 2 Waypoints erforderlich');
        }
        
        // Cache-Key erstellen
        $cacheKey = $this->createCacheKey('directions', $waypoints, $options);
        
        // Cache prüfen
        $cached = $this->getCachedRoute($cacheKey, $options);
        if ($cached !== null) {
            return $cached;
        }
        
        // API-Request vorbereiten
        $params = $this->buildDirectionsParams($waypoints, $options);
        
        // API aufrufen
        $response = $this->makeApiRequest('directions/json', $params);
        
        // Response verarbeiten
        $result = $this->processDirectionsResponse($response, $waypoints);
        
        // In Cache speichern mit angepasster Lifetime
        if (!empty($result)) {
            $lifetime = $this->getRouteCacheLifetime($options);
            $this->saveToCache($cacheKey, $result, $lifetime);
        }
        
        return $result;
    }

    /**
     * Distance Matrix für mehrere Ziele
     */
    public function calculateDistanceMatrix(array $origins, array $destinations, array $options = []): array
    {
        // Cache-Key
        $cacheKey = $this->createCacheKey('matrix', 
            array_merge($origins, $destinations), 
            $options
        );
        
        // Cache prüfen
        $cached = $this->getFromCache($cacheKey);
        if ($cached !== null && empty($options['departure_time'])) {
            return $cached;
        }
        
        // Parameter vorbereiten
        $params = [
            'origins' => implode('|', array_map([$this, 'formatLocation'], $origins)),
            'destinations' => implode('|', array_map([$this, 'formatLocation'], $destinations)),
            'mode' => $options['mode'] ?? self::MODE_DRIVING,
            'units' => 'metric',
            'language' => 'de',
            'key' => $this->apiKey
        ];
        
        // Traffic-Optionen
        if (!empty($options['departure_time'])) {
            $params['departure_time'] = $this->formatDepartureTime($options['departure_time']);
            $params['traffic_model'] = $options['traffic_model'] ?? self::TRAFFIC_MODEL_BEST_GUESS;
        }
        
        // API aufrufen
        $response = $this->makeApiRequest('distancematrix/json', $params);
        
        // Response verarbeiten
        $result = $this->processMatrixResponse($response);
        
        // Cachen
        if (!empty($result)) {
            $lifetime = !empty($options['departure_time']) ? $this->cacheLifetime : 86400;
            $this->saveToCache($cacheKey, $result, $lifetime);
        }
        
        return $result;
    }

    /**
     * Geocoding über Google Maps
     */
    public function geocode(string $address): ?array
    {
        $cacheKey = $this->createCacheKey('geocode', [$address], []);
        
        // Cache prüfen
        $cached = $this->getFromCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        
        // API-Parameter
        $params = [
            'address' => $address,
            'region' => 'de',
            'language' => 'de',
            'key' => $this->apiKey
        ];
        
        // API aufrufen
        $response = $this->makeApiRequest('geocode/json', $params);
        
        if (empty($response['results'][0])) {
            return null;
        }
        
        $location = $response['results'][0]['geometry']['location'];
        $result = [
            'latitude' => $location['lat'],
            'longitude' => $location['lng'],
            'formatted_address' => $response['results'][0]['formatted_address'],
            'place_id' => $response['results'][0]['place_id'],
            'types' => $response['results'][0]['types'] ?? []
        ];
        
        // Langzeit-Cache für Geocoding
        $this->saveToCache($cacheKey, $result, $this->geocodeCacheLifetime);
        
        return $result;
    }

    /**
     * Places API für Adress-Autocomplete
     */
    public function autocomplete(string $input, array $options = []): array
    {
        $params = [
            'input' => $input,
            'language' => 'de',
            'components' => 'country:de',
            'key' => $this->apiKey
        ];
        
        if (!empty($options['location'])) {
            $params['location'] = $options['location']['lat'] . ',' . $options['location']['lng'];
            $params['radius'] = $options['radius'] ?? 50000; // 50km
        }
        
        $response = $this->makeApiRequest('place/autocomplete/json', $params);
        
        return array_map(function($prediction) {
            return [
                'description' => $prediction['description'],
                'place_id' => $prediction['place_id'],
                'main_text' => $prediction['structured_formatting']['main_text'] ?? '',
                'secondary_text' => $prediction['structured_formatting']['secondary_text'] ?? ''
            ];
        }, $response['predictions'] ?? []);
    }

    /**
     * Traffic-Layer-Daten für Karte
     */
    public function getTrafficTileUrl(): string
    {
        // Google Maps Traffic Tile URL
        return "https://mt0.google.com/vt?lyrs=m,traffic&x={x}&y={y}&z={z}&key=" . $this->apiKey;
    }

    /**
     * Baut Directions-Parameter
     */
    private function buildDirectionsParams(array $waypoints, array $options): array
    {
        $origin = array_shift($waypoints);
        $destination = array_pop($waypoints);
        
        $params = [
            'origin' => $this->formatLocation($origin),
            'destination' => $this->formatLocation($destination),
            'mode' => $options['mode'] ?? self::MODE_DRIVING,
            'units' => 'metric',
            'language' => 'de',
            'alternatives' => $options['alternatives'] ?? false,
            'key' => $this->apiKey
        ];
        
        // Waypoints
        if (!empty($waypoints)) {
            $waypointStrings = array_map(function($wp) use ($options) {
                $prefix = !empty($options['optimize']) ? 'optimize:true|' : '';
                return $prefix . $this->formatLocation($wp);
            }, $waypoints);
            $params['waypoints'] = implode('|', $waypointStrings);
        }
        
        // Traffic-Optionen
        if (!empty($options['departure_time'])) {
            $params['departure_time'] = $this->formatDepartureTime($options['departure_time']);
            $params['traffic_model'] = $options['traffic_model'] ?? self::TRAFFIC_MODEL_BEST_GUESS;
        }
        
        // Vermeidungen
        if (!empty($options['avoid'])) {
            $params['avoid'] = implode('|', (array)$options['avoid']);
        }
        
        return $params;
    }

    /**
     * Verarbeitet Directions-Response
     */
    private function processDirectionsResponse(array $response, array $originalWaypoints): array
    {
        if (empty($response['routes'][0])) {
            throw new Exception('Keine Route gefunden');
        }
        
        $route = $response['routes'][0];
        $legs = $route['legs'];
        
        // Basis-Informationen
        $result = [
            'total_distance' => 0,
            'total_duration' => 0,
            'total_duration_in_traffic' => 0,
            'polyline' => $route['overview_polyline']['points'],
            'bounds' => $route['bounds'],
            'warnings' => $route['warnings'] ?? [],
            'waypoint_order' => $route['waypoint_order'] ?? []
        ];
        
        // Legs verarbeiten
        $segments = [];
        foreach ($legs as $index => $leg) {
            $segment = [
                'distance' => $leg['distance']['value'], // Meter
                'duration' => $leg['duration']['value'], // Sekunden
                'duration_in_traffic' => $leg['duration_in_traffic']['value'] ?? null,
                'start_address' => $leg['start_address'],
                'end_address' => $leg['end_address'],
                'traffic_delay' => 0
            ];
            
            // Traffic-Verzögerung berechnen
            if ($segment['duration_in_traffic'] !== null) {
                $segment['traffic_delay'] = $segment['duration_in_traffic'] - $segment['duration'];
                $segment['traffic_severity'] = $this->calculateTrafficSeverity($segment);
            }
            
            $result['total_distance'] += $segment['distance'];
            $result['total_duration'] += $segment['duration'];
            $result['total_duration_in_traffic'] += $segment['duration_in_traffic'] ?? $segment['duration'];
            
            $segments[] = $segment;
        }
        
        $result['segments'] = $segments;
        $result['total_traffic_delay'] = $result['total_duration_in_traffic'] - $result['total_duration'];
        $result['traffic_severity'] = $this->calculateOverallTrafficSeverity($result);
        
        // Kilometern und Minuten
        $result['total_distance_km'] = round($result['total_distance'] / 1000, 2);
        $result['total_duration_min'] = round($result['total_duration'] / 60, 0);
        $result['total_duration_in_traffic_min'] = round($result['total_duration_in_traffic'] / 60, 0);
        
        return $result;
    }

    /**
     * Verarbeitet Distance Matrix Response
     */
    private function processMatrixResponse(array $response): array
    {
        if ($response['status'] !== 'OK') {
            throw new Exception('Distance Matrix Error: ' . $response['status']);
        }
        
        $results = [];
        
        foreach ($response['rows'] as $rowIndex => $row) {
            foreach ($row['elements'] as $colIndex => $element) {
                if ($element['status'] !== 'OK') {
                    continue;
                }
                
                $results[] = [
                    'origin_index' => $rowIndex,
                    'destination_index' => $colIndex,
                    'distance' => $element['distance']['value'],
                    'duration' => $element['duration']['value'],
                    'duration_in_traffic' => $element['duration_in_traffic']['value'] ?? null
                ];
            }
        }
        
        return $results;
    }

    /**
     * Berechnet Traffic-Schweregrad
     */
    private function calculateTrafficSeverity(array $segment): string
    {
        if (!isset($segment['duration_in_traffic'])) {
            return 'unknown';
        }
        
        $ratio = $segment['duration_in_traffic'] / $segment['duration'];
        
        if ($ratio < 1.1) return 'low';
        if ($ratio < 1.3) return 'medium';
        if ($ratio < 1.5) return 'high';
        return 'severe';
    }

    /**
     * Berechnet gesamten Traffic-Schweregrad
     */
    private function calculateOverallTrafficSeverity(array $route): string
    {
        if ($route['total_duration'] == 0) {
            return 'unknown';
        }
        
        $ratio = $route['total_duration_in_traffic'] / $route['total_duration'];
        
        if ($ratio < 1.1) return 'low';
        if ($ratio < 1.25) return 'medium';
        if ($ratio < 1.4) return 'high';
        return 'severe';
    }

    /**
     * Formatiert Location für API
     */
    private function formatLocation(array $location): string
    {
        if (isset($location['lat']) && isset($location['lng'])) {
            return $location['lat'] . ',' . $location['lng'];
        }
        
        if (isset($location['latitude']) && isset($location['longitude'])) {
            return $location['latitude'] . ',' . $location['longitude'];
        }
        
        if (isset($location['address'])) {
            return urlencode($location['address']);
        }
        
        if (isset($location['place_id'])) {
            return 'place_id:' . $location['place_id'];
        }
        
        throw new Exception('Ungültiges Location-Format');
    }

    /**
     * Formatiert Departure Time
     */
    private function formatDepartureTime($time): string
    {
        if ($time === 'now') {
            return 'now';
        }
        
        if (is_numeric($time)) {
            return (string)$time;
        }
        
        if (is_string($time)) {
            return (string)strtotime($time);
        }
        
        return 'now';
    }

    /**
     * API-Request durchführen mit Limit-Checking
     */
    private function makeApiRequest(string $endpoint, array $params): array
    {
        $startTime = microtime(true);
        
        // API-Limit prüfen
        $limitCheck = $this->limitAgent->checkApiRequest(APIUsageTracker::API_GOOGLE_MAPS, 'routing');
        
        if (!$limitCheck['allowed']) {
            // Fallback-Strategie verwenden wenn blockiert
            if ($limitCheck['fallback_mode']) {
                $fallbackResult = $this->limitAgent->executeFallbackStrategy(
                    APIUsageTracker::API_GOOGLE_MAPS,
                    $limitCheck['fallback_mode'],
                    ['endpoint' => $endpoint, 'params' => $params]
                );
                
                if ($fallbackResult['success']) {
                    return $fallbackResult['data'];
                }
            }
            
            throw new Exception($limitCheck['user_message']['message'] ?? 'API-Limit erreicht');
        }
        
        // Warning-Message für User speichern wenn vorhanden
        if ($limitCheck['user_message']) {
            $_SESSION['api_warning'] = $limitCheck['user_message'];
        }
        
        $url = $this->baseUrl . '/' . $endpoint . '?' . http_build_query($params);
        
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 10,
                'ignore_errors' => true
            ]
        ]);
        
        try {
            $response = @file_get_contents($url, false, $context);
            $responseTime = (microtime(true) - $startTime) * 1000; // milliseconds
            
            if ($response === false) {
                $this->limitAgent->trackFailedRequest(
                    APIUsageTracker::API_GOOGLE_MAPS,
                    $endpoint,
                    'HTTP request failed'
                );
                throw new Exception('Google Maps API Request fehlgeschlagen');
            }
            
            $data = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->limitAgent->trackFailedRequest(
                    APIUsageTracker::API_GOOGLE_MAPS,
                    $endpoint,
                    'Invalid JSON response: ' . json_last_error_msg()
                );
                throw new Exception('Ungültige JSON-Antwort von Google Maps API');
            }
            
            // Fehler prüfen
            if (isset($data['status']) && !in_array($data['status'], ['OK', 'ZERO_RESULTS'])) {
                $errorMsg = $data['error_message'] ?? $data['status'];
                $this->limitAgent->trackFailedRequest(
                    APIUsageTracker::API_GOOGLE_MAPS,
                    $endpoint,
                    'API Error: ' . $errorMsg
                );
                throw new Exception('Google Maps API Error: ' . $errorMsg);
            }
            
            // Erfolgreiche Anfrage tracken
            $this->limitAgent->trackSuccessfulRequest(
                APIUsageTracker::API_GOOGLE_MAPS,
                $endpoint,
                $responseTime,
                [
                    'response_size' => strlen($response),
                    'params_count' => count($params),
                    'status' => $data['status'] ?? 'unknown'
                ]
            );
            
            return $data;
            
        } catch (Exception $e) {
            // Fehler tracken falls noch nicht geschehen
            if (strpos($e->getMessage(), 'API Error:') === false) {
                $this->limitAgent->trackFailedRequest(
                    APIUsageTracker::API_GOOGLE_MAPS,
                    $endpoint,
                    $e->getMessage()
                );
            }
            throw $e;
        }
    }

    /**
     * Cache-Key erstellen
     */
    private function createCacheKey(string $type, array $data, array $options): string
    {
        $keyData = [
            'type' => $type,
            'data' => $data,
            'options' => array_filter($options, function($key) {
                // Diese Optionen beeinflussen das Ergebnis
                return in_array($key, ['mode', 'avoid', 'traffic_model', 'optimize']);
            }, ARRAY_FILTER_USE_KEY)
        ];
        
        return 'gmaps_' . md5(json_encode($keyData));
    }

    /**
     * Aus Cache laden
     */
    private function getFromCache(string $key): ?array
    {
        $result = $this->db->selectOne(
            'SELECT data, expires_at FROM cache WHERE cache_key = ? AND expires_at > NOW()',
            [$key]
        );
        
        if ($result === null) {
            return null;
        }
        
        return json_decode($result['data'], true);
    }

    /**
     * In Cache speichern
     */
    private function saveToCache(string $key, array $data, int $lifetime): void
    {
        $this->db->query(
            'INSERT INTO cache (cache_key, data, expires_at) 
             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND))
             ON DUPLICATE KEY UPDATE 
                data = VALUES(data),
                expires_at = VALUES(expires_at)',
            [$key, json_encode($data), $lifetime]
        );
    }

    /**
     * Cache bereinigen
     */
    public function cleanCache(): int
    {
        return $this->db->delete(
            'DELETE FROM cache WHERE expires_at < NOW()'
        );
    }
    
    /**
     * Spezielle Cache-Prüfung für Routen
     */
    private function getCachedRoute(string $key, array $options): ?array
    {
        // Bei Live-Traffic nur kurz cachen
        if (!empty($options['departure_time']) && $options['departure_time'] === 'now') {
            // Prüfen ob Cache noch frisch genug für Traffic
            $result = $this->db->selectOne(
                'SELECT data, expires_at, TIMESTAMPDIFF(MINUTE, created_at, NOW()) as age_minutes 
                 FROM cache 
                 WHERE cache_key = ? AND expires_at > NOW()',
                [$key]
            );
            
            if ($result && $result['age_minutes'] < 5) {
                // Nur verwenden wenn weniger als 5 Minuten alt
                return json_decode($result['data'], true);
            }
            return null;
        }
        
        // Normale Cache-Prüfung
        return $this->getFromCache($key);
    }
    
    /**
     * Bestimmt Cache-Lifetime basierend auf Optionen
     */
    private function getRouteCacheLifetime(array $options): int
    {
        // Live-Traffic: Kurze Cache-Zeit
        if (!empty($options['departure_time']) && $options['departure_time'] === 'now') {
            return 300; // 5 Minuten
        }
        
        // Zukünftige Abfahrtszeit: Mittlere Cache-Zeit
        if (!empty($options['departure_time'])) {
            return $this->cacheLifetime; // 15 Minuten
        }
        
        // Keine Traffic-Daten: Lange Cache-Zeit
        return $this->routeCacheLifetime; // 1 Stunde
    }
    
    /**
     * Cache-Statistiken abrufen
     */
    public function getCacheStats(): array
    {
        $stats = $this->db->selectOne(
            'SELECT 
                COUNT(*) as total_entries,
                COUNT(CASE WHEN cache_key LIKE "gmaps_directions%" THEN 1 END) as route_entries,
                COUNT(CASE WHEN cache_key LIKE "gmaps_geocode%" THEN 1 END) as geocode_entries,
                COUNT(CASE WHEN cache_key LIKE "gmaps_matrix%" THEN 1 END) as matrix_entries,
                SUM(LENGTH(data)) as total_size_bytes,
                MIN(created_at) as oldest_entry,
                MAX(created_at) as newest_entry
             FROM cache 
             WHERE cache_key LIKE "gmaps_%"'
        );
        
        return [
            'total_entries' => (int)$stats['total_entries'],
            'route_entries' => (int)$stats['route_entries'],
            'geocode_entries' => (int)$stats['geocode_entries'],
            'matrix_entries' => (int)$stats['matrix_entries'],
            'total_size_mb' => round(($stats['total_size_bytes'] ?? 0) / 1024 / 1024, 2),
            'oldest_entry' => $stats['oldest_entry'],
            'newest_entry' => $stats['newest_entry']
        ];
    }
    
    /**
     * Cache für bestimmte Playlist leeren
     */
    public function clearPlaylistCache(int $playlistId): int
    {
        // Alle Cache-Einträge finden die diese Playlist-ID enthalten könnten
        return $this->db->delete(
            'DELETE FROM cache 
             WHERE cache_key LIKE ? 
             AND (data LIKE ? OR data LIKE ?)',
            [
                'gmaps_%',
                '%"playlist_id":' . $playlistId . '%',
                '%"playlistId":' . $playlistId . '%'
            ]
        );
    }
}