<?php

declare(strict_types=1);

namespace App\Agents;

use App\Core\Database;
use App\Core\Config;
use App\Services\APIUsageTracker;
use App\Agents\APILimitAgent;
use App\Agents\CacheAgent;
use Exception;
use PDO;

/**
 * GeoAgent - Adress-Geokodierung und Caching
 * 
 * Erfolgskriterium: Trefferquote > 98%
 * 
 * @author 2Brands Media GmbH
 */
class GeoAgent
{
    private Database $db;
    private Config $config;
    private APILimitAgent $limitAgent;
    private CacheAgent $cacheAgent;
    private array $headers = [];
    private int $requestDelay = 1000; // Millisekunden zwischen Requests
    private int $timeout = 5000; // Timeout in Millisekunden
    
    // Provider-Konstanten
    private const PROVIDER_NOMINATIM = 'nominatim';
    private const PROVIDER_ORS = 'openrouteservice';
    
    // Confidence Scores
    private const CONFIDENCE_HIGH = 1.0;
    private const CONFIDENCE_MEDIUM = 0.8;
    private const CONFIDENCE_LOW = 0.5;

    public function __construct(Database $db, Config $config, ?CacheAgent $cacheAgent = null)
    {
        $this->db = $db;
        $this->config = $config;
        $this->limitAgent = new APILimitAgent($db, $config);
        $this->cacheAgent = $cacheAgent ?? new CacheAgent($db, $config);
        
        // Standard-Headers setzen
        $this->headers = [
            'User-Agent' => $this->config->get('geocoding.user_agent', 'MetropolPortal/1.0'),
            'Accept' => 'application/json',
            'Accept-Language' => 'de-DE,de;q=0.9,en;q=0.8'
        ];
        
        // Konfiguration laden
        $this->requestDelay = (int) $this->config->get('geocoding.delay', 1000);
        $this->timeout = (int) $this->config->get('geocoding.timeout', 5000);
    }

    /**
     * Geokodiert eine Adresse zu Koordinaten - Optimiert mit intelligentem Caching
     */
    public function geocode(string $address): ?array
    {
        // Adresse normalisieren
        $normalizedAddress = $this->normalizeAddress($address);
        $cacheKey = 'geocoding_' . md5($normalizedAddress);
        
        // Intelligenter Cache-Lookup mit Fuzzy-Matching
        return $this->cacheAgent->get($cacheKey, 'geocoding', function() use ($normalizedAddress, $address) {
            // Geokodierung über Provider
            $result = $this->geocodeWithNominatim($normalizedAddress);
            
            // Fallback zu ORS wenn Nominatim fehlschlägt
            if ($result === null && $this->config->has('api.ors_key')) {
                $result = $this->geocodeWithORS($normalizedAddress);
            }
            
            return $result;
        }, ['original_address' => $address, 'normalized_address' => $normalizedAddress]);
    }

    /**
     * Geokodiert mehrere Adressen gleichzeitig
     */
    public function geocodeBatch(array $addresses): array
    {
        $results = [];
        
        foreach ($addresses as $address) {
            // Zwischen Requests warten (Rate Limiting)
            if (!empty($results)) {
                usleep($this->requestDelay * 1000);
            }
            
            $results[$address] = $this->geocode($address);
        }
        
        return $results;
    }

    /**
     * Reverse Geocoding - Koordinaten zu Adresse
     */
    public function reverseGeocode(float $latitude, float $longitude): ?string
    {
        // Cache-Key für Reverse Geocoding
        $cacheKey = sprintf('reverse_%f_%f', $latitude, $longitude);
        $cached = $this->getFromCache(hash('sha256', $cacheKey));
        
        if ($cached !== null) {
            $this->incrementCacheHits($cached['id']);
            return $cached['address'];
        }
        
        // Reverse Geocoding mit Nominatim
        $address = $this->reverseGeocodeWithNominatim($latitude, $longitude);
        
        if ($address !== null) {
            // In Cache speichern
            $this->saveToCache(
                hash('sha256', $cacheKey),
                $address,
                [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'provider' => self::PROVIDER_NOMINATIM,
                    'confidence' => self::CONFIDENCE_HIGH
                ]
            );
        }
        
        return $address;
    }

