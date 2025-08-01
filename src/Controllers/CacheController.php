<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Agents\GoogleMapsAgent;
use App\Agents\GeoAgent;
use App\Middleware\AuthMiddleware;

/**
 * CacheController - Cache-Management für Admins
 * 
 * @author 2Brands Media GmbH
 */
class CacheController extends BaseController
{
    private ?GoogleMapsAgent $googleMapsAgent = null;
    private GeoAgent $geoAgent;

    public function __construct()
    {
        parent::__construct();
        
        // Agents laden
        $this->geoAgent = $this->container->get('geo');
        
        if ($this->container->has('google_maps')) {
            $this->googleMapsAgent = $this->container->get('google_maps');
        }
        
        // Nur Admins haben Zugriff
        $this->middleware(AuthMiddleware::class);
    }

    /**
     * Cache-Statistiken anzeigen
     * 
     * GET /api/cache/stats
     */
    public function stats(Request $request, Response $response): Response
    {
        if (!$this->auth->isAdmin()) {
            return $response->json([
                'error' => true,
                'message' => 'Nur Administratoren haben Zugriff'
            ], 403);
        }
        
        $stats = [
            'geocoding' => $this->geoAgent->getCacheStats(),
            'google_maps' => null
        ];
        
        if ($this->googleMapsAgent) {
            $stats['google_maps'] = $this->googleMapsAgent->getCacheStats();
        }
        
        // Gesamt-Cache-Statistiken
        $totalStats = $this->db->selectOne(
            'SELECT 
                COUNT(*) as total_entries,
                SUM(LENGTH(data)) as total_size_bytes,
                COUNT(CASE WHEN expires_at < NOW() THEN 1 END) as expired_entries,
                COUNT(CASE WHEN expires_at > NOW() THEN 1 END) as active_entries
             FROM cache'
        );
        
        $stats['total'] = [
            'total_entries' => (int)$totalStats['total_entries'],
            'active_entries' => (int)$totalStats['active_entries'],
            'expired_entries' => (int)$totalStats['expired_entries'],
            'total_size_mb' => round(($totalStats['total_size_bytes'] ?? 0) / 1024 / 1024, 2)
        ];
        
        return $response->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Cache bereinigen
     * 
     * POST /api/cache/clean
     */
    public function clean(Request $request, Response $response): Response
    {
        if (!$this->auth->isAdmin()) {
            return $response->json([
                'error' => true,
                'message' => 'Nur Administratoren haben Zugriff'
            ], 403);
        }
        
        $data = $request->json();
        $type = $data['type'] ?? 'expired';
        
        $deletedCount = 0;
        
        switch ($type) {
            case 'expired':
                // Nur abgelaufene Einträge
                $deletedCount = $this->db->delete(
                    'DELETE FROM cache WHERE expires_at < NOW()'
                );
                break;
                
            case 'geocoding':
                // Geocoding-Cache
                $deletedCount = $this->geoAgent->cleanCache();
                break;
                
            case 'google_maps':
                // Google Maps Cache
                if ($this->googleMapsAgent) {
                    $deletedCount = $this->googleMapsAgent->cleanCache();
                }
                break;
                
            case 'all':
                // Gesamter Cache
                if ($this->auth->isSuperAdmin()) {
                    $deletedCount = $this->db->delete('DELETE FROM cache');
                } else {
                    return $response->json([
                        'error' => true,
                        'message' => 'Nur Super-Admins können den gesamten Cache leeren'
                    ], 403);
                }
                break;
                
            case 'playlist':
                // Cache für spezifische Playlist
                if (!empty($data['playlist_id'])) {
                    if ($this->googleMapsAgent) {
                        $deletedCount += $this->googleMapsAgent->clearPlaylistCache((int)$data['playlist_id']);
                    }
                }
                break;
                
            default:
                return $response->json([
                    'error' => true,
                    'message' => 'Ungültiger Cache-Typ'
                ], 400);
        }
        
        return $response->json([
            'success' => true,
            'data' => [
                'deleted_count' => $deletedCount,
                'type' => $type
            ]
        ]);
    }

    /**
     * Cache-Einträge durchsuchen
     * 
     * GET /api/cache/search
     */
    public function search(Request $request, Response $response): Response
    {
        if (!$this->auth->isAdmin()) {
            return $response->json([
                'error' => true,
                'message' => 'Nur Administratoren haben Zugriff'
            ], 403);
        }
        
        $query = $request->query('q', '');
        $type = $request->query('type', '');
        $limit = min((int)$request->query('limit', 50), 100);
        
        $where = ['1=1'];
        $params = [];
        
        if ($query) {
            $where[] = '(cache_key LIKE ? OR data LIKE ?)';
            $params[] = '%' . $query . '%';
            $params[] = '%' . $query . '%';
        }
        
        if ($type) {
            $where[] = 'cache_key LIKE ?';
            $params[] = $type . '_%';
        }
        
        $entries = $this->db->select(
            'SELECT 
                cache_key,
                LENGTH(data) as size_bytes,
                created_at,
                expires_at,
                CASE WHEN expires_at > NOW() THEN 1 ELSE 0 END as is_active
             FROM cache
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY created_at DESC
             LIMIT ?',
            array_merge($params, [$limit])
        );
        
        return $response->json([
            'success' => true,
            'data' => array_map(function($entry) {
                return [
                    'key' => $entry['cache_key'],
                    'size_kb' => round($entry['size_bytes'] / 1024, 2),
                    'created_at' => $entry['created_at'],
                    'expires_at' => $entry['expires_at'],
                    'is_active' => (bool)$entry['is_active'],
                    'type' => $this->extractCacheType($entry['cache_key'])
                ];
            }, $entries)
        ]);
    }

    /**
     * Cache-Typ aus Key extrahieren
     */
    private function extractCacheType(string $key): string
    {
        if (str_starts_with($key, 'gmaps_directions')) return 'google_maps_route';
        if (str_starts_with($key, 'gmaps_geocode')) return 'google_maps_geocode';
        if (str_starts_with($key, 'gmaps_matrix')) return 'google_maps_matrix';
        if (str_starts_with($key, 'geo_')) return 'geocoding';
        return 'other';
    }
}