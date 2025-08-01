<?php

declare(strict_types=1);

namespace App\Agents;

use App\Core\Database;
use App\Core\Config;
use Exception;
use PDO;

/**
 * RouteAgent - Routenberechnung mit Live-Traffic
 * 
 * Erfolgskriterium: Response < 300ms
 * 
 * @author 2Brands Media GmbH
 */
class RouteAgent
{
    private Database $db;
    private Config $config;
    private ?GeoAgent $geoAgent = null;
    private ?GoogleMapsAgent $googleMapsAgent = null;
    private string $apiKey;
    private string $apiUrl;
    private int $timeout = 10000; // 10 Sekunden
    private bool $useGoogleMaps = false;
    
    // Profile-Konstanten
    private const PROFILE_CAR = 'driving-car';
    private const PROFILE_TRUCK = 'driving-hgv';
    private const PROFILE_BIKE = 'cycling-regular';
    private const PROFILE_WALK = 'foot-walking';
    
    // Präferenz-Konstanten
    private const PREF_FASTEST = 'fastest';
    private const PREF_SHORTEST = 'shortest';
    private const PREF_RECOMMENDED = 'recommended';
    
    // Limits
    private const MAX_WAYPOINTS = 50;
    private const MAX_RADIUS_METERS = 200; // für Waypoint-Snapping

    public function __construct(Database $db, Config $config)
    {
        $this->db = $db;
        $this->config = $config;
        
        $this->apiKey = $this->config->get('api.ors_key', '');
        $this->apiUrl = $this->config->get('api.ors_url', 'https://api.openrouteservice.org/v2');
        
        // Google Maps bevorzugen wenn verfügbar
        $this->useGoogleMaps = !empty($this->config->get('google.maps_api_key'));
        
        if (!$this->useGoogleMaps && empty($this->apiKey)) {
            throw new Exception('Keine Routing-API konfiguriert');
        }
    }
    
    /**
     * GeoAgent Setter für Dependency Injection
     */
    public function setGeoAgent(GeoAgent $geoAgent): void
    {
        $this->geoAgent = $geoAgent;
    }
    
    /**
     * GoogleMapsAgent Setter für Dependency Injection
     */
    public function setGoogleMapsAgent(GoogleMapsAgent $googleMapsAgent): void
    {
        $this->googleMapsAgent = $googleMapsAgent;
    }

    /**
     * Berechnet Route für eine Playlist
     */
    public function calculateRoute(int $playlistId, array $options = []): array
    {
        // Stopps laden
        $stops = $this->getPlaylistStops($playlistId);
        
        if (count($stops) < 2) {
            throw new Exception('Mindestens 2 Stopps erforderlich für Routenberechnung');
        }
        
        if (count($stops) > self::MAX_WAYPOINTS) {
            throw new Exception('Maximal ' . self::MAX_WAYPOINTS . ' Stopps erlaubt');
        }
        
        // Google Maps verwenden wenn verfügbar und Traffic gewünscht
        if ($this->useGoogleMaps && $this->googleMapsAgent && $this->config->get('google.traffic_enabled')) {
            try {
                return $this->calculateRouteWithGoogleMaps($playlistId, $stops, $options);
            } catch (Exception $e) {
                // Fallback zu ORS
                error_log("Google Maps fehlgeschlagen, Fallback zu ORS: " . $e->getMessage());
            }
        }
        
        // OpenRouteService verwenden
        return $this->calculateRouteWithORS($playlistId, $stops, $options);
    }
    
