<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Agents\RouteAgent;
use App\Agents\PlaylistAgent;
use App\Middleware\AuthMiddleware;

/**
 * RouteController - API-Endpunkte für Routenberechnung
 * 
 * @author 2Brands Media GmbH
 */
class RouteController extends BaseController
{
    private RouteAgent $routeAgent;
    private PlaylistAgent $playlistAgent;

    public function __construct()
    {
        parent::__construct();
        $this->routeAgent = $this->container->get('route');
        $this->playlistAgent = $this->container->get('playlist');
        
        // Authentifizierung erforderlich
        $this->middleware(AuthMiddleware::class);
    }

    /**
     * Berechnet Route für eine Playlist
     * 
     * POST /api/routes/calculate/{playlistId}
     */
    public function calculate(Request $request, Response $response, array $params): Response
    {
        $playlistId = (int) $params['playlistId'];
        $data = $request->json();
        
        // Playlist-Zugriff prüfen
        if (!$this->hasPlaylistAccess($playlistId)) {
            return $response->json([
                'error' => true,
                'message' => 'Keine Berechtigung für diese Playlist'
            ], 403);
        }
        
        try {
            // Optionen vorbereiten
            $options = [
                'profile' => $data['profile'] ?? 'driving-car',
                'preference' => $data['preference'] ?? 'fastest',
                'avoid_features' => $data['avoid_features'] ?? [],
                'instructions' => $data['instructions'] ?? false
            ];
            
            // Route berechnen
            $result = $this->playlistAgent->calculateRoute($playlistId, $options);
            
            return $response->json([
                'success' => true,
                'data' => [
                    'playlist_id' => $playlistId,
                    'total_distance' => round($result['total_distance'], 2),
                    'total_distance_km' => round($result['total_distance'], 2),
                    'total_duration' => round($result['total_duration'] / 60, 0),
                    'total_duration_min' => round($result['total_duration'] / 60, 0),
                    'total_duration_in_traffic_min' => $result['total_duration_in_traffic'] ?? null,
                    'traffic_delay_min' => isset($result['traffic_delay']) ? round($result['traffic_delay'] / 60, 0) : null,
                    'traffic_severity' => $result['traffic_severity'] ?? null,
                    'geometry' => $result['geometry'] ?? null,
                    'bbox' => $result['bbox'] ?? null,
                    'using_google_maps' => $this->routeAgent->isGoogleMapsAvailable(),
                    'stops' => array_map(function($stop) {
                        return [
                            'id' => $stop['id'],
                            'position' => $stop['position'],
                            'address' => $stop['address'],
                            'travel_duration_min' => $stop['travel_duration'],
                            'travel_duration_in_traffic_min' => $stop['travel_duration_in_traffic'] ?? null,
                            'distance_m' => $stop['distance'],
                            'work_duration_min' => $stop['work_duration'],
                            'traffic_severity' => $stop['traffic_severity'] ?? null
                        ];
                    }, $result['stops'] ?? [])
                ]
            ]);
            
        } catch (\Exception $e) {
            return $response->json([
                'error' => true,
                'message' => 'Routenberechnung fehlgeschlagen: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Optimiert Route für eine Playlist
     * 
     * POST /api/routes/optimize/{playlistId}
     */
    public function optimize(Request $request, Response $response, array $params): Response
    {
        $playlistId = (int) $params['playlistId'];
        $data = $request->json();
        
        // Playlist-Zugriff prüfen
        if (!$this->hasPlaylistAccess($playlistId)) {
            return $response->json([
                'error' => true,
                'message' => 'Keine Berechtigung für diese Playlist'
            ], 403);
        }
        
        try {
            // Optionen vorbereiten
            $options = [
                'profile' => $data['profile'] ?? 'driving-car',
                'preference' => $data['preference'] ?? 'fastest',
                'roundtrip' => $data['roundtrip'] ?? false,
                'avoid_features' => $data['avoid_features'] ?? []
            ];
            
            // Route optimieren
            $result = $this->playlistAgent->optimizeRoute($playlistId, $options);
            
            // Optimierungs-Statistiken berechnen
            $savings = [
                'distance_saved_km' => 0,
                'duration_saved_min' => 0,
                'percentage_saved' => 0
            ];
            
            if (!empty($result['optimized'])) {
                // Savings werden vom RouteAgent in der DB gespeichert
                $optimization = $this->getLatestOptimization($playlistId);
                if ($optimization) {
                    $savings = [
                        'distance_saved_km' => round($optimization['savings_distance'] / 1000, 2),
                        'duration_saved_min' => $optimization['savings_duration'],
                        'percentage_saved' => round($optimization['savings_percentage'], 1)
                    ];
                }
            }
            
            return $response->json([
                'success' => true,
                'data' => [
                    'playlist_id' => $playlistId,
                    'optimized' => $result['optimized'] ?? false,
                    'total_distance_km' => round($result['total_distance'], 2),
                    'total_duration_min' => round($result['total_duration'] / 60, 0),
                    'savings' => $savings,
                    'optimized_order' => $result['optimized_order'] ?? [],
                    'geometry' => $result['geometry'] ?? null,
                    'stops' => array_map(function($stop) {
                        return [
                            'id' => $stop['id'],
                            'position' => $stop['position'],
                            'address' => $stop['address'],
                            'travel_duration_min' => $stop['travel_duration'],
                            'distance_m' => $stop['distance']
                        ];
                    }, $result['stops'] ?? [])
                ]
            ]);
            
        } catch (\Exception $e) {
            return $response->json([
                'error' => true,
                'message' => 'Routenoptimierung fehlgeschlagen: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Vorschau einer Route ohne zu speichern
     * 
     * POST /api/routes/preview
     */
    public function preview(Request $request, Response $response): Response
    {
        $data = $request->json();
        
        // Validierung
        if (empty($data['coordinates']) || !is_array($data['coordinates'])) {
            return $response->json([
                'error' => true,
                'message' => 'Koordinaten-Array erforderlich'
            ], 400);
        }
        
        if (count($data['coordinates']) < 2) {
            return $response->json([
                'error' => true,
                'message' => 'Mindestens 2 Koordinaten erforderlich'
            ], 400);
        }
        
        try {
            // Optionen vorbereiten
            $options = [
                'profile' => $data['profile'] ?? 'driving-car',
                'preference' => $data['preference'] ?? 'fastest',
                'optimize' => $data['optimize'] ?? false,
                'roundtrip' => $data['roundtrip'] ?? false
            ];
            
            // Vorschau berechnen
            $result = $this->routeAgent->previewRoute($data['coordinates'], $options);
            
            return $response->json([
                'success' => true,
                'data' => [
                    'total_distance_km' => round($result['total_distance'], 2),
                    'total_duration_min' => round($result['total_duration'] / 60, 0),
                    'geometry' => $result['geometry'] ?? null,
                    'bbox' => $result['bbox'] ?? null,
                    'segments' => array_map(function($segment) {
                        return [
                            'distance_km' => round($segment['distance'], 2),
                            'duration_min' => round($segment['duration'] / 60, 1)
                        ];
                    }, $result['segments'] ?? [])
                ]
            ]);
            
        } catch (\Exception $e) {
            return $response->json([
                'error' => true,
                'message' => 'Routenvorschau fehlgeschlagen: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Berechnet geschätzte Ankunftszeiten
     * 
     * GET /api/routes/etas/{playlistId}
     */
    public function etas(Request $request, Response $response, array $params): Response
    {
        $playlistId = (int) $params['playlistId'];
        $startTime = $request->query('start_time', date('Y-m-d H:i:s'));
        
        // Playlist-Zugriff prüfen
        if (!$this->hasPlaylistAccess($playlistId)) {
            return $response->json([
                'error' => true,
                'message' => 'Keine Berechtigung für diese Playlist'
            ], 403);
        }
        
        try {
            $etas = $this->routeAgent->calculateETAs($playlistId, $startTime);
            
            return $response->json([
                'success' => true,
                'data' => $etas
            ]);
            
        } catch (\Exception $e) {
            return $response->json([
                'error' => true,
                'message' => 'ETA-Berechnung fehlgeschlagen: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Gibt verfügbare Routing-Profile zurück
     * 
     * GET /api/routes/profiles
     */
    public function profiles(Request $request, Response $response): Response
    {
        $profiles = $this->routeAgent->getAvailableProfiles();
        
        return $response->json([
            'success' => true,
            'data' => array_map(function($name, $key) {
                return [
                    'key' => $key,
                    'name' => $name,
                    'icon' => $this->getProfileIcon($key)
                ];
            }, $profiles, array_keys($profiles))
        ]);
    }

    /**
     * Prüft Zugriff auf Playlist
     */
    private function hasPlaylistAccess(int $playlistId): bool
    {
        $playlist = $this->playlistAgent->getPlaylist($playlistId);
        
        if (!$playlist) {
            return false;
        }
        
        // Admin hat immer Zugriff
        if ($this->auth->isAdmin()) {
            return true;
        }
        
        // Eigene Playlists
        return $playlist['user_id'] === $this->auth->user()['id'];
    }

    /**
     * Lädt letzte Optimierung
     */
    private function getLatestOptimization(int $playlistId): ?array
    {
        return $this->db->selectOne(
            'SELECT * FROM route_optimizations 
            WHERE playlist_id = ? 
            ORDER BY created_at DESC 
            LIMIT 1',
            [$playlistId]
        );
    }

    /**
     * Gibt Icon für Profil zurück
     */
    private function getProfileIcon(string $profile): string
    {
        $icons = [
            'driving-car' => 'car',
            'driving-hgv' => 'truck',
            'cycling-regular' => 'bicycle',
            'foot-walking' => 'walking'
        ];
        
        return $icons[$profile] ?? 'route';
    }
    
    /**
     * Gibt aktuelle Traffic-Informationen zurück
     * 
     * GET /api/routes/traffic/{playlistId}
     */
    public function traffic(Request $request, Response $response, array $params): Response
    {
        $playlistId = (int) $params['playlistId'];
        
        // Playlist-Zugriff prüfen
        if (!$this->hasPlaylistAccess($playlistId)) {
            return $response->json([
                'error' => true,
                'message' => 'Keine Berechtigung für diese Playlist'
            ], 403);
        }
        
        // Traffic-Info abrufen
        $trafficInfo = $this->routeAgent->getTrafficInfo($playlistId);
        
        if ($trafficInfo === null) {
            return $response->json([
                'success' => false,
                'message' => 'Keine Traffic-Daten verfügbar'
            ]);
        }
        
        return $response->json([
            'success' => true,
            'data' => $trafficInfo
        ]);
    }
}