    /**
     * Geokodierung mit OpenStreetMap Nominatim
     */
    private function geocodeWithNominatim(string $address): ?array
    {
        $startTime = microtime(true);
        
        // API-Limit prüfen
        $limitCheck = $this->limitAgent->checkApiRequest(APIUsageTracker::API_NOMINATIM, 'geocoding');
        
        if (!$limitCheck['allowed']) {
            // Fallback zu Cache-Only wenn blockiert
            if ($limitCheck['fallback_mode']) {
                $fallbackResult = $this->limitAgent->executeFallbackStrategy(
                    APIUsageTracker::API_NOMINATIM,
                    $limitCheck['fallback_mode'],
                    ['address' => $address]
                );
                
                if ($fallbackResult['success']) {
                    return $fallbackResult['data'];
                }
            }
            
            error_log("Nominatim API blocked: " . ($limitCheck['user_message']['message'] ?? 'Limit reached'));
            return null;
        }
        
        // Warning-Message für User speichern wenn vorhanden
        if ($limitCheck['user_message']) {
            $_SESSION['api_warning'] = $limitCheck['user_message'];
        }
        
        $baseUrl = $this->config->get('geocoding.nominatim_url', 'https://nominatim.openstreetmap.org');
        $params = [
            'q' => $address,
            'format' => 'json',
            'limit' => 1,
            'addressdetails' => 1,
            'countrycodes' => 'de', // Nur Deutschland
            'accept-language' => 'de'
        ];
        
        $url = $baseUrl . '/search?' . http_build_query($params);
        
        try {
            $response = $this->makeRequest($url);
            $responseTime = (microtime(true) - $startTime) * 1000;
            
            if (empty($response)) {
                $this->limitAgent->trackSuccessfulRequest(
                    APIUsageTracker::API_NOMINATIM,
                    'search',
                    $responseTime,
                    ['result_count' => 0, 'address' => $address]
                );
                return null;
            }
            
            $data = $response[0]; // Erstes Ergebnis
            
            $result = [
                'latitude' => (float) $data['lat'],
                'longitude' => (float) $data['lon'],
                'provider' => self::PROVIDER_NOMINATIM,
                'confidence' => $this->calculateConfidence($data),
                'raw_response' => json_encode($data)
            ];
            
            // Erfolgreiche Anfrage tracken
            $this->limitAgent->trackSuccessfulRequest(
                APIUsageTracker::API_NOMINATIM,
                'search',
                $responseTime,
                [
                    'result_count' => count($response),
                    'confidence' => $result['confidence'],
                    'address' => $address
                ]
            );
            
            return $result;
            
        } catch (Exception $e) {
            $this->limitAgent->trackFailedRequest(
                APIUsageTracker::API_NOMINATIM,
                'search',
                $e->getMessage(),
                ['address' => $address]
            );
            error_log("Nominatim geocoding failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Geokodierung mit OpenRouteService
     */
    private function geocodeWithORS(string $address): ?array
    {
        $apiKey = $this->config->get('api.ors_key');
        if (empty($apiKey)) {
            return null;
        }
        
        $baseUrl = $this->config->get('api.ors_url', 'https://api.openrouteservice.org/v2');
        $url = $baseUrl . '/geocode/search';
        
        $params = [
            'api_key' => $apiKey,
            'text' => $address,
            'boundary.country' => 'DE',
            'size' => 1
        ];
        
        try {
            $response = $this->makeRequest($url . '?' . http_build_query($params));
            
            if (empty($response['features'])) {
                return null;
            }
            
            $feature = $response['features'][0];
            $coords = $feature['geometry']['coordinates'];
            
            return [
                'latitude' => $coords[1],
                'longitude' => $coords[0],
                'provider' => self::PROVIDER_ORS,
                'confidence' => $feature['properties']['confidence'] ?? self::CONFIDENCE_MEDIUM,
                'raw_response' => json_encode($feature)
            ];
            
        } catch (Exception $e) {
            error_log("ORS geocoding failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Reverse Geocoding mit Nominatim
     */
    private function reverseGeocodeWithNominatim(float $latitude, float $longitude): ?string
    {
        $baseUrl = $this->config->get('geocoding.nominatim_url', 'https://nominatim.openstreetmap.org');
        $params = [
            'lat' => $latitude,
            'lon' => $longitude,
            'format' => 'json',
            'addressdetails' => 1,
            'accept-language' => 'de'
        ];
        
        $url = $baseUrl . '/reverse?' . http_build_query($params);
        
        try {
            $response = $this->makeRequest($url);
            
            if (empty($response['display_name'])) {
                return null;
            }
            
            return $response['display_name'];
            
        } catch (Exception $e) {
            error_log("Nominatim reverse geocoding failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * HTTP-Request durchführen
     */
    private function makeRequest(string $url): ?array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => $this->buildHeaders(),
                'timeout' => $this->timeout / 1000,
                'ignore_errors' => true
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            throw new Exception('Request failed');
        }
        
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response');
        }
        
        return $data;
    }

    /**
     * Headers für Request erstellen
     */
    private function buildHeaders(): string
    {
        $headers = [];
        foreach ($this->headers as $key => $value) {
            $headers[] = "$key: $value";
        }
        return implode("\r\n", $headers);
    }

    /**
     * Adresse normalisieren für bessere Cache-Trefferquote
     */
    private function normalizeAddress(string $address): string
    {
        // Kleinschreibung
        $normalized = mb_strtolower($address, 'UTF-8');
        
        // Mehrfache Leerzeichen entfernen
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        
        // Trimmen
        $normalized = trim($normalized);
        
        // Sonderzeichen normalisieren
        $replacements = [
            'str.' => 'straße',
            'str ' => 'straße ',
            'plz.' => 'platz',
            'plz ' => 'platz ',
            'nr.' => 'nummer',
            'nr ' => 'nummer '
        ];
        
        foreach ($replacements as $search => $replace) {
            $normalized = str_replace($search, $replace, $normalized);
        }
        
        return $normalized;
    }

    /**
     * Adress-Hash erstellen
     */
    private function hashAddress(string $address): string
    {
        return hash('sha256', $address);
    }

    /**
     * Aus Cache laden
     */
    private function getFromCache(string $addressHash): ?array
    {
        $result = $this->db->selectOne(
            'SELECT * FROM geocache WHERE address_hash = ?',
            [$addressHash]
        );
        
        if ($result === null) {
            return null;
        }
        
        return [
            'id' => $result['id'],
            'latitude' => (float) $result['latitude'],
            'longitude' => (float) $result['longitude'],
            'address' => $result['address'],
            'provider' => $result['provider'],
            'confidence' => (float) $result['confidence'],
            'raw_response' => $result['raw_response']
        ];
    }

    /**
     * In Cache speichern
     */
    private function saveToCache(string $addressHash, string $address, array $result): void
    {
        $this->db->insert('geocache', [
            'address_hash' => $addressHash,
            'address' => $address,
            'latitude' => $result['latitude'],
            'longitude' => $result['longitude'],
            'provider' => $result['provider'],
            'confidence' => $result['confidence'],
            'raw_response' => $result['raw_response'] ?? null
        ]);
    }

    /**
     * Cache-Hits erhöhen
     */
    private function incrementCacheHits(int $id): void
    {
        $this->db->query(
            'UPDATE geocache SET hits = hits + 1 WHERE id = ?',
            [$id]
        );
    }

    /**
     * Confidence Score berechnen
     */
    private function calculateConfidence(array $nominatimResult): float
    {
        $confidence = self::CONFIDENCE_HIGH;
        
        // Wichtigkeit des Ergebnisses
        $importance = (float) ($nominatimResult['importance'] ?? 0);
        if ($importance < 0.5) {
            $confidence *= 0.8;
        }
        
        // Typ des Ergebnisses
        $type = $nominatimResult['type'] ?? '';
        if (!in_array($type, ['house', 'building', 'residential'])) {
            $confidence *= 0.9;
        }
        
        // Klasse des Ergebnisses
        $class = $nominatimResult['class'] ?? '';
        if ($class !== 'place') {
            $confidence *= 0.95;
        }
        
        return min(self::CONFIDENCE_HIGH, max(self::CONFIDENCE_LOW, $confidence));
    }

    /**
     * Cache bereinigen (alte Einträge löschen)
     */
    public function cleanCache(int $daysOld = 90): int
    {
        $date = date('Y-m-d H:i:s', strtotime("-{$daysOld} days"));
        
        return $this->db->delete(
            'geocache',
            'created_at < ? AND hits < 5',
            [$date]
        );
    }

    /**
     * Cache-Statistiken abrufen
     */
    public function getCacheStats(): array
    {
        $stats = $this->db->selectOne(
            'SELECT 
                COUNT(*) as total_entries,
                SUM(hits) as total_hits,
                AVG(hits) as avg_hits,
                MIN(created_at) as oldest_entry,
                MAX(created_at) as newest_entry
            FROM geocache'
        );
        
        $byProvider = $this->db->select(
            'SELECT provider, COUNT(*) as count 
            FROM geocache 
            GROUP BY provider'
        );
        
        return [
            'total_entries' => (int) $stats['total_entries'],
            'total_hits' => (int) $stats['total_hits'],
            'avg_hits' => (float) $stats['avg_hits'],
            'oldest_entry' => $stats['oldest_entry'],
            'newest_entry' => $stats['newest_entry'],
            'by_provider' => $byProvider
        ];
    }
}