    /**
     * Route mit Google Maps berechnen
     */
    private function calculateRouteWithGoogleMaps(int $playlistId, array $stops, array $options): array
    {
        // Waypoints vorbereiten
        $waypoints = [];
        foreach ($stops as $stop) {
            $waypoints[] = [
                'lat' => (float)$stop['latitude'],
                'lng' => (float)$stop['longitude'],
                'address' => $stop['address']
            ];
        }
        
        // Google Maps Optionen
        $gmOptions = [
            'mode' => $this->mapProfileToGoogleMode($options['profile'] ?? self::PROFILE_CAR),
            'departure_time' => $options['departure_time'] ?? $this->config->get('google.departure_time', 'now'),
            'traffic_model' => $options['traffic_model'] ?? $this->config->get('google.traffic_model', 'best_guess'),
            'alternatives' => $options['alternatives'] ?? $this->config->get('google.alternatives', false),
            'optimize' => $options['optimize'] ?? false
        ];
        
        // Route berechnen
        $gmResult = $this->googleMapsAgent->calculateRoute($waypoints, $gmOptions);
        
        // In unser Format konvertieren
        $result = $this->convertGoogleMapsResult($gmResult, $stops);
        
        // In Datenbank speichern
        $this->saveRouteData($playlistId, $result);
        
        return $result;
    }
    
    /**
     * Route mit OpenRouteService berechnen (Original-Methode)
     */
    private function calculateRouteWithORS(int $playlistId, array $stops, array $options): array
    {
        // Koordinaten sammeln
        $coordinates = [];
        foreach ($stops as $stop) {
            if (empty($stop['latitude']) || empty($stop['longitude'])) {
                throw new Exception("Stopp '{$stop['address']}' hat keine Koordinaten");
            }
            $coordinates[] = [(float)$stop['longitude'], (float)$stop['latitude']];
        }
        
        // Route berechnen
        $routeData = $this->requestRoute($coordinates, $options);
        
        // Ergebnis verarbeiten
        $result = $this->processRouteResponse($routeData, $stops);
        
        // In Datenbank speichern
        $this->saveRouteData($playlistId, $result);
        
        return $result;
    }

    /**
     * Optimiert die Reihenfolge der Stopps (TSP)
     */
    public function optimizeRoute(int $playlistId, array $options = []): array
    {
        $options['optimize'] = true;
        $options['roundtrip'] = $options['roundtrip'] ?? false;
        
        // Route mit Optimierung berechnen
        $result = $this->calculateRoute($playlistId, $options);
        
        // Optimierung speichern
        $this->saveOptimization($playlistId, $result);
        
        return $result;
    }

    /**
     * Berechnet Route ohne zu speichern (Preview)
     */
    public function previewRoute(array $coordinates, array $options = []): array
    {
        if (count($coordinates) < 2) {
            throw new Exception('Mindestens 2 Koordinaten erforderlich');
        }
        
        // Route berechnen
        $routeData = $this->requestRoute($coordinates, $options);
        
        // Basis-Verarbeitung ohne Stopp-Referenzen
        return [
            'total_distance' => $routeData['routes'][0]['summary']['distance'] ?? 0,
            'total_duration' => $routeData['routes'][0]['summary']['duration'] ?? 0,
            'geometry' => $routeData['routes'][0]['geometry'] ?? null,
            'bbox' => $routeData['bbox'] ?? null,
            'segments' => $this->extractSegments($routeData)
        ];
    }

    /**
     * Führt API-Request zu OpenRouteService durch
     */
    private function requestRoute(array $coordinates, array $options = []): array
    {
        $url = $this->apiUrl . '/directions/' . ($options['profile'] ?? self::PROFILE_CAR) . '/geojson';
        
        // Request-Body erstellen
        $body = [
            'coordinates' => $coordinates,
            'preference' => $options['preference'] ?? self::PREF_FASTEST,
            'units' => 'km',
            'language' => 'de',
            'geometry' => true,
            'instructions' => $options['instructions'] ?? false,
            'elevation' => false,
            'options' => [
                'avoid_features' => $options['avoid_features'] ?? [],
                'vehicle_type' => $options['vehicle_type'] ?? 'car'
            ]
        ];
        
        // Optimierung aktivieren
        if (!empty($options['optimize'])) {
            $body['optimize'] = true;
            $body['roundtrip'] = $options['roundtrip'] ?? false;
        }
        
        // Extra-Informationen
        if (!empty($options['extra_info'])) {
            $body['extra_info'] = $options['extra_info'];
        }
        
        // HTTP-Context erstellen
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'Authorization: ' . $this->apiKey
                ],
                'content' => json_encode($body),
                'timeout' => $this->timeout / 1000
            ]
        ]);
        
        // Request durchführen
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            $error = error_get_last();
            throw new Exception('ORS API Request fehlgeschlagen: ' . ($error['message'] ?? 'Unbekannter Fehler'));
        }
        
        // Response parsen
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Ungültige JSON-Antwort von ORS API');
        }
        
        if (isset($data['error'])) {
            throw new Exception('ORS API Fehler: ' . $data['error']['message']);
        }
        
        return $data;
    }

    /**
     * Verarbeitet die Route-Response
     */
    private function processRouteResponse(array $routeData, array $stops): array
    {
        if (empty($routeData['routes'][0])) {
            throw new Exception('Keine Route gefunden');
        }
        
        $route = $routeData['routes'][0];
        
        // Basis-Informationen
        $result = [
            'total_distance' => $route['summary']['distance'] ?? 0,
            'total_duration' => $route['summary']['duration'] ?? 0,
            'geometry' => $route['geometry'] ?? null,
            'bbox' => $routeData['bbox'] ?? null
        ];
        
        // Segmente verarbeiten
        $segments = $this->extractSegments($routeData);
        
        // Segments den Stopps zuordnen
        $result['stops'] = [];
        foreach ($stops as $index => $stop) {
            $stopData = [
                'id' => $stop['id'],
                'position' => $index + 1,
                'address' => $stop['address'],
                'latitude' => $stop['latitude'],
                'longitude' => $stop['longitude'],
                'work_duration' => $stop['work_duration']
            ];
            
            // Segment-Daten hinzufügen (zum nächsten Stopp)
            if (isset($segments[$index])) {
                $stopData['travel_duration'] = round($segments[$index]['duration'] / 60); // Minuten
                $stopData['distance'] = round($segments[$index]['distance'] * 1000); // Meter
            } else {
                $stopData['travel_duration'] = 0;
                $stopData['distance'] = 0;
            }
            
            $result['stops'][] = $stopData;
        }
        
        // Optimierte Reihenfolge
        if (isset($routeData['metadata']['query']['optimize']) && $routeData['metadata']['query']['optimize']) {
            $result['optimized'] = true;
            $result['optimized_order'] = $this->extractOptimizedOrder($routeData);
        }
        
        return $result;
    }

    /**
     * Extrahiert Segment-Informationen
     */
    private function extractSegments(array $routeData): array
    {
        $segments = [];
        
        if (isset($routeData['routes'][0]['segments'])) {
            foreach ($routeData['routes'][0]['segments'] as $segment) {
                $segments[] = [
                    'distance' => $segment['distance'] ?? 0,
                    'duration' => $segment['duration'] ?? 0
                ];
            }
        }
        
        return $segments;
    }

    /**
     * Extrahiert optimierte Reihenfolge
     */
    private function extractOptimizedOrder(array $routeData): array
    {
        // ORS gibt die optimierte Reihenfolge in der waypoint_order zurück
        if (isset($routeData['routes'][0]['waypoint_order'])) {
            return $routeData['routes'][0]['waypoint_order'];
        }
        
        // Fallback: Original-Reihenfolge
        $count = count($routeData['metadata']['query']['coordinates'] ?? []);
        return range(0, $count - 1);
    }

    /**
     * Speichert Route-Daten in Datenbank
     */
    private function saveRouteData(int $playlistId, array $routeData): void
    {
        // Transaktion starten
        $this->db->beginTransaction();
        
        try {
            // Playlist aktualisieren
            $updateData = [
                round($routeData['total_distance'] * 1000), // in Metern
                round($routeData['total_duration'] / 60), // in Minuten
                json_encode($routeData['geometry']),
                $playlistId
            ];
            
            // Traffic-Daten hinzufügen wenn vorhanden
            if (isset($routeData['total_duration_in_traffic'])) {
                $this->db->update(
                    'UPDATE playlists SET 
                        total_distance = ?,
                        total_travel_time = ?,
                        route_polyline = ?,
                        total_travel_time_in_traffic = ?,
                        total_traffic_delay = ?,
                        overall_traffic_severity = ?,
                        last_traffic_update = NOW()
                    WHERE id = ?',
                    [
                        round($routeData['total_distance'] * 1000),
                        round($routeData['total_duration'] / 60),
                        json_encode($routeData['geometry']),
                        round($routeData['total_duration_in_traffic'] / 60),
                        $routeData['traffic_delay'] ?? 0,
                        $routeData['traffic_severity'] ?? 'unknown',
                        $playlistId
                    ]
                );
            } else {
                $this->db->update(
                    'UPDATE playlists SET 
                        total_distance = ?,
                        total_travel_time = ?,
                        route_polyline = ?
                    WHERE id = ?',
                    $updateData
                );
            }
            
            // Stopps aktualisieren
            foreach ($routeData['stops'] as $stop) {
                if (isset($stop['travel_duration_in_traffic'])) {
                    $this->db->update(
                        'UPDATE stops SET 
                            travel_duration = ?,
                            distance = ?,
                            travel_duration_in_traffic = ?,
                            traffic_delay = ?,
                            traffic_status = ?
                        WHERE id = ?',
                        [
                            $stop['travel_duration'],
                            $stop['distance'],
                            $stop['travel_duration_in_traffic'],
                            $stop['traffic_delay'] ?? 0,
                            $stop['traffic_severity'] ?? 'unknown',
                            $stop['id']
                        ]
                    );
                } else {
                    $this->db->update(
                        'UPDATE stops SET 
                            travel_duration = ?,
                            distance = ?
                        WHERE id = ?',
                        [
                            $stop['travel_duration'],
                            $stop['distance'],
                            $stop['id']
                        ]
                    );
                }
            }
            
            // Traffic-History speichern wenn vorhanden
            if (isset($routeData['total_duration_in_traffic'])) {
                $this->saveTrafficHistory($playlistId, $routeData);
            }
            
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Speichert Optimierungs-Ergebnis
     */
    private function saveOptimization(int $playlistId, array $result): void
    {
        // Original-Daten laden
        $original = $this->db->selectOne(
            'SELECT total_distance, total_travel_time FROM playlists WHERE id = ?',
            [$playlistId]
        );
        
        if (!$original) {
            return;
        }
        
        // Optimierung speichern
        $this->db->insert(
            'INSERT INTO route_optimizations (
                playlist_id,
                original_order,
                optimized_order,
                original_duration,
                optimized_duration,
                original_distance,
                optimized_distance,
                optimization_type,
                algorithm_used,
                traffic_considered,
                execution_time_ms
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $playlistId,
                json_encode(range(0, count($result['stops']) - 1)),
                json_encode($result['optimized_order'] ?? []),
                $original['total_travel_time'] ?? 0,
                round($result['total_duration'] / 60),
                $original['total_distance'] ?? 0,
                round($result['total_distance'] * 1000),
                'balanced',
                'openrouteservice',
                0, // Kein Live-Traffic verfügbar
                null
            ]
        );
        
        // Playlist als optimiert markieren
        $this->db->update(
            'UPDATE playlists SET last_optimized_at = NOW() WHERE id = ?',
            [$playlistId]
        );
    }

    /**
     * Lädt Stopps einer Playlist
     */
    private function getPlaylistStops(int $playlistId): array
    {
        return $this->db->select(
            'SELECT id, position, address, latitude, longitude, work_duration 
            FROM stops 
            WHERE playlist_id = ? 
            ORDER BY position',
            [$playlistId]
        );
    }

    /**
     * Berechnet geschätzte Ankunftszeiten
     */
    public function calculateETAs(int $playlistId, string $startTime = 'now'): array
    {
        $playlist = $this->db->selectOne(
            'SELECT total_travel_time FROM playlists WHERE id = ?',
            [$playlistId]
        );
        
        if (!$playlist) {
            throw new Exception('Playlist nicht gefunden');
        }
        
        $stops = $this->getPlaylistStops($playlistId);
        $currentTime = strtotime($startTime);
        $etas = [];
        
        foreach ($stops as $stop) {
            // Ankunftszeit
            $etas[] = [
                'stop_id' => $stop['id'],
                'arrival_time' => date('H:i', $currentTime),
                'departure_time' => date('H:i', $currentTime + ($stop['work_duration'] * 60))
            ];
            
            // Zeit für Arbeit und Fahrt zum nächsten Stopp addieren
            $currentTime += ($stop['work_duration'] * 60);
            
            // Fahrtzeit aus DB laden
            $travel = $this->db->selectOne(
                'SELECT travel_duration FROM stops WHERE id = ?',
                [$stop['id']]
            );
            
            if ($travel && $travel['travel_duration']) {
                $currentTime += ($travel['travel_duration'] * 60);
            }
        }
        
        return [
            'start_time' => $startTime,
            'end_time' => date('H:i', $currentTime),
            'total_duration' => round(($currentTime - strtotime($startTime)) / 60),
            'etas' => $etas
        ];
    }

    /**
     * Gibt verfügbare Routing-Profile zurück
     */
    public function getAvailableProfiles(): array
    {
        return [
            self::PROFILE_CAR => 'PKW',
            self::PROFILE_TRUCK => 'LKW',
            self::PROFILE_BIKE => 'Fahrrad',
            self::PROFILE_WALK => 'Zu Fuß'
        ];
    }

    /**
     * Gibt verfügbare Präferenzen zurück
     */
    public function getAvailablePreferences(): array
    {
        return [
            self::PREF_FASTEST => 'Schnellste Route',
            self::PREF_SHORTEST => 'Kürzeste Route',
            self::PREF_RECOMMENDED => 'Empfohlene Route'
        ];
    }
    
    /**
     * Konvertiert ORS-Profil zu Google Maps Mode
     */
    private function mapProfileToGoogleMode(string $profile): string
    {
        $mapping = [
            self::PROFILE_CAR => 'driving',
            self::PROFILE_TRUCK => 'driving', // Google unterscheidet nicht
            self::PROFILE_BIKE => 'bicycling',
            self::PROFILE_WALK => 'walking'
        ];
        
        return $mapping[$profile] ?? 'driving';
    }
    
    /**
     * Konvertiert Google Maps Result in unser Format
     */
    private function convertGoogleMapsResult(array $gmResult, array $stops): array
    {
        $result = [
            'total_distance' => $gmResult['total_distance_km'] ?? 0,
            'total_duration' => $gmResult['total_duration_min'] ?? 0,
            'total_duration_in_traffic' => $gmResult['total_duration_in_traffic_min'] ?? null,
            'traffic_delay' => $gmResult['total_traffic_delay'] ?? 0,
            'traffic_severity' => $gmResult['traffic_severity'] ?? 'unknown',
            'geometry' => $gmResult['polyline'] ?? null,
            'bbox' => $gmResult['bounds'] ?? null,
            'stops' => []
        ];
        
        // Stopps mit Segment-Daten anreichern
        foreach ($stops as $index => $stop) {
            $stopData = [
                'id' => $stop['id'],
                'position' => $index + 1,
                'address' => $stop['address'],
                'latitude' => $stop['latitude'],
                'longitude' => $stop['longitude'],
                'work_duration' => $stop['work_duration']
            ];
            
            // Segment-Daten hinzufügen
            if (isset($gmResult['segments'][$index])) {
                $segment = $gmResult['segments'][$index];
                $stopData['travel_duration'] = round($segment['duration'] / 60);
                $stopData['travel_duration_in_traffic'] = isset($segment['duration_in_traffic']) 
                    ? round($segment['duration_in_traffic'] / 60) 
                    : null;
                $stopData['distance'] = $segment['distance'];
                $stopData['traffic_delay'] = $segment['traffic_delay'] ?? 0;
                $stopData['traffic_severity'] = $segment['traffic_severity'] ?? 'unknown';
            } else {
                $stopData['travel_duration'] = 0;
                $stopData['distance'] = 0;
            }
            
            $result['stops'][] = $stopData;
        }
        
        // Optimierte Reihenfolge
        if (!empty($gmResult['waypoint_order'])) {
            $result['optimized'] = true;
            $result['optimized_order'] = $gmResult['waypoint_order'];
        }
        
        return $result;
    }
    
    /**
     * Prüft ob Google Maps verfügbar ist
     */
    public function isGoogleMapsAvailable(): bool
    {
        return $this->useGoogleMaps && $this->googleMapsAgent !== null;
    }
    
    /**
     * Gibt Traffic-Informationen zurück
     */
    public function getTrafficInfo(int $playlistId): ?array
    {
        if (!$this->isGoogleMapsAvailable()) {
            return null;
        }
        
        $playlist = $this->db->selectOne(
            'SELECT total_distance, total_travel_time, route_polyline FROM playlists WHERE id = ?',
            [$playlistId]
        );
        
        if (!$playlist || empty($playlist['route_polyline'])) {
            return null;
        }
        
        // Stopps für Traffic-Update laden
        $stops = $this->getPlaylistStops($playlistId);
        if (count($stops) < 2) {
            return null;
        }
        
        try {
            // Nur Traffic-Update abrufen
            $waypoints = array_map(function($stop) {
                return [
                    'lat' => (float)$stop['latitude'],
                    'lng' => (float)$stop['longitude']
                ];
            }, $stops);
            
            $result = $this->googleMapsAgent->calculateRoute($waypoints, [
                'mode' => 'driving',
                'departure_time' => 'now'
            ]);
            
            return [
                'current_duration_min' => $result['total_duration_in_traffic_min'],
                'normal_duration_min' => $result['total_duration_min'],
                'traffic_delay_min' => round(($result['total_traffic_delay'] ?? 0) / 60),
                'traffic_severity' => $result['traffic_severity'] ?? 'unknown',
                'segments' => array_map(function($segment) {
                    return [
                        'traffic_severity' => $segment['traffic_severity'] ?? 'unknown',
                        'delay_seconds' => $segment['traffic_delay'] ?? 0
                    ];
                }, $result['segments'] ?? [])
            ];
            
        } catch (Exception $e) {
            error_log("Traffic-Update fehlgeschlagen: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Speichert Traffic-History
     */
    private function saveTrafficHistory(int $playlistId, array $routeData): void
    {
        try {
            $this->db->insert(
                'INSERT INTO traffic_history (
                    playlist_id,
                    total_duration_normal,
                    total_duration_in_traffic,
                    traffic_delay,
                    traffic_severity,
                    segment_data
                ) VALUES (?, ?, ?, ?, ?, ?)',
                [
                    $playlistId,
                    round($routeData['total_duration'] / 60),
                    round($routeData['total_duration_in_traffic'] / 60),
                    $routeData['traffic_delay'] ?? 0,
                    $routeData['traffic_severity'] ?? 'unknown',
                    json_encode($routeData['stops'] ?? [])
                ]
            );
        } catch (Exception $e) {
            // Fehler loggen aber nicht werfen (History ist nicht kritisch)
            error_log("Traffic-History konnte nicht gespeichert werden: " . $e->getMessage());
        }
    }
}